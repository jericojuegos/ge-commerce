<?php
/**
 * Feature Module Registry for GE Commerce.
 *
 * @package GeCommerce\Core
 */

declare(strict_types=1);

namespace GeCommerce\Core;

final class ModuleRegistry {

	/**
	 * Registered module service providers.
	 *
	 * @var list<ServiceProvider>
	 */
	private array $providers = [];

	/**
	 * Whether the registry has booted.
	 */
	private bool $booted = false;

	public function __construct(
		private readonly Container $container
	) {}

	/**
	 * Add a module ServiceProvider instance or class-string to the registry.
	 *
	 * @param ServiceProvider|class-string<ServiceProvider> $provider Provider instance or class name.
	 */
	public function add( ServiceProvider|string $provider ): self {
		$instance = is_string( $provider ) ? new $provider() : $provider;
		$this->providers[] = $instance;
		$instance->register( $this->container );

		if ( $this->booted ) {
			$instance->boot( $this->container );
		}

		return $this;
	}

	/**
	 * Boot all registered module service providers.
	 */
	public function bootAll(): void {
		if ( $this->booted ) {
			return;
		}

		foreach ( $this->providers as $provider ) {
			$provider->boot( $this->container );
		}

		$this->booted = true;
	}

	/**
	 * Return all registered providers.
	 *
	 * @return list<ServiceProvider>
	 */
	public function providers(): array {
		return $this->providers;
	}
}
