<?php
/**
 * Unit tests for Money Value Object and Core Container/Migrator.
 *
 * @package GeCommerce\Tests\Unit\Shared
 */

declare(strict_types=1);

namespace GeCommerce\Tests\Unit\Shared;

use GeCommerce\Core\Container;
use GeCommerce\Core\ModuleRegistry;
use GeCommerce\Core\ServiceProvider;
use GeCommerce\Shared\ValueObjects\Money;
use GeCommerce\WordPress\Database\Migrator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase {

	public function test_creates_money_from_integer_cents_and_performs_exact_arithmetic(): void {
		$unitPrice = Money::fromCents( 1999, 'USD' );
		$lineTotal = $unitPrice->multiply( 3 );
		$shipping  = Money::fromCents( 500, 'USD' );
		$total     = $lineTotal->add( $shipping );

		$this->assertSame( 5997, $lineTotal->cents() );
		$this->assertSame( 6497, $total->cents() );
		$this->assertSame( 'USD', $total->currency() );
	}

	public function test_calculates_basis_points_percentage_with_integer_half_up_rounding(): void {
		$subtotal = Money::fromCents( 1005, 'USD' ); // $10.05
		$tax      = $subtotal->percentageBasisPoints( 825 ); // 8.25% -> 82.9125 cents -> 83 cents

		$this->assertSame( 83, $tax->cents() );
	}

	public function test_clamps_subtraction_at_zero_when_discount_exceeds_subtotal(): void {
		$subtotal = Money::fromCents( 1500, 'USD' );
		$discount = Money::fromCents( 2500, 'USD' );

		$clamped = $subtotal->subtractClamped( $discount );
		$this->assertSame( 0, $clamped->cents() );
		$this->assertTrue( $clamped->isZero() );
	}

	public function test_rejects_negative_cents_and_currency_mismatch(): void {
		$this->expectException( InvalidArgumentException::class );
		Money::fromCents( -1, 'USD' );
	}

	public function test_rejects_adding_different_currencies(): void {
		$usd = Money::fromCents( 1000, 'USD' );
		$eur = Money::fromCents( 1000, 'EUR' );

		$this->expectException( InvalidArgumentException::class );
		$usd->add( $eur );
	}

	public function test_container_and_module_registry_lifecycle(): void {
		$container = new Container();
		$registry  = new ModuleRegistry( $container );

		$provider = new class extends ServiceProvider {
			public bool $booted = false;

			public function register( Container $container ): void {
				$container->singleton( 'test.service', static fn(): Money => Money::fromCents( 4200 ) );
			}

			public function boot( Container $container ): void {
				$this->booted = $container->has( 'test.service' );
			}
		};

		$registry->add( $provider );
		$registry->bootAll();

		$this->assertTrue( $provider->booted );
		$this->assertSame( 4200, $container->get( 'test.service' )->cents() );
	}

	public function test_migrator_defines_all_seven_custom_gec_tables(): void {
		$queries = Migrator::getSchemaQueries( 'wp_gec_', 'DEFAULT CHARACTER SET utf8mb4' );

		$expectedTables = [
			'products',
			'skus',
			'sessions',
			'coupons',
			'orders',
			'order_items',
			'stock_reservations',
		];

		$this->assertSame( $expectedTables, array_keys( $queries ) );
		foreach ( $expectedTables as $table ) {
			$this->assertStringContainsString( "CREATE TABLE wp_gec_{$table}", $queries[ $table ] );
			$this->assertStringContainsString( 'PRIMARY KEY  (', $queries[ $table ] );
		}
	}
}
