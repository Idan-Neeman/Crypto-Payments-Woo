<?php
// Exit if accessed directly
defined('ABSPATH') || die('Access Restricted!');

// Include everything.
//include( dirname( __FILE__ ) . '/wcp-include-all.php' );

// APIs to retrieve data from the blockchain
// such as balance
// All APIs should extend from BlockchainAPI
// Should implement the following:
// * get_supported_cryptos
// * get_funds_received
// If the API needs an API key, then the
// following should also be overriden:
// * __construct
// * is_active
abstract class BlockchainAPI
{

	protected $crypto_in_use;

	public function __construct($crypto_in_use)
	{
		$this->crypto_in_use = strtolower($crypto_in_use);
		$this->api_timeout    = wcp__get_settings()['blockchain_api_timeout_secs'];
	}

	abstract protected function get_supported_cryptos();

	protected function is_crypto_supported()
	{
		return in_array($this->crypto_in_use, $this->get_supported_cryptos());
	}

	public function is_active()
	{
		if ($this->is_crypto_supported()) {
			return true;
		}
		return false;
	}

	abstract public function get_funds_received($address, $check_confirmation);
}

class BlockchainInfoAPI extends BlockchainAPI
{

	protected function get_supported_cryptos()
	{
		return array('btc');
	}

	public function get_funds_received($address, $check_confirmation)
	{
		if ($check_confirmation)
			$funds_received = WCP__file_get_contents('https://blockchain.info/q/addressbalance/' . $address['btc_address'] . '?confirmations=' . esc_attr(get_option(WCP_BTC_SETTINGS)['confs_num']), $this->api_timeout);
		else
			$funds_received = WCP__file_get_contents('https://blockchain.info/q/addressbalance/' . $address['btc_address'], $this->api_timeout);

		if (!is_numeric($funds_received)) {
			return false;
		}
		return $funds_received;
	}
}

class FairExplorer extends BlockchainAPI
{

	protected function get_supported_cryptos()
	{
		return array('fair');
	}

	private function extract_funds_received($json_val, $check_confirmation)
	{
		if (!$json_val) {
			return false;
		}
		if ($check_confirmation)
			return $json_val['confirmed'];
		else
			return $json_val['confirmed'] + $json_val['unconfirmed'];
	}

	public function get_funds_received($address, $check_confirmation)
	{
		$funds_received = $this->extract_funds_received(@json_decode(trim(
			@WCP__file_get_contents(
				'http://server.faircoin.co/api/balance.php?address=' . $address['fair_address'],
				$this->api_timeout
			)
		), true),$check_confirmation);

		if (!is_numeric($funds_received)) {
			return false;
		}
		return $funds_received;
	}
}
