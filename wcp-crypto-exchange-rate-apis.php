<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || die( 'Access Restricted!' );

// Include everything.
//include( dirname( __FILE__ ) . '/wcp-include-all.php' );

// APIs to retrieve data for exchange rates
// All APIs should extend from ExchangeRateAPI
// Should implement the following:
// * get_supported_cryptos
// * get_supported_exchange_reference_rates
// According to the get_supported_exchange_reference_rates
// the following should be overriden as needed
// * get_exchange_rate
// If the API needs an API key, then the
// following should also be overriden:
// * __construct
// * is_active
// See Class Coinmarketcap for an example
abstract class ExchangeRateAPI {

	protected $crypto_in_use;
	protected $exchange_rate_api_timeout_secs;

	public function __construct( $crypto_in_use ) {
		$this->crypto_in_use                 = strtolower( $crypto_in_use );
		$this->exchange_rate_api_timeout_secs = wcp__get_settings()['exchange_rate_api_timeout_secs'];
	}

	abstract protected function get_supported_cryptos();

	protected function is_crypto_supported() {
		 return in_array( $this->crypto_in_use, $this->get_supported_cryptos() );
	}

	public function is_active() {
		if ( $this->is_crypto_supported() ) {
			return true;
		}
		return false;
	}

}

class Bitpay extends ExchangeRateAPI {

	private static $supported_cryptos = array( 'btc' );

	protected function get_supported_cryptos() {
		return self::$supported_cryptos;
	}

	public function get_exchange_rate() {
		$source_url = 'https://bitpay.com/api/rates/BTC/' . get_woocommerce_currency();
		$result     = @WCP__file_get_contents( $source_url, $this->exchange_rate_api_timeout_secs );

		$rate_obj = @json_decode( trim( $result ), true );

		if ( @$rate_obj['code'] == get_woocommerce_currency() && $rate_obj['rate'] ) {
			return $rate_obj['rate'];
		}

		return false;
	}
}

class Coingecko extends ExchangeRateAPI {

	private static $supported_cryptos = array('btc');

	protected function get_supported_cryptos() {
		return self::$supported_cryptos;
	}

	private function get_variant_url_part() {
		switch ( $this->crypto_in_use ) {
			case 'btc':
				return 'bitcoin';
				break;
			case 'fair':
				return 'faircoin';
				break;
			default:
				break;
		}
	}

	public function get_exchange_rate() {
		$source_url = 'https://api.coingecko.com/api/v3/simple/price?ids=' . $this->get_variant_url_part() . '&vs_currencies=' . get_woocommerce_currency();
		$result     = @WCP__file_get_contents( $source_url, $this->exchange_rate_api_timeout_secs );

		$rate_obj = @json_decode( trim( $result ), true );

		$currency_code_tolower = strtolower( get_woocommerce_currency() );

		if ( $rate_obj[ $this->get_variant_url_part() ][ $currency_code_tolower ] ) {
			return $rate_obj[ $this->get_variant_url_part() ][ $currency_code_tolower ];
		}

		return false;
	}
}

class FreeVision extends ExchangeRateAPI {

	private static $supported_cryptos = array( 'fair' );

	protected function get_supported_cryptos() {
		return self::$supported_cryptos;
	}

	public function get_exchange_rate() {
		$source_url = 'https://faircoin.co/api/freevision.php';
		$result     = @WCP__file_get_contents( $source_url, $this->exchange_rate_api_timeout_secs );

		$rate_obj = @json_decode( trim( $result ), true );

		if ( @$rate_obj[get_woocommerce_currency()] ) {
			return $rate_obj[get_woocommerce_currency()];
		}

		return false;
	}
}

class FairCoop extends ExchangeRateAPI {

	private static $supported_cryptos = array( 'fair' );

	protected function get_supported_cryptos() {
		return self::$supported_cryptos;
	}

	public function get_exchange_rate() {
		$source_url = 'https://faircoin.co/api/faircoop.php';
		$result     = @WCP__file_get_contents( $source_url, $this->exchange_rate_api_timeout_secs );

		$rate_obj = @json_decode( trim( $result ), true );

		if ( @$rate_obj[get_woocommerce_currency()] ) {
			return $rate_obj[get_woocommerce_currency()];
		}

		return false;
	}
}

class Fairo extends ExchangeRateAPI {

	private static $supported_cryptos = array( 'fair' );

	protected function get_supported_cryptos() {
		return self::$supported_cryptos;
	}

	public function get_exchange_rate() {
		$source_url = 'https://faircoin.co/api/fairo.php';
		$result     = @WCP__file_get_contents( $source_url, $this->exchange_rate_api_timeout_secs );

		$rate_obj = @json_decode( trim( $result ), true );

		if ( @$rate_obj[get_woocommerce_currency()] ) {
			return $rate_obj[get_woocommerce_currency()];
		}

		return false;
	}
}

class Bisq extends ExchangeRateAPI {

	private static $supported_cryptos = array( 'fair' );

	protected function get_supported_cryptos() {
		return self::$supported_cryptos;
	}

	public function get_exchange_rate() {
		$source_url = 'https://faircoin.co/api/bisq.php';
		$result     = @WCP__file_get_contents( $source_url, $this->exchange_rate_api_timeout_secs );

		$rate_obj = @json_decode( trim( $result ), true );

		if ( @$rate_obj[get_woocommerce_currency()] ) {
			return $rate_obj[get_woocommerce_currency()];
		}

		return false;
	}
}
