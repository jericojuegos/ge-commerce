<?php
/**
 * Dependency Injection Container for GE Commerce.
 *
 * @package GeCommerce\Core
 */

declare(strict_types=1);

namespace GeCommerce\Core;

use Closure;
use RuntimeException;

final class Container {

	/**
	 * Registered factory definitions.
	 *
	 * @var array<string, Closure(Container): mixed>
	 */
	private array $definitions = [];

	/**
	 * Singleton flags per service identifier.
	 *
	 * @var array<string, bool>
	 */
	private array $singletons = [];

	/**
	 * Resolved singleton instances.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = [];

	/**
	 * Register a transient binding in the container.
	 *
	 * @param string                    $id       Class or interface identifier.
	 * @param Closure(Container): mixed $concrete Factory closure returning the dependency.
	 */
	public function bind( string $id, Closure $concrete ): void {
		$this->definitions[ $id ] = $concrete;
		$this->singletons[ $id ]  = false;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Register a shared singleton binding in the container.
	 *
	 * @param string                    $id       Class or interface identifier.
	 * @param Closure(Container): mixed $concrete Factory closure returning the singleton instance.
	 */
	public function singleton( string $id, Closure $concrete ): void {
		$this->definitions[ $id ] = $concrete;
		$this->singletons[ $id ]  = true;
		unset( $this->instances[ $id ] );
	}

	/**
	 * Bind an existing object instance into the container.
	 *
	 * @param string $id       Class or interface identifier.
	 * @param mixed  $instance Concrete instance.
	 */
	public function instance( string $id, mixed $instance ): void {
		$this->instances[ $id ]  = $instance;
		$this->singletons[ $id ] = true;
	}

	/**
	 * Check whether an identifier is bound in the container.
	 *
	 * @param string $id Class or interface identifier.
	 */
	public function has( string $id ): bool {
		return array_key_exists( $id, $this->instances ) || array_key_exists( $id, $this->definitions );
	}

	/**
	 * Resolve a service from the container.
	 *
	 * @template T
	 * @param class-string<T>|string $id Class or interface identifier.
	 * @return T|mixed
	 * @throws RuntimeException When the service is not registered.
	 */
	public function get( string $id ): mixed {
		if ( array_key_exists( $id, $this->instances ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->definitions[ $id ] ) ) {
			throw new RuntimeException( sprintf( 'Service "%s" is not registered in the GE Commerce container.', $id ) );
		}

		$resolved = ( $this->definitions[ $id ] )( $this );

		if ( ! empty( $this->singletons[ $id ] ) ) {
			$this->instances[ $id ] = $resolved;
		}

		return $resolved;
	}
}
