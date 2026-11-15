<?php
/**
 * Pure PHP Regional Tax Calculator Domain Service.
 *
 * @package GeCommerce\Modules\Cart\Domain\Services
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Domain\Services;

use GeCommerce\Modules\Cart\Domain\ValueObjects\TaxZone;
use GeCommerce\Shared\ValueObjects\Money;

final class TaxCalculator {

	/**
	 * Resolve most specific TaxZone and compute tax in integer minor units.
	 *
	 * @param list<TaxZone> $zones
	 * @return array{tax: Money, rate_bps: int, label: string}
	 */
	public function calculate( Money $taxableSubtotal, string $country, string $region, array $zones ): array {
		$matchedZone = $this->matchZone( $country, $region, $zones );
		if ( null === $matchedZone || $taxableSubtotal->isZero() ) {
			return [
				'tax'      => Money::zero( $taxableSubtotal->currency() ),
				'rate_bps' => null !== $matchedZone ? $matchedZone->rateBps() : 0,
				'label'    => null !== $matchedZone ? $matchedZone->label() : 'No Tax',
			];
		}

		return [
			'tax'      => $taxableSubtotal->percentageBasisPoints( $matchedZone->rateBps() ),
			'rate_bps' => $matchedZone->rateBps(),
			'label'    => $matchedZone->label(),
		];
	}

	/**
	 * @param list<TaxZone> $zones
	 */
	public function matchZone( string $country, string $region, array $zones ): ?TaxZone {
		$bestZone  = null;
		$bestScore = -1;

		foreach ( $zones as $zone ) {
			$score = $zone->specificityScore( $country, $region );
			if ( null !== $score && $score > $bestScore ) {
				$bestScore = $score;
				$bestZone  = $zone;
			}
		}

		return $bestZone;
	}
}
