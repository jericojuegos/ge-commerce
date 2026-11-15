<?php
/**
 * Infrastructure Adapter Implementing Cross-Module CatalogLookupInterface.
 *
 * @package GeCommerce\Modules\Catalog\Infrastructure\Adapters
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Catalog\Infrastructure\Adapters;

use GeCommerce\Shared\Contracts\CatalogLookupInterface;
use GeCommerce\Shared\Contracts\SkuSnapshot;
use GeCommerce\Shared\ValueObjects\Money;
use RuntimeException;

final class CatalogLookupAdapter implements CatalogLookupInterface {

	private function db(): \wpdb {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! $wpdb instanceof \wpdb ) {
			throw new RuntimeException( 'WordPress $wpdb instance is not available.' );
		}

		return $wpdb;
	}

	public function findSkuById( int $skuId ): ?SkuSnapshot {
		if ( $skuId <= 0 ) {
			return null;
		}

		$wpdb        = $this->db();
		$skusTbl     = $wpdb->prefix . 'gec_skus';
		$productsTbl = $wpdb->prefix . 'gec_products';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.id AS sku_id, s.product_id, s.sku_code, s.price_cents, s.available_qty, p.title AS product_title, p.status AS product_status
				 FROM {$skusTbl} s
				 INNER JOIN {$productsTbl} p ON p.id = s.product_id
				 WHERE s.id = %d
				 LIMIT 1",
				$skuId
			),
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrateSnapshot( $row ) : null;
	}

	public function findSkuByCode( string $skuCode ): ?SkuSnapshot {
		$normalized = strtoupper( trim( $skuCode ) );
		if ( '' === $normalized ) {
			return null;
		}

		$wpdb        = $this->db();
		$skusTbl     = $wpdb->prefix . 'gec_skus';
		$productsTbl = $wpdb->prefix . 'gec_products';

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.id AS sku_id, s.product_id, s.sku_code, s.price_cents, s.available_qty, p.title AS product_title, p.status AS product_status
				 FROM {$skusTbl} s
				 INNER JOIN {$productsTbl} p ON p.id = s.product_id
				 WHERE s.sku_code = %s
				 LIMIT 1",
				$normalized
			),
			ARRAY_A
		);

		return is_array( $row ) ? $this->hydrateSnapshot( $row ) : null;
	}

	/**
	 * @param array<string, mixed> $row
	 */
	private function hydrateSnapshot( array $row ): SkuSnapshot {
		return new SkuSnapshot(
			skuId: (int) $row['sku_id'],
			productId: (int) $row['product_id'],
			skuCode: (string) $row['sku_code'],
			productTitle: (string) $row['product_title'],
			price: Money::fromCents( max( 0, (int) $row['price_cents'] ) ),
			availableQty: max( 0, (int) $row['available_qty'] ),
			isPublished: 'published' === (string) $row['product_status']
		);
	}
}
