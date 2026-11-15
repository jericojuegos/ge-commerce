<?php
/**
 * Application Handler for Saving Tax & Shipping Zone Configurations.
 *
 * @package GeCommerce\Modules\Cart\Application\Handlers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Application\Handlers;

use GeCommerce\Modules\Cart\Domain\Contracts\ZoneConfigRepositoryInterface;
use GeCommerce\Modules\Cart\Domain\ValueObjects\ShippingZone;
use GeCommerce\Modules\Cart\Domain\ValueObjects\TaxZone;

final class SaveZoneConfigHandler {

	public function __construct(
		private readonly ZoneConfigRepositoryInterface $zoneRepo
	) {}

	/**
	 * @param list<array{country: string, region: string, rate_bps: int, label?: string}>                                    $taxRows
	 * @param list<array{country: string, region: string, method: string, flat_rate_cents: int, free_over_cents?: ?int, label?: string}> $shippingRows
	 * @return array{tax_zones: list<TaxZone>, shipping_zones: list<ShippingZone>}
	 */
	public function execute( array $taxRows, array $shippingRows ): array {
		$taxZones = [];
		foreach ( $taxRows as $row ) {
			$taxZones[] = new TaxZone(
				country: (string) $row['country'],
				region: (string) $row['region'],
				rateBps: (int) $row['rate_bps'],
				label: (string) ( $row['label'] ?? '' )
			);
		}

		$shippingZones = [];
		foreach ( $shippingRows as $row ) {
			$freeOver        = isset( $row['free_over_cents'] ) && null !== $row['free_over_cents'] && '' !== $row['free_over_cents']
				? (int) $row['free_over_cents']
				: null;
			$shippingZones[] = new ShippingZone(
				country: (string) $row['country'],
				region: (string) $row['region'],
				method: (string) $row['method'],
				flatRateCents: (int) $row['flat_rate_cents'],
				freeOverCents: $freeOver,
				label: (string) ( $row['label'] ?? '' )
			);
		}

		$this->zoneRepo->saveZones( $taxZones, $shippingZones );

		return [
			'tax_zones'      => $this->zoneRepo->getTaxZones(),
			'shipping_zones' => $this->zoneRepo->getShippingZones(),
		];
	}
}
