<?php
/**
 * Pure PHP Coupon Aggregate Entity.
 *
 * @package GeCommerce\Modules\Coupon\Domain\Entities
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Coupon\Domain\Entities;

use DateTimeImmutable;
use DateTimeZone;
use GeCommerce\Modules\Coupon\Domain\ValueObjects\DiscountType;
use GeCommerce\Shared\ValueObjects\Money;
use InvalidArgumentException;

final class Coupon {

	private readonly string $code;

	private readonly ?string $expiresAt;

	public function __construct(
		private readonly ?int $id,
		string $code,
		private readonly DiscountType $discountType,
		private readonly int $amountValue,
		private readonly Money $minOrder,
		private readonly ?int $usageLimit = null,
		private readonly int $usedCount = 0,
		?string $expiresAt = null
	) {
		$cleanCode = strtoupper( trim( $code ) );
		if ( '' === $cleanCode ) {
			throw new InvalidArgumentException( 'Coupon code cannot be empty.' );
		}
		if ( strlen( $cleanCode ) > 64 ) {
			throw new InvalidArgumentException( 'Coupon code cannot exceed 64 characters.' );
		}
		if ( ! preg_match( '/^[A-Z0-9_-]+$/', $cleanCode ) ) {
			throw new InvalidArgumentException( 'Coupon code may only contain alphanumeric characters, underscores, and hyphens.' );
		}
		if ( $amountValue < 0 ) {
			throw new InvalidArgumentException( 'Coupon discount value cannot be negative.' );
		}
		if ( DiscountType::PERCENT_BPS === $discountType && $amountValue > 10000 ) {
			throw new InvalidArgumentException( 'Percentage discount in basis points cannot exceed 10000 (100%).' );
		}
		if ( null !== $usageLimit && $usageLimit <= 0 ) {
			throw new InvalidArgumentException( 'Coupon usage limit must be greater than zero or null.' );
		}
		if ( $usedCount < 0 ) {
			throw new InvalidArgumentException( 'Coupon used count cannot be negative.' );
		}

		$normalizedExpiry = null;
		if ( null !== $expiresAt && '' !== trim( $expiresAt ) ) {
			try {
				$dt               = new DateTimeImmutable( trim( $expiresAt ), new DateTimeZone( 'UTC' ) );
				$normalizedExpiry = $dt->format( 'Y-m-d H:i:s' );
			} catch ( \Exception $e ) {
				throw new InvalidArgumentException( 'Coupon expiration timestamp is invalid.', 0, $e );
			}
		}

		$this->code      = $cleanCode;
		$this->expiresAt = $normalizedExpiry;
	}

	public function id(): ?int {
		return $this->id;
	}

	public function code(): string {
		return $this->code;
	}

	public function discountType(): DiscountType {
		return $this->discountType;
	}

	public function amountValue(): int {
		return $this->amountValue;
	}

	public function minOrder(): Money {
		return $this->minOrder;
	}

	public function usageLimit(): ?int {
		return $this->usageLimit;
	}

	public function usedCount(): int {
		return $this->usedCount;
	}

	public function expiresAt(): ?string {
		return $this->expiresAt;
	}

	public function isExpired( ?string $nowUtc = null ): bool {
		if ( null === $this->expiresAt ) {
			return false;
		}

		$tz     = new DateTimeZone( 'UTC' );
		$nowDt  = null !== $nowUtc && '' !== trim( $nowUtc )
			? new DateTimeImmutable( $nowUtc, $tz )
			: new DateTimeImmutable( 'now', $tz );
		$expDt  = new DateTimeImmutable( $this->expiresAt, $tz );

		return $expDt <= $nowDt;
	}

	public function hasRemainingUses(): bool {
		if ( null === $this->usageLimit ) {
			return true;
		}

		return $this->usedCount < $this->usageLimit;
	}

	/**
	 * Validate eligibility against subtotal, usage limit, and expiry.
	 *
	 * @throws InvalidArgumentException If coupon cannot be applied.
	 */
	public function assertApplicable( Money $subtotal, ?string $nowUtc = null ): void {
		if ( $this->isExpired( $nowUtc ) ) {
			throw new InvalidArgumentException( sprintf( 'Coupon "%s" has expired.', $this->code ) );
		}

		if ( ! $this->hasRemainingUses() ) {
			throw new InvalidArgumentException( sprintf( 'Coupon "%s" has reached its usage limit.', $this->code ) );
		}

		if ( ! $subtotal->greaterThanOrEqual( $this->minOrder ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'Coupon "%s" requires a minimum order of %d cents.',
					$this->code,
					$this->minOrder->cents()
				)
			);
		}
	}

	/**
	 * Calculate discount in integer cents, strictly clamped to [0, subtotal_cents].
	 */
	public function calculateDiscount( Money $subtotal, ?string $nowUtc = null ): Money {
		$this->assertApplicable( $subtotal, $nowUtc );

		if ( $subtotal->isZero() ) {
			return Money::zero( $subtotal->currency() );
		}

		$rawDiscount = match ( $this->discountType ) {
			DiscountType::FIXED_CENTS => Money::fromCents( $this->amountValue, $subtotal->currency() ),
			DiscountType::PERCENT_BPS => $subtotal->percentageBasisPoints( $this->amountValue ),
		};

		// Clamp discount so it never exceeds subtotal.
		$clampedCents = min( $rawDiscount->cents(), $subtotal->cents() );
		return Money::fromCents( $clampedCents, $subtotal->currency() );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'id'              => $this->id,
			'code'            => $this->code,
			'discount_type'   => $this->discountType->value,
			'amount_value'    => $this->amountValue,
			'min_order_cents' => $this->minOrder->cents(),
			'usage_limit'     => $this->usageLimit,
			'used_count'      => $this->usedCount,
			'expires_at'      => $this->expiresAt,
		];
	}
}
