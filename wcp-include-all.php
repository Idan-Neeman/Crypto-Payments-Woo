<?php
//==============================================================================
// Global definitions
if (!defined('WCP_PLUGIN_NAME')) {
    define('WCP_PLUGIN_NAME', 'Crypto Payments Woo');
}
if (!defined('WCP_VERSION')) {
    define('WCP_VERSION', '1.00');
}
if (!defined('WCP_SETTINGS_NAME')) {
    define('WCP_SETTINGS_NAME', 'WCP-Settings');
}
//==============================================================================
// Define Settings Names
if (!defined('WCP_BTC_SETTINGS')) {
    define('WCP_BTC_SETTINGS', 'WCP_btc');
}
if (!defined('WCP_FAIR_SETTINGS')) {
    define('WCP_FAIR_SETTINGS', 'WCP_fair');
}
if (!defined('WCP_GENERAL_SETTINGS')) {
    define('WCP_GENERAL_SETTINGS', 'WCP_general');
}
if (!defined('WCP_API_SETTINGS')) {
    define('WCP_API_SETTINGS', 'WCP_api');
}
//==============================================================================
// i18n plugin domain for language files
if (!defined('WCP_I18N_DOMAIN')) {
    define('WCP_I18N_DOMAIN', 'wcp');
}
//==============================================================================
// Select math library
if (extension_loaded('gmp') && !defined('USE_EXT')) {
    define('USE_EXT', 'GMP');
} elseif (extension_loaded('bcmath') && !defined('USE_EXT')) {
    define('USE_EXT', 'BCMATH');
}

//==============================================================================
// Safely load necessary modules
if (!class_exists('bcmath_Utils')) {
    include(dirname(__FILE__) . '/libs/util/bcmath_Utils.php');
}
if (!class_exists('gmp_Utils')) {
    include(dirname(__FILE__) . '/libs/util/gmp_Utils.php');
}
if (!class_exists('CurveFp')) {
    include(dirname(__FILE__) . '/libs/CurveFp.php');
}
if (!class_exists('Point')) {
    include(dirname(__FILE__) . '/libs/Point.php');
}
if (!class_exists('NumberTheory')) {
    include(dirname(__FILE__) . '/libs/NumberTheory.php');
}
if (!class_exists('ElectrumHelper')) {
    include(dirname(__FILE__) . '/libs/ElectrumHelper.php');
}

//==============================================================================
// Include Plugin Files
if (!function_exists('wcp__get_settings')) {
    include(dirname(__FILE__) . '/wcp-admin.php');
}
if (!class_exists('CryptoCronJob')) {
    include(dirname(__FILE__) . '/wcp-cron.php');
}
if (!class_exists('BlockchainAPI')) {
    include(dirname(__FILE__) . '/wcp-crypto-blockchain-apis.php');
}
if (!function_exists('WCP__plugins_loaded__load_crypto_gateway')) {
    include(dirname(__FILE__) . '/wcp-crypto-gateway.php');
}
if (!class_exists('ExchangeRateAPI')) {
    include(dirname(__FILE__) . '/wcp-crypto-exchange-rate-apis.php');
}
if (!class_exists('BlockchainAPI')) {
    include(dirname(__FILE__) . '/wcp-crypto-blockchain-apis.php');
}
if (!function_exists('WCP_activate')) {
    include(dirname(__FILE__) . '/wcp-woocommerce.php');
}
if (!class_exists('ElectrumUtil')) {
    include(dirname(__FILE__) . '/wcp-utils.php');
}
if (!function_exists('WCP__MATH_generate_crypto_address_from_mpk_v1')) {
    include_once(dirname(__FILE__) . '/wcp-mpkgen.php');
}
if (!class_exists('Table')) {
    include_once(dirname(__FILE__) . '/classes/class-table.php');
}
if (!class_exists('WP_OSA')) {
    include_once(dirname(__FILE__) . '/class-wp-osa.php');
}
if (!function_exists('WCP__stripslashes')) {
    include_once(dirname(__FILE__) . '/wposa-init.php');
}
//==============================================================================
// Load cashaddr libs
require_once dirname(__FILE__) . '/libs/cashaddr/Base32.php';
require_once dirname(__FILE__) . '/libs/cashaddr/CashAddress.php';
require_once dirname(__FILE__) . '/libs/cashaddr/Exception/Base32Exception.php';
require_once dirname(__FILE__) . '/libs/cashaddr/Exception/CashAddressException.php';
require_once dirname(__FILE__) . '/libs/cashaddr/Exception/InvalidChecksumException.php';
