<?php
/**
 * Cross-Module Catalog Lookup Port Interface.
 *
 * @package GeCommerce\Shared\Contracts
 */

declare(strict_types=1);

namespace GeCommerce\Shared\Contracts;

interface CatalogLookupInterface {

	public function findSkuById( int $skuId ): ?SkuSnapshot;

	public function findSkuByCode( string $skuCode ): ?SkuSnapshot;
}
