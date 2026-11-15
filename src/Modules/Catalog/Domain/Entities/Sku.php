<?php
/**
 * Pure PHP SKU Entity.
 *
 * @package GeCommerce\Modules\Catalog\Domain\Entities
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Catalog\Domain\Entities;

use GeCommerce\Modules\Catalog\Domain\ValueObjects\SkuAttributes;
use GeCommerce\Shared\ValueObjects\Money;
use InvalidArgumentException;

final class Sku {

	private readonly string $skuCode;

	public function __construct(
		private readonly ?int $id,
		private readonly ?int $productId,
		string $skuCode,
		private readonly SkuAttributes $attributes,
		private readonly Money $price,
		private readonly int $availableQty,
		private readonly int $reservedQty = 0
	) {
		$cleanCode = strtoupper( trim( $skuCode ) );
		if ( '' === $cleanCode ) {
			throw new InvalidArgumentException( 'SKU code cannot be empty.' );
		}
		if ( strlen( $cleanCode ) > 100 ) {
			throw new InvalidArgumentException( 'SKU code cannot exceed 100 characters.' );
		}
		if ( $availableQty < 0 ) {
			throw new InvalidArgumentException( 'Available quantity cannot be negative.' );
		}
		if ( $reservedQty < 0 ) {
			throw new InvalidArgumentException( 'Reserved quantity cannot be negative.' );
		}

		$this->skuCode = $cleanCode;
	}

	public function id(): ?int {
		return $this->id;
	}

	public function productId(): ?int {
		return $this->productId;
	}

	public function skuCode(): string {
		return $this->skuCode;
	}

	public function attributes(): SkuAttributes {
		return $this->attributes;
	}

	public function price(): Money {
		return $this->price;
	}

	public function availableQty(): int {
		return $this->availableQty;
	}

	public function reservedQty(): int {
		return $this->reservedQty;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'id'            => $this->id,
			'product_id'    => $this->productId,
			'sku_code'      => $this->skuCode,
			'attributes'    => $this->attributes->toArray(),
			'price_cents'   => $this->price->cents(),
			'currency'      => $this->price->currency(),
			'available_qty' => $this->availableQty,
			'reserved_qty'  => $this->reservedQty,
		];
	}
}
