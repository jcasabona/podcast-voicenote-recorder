<?php
/**
 * Plugin Name: Podcast Voicenote Recorder
 * Description: A simple, client-side WebM audio recorder for collecting listener voice submissions.
 * Version: 2.4
 * Author: Gemini
 * Author URI: https://google.com
 * License: GPL2
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. --- Define Upload Handler Location (for the JavaScript) ---
// This assumes the voicenotes-upload-handler.php is in the same plugin directory.
$upload_handler_url = plugin_dir_url( __FILE__ ) . 'voicenotes-upload-handler.php';

/**
 * Creates the dedicated upload directory upon plugin activation.
 */
function pvr_activate() {
    $upload_dir = wp_upload_dir();
    $target_dir = $upload_dir['basedir'] . '/podcast_voicenotes';

    if ( ! is_dir( $target_dir ) ) {
        if ( ! wp_mkdir_p( $target_dir ) ) {
            error_log( 'Podcast Voicenote Recorder: Failed to create upload directory: ' . $target_dir );
        }
    }
}
register_activation_hook( __FILE__, 'pvr_activate' );

// =========================================================
// == ADMIN INTERFACE ======================================
// =========================================================

/**
 * Add the main menu item for managing voicenotes.
 */
function pvr_add_admin_menu() {
    add_menu_page(
        'Voicenote Submissions', 
        'Voicenotes', 
        'manage_options', 
        'podcast_voicenotes', 
        'pvr_voicenote_page', 
        'dashicons-microphone', // WordPress icon for microphone
        6 
    );
}
add_action( 'admin_menu', 'pvr_add_admin_menu' );

/**
 * Handle file deletion request.
 */
function pvr_handle_delete_voicenote() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    
    // Check if the delete action is requested and security nonce is valid
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete_voicenote' && isset( $_GET['file'] ) ) {
        check_admin_referer( 'pvr_delete_voicenote_nonce' );

        $filename = sanitize_file_name( wp_unslash( $_GET['file'] ) );
        $upload_dir_info = wp_upload_dir();
        $target_dir = $upload_dir_info['basedir'] . '/podcast_voicenotes/';
        $file_path = $target_dir . $filename;

        // Check file path validity to prevent directory traversal
        if ( strpos( $file_path, $target_dir ) !== 0 ) {
            // Error handling for invalid path
            wp_die( 'Invalid file path.' );
        }
        
        if ( file_exists( $file_path ) && unlink( $file_path ) ) {
            wp_redirect( admin_url( 'admin.php?page=podcast_voicenotes&message=1' ) );
            exit;
        } else {
            wp_die( 'Error deleting file.' );
        }
    }
}
add_action( 'admin_init', 'pvr_handle_delete_voicenote' );


/**
 * Renders the main admin page content (File List).
 */
function pvr_voicenote_page() {
    $upload_dir_info = wp_upload_dir();
    $target_dir = $upload_dir_info['basedir'] . '/podcast_voicenotes/';
    $target_url = $upload_dir_info['baseurl'] . '/podcast_voicenotes/';
    
    // Display delete success message
    if ( isset( $_GET['message'] ) && $_GET['message'] === '1' ) {
        echo '<div class="notice notice-success is-dismissible"><p>Voicenote deleted successfully.</p></div>';
    }

    echo '<div class="wrap">';
    echo '<h1>Voicenote Submissions</h1>';
    echo '<p>Review, listen to, and manage the voice messages submitted by your podcast listeners.</p>';

    // --- File Scanning and Table Generation ---
    if ( is_dir( $target_dir ) ) {
        $files = array_diff( scandir( $target_dir ), array( '.', '..', '.htaccess' ) );
        $voicenotes = [];
        
        // Retrieve the WordPress date format settings
        $date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        $site_timezone = wp_timezone_string(); // e.g., 'America/New_York'
        $server_timezone = date_default_timezone_get(); // Server's base timezone (often UTC or Europe/London)

        foreach ( $files as $file ) {
            if ( pathinfo( $file, PATHINFO_EXTENSION ) === 'webm' ) {
                $file_time_raw = filemtime( $target_dir . $file );
                
                // --- GUARANTEED TIMEZONE FIX ---
                // 1. Create a DateTime object using the raw server timestamp and the server's detected timezone.
                $datetime = new DateTime('@' . $file_time_raw);
                $datetime->setTimezone(new DateTimeZone($server_timezone));

                // 2. Convert the DateTime object to the site's configured timezone.
                $datetime->setTimezone(new DateTimeZone($site_timezone));

                // 3. Format the time using the standard WP format settings.
                $formatted_date = $datetime->format($date_format);
                // --------------------------------------------------------------------------------

                $voicenotes[] = [
                    'filename' => $file,
                    'url' => $target_url . $file,
                    'size' => size_format( filesize( $target_dir . $file ) ),
                    'date' => $formatted_date,
                    'timestamp' => $file_time_raw,
                ];
            }
        }
    }

    if ( empty( $voicenotes ) ) {
        echo '<p>No voicenote submissions found yet.</p>';
        echo '</div>';
        return;
    }

    // Sort by newest first
    usort( $voicenotes, function( $a, $b ) {
        return $b['timestamp'] <=> $a['timestamp'];
    });

    echo '<table class="wp-list-table widefat striped">';
    echo '<thead><tr><th>Date Submitted</th><th>Filename</th><th>Size</th><th>Playback / Actions</th></tr></thead>';
    echo '<tbody>';

    foreach ( $voicenotes as $note ) {
        $delete_url = wp_nonce_url( 
            admin_url( 'admin.php?page=podcast_voicenotes&action=delete_voicenote&file=' . urlencode( $note['filename'] ) ), 
            'pvr_delete_voicenote_nonce' 
        );

        echo '<tr>';
        echo '<td>' . esc_html( $note['date'] ) . '</td>';
        echo '<td>' . esc_html( $note['filename'] ) . '</td>';
        echo '<td>' . esc_html( $note['size'] ) . '</td>';
        echo '<td>';
        
        // Audio Player (Modern browsers support WebM directly)
        echo '<audio controls src="' . esc_url( $note['url'] ) . '" style="width: 100%; max-width: 300px; margin-right: 15px;"></audio>';
        
        // Action Links
        echo '<div style="margin-top: 5px;">';
        echo '<a href="' . esc_url( $note['url'] ) . '" class="button button-secondary" download>Download</a> | ';
        // NOTE: We are intentionally using onclick="return confirm(...)" here as the WordPress standard in the admin area.
        echo '<a href="' . esc_url( $delete_url ) . '" class="submitdelete" onclick="return confirm(\'Are you sure you want to delete this voicenote? This action cannot be undone.\')">Delete</a>';
        echo '</div>';

        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
}


// =========================================================
// == FRONTEND RENDER (Max Duration Implemented) ===========
// =========================================================

/**
 * Renders the main HTML/JS recorder interface via a shortcode.
 *
 * Usage: [podcast_voicenote_recorder]
 */
function pvr_render_recorder_shortcode() {
    global $upload_handler_url;

    // Start output buffering to capture the HTML/JS content
    ob_start();

    // The entire HTML, CSS, and JavaScript from recorder.html is embedded here.
    // PHP variables are used to set the dynamic UPLOAD_ENDPOINT.
    ?>
    <div class="pvr-recorder-wrapper" style="background-color: #F7F4EB;">
        <style>
            /* Custom styles for waveform effect */
            .waveform {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 50px;
            }
            .bar {
                width: 4px;
                height: 5px;
                margin: 0 2px;
                /* Brand color for waveform bars */
                background-color: #F7D677; 
                animation: pulse 0.5s ease-in-out infinite alternate;
                border-radius: 9999px;
                transform-origin: bottom;
                opacity: 0.8;
            }
            .recording .bar {
                /* Randomize start height and speed for a natural look */
                animation: pulse 0.5s ease-in-out infinite alternate;
            }
            .recording .bar:nth-child(1) { animation-delay: 0.0s; height: 10px; }
            .recording .bar:nth-child(2) { animation-delay: 0.1s; height: 15px; }
            .recording .bar:nth-child(3) { animation-delay: 0.2s; height: 20px; }
            .recording .bar:nth-child(4) { animation-delay: 0.3s; height: 18px; }
            .recording .bar:nth-child(5) { animation-delay: 0.4s; height: 25px; }
            .recording .bar:nth-child(6) { animation-delay: 0.5s; height: 12px; }
            .recording .bar:nth-child(7) { animation-delay: 0.6s; height: 30px; }
            .recording .bar:nth-child(8) { animation-delay: 0.7s; height: 16px; }
            .recording .bar:nth-child(9) { animation-delay: 0.8s; height: 22px; }
            .recording .bar:nth-child(10) { animation-delay: 0.9s; height: 11px; }

            @keyframes pulse {
                0% { transform: scaleY(0.1); }
                100% { transform: scaleY(1.0); }
            }
            .not-recording .bar {
                animation: none;
                height: 5px; /* Minimal height when not recording */
            }
            /* Ensure the recorder is centered and styled nicely within the WP content area */
            .pvr-recorder-wrapper {
                display: flex;
                justify-content: center;
                padding: 1rem 0;
                min-height: 50vh; 
                align-items: center;
            }
            /* Specific overrides for text colors to match branding */
            .pvr-text-accent { color: #F7D677; }
            .pvr-border-primary { border-color: rgba(8, 44, 69, 0.1); }
            .pvr-bg-primary { background-color: #082C45; }
            .pvr-bg-accent { background-color: #F7D677; }
            .pvr-text-contrast { color: #082C45; } /* Dark text on gold/light background */
        </style>

        <script src="https://cdn.tailwindcss.com"></script>
        
        <div id="recorder-container" class="w-full max-w-lg p-6 md:p-10 rounded-xl shadow-2xl border pvr-border-primary pvr-bg-primary">

            <h1 class="text-3xl font-extrabold mb-2 text-center pvr-text-accent">Voice Message Recorder</h1>
            <p class="text-center text-sm mb-8 pvr-text-accent">Maximum recording time: 5 minutes.</p>

            <!-- Waveform and Status Display -->
            <div class="mb-8 text-center">
                <div id="waveform" class="waveform recording-status not-recording">
                    <div class="bar"></div>
                    <div class="bar"></div>
                    <div class="bar"></div>
                    <div class="bar"></div>
                    <div class="bar"></div>
                    <div class="bar"></div>
                    <div class="bar"></div>
                    <div class="bar"></div>
                    <div class="bar"></div>
                    <div class="bar"></div>
                </div>
                <p id="status-message" class="text-lg font-semibold mt-4 pvr-text-accent">Ready to record</p>
                <p id="timer" class="text-2xl font-mono mt-1 pvr-text-accent">00:00</p>
            </div>

            <!-- Controls -->
            <div class="flex justify-center space-x-4">
                <!-- Start/Stop Button -->
                <button id="record-button" class="flex items-center justify-center p-4 rounded-full shadow-lg transition-all duration-300 transform active:scale-95 w-20 h-20 pvr-bg-accent pvr-text-contrast">
                    <!-- New Mic Icon (Start Recording) -->
                    <svg id="mic-icon" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-2c0 3.03-2.43 5.5-5.3 5.5S6.7 15.03 6.7 12H5c0 3.53 2.61 6.43 6 6.9V21h2v-2.1c3.39-.47 6-3.37 6-6.9h-1.7z"/>
                    </svg>
                    <!-- New Stop Icon (Stop Recording) -->
                    <svg id="stop-icon" xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 hidden" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="7" y="7" width="10" height="10" rx="2" ry="2"/>
                    </svg>
                </button>
            </div>

            <!-- Audio Playback and Actions (Hidden until recording is stopped) -->
            <div id="playback-area" class="mt-8 pt-6 border-t pvr-border-primary hidden">
                <h2 class="text-xl font-bold mb-4 text-center pvr-text-accent">Review Recording</h2>
                <audio id="audio-playback" controls class="w-full rounded-lg shadow-inner"></audio>

                <div class="flex justify-center mt-6">
                    <!-- Upload Button -->
                    <button id="upload-button" class="w-full flex items-center justify-center px-4 py-3 font-semibold rounded-lg shadow-md transition-colors duration-200 pvr-bg-accent pvr-text-contrast hover:opacity-80">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242M12 12v9"></path>
                            <line x1="16" y1="16" x2="12" y2="12"></line>
                            <line x1="8" y1="16" x2="12" y2="12"></line>
                        </svg>
                        Submit Voicenote
                    </button>
                </div>

                <button id="new-record-button" class="w-full mt-4 flex items-center justify-center px-4 py-2 bg-gray-200 text-gray-700 font-semibold rounded-lg shadow-sm hover:bg-gray-300 transition-colors duration-200">
                    Record New Message
                </button>
            </div>

            <!-- Error/Success Message Box -->
            <div id="message-box" class="mt-4 p-3 rounded-lg hidden" role="alert">
                <p id="message-text" class="font-medium"></p>
            </div>

        </div>

        <script>
            // --- CONFIGURATION ---
            const MAX_RECORDING_SECONDS = 300; // 5 minutes
            const MAX_RECORDING_MESSAGE = 'Maximum recording time (5:00) reached. Submitting...';
            // ---------------------

            // Ensure the script runs after the DOM is loaded
            document.addEventListener('DOMContentLoaded', () => {
                const recordButton = document.getElementById('record-button');
                const newRecordButton = document.getElementById('new-record-button');
                const uploadButton = document.getElementById('upload-button');
                const audioPlayback = document.getElementById('audio-playback');
                const playbackArea = document.getElementById('playback-area');
                const statusMessage = document.getElementById('status-message');
                const timerElement = document.getElementById('timer');
                const messageBox = document.getElementById('message-box');
                const messageText = document.getElementById('message-text');
                const micIcon = document.getElementById('mic-icon');
                const stopIcon = document.getElementById('stop-icon');
                const waveform = document.getElementById('waveform');

                // --- DYNAMIC UPLOAD ENDPOINT SET BY PHP ---
                const UPLOAD_ENDPOINT = '<?php echo $upload_handler_url; ?>'; 
                // ------------------------------------------

                let mediaRecorder;
                let audioChunks = [];
                let audioBlob;
                let timerInterval;
                let startTime;

                // Utility to display messages (errors or success)
                function displayMessage(message, isError = false) {
                    messageText.textContent = message;
                    messageBox.classList.remove('hidden', 'bg-red-100', 'border-red-400', 'text-red-700', 'bg-green-100', 'border-green-400', 'text-green-700');
                    
                    if (isError) {
                        messageBox.classList.add('bg-red-100', 'border-red-400', 'text-red-700');
                    } else {
                        messageBox.classList.add('bg-green-100', 'border-green-400', 'text-green-700');
                    }
                }

                // Timer functions
                function startTimer() {
                    startTime = Date.now();
                    timerInterval = setInterval(() => {
                        const elapsedTime = Date.now() - startTime;
                        const totalSeconds = Math.floor(elapsedTime / 1000);
                        
                        // --- MAX DURATION CHECK ---
                        if (totalSeconds >= MAX_RECORDING_SECONDS) {
                            stopRecording();
                            statusMessage.textContent = MAX_RECORDING_MESSAGE;
                            return;
                        }
                        // --------------------------

                        const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
                        const seconds = String(totalSeconds % 60).padStart(2, '0');
                        timerElement.textContent = `${minutes}:${seconds}`;
                    }, 1000);
                }

                function stopTimer() {
                    clearInterval(timerInterval);
                }

                // Initialize state for new recording
                function resetState() {
                    stopTimer();
                    timerElement.textContent = '00:00';
                    statusMessage.textContent = 'Ready to record';
                    recordButton.disabled = false;
                    uploadButton.disabled = true;
                    uploadButton.classList.add('opacity-50', 'cursor-not-allowed');

                    // Reset button colors to default (Brand Accent: Gold)
                    recordButton.style.backgroundColor = '#F7D677'; 
                    recordButton.style.color = '#082C45'; 
                    
                    micIcon.style.color = '#082C45'; 
                    stopIcon.style.color = '#082C45';

                    playbackArea.classList.add('hidden');
                    messageBox.classList.add('hidden');
                    micIcon.classList.remove('hidden');
                    stopIcon.classList.add('hidden');
                    waveform.classList.add('not-recording');
                    waveform.classList.remove('recording');
                }

                // Start Recording
                async function startRecording() {
                    resetState();
                    try {
                        // Request access to microphone
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });

                        // Initialize MediaRecorder
                        mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm' });
                        audioChunks = [];

                        mediaRecorder.ondataavailable = event => {
                            audioChunks.push(event.data);
                        };

                        mediaRecorder.onstop = () => {
                            // Stop all tracks to release the microphone
                            stream.getTracks().forEach(track => track.stop());
                            
                            audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                            
                            // Show playback and actions
                            const audioUrl = URL.createObjectURL(audioBlob);
                            audioPlayback.src = audioUrl;

                            statusMessage.textContent = 'Recording finished. Review and Submit.';
                            playbackArea.classList.remove('hidden');
                            recordButton.disabled = true; // Cannot re-record until 'new' is clicked
                            stopTimer();
                            waveform.classList.add('not-recording');
                            waveform.classList.remove('recording');
                            
                            // Enable the upload button
                            uploadButton.disabled = false;
                            uploadButton.classList.remove('opacity-50', 'cursor-not-allowed');
                        };

                        // Start recording
                        mediaRecorder.start();
                        startTimer();
                        statusMessage.textContent = 'Recording... click to stop.';
                        
                        // Set button to indicate active recording (e.g., bright red)
                        recordButton.style.backgroundColor = '#d32f2f'; // Red for recording
                        recordButton.style.color = '#F7F4EB'; // Light text on red
                        micIcon.classList.add('hidden');
                        stopIcon.classList.remove('hidden');
                        stopIcon.style.color = '#F7F4EB'; // Light text on red
                        waveform.classList.add('recording');
                        waveform.classList.remove('not-recording');
                    } catch (err) {
                        console.error('Error accessing microphone:', err);
                        displayMessage('Could not start recording. Check if microphone access is granted.', true);
                        resetState();
                    }
                }

                // Stop Recording
                function stopRecording() {
                    if (mediaRecorder && mediaRecorder.state === 'recording') {
                        mediaRecorder.stop();
                        // Change button style to indicate stop/inactive
                        recordButton.style.backgroundColor = '#a7a7a7'; 
                        recordButton.style.color = '#FFFFFF'; 
                        micIcon.classList.remove('hidden');
                        stopIcon.classList.add('hidden');
                    }
                }

                // UPLOAD LOGIC
                async function uploadAudio(blob) {
                    if (!blob) {
                        displayMessage('No audio data to upload.', true);
                        return;
                    }

                    // Set UI state to uploading
                    statusMessage.textContent = 'Uploading... Please wait.';
                    uploadButton.disabled = true;
                    uploadButton.classList.add('opacity-50');

                    const formData = new FormData();
                    const filename = `voicenote_${Date.now()}.webm`;
                    formData.append('audio_file', blob, filename);
                    
                    try {
                        const response = await fetch(UPLOAD_ENDPOINT, {
                            method: 'POST',
                            body: formData,
                        });

                        const result = await response.json(); 

                        if (response.ok && result.success) {
                            displayMessage(`Upload Successful! Your voice message has been saved.`, false);
                            statusMessage.textContent = 'Upload complete. Thank you!';
                            
                            console.log('File successfully uploaded. Check this URL for admin review:', result.url);

                        } else {
                            // Check for rate limiting error or other server error
                            const error = result.error || `Server failed with status ${response.status}`;
                            displayMessage(`Submission Failed: ${error}`, true);
                        }
                    } catch (error) {
                        console.error('Network or server error:', error);
                        displayMessage('A network error occurred during upload. Check console for details.', true);
                    } finally {
                        // Re-enable/update buttons after process completes 
                        uploadButton.disabled = false;
                        uploadButton.classList.remove('opacity-50');
                    }
                }

                // Event Listeners
                recordButton.addEventListener('click', () => {
                    if (mediaRecorder && mediaRecorder.state === 'recording') {
                        stopRecording();
                    } else {
                        startRecording();
                    }
                });

                uploadButton.addEventListener('click', () => {
                    uploadAudio(audioBlob);
                });

                newRecordButton.addEventListener('click', resetState);

                // Initialize the state when the page loads
                resetState();
            }); // End DOMContentLoaded
        </script>
    </div>
    <?php
    // Return the buffered content
    return ob_get_clean();
}
add_shortcode( 'podcast_voicenote_recorder', 'pvr_render_recorder_shortcode' );