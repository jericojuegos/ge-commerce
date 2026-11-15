<?php
/**
 * Use-case handler for retrieving a single Product by ID.
 *
 * @package GeCommerce\Modules\Catalog\Application\Handlers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Catalog\Application\Handlers;

use GeCommerce\Modules\Catalog\Domain\Contracts\ProductRepositoryInterface;
use GeCommerce\Modules\Catalog\Domain\Entities\Product;
use RuntimeException;

final class GetProductHandler {

	public function __construct(
		private readonly ProductRepositoryInterface $products
	) {}

	public function execute( int $id ): Product {
		$product = $this->products->findById( $id );
		if ( null === $product ) {
			throw new RuntimeException( sprintf( 'Product %d not found.', $id ) );
		}

		return $product;
	}
}
