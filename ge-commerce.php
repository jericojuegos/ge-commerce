<?php
/**
 * Plugin Name:       GE Commerce
 * Plugin URI:        https://jutsuworks.dev/ge-commerce
 * Description:       Standalone, zero-bloat WordPress E-Commerce Core engine built on custom tables and Hexagonal Architecture (Ports & Adapters).
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            JutsuWorks
 * License:           GPL-2.0-or-later
 * Text Domain:       ge-commerce
 *
 * @package GeCommerce
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GE_COMMERCE_VERSION', '0.1.0' );
define( 'GE_COMMERCE_DB_VERSION', '1.0.0' );
define( 'GE_COMMERCE_FILE', __FILE__ );
define( 'GE_COMMERCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'GE_COMMERCE_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( GE_COMMERCE_PATH . 'vendor/autoload.php' ) ) {
	require_once GE_COMMERCE_PATH . 'vendor/autoload.php';
}

register_activation_hook(
	__FILE__,
	static function (): void {
		\GeCommerce\WordPress\Database\Migrator::migrate();

		if ( function_exists( 'get_role' ) ) {
			$adminRole = \get_role( 'administrator' );
			if ( null !== $adminRole && ! $adminRole->has_cap( 'manage_ge_commerce' ) ) {
				$adminRole->add_cap( 'manage_ge_commerce' );
			}
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		\GeCommerce\Core\Plugin::instance()->boot();
	}
);
