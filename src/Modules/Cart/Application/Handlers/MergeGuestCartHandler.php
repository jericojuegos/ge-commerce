<?php
/**
 * Application Handler for Merging a Guest Session Cart into a Logged-In User Cart.
 *
 * @package GeCommerce\Modules\Cart\Application\Handlers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Application\Handlers;

use GeCommerce\Modules\Cart\Domain\Contracts\CartSessionRepositoryInterface;
use GeCommerce\Modules\Cart\Domain\Entities\Cart;
use GeCommerce\Modules\Cart\Domain\ValueObjects\CartTotals;
use GeCommerce\Shared\Contracts\CatalogLookupInterface;
use InvalidArgumentException;

final class MergeGuestCartHandler {

	public function __construct(
		private readonly CartSessionRepositoryInterface $sessions,
		private readonly CatalogLookupInterface $catalog,
		private readonly GetCartHandler $getCartHandler,
		private readonly CalculateCartTotalsHandler $totalsCalculator
	) {}

	/**
	 * Merge guest session into authenticated user cart and delete guest session.
	 *
	 * @return array{cart: Cart, totals: CartTotals}
	 */
	public function execute(
		string $guestSessionKey,
		int $userId,
		string $fallbackUserSessionKey = '',
		?string $nowUtc = null
	): array {
		if ( $userId <= 0 ) {
			throw new InvalidArgumentException( 'Authenticated user ID must be positive to merge a guest cart.' );
		}

		$userSessionKey = '' !== trim( $fallbackUserSessionKey ) ? trim( $fallbackUserSessionKey ) : 'user_' . $userId;
		$userCart       = $this->getCartHandler->loadOrCreate( $userSessionKey, $userId, $nowUtc );

		$guestSessionKey = trim( $guestSessionKey );
		if ( '' === $guestSessionKey || $guestSessionKey === $userCart->sessionKey() ) {
			return [
				'cart'   => $userCart,
				'totals' => $this->totalsCalculator->forCart( $userCart, $nowUtc ),
			];
		}

		$guestCart = $this->sessions->findBySessionKey( $guestSessionKey, $nowUtc );
		if ( null === $guestCart ) {
			return [
				'cart'   => $userCart,
				'totals' => $this->totalsCalculator->forCart( $userCart, $nowUtc ),
			];
		}

		// If the session already belongs to this user or is expired, clean up or return safely.
		if ( $guestCart->isExpired( $nowUtc ) ) {
			$this->sessions->deleteBySessionKey( $guestSessionKey );
			return [
				'cart'   => $userCart,
				'totals' => $this->totalsCalculator->forCart( $userCart, $nowUtc ),
			];
		}

		$mergedCart = $userCart->mergeFrom(
			$guestCart,
			function ( int $skuId ): int {
				$snapshot = $this->catalog->findSkuById( $skuId );
				if ( null === $snapshot || ! $snapshot->isPublished ) {
					return 0;
				}
				return $snapshot->availableQty;
			}
		);

		$saved = $this->sessions->save( $mergedCart );
		$this->sessions->deleteBySessionKey( $guestSessionKey );

		return [
			'cart'   => $saved,
			'totals' => $this->totalsCalculator->forCart( $saved, $nowUtc ),
		];
	}
}
