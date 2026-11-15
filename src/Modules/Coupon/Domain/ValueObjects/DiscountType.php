<?php
/**
 * Coupon Discount Type Backed Enum.
 *
 * @package GeCommerce\Modules\Coupon\Domain\ValueObjects
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Coupon\Domain\ValueObjects;

enum DiscountType: string {
	case FIXED_CENTS = 'fixed_cents';
	case PERCENT_BPS = 'percent_bps';
}
