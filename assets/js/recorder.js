/**
 * Podcast Voicenote Recorder field for Gravity Forms.
 *
 * Records WebM audio in the browser and injects the result into the field's
 * hidden file input (via the DataTransfer API) so it submits through the normal
 * Gravity Forms file pipeline.
 */
( function () {
	'use strict';

	function formatTime( totalSeconds ) {
		var minutes = String( Math.floor( totalSeconds / 60 ) ).padStart( 2, '0' );
		var seconds = String( totalSeconds % 60 ).padStart( 2, '0' );
		return minutes + ':' + seconds;
	}

	function initRecorder( wrapper ) {
		if ( wrapper.dataset.pvrInit === '1' ) {
			return;
		}
		wrapper.dataset.pvrInit = '1';

		var maxSeconds = parseInt( wrapper.dataset.maxSeconds || '300', 10 );
		var fileInput  = wrapper.querySelector( '.pvr-file-input' );
		var recordBtn  = wrapper.querySelector( '.pvr-record-button' );
		var newBtn     = wrapper.querySelector( '.pvr-new-button' );
		var audioEl    = wrapper.querySelector( '.pvr-audio' );
		var playback   = wrapper.querySelector( '.pvr-playback' );
		var statusEl   = wrapper.querySelector( '.pvr-status' );
		var timerEl    = wrapper.querySelector( '.pvr-timer' );
		var messageEl  = wrapper.querySelector( '.pvr-message' );
		var micIcon    = wrapper.querySelector( '.pvr-icon-mic' );
		var stopIcon   = wrapper.querySelector( '.pvr-icon-stop' );
		var waveform   = wrapper.querySelector( '.pvr-waveform' );

		if ( ! fileInput || ! recordBtn ) {
			return;
		}

		// Feature detection — bail gracefully on unsupported browsers.
		if ( ! navigator.mediaDevices || ! window.MediaRecorder ) {
			showMessage( 'Your browser does not support audio recording. Please try a recent version of Chrome, Firefox, Edge or Safari.', true );
			recordBtn.disabled = true;
			return;
		}

		var mediaRecorder = null;
		var audioChunks   = [];
		var audioBlob     = null;
		var timerInterval = null;
		var startTime     = 0;
		var stream        = null;

		function showMessage( text, isError ) {
			if ( ! messageEl ) {
				return;
			}
			messageEl.textContent = text;
			messageEl.classList.remove( 'pvr-hidden' );
			messageEl.classList.toggle( 'pvr-error', !! isError );
			messageEl.classList.toggle( 'pvr-success', ! isError );
		}

		function clearFile() {
			try {
				var dt = new DataTransfer();
				fileInput.files = dt.files;
			} catch ( e ) {
				fileInput.value = '';
			}
		}

		function setFile( blob ) {
			var fileName = 'voicenote-' + Date.now() + '.webm';
			try {
				var file = new File( [ blob ], fileName, { type: 'audio/webm' } );
				var dt = new DataTransfer();
				dt.items.add( file );
				fileInput.files = dt.files;
			} catch ( e ) {
				// DataTransfer not supported; surface a clear error rather than
				// silently submitting an empty file.
				showMessage( 'This browser cannot attach the recording to the form. Please try a different browser.', true );
			}
		}

		function startTimer() {
			startTime = Date.now();
			timerInterval = setInterval( function () {
				var totalSeconds = Math.floor( ( Date.now() - startTime ) / 1000 );
				if ( totalSeconds >= maxSeconds ) {
					stopRecording();
					return;
				}
				timerEl.textContent = formatTime( totalSeconds );
			}, 1000 );
		}

		function stopTimer() {
			clearInterval( timerInterval );
		}

		function resetState() {
			stopTimer();
			audioBlob = null;
			audioChunks = [];
			clearFile();
			timerEl.textContent = '00:00';
			statusEl.textContent = 'Ready to record';
			recordBtn.disabled = false;
			recordBtn.classList.remove( 'pvr-is-recording' );
			micIcon.classList.remove( 'pvr-hidden' );
			stopIcon.classList.add( 'pvr-hidden' );
			waveform.classList.add( 'pvr-not-recording' );
			waveform.classList.remove( 'pvr-recording' );
			playback.classList.add( 'pvr-hidden' );
			if ( messageEl ) {
				messageEl.classList.add( 'pvr-hidden' );
			}
		}

		function startRecording() {
			resetState();
			navigator.mediaDevices.getUserMedia( { audio: true } ).then( function ( s ) {
				stream = s;
				var options = MediaRecorder.isTypeSupported( 'audio/webm' ) ? { mimeType: 'audio/webm' } : {};
				mediaRecorder = new MediaRecorder( stream, options );
				audioChunks = [];

				mediaRecorder.ondataavailable = function ( event ) {
					if ( event.data && event.data.size > 0 ) {
						audioChunks.push( event.data );
					}
				};

				mediaRecorder.onstop = function () {
					stream.getTracks().forEach( function ( track ) {
						track.stop();
					} );

					audioBlob = new Blob( audioChunks, { type: 'audio/webm' } );
					audioEl.src = URL.createObjectURL( audioBlob );
					setFile( audioBlob );

					statusEl.textContent = 'Recording ready. Review it, then submit the form.';
					playback.classList.remove( 'pvr-hidden' );
					recordBtn.disabled = true;
					recordBtn.classList.remove( 'pvr-is-recording' );
					micIcon.classList.remove( 'pvr-hidden' );
					stopIcon.classList.add( 'pvr-hidden' );
					waveform.classList.add( 'pvr-not-recording' );
					waveform.classList.remove( 'pvr-recording' );
					stopTimer();
				};

				mediaRecorder.start();
				startTimer();
				statusEl.textContent = 'Recording… click to stop.';
				recordBtn.classList.add( 'pvr-is-recording' );
				micIcon.classList.add( 'pvr-hidden' );
				stopIcon.classList.remove( 'pvr-hidden' );
				waveform.classList.add( 'pvr-recording' );
				waveform.classList.remove( 'pvr-not-recording' );
			} ).catch( function ( err ) {
				if ( window.console ) {
					window.console.error( 'PVR: microphone access error', err );
				}
				showMessage( 'Could not start recording. Please allow microphone access and try again.', true );
				resetState();
			} );
		}

		function stopRecording() {
			if ( mediaRecorder && mediaRecorder.state === 'recording' ) {
				mediaRecorder.stop();
			}
		}

		recordBtn.addEventListener( 'click', function () {
			if ( mediaRecorder && mediaRecorder.state === 'recording' ) {
				stopRecording();
			} else {
				startRecording();
			}
		} );

		if ( newBtn ) {
			newBtn.addEventListener( 'click', resetState );
		}

		resetState();
	}

	function initAll( context ) {
		var root = context || document;
		var recorders = root.querySelectorAll ? root.querySelectorAll( '.pvr-recorder:not(.pvr-editor-preview)' ) : [];
		Array.prototype.forEach.call( recorders, initRecorder );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			initAll( document );
		} );
	} else {
		initAll( document );
	}

	// Re-init after Gravity Forms re-renders the form (AJAX / multi-page).
	if ( window.jQuery ) {
		window.jQuery( document ).on( 'gform_post_render', function () {
			initAll( document );
		} );
	}
} )();
