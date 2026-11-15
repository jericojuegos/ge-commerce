<?php
/**
 * Pure PHP Regional Shipping Calculator Domain Service.
 *
 * @package GeCommerce\Modules\Cart\Domain\Services
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Domain\Services;

use GeCommerce\Modules\Cart\Domain\ValueObjects\ShippingZone;
use GeCommerce\Shared\ValueObjects\Money;

final class ShippingCalculator {

	/**
	 * Resolve most specific ShippingZone and compute shipping cost in integer minor units.
	 *
	 * @param list<ShippingZone> $zones
	 * @return array{shipping: Money, label: string}
	 */
	public function calculate(
		Money $taxableSubtotal,
		bool $isEmptyCart,
		string $country,
		string $region,
		string $method,
		array $zones
	): array {
		$currency = $taxableSubtotal->currency();
		if ( $isEmptyCart ) {
			return [
				'shipping' => Money::zero( $currency ),
				'label'    => 'Empty Cart',
			];
		}

		$matched = $this->matchZone( $country, $region, $method, $zones );
		if ( null === $matched ) {
			return [
				'shipping' => Money::zero( $currency ),
				'label'    => 'Free / Standard',
			];
		}

		$freeOver = $matched->freeOverCents();
		if ( null !== $freeOver && $taxableSubtotal->cents() >= $freeOver ) {
			return [
				'shipping' => Money::zero( $currency ),
				'label'    => $matched->label() . ' (Free Threshold Met)',
			];
		}

		return [
			'shipping' => Money::fromCents( $matched->flatRateCents(), $currency ),
			'label'    => $matched->label(),
		];
	}

	/**
	 * @param list<ShippingZone> $zones
	 */
	public function matchZone( string $country, string $region, string $method, array $zones ): ?ShippingZone {
		$bestZone  = null;
		$bestScore = -1;

		foreach ( $zones as $zone ) {
			$score = $zone->specificityScore( $country, $region, $method );
			if ( null !== $score && $score > $bestScore ) {
				$bestScore = $score;
				$bestZone  = $zone;
			}
		}

		return $bestZone;
	}
}
