<?php
/**
 * "Voice Recorder" Gravity Forms field.
 *
 * Renders a browser-based WebM audio recorder. The recorded blob is injected
 * into a hidden file input so it submits through the normal Gravity Forms
 * pipeline and is stored as part of the entry (just like a file-upload field).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'GF_Field' ) ) {
	return;
}

class GF_Field_Voicenote extends GF_Field {

	/**
	 * Field type identifier.
	 *
	 * @var string
	 */
	public $type = 'voicenote';

	/**
	 * Title shown in the form editor field button.
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Voice Recorder', 'podcast-voicenote-recorder' );
	}

	/**
	 * Description shown in the form editor flyout.
	 *
	 * @return string
	 */
	public function get_form_editor_field_description() {
		return esc_attr__( 'Lets visitors record an audio message in their browser and submit it as part of the form. The recording is saved with the entry.', 'podcast-voicenote-recorder' );
	}

	/**
	 * Dashicon for the editor button.
	 *
	 * @return string
	 */
	public function get_form_editor_field_icon() {
		return 'dashicons-microphone';
	}

	/**
	 * Which editor button group this field belongs to.
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
			'text'  => $this->get_form_editor_field_title(),
			'icon'  => $this->get_form_editor_field_icon(),
		);
	}

	/**
	 * Editor settings available for this field.
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings() {
		return array(
			'label_setting',
			'description_setting',
			'admin_label_setting',
			'rules_setting',
			'error_message_setting',
			'css_class_setting',
			'conditional_logic_field_setting',
		);
	}

	/**
	 * Enable conditional logic support for this field.
	 *
	 * @return bool
	 */
	public function is_conditional_logic_supported() {
		return true;
	}

	/**
	 * Treat this field like a single file upload for storage purposes.
	 *
	 * @return bool
	 */
	public function is_value_submission_array() {
		return false;
	}

	/**
	 * Render the field input markup.
	 *
	 * @param array       $form  Form object.
	 * @param string      $value Saved value (entry URL when viewing an entry).
	 * @param array|null  $entry Entry object when in entry context.
	 * @return string
	 */
	public function get_field_input( $form, $value = '', $entry = null ) {
		$form_id  = absint( $form['id'] );
		$id       = (int) $this->id;
		$field_id = "input_{$form_id}_{$id}";
		$input_name = "input_{$id}";

		// In the form editor, show a non-interactive preview.
		if ( $this->is_form_editor() ) {
			return $this->get_editor_preview();
		}

		// When viewing/editing an existing entry, show the saved recording.
		if ( $this->is_entry_detail() && ! empty( $value ) ) {
			return sprintf(
				'<div class="ginput_container ginput_container_voicenote"><audio controls preload="metadata" src="%1$s" style="width:100%%;max-width:400px;"></audio><br/><a href="%1$s" download>%2$s</a></div>',
				esc_url( $value ),
				esc_html__( 'Download recording', 'podcast-voicenote-recorder' )
			);
		}

		$addon         = function_exists( 'pvr_addon' ) ? pvr_addon() : null;
		$max_seconds   = $addon ? (int) $addon->setting( 'max_recording_seconds', 300 ) : 300;
		$max_file_size = $addon ? (int) $addon->setting( 'max_file_size', 50 ) : 50;
		$color_primary = $addon ? $addon->setting( 'color_primary', '#082C45' ) : '#082C45';
		$color_accent  = $addon ? $addon->setting( 'color_accent', '#F7D677' ) : '#F7D677';

		$disabled = $this->is_form_editor() ? 'disabled' : '';

		$max_label = sprintf( '%d:%02d', (int) floor( $max_seconds / 60 ), $max_seconds % 60 );

		ob_start();
		?>
		<div class="ginput_container ginput_container_voicenote">
			<div class="pvr-recorder"
				data-max-seconds="<?php echo esc_attr( $max_seconds ); ?>"
				data-max-size-mb="<?php echo esc_attr( $max_file_size ); ?>"
				data-input-id="<?php echo esc_attr( $field_id ); ?>"
				style="--pvr-primary: <?php echo esc_attr( $color_primary ); ?>; --pvr-accent: <?php echo esc_attr( $color_accent ); ?>;">

				<div class="pvr-waveform pvr-not-recording" aria-hidden="true">
					<span class="pvr-bar"></span><span class="pvr-bar"></span><span class="pvr-bar"></span>
					<span class="pvr-bar"></span><span class="pvr-bar"></span><span class="pvr-bar"></span>
					<span class="pvr-bar"></span><span class="pvr-bar"></span><span class="pvr-bar"></span>
					<span class="pvr-bar"></span>
				</div>

				<p class="pvr-status" role="status"><?php esc_html_e( 'Ready to record', 'podcast-voicenote-recorder' ); ?></p>
				<p class="pvr-timer">00:00</p>

				<div class="pvr-controls">
					<button type="button" class="pvr-record-button" <?php echo esc_attr( $disabled ); ?>
						aria-label="<?php esc_attr_e( 'Start or stop recording', 'podcast-voicenote-recorder' ); ?>">
						<svg class="pvr-icon-mic" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-2c0 3.03-2.43 5.5-5.3 5.5S6.7 15.03 6.7 12H5c0 3.53 2.61 6.43 6 6.9V21h2v-2.1c3.39-.47 6-3.37 6-6.9h-1.7z"/></svg>
						<svg class="pvr-icon-stop pvr-hidden" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><rect x="7" y="7" width="10" height="10" rx="2" ry="2"/></svg>
					</button>
				</div>

				<p class="pvr-hint">
					<?php
					printf(
						/* translators: %s: maximum recording length, formatted as m:ss. */
						esc_html__( 'Tap the mic to start · max length %s', 'podcast-voicenote-recorder' ),
						esc_html( $max_label )
					);
					?>
				</p>

				<div class="pvr-playback pvr-hidden">
					<audio class="pvr-audio" controls preload="metadata"></audio>
					<button type="button" class="pvr-new-button"><?php esc_html_e( 'Record a new message', 'podcast-voicenote-recorder' ); ?></button>
				</div>

				<p class="pvr-message pvr-hidden" role="alert"></p>

				<input type="file" name="<?php echo esc_attr( $input_name ); ?>" id="<?php echo esc_attr( $field_id ); ?>" class="pvr-file-input" accept="audio/webm" style="position:absolute;left:-9999px;" tabindex="-1" aria-hidden="true" />
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Static, non-interactive preview for the form editor.
	 *
	 * @return string
	 */
	private function get_editor_preview() {
		return '<div class="ginput_container ginput_container_voicenote"><div class="pvr-recorder pvr-editor-preview" style="--pvr-primary:#082C45;--pvr-accent:#F7D677;">'
			. '<div class="pvr-waveform pvr-not-recording" aria-hidden="true"><span class="pvr-bar"></span><span class="pvr-bar"></span><span class="pvr-bar"></span><span class="pvr-bar"></span><span class="pvr-bar"></span></div>'
			. '<p class="pvr-status">' . esc_html__( 'Voice recorder (shown to visitors on the live form)', 'podcast-voicenote-recorder' ) . '</p>'
			. '<div class="pvr-controls"><button type="button" class="pvr-record-button" disabled><svg class="pvr-icon-mic" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 14c1.66 0 3-1.34 3-3V5c0-1.66-1.34-3-3-3S9 3.34 9 5v6c0 1.66 1.34 3 3 3zm5.3-2c0 3.03-2.43 5.5-5.3 5.5S6.7 15.03 6.7 12H5c0 3.53 2.61 6.43 6 6.9V21h2v-2.1c3.39-.47 6-3.37 6-6.9h-1.7z"/></svg></button></div>'
			. '</div></div>';
	}

	/**
	 * Server-side validation.
	 *
	 * @param string|array $value The field value (unused for file fields).
	 * @param array        $form  The form object.
	 */
	public function validate( $value, $form ) {
		$input_name = 'input_' . $this->id;
		$has_file   = ! empty( $_FILES[ $input_name ]['name'] ) && UPLOAD_ERR_NO_FILE !== $_FILES[ $input_name ]['error'];

		if ( $this->isRequired && ! $has_file ) {
			$this->failed_validation  = true;
			$this->validation_message = empty( $this->errorMessage )
				? esc_html__( 'Please record a voice message before submitting.', 'podcast-voicenote-recorder' )
				: $this->errorMessage;

			return;
		}

		if ( ! $has_file ) {
			return;
		}

		$file = $_FILES[ $input_name ];

		// Surface PHP upload errors (e.g. exceeded server upload_max_filesize).
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			$this->failed_validation  = true;
			$this->validation_message = esc_html__( 'The recording could not be uploaded. It may be too large for this server.', 'podcast-voicenote-recorder' );

			return;
		}

		$addon       = function_exists( 'pvr_addon' ) ? pvr_addon() : null;
		$max_mb      = $addon ? (int) $addon->setting( 'max_file_size', 50 ) : 50;
		$max_bytes   = $max_mb * 1024 * 1024;

		if ( (int) $file['size'] > $max_bytes ) {
			$this->failed_validation  = true;
			$this->validation_message = sprintf(
				/* translators: %d: maximum file size in megabytes. */
				esc_html__( 'The recording is too large. The maximum allowed size is %d MB.', 'podcast-voicenote-recorder' ),
				$max_mb
			);

			return;
		}

		// Only accept WebM, matching what the recorder produces.
		$is_webm = ( false !== strpos( (string) $file['type'], 'webm' ) )
			|| ( function_exists( 'str_ends_with' ) ? str_ends_with( strtolower( $file['name'] ), '.webm' ) : preg_match( '/\.webm$/i', $file['name'] ) );

		if ( ! $is_webm ) {
			$this->failed_validation  = true;
			$this->validation_message = esc_html__( 'Only WebM audio recordings are accepted.', 'podcast-voicenote-recorder' );
		}
	}

	/**
	 * Move the uploaded recording into the form's upload directory and store
	 * its URL as the entry value.
	 *
	 * @param string $value      Incoming value (unused for files).
	 * @param array  $form       Form object.
	 * @param string $input_name Input name.
	 * @param int    $lead_id    Entry ID.
	 * @param array  $lead       Entry object.
	 * @return string The stored file URL, or empty string.
	 */
	public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
		$field_input = 'input_' . $this->id;

		if ( empty( $_FILES[ $field_input ]['name'] ) || UPLOAD_ERR_OK !== $_FILES[ $field_input ]['error'] ) {
			// Preserve any existing value (e.g. when re-saving an entry in admin).
			return is_string( $value ) ? $value : '';
		}

		$file_name = 'voicenote-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false ) . '.webm';

		$target = GFFormsModel::get_file_upload_path( $form['id'], $file_name );
		if ( empty( $target['path'] ) ) {
			return '';
		}

		if ( move_uploaded_file( $_FILES[ $field_input ]['tmp_name'], $target['path'] ) ) {
			GFCommon::log_debug( __METHOD__ . '(): Saved voicenote to ' . $target['path'] );
			return $target['url'];
		}

		GFCommon::log_error( __METHOD__ . '(): Failed to move uploaded voicenote for field ' . $this->id );

		return '';
	}

	/**
	 * Entry detail display (single entry view in the admin and notifications).
	 *
	 * @param string $value    The stored URL.
	 * @param string $currency Currency (unused).
	 * @param bool   $use_text Use text (unused).
	 * @param string $format   'html' or 'text'.
	 * @param string $media    'screen' or 'email'.
	 * @return string
	 */
	public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
		if ( empty( $value ) ) {
			return '';
		}

		if ( 'html' === $format ) {
			return sprintf(
				'<audio controls preload="metadata" src="%1$s" style="width:100%%;max-width:400px;"></audio><br/><a href="%1$s" download>%2$s</a>',
				esc_url( $value ),
				esc_html__( 'Download recording', 'podcast-voicenote-recorder' )
			);
		}

		// Plain-text contexts (e.g. text emails) get the URL.
		return $value;
	}

	/**
	 * Entry list column display (compact link).
	 *
	 * @param string $value The stored URL.
	 * @return string
	 */
	public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
		if ( empty( $value ) ) {
			return '';
		}

		return sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $value ),
			esc_html__( 'Listen', 'podcast-voicenote-recorder' )
		);
	}

	/**
	 * Merge tag value (used in notifications, confirmations).
	 *
	 * @return string
	 */
	public function get_value_merge_tag( $value, $input_id, $entry, $form, $modifier, $raw_value, $url_encode, $esc_html, $format, $nl2br ) {
		return empty( $raw_value ) ? '' : $raw_value;
	}
}
