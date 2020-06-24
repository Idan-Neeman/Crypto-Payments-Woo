<?php

// Exit if accessed directly
defined('ABSPATH') || die('Access Restricted!');

//==============================================================================
add_action('plugins_loaded', 'WCP__plugins_loaded__load_crypto_gateway', 0);
//==============================================================================
// Hook payment gateway into WooCommerce
function WCP__plugins_loaded__load_crypto_gateway()
{
	if (!class_exists('WC_Payment_Gateway')) {
		// Nothing happens here if WooCommerce is not loaded
		return;
	}

	//==============================================================================
	/**
	 * Crypto Based Blockchain Base Payment Gateway
	 *
	 * Provides a base Payment Gateway for Crypto currencies blockchains
	 *
	 * @class       WCP_Crypto
	 * @extends     WC_Payment_Gateway
	 */
	abstract class WCP_Crypto extends WC_Payment_Gateway
	{
		// -------------------------------------------------------------------
		/**
		 * Constructor for the gateway.
		 *
		 * @access public
		 * @return void
		 */
		public function __construct()
		{
			$this->has_fields    = false;
			$this->id            = $this->get_gateway_id();
			$this->settings_name = $this->get_settings_name();

			// Load the form fields.
			$this->init_form_fields();
			$this->init_settings();

			$this->method_title = $this->get_payment_method_title();
			$this->icon         = $this->get_gateway_icon();

			// Define user set variables
			$this->title = $this->settings['title']; // The title which the user is shown on the checkout – retrieved from the settings which init_settings loads.

			$this->description                    = $this->settings['description'];   // Short description about the gateway which is shown on checkout.
			$this->instructions                   = $this->settings['instructions'];  // Detailed payment instructions for the buyer.
			$this->instructions_multi_payment_str = __('You may send payments from multiple accounts to reach the total required.', 'woocommerce');
			// $this->instructions_single_payment_str = __('You must pay in a single payment in full.', 'woocommerce');
			// Actions
			if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
				add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
			} else {
				add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
			} // hook into this action to save options in the backend

			add_action('woocommerce_receipt_' . $this->id, array(&$this, 'receipt_page'));

			// Customer Emails
			add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 2); // hooks into the email template to show additional details

			// Validate currently set currency for the store. Must be among supported ones.
			if (!$this->is_gateway_valid_for_use()) {
				$this->enabled = false;
			}

			// Payment template loading as API
			add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'checkout_template_loading'));
		}
		// -------------------------------------------------------------------
		//
		public function get_gateway_icon()
		{
			$symbol = $this->get_crypto_symbol();
			return plugins_url($this->settings['checkout_icon'] ? $this->settings['checkout_icon'] : '/images/checkout-icons/' . $symbol . '/default.png', __FILE__);
		}

		abstract public function get_payment_method_title();

		abstract public function get_gateway_id();

		abstract public function get_settings_name();

		abstract public function get_crypto_symbol();

		public function get_settings($key = false)
		{
		}

		public function update_settings($wcpcash_use_these_settings = false)
		{
		}

		public function update_individual_wcp_setting(&$wcp_current_setting, $wcp_new_setting)
		{
		}

		public function get_next_available_mpk()
		{
			$name = $this->get_settings_name();
			$btc_settings = @esc_attr(get_option(WCP_BTC_SETTINGS)['electrum_mpk']);
			$btc_mpk = $btc_settings;
			$fair_settings = @esc_attr(get_option(WCP_FAIR_SETTINGS)['electrum_mpk']);
			$fair_mpk = $fair_settings;
			$symbol = $this->get_crypto_symbol();

			if ($symbol == 'btc') {
				return @$btc_mpk;
			} elseif ($symbol == 'fair') {
				return @$fair_mpk;
			}
		}

		public function get_max_unused_addresses_buffer()
		{
			$symbol = $this->get_crypto_symbol();
			$btc_setting = @esc_attr(get_option(WCP_BTC_SETTINGS)['max_unused_addresses_buffer']);
			$fair_setting = @esc_attr(get_option(WCP_FAIR_SETTINGS)['max_unused_addresses_buffer']);
			if ($symbol == 'btc') {
				return $btc_setting;
			} elseif ($symbol == 'fair') {
				return $fair_setting;
			}
		}

		public static function is_valid_mpk($mpk, &$reason_message)
		{
			if (!$mpk) {
				$reason_message = __('Please specify Electrum Master Public Key (MPK). <br/><b>How to get my MPK</b>? launch your electrum wallet (Electrum/ElectrumFair - depending on the currency you currently set), select: Wallet->Information', 'woocommerce');
			} elseif (!preg_match('/^[a-f0-9]{128}$/', $mpk) && !preg_match('/^xpub[a-zA-Z0-9]{107}$/', $mpk)) {
				$reason_message = __('Electrum Master Public Key is invalid. Must be 128 or 111 characters long, consisting of digits and letters.', 'woocommerce');
			} elseif (!extension_loaded('gmp') && !extension_loaded('bcmath')) {
				$reason_message = __(
					"ERROR: neither 'bcmath' nor 'gmp' math extensions are loaded For Electrum wallet options to function. Contact your hosting company and ask them to enable either 'bcmath' or 'gmp' extensions. 'gmp' is preferred (much faster)!
            <br />We recommend <a href='http://livenet.co.il/' target='_blank'><b>LiveNet</b></a> as the best hosting services provider.",
					'woocommerce'
				);
			} else {
				return true;
			}

			return false;
		}

		// -------------------------------------------------------------------
		/**
		 * Check if this gateway is enabled and available for the store's default currency
		 *
		 * @access public
		 * @return bool
		 */
		public function is_gateway_valid_for_use(&$ret_reason_message = null, &$exchange_rate = 0)
		{
			// ----------------------------------
			// Validate settings
			$mpk = $this->get_next_available_mpk();
			if (!$this->is_valid_mpk($mpk, $ret_reason_message)) {
				return false;
			}

			// ----------------------------------
			// ----------------------------------
			// Validate connection to exchange rate services
			$store_currency_code = get_woocommerce_currency();
			if ($store_currency_code != 'BTC' && $store_currency_code != 'FAIR') {
				$exchange_rate = $this->get_exchange_rate_per_crypto();
				if (!$exchange_rate) {

					// Assemble error message.
					$error_msg           = "ERROR: Cannot determine exchange rates (for '$store_currency_code')! Make sure your PHP settings are configured properly and your server can (is allowed to) connect to external WEB services via PHP.";

					if ($ret_reason_message !== null) {
						$ret_reason_message = $error_msg;
					}
					return false;
				}
			}
			return true;
		}

		abstract public function get_payment_instructions_description();

		abstract public function default_payment_instructions();

		abstract public function get_icon_dir();

		public function get_checkout_icon_options()
		{

			$icon_options = array();

			$plugin_root = dirname(__FILE__);
			$icon_dir    = $this->get_icon_dir();
			$icons       = scandir($plugin_root . $icon_dir);
			foreach ($icons as $icon) {
				if (!is_file($plugin_root . $icon_dir . $icon)) {
					continue;
				}
				$icon_rel_path = $icon_dir . $icon;
				$icon_url      = plugins_url($icon_rel_path, __FILE__);

				$icon_options[$icon] = array(
					'url'      => $icon_url,
					'rel_path' => $icon_rel_path,
				);
			}

			return $icon_options;
		}

		public function generate_iconradio_html($key, $data)
		{
			$field_key = $this->get_field_key($key);
			$defaults  = array(
				'title'             => '',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'type'              => 'text',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
				'options'           => array(),
			);

			$data = wp_parse_args($data, $defaults);

			ob_start();
?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<?php echo $this->get_tooltip_html($data); ?>
					<label for="<?php echo esc_attr($field_key); ?>"><?php echo wp_kses_post($data['title']); ?></label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span><?php echo wp_kses_post($data['title']); ?></span></legend>

						<?php foreach ((array) $data['options'] as $icon_key => $icon_data) : ?>
							<input type="radio" class="<?php echo esc_attr($data['class']); ?>" name="<?php echo esc_attr($field_key); ?>" id="<?php echo esc_attr($field_key); ?>" style="<?php echo esc_attr($data['css']); ?>" <?php disabled($data['disabled'], true); ?> <?php echo $this->get_custom_attribute_html($data); ?> id="<?php echo esc_attr($icon_key); ?>" value="<?php echo esc_attr($icon_data['rel_path']); ?>" <?php checked($icon_data['rel_path'], esc_attr($this->get_option($key))); ?> />

							<label for="<?php echo esc_attr($icon_key); ?>"><img src="<?php echo esc_attr($icon_data['url']); ?>" height="32"></img></label><br />
						<?php endforeach; ?>

						<?php echo $this->get_description_html($data); ?>
						<?php
						echo "<p class='description'>You can upload new icons for this gateway to: " . str_replace(ABSPATH, '', dirname(__FILE__) . $this->get_icon_dir()) . '<br/>
                                        Make sure to scale the image to a height of 32px.</p>';
						?>
					</fieldset>
				</td>
			</tr>
		<?php

			return ob_get_clean();
		}

		// -------------------------------------------------------------------
		/**
		 * Initialise Gateway Settings Form Fields
		 *
		 * @access public
		 * @return void
		 */
		public function init_form_fields()
		{
			// This defines the settings we want to show in the admin area.
			// This allows user to customize payment gateway.
			// Add as many as you see fit.
			// See this for more form elements: http://wcdocs.woothemes.com/codex/extending/settings-api/
			// -----------------------------------
			// Assemble currency ticker.
			$store_currency_code = get_woocommerce_currency();
			if ($store_currency_code == 'BTC' || $store_currency_code == 'FAIR') {
				$currency_code = 'USD';
			} else {
				$currency_code = $store_currency_code;
			}

			// -----------------------------------
			// Payment instructions
			$payment_instructions = $this->default_payment_instructions();

			$payment_instructions = trim($payment_instructions);

			$payment_instructions_description = $this->get_payment_instructions_description($payment_instructions);

			$payment_instructions_description = trim($payment_instructions_description);
			// -----------------------------------
			$this->form_fields = array(
				'enabled'                              => array(
					'title'   => __('Enable/Disable', 'woocommerce'),
					'type'    => 'checkbox',
					'label'   => __('Enable ' . $this->get_payment_method_title() . ' Payments', 'woocommerce'),
					'default' => 'yes',
				),
				'title'                                => array(
					'title'       => __('Title', 'woocommerce'),
					'type'        => 'text',
					'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
					'default'     => __($this->get_payment_method_title() . ' Payment', 'woocommerce'),
				),

				'description'                          => array(
					'title'       => __('Customer Message', 'woocommerce'),
					'type'        => 'text',
					'description' => __('Initial instructions for the customer at checkout screen', 'woocommerce'),
					'default'     => __('Please proceed to the next screen to see necessary payment details.', 'woocommerce'),
				),
				'instructions'                         => array(
					'title'       => __('Payment Instructions (HTML)', 'woocommerce'),
					'type'        => 'textarea',
					'description' => $payment_instructions_description,
					'default'     => $payment_instructions,
				),
				'electrum_mpk_saved'                   => array(
					'title'       => __('All Electrum Master Public Keys you\'ve used previously.', 'woocommerce'),
					'description' => __('Changing this field will have no effect.', 'woocommerce'),
					'type'        => 'textarea',
					'default'     => '',
				),
				'checkout_icon'                        => array(
					'title'       => __('The icon your users see when choosing the checkout options.', 'woocommerce'),
					'description' => __('The user will see multiple checkout options, each one identified by an icon. This defines the icon for this checkout option.', 'woocommerce'),
					'type'        => 'iconradio',
					'options'     => $this->get_checkout_icon_options(),
				),
			);
		}

		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 * @access public
		 * @return void
		 */
		public function admin_options()
		{
			$validation_msg = '';
			$exchange_rate  = 0;
			$store_valid    = $this->is_gateway_valid_for_use($validation_msg, $exchange_rate);
			$currency_code  = get_woocommerce_currency();

			// After defining the options, we need to display them too; thats where this next function comes into play:
		?>
			<h3><?php _e($this->get_payment_method_title() . ' Payment', 'woocommerce'); ?></h3>
			<p>
				<?php _e('Allows to accept payments in ' . $this->get_payment_method_title() . '. ' . $this->get_payment_method_title() . ' is peer-to-peer, decentralized digital currency that enables instant payments from anyone to anyone, anywhere in the world.', 'woocommerce'); ?>
			</p>
			<?php
			if ($store_valid) {
				echo '<span style="display: block;" class="notice notice-success"><p>' . __($this->get_payment_method_title() . ' payment gateway is <b>operational</b>', 'woocommerce') . '</p></span>';
				echo '<span style="display: block;" class="notice notice-success"><p>According to your settings (including multiplier), current calculated rate for 1 ' . $this->get_payment_method_title() . ' = ' . $exchange_rate . ' (' . $currency_code . ')</p></span>';
			} else {
				echo '<span style="display: block;" class="notice notice-error"><p>' . __($this->get_payment_method_title() . ' payment gateway is <b>not operational</b>! ', 'woocommerce') . $validation_msg . '</p></span>';
			}
			?>
			<table class="form-table">
				<?php
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
				?>
			</table>
			<!--/.form-table-->
<?php
		}
		// -------------------------------------------------------------------
		// -------------------------------------------------------------------
		// Hook into admin options saving.
		public function process_admin_options()
		{
			// Call parent
			parent::process_admin_options();

			return;
		}
		// -------------------------------------------------------------------
		function get_provider($exchange_reference_rate)
		{
			$provider = new $exchange_reference_rate($this->get_crypto_symbol());
			if ($provider->is_active()) {
				return $provider;
			}
			return false;
		}

		function get_rate($exchange_reference_rate)
		{
			$provider = $this->get_provider($exchange_reference_rate);
			if ($provider) {
				$rate = call_user_func(array($provider, 'get_exchange_rate'));
				if ($rate) {
					return $rate;
				}
			}
			return false;
		}

		// this exists to allow overriding at the gateway implementation level
		function get_exchange_rate($reference_rate)
		{
			return $this->get_rate($reference_rate);
		}

		function get_cache()
		{
			$wcp_settings = wcp__get_settings();
			$symbol = $this->get_crypto_symbol();
			switch ($symbol) {
				case 'btc':
					return @$wcp_settings['exchange_rates'][get_woocommerce_currency()][esc_attr(get_option(WCP_BTC_SETTINGS)['exchange_reference_rate'])];
					break;
				case 'fair':
					return @$wcp_settings['exchange_rates'][get_woocommerce_currency()][esc_attr(get_option(WCP_FAIR_SETTINGS)['exchange_reference_rate'])];
					break;
				default:
					break;
			}
		}

		function set_cache($exchange_rate)
		{
			$symbol = $this->get_crypto_symbol();
			// Save new currency exchange rate info in cache
			switch ($symbol) {
				case 'btc':
					WCP__update_cache($exchange_rate, esc_attr(get_option(WCP_BTC_SETTINGS)['exchange_reference_rate']));
					break;
				case 'fair':
					WCP__update_cache($exchange_rate, esc_attr(get_option(WCP_FAIR_SETTINGS)['exchange_reference_rate']));
					break;
				default:
					break;
			}
		}

		function get_exchange_rate_per_crypto()
		{
			if (get_woocommerce_currency() == 'BTC' || get_woocommerce_currency() == 'FAIR') {
				return '1.00';
			}
			$name = $this->get_settings_name();
			$symbol = $this->get_crypto_symbol();
			$btc_reference_rate = esc_attr(get_option(WCP_BTC_SETTINGS)['exchange_reference_rate']);
			$fair_reference_rate = esc_attr(get_option(WCP_FAIR_SETTINGS)['exchange_reference_rate']);

			// added so each coin can have seperate settings
			switch ($symbol) {
				case 'btc':
					$exchange_multiplier = esc_attr(get_option(WCP_BTC_SETTINGS)['exchange_multiplier']);
					break;
				case 'fair':
					$exchange_multiplier = esc_attr(get_option(WCP_FAIR_SETTINGS)['exchange_multiplier']);
					break;
				default:
					break;
			}
			if (!$exchange_multiplier) {
				$exchange_multiplier = 1;
			}

			$current_time       = time();
			$this_currency_info = $this->get_cache();

			if ($this_currency_info && isset($this_currency_info['time-last-checked'])) {
				$delta = $current_time - $this_currency_info['time-last-checked'];
				switch ($symbol) {
					case 'btc':
						$cache_time = esc_attr(get_option(WCP_BTC_SETTINGS)['exchange_rate_cache_time']);
						break;
					case 'fair':
						$cache_time = esc_attr(get_option(WCP_FAIR_SETTINGS)['exchange_rate_cache_time']);
						break;
					default:
						break;
				}
				if ($delta < ($cache_time * 60)) {

					// Exchange rates cache hit
					// Use cached value as it is still fresh.
					$final_rate = $this_currency_info['exchange_rate'] / $exchange_multiplier;
					return $final_rate;
				}
			}
			// we can straight up call the function since we've
			// previously validated the exchange rate type
			switch ($symbol) {
				case 'btc':
					$exchange_rate = $this->get_exchange_rate($btc_reference_rate);
					break;
				case 'fair':
					$exchange_rate = $this->get_exchange_rate($fair_reference_rate);
					break;
				default:
					break;
			}
			$this->set_cache($exchange_rate);

			if ($exchange_rate) {
				return $exchange_rate / $exchange_multiplier;
			}

			return false;
		}

		abstract public function get_electrum_util();

		abstract public function update_order_metadata($order_id, $ret_info_array);

		// -------------------------------------------------------------------
		/**
		 * Process the payment and return the result
		 *
		 * @access public
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment($order_id)
		{
			$wcp_settings = wcp__get_settings();
			$order        = wc_get_order($order_id);

			// TODO: Implement CRM features within store admin dashboard
			$order_meta                = array();
			$order_meta['bw_order']    = $order->get_id();
			$order_meta['bw_items']    = $order->get_items();
			$order_meta['bw_b_addr']   = $order->get_formatted_billing_address();
			$order_meta['bw_s_addr']   = $order->get_formatted_shipping_address();
			$order_meta['bw_b_email']  = $order->get_billing_email();
			$order_meta['bw_currency'] = $order->get_currency();
			$order_meta['bw_settings'] = $wcp_settings;
			$order_meta['bw_store']    = plugins_url('', __FILE__);

			// -----------------------------------
			// Save crypto currency payment info together with the order.
			// Note: this code must be on top here, as other filters will be called from here and will use these values ...
			//
			// Calculate realtime crypto currency price (if exchange is necessary)
			$exchange_rate = $this->get_exchange_rate_per_crypto();
			if (!$exchange_rate) {
				$msg = 'ERROR: Cannot determine ' . $this->get_payment_method_title() . ' exchange rate. Possible issues: store server does not allow outgoing connections, exchange rate servers are blocking incoming connections or down. ';
				WCP__log_event(__FILE__, __LINE__, $msg);
				exit('<h2 style="color:red;">' . $msg . '</h2>');
			}

			$order_total_in_crypto = ($order->get_total() / $exchange_rate);
			if (get_woocommerce_currency() != 'BTC' && get_woocommerce_currency() != 'FAIR') {
				// Apply exchange rate multiplier only for stores with non-crypto currency default currency.
				$order_total_in_crypto = $order_total_in_crypto;
			}

			$order_total_in_crypto = sprintf('%.8f', $order_total_in_crypto);

			$bitcoins_address = false;
			$fair_address     = false;

			$order_info =
				array(
					'order_meta'       => $order_meta,
					'order_id'         => $order_id,
					'order_total'      => $order_total_in_crypto,  // Order total in crypto
					'order_datetime'   => date('Y-m-d H:i:s T'),
					'requested_by_ip'  => @$_SERVER['REMOTE_ADDR'],
					'requested_by_ua'  => @$_SERVER['HTTP_USER_AGENT'],
					'requested_by_srv' => base64_encode(serialize($_SERVER)),
				);

			$ret_info_array = array();

			$ret_info_array = $this->get_electrum_util()->get_crypto_address_for_payment__electrum($order_info);

			if ($ret_info_array['result'] != 'success') {
				$msg = "ERROR: cannot generate crypto address for the order: '" . @$ret_info_array['message'] . "'";
				WCP__log_event(__FILE__, __LINE__, $msg);
				exit('<h2 style="color:blue;">' . $msg . '</h2>');
			}

			update_post_meta(
				$order_id,             // post id ($order_id)
				'order_total_in_' . $this->get_crypto_symbol(),  // meta key
				$order_total_in_crypto    // meta value. If array - will be auto-serialized
			);
			update_post_meta(
				$order_id,             // post id ($order_id)
				$this->get_gateway_id() . 's_paid_total', // meta key
				'0'    // meta value. If array - will be auto-serialized
			);
			update_post_meta(
				$order_id,             // post id ($order_id)
				$this->get_gateway_id() . 's_refunded',   // meta key
				'0'    // meta value. If array - will be auto-serialized
			);
			update_post_meta(
				$order_id,            // post id ($order_id)
				'exchange_rate',  // meta key
				$exchange_rate    // meta value. If array - will be auto-serialized
			);
			update_post_meta(
				$order_id,                 // post id ($order_id)
				'_incoming_payments',  // meta key. Starts with '_' - hidden from UI.
				array()                    // array (array('datetime'=>'', 'from_addr'=>'', 'amount'=>''),)
			);
			update_post_meta(
				$order_id,                 // post id ($order_id)
				'_payment_completed',  // meta key. Starts with '_' - hidden from UI.
				0                  // array (array('datetime'=>'', 'from_addr'=>'', 'amount'=>''),)
			);
			update_post_meta(
				$order_id,       // post id ($order_id)
				'crypto_variant',  // meta key
				$this->get_crypto_symbol()  // meta value. If array - will be auto-serialized
			);
			$this->update_order_metadata($order_id, $ret_info_array);
			// -----------------------------------
			// The crypto gateway does not take payment immediately, but it does need to change the orders status to on-hold
			// (so the store owner knows that crypto payment is pending).
			// We also need to tell WooCommerce that it needs to redirect to the thankyou page – this is done with the returned array
			// and the result being a success.
			//
			global $woocommerce;

			// Updating the order status:
			// Mark as on-hold as this triggers thank you page and email
			$order->update_status('on-hold', __('Sending email to customer and admin', 'woocommerce'));
			// Then mark as pending so woocommerce can cancel unpaid orders
			$order->update_status('pending', __('Awaiting ' . $this->get_payment_method_title() . ' (' . strtoupper($this->get_crypto_symbol()) . ') payment to arrive', 'woocommerce'));

			$this->fill_in_instructions($order, true);

			// Remove cart
			$woocommerce->cart->empty_cart();

			// Get the order key correctly and Return thankyou redirect
			$order_key = get_post_meta($order_id, '_order_key', true);

			//Get the url of payment form
			$order_url = WC()->api_request_url('WC_Gateway_' . $this->id);
			$order_url = add_query_arg('show_order', $order_key, $order_url);

			if (version_compare(WOOCOMMERCE_VERSION, '2.1', '<')) {
				return array(
					'result' 	=> 'success',
					'redirect'	=> $order_url
				);
			} else {
				return array(
					'result' 	=> 'success',
					'redirect'	=> $order_url
				);
			}
		}
		// -------------------------------------------------------------------
		// -------------------------------------------------------------------
		/**
		 * Output for the order receipt page.
		 *
		 * @access public
		 * @return void
		 */
		public function receipt_page($order_id)
		{
			// WCP__receipt_page is hooked into the "receipt" page and in the simplest case can just echo’s the description.
			// Get order object.
			$order = wc_get_order($order_id);

			// Redirect to "order received" page if the order is already paid
			if ($order->is_paid()) {
				$redirect = $order->get_checkout_order_received_url();
				wp_safe_redirect($redirect);
				exit;
			}

			$instructions = $this->fill_in_instructions($order);

			echo wpautop(wptexturize($instructions));
		}
		// -------------------------------------------------------------------
		// Loading the checkout template
		// -------------------------------------------------------------------
		public function redirect_to_template($template)
		{
			//add_action('wp_enqueue_scripts', 'bnomics_enqueue_stylesheets' );
			//add_action('wp_enqueue_scripts', 'bnomics_enqueue_scripts' );
			if ($overridden_template = locate_template($template)) {
				// locate_template() returns path to file
				// if either the child theme or the parent theme have overridden the template
				load_template($overridden_template);
			} else {
				// If neither the child nor parent theme have overridden the template,
				// we load the template from the 'templates' sub-directory of the directory this file is in
				load_template(plugin_dir_path(__FILE__) . "/" . $template);
			}
			exit();
		}
		// -------------------------------------------------------------------
		// Loading the order and redirect to checkout form template
		// -------------------------------------------------------------------
		public function checkout_template_loading()
		{
			$order_key = isset($_REQUEST["show_order"]) ? $_REQUEST["show_order"] : "";
			$order_id = wc_get_order_id_by_order_key($order_key);
			$order = wc_get_order($order_id);
			if (!$order) { //If order not exist
				wp_redirect(home_url());
				exit();
			}

			if ($order_key) {
				$this->redirect_to_template('wcp-checkout-template.php');
			}
		}
		// -------------------------------------------------------------------
		// -------------------------------------------------------------------
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool     $sent_to_admin
		 * @return void
		 */
		public function email_instructions($order, $sent_to_admin)
		{
			if ($sent_to_admin) {
				return;
			}
			if (!in_array($order->get_status(), array('pending', 'on-hold'), true)) {
				return;
			}
			if ($order->get_payment_method() !== 'bitcoin_btc' || $order->get_payment_method() !== 'faircoin_fair') {
				return;
			}

			$instructions = $this->fill_in_instructions($order);

			echo wpautop(wptexturize($instructions));
		}

		abstract public function fill_in_instructions($order, $add_order_note = false);
	}
	//==============================================================================
	// END Class WCP_Crypto
	//==============================================================================

	//==============================================================================
	// include all gateways implemented
	require_once dirname(__FILE__) . '/wcp-crypto-gateway-btc.php';
	require_once dirname(__FILE__) . '/wcp-crypto-gateway-fair.php';

	//==============================================================================
	// Hook into WooCommerce - add necessary hooks and filters
	add_filter('woocommerce_payment_gateways', 'WCP__add_crypto_gateway');

	// Disable unnecessary billing fields.
	// Note: it affects whole store.
	// add_filter ('woocommerce_checkout_fields' ,     'WCP__woocommerce_checkout_fields' );
	add_filter('woocommerce_currencies', 'WCP__add_btc_currency');
	add_filter('woocommerce_currency_symbol', 'WCP__add_btc_currency_symbol', 10, 2);

	// Change [Order] button text on checkout screen.
	// Note: this will affect all payment methods.
	// add_filter ('woocommerce_order_button_text',    'WCP__order_button_text');

	//==============================================================================
	/**
	 * Add the gateway to WooCommerce
	 *
	 * @access public
	 * @param array $methods
	 * @package
	 * @return array/
	 */
	function WCP__add_crypto_gateway($methods)
	{
		$methods[] = 'WCP_Bitcoin_BTC';
		$methods[] = 'WCP_FairCoin_FAIR';
		return $methods;
	}
	//==============================================================================
	// Our hooked in function - $fields is passed via the filter!
	function WCP__woocommerce_checkout_fields($fields)
	{
		unset($fields['order']['order_comments']);
		unset($fields['billing']['billing_first_name']);
		unset($fields['billing']['billing_last_name']);
		unset($fields['billing']['billing_company']);
		unset($fields['billing']['billing_address_1']);
		unset($fields['billing']['billing_address_2']);
		unset($fields['billing']['billing_city']);
		unset($fields['billing']['billing_postcode']);
		unset($fields['billing']['billing_country']);
		unset($fields['billing']['billing_state']);
		unset($fields['billing']['billing_phone']);
		return $fields;
	}
	//==============================================================================
	// Add supported currencies
	function WCP__add_btc_currency($currencies)
	{
		$currencies['BTC'] = __('Bitcoin', 'woocommerce');
		$currencies['FAIR'] = __('FairCoin', 'woocommerce');
		return $currencies;
	}

	//==============================================================================
	// BTC symbol
	function WCP__add_btc_currency_symbol($currency_symbol, $currency)
	{
		switch ($currency) {
			case 'BTC':
				$currency_symbol = '฿';
				break;
			case 'FAIR':
				$currency_symbol = 'ƒ';
				break;
		}

		return $currency_symbol;
	}
	//==============================================================================
	// Text for order button
	function WCP__order_button_text()
	{
		return 'Continue';
	}

	//==============================================================================
	// Payment Completed
	function WCP__process_payment_completed_for_order($gateway_id, $order_id, $crypto_paid = false)
	{
		if ($crypto_paid) {
			update_post_meta($order_id, $gateway_id . 's_paid_total', $crypto_paid);
		}

		// Payment completed
		// Make sure this logic is done only once, in case customer keep sending payments :)
		if (!get_post_meta($order_id, '_payment_completed', true)) {
			update_post_meta($order_id, '_payment_completed', '1');
			WCP__log_event(__FILE__, __LINE__, "Success: order '{$order_id}' paid in full. Processing and notifying customer ...");
		}
		// Instantiate order object.
		$order = wc_get_order($order_id);
		$order->add_order_note(__('Order paid in full', 'woocommerce'));

		$order->payment_complete();

		$wcp_settings = wcp__get_settings();

		switch ($gateway_id) {
			case 'bitcoin_btc':
				$autocomplete_setting = esc_attr(get_option(WCP_BTC_SETTINGS)['autocomplete_paid_orders']);
				break;
			case 'faircoin_fair':
				$autocomplete_setting = esc_attr(get_option(WCP_FAIR_SETTINGS)['autocomplete_paid_orders']);
				break;
			default:
				break;
		}

		if ($autocomplete_setting == '1') {
			// Complete the order
			$order->update_status('completed', __('Order marked as completed according to plugin settings', 'woocommerce'));
		} else
			$order->update_status('processing', __('Order marked as processing according to plugin settings', 'woocommerce'));


		// Notify admin about payment processed
		$email = get_option('admin_email');
		if ($email) {
			$sanitary_email = sanitize_email($email);
			// Send email from admin to admin
			wp_mail(
				$sanitary_email,
				"Full payment received for order ID: '{$order_id}'",
				"Order ID: '{$order_id}' paid in full. Received '$gateway_id': '$crypto_paid'. Please process and complete order for customer."
			);
		}
	}
	//==============================================================================
}
