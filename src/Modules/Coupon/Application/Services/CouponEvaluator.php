<?php
/**
 * Pure Application Service Implementing Cross-Module CouponEvaluatorInterface.
 *
 * @package GeCommerce\Modules\Coupon\Application\Services
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Coupon\Application\Services;

use GeCommerce\Modules\Coupon\Domain\Contracts\CouponRepositoryInterface;
use GeCommerce\Shared\Contracts\CouponEvaluationResult;
use GeCommerce\Shared\Contracts\CouponEvaluatorInterface;
use GeCommerce\Shared\ValueObjects\Money;
use InvalidArgumentException;

final class CouponEvaluator implements CouponEvaluatorInterface {

	public function __construct(
		private readonly CouponRepositoryInterface $repository
	) {}

	public function evaluate( string $code, Money $subtotal, ?string $nowUtc = null ): CouponEvaluationResult {
		$normalized = strtoupper( trim( $code ) );
		if ( '' === $normalized ) {
			return CouponEvaluationResult::invalid( '', 'Coupon code cannot be empty.', $subtotal->currency() );
		}

		$coupon = $this->repository->findByCode( $normalized );
		if ( null === $coupon ) {
			return CouponEvaluationResult::invalid(
				$normalized,
				sprintf( 'Coupon "%s" does not exist.', $normalized ),
				$subtotal->currency()
			);
		}

		try {
			$discount = $coupon->calculateDiscount( $subtotal, $nowUtc );
			return CouponEvaluationResult::valid( $coupon->code(), $discount );
		} catch ( InvalidArgumentException $e ) {
			return CouponEvaluationResult::invalid( $coupon->code(), $e->getMessage(), $subtotal->currency() );
		}
	}

	public function incrementUsage( string $code ): void {
		$normalized = strtoupper( trim( $code ) );
		if ( '' !== $normalized ) {
			$this->repository->incrementUsage( $normalized );
		}
	}
}
