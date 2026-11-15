<?php
/**
 * Pure PHP Integer Minor-Unit Money Value Object (Zero-Float Arithmetic).
 *
 * @package GeCommerce\Shared\ValueObjects
 */

declare(strict_types=1);

namespace GeCommerce\Shared\ValueObjects;

use InvalidArgumentException;

final class Money {

	private readonly int $cents;

	private readonly string $currency;

	/**
	 * Construct an immutable Money value object in integer minor units (cents).
	 *
	 * @param int    $cents    Amount in minor units (must be >= 0).
	 * @param string $currency Three-letter ISO 4217 currency code.
	 * @throws InvalidArgumentException If cents is negative or currency code is invalid.
	 */
	public function __construct( int $cents, string $currency = 'USD' ) {
		if ( $cents < 0 ) {
			throw new InvalidArgumentException( 'Monetary amount in cents cannot be negative.' );
		}

		$normalizedCurrency = strtoupper( trim( $currency ) );
		if ( 3 !== strlen( $normalizedCurrency ) ) {
			throw new InvalidArgumentException( 'Currency must be a 3-letter ISO code.' );
		}

		$this->cents    = $cents;
		$this->currency = $normalizedCurrency;
	}

	/**
	 * Named constructor from integer cents.
	 */
	public static function fromCents( int $cents, string $currency = 'USD' ): self {
		return new self( $cents, $currency );
	}

	/**
	 * Named constructor for zero money.
	 */
	public static function zero( string $currency = 'USD' ): self {
		return new self( 0, $currency );
	}

	/**
	 * Return integer minor units (cents).
	 */
	public function cents(): int {
		return $this->cents;
	}

	/**
	 * Return 3-letter ISO currency code.
	 */
	public function currency(): string {
		return $this->currency;
	}

	/**
	 * Whether the monetary value is zero.
	 */
	public function isZero(): bool {
		return 0 === $this->cents;
	}

	/**
	 * Add another Money value of the same currency.
	 */
	public function add( self $other ): self {
		$this->assertSameCurrency( $other );
		return new self( $this->cents + $other->cents, $this->currency );
	}

	/**
	 * Subtract another Money value of the same currency (throws if result < 0).
	 *
	 * @throws InvalidArgumentException When subtraction would result in negative money.
	 */
	public function subtract( self $other ): self {
		$this->assertSameCurrency( $other );
		if ( $other->cents > $this->cents ) {
			throw new InvalidArgumentException( 'Money subtraction cannot result in a negative amount.' );
		}

		return new self( $this->cents - $other->cents, $this->currency );
	}

	/**
	 * Subtract another Money value clamped at zero (useful for applying discounts).
	 */
	public function subtractClamped( self $other ): self {
		$this->assertSameCurrency( $other );
		return new self( max( 0, $this->cents - $other->cents ), $this->currency );
	}

	/**
	 * Multiply by a non-negative integer quantity.
	 *
	 * @throws InvalidArgumentException When multiplier is negative.
	 */
	public function multiply( int $multiplier ): self {
		if ( $multiplier < 0 ) {
			throw new InvalidArgumentException( 'Money multiplier cannot be negative.' );
		}

		return new self( $this->cents * $multiplier, $this->currency );
	}

	/**
	 * Calculate a percentage in basis points (e.g. 1000 = 10.00%, 825 = 8.25%)
	 * using pure integer half-up rounding (`intdiv`), zero floats.
	 *
	 * @param int $basisPoints Basis points (>= 0, where 10000 = 100%).
	 * @throws InvalidArgumentException When basis points is negative.
	 */
	public function percentageBasisPoints( int $basisPoints ): self {
		if ( $basisPoints < 0 ) {
			throw new InvalidArgumentException( 'Basis points cannot be negative.' );
		}

		$calculatedCents = intdiv( ( $this->cents * $basisPoints ) + 5000, 10000 );
		return new self( $calculatedCents, $this->currency );
	}

	/**
	 * Value equality check.
	 */
	public function equals( self $other ): bool {
		return $this->cents === $other->cents && $this->currency === $other->currency;
	}

	/**
	 * Check whether this amount is greater than or equal to another Money object.
	 */
	public function greaterThanOrEqual( self $other ): bool {
		$this->assertSameCurrency( $other );
		return $this->cents >= $other->cents;
	}

	/**
	 * Export to array representation.
	 *
	 * @return array{amount_cents: int, currency: string}
	 */
	public function toArray(): array {
		return [
			'amount_cents' => $this->cents,
			'currency'     => $this->currency,
		];
	}

	private function assertSameCurrency( self $other ): void {
		if ( $this->currency !== $other->currency ) {
			throw new InvalidArgumentException(
				sprintf( 'Currency mismatch: cannot combine %s and %s.', $this->currency, $other->currency )
			);
		}
	}
}
