<?php
/**
 * Use-Case Handler for Creating or Updating a Coupon.
 *
 * @package GeCommerce\Modules\Coupon\Application\Handlers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Coupon\Application\Handlers;

use GeCommerce\Modules\Coupon\Application\DTOs\SaveCouponDTO;
use GeCommerce\Modules\Coupon\Domain\Contracts\CouponRepositoryInterface;
use GeCommerce\Modules\Coupon\Domain\Entities\Coupon;
use GeCommerce\Modules\Coupon\Domain\ValueObjects\DiscountType;
use GeCommerce\Shared\ValueObjects\Money;
use InvalidArgumentException;
use RuntimeException;

final class SaveCouponHandler {

	public function __construct(
		private readonly CouponRepositoryInterface $repository
	) {}

	public function execute( SaveCouponDTO $dto ): Coupon {
		$existing = null;
		if ( null !== $dto->id ) {
			$existing = $this->repository->findById( $dto->id );
			if ( null === $existing ) {
				throw new RuntimeException( sprintf( 'Coupon %d not found.', $dto->id ) );
			}
		}

		$discountType = DiscountType::tryFrom( strtolower( trim( $dto->discountType ) ) );
		if ( null === $discountType ) {
			throw new InvalidArgumentException(
				sprintf( 'Invalid discount type "%s". Must be "fixed_cents" or "percent_bps".', $dto->discountType )
			);
		}

		$usedCount = null !== $existing ? $existing->usedCount() : $dto->usedCount;

		$coupon = new Coupon(
			id: $dto->id,
			code: $dto->code,
			discountType: $discountType,
			amountValue: $dto->amountValue,
			minOrder: Money::fromCents( $dto->minOrderCents ),
			usageLimit: $dto->usageLimit,
			usedCount: $usedCount,
			expiresAt: $dto->expiresAt
		);

		if ( $this->repository->codeExists( $coupon->code(), $dto->id ) ) {
			throw new InvalidArgumentException( sprintf( 'Coupon code "%s" already exists.', $coupon->code() ) );
		}

		return $this->repository->save( $coupon );
	}
}
