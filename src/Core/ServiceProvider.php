<?php
/**
 * Base ServiceProvider contract for GE Commerce feature modules.
 *
 * @package GeCommerce\Core
 */

declare(strict_types=1);

namespace GeCommerce\Core;

abstract class ServiceProvider {

	/**
	 * Register module bindings into the DI container.
	 *
	 * @param Container $container DI container instance.
	 */
	abstract public function register( Container $container ): void;

	/**
	 * Boot module hooks and routes after all providers are registered.
	 *
	 * @param Container $container DI container instance.
	 */
	public function boot( Container $container ): void {
		// Optional override in concrete feature providers.
	}
}
