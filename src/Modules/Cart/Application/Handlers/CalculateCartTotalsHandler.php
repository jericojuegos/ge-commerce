<?php
/**
 * Application Handler for Computing Cart Subtotal, Coupon Discount, Tax & Shipping Totals.
 *
 * @package GeCommerce\Modules\Cart\Application\Handlers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Application\Handlers;

use GeCommerce\Modules\Cart\Application\DTOs\QuotePreviewDTO;
use GeCommerce\Modules\Cart\Domain\Contracts\ZoneConfigRepositoryInterface;
use GeCommerce\Modules\Cart\Domain\Entities\Cart;
use GeCommerce\Modules\Cart\Domain\Services\ShippingCalculator;
use GeCommerce\Modules\Cart\Domain\Services\TaxCalculator;
use GeCommerce\Modules\Cart\Domain\ValueObjects\CartTotals;
use GeCommerce\Shared\Contracts\CouponEvaluatorInterface;
use GeCommerce\Shared\ValueObjects\Money;
use InvalidArgumentException;

final class CalculateCartTotalsHandler {

	public function __construct(
		private readonly CouponEvaluatorInterface $couponEvaluator,
		private readonly ZoneConfigRepositoryInterface $zoneRepo,
		private readonly TaxCalculator $taxCalculator = new TaxCalculator(),
		private readonly ShippingCalculator $shippingCalculator = new ShippingCalculator()
	) {}

	public function forCart( Cart $cart, ?string $nowUtc = null ): CartTotals {
		return $this->compute(
			subtotal: $cart->subtotal(),
			isEmptyCart: $cart->isEmpty(),
			couponCode: $cart->couponCode(),
			country: $cart->shippingCountry(),
			region: $cart->shippingRegion(),
			shippingMethod: $cart->shippingMethod(),
			nowUtc: $nowUtc
		);
	}

	public function forPreview( QuotePreviewDTO $dto ): CartTotals {
		if ( $dto->subtotalCents < 0 ) {
			throw new InvalidArgumentException( 'Subtotal cents cannot be negative.' );
		}

		$subtotal = Money::fromCents( $dto->subtotalCents );
		return $this->compute(
			subtotal: $subtotal,
			isEmptyCart: $subtotal->isZero(),
			couponCode: $dto->couponCode,
			country: $dto->country,
			region: $dto->region,
			shippingMethod: $dto->shippingMethod,
			nowUtc: $dto->nowUtc
		);
	}

	private function compute(
		Money $subtotal,
		bool $isEmptyCart,
		?string $couponCode,
		string $country,
		string $region,
		string $shippingMethod,
		?string $nowUtc
	): CartTotals {
		$discount      = Money::zero( $subtotal->currency() );
		$appliedCoupon = null;
		$couponWarning = null;

		if ( null !== $couponCode && '' !== trim( $couponCode ) && ! $subtotal->isZero() ) {
			$evaluation = $this->couponEvaluator->evaluate( $couponCode, $subtotal, $nowUtc );
			if ( $evaluation->valid ) {
				$discount      = $evaluation->discount;
				$appliedCoupon = $evaluation->code;
			} else {
				$couponWarning = $evaluation->errorMessage;
			}
		}

		$taxableSubtotal = $subtotal->subtractClamped( $discount );

		$taxResult = $this->taxCalculator->calculate(
			$taxableSubtotal,
			$country,
			$region,
			$this->zoneRepo->getTaxZones()
		);

		$shippingResult = $this->shippingCalculator->calculate(
			$taxableSubtotal,
			$isEmptyCart,
			$country,
			$region,
			$shippingMethod,
			$this->zoneRepo->getShippingZones()
		);

		$total = $taxableSubtotal
			->add( $taxResult['tax'] )
			->add( $shippingResult['shipping'] );

		return new CartTotals(
			subtotal: $subtotal,
			discount: $discount,
			taxableSubtotal: $taxableSubtotal,
			tax: $taxResult['tax'],
			shipping: $shippingResult['shipping'],
			total: $total,
			taxLabel: $taxResult['label'],
			taxRateBps: $taxResult['rate_bps'],
			shippingLabel: $shippingResult['label'],
			appliedCoupon: $appliedCoupon,
			couponWarning: $couponWarning
		);
	}
}
