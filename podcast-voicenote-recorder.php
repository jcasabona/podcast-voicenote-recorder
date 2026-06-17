<?php
/**
 * Plugin Name: Podcast Voicenote Recorder for Gravity Forms
 * Description: Adds a browser-based audio recording field to Gravity Forms, letting listeners record and submit voice messages that are stored as form entries.
 * Version: 3.0.1
 * Author: Joe Casabona
 * License: GPL2
 * Text Domain: podcast-voicenote-recorder
 * Requires at least: 5.8
 * Requires PHP: 7.2
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PVR_VERSION', '3.0.1' );
define( 'PVR_PLUGIN_FILE', __FILE__ );
define( 'PVR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PVR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PVR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Bootstrap the Gravity Forms Add-On once Gravity Forms has loaded.
 *
 * Registering on the `gform_loaded` action guarantees that the Add-On
 * Framework and the core GF_Field class are available before we extend them.
 */
add_action( 'gform_loaded', 'pvr_bootstrap_addon', 5 );
function pvr_bootstrap_addon() {
	if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
		return;
	}

	GFForms::include_addon_framework();

	require_once PVR_PLUGIN_DIR . 'includes/class-pvr-addon.php';
	GFAddOn::register( 'PVR_AddOn' );

	// Register the custom field type with Gravity Forms.
	require_once PVR_PLUGIN_DIR . 'includes/class-gf-field-voicenote.php';
	GF_Fields::register( new GF_Field_Voicenote() );
}

/**
 * Convenience accessor for the Add-On instance.
 *
 * @return PVR_AddOn|null
 */
function pvr_addon() {
	if ( class_exists( 'PVR_AddOn' ) ) {
		return PVR_AddOn::get_instance();
	}

	return null;
}

/**
 * Show an admin notice when Gravity Forms is not active.
 *
 * The add-on is useless without Gravity Forms, so make the dependency obvious
 * rather than silently doing nothing.
 */
add_action( 'admin_init', 'pvr_check_gravityforms_dependency' );
function pvr_check_gravityforms_dependency() {
	if ( class_exists( 'GFForms' ) ) {
		return;
	}

	add_action( 'admin_notices', 'pvr_gravityforms_missing_notice' );
}

function pvr_gravityforms_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	echo '<div class="notice notice-error"><p>';
	echo esc_html__( 'Podcast Voicenote Recorder for Gravity Forms requires Gravity Forms to be installed and active.', 'podcast-voicenote-recorder' );
	echo '</p></div>';
}
