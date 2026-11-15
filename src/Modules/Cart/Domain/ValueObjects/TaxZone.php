<?php
/**
 * Pure PHP Tax Zone Value Object.
 *
 * @package GeCommerce\Modules\Cart\Domain\ValueObjects
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Domain\ValueObjects;

use InvalidArgumentException;

final class TaxZone {

	private readonly string $country;

	private readonly string $region;

	private readonly string $label;

	public function __construct(
		string $country,
		string $region,
		private readonly int $rateBps,
		string $label = ''
	) {
		$cleanCountry = strtoupper( trim( $country ) );
		$cleanRegion  = strtoupper( trim( $region ) );

		if ( '' === $cleanCountry ) {
			throw new InvalidArgumentException( 'Tax zone country cannot be empty (use "*" for wildcard).' );
		}
		if ( '' === $cleanRegion ) {
			$cleanRegion = '*';
		}
		if ( $rateBps < 0 || $rateBps > 10000 ) {
			throw new InvalidArgumentException( 'Tax rate in basis points must be between 0 and 10000.' );
		}

		$this->country = $cleanCountry;
		$this->region  = $cleanRegion;
		$this->label   = '' !== trim( $label ) ? trim( $label ) : sprintf( '%s-%s Tax', $cleanCountry, $cleanRegion );
	}

	public function country(): string {
		return $this->country;
	}

	public function region(): string {
		return $this->region;
	}

	public function rateBps(): int {
		return $this->rateBps;
	}

	public function label(): string {
		return $this->label;
	}

	/**
	 * Return match specificity (3 = exact country+region, 2 = country+*, 1 = *+*, null = no match).
	 */
	public function specificityScore( string $country, string $region ): ?int {
		$targetCountry = strtoupper( trim( $country ) );
		$targetRegion  = strtoupper( trim( $region ) );

		if ( $this->country === $targetCountry && $this->region === $targetRegion && '*' !== $this->region && '*' !== $this->country ) {
			return 3;
		}
		if ( $this->country === $targetCountry && '*' === $this->region && '*' !== $this->country ) {
			return 2;
		}
		if ( '*' === $this->country && '*' === $this->region ) {
			return 1;
		}

		return null;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'country'  => $this->country,
			'region'   => $this->region,
			'rate_bps' => $this->rateBps,
			'label'    => $this->label,
		];
	}
}
