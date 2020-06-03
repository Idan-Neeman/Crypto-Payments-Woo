<?php

// Exit if accessed directly
defined( 'ABSPATH' ) || die( 'Access Restricted!' );

/*
 * FairCoin payment gateway
 */
class WCP_FairCoin_FAIR extends WCP_Crypto {

	// -------------------------------------------------------------------
	/**
	 * Constructor for the gateway.
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() {
		parent::__construct();
	}

	public function get_payment_method_title() {
		return __( 'FairCoin', 'woocommerce' );
	}

	public function get_gateway_id() {
		return 'faircoin_' . strtolower( $this->get_crypto_symbol() );
	}

	public function get_settings_name() {
		return WCP_FAIR_SETTINGS ;
	}

	public function get_crypto_symbol() {
		return 'fair';
	}

	public function get_icon_dir() {
		return '/images/checkout-icons/' . strtolower( $this->get_crypto_symbol() ) . '/';
	}

	public function get_electrum_util() {
		$get_mpk = esc_attr( get_option(WCP_FAIR_SETTINGS)['electrum_mpk'] );
		$get_starting_index = esc_attr( get_option(WCP_FAIR_SETTINGS)['starting_index_for_new_addresses'] );
		return new ElectrumFAIRUtil( $get_mpk, $get_starting_index );
	}

	public function update_order_metadata( $order_id, $ret_info_array ) {
		$fair_address     = @$ret_info_array['generated_fair_address'];
		WCP__log_event( __FILE__, __LINE__, '     Generated unique faircoin ' . $this->get_crypto_symbol() . " address: '{$fair_address}' for order_id " . $order_id );

		update_post_meta(
			$order_id,       // post id ($order_id)
			'fair_address',  // meta key
			$fair_address  // meta value. If array - will be auto-serialized
		);
	}

	public function get_payment_instructions_description() {
		$payment_instructions_description = '
          <p class="description" style="width:50%;float:left;width:45%;">
            ' . __( 'Specific instructions given to the customer to complete Faircoins payment.<br />You may change it, but make sure these tags will be present: <b>{{{FAIRCOINS_AMOUNT}}}</b>, <b>{{{FAIRCOINS_ADDRESS}}}</b>, <b>{{{FAIRCOINS_PAY_URL}}}</b> and <b>{{{EXTRA_INSTRUCTIONS}}}</b> as these tags will be replaced with customer - specific payment details.', 'woocommerce' ) . '
          </p>
          <p class="description" style="width:50%;float:right;width:50%;">
            Payment Instructions, original template (for reference):<br />
            <textarea rows="2" onclick="this.focus();this.select()" readonly="readonly" style="width:100%;background-color:#f1f1f1;height:4em">' . $this->default_payment_instructions() . '</textarea>
          </p>';

		return $payment_instructions_description;
	}

	public function default_payment_instructions() {
		$payment_instructions = '
            <table  style="word-break: inherit;" id="wcp-payment-instructions-table">
              <tr>
                <td colspan="2">' . __( 'Please send your Faircoin FAIR payment as follows:', 'woocommerce' ) . '</td>
              </tr>
              <tr>
                <td style="vertical-align:middle;">
                  ' . __( 'Amount', 'woocommerce' ) . ' (<strong>FAIR</strong>):
                </td>
                <td>
                  <div style="padding:2px 6px;margin:2px;color:#CC0000;font-weight: bold;font-size: 120%;">
                    {{{FAIRCOINS_AMOUNT}}}
                  </div>
                </td>
              </tr>
              <tr>
                <td style="vertical-align:middle;">
                  FairCoin Address:
                </td>
                <td>
                  <div style="padding:2px 6px;font-weight: bold;font-size: 120%; word-break: break-all;">
                    {{{FAIRCOINS_ADDRESS}}}
                  </div>
                </td>
              </tr>
              <tr>
                <td style="vertical-align:middle;">
                    QR Code:
                </td>
                <td>
                  <div style="padding:2px 0px;">
                    <a href="{{{FAIRCOINS_PAY_URL}}}"><img src="https://api.qrserver.com/v1/create-qr-code/?color=000000&amp;bgcolor=FFFFFF&amp;data=faircoin%3A{{{FAIRCOINS_ADDRESS}}}%3Famount%3D{{{FAIRCOINS_AMOUNT}}}%26message%3D{{{PAYMENT_MESSAGE_URL_SAFE}}}&amp;qzone=1&amp;margin=0&amp;size=180x180&amp;ecc=L" style="vertical-align:middle;border:1px solid #888;" /></a>
                  </div>
                </td>
              </tr>
            </table>

            ' . __( 'Please note:', 'woocommerce' ) . '
            <ol>
                <li>' . __( 'The payment method chosen ONLY accepts FairCoin! Any other payments (Bitcoin, LiteCoin etc) will not process and the money will be lost forever!', 'woocommerce' ) . '</li>
                <li>' . __( 'We are not responsible for lost funds if you send anything other than FAIR', 'woocommerce' ) . '</li>
                <li>' . __( 'You must initiate a payment within 1 hour, or your order may be cancelled', 'woocommerce' ) . '</li>
                <li>' . __( 'As soon as your payment is received in full you will receive email confirmation with order delivery details.', 'woocommerce' ) . '</li>
                <li>{{{EXTRA_INSTRUCTIONS}}}</li>
            </ol>';
		return $payment_instructions;
	}


	public function fill_in_instructions( $order, $add_order_note = false ) {
		// Assemble detailed instructions.
		$order_total_in_fair = get_post_meta( $order->get_id(), 'order_total_in_fair', true ); // set single to true to receive properly unserialized array
		$faircoins_address   = get_post_meta( $order->get_id(), 'fair_address', true ); // set single to true to receive properly unserialized

		$payment_message = urlencode( get_bloginfo( 'name' ) . ' Order number:' . $order->get_order_number() );

		$instructions = $this->instructions;
		$instructions = str_replace( '{{{FAIRCOINS_PAY_URL}}}', 'faircoin:{{{FAIRCOINS_ADDRESS}}}?amount={{{FAIRCOINS_AMOUNT}}}&message={{{PAYMENT_MESSAGE}}}', $instructions );
		$instructions = str_replace( '{{{FAIRCOINS_AMOUNT}}}', $order_total_in_fair, $instructions );
		$instructions = str_replace( '{{{FAIRCOINS_ADDRESS}}}', $faircoins_address, $instructions );
		$instructions = str_replace( '{{{PAYMENT_MESSAGE}}}', $payment_message, $instructions );
		// we need to double urlencode because it needs to be urlencoded for the generated qr code and the get request
		// for the qr code also needs it urlencoded
		$instructions = str_replace( '{{{PAYMENT_MESSAGE_URL_SAFE}}}', urlencode( $payment_message ), $instructions );
		$instructions =
			str_replace(
				'{{{EXTRA_INSTRUCTIONS}}}',
				$this->instructions_multi_payment_str,
				$instructions
			);
		if ( $add_order_note ) {
			$order->add_order_note( __( "Order instructions: price={$order_total_in_fair} FAIR, incoming account:{$faircoins_address}", 'woocommerce' ) );
		}

		return $instructions;
	}
}
