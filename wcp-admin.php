<?php

/**
 * Class defining admin functions
 *
 * @package    (wcp\)
 */
// Exit if accessed directly
defined('ABSPATH') || die('Access Restricted!');

// Include everything.
include_once(dirname(__FILE__) . '/wcp-include-all.php');

//==============================================================================
// Global vars.
global $g_wcp_plugin_directory_url;
$g_wcp_plugin_directory_url = plugins_url('', __FILE__);

global $g_wcp_cron_script_url;
$g_wcp_cron_script_url = get_site_url() . '/wp-cron.php';

//==============================================================================
// Global default settings
global $g_wcp_config_defaults;
$g_wcp_config_defaults = array(
	'assigned_address_expires_in_mins'     => 5 * 60,   // 5 hours to pay for order and receive necessary number of confirmations.
	'funds_received_value_expires_in_mins' => '5',      // 'received_funds_checked_at' is fresh (considered to be a valid value) if it was last checked within 'funds_received_value_expires_in_mins' minutes.
	'max_blockchains_api_failures'         => '3',    // Return error after this number of sequential failed attempts to retrieve blockchain data.
	'max_unusable_generated_addresses'     => '20',   // Return error after this number of unusable (non-empty) wallet addresses were sequentially generated.
	'blockchain_api_timeout_secs'          => '20',   // Connection and request timeouts for get operations dealing with blockchain requests.
	'exchange_rate_api_timeout_secs'       => '10',   // Connection and request timeouts for get operations dealing with exchange rate API requests.
	'soft_cron_job_schedule_name'          => 'minutes_5',   // WP cron job frequency.
	'database_schema_version' 						 => '1.4',
);

global $g_wcp_btc_user_defaults;
$g_wcp_btc_user_defaults = array(
	'confs_num'            								 		 => '6',
	'exchange_reference_rate'											 => 'Coingecko',
	'exchange_multiplier'											 => '1.00',
	'exchange_rate_cache_time'								 => '20',
	'starting_index_for_new_addresses'		     => '5',
	'autocomplete_paid_orders'                 => '1',
);

global $g_wcp_fair_user_defaults;
$g_wcp_fair_user_defaults = array(
	'exchange_reference_rate'											 => 'FreeVision',
	'exchange_multiplier'											 => '1.00',
	'exchange_rate_cache_time'								 => '20',
	'starting_index_for_new_addresses'		     => '5',
	'autocomplete_paid_orders'                 => '1',
);

global 	 $g_wcp_general_defaults;
$g_wcp_general_defaults = array(
	'enable_soft_cron'            								 		=> '1',
	'delete_db_tables_on_uninstall'                   => '1',
	'reuse_expired_addresses'                         => '1',   // True - may reduce anonymouty of store customers (someone may click/generate bunch of fake orders to list many addresses that in a future will be used by real customers).
																// False - better anonymouty but may leave many addresses in wallet unused (and hence will require very high 'gap limit') due to many unpaid order clicks.
																// In this case it is recommended to regenerate new wallet after 'gap limit' reaches 1000.


);
//==============================================================================
/**
 * Returns plugin wide settings
 *
 * @return array       array containing all the settings, some settings are arrays with more values
 */
function wcp__get_settings()
{
	global   $g_wcp_plugin_directory_url;
	global   $g_wcp_config_defaults;
	global   $g_wcp_btc_user_defaults;
	global   $g_wcp_fair_user_defaults;
	global   $g_wcp_general_defaults;


	$wcp_settings = get_option(WCP_SETTINGS_NAME);
	if (!is_array($wcp_settings)) {
		$wcp_settings = $g_wcp_config_defaults;
	}
	return $wcp_settings;

	$btc_settings = get_option(WCP_BTC_SETTINGS);
	if (!is_array($btc_settings)) {
		$btc_settings = $g_wcp_btc_user_defaults;
	}
	return $btc_settings;

	$fair_settings = get_option(WCP_FAIR_SETTINGS);
	if (!is_array($fair_settings)) {
		$fair_settings = $g_wcp_fair_user_defaults;
	}
	return $fair_settings;

	$general_settings = get_option(WCP_GENERAL_SETTINGS);
	if (!is_array($general_settings)) {
		$general_settings = $g_wcp_general_defaults;
	}
	return $general_settings;
}

//==============================================================================
/**
 * Returns Bitcoin (BTC) specific settings
 *
 * @return array       array containing all Bitcoin (BTC) settings, some settings are arrays with more values
 */
function wcp__get_btc_settings()
{
	global   $g_wcp_plugin_directory_url;
	global 	 $g_wcp_btc_user_defaults;

	$btc_settings = get_option(WCP_BTC_SETTINGS);
	if (!is_array($btc_settings)) {
		$btc_settings = $g_wcp_btc_user_defaults;
	}
	return $btc_settings;
}

//==============================================================================
/**
 * Returns FairCoin (FAIR) specific settings
 *
 * @return array       array containing all FairCoin (FAIR) settings, some settings are arrays with more values
 */
function wcp__get_fair_settings()
{
	global   $g_wcp_plugin_directory_url;
	global 	 $g_wcp_fair_user_defaults;

	$fair_settings = get_option(WCP_FAIR_SETTINGS);
	if (!is_array($fair_settings)) {
		$fair_settings = $g_wcp_fair_user_defaults;
	}
	return $fair_settings;
}

//==============================================================================
/**
 * Returns Settings saved on general tab of admin page
 *
 * @return array       array containing all general settings, some settings are arrays with more values
 */
function wcp__get_general_settings()
{
	global   $g_wcp_plugin_directory_url;
	global 	 $g_wcp_general_defaults;

	$general_settings = get_option(WCP_GENERAL_SETTINGS);
	if (!is_array($general_settings)) {
		$general_settings = $g_wcp_general_defaults;
	}
	return $general_settings;
}
//==============================================================================
/**
 * Updates the plugin wide settings
 * This can be
 *
 * @return [type] [description]
 */
function wcp__update_settings()
{
	global   $g_wcp_config_defaults;
	global 	 $g_wcp_btc_user_defaults;
	global 	 $g_wcp_fair_user_defaults;
	global 	 $g_wcp_general_defaults;

	// Load current settings and overwrite them with whatever values are present on submitted form
	$wcp_settings = wcp__get_settings();
	foreach ($g_wcp_general_defaults as $k => $v) {
		if (!isset($wcp_settings[$k])) {
			$wcp_settings[$k] = '';
		} // Force set to something.
		// if no old value is present and no new value is given
		// we want to set the settings to the default
		$value = $v;
		if (isset($_POST[$k])) {
			// we have a new value that we want to set
			$value = $_POST[$k];
		} elseif ($wcp_settings[$k] != '') {
			// there is a value and we do not want to change it.
			continue;
		}
		WCP__update_individual_wcp_setting($wcp_settings[$k], $value);
	}

	$btc_settings = wcp__get_btc_settings();
	foreach ($g_wcp_btc_user_defaults as $k => $v) {
		if (!isset($btc_settings[$k])) {
			$btc_settings[$k] = '';
		} // Force set to something.
		// if no old value is present and no new value is given
		// we want to set the settings to the default
		$value = $v;
		if (isset($_POST[$k])) {
			// we have a new value that we want to set
			$value = $_POST[$k];
		} elseif ($btc_settings[$k] != '') {
			// there is a value and we do not want to change it.
			continue;
		}
		WCP__update_individual_wcp_setting($btc_settings[$k], $value);
	}

	$fair_settings = wcp__get_fair_settings();
	foreach ($g_wcp_fair_user_defaults as $k => $v) {
		if (!isset($fair_settings[$k])) {
			$fair_settings[$k] = '';
		} // Force set to something.
		// if no old value is present and no new value is given
		// we want to set the settings to the default
		$value = $v;
		if (isset($_POST[$k])) {
			// we have a new value that we want to set
			$value = $_POST[$k];
		} elseif ($fair_settings[$k] != '') {
			// there is a value and we do not want to change it.
			continue;
		}
		WCP__update_individual_wcp_setting($fair_settings[$k], $value);
	}

	$general_settings = wcp__get_general_settings();
	foreach ($g_wcp_general_defaults as $k => $v) {
		if (!isset($general_settings[$k])) {
			$general_settings[$k] = '';
		} // Force set to something.
		// if no old value is present and no new value is given
		// we want to set the settings to the default
		$value = $v;
		if (isset($_POST[$k])) {
			// we have a new value that we want to set
			$value = $_POST[$k];
		} elseif ($general_settings[$k] != '') {
			// there is a value and we do not want to change it.
			continue;
		}
		WCP__update_individual_wcp_setting($general_settings[$k], $value);
	}
	update_option(WCP_SETTINGS_NAME, $wcp_settings);
	update_option(WCP_BTC_SETTINGS, $btc_settings);
	update_option(WCP_FAIR_SETTINGS, $fair_settings);
	update_option(WCP_GENERAL_SETTINGS, $general_settings);
}

//==============================================================================
// Takes care of recursive updating
function WCP__update_individual_wcp_setting(&$wcp_current_setting, $wcp_new_setting)
{
	if (is_string($wcp_new_setting)) {
		$wcp_current_setting = WCP__stripslashes($wcp_new_setting);
	} elseif (is_array($wcp_new_setting)) {  // Note: new setting may not exist yet in current setting: curr[t5] - not set yet, while new[t5] set.
		// Need to do recursive
		foreach ($wcp_new_setting as $k => $v) {
			if (!isset($wcp_current_setting[$k])) {
				$wcp_current_setting[$k] = '';
			}   // If not set yet - force set it to something.
			WCP__update_individual_wcp_setting($wcp_current_setting[$k], $v);
		}
	} else {
		$wcp_current_setting = $wcp_new_setting;
	}
}

//==============================================================================
// Reset settings only for one screen
function WCP__reset_partial_settings()
{
	global   $g_wcp_config_defaults;

	// Load current settings and overwrite ones that are present on submitted form with defaults
	$wcp_settings = wcp__get_settings();

	foreach ($_POST as $k => $v) {
		if (isset($g_wcp_config_defaults[$k])) {
			if (!isset($wcp_settings[$k])) {
				$wcp_settings[$k] = '';
			} // Force set to something.
			WCP__update_individual_wcp_setting($wcp_settings[$k], $g_wcp_config_defaults[$k]);
		}
	}

	update_option(WCP_SETTINGS_NAME, $wcp_settings);
}

//==============================================================================
// Resets all settings to default
function WCP__reset_all_settings()
{
	global   $g_wcp_config_defaults;

	update_option(WCP_SETTINGS_NAME, $g_wcp_config_defaults);
}


//==============================================================================
// Updates the exchange rate cache
function WCP__update_cache($exchange_rate, $exchange_reference_rate)
{
	// Save new currency exchange rate info in cache
	$wcp_settings  = wcp__get_settings();
	$currency_code = get_woocommerce_currency();

	if (!isset($wcp_settings['exchange_rates'])) {
		$wcp_settings['exchange_rates'] = array();
	}

	if (!isset($wcp_settings['exchange_rates'][$currency_code])) {
		$wcp_settings['exchange_rates'][$currency_code] = array();
	}

	if (!isset($wcp_settings['exchange_rates'][$currency_code][$exchange_reference_rate])) {
		$wcp_settings['exchange_rates'][$currency_code][$exchange_reference_rate] = array();
	}

	$wcp_settings['exchange_rates'][$currency_code][$exchange_reference_rate]['time-last-checked'] = time();
	$wcp_settings['exchange_rates'][$currency_code][$exchange_reference_rate]['exchange_rate']     = $exchange_rate;
	update_option(WCP_SETTINGS_NAME, $wcp_settings);
}

//==============================================================================
// Creates required tables in database
/*
	----------------------------------
	: Table 'btc_addresses' :
	----------------------------------
	  status                "unused"      - never been used address with last known zero balance
							"assigned"    - order was placed and this address was assigned for payment
							"revalidate"  - assigned/expired, unused or unknown address suddenly got non-zero balance in it. Revalidate it for possible late order payment against meta_data.
							"used"        - order was placed and this address and payment in full was received. Address will not be used again.
							"xused"       - address was used (touched with funds) by unknown entity outside of this application. No metadata is present for this address, will not be able to correlated it with any order.
							"unknown"     - new address was generated but cannot retrieve balance due to blockchain API failure.
*/
function WCP__create_database_tables()
{
	$create_tables = array('TableFAIR', 'TableBTC');

	foreach ($create_tables as $table) {
		$table::create_database_tables();
	}
}

//==============================================================================
// NOTE: Irreversibly deletes all plugin tables and data
function WCP__delete_database_tables()
{
	$create_tables = array('TableFAIR', 'TableBTC');

	foreach ($create_tables as $table) {
		$table::delete_database_tables();
	}
}
