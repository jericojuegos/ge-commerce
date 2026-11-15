<?php
/**
 * WordPress Options Adapter for Tax & Shipping Zone Configuration.
 *
 * @package GeCommerce\Modules\Cart\Infrastructure\Repositories
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Infrastructure\Repositories;

use GeCommerce\Modules\Cart\Domain\Contracts\ZoneConfigRepositoryInterface;
use GeCommerce\Modules\Cart\Domain\ValueObjects\ShippingZone;
use GeCommerce\Modules\Cart\Domain\ValueObjects\TaxZone;

final class WpZoneConfigRepository implements ZoneConfigRepositoryInterface {

	public const OPTION_TAX_ZONES      = 'ge_commerce_tax_zones';
	public const OPTION_SHIPPING_ZONES = 'ge_commerce_shipping_zones';

	/**
	 * @return list<TaxZone>
	 */
	public function getTaxZones(): array {
		$raw = function_exists( 'get_option' ) ? \get_option( self::OPTION_TAX_ZONES, null ) : null;
		if ( ! is_array( $raw ) || [] === $raw ) {
			return $this->defaultTaxZones();
		}

		$zones = [];
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$zones[] = new TaxZone(
				country: (string) ( $row['country'] ?? '*' ),
				region: (string) ( $row['region'] ?? '*' ),
				rateBps: max( 0, (int) ( $row['rate_bps'] ?? 0 ) ),
				label: (string) ( $row['label'] ?? '' )
			);
		}

		return [] !== $zones ? $zones : $this->defaultTaxZones();
	}

	/**
	 * @return list<ShippingZone>
	 */
	public function getShippingZones(): array {
		$raw = function_exists( 'get_option' ) ? \get_option( self::OPTION_SHIPPING_ZONES, null ) : null;
		if ( ! is_array( $raw ) || [] === $raw ) {
			return $this->defaultShippingZones();
		}

		$zones = [];
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$freeOver = isset( $row['free_over_cents'] ) && null !== $row['free_over_cents'] && '' !== $row['free_over_cents']
				? max( 0, (int) $row['free_over_cents'] )
				: null;
			$zones[]  = new ShippingZone(
				country: (string) ( $row['country'] ?? '*' ),
				region: (string) ( $row['region'] ?? '*' ),
				method: (string) ( $row['method'] ?? 'standard' ),
				flatRateCents: max( 0, (int) ( $row['flat_rate_cents'] ?? 0 ) ),
				freeOverCents: $freeOver,
				label: (string) ( $row['label'] ?? '' )
			);
		}

		return [] !== $zones ? $zones : $this->defaultShippingZones();
	}

	public function saveZones( array $taxZones, array $shippingZones ): void {
		if ( ! function_exists( 'update_option' ) ) {
			return;
		}

		\update_option(
			self::OPTION_TAX_ZONES,
			array_map( static fn( TaxZone $z ): array => $z->toArray(), $taxZones ),
			false
		);
		\update_option(
			self::OPTION_SHIPPING_ZONES,
			array_map( static fn( ShippingZone $z ): array => $z->toArray(), $shippingZones ),
			false
		);
	}

	/**
	 * @return list<TaxZone>
	 */
	private function defaultTaxZones(): array {
		return [
			new TaxZone( 'US', 'CA', 825, 'California Sales Tax (8.25%)' ),
			new TaxZone( 'US', '*', 600, 'US Base Sales Tax (6.00%)' ),
			new TaxZone( '*', '*', 0, 'Zero Tax' ),
		];
	}

	/**
	 * @return list<ShippingZone>
	 */
	private function defaultShippingZones(): array {
		return [
			new ShippingZone( 'US', '*', 'standard', 599, 7500, 'US Standard ($5.99, Free >= $75)' ),
			new ShippingZone( 'US', '*', 'express', 1499, null, 'US Express ($14.99)' ),
			new ShippingZone( '*', '*', 'standard', 1999, null, 'International Standard ($19.99)' ),
		];
	}
}
