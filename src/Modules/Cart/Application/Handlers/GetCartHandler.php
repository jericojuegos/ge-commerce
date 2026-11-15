<?php
/**
 * Application Handler for Retrieving & Refreshing a Session Cart.
 *
 * @package GeCommerce\Modules\Cart\Application\Handlers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Application\Handlers;

use DateTimeImmutable;
use DateTimeZone;
use GeCommerce\Modules\Cart\Domain\Contracts\CartSessionRepositoryInterface;
use GeCommerce\Modules\Cart\Domain\Entities\Cart;
use GeCommerce\Modules\Cart\Domain\Entities\CartItem;
use GeCommerce\Modules\Cart\Domain\ValueObjects\CartTotals;
use GeCommerce\Shared\Contracts\CatalogLookupInterface;

final class GetCartHandler {

	public function __construct(
		private readonly CartSessionRepositoryInterface $sessions,
		private readonly CatalogLookupInterface $catalog,
		private readonly CalculateCartTotalsHandler $totalsCalculator
	) {}

	/**
	 * Load active cart for userId (>0) or guest sessionKey, refreshing prices against live Catalog SKUs.
	 *
	 * @return array{cart: Cart, totals: CartTotals}
	 */
	public function execute( string $sessionKey, int $userId = 0, ?string $nowUtc = null ): array {
		$cart = $this->loadOrCreate( $sessionKey, $userId, $nowUtc );

		$refreshedItems = [];
		$changed        = false;
		foreach ( $cart->items() as $item ) {
			$snapshot = $this->catalog->findSkuById( $item->skuId() );
			if ( null === $snapshot || ! $snapshot->isPublished || $snapshot->availableQty <= 0 ) {
				$changed = true;
				continue;
			}

			$qty = min( $item->qty(), $snapshot->availableQty );
			if (
				$qty !== $item->qty()
				|| $snapshot->price->cents() !== $item->unitPrice()->cents()
				|| $snapshot->skuCode !== $item->skuCode()
			) {
				$changed = true;
			}

			$refreshedItems[] = new CartItem(
				skuId: $snapshot->skuId,
				skuCode: $snapshot->skuCode,
				title: $snapshot->productTitle,
				unitPrice: $snapshot->price,
				qty: $qty
			);
		}

		if ( $changed ) {
			$cart = new Cart(
				sessionKey: $cart->sessionKey(),
				userId: $cart->userId(),
				items: $refreshedItems,
				couponCode: $cart->couponCode(),
				shippingCountry: $cart->shippingCountry(),
				shippingRegion: $cart->shippingRegion(),
				shippingMethod: $cart->shippingMethod(),
				expiresAt: $cart->expiresAt()
			);
			$cart = $this->sessions->save( $cart );
		}

		return [
			'cart'   => $cart,
			'totals' => $this->totalsCalculator->forCart( $cart, $nowUtc ),
		];
	}

	public function loadOrCreate( string $sessionKey, int $userId = 0, ?string $nowUtc = null ): Cart {
		$cart = null;
		if ( $userId > 0 ) {
			$cart = $this->sessions->findByUserId( $userId, $nowUtc );
		}
		if ( null === $cart ) {
			$cart = $this->sessions->findBySessionKey( $sessionKey, $nowUtc );
		}

		if ( null !== $cart && ! $cart->isExpired( $nowUtc ) ) {
			return $cart;
		}

		if ( null !== $cart && $cart->isExpired( $nowUtc ) ) {
			$this->sessions->deleteBySessionKey( $cart->sessionKey() );
		}

		$tz    = new DateTimeZone( 'UTC' );
		$nowDt = null !== $nowUtc && '' !== trim( $nowUtc )
			? new DateTimeImmutable( $nowUtc, $tz )
			: new DateTimeImmutable( 'now', $tz );

		return new Cart(
			sessionKey: $sessionKey,
			userId: $userId,
			items: [],
			couponCode: null,
			expiresAt: $nowDt->modify( '+7 days' )->format( 'Y-m-d H:i:s' )
		);
	}
}
