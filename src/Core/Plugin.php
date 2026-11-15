<?php
/**
 * Core Plugin Bootstrapper for GE Commerce.
 *
 * @package GeCommerce\Core
 */

declare(strict_types=1);

namespace GeCommerce\Core;

use GeCommerce\Modules\Cart\Presentation\Providers\CartServiceProvider;
use GeCommerce\Modules\Catalog\Presentation\Providers\CatalogServiceProvider;
use GeCommerce\Modules\Coupon\Presentation\Providers\CouponServiceProvider;
use GeCommerce\WordPress\Database\Migrator;
use GeCommerce\WordPress\Hooks\HookDispatcher;

final class Plugin {

	private static ?self $instance = null;

	private readonly Container $container;

	private readonly ModuleRegistry $modules;

	public function __construct( ?Container $container = null ) {
		$this->container = $container ?? new Container();
		$this->modules   = new ModuleRegistry( $this->container );

		$this->registerCoreServices();
		$this->registerFeatureModules();
	}

	/**
	 * Retrieve singleton plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Boot the plugin lifecycle, database migrations, admin menu, and feature modules.
	 */
	public function boot(): void {
		Migrator::maybeMigrate();

		if ( function_exists( 'add_action' ) ) {
			\add_action( 'admin_menu', [ $this, 'registerAdminMenu' ] );
			\add_action( 'admin_enqueue_scripts', [ $this, 'enqueueAdminAssets' ] );
		}

		$this->modules->bootAll();
	}

	/**
	 * Register the GE Commerce top-level admin menu page.
	 */
	public function registerAdminMenu(): void {
		if ( ! function_exists( 'add_menu_page' ) ) {
			return;
		}

		\add_menu_page(
			'GE Commerce',
			'GE Commerce',
			'manage_options',
			'ge-commerce',
			[ $this, 'renderAdminShell' ],
			'dashicons-cart',
			56
		);
	}

	/**
	 * Render the root mount container for the React Admin SPA.
	 */
	public function renderAdminShell(): void {
		echo '<div id="ge-commerce-admin-root" class="gec-admin-shell"></div>';
	}

	/**
	 * Enqueue compiled React Admin assets on the GE Commerce admin screen.
	 *
	 * @param string $hookSuffix Current admin page hook suffix.
	 */
	public function enqueueAdminAssets( string $hookSuffix ): void {
		if ( 'toplevel_page_ge-commerce' !== $hookSuffix ) {
			return;
		}

		if ( ! defined( 'GE_COMMERCE_PATH' ) || ! defined( 'GE_COMMERCE_URL' ) ) {
			return;
		}

		$entryBase = file_exists( GE_COMMERCE_PATH . 'build/index.tsx.js' ) ? 'build/index.tsx' : 'build/index';
		$assetFile = GE_COMMERCE_PATH . $entryBase . '.asset.php';
		$asset     = file_exists( $assetFile )
			? require $assetFile
			: [
				'dependencies' => [ 'wp-element', 'wp-components', 'wp-api-fetch' ],
				'version'      => defined( 'GE_COMMERCE_VERSION' ) ? GE_COMMERCE_VERSION : '0.1.0',
			];

		if ( file_exists( GE_COMMERCE_PATH . $entryBase . '.js' ) && function_exists( 'wp_enqueue_script' ) ) {
			\wp_enqueue_script(
				'ge-commerce-admin',
				GE_COMMERCE_URL . $entryBase . '.js',
				$asset['dependencies'],
				(string) $asset['version'],
				true
			);
		}

		if ( function_exists( 'wp_enqueue_style' ) ) {
			\wp_enqueue_style( 'wp-components' );
			if ( file_exists( GE_COMMERCE_PATH . $entryBase . '.css' ) ) {
				\wp_enqueue_style(
					'ge-commerce-admin-style',
					GE_COMMERCE_URL . $entryBase . '.css',
					[ 'wp-components' ],
					(string) $asset['version']
				);
			}
		}
	}

	/**
	 * Expose the DI container.
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Expose the ModuleRegistry.
	 */
	public function modules(): ModuleRegistry {
		return $this->modules;
	}

	/**
	 * Register core infrastructure singletons in the container.
	 */
	private function registerCoreServices(): void {
		$this->container->instance( Container::class, $this->container );
		$this->container->instance( ModuleRegistry::class, $this->modules );
		$this->container->singleton(
			HookDispatcher::class,
			static fn(): HookDispatcher => new HookDispatcher()
		);
	}

	/**
	 * Register active feature module service providers.
	 */
	private function registerFeatureModules(): void {
		$this->modules->add( CatalogServiceProvider::class );
		$this->modules->add( CouponServiceProvider::class );
		$this->modules->add( CartServiceProvider::class );
	}
}
