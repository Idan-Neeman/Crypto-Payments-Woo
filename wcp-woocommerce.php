<?php
/*
Plugin Name: Crypto Payments Woo
Plugin URI: https://github.com/Idan-Neeman/Crypto-Payments-Woo
Description: Accept Bitcoin/FairCoin payment from WooCommerce store without help of middle man! Receive payment instantly and directly to your own coin address (generate on-the-fly by Electrum) without rotating to 3rd party wallet.
Version: 1.1
Author: Idan Neeman
Author URI: https://github.com/Idan-Neeman
Text Domain: WCP_I18N_DOMAIN
Domain Path: /lang
WC requires at least: 3.0.0
WC tested up to: 4.2.0
License: GNU General Public License 2.0 (GPL) http://www.gnu.org/licenses/gpl.html
*/

// Exit if accessed directly
defined('ABSPATH') || die('Access Restricted!');

// Include everything.
include(dirname(__FILE__) . '/wcp-include-all.php');

// ---------------------------------------------------------------------------
// Add hooks and filters
// create custom plugin settings menu

register_activation_hook(__FILE__, 'WCP_activate');
register_deactivation_hook(__FILE__, 'WCP_deactivate');
register_uninstall_hook(__FILE__, 'WCP_uninstall');

add_filter('cron_schedules', 'WCP__add_custom_scheduled_intervals');
add_action('WCP_cron_action', 'WCP_cron_job_worker');     // Multiple functions can be attached to 'WCP_cron_action' action

WCP_set_lang_file();

//Add ajax actions to allow auto check balance via ajax in payment form
add_action('wp_ajax_nopriv_balance_check_action', 'balance_check_action');
add_action('wp_ajax_balance_check_action', 'balance_check_action');

// ---------------------------------------------------------------------------
// ===========================================================================
// activating the default values
function WCP_activate()
{
	global  $g_wcp_config_defaults;
	global 	$g_wcp_btc_user_defaults;
	global 	$g_wcp_fair_user_defaults;

	$wcp_default_options = $g_wcp_config_defaults;
	$wcp_default_btc_user_options = $g_wcp_btc_user_defaults;
	$wcp_default_fair_user_options = $g_wcp_fair_user_defaults;

	// This will overwrite default options with already existing options but leave new options (in case of upgrading to new version) untouched.
	$wcp_settings = wcp__get_settings();
	foreach ($wcp_settings as $key => $value) {
		$wcp_default_options[$key] = $value;
	}

	// Re-get new settings.
	$wcp_settings = wcp__get_settings();

	// Create necessary database tables if not already exists...
	WCP__create_database_tables();

	// Re-get all the rest settings (general, btc, fair).
	wcp__update_settings();

	// ----------------------------------
	// Setup cron jobs
	if (!wp_next_scheduled('WCP_cron_action')) {
		$cron_job_schedule_name = $wcp_settings['soft_cron_job_schedule_name'];
		wp_schedule_event(time(), $cron_job_schedule_name, 'WCP_cron_action');
	}
	// ----------------------------------
}
// ---------------------------------------------------------------------------
// Cron Subfunctions
function WCP__add_custom_scheduled_intervals($schedules)
{
	$schedules['seconds_30']  = array(
		'interval' => 30,
		'display'  => __('Once every 30 seconds'),
	);     // For testing only.
	$schedules['minutes_1']   = array(
		'interval' => 1 * 60,
		'display'  => __('Once every 1 minute'),
	);
	$schedules['minutes_2.5'] = array(
		'interval' => 2.5 * 60,
		'display'  => __('Once every 2.5 minutes'),
	);
	$schedules['minutes_5']   = array(
		'interval' => 5 * 60,
		'display'  => __('Once every 5 minutes'),
	);

	return $schedules;
}
// ---------------------------------------------------------------------------
// deactivating
function WCP_deactivate()
{
	// Do deactivation cleanup. Do not delete previous settings in case user will reactivate plugin again...
	// ----------------------------------
	// Clear cron jobs
	wp_clear_scheduled_hook('WCP_cron_action');
	// ----------------------------------
}
// ---------------------------------------------------------------------------
// uninstalling
function WCP_uninstall()
{
	$delete_data = esc_attr(get_option(WCP_GENERAL_SETTINGS)['delete_db_tables_on_uninstall']);

	if ($delete_data) {
		// delete all settings.
		delete_option(WCP_SETTINGS_NAME);
		delete_option(WCP_BTC_SETTINGS);
		delete_option(WCP_FAIR_SETTINGS);
		delete_option(WCP_GENERAL_SETTINGS);

		// delete all DB tables and data.
		WCP__delete_database_tables();
	}
}
// ---------------------------------------------------------------------------
// load language files
function WCP_set_lang_file()
{
	// set the language file
	$currentLocale = get_locale();
	if (!empty($currentLocale)) {
		$moFile = dirname(__FILE__) . '/lang/' . $currentLocale . '.mo';
		if (@file_exists($moFile) && is_readable($moFile)) {
			load_textdomain('WCP_I18N_DOMAIN', $moFile);
		}
	}
}
// ===========================================================================
// Logic same as process_payment in wp-cron.php
// ===========================================================================
function balance_check_action()
{
	global $wpdb;

	$show_order = isset($_REQUEST["show_order"]) ? $_REQUEST["show_order"] : ""; //order key
	$order_id = wc_get_order_id_by_order_key($show_order);

	if (!isset($_POST['show_order']) || !isset($order_id)) {
		echo "Access Restricted!";
		die();
	}
	$order = wc_get_order($order_id);

	if ($order->get_status() != 'pending') {
		$output = array("status" => "completed");
	} else {
		$gateway = wc_get_payment_gateway_by_order($order);
		$crypto_variant = $order->get_meta('crypto_variant');
		$payment_method = $gateway->get_gateway_id();
		$wcp_settings = wcp__get_settings();
		$funds_received_value_expires_in_secs = 60; // $this->wcp_settings['funds_received_value_expires_in_mins'] * 60;
		$assigned_address_expires_in_secs     = $wcp_settings['assigned_address_expires_in_mins'] * 60;
		$current_time  = time();
		switch ($crypto_variant) {
			case 'btc':
				$crypto_addresses_table_name = TableBTC::get_table_name();
				$crypto_addresses_column_name = "btc_address";
				break;
			case 'fair':
				$crypto_addresses_table_name = TableFAIR::get_table_name();
				$crypto_addresses_column_name = "fair_address";
				break;
		}

		$crypto_address = $order->get_meta($crypto_addresses_column_name);

		$output = array("status" => "pending");

		$query                  =
			"SELECT * FROM `$crypto_addresses_table_name`
			 WHERE (
					 (`status`='assigned' AND (('$current_time' - `assigned_at`) < '$assigned_address_expires_in_secs'))
					 OR
					 (`status`='revalidate')
			 )
			 AND (('$current_time' - `received_funds_checked_at`) > '$funds_received_value_expires_in_secs') AND (`$crypto_addresses_column_name` = '$crypto_address' )
			 ORDER BY `received_funds_checked_at` ASC;"; // Check the ones that haven't been checked for longest time
		$rows_for_balance_check = $wpdb->get_results($query, ARRAY_A);

		if (is_array($rows_for_balance_check)) {
			$ran_cycles = 0;
			foreach ($rows_for_balance_check as $row_for_balance_check) {

				$ran_cycles++;  // To limit number of cycles per soft cron job.

				$address_request_array = array();
				// Retrieve current balance at address considering required confirmations number and api_timemout value.
				$address_request_array[$crypto_addresses_column_name] = $row_for_balance_check[$crypto_addresses_column_name];
				$balance_info_array                   = $gateway->get_electrum_util()->getreceivedbyaddress_info($address_request_array, true);

				// Prepare 'address_meta' for use.
				$address_meta    = WCP_unserialize_address_meta(@$row_for_balance_check['address_meta']);
				$last_order_info = @$address_meta['orders'][0];
				$row_id          = $row_for_balance_check['id'];

				if ($balance_info_array['result'] == 'success') {

					// Refresh 'received_funds_checked_at' field
					$current_time = time();
					$query        =
						"UPDATE `$crypto_addresses_table_name`
										 SET `total_received_funds` = '{$balance_info_array['balance']}',
												 `received_funds_checked_at`='$current_time'
										 WHERE `id`='$row_id';";
					$ret_code     = $wpdb->query($query);

					if ($balance_info_array['balance'] > 0) {
						if ($row_for_balance_check['status'] == 'revalidate') {
							// Address with suddenly appeared balance. Check if that is matching to previously-placed [likely expired] order
							if (!$last_order_info || !@$last_order_info['order_id'] || !@$balance_info_array['balance'] || !@$last_order_info['order_total']) {
								// No proper metadata present. Mark this address as 'xused' (used by unknown entity outside of this application) and be done with it forever.
								$query    =
									"UPDATE `$crypto_addresses_table_name`
																 SET `status` = 'xused'
																 WHERE `id`='$row_id';";
								$ret_code = $wpdb->query($query);
								continue;
							} else {
								// Metadata for this address is present. Mark this address as 'assigned' and treat it like that further down...
								$query    =
									"UPDATE `$crypto_addresses_table_name`
																 SET `status` = 'assigned'
																 WHERE `id`='$row_id';";
								$ret_code = $wpdb->query($query);
							}
						}

						WCP__log_event(__FILE__, __LINE__, "Cron job: NOTE: Detected non-zero balance at address: '{$row_for_balance_check[$crypto_addresses_column_name]}, order ID = '{$last_order_info['order_id']}'. Detected balance ='{$balance_info_array['balance']}'.");

						if ($balance_info_array['balance'] < $last_order_info['order_total']) {
							WCP__log_event(__FILE__, __LINE__, "Cron job: NOTE: balance at address: '{$row_for_balance_check[$crypto_addresses_column_name]}' ('{$crypto_variant}' '{$balance_info_array['balance']}') is not yet sufficient to complete it's order (order ID = '{$last_order_info['order_id']}'). Total required: '{$last_order_info['order_total']}'. Will wait for more funds to arrive...");
						}
					} else {
					}

					// Note: to be perfectly safe against late-paid orders, we need to:
					// Scan '$address_meta['orders']' for first UNPAID order that is exactly matching amount at address.
					if ($balance_info_array['balance'] >= $last_order_info['order_total']) {
						// Process full payment event

						// Last order was fully paid! Complete it...
						WCP__log_event(__FILE__, __LINE__, "Cron job: NOTE: Full payment for order ID '{$last_order_info['order_id']}' detected at address: '{$row_for_balance_check[$crypto_addresses_column_name]}' ('{$crypto_variant}' '{$balance_info_array['balance']}'). Total was required for this order: '{$last_order_info['order_total']}'. Processing order ...");

						// Update order' meta info
						$address_meta['orders'][0]['paid'] = true;

						// Process and complete the order within WooCommerce (send confirmation emails, etc...)
						WCP__process_payment_completed_for_order($payment_method, $last_order_info['order_id'], $balance_info_array['balance']);

						// Update address' record
						$address_meta_serialized = WCP_serialize_address_meta($address_meta);

						// Update DB - mark address as 'used'.
						//
						$current_time = time();

						// Note: `total_received_funds` and `received_funds_checked_at` are already updated above.
						//
						$query    =
							"UPDATE `$crypto_addresses_table_name`
												 SET
														`status`='used',
														`address_meta`='$address_meta_serialized'
												 WHERE `id`='$row_id';";
						$ret_code = $wpdb->query($query);
						WCP__log_event(__FILE__, __LINE__, "Cron job: SUCCESS: Order ID '{$last_order_info['order_id']}' successfully completed.");
						$output = array("status" => "completed");
					}
				} else {
					WCP__log_event(__FILE__, __LINE__, "Cron job: Warning: Cannot retrieve balance for address: '{$row_for_balance_check[$crypto_addresses_column_name]}: " . $balance_info_array['message']);
					$output = array("status" => "error");
				}
			}
		}
	}

	header('Content-type: text/javascript');
	if (isset($output))
		echo (json_encode($output));

	die();
}
// ===========================================================================
