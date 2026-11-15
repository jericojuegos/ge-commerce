<?php
/**
 * Use-case handler for listing Products with pagination and status filtering.
 *
 * @package GeCommerce\Modules\Catalog\Application\Handlers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Catalog\Application\Handlers;

use GeCommerce\Modules\Catalog\Domain\Contracts\ProductRepositoryInterface;
use GeCommerce\Modules\Catalog\Domain\Entities\Product;
use GeCommerce\Modules\Catalog\Domain\ValueObjects\ProductStatus;
use InvalidArgumentException;

final class ListProductsHandler {

	public function __construct(
		private readonly ProductRepositoryInterface $products
	) {}

	/**
	 * @return array{items: list<Product>, total: int}
	 */
	public function execute( int $page = 1, int $perPage = 20, ?string $status = null ): array {
		$cleanPage    = max( 1, $page );
		$cleanPerPage = min( 100, max( 1, $perPage ) );
		$offset       = ( $cleanPage - 1 ) * $cleanPerPage;

		$cleanStatus = null;
		if ( null !== $status && '' !== $status ) {
			$enum = ProductStatus::tryFrom( $status );
			if ( null === $enum ) {
				throw new InvalidArgumentException( sprintf( 'Invalid product status filter "%s".', $status ) );
			}
			$cleanStatus = $enum->value;
		}

		return [
			'items' => $this->products->findAll( $cleanPerPage, $offset, $cleanStatus ),
			'total' => $this->products->count( $cleanStatus ),
		];
	}
}
