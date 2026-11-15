<?php
/**
 * Pure PHP Cart Aggregate Root.
 *
 * @package GeCommerce\Modules\Cart\Domain\Entities
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Domain\Entities;

use DateTimeImmutable;
use DateTimeZone;
use GeCommerce\Shared\ValueObjects\Money;
use InvalidArgumentException;

final class Cart {

	private readonly string $sessionKey;

	/**
	 * @var array<int, CartItem> Indexed by skuId.
	 */
	private readonly array $items;

	private readonly ?string $couponCode;

	private readonly string $shippingCountry;

	private readonly string $shippingRegion;

	private readonly string $shippingMethod;

	private readonly string $expiresAt;

	/**
	 * @param list<CartItem> $items
	 */
	public function __construct(
		string $sessionKey,
		private readonly int $userId,
		array $items = [],
		?string $couponCode = null,
		string $shippingCountry = 'US',
		string $shippingRegion = 'CA',
		string $shippingMethod = 'standard',
		string $expiresAt = ''
	) {
		$cleanKey = trim( $sessionKey );
		if ( '' === $cleanKey || strlen( $cleanKey ) > 128 ) {
			throw new InvalidArgumentException( 'Cart session key must be between 1 and 128 characters.' );
		}
		if ( $userId < 0 ) {
			throw new InvalidArgumentException( 'Cart user ID cannot be negative.' );
		}

		$indexedItems = [];
		foreach ( $items as $item ) {
			if ( ! $item instanceof CartItem ) {
				throw new InvalidArgumentException( 'All cart items must be instances of CartItem.' );
			}
			$skuId = $item->skuId();
			if ( isset( $indexedItems[ $skuId ] ) ) {
				$mergedQty              = $indexedItems[ $skuId ]->qty() + $item->qty();
				$indexedItems[ $skuId ] = $item->withQty( $mergedQty );
			} else {
				$indexedItems[ $skuId ] = $item;
			}
		}

		$cleanCoupon = null;
		if ( null !== $couponCode && '' !== trim( $couponCode ) ) {
			$cleanCoupon = strtoupper( trim( $couponCode ) );
		}

		$tz = new DateTimeZone( 'UTC' );
		if ( '' === trim( $expiresAt ) ) {
			$expDt = ( new DateTimeImmutable( 'now', $tz ) )->modify( '+7 days' );
		} else {
			try {
				$expDt = new DateTimeImmutable( trim( $expiresAt ), $tz );
			} catch ( \Exception $e ) {
				throw new InvalidArgumentException( 'Cart expiration timestamp is invalid.', 0, $e );
			}
		}

		$this->sessionKey      = $cleanKey;
		$this->items           = $indexedItems;
		$this->couponCode      = $cleanCoupon;
		$this->shippingCountry = strtoupper( trim( $shippingCountry ) ) ?: 'US';
		$this->shippingRegion  = strtoupper( trim( $shippingRegion ) ) ?: '*';
		$this->shippingMethod  = strtolower( trim( $shippingMethod ) ) ?: 'standard';
		$this->expiresAt       = $expDt->format( 'Y-m-d H:i:s' );
	}

	public function sessionKey(): string {
		return $this->sessionKey;
	}

	public function userId(): int {
		return $this->userId;
	}

	/**
	 * @return list<CartItem>
	 */
	public function items(): array {
		return array_values( $this->items );
	}

	public function isEmpty(): bool {
		return [] === $this->items;
	}

	public function couponCode(): ?string {
		return $this->couponCode;
	}

	public function shippingCountry(): string {
		return $this->shippingCountry;
	}

	public function shippingRegion(): string {
		return $this->shippingRegion;
	}

	public function shippingMethod(): string {
		return $this->shippingMethod;
	}

	public function expiresAt(): string {
		return $this->expiresAt;
	}

	public function isExpired( ?string $nowUtc = null ): bool {
		$tz    = new DateTimeZone( 'UTC' );
		$nowDt = null !== $nowUtc && '' !== trim( $nowUtc )
			? new DateTimeImmutable( $nowUtc, $tz )
			: new DateTimeImmutable( 'now', $tz );
		$expDt = new DateTimeImmutable( $this->expiresAt, $tz );

		return $expDt <= $nowDt;
	}

	public function findItem( int $skuId ): ?CartItem {
		return $this->items[ $skuId ] ?? null;
	}

	public function subtotal( string $currency = 'USD' ): Money {
		$sum = Money::zero( $currency );
		foreach ( $this->items as $item ) {
			$sum = $sum->add( $item->lineTotal() );
		}

		return $sum;
	}

	public function withSetItem( CartItem $item ): self {
		$next                   = $this->items;
		$next[ $item->skuId() ] = $item;

		return new self(
			sessionKey: $this->sessionKey,
			userId: $this->userId,
			items: array_values( $next ),
			couponCode: $this->couponCode,
			shippingCountry: $this->shippingCountry,
			shippingRegion: $this->shippingRegion,
			shippingMethod: $this->shippingMethod,
			expiresAt: $this->expiresAt
		);
	}

	public function withoutItem( int $skuId ): self {
		$next = $this->items;
		unset( $next[ $skuId ] );

		return new self(
			sessionKey: $this->sessionKey,
			userId: $this->userId,
			items: array_values( $next ),
			couponCode: $this->couponCode,
			shippingCountry: $this->shippingCountry,
			shippingRegion: $this->shippingRegion,
			shippingMethod: $this->shippingMethod,
			expiresAt: $this->expiresAt
		);
	}

	public function withCouponCode( ?string $couponCode ): self {
		return new self(
			sessionKey: $this->sessionKey,
			userId: $this->userId,
			items: array_values( $this->items ),
			couponCode: $couponCode,
			shippingCountry: $this->shippingCountry,
			shippingRegion: $this->shippingRegion,
			shippingMethod: $this->shippingMethod,
			expiresAt: $this->expiresAt
		);
	}

	public function withDestination( string $country, string $region, string $method ): self {
		return new self(
			sessionKey: $this->sessionKey,
			userId: $this->userId,
			items: array_values( $this->items ),
			couponCode: $this->couponCode,
			shippingCountry: $country,
			shippingRegion: $region,
			shippingMethod: $method,
			expiresAt: $this->expiresAt
		);
	}

	public function withExtendedExpiry( string $expiresAt ): self {
		return new self(
			sessionKey: $this->sessionKey,
			userId: $this->userId,
			items: array_values( $this->items ),
			couponCode: $this->couponCode,
			shippingCountry: $this->shippingCountry,
			shippingRegion: $this->shippingRegion,
			shippingMethod: $this->shippingMethod,
			expiresAt: $expiresAt
		);
	}

	/**
	 * Merge a guest cart into this user cart, summing matching SKUs up to optional max available stock.
	 *
	 * @param null|callable(int):int $maxQtyResolver Optional callback returning available stock for a skuId.
	 */
	public function mergeFrom( self $guestCart, ?callable $maxQtyResolver = null ): self {
		$merged = $this->items;

		foreach ( $guestCart->items as $skuId => $guestItem ) {
			$existingQty = isset( $merged[ $skuId ] ) ? $merged[ $skuId ]->qty() : 0;
			$desiredQty  = $existingQty + $guestItem->qty();

			if ( null !== $maxQtyResolver ) {
				$maxAvailable = max( 0, (int) $maxQtyResolver( $skuId ) );
				if ( 0 === $maxAvailable ) {
					continue;
				}
				$desiredQty = min( $desiredQty, $maxAvailable );
			}

			if ( $desiredQty > 0 ) {
				$merged[ $skuId ] = $guestItem->withQty( $desiredQty );
			}
		}

		$coupon = $this->couponCode ?? $guestCart->couponCode;

		return new self(
			sessionKey: $this->sessionKey,
			userId: $this->userId,
			items: array_values( $merged ),
			couponCode: $coupon,
			shippingCountry: $this->shippingCountry,
			shippingRegion: $this->shippingRegion,
			shippingMethod: $this->shippingMethod,
			expiresAt: $this->expiresAt
		);
	}

	/**
	 * Serialize cart items & destination for `wp_gec_sessions.cart_json`.
	 */
	public function toCartJson(): string {
		$payload = [
			'items'       => array_map( static fn( CartItem $i ): array => $i->toArray(), array_values( $this->items ) ),
			'destination' => [
				'country'         => $this->shippingCountry,
				'region'          => $this->shippingRegion,
				'shipping_method' => $this->shippingMethod,
			],
		];

		$encoded = json_encode( $payload, JSON_THROW_ON_ERROR );
		return $encoded;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'session_key' => $this->sessionKey,
			'user_id'     => $this->userId,
			'items'       => array_map( static fn( CartItem $i ): array => $i->toArray(), array_values( $this->items ) ),
			'coupon_code' => $this->couponCode,
			'destination' => [
				'country'         => $this->shippingCountry,
				'region'          => $this->shippingRegion,
				'shipping_method' => $this->shippingMethod,
			],
			'expires_at'  => $this->expiresAt,
		];
	}
}
