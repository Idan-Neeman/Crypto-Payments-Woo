<?php
/**
 * Defines the class Table and it's children
 *
 * @package    (wcp\classes\)
 */

defined( 'ABSPATH' ) || die( 'Access Restricted!' );

/**
 * Table
 */
abstract class Table {

	/**
	 * Returns the crypto symbol the child table class implements
	 */
	abstract public static function get_crypto_symbol();

	/**
	 * Schema version for the table
	 *
	 * @return float
	 */
	abstract public static function get_schema_version();

	/**
	 * Return the query that creates the database if one does not exist already with the same name
	 *
	 * @param  string $crypto_addresses_table_name name of the table to be created.
	 * @return string                           query that creates the required table.
	 */
	abstract public static function get_query( $crypto_addresses_table_name );

	/**
	 * Return the table name to be used by the child class
	 *
	 * @return string the table name based on the crypto symbol set by the child class
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'wcp_addresses_' . static::get_crypto_symbol();
	}

	/**
	 * Create the table(s) needed for the operation of the child symbol
	 *
	 * @return void nothing is returned.
	 */
	public static function create_database_tables() {
		global $wpdb;

		$wcp_settings         = wcp__get_settings();
		$must_update_settings = false;

		$crypto_addresses_table_name = static::get_table_name();

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$crypto_addresses_table_name'" ) != $crypto_addresses_table_name ) {
			$b_first_time = true;
		} else {
			$b_first_time = false;
		}

		$query = static::get_query( $crypto_addresses_table_name );
		$wpdb->query( $query );
		// ----------------------------------------------------------
		// upgrade wcp_btc_addresses table, add additional indexes
		if ( ! $b_first_time ) {
			$version = floatval( $wcp_settings['database_schema_version']);
			// For future updates.
		} else {
			if ( ! is_array( $wcp_settings['database_schema_version'] ) ) {
				$wcp_settings['database_schema_version'] = array();
			}
			$wcp_settings['database_schema_version'][ static::get_crypto_symbol() ] = static::get_schema_version();
		}
	}

	/**
	 * Delete the databases upon created
	 *
	 * @return void
	 */
	public static function delete_database_tables() {
		global $wpdb;

		$crypto_addresses_table_name = static::get_table_name();

		$wpdb->query( "DROP TABLE IF EXISTS `$crypto_addresses_table_name`" );
	}
}
/**
 * Class implementing FAIR Tables
 */
class TableFAIR extends Table {

	/**
	 * Variant this table represents
	 *
	 * @return string the faircoin symbol in this case fair
	 */
	public static function get_crypto_symbol() {
		return 'fair';
	}

	/**
	 * Schema version, this is useful for future changes
	 *
	 * @return int version of the schema
	 */
	public static function get_schema_version() {
		return 1.0;
	}

	/**
	 * Query string that allows the creation of the table needed
	 * fair includes fair_address
	 *
	 * @param  string $crypto_addresses_table_name name of the tabel to be created.
	 * @return strign                           sting containing the SQL
	 */
	public static function get_query( $crypto_addresses_table_name ) {
		return "CREATE TABLE IF NOT EXISTS `$crypto_addresses_table_name` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `fair_address` char(80),
            `origin_id` char(128) NOT NULL DEFAULT '',
            `index_in_wallet` bigint(20) NOT NULL DEFAULT '0',
            `status` char(16)  NOT NULL DEFAULT 'unknown',
            `last_assigned_to_ip` char(16) NOT NULL DEFAULT '0.0.0.0',
            `assigned_at` bigint(20) NOT NULL DEFAULT '0',
            `total_received_funds` DECIMAL( 16, 8 ) NOT NULL DEFAULT '0.00000000',
            `received_funds_checked_at` bigint(20) NOT NULL DEFAULT '0',
            `address_meta` MEDIUMBLOB NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `fair_address` (`fair_address`),
            KEY `index_in_wallet` (`index_in_wallet`),
            KEY `origin_id` (`origin_id`),
            KEY `status` (`status`)
            );";
	}
}

/**
 * Class implementing BTC Tables
 */
class TableBTC extends Table {
	/**
	 * Variant this table represents
	 *
	 * @return string the bitcoin symbol in this case btc
	 */
	public static function get_crypto_symbol() {
		return 'btc';
	}
	/**
	 * Schema version, this is useful for future changes
	 *
	 * @return int version of the schema
	 */
	public static function get_schema_version() {
		return 1.0;
	}
	/**
	 * Query string that allows the creation of the table needed
	 *
	 * @param  string $crypto_addresses_table_name name of the tabel to be created.
	 * @return strign                           sting containing the SQL
	 */
	public static function get_query( $crypto_addresses_table_name ) {
		return "CREATE TABLE IF NOT EXISTS `$crypto_addresses_table_name` (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `btc_address` char(36) NOT NULL,
            `origin_id` char(128) NOT NULL DEFAULT '',
            `index_in_wallet` bigint(20) NOT NULL DEFAULT '0',
            `status` char(16)  NOT NULL DEFAULT 'unknown',
            `last_assigned_to_ip` char(16) NOT NULL DEFAULT '0.0.0.0',
            `assigned_at` bigint(20) NOT NULL DEFAULT '0',
            `total_received_funds` DECIMAL( 16, 8 ) NOT NULL DEFAULT '0.00000000',
            `received_funds_checked_at` bigint(20) NOT NULL DEFAULT '0',
            `address_meta` MEDIUMBLOB NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `btc_address` (`btc_address`),
            KEY `index_in_wallet` (`index_in_wallet`),
            KEY `origin_id` (`origin_id`),
            KEY `status` (`status`)
            );";
	}
}
