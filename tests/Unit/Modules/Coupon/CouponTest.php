<?php
/**
 * Zero-WP Unit & Invariant Tests for Coupon Domain & Application Services.
 *
 * @package GeCommerce\Tests\Unit\Modules\Coupon
 */

declare(strict_types=1);

namespace GeCommerce\Tests\Unit\Modules\Coupon;

use GeCommerce\Modules\Coupon\Application\DTOs\SaveCouponDTO;
use GeCommerce\Modules\Coupon\Application\Handlers\DeleteCouponHandler;
use GeCommerce\Modules\Coupon\Application\Handlers\GetCouponHandler;
use GeCommerce\Modules\Coupon\Application\Handlers\ListCouponsHandler;
use GeCommerce\Modules\Coupon\Application\Handlers\SaveCouponHandler;
use GeCommerce\Modules\Coupon\Application\Services\CouponEvaluator;
use GeCommerce\Modules\Coupon\Domain\Contracts\CouponRepositoryInterface;
use GeCommerce\Modules\Coupon\Domain\Entities\Coupon;
use GeCommerce\Shared\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CouponTest extends TestCase {

	private function createInMemoryRepository(): CouponRepositoryInterface {
		return new class implements CouponRepositoryInterface {
			/** @var array<int, Coupon> */
			public array $items = [];
			public int $nextId = 1;
			public int $saveCalls = 0;

			public function findById( int $id ): ?Coupon {
				return $this->items[ $id ] ?? null;
			}

			public function findByCode( string $code ): ?Coupon {
				$normalized = strtoupper( trim( $code ) );
				foreach ( $this->items as $coupon ) {
					if ( $coupon->code() === $normalized ) {
						return $coupon;
					}
				}
				return null;
			}

			public function findAll( int $limit = 50, int $offset = 0 ): array {
				return array_slice( array_values( $this->items ), $offset, $limit );
			}

			public function count(): int {
				return count( $this->items );
			}

			public function save( Coupon $coupon ): Coupon {
				++$this->saveCalls;
				$id    = $coupon->id() ?? $this->nextId++;
				$saved = new Coupon(
					id: $id,
					code: $coupon->code(),
					discountType: $coupon->discountType(),
					amountValue: $coupon->amountValue(),
					minOrder: $coupon->minOrder(),
					usageLimit: $coupon->usageLimit(),
					usedCount: $coupon->usedCount(),
					expiresAt: $coupon->expiresAt()
				);

				$this->items[ $id ] = $saved;
				return $saved;
			}

			public function delete( int $id ): bool {
				if ( ! isset( $this->items[ $id ] ) ) {
					return false;
				}
				unset( $this->items[ $id ] );
				return true;
			}

			public function codeExists( string $code, ?int $excludeId = null ): bool {
				$normalized = strtoupper( trim( $code ) );
				foreach ( $this->items as $coupon ) {
					if ( $coupon->code() === $normalized && $coupon->id() !== $excludeId ) {
						return true;
					}
				}
				return false;
			}

			public function incrementUsage( string $code ): void {
				$coupon = $this->findByCode( $code );
				if ( null !== $coupon && null !== $coupon->id() ) {
					$this->items[ $coupon->id() ] = new Coupon(
						id: $coupon->id(),
						code: $coupon->code(),
						discountType: $coupon->discountType(),
						amountValue: $coupon->amountValue(),
						minOrder: $coupon->minOrder(),
						usageLimit: $coupon->usageLimit(),
						usedCount: $coupon->usedCount() + 1,
						expiresAt: $coupon->expiresAt()
					);
				}
			}
		};
	}

	public function test_property_1_computes_fixed_and_percentage_discounts_and_clamps_at_subtotal(): void {
		$repo      = $this->createInMemoryRepository();
		$save      = new SaveCouponHandler( $repo );
		$evaluator = new CouponEvaluator( $repo );

		$fixed = $save->execute(
			new SaveCouponDTO(
				id: null,
				code: 'save20',
				discountType: 'fixed_cents',
				amountValue: 2000,
				minOrderCents: 500,
				usageLimit: 10,
				expiresAt: '2099-12-31 23:59:59'
			)
		);

		$this->assertSame( 'SAVE20', $fixed->code() );

		// Fixed $20.00 off $15.00 must clamp to $15.00 (1500 cents).
		$clampedResult = $evaluator->evaluate( 'save20', Money::fromCents( 1500 ), '2026-11-15 00:00:00' );
		$this->assertTrue( $clampedResult->valid );
		$this->assertSame( 1500, $clampedResult->discount->cents() );

		// Percentage 15.25% (1525 bps) off $19.99 (1999 cents) -> intdiv(1999 * 1525 + 5000, 10000) = 305 cents.
		$save->execute(
			new SaveCouponDTO(
				id: null,
				code: 'VIP1525',
				discountType: 'percent_bps',
				amountValue: 1525,
				minOrderCents: 0
			)
		);

		$pctResult = $evaluator->evaluate( 'VIP1525', Money::fromCents( 1999 ), '2026-11-15 00:00:00' );
		$this->assertTrue( $pctResult->valid );
		$this->assertSame( 305, $pctResult->discount->cents() );

		$listed = ( new ListCouponsHandler( $repo ) )->execute();
		$this->assertSame( 2, $listed['total'] );

		$fetched = ( new GetCouponHandler( $repo ) )->execute( $fixed->id() ?? 0 );
		$this->assertSame( 'SAVE20', $fetched->code() );
		$this->assertTrue( ( new DeleteCouponHandler( $repo ) )->execute( $fixed->id() ?? 0 ) );
	}

	public function test_property_2_rejects_invalid_coupon_creation_before_save(): void {
		$repo = $this->createInMemoryRepository();
		$save = new SaveCouponHandler( $repo );

		try {
			$save->execute( new SaveCouponDTO( null, 'BADPCT', 'percent_bps', 10001 ) );
			$this->fail( 'Expected InvalidArgumentException for percent_bps > 10000.' );
		} catch ( InvalidArgumentException ) {
			$this->assertSame( 0, $repo->saveCalls );
		}

		try {
			$save->execute( new SaveCouponDTO( null, 'NEGVAL', 'fixed_cents', -50 ) );
			$this->fail( 'Expected InvalidArgumentException for negative amount.' );
		} catch ( InvalidArgumentException ) {
			$this->assertSame( 0, $repo->saveCalls );
		}

		$save->execute( new SaveCouponDTO( null, 'UNIQUE10', 'fixed_cents', 1000 ) );

		$this->expectException( InvalidArgumentException::class );
		try {
			$save->execute( new SaveCouponDTO( null, 'unique10', 'fixed_cents', 500 ) );
		} finally {
			$this->assertSame( 1, $repo->saveCalls );
		}
	}

	public function test_property_2_rejects_expired_exhausted_and_below_minimum_coupons(): void {
		$repo      = $this->createInMemoryRepository();
		$save      = new SaveCouponHandler( $repo );
		$evaluator = new CouponEvaluator( $repo );

		$save->execute(
			new SaveCouponDTO(
				id: null,
				code: 'EXPIRED',
				discountType: 'fixed_cents',
				amountValue: 500,
				expiresAt: '2025-01-01 00:00:00'
			)
		);
		$expiredRes = $evaluator->evaluate( 'EXPIRED', Money::fromCents( 5000 ), '2026-11-15 00:00:00' );
		$this->assertFalse( $expiredRes->valid );
		$this->assertSame( 0, $expiredRes->discount->cents() );

		$save->execute(
			new SaveCouponDTO(
				id: null,
				code: 'LIMITED',
				discountType: 'fixed_cents',
				amountValue: 500,
				minOrderCents: 3000,
				usageLimit: 1
			)
		);

		$belowMin = $evaluator->evaluate( 'LIMITED', Money::fromCents( 2500 ), '2026-11-15 00:00:00' );
		$this->assertFalse( $belowMin->valid );

		$evaluator->incrementUsage( 'LIMITED' );
		$exhausted = $evaluator->evaluate( 'LIMITED', Money::fromCents( 5000 ), '2026-11-15 00:00:00' );
		$this->assertFalse( $exhausted->valid );
	}
}
