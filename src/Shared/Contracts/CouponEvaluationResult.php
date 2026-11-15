<?php
/**
 * Immutable Cross-Module Coupon Evaluation Result.
 *
 * @package GeCommerce\Shared\Contracts
 */

declare(strict_types=1);

namespace GeCommerce\Shared\Contracts;

use GeCommerce\Shared\ValueObjects\Money;

final class CouponEvaluationResult {

	public function __construct(
		public readonly bool $valid,
		public readonly string $code,
		public readonly Money $discount,
		public readonly ?string $errorMessage = null
	) {}

	public static function valid( string $code, Money $discount ): self {
		return new self( true, $code, $discount, null );
	}

	public static function invalid( string $code, string $errorMessage, string $currency = 'USD' ): self {
		return new self( false, $code, Money::zero( $currency ), $errorMessage );
	}
}
