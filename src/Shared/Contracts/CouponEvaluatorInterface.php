<?php
/**
 * Cross-Module Coupon Evaluator Port Interface.
 *
 * @package GeCommerce\Shared\Contracts
 */

declare(strict_types=1);

namespace GeCommerce\Shared\Contracts;

use GeCommerce\Shared\ValueObjects\Money;

interface CouponEvaluatorInterface {

	public function evaluate( string $code, Money $subtotal, ?string $nowUtc = null ): CouponEvaluationResult;

	public function incrementUsage( string $code ): void;
}
