<?php
/**
 * Product Repository Port Interface.
 *
 * @package GeCommerce\Modules\Catalog\Domain\Contracts
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Catalog\Domain\Contracts;

use GeCommerce\Modules\Catalog\Domain\Entities\Product;

interface ProductRepositoryInterface {

	public function findById( int $id ): ?Product;

	public function findBySlug( string $slug ): ?Product;

	/**
	 * @return list<Product>
	 */
	public function findAll( int $limit = 50, int $offset = 0, ?string $status = null ): array;

	public function count( ?string $status = null ): int;

	public function save( Product $product ): Product;

	public function delete( int $id ): bool;

	public function skuCodeExists( string $skuCode, ?int $excludeSkuId = null ): bool;

	public function slugExists( string $slug, ?int $excludeProductId = null ): bool;
}
