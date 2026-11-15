<?php
/**
 * WordPress $wpdb Repository Adapter for Catalog Products & SKUs.
 *
 * @package GeCommerce\Modules\Catalog\Infrastructure\Repositories
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Catalog\Infrastructure\Repositories;

use GeCommerce\Modules\Catalog\Domain\Contracts\ProductRepositoryInterface;
use GeCommerce\Modules\Catalog\Domain\Entities\Product;
use GeCommerce\Modules\Catalog\Domain\Entities\Sku;
use GeCommerce\Modules\Catalog\Domain\ValueObjects\ProductStatus;
use GeCommerce\Modules\Catalog\Domain\ValueObjects\SkuAttributes;
use GeCommerce\Shared\ValueObjects\Money;
use RuntimeException;
use Throwable;

final class WpdbProductRepository implements ProductRepositoryInterface {

	private function db(): \wpdb {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
			throw new RuntimeException( 'WordPress $wpdb instance is not available.' );
		}

		return $wpdb;
	}

	public function findById( int $id ): ?Product {
		$wpdb         = $this->db();
		$productsTbl  = $wpdb->prefix . 'gec_products';
		$row          = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$productsTbl} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$skus = $this->findSkusForProducts( [ (int) $row['id'] ] );
		return $this->hydrateProduct( $row, $skus[ (int) $row['id'] ] ?? [] );
	}

	public function findBySlug( string $slug ): ?Product {
		$wpdb        = $this->db();
		$productsTbl = $wpdb->prefix . 'gec_products';
		$row         = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$productsTbl} WHERE slug = %s LIMIT 1", strtolower( trim( $slug ) ) ),
			ARRAY_A
		);

		if ( ! is_array( $row ) ) {
			return null;
		}

		$skus = $this->findSkusForProducts( [ (int) $row['id'] ] );
		return $this->hydrateProduct( $row, $skus[ (int) $row['id'] ] ?? [] );
	}

	public function findAll( int $limit = 50, int $offset = 0, ?string $status = null ): array {
		$wpdb        = $this->db();
		$productsTbl = $wpdb->prefix . 'gec_products';

		if ( null !== $status ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$productsTbl} WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d",
				$status,
				$limit,
				$offset
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$productsTbl} ORDER BY id DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			);
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		if ( ! is_array( $rows ) || [] === $rows ) {
			return [];
		}

		$productIds = array_map( static fn( array $r ): int => (int) $r['id'], $rows );
		$skuMap     = $this->findSkusForProducts( $productIds );

		$result = [];
		foreach ( $rows as $row ) {
			$pid      = (int) $row['id'];
			$result[] = $this->hydrateProduct( $row, $skuMap[ $pid ] ?? [] );
		}

		return $result;
	}

	public function count( ?string $status = null ): int {
		$wpdb        = $this->db();
		$productsTbl = $wpdb->prefix . 'gec_products';

		if ( null !== $status ) {
			$count = $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$productsTbl} WHERE status = %s", $status )
			);
		} else {
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$productsTbl}" );
		}

		return (int) $count;
	}

	public function save( Product $product ): Product {
		$wpdb        = $this->db();
		$productsTbl = $wpdb->prefix . 'gec_products';
		$skusTbl     = $wpdb->prefix . 'gec_skus';

		$wpdb->query( 'START TRANSACTION' );

		try {
			$productData = [
				'slug'        => $product->slug(),
				'title'       => $product->title(),
				'description' => $product->description(),
				'status'      => $product->status()->value,
			];

			if ( null === $product->id() ) {
				$inserted = $wpdb->insert(
					$productsTbl,
					$productData,
					[ '%s', '%s', '%s', '%s' ]
				);
				if ( false === $inserted ) {
					throw new RuntimeException( 'Failed to insert product: ' . $wpdb->last_error );
				}
				$productId = (int) $wpdb->insert_id;
			} else {
				$productId = $product->id();
				$updated   = $wpdb->update(
					$productsTbl,
					$productData,
					[ 'id' => $productId ],
					[ '%s', '%s', '%s', '%s' ],
					[ '%d' ]
				);
				if ( false === $updated ) {
					throw new RuntimeException( 'Failed to update product: ' . $wpdb->last_error );
				}
			}

			$keptSkuIds = [];
			foreach ( $product->skus() as $sku ) {
				$skuData = [
					'product_id'      => $productId,
					'sku_code'        => $sku->skuCode(),
					'attributes_json' => $sku->attributes()->toJson(),
					'price_cents'     => $sku->price()->cents(),
					'available_qty'   => $sku->availableQty(),
					'reserved_qty'    => $sku->reservedQty(),
				];

				if ( null === $sku->id() ) {
					$inserted = $wpdb->insert(
						$skusTbl,
						$skuData,
						[ '%d', '%s', '%s', '%d', '%d', '%d' ]
					);
					if ( false === $inserted ) {
						throw new RuntimeException( 'Failed to insert SKU: ' . $wpdb->last_error );
					}
					$keptSkuIds[] = (int) $wpdb->insert_id;
				} else {
					$skuId   = $sku->id();
					$updated = $wpdb->update(
						$skusTbl,
						$skuData,
						[ 'id' => $skuId, 'product_id' => $productId ],
						[ '%d', '%s', '%s', '%d', '%d', '%d' ],
						[ '%d', '%d' ]
					);
					if ( false === $updated ) {
						throw new RuntimeException( 'Failed to update SKU: ' . $wpdb->last_error );
					}
					$keptSkuIds[] = $skuId;
				}
			}

			if ( [] === $keptSkuIds ) {
				$wpdb->query( $wpdb->prepare( "DELETE FROM {$skusTbl} WHERE product_id = %d", $productId ) );
			} else {
				$placeholders = implode( ',', array_fill( 0, count( $keptSkuIds ), '%d' ) );
				$params       = array_merge( [ $productId ], $keptSkuIds );
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$skusTbl} WHERE product_id = %d AND id NOT IN ({$placeholders})",
						...$params
					)
				);
			}

			$wpdb->query( 'COMMIT' );
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}

		$persisted = $this->findById( $productId );
		if ( null === $persisted ) {
			throw new RuntimeException( 'Failed to reload saved product.' );
		}

		return $persisted;
	}

	public function delete( int $id ): bool {
		$wpdb        = $this->db();
		$productsTbl = $wpdb->prefix . 'gec_products';
		$skusTbl     = $wpdb->prefix . 'gec_skus';

		$wpdb->query( 'START TRANSACTION' );

		try {
			$wpdb->delete( $skusTbl, [ 'product_id' => $id ], [ '%d' ] );
			$deleted = $wpdb->delete( $productsTbl, [ 'id' => $id ], [ '%d' ] );
			if ( false === $deleted ) {
				throw new RuntimeException( 'Failed to delete product: ' . $wpdb->last_error );
			}
			$wpdb->query( 'COMMIT' );
			return $deleted > 0;
		} catch ( Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}
	}

	public function skuCodeExists( string $skuCode, ?int $excludeSkuId = null ): bool {
		$wpdb       = $this->db();
		$skusTbl    = $wpdb->prefix . 'gec_skus';
		$normalized = strtoupper( trim( $skuCode ) );

		if ( null !== $excludeSkuId ) {
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$skusTbl} WHERE sku_code = %s AND id != %d LIMIT 1",
					$normalized,
					$excludeSkuId
				)
			);
		} else {
			$found = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$skusTbl} WHERE sku_code = %s LIMIT 1", $normalized )
			);
		}

		return null !== $found;
	}

	public function slugExists( string $slug, ?int $excludeProductId = null ): bool {
		$wpdb        = $this->db();
		$productsTbl = $wpdb->prefix . 'gec_products';
		$normalized  = strtolower( trim( $slug ) );

		if ( null !== $excludeProductId ) {
			$found = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$productsTbl} WHERE slug = %s AND id != %d LIMIT 1",
					$normalized,
					$excludeProductId
				)
			);
		} else {
			$found = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$productsTbl} WHERE slug = %s LIMIT 1", $normalized )
			);
		}

		return null !== $found;
	}

	/**
	 * @param list<int> $productIds
	 * @return array<int, list<Sku>>
	 */
	private function findSkusForProducts( array $productIds ): array {
		if ( [] === $productIds ) {
			return [];
		}

		$wpdb         = $this->db();
		$skusTbl      = $wpdb->prefix . 'gec_skus';
		$placeholders = implode( ',', array_fill( 0, count( $productIds ), '%d' ) );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$skusTbl} WHERE product_id IN ({$placeholders}) ORDER BY id ASC",
				...$productIds
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return [];
		}

		$map = [];
		foreach ( $rows as $row ) {
			$pid          = (int) $row['product_id'];
			$map[ $pid ][] = new Sku(
				id: (int) $row['id'],
				productId: $pid,
				skuCode: (string) $row['sku_code'],
				attributes: SkuAttributes::fromJson( (string) $row['attributes_json'] ),
				price: Money::fromCents( (int) $row['price_cents'] ),
				availableQty: (int) $row['available_qty'],
				reservedQty: (int) $row['reserved_qty']
			);
		}

		return $map;
	}

	/**
	 * @param array<string, mixed> $row
	 * @param list<Sku>            $skus
	 */
	private function hydrateProduct( array $row, array $skus ): Product {
		$status = ProductStatus::tryFrom( (string) $row['status'] ) ?? ProductStatus::DRAFT;

		return new Product(
			id: (int) $row['id'],
			slug: (string) $row['slug'],
			title: (string) $row['title'],
			description: (string) $row['description'],
			status: $status,
			skus: $skus,
			createdAt: isset( $row['created_at'] ) ? (string) $row['created_at'] : null,
			updatedAt: isset( $row['updated_at'] ) ? (string) $row['updated_at'] : null
		);
	}
}
