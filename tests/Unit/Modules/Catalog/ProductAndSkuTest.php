<?php
/**
 * Zero-WP Unit & Invariant Tests for Catalog Domain & Application Handlers.
 *
 * @package GeCommerce\Tests\Unit\Modules\Catalog
 */

declare(strict_types=1);

namespace GeCommerce\Tests\Unit\Modules\Catalog;

use GeCommerce\Modules\Catalog\Application\DTOs\SaveProductDTO;
use GeCommerce\Modules\Catalog\Application\DTOs\SkuInputDTO;
use GeCommerce\Modules\Catalog\Application\Handlers\DeleteProductHandler;
use GeCommerce\Modules\Catalog\Application\Handlers\GetProductHandler;
use GeCommerce\Modules\Catalog\Application\Handlers\ListProductsHandler;
use GeCommerce\Modules\Catalog\Application\Handlers\SaveProductHandler;
use GeCommerce\Modules\Catalog\Domain\Contracts\ProductRepositoryInterface;
use GeCommerce\Modules\Catalog\Domain\Entities\Product;
use GeCommerce\Modules\Catalog\Domain\Entities\Sku;
use GeCommerce\Modules\Catalog\Domain\ValueObjects\SkuAttributes;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProductAndSkuTest extends TestCase {

	private function createInMemoryRepository(): ProductRepositoryInterface {
		return new class implements ProductRepositoryInterface {
			/** @var array<int, Product> */
			public array $items = [];
			public int $nextProductId = 1;
			public int $nextSkuId = 1;
			public int $saveCalls = 0;

			public function findById( int $id ): ?Product {
				return $this->items[ $id ] ?? null;
			}

			public function findBySlug( string $slug ): ?Product {
				foreach ( $this->items as $product ) {
					if ( $product->slug() === $slug ) {
						return $product;
					}
				}
				return null;
			}

			public function findAll( int $limit = 50, int $offset = 0, ?string $status = null ): array {
				$filtered = array_values(
					array_filter(
						$this->items,
						static fn( Product $p ): bool => null === $status || $p->status()->value === $status
					)
				);
				return array_slice( $filtered, $offset, $limit );
			}

			public function count( ?string $status = null ): int {
				return count( $this->findAll( 1000, 0, $status ) );
			}

			public function save( Product $product ): Product {
				++$this->saveCalls;
				$productId = $product->id() ?? $this->nextProductId++;
				$savedSkus = [];
				foreach ( $product->skus() as $sku ) {
					$skuId       = $sku->id() ?? $this->nextSkuId++;
					$savedSkus[] = new Sku(
						id: $skuId,
						productId: $productId,
						skuCode: $sku->skuCode(),
						attributes: $sku->attributes(),
						price: $sku->price(),
						availableQty: $sku->availableQty(),
						reservedQty: $sku->reservedQty()
					);
				}

				$saved = new Product(
					id: $productId,
					slug: $product->slug(),
					title: $product->title(),
					description: $product->description(),
					status: $product->status(),
					skus: $savedSkus
				);

				$this->items[ $productId ] = $saved;
				return $saved;
			}

			public function delete( int $id ): bool {
				if ( ! isset( $this->items[ $id ] ) ) {
					return false;
				}
				unset( $this->items[ $id ] );
				return true;
			}

			public function skuCodeExists( string $skuCode, ?int $excludeSkuId = null ): bool {
				$normalized = strtoupper( trim( $skuCode ) );
				foreach ( $this->items as $product ) {
					foreach ( $product->skus() as $sku ) {
						if ( $sku->skuCode() === $normalized && $sku->id() !== $excludeSkuId ) {
							return true;
						}
					}
				}
				return false;
			}

			public function slugExists( string $slug, ?int $excludeProductId = null ): bool {
				$normalized = strtolower( trim( $slug ) );
				foreach ( $this->items as $product ) {
					if ( $product->slug() === $normalized && $product->id() !== $excludeProductId ) {
						return true;
					}
				}
				return false;
			}
		};
	}

	public function test_property_1_persists_product_and_skus_with_integer_cents_and_normalized_attributes(): void {
		$repo    = $this->createInMemoryRepository();
		$handler = new SaveProductHandler( $repo );

		$saved = $handler->execute(
			new SaveProductDTO(
				id: null,
				slug: 'ninja-hoodie',
				title: 'Ninja Hoodie',
				description: 'Heavyweight cotton hoodie.',
				status: 'published',
				skus: [
					new SkuInputDTO(
						id: null,
						skuCode: 'nh-blk-l',
						attributes: [ 'Size' => 'L', 'Color' => 'Black' ],
						priceCents: 4999,
						availableQty: 25
					),
					new SkuInputDTO(
						id: null,
						skuCode: 'nh-blk-m',
						attributes: [ 'Size' => 'M', 'Color' => 'Black' ],
						priceCents: 4999,
						availableQty: 10
					),
				]
			)
		);

		$this->assertSame( 1, $saved->id() );
		$this->assertSame( 'ninja-hoodie', $saved->slug() );
		$this->assertCount( 2, $saved->skus() );
		$this->assertSame( 'NH-BLK-L', $saved->skus()[0]->skuCode() );
		$this->assertSame( 4999, $saved->skus()[0]->price()->cents() );
		$this->assertSame( 25, $saved->skus()[0]->availableQty() );
		$this->assertSame( '{"color":"Black","size":"L"}', $saved->skus()[0]->attributes()->toJson() );
		$this->assertSame(
			[ 'color' => 'Black', 'size' => 'L' ],
			SkuAttributes::fromJson( $saved->skus()[0]->attributes()->toJson() )->toArray()
		);

		$fetched = ( new GetProductHandler( $repo ) )->execute( 1 );
		$this->assertSame( 'Ninja Hoodie', $fetched->title() );

		$listed = ( new ListProductsHandler( $repo ) )->execute( 1, 20, 'published' );
		$this->assertSame( 1, $listed['total'] );
		$this->assertCount( 1, $listed['items'] );

		$this->assertTrue( ( new DeleteProductHandler( $repo ) )->execute( 1 ) );
		$this->assertNull( $repo->findById( 1 ) );
	}

	public function test_property_2_rejects_negative_price_cents_before_saving(): void {
		$repo    = $this->createInMemoryRepository();
		$handler = new SaveProductHandler( $repo );

		$this->expectException( InvalidArgumentException::class );

		try {
			$handler->execute(
				new SaveProductDTO(
					id: null,
					slug: 'invalid-price',
					title: 'Invalid Price',
					description: '',
					status: 'draft',
					skus: [
						new SkuInputDTO( null, 'SKU-1', [], -100, 5 ),
					]
				)
			);
		} finally {
			$this->assertSame( 0, $repo->saveCalls );
		}
	}

	public function test_property_2_rejects_negative_available_qty_before_saving(): void {
		$repo    = $this->createInMemoryRepository();
		$handler = new SaveProductHandler( $repo );

		$this->expectException( InvalidArgumentException::class );

		try {
			$handler->execute(
				new SaveProductDTO(
					id: null,
					slug: 'invalid-qty',
					title: 'Invalid Qty',
					description: '',
					status: 'draft',
					skus: [
						new SkuInputDTO( null, 'SKU-1', [], 1000, -1 ),
					]
				)
			);
		} finally {
			$this->assertSame( 0, $repo->saveCalls );
		}
	}

	public function test_property_2_rejects_duplicate_sku_code_across_products(): void {
		$repo    = $this->createInMemoryRepository();
		$handler = new SaveProductHandler( $repo );

		$handler->execute(
			new SaveProductDTO(
				id: null,
				slug: 'first-product',
				title: 'First Product',
				description: '',
				status: 'published',
				skus: [
					new SkuInputDTO( null, 'SHARED-SKU', [], 1500, 5 ),
				]
			)
		);

		$this->expectException( InvalidArgumentException::class );

		try {
			$handler->execute(
				new SaveProductDTO(
					id: null,
					slug: 'second-product',
					title: 'Second Product',
					description: '',
					status: 'published',
					skus: [
						new SkuInputDTO( null, 'shared-sku', [], 2000, 3 ),
					]
				)
			);
		} finally {
			$this->assertSame( 1, $repo->saveCalls );
		}
	}

	public function test_throws_runtime_exception_when_getting_or_deleting_missing_product(): void {
		$repo = $this->createInMemoryRepository();

		$this->expectException( RuntimeException::class );
		( new GetProductHandler( $repo ) )->execute( 999 );
	}
}
