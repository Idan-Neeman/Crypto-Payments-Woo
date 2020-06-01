<?php
/*
Plugin Name: Crypto Payments Woo
Plugin URI: https://github.com/Idan-Neeman/Crypto-Payments-Woo
Description: Accept Bitcoin/FairCoin payment from WooCommerce store without help of middle man! Receive payment instantly and directly to your own coin address (generate on-the-fly by Electrum) without rotating to 3rd party wallet.
Version: 1.00
Author: Idan Neeman
Author URI: https://github.com/Idan-Neeman
License: GNU General Public License 2.0 (GPL) http://www.gnu.org/licenses/gpl.html
*/

// Exit if accessed directly
defined( 'ABSPATH' ) || die( 'Access Restricted!' );

// Include everything.
include ( dirname( __FILE__ ) . '/wcp-include-all.php' );

// ---------------------------------------------------------------------------
// Add hooks and filters
// create custom plugin settings menu

register_activation_hook( __FILE__, 'WCP_activate' );
register_deactivation_hook( __FILE__, 'WCP_deactivate' );
register_uninstall_hook( __FILE__, 'WCP_uninstall' );

add_filter( 'cron_schedules', 'WCP__add_custom_scheduled_intervals' );
add_action( 'WCP_cron_action', 'WCP_cron_job_worker' );     // Multiple functions can be attached to 'WCP_cron_action' action

WCP_set_lang_file();
// ---------------------------------------------------------------------------
// ===========================================================================
// activating the default values
function WCP_activate() {
	global  $g_wcp_config_defaults;
	global 	$g_wcp_btc_user_defaults;
	global 	$g_wcp_fair_user_defaults;

	$wcp_default_options = $g_wcp_config_defaults;
	$wcp_default_btc_user_options = $g_wcp_btc_user_defaults;
	$wcp_default_fair_user_options = $g_wcp_fair_user_defaults;

	// This will overwrite default options with already existing options but leave new options (in case of upgrading to new version) untouched.
	$wcp_settings = wcp__get_settings();
	foreach ( $wcp_settings as $key => $value ) {
		$wcp_default_options[ $key ] = $value;
	}

	// Re-get new settings.
	$wcp_settings = wcp__get_settings();

	// Create necessary database tables if not already exists...
	WCP__create_database_tables( $wcp_settings );

	// ----------------------------------
	// Setup cron jobs
	if ( ! wp_next_scheduled( 'WCP_cron_action' ) ) {
		$cron_job_schedule_name = $wcp_settings['soft_cron_job_schedule_name'];
		wp_schedule_event( time(), $cron_job_schedule_name, 'WCP_cron_action' );
	}
	// ----------------------------------
}
// ---------------------------------------------------------------------------
// Cron Subfunctions
function WCP__add_custom_scheduled_intervals( $schedules ) {
	$schedules['seconds_30']  = array(
		'interval' => 30,
		'display'  => __( 'Once every 30 seconds' ),
	);     // For testing only.
	$schedules['minutes_1']   = array(
		'interval' => 1 * 60,
		'display'  => __( 'Once every 1 minute' ),
	);
	$schedules['minutes_2.5'] = array(
		'interval' => 2.5 * 60,
		'display'  => __( 'Once every 2.5 minutes' ),
	);
	$schedules['minutes_5']   = array(
		'interval' => 5 * 60,
		'display'  => __( 'Once every 5 minutes' ),
	);

	return $schedules;
}
// ---------------------------------------------------------------------------
// deactivating
function WCP_deactivate() {
	 // Do deactivation cleanup. Do not delete previous settings in case user will reactivate plugin again...
	// ----------------------------------
	// Clear cron jobs
	wp_clear_scheduled_hook( 'WCP_cron_action' );
	// ----------------------------------
}
// ---------------------------------------------------------------------------
// uninstalling
function WCP_uninstall() {
	$delete_data = esc_attr( get_option(WCP_GENERAL_SETTINGS)['delete_db_tables_on_uninstall'] );

	if ( $delete_data ) {
		// delete all settings.
		delete_option( WCP_SETTINGS_NAME );
		delete_option( WCP_BTC_SETTINGS );
		delete_option( WCP_FAIR_SETTINGS );
		delete_option( WCP_GENERAL_SETTINGS );

		// delete all DB tables and data.
		WCP__delete_database_tables();
	}
}
// ---------------------------------------------------------------------------
// load language files
function WCP_set_lang_file() {
	// set the language file
	$currentLocale = get_locale();
	if ( ! empty( $currentLocale ) ) {
		$moFile = dirname( __FILE__ ) . '/lang/' . $currentLocale . '.mo';
		if ( @file_exists( $moFile ) && is_readable( $moFile ) ) {
			load_textdomain( WCP_I18N_DOMAIN, $moFile );
		}
	}
}
// ===========================================================================
