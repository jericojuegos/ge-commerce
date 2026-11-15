<?php
/**
 * Use-case handler for creating or updating a Product and its SKUs.
 *
 * @package GeCommerce\Modules\Catalog\Application\Handlers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Catalog\Application\Handlers;

use GeCommerce\Modules\Catalog\Application\DTOs\SaveProductDTO;
use GeCommerce\Modules\Catalog\Domain\Contracts\ProductRepositoryInterface;
use GeCommerce\Modules\Catalog\Domain\Entities\Product;
use GeCommerce\Modules\Catalog\Domain\Entities\Sku;
use GeCommerce\Modules\Catalog\Domain\ValueObjects\ProductStatus;
use GeCommerce\Modules\Catalog\Domain\ValueObjects\SkuAttributes;
use GeCommerce\Shared\ValueObjects\Money;
use InvalidArgumentException;
use RuntimeException;

final class SaveProductHandler {

	public function __construct(
		private readonly ProductRepositoryInterface $products
	) {}

	public function execute( SaveProductDTO $dto ): Product {
		if ( null !== $dto->id && null === $this->products->findById( $dto->id ) ) {
			throw new RuntimeException( sprintf( 'Product %d not found.', $dto->id ) );
		}

		$status = ProductStatus::tryFrom( $dto->status );
		if ( null === $status ) {
			throw new InvalidArgumentException( sprintf( 'Invalid product status "%s".', $dto->status ) );
		}

		$skus = [];
		foreach ( $dto->skus as $skuDto ) {
			$skus[] = new Sku(
				id: $skuDto->id,
				productId: $dto->id,
				skuCode: $skuDto->skuCode,
				attributes: new SkuAttributes( $skuDto->attributes ),
				price: Money::fromCents( $skuDto->priceCents ),
				availableQty: $skuDto->availableQty,
				reservedQty: $skuDto->reservedQty
			);
		}

		$product = new Product(
			id: $dto->id,
			slug: $dto->slug,
			title: $dto->title,
			description: $dto->description,
			status: $status,
			skus: $skus
		);

		if ( $this->products->slugExists( $product->slug(), $dto->id ) ) {
			throw new InvalidArgumentException( sprintf( 'Product slug "%s" already exists.', $product->slug() ) );
		}

		foreach ( $product->skus() as $sku ) {
			if ( $this->products->skuCodeExists( $sku->skuCode(), $sku->id() ) ) {
				throw new InvalidArgumentException( sprintf( 'SKU code "%s" already exists.', $sku->skuCode() ) );
			}
		}

		return $this->products->save( $product );
	}
}
