<?php
/**
 * WordPress $wpdb Repository Adapter for Coupons.
 *
 * @package GeCommerce\Modules\Coupon\Infrastructure\Repositories
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Coupon\Infrastructure\Repositories;

use GeCommerce\Modules\Coupon\Domain\Contracts\CouponRepositoryInterface;
use GeCommerce\Modules\Coupon\Domain\Entities\Coupon;
use GeCommerce\Modules\Coupon\Domain\ValueObjects\DiscountType;
use GeCommerce\Shared\ValueObjects\Money;
use RuntimeException;

final class WpdbCouponRepository implements CouponRepositoryInterface {

	private function db(): \wpdb {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
			throw new RuntimeException( 'WordPress $wpdb instance is not available.' );
		}

		return $wpdb;
	}

	public function findById( int $id ): ?Coupon {
		$wpdb       = $this->db();
		$couponsTbl = $wpdb->prefix . 'gec_coupons';
		$row        = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$couponsTbl} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrateCoupon( $row ) : null;
	}

	public function findByCode( string $code ): ?Coupon {
		$wpdb       = $this->db();
		$couponsTbl = $wpdb->prefix . 'gec_coupons';
		$normalized = strtoupper( trim( $code ) );
		if ( '' === $normalized ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$couponsTbl} WHERE code = %s LIMIT 1", $normalized ),
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrateCoupon( $row ) : null;
	}

	public function findAll( int $limit = 50, int $offset = 0 ): array {
		$wpdb       = $this->db();
		$couponsTbl = $wpdb->prefix . 'gec_coupons';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$couponsTbl} ORDER BY id DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		return array_map( fn( array $row ): Coupon => $this->hydrateCoupon( $row ), $rows );
	}

	public function count(): int {
		$wpdb       = $this->db();
		$couponsTbl = $wpdb->prefix . 'gec_coupons';
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$couponsTbl}" );
	}

	public function save( Coupon $coupon ): Coupon {
		$wpdb       = $this->db();
		$couponsTbl = $wpdb->prefix . 'gec_coupons';

		$data = [
			'code'            => $coupon->code(),
			'discount_type'   => $coupon->discountType()->value,
			'amount_value'    => $coupon->amountValue(),
			'min_order_cents' => $coupon->minOrder()->cents(),
			'usage_limit'     => $coupon->usageLimit(),
			'used_count'      => $coupon->usedCount(),
			'expires_at'      => $coupon->expiresAt(),
		];

		$formats = [ '%s', '%s', '%d', '%d', null === $coupon->usageLimit() ? null : '%d', '%d', null === $coupon->expiresAt() ? null : '%s' ];
		// Build explicit prepared SQL so NULL values are stored cleanly as SQL NULL instead of empty/0.
		if ( null === $coupon->id() ) {
			$sql = $wpdb->prepare(
				"INSERT INTO {$couponsTbl} (code, discount_type, amount_value, min_order_cents, usage_limit, used_count, expires_at)
				 VALUES (%s, %s, %d, %d, " . ( null === $coupon->usageLimit() ? 'NULL' : '%d' ) . ', %d, ' . ( null === $coupon->expiresAt() ? 'NULL' : '%s' ) . ')',
				...array_values(
					array_filter(
						[
							$data['code'],
							$data['discount_type'],
							$data['amount_value'],
							$data['min_order_cents'],
							$coupon->usageLimit(),
							$data['used_count'],
							$coupon->expiresAt(),
						],
						static fn( $v ): bool => null !== $v
					)
				)
			);

			$result = $wpdb->query( $sql );
			if ( false === $result ) {
				throw new RuntimeException( 'Failed to insert coupon: ' . $wpdb->last_error );
			}
			$couponId = (int) $wpdb->insert_id;
		} else {
			$couponId = $coupon->id();
			$params   = [
				$data['code'],
				$data['discount_type'],
				$data['amount_value'],
				$data['min_order_cents'],
			];
			$limitSql = 'usage_limit = NULL';
			if ( null !== $coupon->usageLimit() ) {
				$limitSql = 'usage_limit = %d';
				$params[] = $coupon->usageLimit();
			}
			$params[]  = $data['used_count'];
			$expirySql = 'expires_at = NULL';
			if ( null !== $coupon->expiresAt() ) {
				$expirySql = 'expires_at = %s';
				$params[]  = $coupon->expiresAt();
			}
			$params[] = $couponId;

			$sql    = $wpdb->prepare(
				"UPDATE {$couponsTbl}
				 SET code = %s, discount_type = %s, amount_value = %d, min_order_cents = %d, {$limitSql}, used_count = %d, {$expirySql}
				 WHERE id = %d",
				...$params
			);
			$result = $wpdb->query( $sql );
			if ( false === $result ) {
				throw new RuntimeException( 'Failed to update coupon: ' . $wpdb->last_error );
			}
		}

		unset( $formats );
		$persisted = $this->findById( $couponId );
		if ( null === $persisted ) {
			throw new RuntimeException( 'Failed to reload saved coupon.' );
		}

		return $persisted;
	}

	public function delete( int $id ): bool {
		$wpdb       = $this->db();
		$couponsTbl = $wpdb->prefix . 'gec_coupons';
		$deleted    = $wpdb->delete( $couponsTbl, [ 'id' => $id ], [ '%d' ] );

		if ( false === $deleted ) {
			throw new RuntimeException( 'Failed to delete coupon: ' . $wpdb->last_error );
		}

		return $deleted > 0;
	}

	public function codeExists( string $code, ?int $excludeId = null ): bool {
		$wpdb       = $this->db();
		$couponsTbl = $wpdb->prefix . 'gec_coupons';
		$normalized = strtoupper( trim( $code ) );

		if ( null !== $excludeId ) {
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$couponsTbl} WHERE code = %s AND id != %d LIMIT 1",
					$normalized,
					$excludeId
				)
			);
		} else {
			$found = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$couponsTbl} WHERE code = %s LIMIT 1", $normalized )
			);
		}

		return null !== $found;
	}

	public function incrementUsage( string $code ): void {
		$wpdb       = $this->db();
		$couponsTbl = $wpdb->prefix . 'gec_coupons';
		$normalized = strtoupper( trim( $code ) );

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$couponsTbl} SET used_count = used_count + 1 WHERE code = %s",
				$normalized
			)
		);
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function hydrateCoupon( array $row ): Coupon {
		$type = DiscountType::tryFrom( (string) $row['discount_type'] ) ?? DiscountType::FIXED_CENTS;

		return new Coupon(
			id: (int) $row['id'],
			code: (string) $row['code'],
			discountType: $type,
			amountValue: (int) $row['amount_value'],
			minOrder: Money::fromCents( max( 0, (int) $row['min_order_cents'] ) ),
			usageLimit: isset( $row['usage_limit'] ) && null !== $row['usage_limit'] ? (int) $row['usage_limit'] : null,
			usedCount: max( 0, (int) $row['used_count'] ),
			expiresAt: isset( $row['expires_at'] ) && null !== $row['expires_at'] && '' !== $row['expires_at'] ? (string) $row['expires_at'] : null
		);
	}
}
