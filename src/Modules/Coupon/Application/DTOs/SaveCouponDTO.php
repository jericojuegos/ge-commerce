<?php
/**
 * Input DTO for Creating or Updating a Coupon.
 *
 * @package GeCommerce\Modules\Coupon\Application\DTOs
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Coupon\Application\DTOs;

final class SaveCouponDTO {

	public function __construct(
		public readonly ?int $id,
		public readonly string $code,
		public readonly string $discountType,
		public readonly int $amountValue,
		public readonly int $minOrderCents = 0,
		public readonly ?int $usageLimit = null,
		public readonly int $usedCount = 0,
		public readonly ?string $expiresAt = null
	) {}
}
