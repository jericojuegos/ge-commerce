<?php
/**
 * WordPress $wpdb Repository Adapter for Cart Sessions (`wp_gec_sessions`).
 *
 * @package GeCommerce\Modules\Cart\Infrastructure\Repositories
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Infrastructure\Repositories;

use GeCommerce\Modules\Cart\Domain\Contracts\CartSessionRepositoryInterface;
use GeCommerce\Modules\Cart\Domain\Entities\Cart;
use GeCommerce\Modules\Cart\Domain\Entities\CartItem;
use GeCommerce\Shared\ValueObjects\Money;
use RuntimeException;

final class WpdbCartSessionRepository implements CartSessionRepositoryInterface {

	private function db(): \wpdb {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
			throw new RuntimeException( 'WordPress $wpdb instance is not available.' );
		}

		return $wpdb;
	}

	public function findBySessionKey( string $sessionKey, ?string $nowUtc = null ): ?Cart {
		$wpdb        = $this->db();
		$sessionsTbl = $wpdb->prefix . 'gec_sessions';
		$cleanKey    = trim( $sessionKey );
		if ( '' === $cleanKey ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$sessionsTbl} WHERE session_key = %s LIMIT 1", $cleanKey ),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$cart = $this->hydrateCart( $row );
		if ( $cart->isExpired( $nowUtc ) ) {
			return null;
		}

		return $cart;
	}

	public function findByUserId( int $userId, ?string $nowUtc = null ): ?Cart {
		if ( $userId <= 0 ) {
			return null;
		}

		$wpdb        = $this->db();
		$sessionsTbl = $wpdb->prefix . 'gec_sessions';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$sessionsTbl} WHERE user_id = %d ORDER BY expires_at DESC LIMIT 1",
				$userId
			),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$cart = $this->hydrateCart( $row );
		if ( $cart->isExpired( $nowUtc ) ) {
			return null;
		}

		return $cart;
	}

	public function save( Cart $cart ): Cart {
		$wpdb        = $this->db();
		$sessionsTbl = $wpdb->prefix . 'gec_sessions';

		$cartJson   = $cart->toCartJson();
		$couponCode = $cart->couponCode();

		if ( null === $couponCode ) {
			$sql = $wpdb->prepare(
				"INSERT INTO {$sessionsTbl} (session_key, user_id, cart_json, coupon_code, expires_at)
				 VALUES (%s, %d, %s, NULL, %s)
				 ON DUPLICATE KEY UPDATE
				   user_id = VALUES(user_id),
				   cart_json = VALUES(cart_json),
				   coupon_code = NULL,
				   expires_at = VALUES(expires_at)",
				$cart->sessionKey(),
				$cart->userId(),
				$cartJson,
				$cart->expiresAt()
			);
		} else {
			$sql = $wpdb->prepare(
				"INSERT INTO {$sessionsTbl} (session_key, user_id, cart_json, coupon_code, expires_at)
				 VALUES (%s, %d, %s, %s, %s)
				 ON DUPLICATE KEY UPDATE
				   user_id = VALUES(user_id),
				   cart_json = VALUES(cart_json),
				   coupon_code = VALUES(coupon_code),
				   expires_at = VALUES(expires_at)",
				$cart->sessionKey(),
				$cart->userId(),
				$cartJson,
				$couponCode,
				$cart->expiresAt()
			);
		}

		$result = $wpdb->query( $sql );
		if ( false === $result ) {
			throw new RuntimeException( 'Failed to save cart session: ' . $wpdb->last_error );
		}

		return $cart;
	}

	public function deleteBySessionKey( string $sessionKey ): bool {
		$wpdb        = $this->db();
		$sessionsTbl = $wpdb->prefix . 'gec_sessions';
		$deleted     = $wpdb->delete( $sessionsTbl, [ 'session_key' => trim( $sessionKey ) ], [ '%s' ] );

		if ( false === $deleted ) {
			throw new RuntimeException( 'Failed to delete cart session: ' . $wpdb->last_error );
		}

		return $deleted > 0;
	}

	public function purgeExpired( ?string $nowUtc = null ): int {
		$wpdb        = $this->db();
		$sessionsTbl = $wpdb->prefix . 'gec_sessions';
		$cutoff      = null !== $nowUtc && '' !== trim( $nowUtc ) ? $nowUtc : gmdate( 'Y-m-d H:i:s' );

		$deleted = $wpdb->query(
			$wpdb->prepare( "DELETE FROM {$sessionsTbl} WHERE expires_at <= %s", $cutoff )
		);

		return is_int( $deleted ) ? $deleted : 0;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function hydrateCart( array $row ): Cart {
		$decoded = json_decode( (string) ( $row['cart_json'] ?? '{}' ), true );
		$items   = [];

		if ( is_array( $decoded ) && is_array( $decoded['items'] ?? null ) ) {
			foreach ( $decoded['items'] as $rawItem ) {
				if ( ! is_array( $rawItem ) ) {
					continue;
				}
				$skuId = (int) ( $rawItem['sku_id'] ?? 0 );
				$qty   = (int) ( $rawItem['qty'] ?? 0 );
				if ( $skuId <= 0 || $qty <= 0 ) {
					continue;
				}
				$items[] = new CartItem(
					skuId: $skuId,
					skuCode: (string) ( $rawItem['sku_code'] ?? 'SKU-' . $skuId ),
					title: (string) ( $rawItem['title'] ?? 'Item' ),
					unitPrice: Money::fromCents( max( 0, (int) ( $rawItem['unit_price_cents'] ?? 0 ) ) ),
					qty: $qty
				);
			}
		}

		$dest    = is_array( $decoded['destination'] ?? null ) ? $decoded['destination'] : [];
		$country = (string) ( $dest['country'] ?? 'US' );
		$region  = (string) ( $dest['region'] ?? 'CA' );
		$method  = (string) ( $dest['shipping_method'] ?? 'standard' );

		return new Cart(
			sessionKey: (string) $row['session_key'],
			userId: max( 0, (int) $row['user_id'] ),
			items: $items,
			couponCode: isset( $row['coupon_code'] ) && null !== $row['coupon_code'] && '' !== $row['coupon_code']
				? (string) $row['coupon_code']
				: null,
			shippingCountry: $country,
			shippingRegion: $region,
			shippingMethod: $method,
			expiresAt: (string) $row['expires_at']
		);
	}
}
