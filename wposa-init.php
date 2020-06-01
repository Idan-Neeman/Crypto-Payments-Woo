<?php

// Exit if accessed directly.
defined('ABSPATH') or die('Access Restricted!');

/**
 * Define global constants.
 *
 * @since 1.0.0
 */

if (!defined('WPOSA_DIR')) {
	define('WPOSA_DIR', WP_PLUGIN_DIR . '/');
}

if (!defined('WPOSA_URL')) {
	define('WPOSA_URL', WP_PLUGIN_URL . '/');
}

/**
 * WP-OOP-Settings-API Initializer
 *
 * Initializes the WP-OOP-Settings-API.
 *
 * @since   1.0.0
 */


/**
 * Class `WP_OOP_Settings_API`.
 *
 * @since 1.0.0
 */
include_once dirname(__FILE__) . '/class-wp-osa.php';


/**
 * Actions/Filters
 *
 * Related to all settings API.
 *
 * @since  1.0.0
 */
if (class_exists('WP_OSA')) {
	/**
	 * Object Instantiation.
	 *
	 * Object for the class `WP_OSA`.
	 */
	$wposa_obj = new WP_OSA();

	// Section: General Settings.
	$wposa_obj->add_section(
		array(
			'id'    => 'WCP_general',
			'title' => __('General Settings', 'WCP'),
		)
	);

	// Section: API Settings.
	$wposa_obj->add_section(
		array(
			'id'    => 'WCP_api',
			'title' => __('API Settings', 'WPOSA'),
			'desc'  => 'We are currently using free APIs, so these settings are not required. In the future, if a license-requiring API is added, we will add fields to enter the license.',
		)
	);

	// Section: BTC Settings.
	$wposa_obj->add_section(
		array(
			'id'    => 'WCP_btc',
			'title' => __('BTC Settings', 'WPOSA'),
			'callback' => 'WCP__btc_gateway_status',
		)
	);

	// Section: FAIR Settings.
	$wposa_obj->add_section(
		array(
			'id'    => 'WCP_fair',
			'title' => __('FAIR Settings', 'WPOSA'),
			'callback' => 'WCP__fair_gateway_status',
		)
	);
	//==============================================================================
	// Render BTC Fields
	//==============================================================================
	// Field: BTC MPK Textarea.
	$wposa_obj->add_field(
		'WCP_btc',
		array(
			'id'   => 'electrum_mpk',
			'type' => 'textarea',
			'name' => __('Electrum Master Public Key:', 'WPOSA'),
			'desc' => __('Changing this will switch the Master Public Key in use. Please verify you are using your Electrum (BTC) wallet as using the wrong MPK will result in loss of funds.', 'WPOSA'),
			'default' => '',
			'sanitize_callback' => 'CPWC_sanitize_mpk_input',
		)
	);

	// Field: BTC Confirmation Number.
	$wposa_obj->add_field(
		'WCP_btc',
		array(
			'id'                => 'confs_num',
			'type'              => 'number',
			'name'              => __('Required Confirmations:', 'WCP'),
			'desc'              => __('The number of confirmations required before payment is complete.`', 'WCP'),
			'default'           => '1',
			'size'							=> 4,
			'sanitize_callback' => 'intval',
		)
	);

	// Field: BTC Max Unused Addresses.
	$wposa_obj->add_field(
		'WCP_btc',
		array(
			'id'                => 'max_unused_addresses_buffer',
			'type'              => 'number',
			'name'              => __('Pre-Generate Addresses:', 'WCP'),
			'desc'              => __('Number of pre-generated addresses (This speeds up the checkout process). Only works if DISABLE_WP_CRON or Hard Cron (see general settings) are used.`', 'WCP'),
			'default'           => '5',
			'sanitize_callback' => 'intval',
		)
	);

	// Field: Select.
	$wposa_obj->add_field(
		'WCP_btc',
		array(
			'id'      => 'exchange_reference_rate',
			'type'    => 'select',
			'name'    => __('Exchange Reference Rate:', 'WPOSA'),
			'desc'    => __('Select the reference to calculate the Bitcoin price. All transactions made through this payment method will be calculated based on the Bitcoin price in the reference you have chosen.', 'WPOSA'),
			'options' => array(
				'Coingecko' => 'Coingecko',
				'Bitpay'  => 'Bitpay',
			),
		)
	);
	// // Field: BTC Exchange Rate Cache.
	$wposa_obj->add_field(
		'WCP_btc',
		array(
			'id'                => 'exchange_rate_cache_time',
			'type'              => 'number',
			'name'              => __('Exchange Rate Cache Time:', 'WCP'),
			'desc'              => __('The amount of time to cache the exchange rate for in minutes.`', 'WCP'),
			'default'           => '5',
			'size'							=> 4,
			'sanitize_callback' => 'intval',
		)
	);

	// // Field: BTC Auto-complete paid orders.
	$wposa_obj->add_field(
		'WCP_btc',
		array(
			'id'      => 'autocomplete_paid_orders',
			'type'    => 'select',
			'name'    => __('Auto-complete paid orders:', 'WPOSA'),
			'desc'    => __('	
				If checked - fully paid order will be marked as "completed" and "Your order is complete" email will be immediately delivered to customer.
				If unchecked: store admin will need to mark order as completed manually - assuming extra time needed to ship physical product after payment is received.
				Note: virtual/downloadable products will automatically complete upon receiving full payment (so this setting does not have effect in this case).', 'WCP'),
			'options' => array(
				'1' => 'Enable',
				'0'  => 'Disable',
			),
		)
	);

	// Field: BTC Exchange Multiplier.
	$wposa_obj->add_field(
		'WCP_btc',
		array(
			'id'      => 'exchange_multiplier',
			'type'    => 'text',
			'name'    => __('Exchange Rate Multiplier:', 'WCP'),
			'desc'    => __('Use this to hedge for volatility (1.05 or whatever ou feel is safe.) or to give your users a discount by paying with Bitcoin (0.95 or whatever you feel is good).', 'WCP'),
			'default' => '1.00',
			'size'							=> 4,
			'sanitize_callback' => 'CPWC_exchange_multiplier_validate'
		)
	);

	// // Field: BTC Starting Index For New Addresses.
	$wposa_obj->add_field(
		'WCP_btc',
		array(
			'id'                => 'starting_index_for_new_addresses',
			'type'              => 'number',
			'name'              => __('Starting Index for addresses (HD Generation):', 'WCP'),
			'desc'              => __('If attempting to use a previously used merchant wallet set this value higher to avoid generating previously used addresses.', 'WCP'),
			'default'           => '1',
			'size'							=> 4,
			'sanitize_callback' => 'intval',
		)
	);
	//==============================================================================
	// Render FAIR Fields
	//==============================================================================
	// Field: FAIR MPK Textarea.
	$wposa_obj->add_field(
		'WCP_fair',
		array(
			'id'   => 'electrum_mpk',
			'type' => 'textarea',
			'name' => __('ElectrumFair Master Public Key:', 'WPOSA'),
			'desc' => __('Changing this will switch the Master Public Key in use. Please verify you are using your ElectrumFair (FAIR) wallet as using the wrong MPK will result in loss of funds.', 'WPOSA'),
			'default' => '',
			'sanitize_callback' => 'CPWC_sanitize_mpk_input',
		)
	);

	// Field: FAIR Max Unused Addresses.
	$wposa_obj->add_field(
		'WCP_fair',
		array(
			'id'                => 'max_unused_addresses_buffer',
			'type'              => 'number',
			'name'              => __('Pre-Generate Addresses:', 'WCP'),
			'desc'              => __('Number of pre-generated addresses (This speeds up the checkout process). Only works if DISABLE_WP_CRON or Hard Cron (see general settings) are used.`', 'WCP'),
			'default'           => '5',
			'sanitize_callback' => 'intval',
		)
	);

	// Field: FAIR Exchange Rate Type Select
	$wposa_obj->add_field(
		'WCP_fair',
		array(
			'id'      => 'exchange_reference_rate',
			'type'    => 'select',
			'name'    => __('Exchange Reference Rate:', 'WPOSA'),
			'desc'    => __('Select the reference to calculate the Faircoin price. All transactions made through this payment method will be calculated based on the Faircoin price in the reference you have chosen.', 'WPOSA'),
			'options' => array(
				'FreeVision' => 'FreeVision',
				'FairCoop'  => 'FairCoop',
				'Fairo'  => 'Fairo',
				'Bisq'  => 'Bisq',
			),
		)
	);
	// // Field: FAIR Exchange Rate Cache.
	$wposa_obj->add_field(
		'WCP_fair',
		array(
			'id'                => 'exchange_rate_cache_time',
			'type'              => 'number',
			'name'              => __('Exchange Rate Cache Time:', 'WCP'),
			'desc'              => __('The amount of time to cache the exchange rate for in minutes.`', 'WCP'),
			'default'           => '15',
			'sanitize_callback' => 'intval',
		)
	);

		// // Field: FAIR Auto-complete paid orders.
		$wposa_obj->add_field(
			'WCP_fair',
			array(
				'id'      => 'autocomplete_paid_orders',
				'type'    => 'select',
				'name'    => __('Auto-complete paid orders:', 'WPOSA'),
				'desc'    => __('	
						If checked - fully paid order will be marked as "completed" and "Your order is complete" email will be immediately delivered to customer.
						If unchecked: store admin will need to mark order as completed manually - assuming extra time needed to ship physical product after payment is received.
						Note: virtual/downloadable products will automatically complete upon receiving full payment (so this setting does not have effect in this case).', 'WCP'),
				'options' => array(
					'1' => 'Enable',
					'0'  => 'Disable',
				),
			)
		);

	// Field: FAIR Exchange Multiplier.
	$wposa_obj->add_field(
		'WCP_fair',
		array(
			'id'      => 'exchange_multiplier',
			'type'    => 'text',
			'name'    => __('Exchange Rate Multiplier:', 'WCP'),
			'desc'    => __('Use this to hedge for volatility (1.05 or whatever ou feel is safe.) or to give your users a discount by paying with Faircoin (0.95 or whatever you feel is good).', 'WCP'),
			'default' => '1.00',
			'sanitize_callback' => 'CPWC_exchange_multiplier_validate'
		)
	);

	// Field: FAIR Starting Index For New Addresses.
	$wposa_obj->add_field(
		'WCP_fair',
		array(
			'id'                => 'starting_index_for_new_addresses',
			'type'              => 'number',
			'name'              => __('Starting Index for addresses (HD Generation):', 'WCP'),
			'desc'              => __('If attempting to use a previously used merchant wallet set this value higher to avoid generating previously used addresses.', 'WCP'),
			'default'           => '1',
			'size'							=> 4,
			'sanitize_callback' => 'intval',
		)
	);

	//==============================================================================
	// Render General Settings
	//==============================================================================
	// Field: Select. Delete All Data Dropdown
	$wposa_obj->add_field(
		'WCP_general',
		array(
			'id'      => 'delete_db_tables_on_uninstall',
			'type'    => 'select',
			'name'    => __('Delete All Data on Uninstall', 'WPOSA'),
			'desc'    => __('If enabled: All plugin-specific settings, database tables and data will be removed from WordPress database upon plugin uninstall (but not upon deactivation or upgrade).', 'WCP'),
			'options' => array(
				'1' => 'Enable',
				'0'  => 'Disable',
			),
		)
	);

	// Field: Select Delete All Data Dropdown
	$wposa_obj->add_field(
		'WCP_general',
		array(
			'id'      => 'reuse_expired_addresses',
			'type'    => 'select',
			'name'    => __('Privacy Mode:', 'WPOSA'),
			'desc'    => __('<b>Disable</b> - will allow to recycle wallet addresses that been generated for each placed order but never received any payments. The drawback of this approach is that potential snoop can generate many fake (never paid for) orders to discover sequence of wallet addresses that belongs to this store and then track down sales through blockchain analysis. The advantage of this option is that it very efficiently reuses empty (zero-balance) addresses within the wallets, allowing only 1 sale per address without growing the wallet size (Electrum "gap" value).<br/>
			<b>Enable (default, recommended)</b> - This will guarantee to generate unique wallet address for every order (real, accidental or fake). This option will provide the most anonymity and privacy to the store owners wallets. The drawback is that it will likely leave a number of addresses within the wallet never used (and hence will require setting very high "gap limit" within the Electrum wallet much sooner).<br/>
			It is recommended to regenerate new wallet after number of used wallet addresses reaches 1000. Wallets with very high gap limits (>1000) are very slow to sync with blockchain and they put an extra load on the network.<br/>
			Privacy mode offers the best anonymity and privacy to the store albeit with the drawbacks of potentially flooding your Electrum wallet with expired and zero-balance addresses.', 'WCP'),
			'options' => array(
				'1' => 'Enable',
				'0'  => 'Disable',
			),
		)
	);

	// Field: Cron Job Type
	$wp_cron_status = (defined('DISABLE_WP_CRON') && constant('DISABLE_WP_CRON') ? 'TRUE' : 'FALSE');
	$general_settings = WCP__get_general_settings();
	$WCP_plugin_directory_url = plugins_url('', __FILE__);
	$WCP_cron_script_url = get_site_url() . '/wp-cron.php';
	global $g_wcp_cron_script_url;

	$cron_setting = $general_settings['enable_soft_cron'];
	$cron_script = '<b>wget -O /dev/null ' . $g_wcp_cron_script_url . '</b>';
	//if ($cron_setting == '1') {
	$soft_cron = "<p style='background-color:#FFC;color:#2A2;''><b>NOTE</b>: Soft Cron / WP-Cron is enabled";
	$hard_cron = "<p style='background-color:#FFC;color:#2A2;'><b>NOTE</b>: Hard Cron job is enabled: make sure to follow instructions below to enable hard cron job at your hosting panel.</p>";
	global $g_wcp_cron_script_url;

	$output = <<<EOT
	Cron job will take care of all regular payments processing tasks, like checking if payments are made and automatically completing the orders.<br/>


<b>Soft Cron</b>: This option should be selected if your hosting provider does not allow Cron access. WP-Cron works fine for sites that get a decent amount of traffic, but not necessarily as great for those sites with little to no traffic.</br>
<b>Hard Cron (recommended)</b>: Cron job driven by the website hosting system/server (usually via CPanel). It pre generates addresses, so the checkout experience for your users is faster.<br/>

When enabling Hard Cron job - make sure the WP-Cron disabled by add the following line <b>define('DISABLE_WP_CRON', true);</b> to file wp-config.php<br />
and $cron_script turn on as cron job task (every 5 minutes) in your hosting.</br>
<br /><b>NOTE: </b>Cron jobs <b>might not work</b> if your site is password protected with HTTP Basic auth or other methods. This will result in WooCommerce store not seeing received payments (even though funds will arrive correctly to your btc/fair addresses).
<br />
<br>
If DISABLE_WP_CRON is TRUE then this assumes the Cron job is driven by the website hosting system/server (usually via CPanel).
<span style="color: red;"><br>DISABLE_WP_CRON is: <b>$wp_cron_status </span>
EOT;

	$wposa_obj->add_field(
		'WCP_general',
		array(
			'id'      => 'enable_soft_cron',
			'type'    => 'select',
			'name'    => __('Cron Job Type:', 'WCP'),
			'desc'    => __($output, 'WCP'),
			'options' => array(
				'1' => 'Soft Cron(WP-Cron)',
				'0'  => 'Hard Cron',
			),
		)
	);
}
//==============================================================================
// ===========================================================================
// Recursively strip slashes from all elements of multi-nested array
function WCP__stripslashes(&$val)
{
	if (is_string($val)) {
		return (stripslashes($val));
	}
	if (!is_array($val)) {
		return $val;
	}

	foreach ($val as $k => $v) {
		$val[$k] = WCP__stripslashes($v);
	}

	return $val;
}