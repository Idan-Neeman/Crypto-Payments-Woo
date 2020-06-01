<?php

// Exit if accessed directly
defined('ABSPATH') || die('Access Restricted!');

//==============================================================================
/*
   Input:
   ------
	  $order_info =
		 array (
			'order_id'        => $order_id,
			'order_total'     => $order_total_in_btc,
			'order_datetime'  => date('Y-m-d H:i:s T'),
			'requested_by_ip' => @$_SERVER['REMOTE_ADDR'],
			);
*/
// Returns:
// --------
/*
	$ret_info_array = array (
	   'result'                      => 'success', // OR 'error'
	   'message'                     => '...',
	   'host_reply_raw'              => '......',
	   'generated_bitcoin_address'   => '18vzABPyVbbia8TDCKDtXJYXcoAFAPk2cj', // or false
	   );
*/
//==============================================================================
/**
 *
 *
 * ElectrumUtil Class.
 *
 * @since 1.0.0
 */
abstract class ElectrumUtil
{
	protected $electrum_mpk;
	protected $wcp_settings;
	protected $starting_index_for_new_addresses;
	public function __construct($electrum_mpk, $starting_index_for_new_addresses)
	{
		$this->electrum_mpk                         = $electrum_mpk;
		$this->wcp_settings                         = wcp__get_settings();
		$this->starting_index_for_new_addresses 		= $starting_index_for_new_addresses;
	}
	abstract public function db_insert_new_address($addresses, $status, $funds_received, $received_funds_checked_at_time, $next_key_index);
	abstract public function fetch_addresses_from_row($address_row);
	public function fetch_address_meta($addresses)
	{
		global $wpdb;
		$crypto_addresses_table_name = $this->get_table_name();
		$address_column = $this->get_crypto_symbol() . '_address';
		$clean_address            = $addresses[$address_column];
		$address_meta             = $wpdb->get_var("SELECT `address_meta` FROM `$crypto_addresses_table_name` WHERE `$address_column`='$clean_address'");
		return $address_meta;
	}
	abstract public function get_crypto_symbol();
	abstract public function get_settings_name();

	public function get_next_key_index()
	{
		global $wpdb;
		$crypto_addresses_table_name = $this->get_table_name();
		$origin_id                = $this->electrum_mpk;
		$next_key_index = $wpdb->get_var("SELECT MAX(`index_in_wallet`) AS `max_index_in_wallet` FROM `$crypto_addresses_table_name` WHERE `origin_id`='$origin_id';");
		if ($next_key_index === null) {
			$next_key_index = $this->starting_index_for_new_addresses;
		} // Start generation of addresses from index #2 (skip two leading wallet's addresses)
		else {
			$next_key_index = $next_key_index + 1;
		}  // Continue with next index
		return $next_key_index;
	}
	abstract public function get_table_name();
	public function mark_address_as_assigned($addresses, $remote_addr, $address_meta_serialized)
	{
		global $wpdb;
		$crypto_addresses_table_name = $this->get_table_name();
		$current_time             = time();
		$address_column = $this->get_crypto_symbol() . '_address';
		$clean_address            = $addresses[$address_column];
		$query                    =
			"UPDATE `$crypto_addresses_table_name`
                 SET
                    `total_received_funds` = '0',
                    `received_funds_checked_at`='$current_time',
                    `status`='assigned',
                    `assigned_at`='$current_time',
                    `last_assigned_to_ip`='$remote_addr',
                    `address_meta`='$address_meta_serialized'
				 WHERE `$address_column`='$clean_address';";
		$ret_code                 = $wpdb->query($query);
	}
	public function make_address_request_array($addresses)
	{
		$address_column = $this->get_crypto_symbol() . '_address';
		$address_request_array                = array();
		$address_request_array[$address_column] = $addresses[$address_column]; // $addresses["fair_address"];
		return $address_request_array;
	}
	abstract public function make_return_address($result, $message, $host_reply_raw, $addresses = null);
	abstract public function run_query_quick_address_scan($current_time);
	public function run_query_unknown_address_scan($current_time)
	{
		global $wpdb;
		$assigned_address_expires_in_secs     = $this->wcp_settings['assigned_address_expires_in_mins'] * 60;
		$funds_received_value_expires_in_secs = $this->wcp_settings['funds_received_value_expires_in_mins'] * 60;
		// -------------------------------------------------------
		// Find all unused addresses belonging to this mpk with possibly (to be verified right after) zero balances
		// Array(rows) or NULL
		// Retrieve:
		// 'unused'    - with old zero balances
		// 'unknown'   - ALL
		// 'assigned'  - expired with old zero balances (if 'reuse_expired_addresses' is true)
		//
		// Hence - any returned address with freshened balance==0 will be clean to use.
		$addresses_reuse = $this->wcp_settings['reuse_expired_addresses'];

		if ($addresses_reuse) {
			$reuse_expired_addresses_oldb_query_part =
				"OR (`status`='assigned'
							 AND (('$current_time' - `assigned_at`) > '$assigned_address_expires_in_secs')
							 AND (('$current_time' - `received_funds_checked_at`) > '$funds_received_value_expires_in_secs')
							 )";
		} else {
			$reuse_expired_addresses_oldb_query_part = '';
		}

		$origin_id                = $this->electrum_mpk;
		$crypto_addresses_table_name = $this->get_table_name();
		$query                                      =
			"SELECT * FROM `$crypto_addresses_table_name`
             WHERE `origin_id`='$origin_id'
             AND `total_received_funds`='0'
             AND (
                `status`='unused'
                OR `status`='unknown'
                $reuse_expired_addresses_oldb_query_part
                )
             ORDER BY `index_in_wallet` ASC;"; // Try to use lower indexes first
		$addresses_to_verify_for_zero_balances_rows = $wpdb->get_results($query, ARRAY_A);
		if (!is_array($addresses_to_verify_for_zero_balances_rows)) {
			$addresses_to_verify_for_zero_balances_rows = array();
		}
		return $addresses_to_verify_for_zero_balances_rows;
	}
	public function set_address_status($address_row, $balance, $new_status)
	{
		global $wpdb;
		$crypto_addresses_table_name           = $this->get_table_name();
		$address_column = $this->get_crypto_symbol() . '_address';
		$current_time                       = time();
		$address_to_verify_for_zero_balance = $address_row[$address_column];
		$query                              =
			"UPDATE `$crypto_addresses_table_name`
             SET
             `status`='$new_status',
             `total_received_funds` = '$balance',
             `received_funds_checked_at`='$current_time'
             WHERE `$address_column`='$address_to_verify_for_zero_balance';";
		$ret_code                           = $wpdb->query($query);
	}
	public function get_crypto_address_for_payment__electrum($order_info)
	{
		$funds_received_value_expires_in_secs = $this->wcp_settings['funds_received_value_expires_in_mins'] * 60;
		$assigned_address_expires_in_secs     = $this->wcp_settings['assigned_address_expires_in_mins'] * 60;
		$current_time = time();
		$clean_address = $this->run_query_quick_address_scan($current_time);
		// -------------------------------------------------------
		if (!array_filter($clean_address)) {
			$addresses_to_verify_for_zero_balances_rows = $this->run_query_unknown_address_scan($current_time);
			// -------------------------------------------------------
			// Try to re-verify balances of existing addresses (with old or non-existing balances) before reverting to slow operation of generating new address.
			//
			$blockchains_api_failures = 0;
			foreach ($addresses_to_verify_for_zero_balances_rows as $address_to_verify_for_zero_balance_row) {
				$maybe_clean_address = $this->fetch_addresses_from_row($address_to_verify_for_zero_balance_row);
				$ret_info_array = $this->getreceivedbyaddress_info($maybe_clean_address);
				if ($ret_info_array['balance'] === false) {
					$blockchains_api_failures++;
					if ($blockchains_api_failures >= $this->wcp_settings['max_blockchains_api_failures']) {
						// Allow no more than 3 contigious blockchains API failures. After which return error reply.
						return $this->make_return_address('error', $ret_info_array['message'], $ret_info_array['host_reply_raw']);
					}
				} elseif ($ret_info_array['balance'] == 0) {
					// Update DB with balance and timestamp, mark address as 'assigned' and return this address as clean.
					$clean_address = $maybe_clean_address;
					break;
				} else {
					// Balance at this address suddenly became non-zero!
					// It means either order was paid after expiration or "unknown" address suddenly showed up with non-zero balance or payment was sent to this address outside of this online store business.
					// Mark it as 'revalidate' so cron job would check if that's possible delayed payment.
					//
					$address_meta = WCP_unserialize_address_meta(@$address_to_verify_for_zero_balance_row['address_meta']);
					if (isset($address_meta['orders'][0])) {
						$new_status = 'revalidate';
					} // Past orders are present. There is a chance (for cron job) to match this payment to past (albeit expired) order.
					else {
						$new_status = 'used';
					}       // No orders were ever placed to this address. Likely payment was sent to this address outside of this online store business.
					$this->set_address_status($address_to_verify_for_zero_balance_row, $ret_info_array['balance'], $new_status);
				}
			}
			// -------------------------------------------------------
		}
		// -------------------------------------------------------
		if (!array_filter($clean_address)) {
			// Still could not find unused virgin address. Time to generate it from scratch.
			/*
			Returns:
			   $ret_info_array = array (
				  'result'                      => 'success', // 'error'
				  'message'                     => '', // Failed to find/generate crypto address',
				  'host_reply_raw'              => '', // Error. No host reply availabe.',
				  'generated_bitcoin_address'   => '1FVai2j2FsFvCbgsy22ZbSMfUd3HLUHvKx', // false,
				  );
			*/
			$ret_addr_array = $this->generate_new_crypto_address_for_electrum_wallet();
			if ($ret_addr_array['result'] == 'success') {
				$clean_address = $this->convert_gen_addr_to_addr_array($ret_addr_array);
			}
		}
		// -------------------------------------------------------
		// -------------------------------------------------------
		if (array_filter($clean_address)) {
			/*
				  $order_info =
				  array (
					 'order_id'     => $order_id,
					 'order_total'  => $order_total_in_btc,
					 'order_datetime'  => date('Y-m-d H:i:s T'),
					 'requested_by_ip' => @$_SERVER['REMOTE_ADDR'],
					 );
			*/
			/*
			$address_meta =
			   array (
				  'orders' =>
					 array (
						// All orders placed on this address in reverse chronological order
						array (
						   'order_id'     => $order_id,
						   'order_total'  => $order_total_in_btc,
						   'order_datetime'  => date('Y-m-d H:i:s T'),
						   'requested_by_ip' => @$_SERVER['REMOTE_ADDR'],
						),
						array (
						   ...
						),
					 ),
				  'other_meta_info' => array (...)
			   );
			*/
			// Prepare `address_meta` field for this clean address.
			$address_meta = WCP_unserialize_address_meta($this->fetch_address_meta($clean_address));
			if (!isset($address_meta['orders']) || !is_array($address_meta['orders'])) {
				$address_meta['orders'] = array();
			}
			array_unshift($address_meta['orders'], $order_info);    // Prepend new order to array of orders
			if (count($address_meta['orders']) > 10) {
				array_pop($address_meta['orders']);
			}   // Do not keep history of more than 10 unfullfilled orders per address.
			$address_meta_serialized = WCP_serialize_address_meta($address_meta);
			// Update DB with balance and timestamp, mark address as 'assigned' and return this address as clean.
			//
			$this->mark_address_as_assigned($clean_address, $order_info['requested_by_ip'], $address_meta_serialized);
			return $this->make_return_address('success', '', '', $clean_address);
		}
		// -------------------------------------------------------
		return $this->make_return_address('error', 'Failed to find/generate crypto address. ' . $ret_addr_array['message'], $ret_addr_array['host_reply_raw']);
	}
	// ===========================================================================
	/*
	Returns:
	   $ret_info_array = array (
		  'result'                      => 'success', // 'error'
		  'message'                     => '', // Failed to find/generate crypto address',
		  'host_reply_raw'              => '', // Error. No host reply availabe.',
		  'generated_bitcoin_address'   => '18vzABPyVbbia8TDCKDtXJYXcoAFAPk2cj', // false,
		  );
	*/
	// If $this->wcp_settings or $electrum_mpk are missing - the best attempt will be made to manifest them.
	// For performance reasons it is better to pass in these vars. if available.
	//
	public function generate_new_crypto_address_for_electrum_wallet()
	{
		if (!$this->electrum_mpk) {
			// Crypto gateway settings either were not saved
			return $this->make_return_address('error', 'No MPK passed and either no MPK present in copy-settings', '');
		}
		$funds_received_value_expires_in_secs = $this->wcp_settings['funds_received_value_expires_in_mins'] * 60;
		$assigned_address_expires_in_secs     = $this->wcp_settings['assigned_address_expires_in_mins'] * 60;
		// Find next index to generate
		$next_key_index = $this->get_next_key_index();
		$addresses                = false;
		$total_new_keys_generated = 0;
		$blockchains_api_failures = 0;
		do {
			$addresses      = WCP__MATH_generate_crypto_address_from_mpk($this->electrum_mpk, $next_key_index);
			$ret_info_array = $this->getreceivedbyaddress_info($addresses);
			$total_new_keys_generated++;
			if ($ret_info_array['balance'] === false) {
				$status = 'unknown';
			} elseif ($ret_info_array['balance'] == 0) { // Newly generated address with freshly checked zero balance is unused and will be assigned.
				$status = 'unused';
			} else { // Generated address that was already used to receive money.
				$status = 'used';
			}
			$funds_received                 = ($ret_info_array['balance'] === false) ? 0 : $ret_info_array['balance'];
			$received_funds_checked_at_time = ($ret_info_array['balance'] === false) ? 0 : time();
			$ret = $this->db_insert_new_address($addresses, $status, $funds_received, $received_funds_checked_at_time, $next_key_index);
			$next_key_index++;
			if ($ret_info_array['balance'] === false) {
				$blockchains_api_failures++;
				if ($blockchains_api_failures >= $this->wcp_settings['max_blockchains_api_failures']) {
					// Allow no more than 3 contigious blockchains API failures. After which return error reply.
					return $this->make_return_address('error', $ret_info_array['message'], $ret_info_array['host_reply_raw']);
				}
			} elseif ($ret_info_array['balance'] == 0) {
				// Update DB with balance and timestamp, mark address as 'assigned' and return this address as clean.
				break;
			}
			if ($total_new_keys_generated >= $this->wcp_settings['max_unusable_generated_addresses']) {
				// Stop it after generating of 20 unproductive addresses.
				// Something is wrong. Possibly old merchant's wallet (with many used addresses) is used for new installation. - For this case 'starting_index_for_new_addresses'
				// needs to be proper set to high value.
				return $this->make_return_address('error', "Problem: Generated '$total_new_keys_generated' addresses and none were found to be unused. Possibly old merchant's wallet (with many used addresses) is used for new installation. If that is the case - 'starting_index_for_new_addresses' needs to be proper set to high value", '');
			}
		} while (true);
		// Here only in case of clean address.
		return $this->make_return_address('success', '', '', $addresses);
	}
	public function get_available_providers()
	{
		// this defines the providers priorities
		// first provider in the array is checked first
		// if it fails, we move on to the next one
		$providers_class = array('BlockchainInfoAPI', 'FairExplorer');
		$providers = array();
		foreach ($providers_class as $provider) {
			$temp_p = new $provider($this->get_crypto_symbol());
			if ($temp_p->is_active()) {
				$providers[] = $temp_p;
			}
		}
		return $providers;
	}
	// ===========================================================================
	//
	public function getreceivedbyaddress_info($address_array, $check_confirmations = false)
	{
		$providers      = $this->get_available_providers();
		$funds_received = false;
		foreach ($providers as $provider) {
			$funds_received = $provider->get_funds_received($address_array, $check_confirmations);
			if (is_numeric($funds_received)) {
				break;
			}
		}
		$funds_received_numeric = $funds_received;
		if (is_numeric($funds_received)) {
			$funds_received = sprintf('%.8f', $funds_received);
		}
		if (is_numeric($funds_received)) {
			$ret_info_array = array(
				'result'         => 'success',
				'message'        => '',
				'host_reply_raw' => '',
				'balance'        => $funds_received,
			);
		} else {
			$ret_info_array = array(
				'result'         => 'error',
				'message'        => 'API failure.',
				'host_reply_raw' => '' . $funds_received . '',
				'balance'        => false,
			);
		}
		return $ret_info_array;
	}
}
//==============================================================================
// NOTE: END ElectrumUtil
//==============================================================================
/**
 *
 *
 * ElectrumFAIRUtil Class.
 *
 * @since 1.0.0
 */
class ElectrumFAIRUtil extends ElectrumUtil
{
	public function convert_gen_addr_to_addr_array($gen_addr)
	{
		return array(
			'fair_address' => $gen_addr['generated_fair_address'],
		);
	}
	public function db_insert_new_address($addresses, $status, $funds_received, $received_funds_checked_at_time, $next_key_index)
	{
		global $wpdb;
		$new_fair_address = $addresses['fair_address'];
		$name = $this->get_settings_name();
		$variant_settings = esc_attr(get_option($name));
		$mpk = esc_attr(get_option($name)['electrum_mpk']);
		$fair_addresses_table_name = $this->get_table_name();
		$origin_id                = $mpk;
		$query =
			"INSERT INTO `$fair_addresses_table_name`
            (`fair_address`, `origin_id`, `index_in_wallet`, `total_received_funds`, `received_funds_checked_at`, `status`) VALUES
            ('$new_fair_address', '$origin_id', '$next_key_index', '$funds_received', '$received_funds_checked_at_time', '$status');";
		$wpdb->query($query);
	}
	public function fetch_addresses_from_row($address_row)
	{
		return array(
			'fair_address' => $address_row['fair_address'],
		);
	}
	public function get_crypto_symbol()
	{
		return 'fair';
	}
	public function get_settings_name()
	{
		return WCP_FAIR_SETTINGS;
	}
	public function get_table_name()
	{
		return TableFAIR::get_table_name();
	}
	public function make_return_address($result, $message, $host_reply_raw, $addresses = null)
	{
		$ret_info_array = array(
			'result'         => $result,
			'message'        => $message,
			'host_reply_raw' => $host_reply_raw,
		);
		if ($result != 'success') {
			$ret_info_array['generated_fair_address']    = false;
		} else {
			$ret_info_array['generated_fair_address']    = $addresses['fair_address'];
		}
		return $ret_info_array;
	}
	public function run_query_quick_address_scan($current_time)
	{
		global $wpdb;
		$name = $this->get_settings_name();
		$mpk = esc_attr(get_option($name)['electrum_mpk']);
		$assigned_address_expires_in_secs     = $this->wcp_settings['assigned_address_expires_in_mins'] * 60;
		$funds_received_value_expires_in_secs = $this->wcp_settings['funds_received_value_expires_in_mins'] * 60;
		if ($this->wcp_settings['reuse_expired_addresses']) {
			$reuse_expired_addresses_freshb_query_part =
				"OR (`status`='assigned'
                AND (('$current_time' - `assigned_at`) > '$assigned_address_expires_in_secs')
                AND (('$current_time' - `received_funds_checked_at`) < '$funds_received_value_expires_in_secs')
                )";
		} else {
			$reuse_expired_addresses_freshb_query_part = '';
		}
		// -------------------------------------------------------
		// Quick scan for ready-to-use address
		// NULL == not found
		// Retrieve:
		// 'unused'   - with fresh zero balances
		// 'assigned' - expired, with fresh zero balances (if 'reuse_expired_addresses' is true)
		//
		// Hence - any returned address will be clean to use.variant_settings['electrum_mpk']

		$origin_id                = $mpk;
		$fair_addresses_table_name = $this->get_table_name();
		$query                    =
			"SELECT `fair_address` FROM `$fair_addresses_table_name`
             WHERE `origin_id`='$origin_id'
             AND `total_received_funds`='0'
             AND (`status`='unused' $reuse_expired_addresses_freshb_query_part)
             ORDER BY `index_in_wallet` ASC
             LIMIT 1;"; // Try to use lower indexes first
		$clean_address            = $wpdb->get_var($query, 0, 0);
		$fair_address             = $wpdb->get_var(null, 1, 0);
		return array(
			'fair_address' => $fair_address,
		);
	}
}
//==============================================================================
// NOTE: END ElectrumFAIRUtil
//==============================================================================
/**
 *
 *
 * ElectrumBTCUtil Class.
 *
 * @since 1.0.0
 */
class ElectrumBTCUtil extends ElectrumUtil
{
	public function convert_gen_addr_to_addr_array($gen_addr)
	{
		return array('btc_address' => $gen_addr['generated_bitcoin_address']);
	}
	public function db_insert_new_address($addresses, $status, $funds_received, $received_funds_checked_at_time, $next_key_index)
	{
		global $wpdb;
		$new_btc_address = $addresses['btc_address'];
		$name = $this->get_settings_name();
		$variant_settings = esc_attr(get_option($name));
		$mpk = esc_attr(get_option($name)['electrum_mpk']);
		$crypto_addresses_table_name = $this->get_table_name();
		$origin_id                = $mpk;
		$query =
			"INSERT INTO `$crypto_addresses_table_name`
            (`btc_address`, `origin_id`, `index_in_wallet`, `total_received_funds`, `received_funds_checked_at`, `status`) VALUES
            ('$new_btc_address', '$origin_id', '$next_key_index', '$funds_received', '$received_funds_checked_at_time', '$status');";
		$wpdb->query($query);
	}
	public function fetch_addresses_from_row($address_row)
	{
		return array('btc_address' => $address_row['btc_address']);
	}
	public function get_crypto_symbol()
	{
		return 'btc';
	}

	public function get_settings_name()
	{
		return WCP_BTC_SETTINGS;
	}

	public function get_table_name()
	{
		return TableBTC::get_table_name();
	}
	public function make_return_address($result, $message, $host_reply_raw, $addresses = null)
	{
		$ret_info_array = array(
			'result'         => $result,
			'message'        => $message,
			'host_reply_raw' => $host_reply_raw,
		);
		if ($result != 'success') {
			$ret_info_array['generated_bitcoin_address'] = false;
		} else {
			$ret_info_array['generated_bitcoin_address'] = $addresses['btc_address'];
		}
		return $ret_info_array;
	}
	public function run_query_quick_address_scan($current_time)
	{
		global $wpdb;
		$assigned_address_expires_in_secs     = $this->wcp_settings['assigned_address_expires_in_mins'] * 60;
		$funds_received_value_expires_in_secs = $this->wcp_settings['funds_received_value_expires_in_mins'] * 60;
		$name = $this->get_settings_name();
		$variant_settings = esc_attr(get_option($name));
		$mpk = esc_attr(get_option($name)['electrum_mpk']);
		if ($this->wcp_settings['reuse_expired_addresses']) {
			$reuse_expired_addresses_freshb_query_part =
				"OR (`status`='assigned'
                AND (('$current_time' - `assigned_at`) > '$assigned_address_expires_in_secs')
                AND (('$current_time' - `received_funds_checked_at`) < '$funds_received_value_expires_in_secs')
                )";
		} else {
			$reuse_expired_addresses_freshb_query_part = '';
		}
		// -------------------------------------------------------
		// Quick scan for ready-to-use address
		// NULL == not found
		// Retrieve:
		// 'unused'   - with fresh zero balances
		// 'assigned' - expired, with fresh zero balances (if 'reuse_expired_addresses' is true)
		//
		// Hence - any returned address will be clean to use.

		$origin_id                = $mpk;
		$crypto_addresses_table_name = $this->get_table_name();
		$query                    =
			"SELECT `btc_address` FROM `$crypto_addresses_table_name`
             WHERE `origin_id`='$origin_id'
             AND `total_received_funds`='0'
             AND (`status`='unused' $reuse_expired_addresses_freshb_query_part)
             ORDER BY `index_in_wallet` ASC
             LIMIT 1;"; // Try to use lower indexes first
		$clean_address            = $wpdb->get_var($query, 0, 0);
		return array('btc_address' => $clean_address);
	}
}
//==============================================================================
// NOTE: END ElectrumFAIRUtil
//==============================================================================

// ===========================================================================
// To accomodate for multiple MPK's and allowed key limits per MPK
function WCP__get_next_available_mpk($wcp_settings = false)
{
	if (!$wcp_settings) {
		$wcp_settings = wcp__get_settings();
	}
	return @$this->wcp_settings['electrum_mpks'][0];
}
// ===========================================================================
// ===========================================================================
// Function makes sure that returned value is valid array
function WCP_unserialize_address_meta($flat_address_meta)
{
	$unserialized = @unserialize($flat_address_meta);
	if (is_array($unserialized)) {
		return $unserialized;
	}
	return array();
}
// ===========================================================================
// ===========================================================================
// Function makes sure that value is ready to be stored in DB
function WCP_serialize_address_meta($address_meta_arr)
{
	return WCP__safe_string_escape(serialize($address_meta_arr));
}
// ===========================================================================
// ===========================================================================
/*
$address_request_array = array (
  'btc_address'            => '1xxxxxxx',
  'required_confirmations' => '6',
  'api_timeout'						 => 10,
  );
$ret_info_array = array (
  'result'                      => 'success',
  'message'                     => "",
  'host_reply_raw'              => "",
  'balance'                     => false == error, else - balance
  );
*/
// ===========================================================================
// ===========================================================================
/*
  Get web page contents
*/
function WCP__file_get_contents($url, $timeout = 60)
{

	$response = wp_remote_get($url, $timeout);
	$resp_code = wp_remote_retrieve_response_code($response);
	$content = wp_remote_retrieve_body($response);
	if (!$err && $resp_code == 200) {
		return trim($content);
	} else {
		return false;
	}
}
// ===========================================================================
// ===========================================================================
function WCP__object_to_array($object)
{
	if (!is_object($object) && !is_array($object)) {
		return $object;
	}
	return array_map('WCP__object_to_array', (array) $object);
}
// ===========================================================================
// ===========================================================================
// Credits: http://www.php.net/manual/en/function.mysql-real-escape-string.php#100854
function WCP__safe_string_escape($str = '')
{
	$len          = strlen($str);
	$escapeCount  = 0;
	$targetString = '';
	for ($offset = 0; $offset < $len; $offset++) {
		switch ($c = $str{
		$offset}) {
			case "'":
				// Escapes this quote only if its not preceded by an unescaped backslash
				if ($escapeCount % 2 == 0) {
					$targetString .= '\\';
				}
				$escapeCount   = 0;
				$targetString .= $c;
				break;
			case '"':
				// Escapes this quote only if its not preceded by an unescaped backslash
				if ($escapeCount % 2 == 0) {
					$targetString .= '\\';
				}
				$escapeCount   = 0;
				$targetString .= $c;
				break;
			case '\\':
				$escapeCount++;
				$targetString .= $c;
				break;
			default:
				$escapeCount   = 0;
				$targetString .= $c;
		}
	}
	return $targetString;
}
// ===========================================================================
// ===========================================================================
// Syntax:
// WCP__log_event (__FILE__, __LINE__, "Hi!");
// WCP__log_event (__FILE__, __LINE__, "Hi!", "/..");
// WCP__log_event (__FILE__, __LINE__, "Hi!", "", "another_log.php");
function WCP__log_event($filename, $linenum, $message, $prepend_path = '', $log_file_name = '__log.php')
{
	$log_filename   = dirname(__FILE__) . $prepend_path . '/' . $log_file_name;
	$logfile_header = "<?php exit(':-)'); ?>\n" . '/* =============== Crypto Payments Woo LOG file =============== */' . "\r\n";
	$logfile_tail   = "\r\nEND";
	// Delete too long logfiles.
	if (@file_exists($log_filename) && filesize($log_filename) > 1000000)
		unlink($log_filename);
	$filename = basename($filename);
	if (@file_exists($log_filename)) {
		// 'r+' non destructive R/W mode.
		$fhandle = @fopen($log_filename, 'r+');
		if ($fhandle) {
			@fseek($fhandle, -strlen($logfile_tail), SEEK_END);
		}
	} else {
		$fhandle = @fopen($log_filename, 'w');
		if ($fhandle) {
			@fwrite($fhandle, $logfile_header);
		}
	}
	if ($fhandle) {
		@fwrite($fhandle, "\r\n// " . $_SERVER['REMOTE_ADDR'] . '(' . $_SERVER['REMOTE_PORT'] . ')' . ' -> ' . date('Y-m-d, G:i:s T') . '|' . WCP_VERSION . '/' . "|$filename($linenum)|: " . $message . $logfile_tail);
		@fclose($fhandle);
	}
}
// ===========================================================================
// ===========================================================================
function WCP__gateway_status($gateway_id)
{
	$payment_gateway_id = $gateway_id;
	$payment_gateways   = WC_Payment_Gateways::instance();
	$payment_gateway    = $payment_gateways->payment_gateways()[$payment_gateway_id];
	$validation_msg = '';
	$exchange_rate  = 0;
	$store_valid    = $payment_gateway->is_gateway_valid_for_use($validation_msg, $exchange_rate);
	$currency_code  = get_woocommerce_currency();

	if ($store_valid) {
		echo '<span style="display: block;" class="notice notice-success"><p>' . __($payment_gateway->get_payment_method_title() . ' payment gateway is <b>operational</b>', 'woocommerce') . '</p></span>';
		echo '<span style="display: block;" class="notice notice-success"><p>According to your settings (including multiplier), current calculated rate for 1 ' . $payment_gateway->get_payment_method_title() . ' = ' . $exchange_rate . ' (' . $currency_code . ')</p></span>';
	} else {
		echo '<span style="display: block;" class="notice notice-error"><p>' . __($payment_gateway->get_payment_method_title() . ' payment gateway is <b>not operational</b>! ', 'woocommerce') . $validation_msg . '</p></span>';
	}
}
// ===========================================================================
// ===========================================================================
function WCP__btc_gateway_status()
{
	WCP__gateway_status('bitcoin_btc');
}
// ===========================================================================
// ===========================================================================
function WCP__fair_gateway_status()
{
	WCP__gateway_status('faircoin_fair');
}
// ===========================================================================
