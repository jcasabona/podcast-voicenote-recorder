<?php
/**
 * Gravity Forms Add-On for the Podcast Voicenote Recorder.
 *
 * Handles settings, asset enqueuing, and IP-based rate limiting. The recording
 * field itself lives in GF_Field_Voicenote.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

GFForms::include_addon_framework();

class PVR_AddOn extends GFAddOn {

	protected $_version                  = PVR_VERSION;
	protected $_min_gravityforms_version = '2.5';
	protected $_slug                     = 'podcast-voicenote-recorder';
	protected $_path                     = PVR_PLUGIN_BASENAME;
	protected $_full_path                = PVR_PLUGIN_FILE;
	protected $_title                    = 'Podcast Voicenote Recorder';
	protected $_short_title              = 'Voicenote Recorder';

	/**
	 * @var PVR_AddOn|null
	 */
	private static $_instance = null;

	/**
	 * Singleton accessor.
	 *
	 * @return PVR_AddOn
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Hooks that apply on both front-end and admin.
	 */
	public function init() {
		parent::init();

		// Enforce the per-IP daily submission limit during form validation.
		add_filter( 'gform_validation', array( $this, 'enforce_rate_limit' ) );

		// Record a successful submission against the IP after the entry is saved.
		add_action( 'gform_after_submission', array( $this, 'record_submission' ), 10, 2 );
	}

	// =====================================================================
	// == ASSETS ===========================================================
	// =====================================================================

	/**
	 * Front-end scripts. Only enqueued on forms that actually use the field.
	 *
	 * @return array
	 */
	public function scripts() {
		$scripts = array(
			array(
				'handle'    => 'pvr-recorder',
				'src'       => PVR_PLUGIN_URL . 'assets/js/recorder.js',
				'version'   => $this->_version,
				'deps'      => array(),
				'in_footer' => true,
				'enqueue'   => array(
					array( 'field_types' => array( 'voicenote' ) ),
				),
			),
		);

		return array_merge( parent::scripts(), $scripts );
	}

	/**
	 * Front-end styles. Only enqueued on forms that use the field.
	 *
	 * @return array
	 */
	public function styles() {
		$styles = array(
			array(
				'handle'  => 'pvr-recorder',
				'src'     => PVR_PLUGIN_URL . 'assets/css/recorder.css',
				'version' => $this->_version,
				'enqueue' => array(
					array( 'field_types' => array( 'voicenote' ) ),
				),
			),
		);

		return array_merge( parent::styles(), $styles );
	}

	// =====================================================================
	// == PLUGIN SETTINGS ==================================================
	// =====================================================================

	/**
	 * Settings shown under Forms > Settings > Voicenote Recorder.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'Recording Defaults', 'podcast-voicenote-recorder' ),
				'fields' => array(
					array(
						'name'              => 'max_recording_seconds',
						'label'             => esc_html__( 'Maximum recording length (seconds)', 'podcast-voicenote-recorder' ),
						'type'              => 'text',
						'class'             => 'small',
						'default_value'     => '300',
						'tooltip'           => esc_html__( 'Recording stops automatically when this length is reached.', 'podcast-voicenote-recorder' ),
						'feedback_callback' => array( $this, 'is_positive_int' ),
					),
					array(
						'name'              => 'max_file_size',
						'label'             => esc_html__( 'Maximum file size (MB)', 'podcast-voicenote-recorder' ),
						'type'              => 'text',
						'class'             => 'small',
						'default_value'     => '50',
						'tooltip'           => esc_html__( 'Submissions larger than this are rejected. Cannot exceed your server upload limit.', 'podcast-voicenote-recorder' ),
						'feedback_callback' => array( $this, 'is_positive_int' ),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Abuse Prevention', 'podcast-voicenote-recorder' ),
				'fields' => array(
					array(
						'name'    => 'rate_limit_enabled',
						'label'   => esc_html__( 'Limit submissions per visitor', 'podcast-voicenote-recorder' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label'         => esc_html__( 'Enable a daily, per-IP submission limit', 'podcast-voicenote-recorder' ),
								'name'          => 'rate_limit_enabled',
								'default_value' => 1,
							),
						),
					),
					array(
						'name'              => 'max_submissions_per_day',
						'label'             => esc_html__( 'Submissions allowed per day, per IP', 'podcast-voicenote-recorder' ),
						'type'              => 'text',
						'class'             => 'small',
						'default_value'     => '5',
						'feedback_callback' => array( $this, 'is_positive_int' ),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Appearance', 'podcast-voicenote-recorder' ),
				'fields' => array(
					array(
						'name'          => 'color_primary',
						'label'         => esc_html__( 'Background color', 'podcast-voicenote-recorder' ),
						'type'          => 'text',
						'class'         => 'small',
						'default_value' => '#082C45',
					),
					array(
						'name'          => 'color_accent',
						'label'         => esc_html__( 'Accent color', 'podcast-voicenote-recorder' ),
						'type'          => 'text',
						'class'         => 'small',
						'default_value' => '#F7D677',
					),
				),
			),
		);
	}

	/**
	 * Validation callback: value must be a positive integer.
	 *
	 * @param string $value Submitted value.
	 * @return bool
	 */
	public function is_positive_int( $value ) {
		return ctype_digit( (string) $value ) && (int) $value > 0;
	}

	/**
	 * Read a plugin setting with a sensible fallback.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function setting( $key, $default = '' ) {
		$value = $this->get_plugin_setting( $key );

		if ( null === $value || '' === $value ) {
			return $default;
		}

		return $value;
	}

	// =====================================================================
	// == RATE LIMITING ====================================================
	// =====================================================================

	/**
	 * Whether a given form contains at least one voicenote field.
	 *
	 * @param array $form Gravity Forms form object.
	 * @return bool
	 */
	private function form_has_voicenote_field( $form ) {
		if ( empty( $form['fields'] ) ) {
			return false;
		}

		foreach ( $form['fields'] as $field ) {
			if ( isset( $field->type ) && 'voicenote' === $field->type ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Best-effort client IP for rate limiting.
	 *
	 * @return string
	 */
	private function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'UNKNOWN_IP';
	}

	const RATE_LIMIT_OPTION = 'pvr_rate_limit_submissions';

	/**
	 * Block submission via gform_validation when the IP is over its daily limit.
	 *
	 * @param array $validation_result Gravity Forms validation result.
	 * @return array
	 */
	public function enforce_rate_limit( $validation_result ) {
		$form = $validation_result['form'];

		if ( ! $this->form_has_voicenote_field( $form ) ) {
			return $validation_result;
		}

		if ( ! $this->setting( 'rate_limit_enabled', 1 ) ) {
			return $validation_result;
		}

		$max   = (int) $this->setting( 'max_submissions_per_day', 5 );
		$ip    = $this->client_ip();
		$today = gmdate( 'Y-m-d' );
		$limits = get_option( self::RATE_LIMIT_OPTION, array() );

		$count = 0;
		if ( isset( $limits[ $ip ] ) && $limits[ $ip ]['date'] === $today ) {
			$count = (int) $limits[ $ip ]['count'];
		}

		if ( $count >= $max ) {
			$validation_result['is_valid'] = false;

			foreach ( $form['fields'] as &$field ) {
				if ( 'voicenote' === $field->type ) {
					$field->failed_validation  = true;
					$field->validation_message = sprintf(
						/* translators: %d: maximum submissions per day. */
						esc_html__( 'You have reached the limit of %d submissions per day. Please try again tomorrow.', 'podcast-voicenote-recorder' ),
						$max
					);
				}
			}
			unset( $field );

			$validation_result['form'] = $form;
		}

		return $validation_result;
	}

	/**
	 * Increment the IP's daily count after a successful submission.
	 *
	 * @param array $entry Entry object.
	 * @param array $form  Form object.
	 */
	public function record_submission( $entry, $form ) {
		if ( ! $this->form_has_voicenote_field( $form ) ) {
			return;
		}

		if ( ! $this->setting( 'rate_limit_enabled', 1 ) ) {
			return;
		}

		$ip     = $this->client_ip();
		$today  = gmdate( 'Y-m-d' );
		$limits = get_option( self::RATE_LIMIT_OPTION, array() );

		// Drop stale days to keep the option from growing without bound.
		foreach ( $limits as $stored_ip => $data ) {
			if ( empty( $data['date'] ) || $data['date'] !== $today ) {
				unset( $limits[ $stored_ip ] );
			}
		}

		if ( isset( $limits[ $ip ] ) ) {
			$limits[ $ip ]['count']++;
		} else {
			$limits[ $ip ] = array(
				'count' => 1,
				'date'  => $today,
			);
		}

		update_option( self::RATE_LIMIT_OPTION, $limits, false );
	}
}
