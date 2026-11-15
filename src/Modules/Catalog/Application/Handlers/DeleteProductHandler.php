<?php
/**
 * Use-case handler for deleting a Product by ID.
 *
 * @package GeCommerce\Modules\Catalog\Application\Handlers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Catalog\Application\Handlers;

use GeCommerce\Modules\Catalog\Domain\Contracts\ProductRepositoryInterface;
use RuntimeException;

final class DeleteProductHandler {

	public function __construct(
		private readonly ProductRepositoryInterface $products
	) {}

	public function execute( int $id ): bool {
		if ( null === $this->products->findById( $id ) ) {
			throw new RuntimeException( sprintf( 'Product %d not found.', $id ) );
		}

		return $this->products->delete( $id );
	}
}
