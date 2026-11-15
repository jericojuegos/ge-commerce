<?php
/**
 * Pure PHP Immutable Cart Totals Breakdown Value Object.
 *
 * @package GeCommerce\Modules\Cart\Domain\ValueObjects
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Domain\ValueObjects;

use GeCommerce\Shared\ValueObjects\Money;

final class CartTotals {

	public function __construct(
		public readonly Money $subtotal,
		public readonly Money $discount,
		public readonly Money $taxableSubtotal,
		public readonly Money $tax,
		public readonly Money $shipping,
		public readonly Money $total,
		public readonly string $taxLabel,
		public readonly int $taxRateBps,
		public readonly string $shippingLabel,
		public readonly ?string $appliedCoupon = null,
		public readonly ?string $couponWarning = null
	) {}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'subtotal_cents'         => $this->subtotal->cents(),
			'discount_cents'         => $this->discount->cents(),
			'taxable_subtotal_cents' => $this->taxableSubtotal->cents(),
			'tax_cents'              => $this->tax->cents(),
			'shipping_cents'         => $this->shipping->cents(),
			'total_cents'            => $this->total->cents(),
			'currency'               => $this->total->currency(),
			'tax_label'              => $this->taxLabel,
			'tax_rate_bps'           => $this->taxRateBps,
			'shipping_label'         => $this->shippingLabel,
			'applied_coupon'         => $this->appliedCoupon,
			'coupon_warning'         => $this->couponWarning,
		];
	}
}
