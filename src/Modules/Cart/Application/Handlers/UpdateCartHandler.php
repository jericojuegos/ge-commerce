<?php
/**
 * Application Handler for Modifying Cart Items, Coupons & Destination.
 *
 * @package GeCommerce\Modules\Cart\Application\Handlers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Application\Handlers;

use GeCommerce\Modules\Cart\Application\DTOs\CartItemInputDTO;
use GeCommerce\Modules\Cart\Domain\Contracts\CartSessionRepositoryInterface;
use GeCommerce\Modules\Cart\Domain\Entities\Cart;
use GeCommerce\Modules\Cart\Domain\Entities\CartItem;
use GeCommerce\Modules\Cart\Domain\ValueObjects\CartTotals;
use GeCommerce\Shared\Contracts\CatalogLookupInterface;
use GeCommerce\Shared\Contracts\CouponEvaluatorInterface;
use InvalidArgumentException;
use RuntimeException;

final class UpdateCartHandler {

	public function __construct(
		private readonly CartSessionRepositoryInterface $sessions,
		private readonly CatalogLookupInterface $catalog,
		private readonly CouponEvaluatorInterface $couponEvaluator,
		private readonly GetCartHandler $getCartHandler,
		private readonly CalculateCartTotalsHandler $totalsCalculator
	) {}

	/**
	 * Add quantity to existing item or create new line item.
	 *
	 * @return array{cart: Cart, totals: CartTotals}
	 */
	public function addItem(
		string $sessionKey,
		int $userId,
		CartItemInputDTO $input,
		?string $nowUtc = null
	): array {
		if ( $input->qty <= 0 ) {
			throw new InvalidArgumentException( 'Quantity to add must be greater than zero.' );
		}

		$cart        = $this->getCartHandler->loadOrCreate( $sessionKey, $userId, $nowUtc );
		$existing    = $cart->findItem( $input->skuId );
		$targetQty   = $input->qty + ( null !== $existing ? $existing->qty() : 0 );

		return $this->persistItemQuantity( $cart, $input->skuId, $targetQty, $nowUtc );
	}

	/**
	 * Set exact quantity for a SKU (or remove if qty === 0).
	 *
	 * @return array{cart: Cart, totals: CartTotals}
	 */
	public function setItemQty(
		string $sessionKey,
		int $userId,
		CartItemInputDTO $input,
		?string $nowUtc = null
	): array {
		if ( $input->qty < 0 ) {
			throw new InvalidArgumentException( 'Cart quantity cannot be negative.' );
		}

		$cart = $this->getCartHandler->loadOrCreate( $sessionKey, $userId, $nowUtc );
		if ( 0 === $input->qty ) {
			$updated = $this->sessions->save( $cart->withoutItem( $input->skuId ) );
			return [
				'cart'   => $updated,
				'totals' => $this->totalsCalculator->forCart( $updated, $nowUtc ),
			];
		}

		return $this->persistItemQuantity( $cart, $input->skuId, $input->qty, $nowUtc );
	}

	/**
	 * Remove a SKU from the cart.
	 *
	 * @return array{cart: Cart, totals: CartTotals}
	 */
	public function removeItem( string $sessionKey, int $userId, int $skuId, ?string $nowUtc = null ): array {
		$cart    = $this->getCartHandler->loadOrCreate( $sessionKey, $userId, $nowUtc );
		$updated = $this->sessions->save( $cart->withoutItem( $skuId ) );

		return [
			'cart'   => $updated,
			'totals' => $this->totalsCalculator->forCart( $updated, $nowUtc ),
		];
	}

	/**
	 * Validate and attach (or clear) a coupon code on the cart.
	 *
	 * @return array{cart: Cart, totals: CartTotals}
	 */
	public function applyCoupon( string $sessionKey, int $userId, ?string $code, ?string $nowUtc = null ): array {
		$cart = $this->getCartHandler->loadOrCreate( $sessionKey, $userId, $nowUtc );

		if ( null === $code || '' === trim( $code ) ) {
			$updated = $this->sessions->save( $cart->withCouponCode( null ) );
			return [
				'cart'   => $updated,
				'totals' => $this->totalsCalculator->forCart( $updated, $nowUtc ),
			];
		}

		$evaluation = $this->couponEvaluator->evaluate( $code, $cart->subtotal(), $nowUtc );
		if ( ! $evaluation->valid ) {
			throw new InvalidArgumentException( $evaluation->errorMessage ?? 'Coupon is not valid.' );
		}

		$updated = $this->sessions->save( $cart->withCouponCode( $evaluation->code ) );

		return [
			'cart'   => $updated,
			'totals' => $this->totalsCalculator->forCart( $updated, $nowUtc ),
		];
	}

	/**
	 * Update shipping destination country/region/method for tax & shipping calculation.
	 *
	 * @return array{cart: Cart, totals: CartTotals}
	 */
	public function setDestination(
		string $sessionKey,
		int $userId,
		string $country,
		string $region,
		string $shippingMethod,
		?string $nowUtc = null
	): array {
		$cart    = $this->getCartHandler->loadOrCreate( $sessionKey, $userId, $nowUtc );
		$updated = $this->sessions->save( $cart->withDestination( $country, $region, $shippingMethod ) );

		return [
			'cart'   => $updated,
			'totals' => $this->totalsCalculator->forCart( $updated, $nowUtc ),
		];
	}

	/**
	 * @return array{cart: Cart, totals: CartTotals}
	 */
	private function persistItemQuantity( Cart $cart, int $skuId, int $targetQty, ?string $nowUtc ): array {
		$snapshot = $this->catalog->findSkuById( $skuId );
		if ( null === $snapshot || ! $snapshot->isPublished ) {
			throw new RuntimeException( sprintf( 'SKU %d is unavailable or does not exist.', $skuId ) );
		}

		if ( $targetQty > $snapshot->availableQty ) {
			throw new InvalidArgumentException(
				sprintf(
					'Requested quantity (%d) for SKU "%s" exceeds available stock (%d).',
					$targetQty,
					$snapshot->skuCode,
					$snapshot->availableQty
				)
			);
		}

		$item    = new CartItem(
			skuId: $snapshot->skuId,
			skuCode: $snapshot->skuCode,
			title: $snapshot->productTitle,
			unitPrice: $snapshot->price,
			qty: $targetQty
		);
		$updated = $this->sessions->save( $cart->withSetItem( $item ) );

		return [
			'cart'   => $updated,
			'totals' => $this->totalsCalculator->forCart( $updated, $nowUtc ),
		];
	}
}
