<?php
/**
 * Pure PHP Shipping Zone Value Object.
 *
 * @package GeCommerce\Modules\Cart\Domain\ValueObjects
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Domain\ValueObjects;

use InvalidArgumentException;

final class ShippingZone {

	private readonly string $country;

	private readonly string $region;

	private readonly string $method;

	private readonly string $label;

	public function __construct(
		string $country,
		string $region,
		string $method,
		private readonly int $flatRateCents,
		private readonly ?int $freeOverCents = null,
		string $label = ''
	) {
		$cleanCountry = strtoupper( trim( $country ) );
		$cleanRegion  = strtoupper( trim( $region ) );
		$cleanMethod  = strtolower( trim( $method ) );

		if ( '' === $cleanCountry ) {
			throw new InvalidArgumentException( 'Shipping zone country cannot be empty (use "*" for wildcard).' );
		}
		if ( '' === $cleanRegion ) {
			$cleanRegion = '*';
		}
		if ( '' === $cleanMethod ) {
			throw new InvalidArgumentException( 'Shipping method cannot be empty.' );
		}
		if ( $flatRateCents < 0 ) {
			throw new InvalidArgumentException( 'Shipping flat rate cannot be negative.' );
		}
		if ( null !== $freeOverCents && $freeOverCents < 0 ) {
			throw new InvalidArgumentException( 'Free shipping threshold cannot be negative.' );
		}

		$this->country = $cleanCountry;
		$this->region  = $cleanRegion;
		$this->method  = $cleanMethod;
		$this->label   = '' !== trim( $label ) ? trim( $label ) : sprintf( '%s (%s)', ucfirst( $cleanMethod ), $cleanCountry );
	}

	public function country(): string {
		return $this->country;
	}

	public function region(): string {
		return $this->region;
	}

	public function method(): string {
		return $this->method;
	}

	public function flatRateCents(): int {
		return $this->flatRateCents;
	}

	public function freeOverCents(): ?int {
		return $this->freeOverCents;
	}

	public function label(): string {
		return $this->label;
	}

	/**
	 * Return match specificity (6 = exact country+region+method down to 1 = *+*+*).
	 */
	public function specificityScore( string $country, string $region, string $method ): ?int {
		$targetCountry = strtoupper( trim( $country ) );
		$targetRegion  = strtoupper( trim( $region ) );
		$targetMethod  = strtolower( trim( $method ) );

		if ( $this->method !== $targetMethod && '*' !== $this->method ) {
			return null;
		}

		$methodBonus = $this->method === $targetMethod ? 10 : 0;

		if ( $this->country === $targetCountry && $this->region === $targetRegion && '*' !== $this->region && '*' !== $this->country ) {
			return $methodBonus + 3;
		}
		if ( $this->country === $targetCountry && '*' === $this->region && '*' !== $this->country ) {
			return $methodBonus + 2;
		}
		if ( '*' === $this->country && '*' === $this->region ) {
			return $methodBonus + 1;
		}

		return null;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'country'         => $this->country,
			'region'          => $this->region,
			'method'          => $this->method,
			'flat_rate_cents' => $this->flatRateCents,
			'free_over_cents' => $this->freeOverCents,
			'label'           => $this->label,
		];
	}
}
