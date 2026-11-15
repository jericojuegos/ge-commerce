<?php
/**
 * Idempotent Custom Table Database Migrator (`wp_gec_*`) for GE Commerce.
 *
 * @package GeCommerce\WordPress\Database
 */

declare(strict_types=1);

namespace GeCommerce\WordPress\Database;

final class Migrator {

	public const OPTION_KEY = 'ge_commerce_db_version';
	public const DB_VERSION = '1.0.0';

	/**
	 * Check installed DB version and run migrations if needed.
	 */
	public static function maybeMigrate(): void {
		if ( ! function_exists( 'get_option' ) ) {
			return;
		}

		$installedVersion = (string) \get_option( self::OPTION_KEY, '' );
		if ( self::DB_VERSION !== $installedVersion ) {
			self::migrate();
		}
	}

	/**
	 * Execute idempotent `dbDelta` migrations for all `wp_gec_*` custom tables.
	 */
	public static function migrate(): void {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return;
		}

		$charsetCollate = $wpdb->get_charset_collate();
		$prefix         = $wpdb->prefix . 'gec_';
		$queries        = self::getSchemaQueries( $prefix, $charsetCollate );

		if ( defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		if ( function_exists( 'dbDelta' ) ) {
			\dbDelta( $queries );
		}

		if ( function_exists( 'update_option' ) ) {
			\update_option( self::OPTION_KEY, self::DB_VERSION, true );
		}
	}

	/**
	 * Return raw SQL `CREATE TABLE` definitions formatted for WordPress `dbDelta()`.
	 *
	 * @param string $prefix         Full table prefix (e.g., `wp_gec_`).
	 * @param string $charsetCollate Database charset and collation clause.
	 * @return array<string, string> Map of logical table name to SQL DDL string.
	 */
	public static function getSchemaQueries( string $prefix = 'wp_gec_', string $charsetCollate = '' ): array {
		return [
			'products'           => "CREATE TABLE {$prefix}products (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				slug varchar(191) NOT NULL,
				title varchar(255) NOT NULL,
				description longtext NOT NULL,
				status varchar(32) NOT NULL DEFAULT 'draft',
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY slug (slug),
				KEY status (status)
			) {$charsetCollate};",

			'skus'               => "CREATE TABLE {$prefix}skus (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				product_id bigint(20) unsigned NOT NULL,
				sku_code varchar(100) NOT NULL,
				attributes_json longtext NOT NULL,
				price_cents bigint(20) NOT NULL DEFAULT 0,
				available_qty int(11) NOT NULL DEFAULT 0,
				reserved_qty int(11) NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				UNIQUE KEY sku_code (sku_code),
				KEY product_id (product_id)
			) {$charsetCollate};",

			'sessions'           => "CREATE TABLE {$prefix}sessions (
				session_key varchar(128) NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				cart_json longtext NOT NULL,
				coupon_code varchar(64) DEFAULT NULL,
				expires_at datetime NOT NULL,
				PRIMARY KEY  (session_key),
				KEY user_id (user_id),
				KEY expires_at (expires_at)
			) {$charsetCollate};",

			'coupons'            => "CREATE TABLE {$prefix}coupons (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				code varchar(64) NOT NULL,
				discount_type varchar(32) NOT NULL,
				amount_value bigint(20) NOT NULL DEFAULT 0,
				min_order_cents bigint(20) NOT NULL DEFAULT 0,
				usage_limit int(11) DEFAULT NULL,
				used_count int(11) NOT NULL DEFAULT 0,
				expires_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY code (code)
			) {$charsetCollate};",

			'orders'             => "CREATE TABLE {$prefix}orders (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				order_number varchar(64) NOT NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(32) NOT NULL DEFAULT 'PENDING_PAYMENT',
				subtotal_cents bigint(20) NOT NULL DEFAULT 0,
				discount_cents bigint(20) NOT NULL DEFAULT 0,
				tax_cents bigint(20) NOT NULL DEFAULT 0,
				shipping_cents bigint(20) NOT NULL DEFAULT 0,
				total_cents bigint(20) NOT NULL DEFAULT 0,
				payment_gateway varchar(64) NOT NULL DEFAULT '',
				idempotency_key varchar(128) DEFAULT NULL,
				created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE KEY order_number (order_number),
				UNIQUE KEY idempotency_key (idempotency_key),
				KEY user_id (user_id),
				KEY status (status)
			) {$charsetCollate};",

			'order_items'        => "CREATE TABLE {$prefix}order_items (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				order_id bigint(20) unsigned NOT NULL,
				sku_id bigint(20) unsigned NOT NULL,
				sku_code varchar(100) NOT NULL,
				title varchar(255) NOT NULL,
				qty int(11) NOT NULL DEFAULT 1,
				unit_price_cents bigint(20) NOT NULL DEFAULT 0,
				line_total_cents bigint(20) NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				KEY order_id (order_id),
				KEY sku_id (sku_id)
			) {$charsetCollate};",

			'stock_reservations' => "CREATE TABLE {$prefix}stock_reservations (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				order_id bigint(20) unsigned NOT NULL,
				sku_id bigint(20) unsigned NOT NULL,
				qty int(11) NOT NULL DEFAULT 1,
				status varchar(32) NOT NULL DEFAULT 'ACTIVE',
				expires_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY order_id (order_id),
				KEY sku_id (sku_id),
				KEY status_expires (status, expires_at)
			) {$charsetCollate};",
		];
	}
}
