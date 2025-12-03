<?php
/**
 * Dedicated file handler for receiving audio uploads.
 * This file is called directly via POST request from the client-side JavaScript.
 */

// --- CRITICAL FIX: Robustly locate wp-load.php ---
// This uses a relative path lookup which is safer than relying on $_SERVER['DOCUMENT_ROOT']
$parse_uri = explode( 'wp-content', $_SERVER['SCRIPT_FILENAME'] );
$wp_root = $parse_uri[0];
require_once( $wp_root . 'wp-load.php' );
// ------------------------------------------------

// Define response headers
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// =========================================================
// == RATE LIMITING CONFIG & LOGIC =========================
// =========================================================

define('MAX_SUBMISSIONS_PER_DAY', 5);
define('RATE_LIMITING_OPTION_KEY', 'pvr_rate_limit_submissions');
$client_ip = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP) ? $_SERVER['REMOTE_ADDR'] : 'UNKNOWN_IP';

/**
 * Checks if the current IP has exceeded the submission limit for today.
 * If exceeded, exits with a 429 response.
 */
function pvr_check_rate_limit($ip) {
    $today = gmdate('Y-m-d');
    $limits = get_option(RATE_LIMITING_OPTION_KEY, []);
    
    // Clean up old days' data to prevent the option from growing indefinitely
    foreach ($limits as $stored_ip => $data) {
        if ($data['date'] !== $today) {
            unset($limits[$stored_ip]);
        }
    }

    if (isset($limits[$ip])) {
        $submission_count = $limits[$ip]['count'];
        if ($submission_count >= MAX_SUBMISSIONS_PER_DAY) {
            http_response_code(429); // Too Many Requests
            echo json_encode([
                'error' => 'Submission limit reached.',
                'message' => 'You have reached the limit of ' . MAX_SUBMISSIONS_PER_DAY . ' submissions allowed per day. Please try again tomorrow.'
            ]);
            exit;
        }
    }
    // Update option even if no change, to ensure cleanup of old days runs periodically
    update_option(RATE_LIMITING_OPTION_KEY, $limits, 'no'); 
}

/**
 * Increments the submission count for the current IP and saves the data.
 */
function pvr_increment_submission($ip) {
    $today = gmdate('Y-m-d');
    $limits = get_option(RATE_LIMITING_OPTION_KEY, []);
    
    if (isset($limits[$ip]) && $limits[$ip]['date'] === $today) {
        $limits[$ip]['count']++;
    } else {
        // Start new count for today
        $limits[$ip] = [
            'count' => 1,
            'date' => $today
        ];
    }
    
    // Save the updated limits back to the WordPress option
    update_option(RATE_LIMITING_OPTION_KEY, $limits, 'no');
}


// --- Execute Rate Limit Check BEFORE processing file ---
pvr_check_rate_limit($client_ip);
// =========================================================


// 1. --- Configuration & Directory Setup (Updated for WP path constants) ---
$wp_upload_dir = wp_upload_dir();
$sub_dir_name = 'podcast_voicenotes'; // Must match the directory set in the main plugin file
$upload_dir = $wp_upload_dir['basedir'] . '/' . $sub_dir_name . '/'; 
$upload_url = $wp_upload_dir['baseurl'] . '/' . $sub_dir_name . '/'; 

// Ensure the directory exists
if (!is_dir($upload_dir)) {
    // Use the robust WordPress function for creating directories
    if ( ! wp_mkdir_p( $upload_dir ) ) { 
        http_response_code(500);
        echo json_encode(['error' => 'Server failed to create the target upload directory.']);
        exit;
    }
}

// 2. --- Input Validation and Check (File Check) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Check if the file was uploaded via the 'audio_file' field
if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
    // Check for size limit errors specifically
    if (isset($_FILES['audio_file']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_INI_SIZE) {
        http_response_code(413);
        echo json_encode(['error' => 'File too large. Maximum file size exceeded (check PHP configuration).']);
        exit;
    }
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded or an upload error occurred.']);
    exit;
}

$file_info = $_FILES['audio_file'];

// Check file size (e.g., limit to 50MB)
$max_size = 50 * 1024 * 1024; 
if ($file_info['size'] > $max_size) {
    http_response_code(413);
    echo json_encode(['error' => 'File size exceeds 50MB limit.']);
    exit;
}

// Basic MIME type validation (check both client and server reported types)
$allowed_mime_types = ['audio/webm', 'video/webm']; // MediaRecorder often uses video/webm for audio only
if (!in_array($file_info['type'], $allowed_mime_types) && !str_ends_with($file_info['name'], '.webm')) {
    http_response_code(415);
    echo json_encode(['error' => 'Invalid file type. Only WebM audio is accepted.']);
    exit;
}

// 3. --- Secure Filename Generation ---
// Use a secure, unique name.
$safe_filename = 'voicenote_' . time() . '_' . substr(md5(microtime()), 0, 8) . '.webm';
$destination_path = $upload_dir . $safe_filename;
$destination_url = $upload_url . $safe_filename; // Calculate the correct public URL

// 4. --- Move and Save File ---
if (move_uploaded_file($file_info['tmp_name'], $destination_path)) {
    
    // --- Increment submission count after successful save ---
    pvr_increment_submission($client_ip);
    
    // 5. --- SEND EMAIL NOTIFICATION ---
    $admin_email = get_option('admin_email');
    $subject = '🎙️ New Podcast Voicenote Submission Received';
    
    $message = "A new voice message has been submitted by a listener.\n\n";
    $message .= "File Name: " . $safe_filename . "\n";
    $message .= "Timestamp: " . date('Y-m-d H:i:s') . " (Server Time)\n\n";
    $message .= "You can review and manage this submission directly in your WordPress dashboard:\n";
    $message .= admin_url('admin.php?page=podcast_voicenotes') . "\n\n";
    $message .= "File URL:\n";
    $message .= $destination_url . "\n\n";
    $message .= "---";

    wp_mail( $admin_email, $subject, $message );
    
    // File saved successfully
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully and email sent.',
        'filename' => $safe_filename,
        'url' => $destination_url
    ]);
} else {
    // Failed to move the file
    http_response_code(500);
    echo json_encode(['error' => 'Failed to move the uploaded file. Check directory permissions.']);
}