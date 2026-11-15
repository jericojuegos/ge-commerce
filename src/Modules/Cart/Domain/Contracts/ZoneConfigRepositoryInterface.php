<?php
/**
 * Tax & Shipping Zone Configuration Repository Port Interface.
 *
 * @package GeCommerce\Modules\Cart\Domain\Contracts
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Domain\Contracts;

use GeCommerce\Modules\Cart\Domain\ValueObjects\ShippingZone;
use GeCommerce\Modules\Cart\Domain\ValueObjects\TaxZone;

interface ZoneConfigRepositoryInterface {

	/**
	 * @return list<TaxZone>
	 */
	public function getTaxZones(): array;

	/**
	 * @return list<ShippingZone>
	 */
	public function getShippingZones(): array;

	/**
	 * @param list<TaxZone>      $taxZones
	 * @param list<ShippingZone> $shippingZones
	 */
	public function saveZones( array $taxZones, array $shippingZones ): void;
}
