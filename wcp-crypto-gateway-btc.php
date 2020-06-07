<?php

// Exit if accessed directly
defined('ABSPATH') || die('Access Restricted!');

/*
 * Bitcoin BTC payment gateway
 */
class WCP_Bitcoin_BTC extends WCP_Crypto
{

	public function __construct()
	{
		parent::__construct();
	}

	public function get_payment_method_title()
	{
		return __('Bitcoin', 'WCP_I18N_DOMAIN');
	}

	public function get_gateway_id()
	{
		return 'bitcoin_' . strtolower($this->get_crypto_symbol());
	}

	public function get_settings_name()
	{
		return WCP_BTC_SETTINGS;
	}

	public function get_crypto_symbol()
	{
		return 'btc';
	}

	public function get_icon_dir()
	{
		return '/images/checkout-icons/' . strtolower($this->get_crypto_symbol()) . '/';
	}

	public function get_electrum_util()
	{
		$get_mpk = esc_attr(get_option(WCP_BTC_SETTINGS)['electrum_mpk']);
		$get_starting_index = esc_attr(get_option(WCP_BTC_SETTINGS)['starting_index_for_new_addresses']);
		return new ElectrumBTCUtil($get_mpk, $get_starting_index);
	}

	public function update_order_metadata($order_id, $ret_info_array)
	{
		$bitcoins_address = @$ret_info_array['generated_bitcoin_address'];
		WCP__log_event(__FILE__, __LINE__, '     Generated unique ' . $this->get_payment_method_title() . " address: '{$bitcoins_address}' for order_id " . $order_id);

		update_post_meta(
			$order_id,       // post id ($order_id)
			'bitcoins_address',  // meta key
			$bitcoins_address  // meta value. If array - will be auto-serialized
		);
	}

	public function get_payment_instructions_description()
	{
		$payment_instructions_description = '
          <p class="description" style="width:50%;float:left;width:45%;">
            ' . __('Specific instructions given to the customer to complete Bitcoins payment.<br />You may change it, but make sure these tags will be present: <b>{{{BITCOINS_AMOUNT}}}</b>, <b>{{{BITCOINS_ADDRESS}}}</b>, <b>{{{BITCOINS_PAY_URL}}}</b> and <b>{{{EXTRA_INSTRUCTIONS}}}</b> as these tags will be replaced with customer - specific payment details.', 'WCP_I18N_DOMAIN') . '
          </p>
          <p class="description" style="width:50%;float:right;width:50%;">
		  ' . __('Payment Instructions, original template (for reference):<br />', 'WCP_I18N_DOMAIN') . '
            <textarea rows="2" onclick="this.focus();this.select()" readonly="readonly" style="width:100%;background-color:#f1f1f1;height:4em">' . $this->default_payment_instructions() . '</textarea>
          </p>';

		return $payment_instructions_description;
	}

	public function default_payment_instructions()
	{
		$payment_instructions = '
			<table id="wcp-payment-instructions-table">
			<tr>
			<td colspan="2">' . __('Please send your Bitcoin BTC payment as follows:', 'WCP_I18N_DOMAIN') . '</td>
			</tr>
			<tr>
			<td class="td-field">
				' . __('Amount', 'WCP_I18N_DOMAIN') . ' (<strong>BTC</strong>):
			</td>
			<td>
				<div class="td-value">
				{{{BITCOINS_AMOUNT}}}
				</div>
			</td>
			</tr>
			<tr>
			<td class="td-field">
			' . __('Bitcoin Address', 'WCP_I18N_DOMAIN') . ':
			</td>
			<td>
				<div class="td-value" id="crypto-address">
				{{{BITCOINS_ADDRESS}}}
				</div>
			</td>
			</tr>
			<tr>
			<td class="td-field">
			' . __('QR code', 'WCP_I18N_DOMAIN') . ':
			</td>
			<td>
				<div class="td-value">
				<a href="{{{BITCOINS_PAY_URL}}}"><img src="https://api.qrserver.com/v1/create-qr-code/?color=000000&amp;bgcolor=FFFFFF&amp;data=bitcoin%3A{{{BITCOINS_ADDRESS}}}%3Famount%3D{{{BITCOINS_AMOUNT}}}%26message%3D{{{PAYMENT_MESSAGE_URL_SAFE}}}&amp;qzone=1&amp;margin=0&amp;size=120x120&amp;ecc=L" style="vertical-align:middle;border:1px solid #888;" /></a>
				</div>
			</td>
			</tr>
			<tr>
			<td class="td-field">
			' . __('Status', 'WCP_I18N_DOMAIN') . ':
							</td>
			<td>

			<div class="td-value">
			<span id="status-msg">' . __('Waiting for payment (include {{{BITCOINS_CONFIRMATIONS}}} confirmations)...', 'WCP_I18N_DOMAIN') . '</span>
			<div id="loader"></div>
			<small id="check-time-msg">' . __('Check balance in', 'WCP_I18N_DOMAIN') . ' <span id="check-time">60</span> ' . __('seconds', 'WCP_I18N_DOMAIN') . '</small>
			</div>

			</td>
			</tr>
		</table>

		' . __('Please note:', 'WCP_I18N_DOMAIN') . '
		<ol>
			<li>' . __('The payment method chosen ONLY accepts Bitcoin (BTC). any other electronic cash payments will not process and the money will be lost forever!', 'WCP_I18N_DOMAIN') . '</li>
			<li>' . __('We are not responsible for lost funds if you send anything other than FAIR', 'WCP_I18N_DOMAIN') . '</li>
			<li>' . __('You must initiate a payment within 1 hour, or your order may be cancelled', 'WCP_I18N_DOMAIN') . '</li>
			<li>' . __('As soon as your payment is received in full you will receive email confirmation with order delivery details.', 'WCP_I18N_DOMAIN') . '</li>
			<li>{{{EXTRA_INSTRUCTIONS}}}</li>
		</ol>';
		return $payment_instructions;
	}

	public function fill_in_instructions($order, $add_order_note = false)
	{
		// Assemble detailed instructions.
		$order_total_in_btc = get_post_meta($order->get_id(), 'order_total_in_btc', true); // set single to true to receive properly unserialized array
		$bitcoins_address   = get_post_meta($order->get_id(), 'bitcoins_address', true); // set single to true to receive properly unserialized

		$payment_message = urlencode(get_bloginfo('name') . ' Order number:' . $order->get_order_number());

		$instructions = $this->instructions;
		$instructions = str_replace('{{{BITCOINS_PAY_URL}}}', 'bitcoin:{{{BITCOINS_ADDRESS}}}?amount={{{BITCOINS_AMOUNT}}}&message={{{PAYMENT_MESSAGE}}}', $instructions);
		$instructions = str_replace('{{{BITCOINS_AMOUNT}}}', $order_total_in_btc, $instructions);
		$instructions = str_replace('{{{BITCOINS_ADDRESS}}}', $bitcoins_address, $instructions);
		$instructions = str_replace('{{{BITCOINS_CONFIRMATIONS}}}', esc_attr(get_option(WCP_BTC_SETTINGS)['confs_num']), $instructions);
		$instructions = str_replace('{{{PAYMENT_MESSAGE}}}', $payment_message, $instructions);
		// we need to double urlencode because it needs to be urlencoded for the generated qr code and the get request
		// for the qr code also needs it urlencoded
		$instructions = str_replace('{{{PAYMENT_MESSAGE_URL_SAFE}}}', urlencode($payment_message), $instructions);
		$instructions =
			str_replace(
				'{{{EXTRA_INSTRUCTIONS}}}',
				$this->instructions_multi_payment_str,
				$instructions
			);
		if ($add_order_note) {
			$order->add_order_note(__("Order instructions: price={$order_total_in_btc} BTC, incoming account:{$bitcoins_address}", 'WCP_I18N_DOMAIN'));
		}

		return $instructions;
	}
}
