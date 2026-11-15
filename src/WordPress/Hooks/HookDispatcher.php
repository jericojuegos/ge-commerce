<?php
/**
 * WordPress Hook Dispatcher Adapter for GE Commerce.
 *
 * @package GeCommerce\WordPress\Hooks
 */

declare(strict_types=1);

namespace GeCommerce\WordPress\Hooks;

final class HookDispatcher {

	/**
	 * Dispatch a WordPress action if running inside WordPress, or no-op in isolated unit tests.
	 *
	 * @param string $hookName Action hook name.
	 * @param mixed  ...$args  Arguments passed to callbacks.
	 */
	public function doAction( string $hookName, mixed ...$args ): void {
		if ( function_exists( 'do_action' ) ) {
			\do_action( $hookName, ...$args );
		}
	}

	/**
	 * Apply a WordPress filter if running inside WordPress, or return the unfiltered value in unit tests.
	 *
	 * @template T
	 * @param string $hookName Filter hook name.
	 * @param T      $value    Value to filter.
	 * @param mixed  ...$args  Additional arguments passed to filter callbacks.
	 * @return T|mixed
	 */
	public function applyFilters( string $hookName, mixed $value, mixed ...$args ): mixed {
		if ( function_exists( 'apply_filters' ) ) {
			return \apply_filters( $hookName, $value, ...$args );
		}

		return $value;
	}
}
