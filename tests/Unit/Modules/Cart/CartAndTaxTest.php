<?php
/**
 * Zero-WP Unit & Invariant Tests for Cart, Tax, Shipping & Guest-Merge Handlers.
 *
 * @package GeCommerce\Tests\Unit\Modules\Cart
 */

declare(strict_types=1);

namespace GeCommerce\Tests\Unit\Modules\Cart;

use GeCommerce\Modules\Cart\Application\DTOs\CartItemInputDTO;
use GeCommerce\Modules\Cart\Application\DTOs\QuotePreviewDTO;
use GeCommerce\Modules\Cart\Application\Handlers\CalculateCartTotalsHandler;
use GeCommerce\Modules\Cart\Application\Handlers\GetCartHandler;
use GeCommerce\Modules\Cart\Application\Handlers\MergeGuestCartHandler;
use GeCommerce\Modules\Cart\Application\Handlers\SaveZoneConfigHandler;
use GeCommerce\Modules\Cart\Application\Handlers\UpdateCartHandler;
use GeCommerce\Modules\Cart\Domain\Contracts\CartSessionRepositoryInterface;
use GeCommerce\Modules\Cart\Domain\Contracts\ZoneConfigRepositoryInterface;
use GeCommerce\Modules\Cart\Domain\Entities\Cart;
use GeCommerce\Modules\Cart\Domain\ValueObjects\ShippingZone;
use GeCommerce\Modules\Cart\Domain\ValueObjects\TaxZone;
use GeCommerce\Shared\Contracts\CatalogLookupInterface;
use GeCommerce\Shared\Contracts\CouponEvaluationResult;
use GeCommerce\Shared\Contracts\CouponEvaluatorInterface;
use GeCommerce\Shared\Contracts\SkuSnapshot;
use GeCommerce\Shared\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CartAndTaxTest extends TestCase {

	private function createInMemorySessions(): CartSessionRepositoryInterface {
		return new class implements CartSessionRepositoryInterface {
			/** @var array<string, Cart> */
			public array $sessions = [];

			public function findBySessionKey( string $sessionKey, ?string $nowUtc = null ): ?Cart {
				$cart = $this->sessions[ $sessionKey ] ?? null;
				if ( null !== $cart && $cart->isExpired( $nowUtc ) ) {
					return null;
				}
				return $cart;
			}

			public function findByUserId( int $userId, ?string $nowUtc = null ): ?Cart {
				foreach ( $this->sessions as $cart ) {
					if ( $cart->userId() === $userId && ! $cart->isExpired( $nowUtc ) ) {
						return $cart;
					}
				}
				return null;
			}

			public function save( Cart $cart ): Cart {
				$this->sessions[ $cart->sessionKey() ] = $cart;
				return $cart;
			}

			public function deleteBySessionKey( string $sessionKey ): bool {
				if ( ! isset( $this->sessions[ $sessionKey ] ) ) {
					return false;
				}
				unset( $this->sessions[ $sessionKey ] );
				return true;
			}

			public function purgeExpired( ?string $nowUtc = null ): int {
				$removed = 0;
				foreach ( $this->sessions as $key => $cart ) {
					if ( $cart->isExpired( $nowUtc ) ) {
						unset( $this->sessions[ $key ] );
						++$removed;
					}
				}
				return $removed;
			}
		};
	}

	private function createZoneRepo(): ZoneConfigRepositoryInterface {
		return new class implements ZoneConfigRepositoryInterface {
			/** @var list<TaxZone> */
			public array $taxZones;
			/** @var list<ShippingZone> */
			public array $shippingZones;

			public function __construct() {
				$this->taxZones = [
					new TaxZone( 'US', 'CA', 825, 'CA Sales Tax' ),
					new TaxZone( 'US', '*', 600, 'US Base Tax' ),
					new TaxZone( '*', '*', 0, 'Zero Tax' ),
				];
				$this->shippingZones = [
					new ShippingZone( 'US', '*', 'standard', 599, 7500, 'US Standard' ),
					new ShippingZone( 'US', '*', 'express', 1499, null, 'US Express' ),
					new ShippingZone( '*', '*', 'standard', 1999, null, 'International Standard' ),
				];
			}

			public function getTaxZones(): array {
				return $this->taxZones;
			}

			public function getShippingZones(): array {
				return $this->shippingZones;
			}

			public function saveZones( array $taxZones, array $shippingZones ): void {
				$this->taxZones      = $taxZones;
				$this->shippingZones = $shippingZones;
			}
		};
	}

	private function createCatalogLookup(): CatalogLookupInterface {
		return new class implements CatalogLookupInterface {
			/** @var array<int, SkuSnapshot> */
			public array $skus;

			public function __construct() {
				$this->skus = [
					101 => new SkuSnapshot( 101, 1, 'HOODIE-L', 'Ninja Hoodie', Money::fromCents( 4999 ), 5, true ),
					102 => new SkuSnapshot( 102, 2, 'MUG-BLK', 'Dev Mug', Money::fromCents( 1500 ), 10, true ),
				];
			}

			public function findSkuById( int $skuId ): ?SkuSnapshot {
				return $this->skus[ $skuId ] ?? null;
			}

			public function findSkuByCode( string $skuCode ): ?SkuSnapshot {
				foreach ( $this->skus as $sku ) {
					if ( $sku->skuCode === strtoupper( trim( $skuCode ) ) ) {
						return $sku;
					}
				}
				return null;
			}
		};
	}

	private function createCouponEvaluator(): CouponEvaluatorInterface {
		return new class implements CouponEvaluatorInterface {
			public function evaluate( string $code, Money $subtotal, ?string $nowUtc = null ): CouponEvaluationResult {
				$code = strtoupper( trim( $code ) );
				if ( 'TENOFF' === $code ) {
					$discount = Money::fromCents( min( 1000, $subtotal->cents() ) );
					return CouponEvaluationResult::valid( 'TENOFF', $discount );
				}
				if ( 'HUGE100' === $code ) {
					$discount = Money::fromCents( min( 10000, $subtotal->cents() ) );
					return CouponEvaluationResult::valid( 'HUGE100', $discount );
				}
				return CouponEvaluationResult::invalid( $code, 'Invalid coupon.' );
			}

			public function incrementUsage( string $code ): void {}
		};
	}

	public function test_property_1_computes_integer_totals_tax_specificity_and_shipping_threshold(): void {
		$sessions   = $this->createInMemorySessions();
		$zones      = $this->createZoneRepo();
		$catalog    = $this->createCatalogLookup();
		$coupons    = $this->createCouponEvaluator();
		$totalsCalc = new CalculateCartTotalsHandler( $coupons, $zones );
		$getCart    = new GetCartHandler( $sessions, $catalog, $totalsCalc );
		$updateCart = new UpdateCartHandler( $sessions, $catalog, $coupons, $getCart, $totalsCalc );

		$now = '2026-11-15 12:00:00';

		// Add 1x Hoodie ($49.99) -> subtotal = 4999.
		$updateCart->addItem( 'guest_abc', 0, new CartItemInputDTO( 101, 1 ), $now );
		// Apply $10.00 off -> discount = 1000, taxable = 3999.
		$updateCart->applyCoupon( 'guest_abc', 0, 'tenoff', $now );
		// Destination US-CA standard: 8.25% tax on 3999 = intdiv(3999*825 + 5000, 10000) = 330 cents; shipping = 599 (<7500).
		$res = $updateCart->setDestination( 'guest_abc', 0, 'US', 'CA', 'standard', $now );

		$this->assertSame( 4999, $res['totals']->subtotal->cents() );
		$this->assertSame( 1000, $res['totals']->discount->cents() );
		$this->assertSame( 3999, $res['totals']->taxableSubtotal->cents() );
		$this->assertSame( 330, $res['totals']->tax->cents() );
		$this->assertSame( 599, $res['totals']->shipping->cents() );
		$this->assertSame( 3999 + 330 + 599, $res['totals']->total->cents() );

		// Add another Hoodie -> subtotal = 9998, taxable = 8998 (>= 7500 -> Free shipping!), CA tax = intdiv(8998*825 + 5000, 10000) = 742.
		$res2 = $updateCart->addItem( 'guest_abc', 0, new CartItemInputDTO( 101, 1 ), $now );
		$this->assertSame( 9998, $res2['totals']->subtotal->cents() );
		$this->assertSame( 8998, $res2['totals']->taxableSubtotal->cents() );
		$this->assertSame( 742, $res2['totals']->tax->cents() );
		$this->assertSame( 0, $res2['totals']->shipping->cents() );
		$this->assertSame( 8998 + 742, $res2['totals']->total->cents() );

		// Check QuotePreviewDTO for US-NY (matches US-* = 600 bps) & HUGE100 clamping.
		$preview = $totalsCalc->forPreview( new QuotePreviewDTO( 5000, 'HUGE100', 'US', 'NY', 'express', $now ) );
		$this->assertSame( 5000, $preview->discount->cents() );
		$this->assertSame( 0, $preview->taxableSubtotal->cents() );
		$this->assertSame( 0, $preview->tax->cents() );
		$this->assertSame( 1499, $preview->shipping->cents() );
		$this->assertSame( 1499, $preview->total->cents() );
	}

	public function test_property_2_merges_guest_cart_into_user_cart_caps_at_stock_and_deletes_guest_session(): void {
		$sessions   = $this->createInMemorySessions();
		$zones      = $this->createZoneRepo();
		$catalog    = $this->createCatalogLookup();
		$coupons    = $this->createCouponEvaluator();
		$totalsCalc = new CalculateCartTotalsHandler( $coupons, $zones );
		$getCart    = new GetCartHandler( $sessions, $catalog, $totalsCalc );
		$updateCart = new UpdateCartHandler( $sessions, $catalog, $coupons, $getCart, $totalsCalc );
		$mergeCart  = new MergeGuestCartHandler( $sessions, $catalog, $getCart, $totalsCalc );

		$now = '2026-11-15 12:00:00';

		// Guest cart has 4x Hoodie (max stock is 5) + 2x Mug + coupon TENOFF.
		$updateCart->addItem( 'guest_xyz', 0, new CartItemInputDTO( 101, 4 ), $now );
		$updateCart->addItem( 'guest_xyz', 0, new CartItemInputDTO( 102, 2 ), $now );
		$updateCart->applyCoupon( 'guest_xyz', 0, 'TENOFF', $now );

		// User #42 already has 3x Hoodie in their user session, no coupon.
		$updateCart->addItem( 'user_42', 42, new CartItemInputDTO( 101, 3 ), $now );

		$merged = $mergeCart->execute( 'guest_xyz', 42, 'user_42', $now );

		// 3 + 4 = 7, capped at available stock 5! Mug = 2. Coupon TENOFF adopted.
		$this->assertSame( 5, $merged['cart']->findItem( 101 )?->qty() );
		$this->assertSame( 2, $merged['cart']->findItem( 102 )?->qty() );
		$this->assertSame( 'TENOFF', $merged['cart']->couponCode() );
		$this->assertNull( $sessions->findBySessionKey( 'guest_xyz', $now ) );
	}

	public function test_property_2_rejects_excess_stock_and_handles_expired_sessions(): void {
		$sessions   = $this->createInMemorySessions();
		$zones      = $this->createZoneRepo();
		$catalog    = $this->createCatalogLookup();
		$coupons    = $this->createCouponEvaluator();
		$totalsCalc = new CalculateCartTotalsHandler( $coupons, $zones );
		$getCart    = new GetCartHandler( $sessions, $catalog, $totalsCalc );
		$updateCart = new UpdateCartHandler( $sessions, $catalog, $coupons, $getCart, $totalsCalc );

		$now = '2026-11-15 12:00:00';
		$updateCart->addItem( 'guest_ttl', 0, new CartItemInputDTO( 102, 2 ), $now );

		// 10 days later -> 7-day session has expired; GetCartHandler returns empty cart.
		$expiredCheck = $getCart->execute( 'guest_ttl', 0, '2026-11-25 12:00:00' );
		$this->assertTrue( $expiredCheck['cart']->isEmpty() );
		$this->assertSame( 0, $expiredCheck['totals']->total->cents() );

		// Requesting qty > available_qty (6 > 5) must throw InvalidArgumentException.
		$this->expectException( InvalidArgumentException::class );
		$updateCart->addItem( 'guest_stock', 0, new CartItemInputDTO( 101, 6 ), $now );
	}

	public function test_save_zone_config_updates_tax_and_shipping_rules(): void {
		$zones   = $this->createZoneRepo();
		$handler = new SaveZoneConfigHandler( $zones );

		$saved = $handler->execute(
			[
				[ 'country' => 'GB', 'region' => '*', 'rate_bps' => 2000, 'label' => 'UK VAT' ],
			],
			[
				[ 'country' => 'GB', 'region' => '*', 'method' => 'standard', 'flat_rate_cents' => 800, 'free_over_cents' => 10000, 'label' => 'Royal Mail' ],
			]
		);

		$this->assertCount( 1, $saved['tax_zones'] );
		$this->assertSame( 2000, $saved['tax_zones'][0]->rateBps() );
		$this->assertSame( 800, $saved['shipping_zones'][0]->flatRateCents() );
	}
}
