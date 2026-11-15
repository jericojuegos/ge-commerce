<?php
/**
 * Pure PHP Cart Line Item Entity.
 *
 * @package GeCommerce\Modules\Cart\Domain\Entities
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Domain\Entities;

use GeCommerce\Shared\ValueObjects\Money;
use InvalidArgumentException;

final class CartItem {

	private readonly string $skuCode;

	private readonly string $title;

	public function __construct(
		private readonly int $skuId,
		string $skuCode,
		string $title,
		private readonly Money $unitPrice,
		private readonly int $qty
	) {
		if ( $skuId <= 0 ) {
			throw new InvalidArgumentException( 'Cart item SKU ID must be a positive integer.' );
		}
		$cleanCode  = strtoupper( trim( $skuCode ) );
		$cleanTitle = trim( $title );

		if ( '' === $cleanCode ) {
			throw new InvalidArgumentException( 'Cart item SKU code cannot be empty.' );
		}
		if ( $qty <= 0 ) {
			throw new InvalidArgumentException( 'Cart item quantity must be greater than zero.' );
		}

		$this->skuCode = $cleanCode;
		$this->title   = '' !== $cleanTitle ? $cleanTitle : $cleanCode;
	}

	public function skuId(): int {
		return $this->skuId;
	}

	public function skuCode(): string {
		return $this->skuCode;
	}

	public function title(): string {
		return $this->title;
	}

	public function unitPrice(): Money {
		return $this->unitPrice;
	}

	public function qty(): int {
		return $this->qty;
	}

	public function lineTotal(): Money {
		return $this->unitPrice->multiply( $this->qty );
	}

	public function withQty( int $qty ): self {
		return new self(
			skuId: $this->skuId,
			skuCode: $this->skuCode,
			title: $this->title,
			unitPrice: $this->unitPrice,
			qty: $qty
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'sku_id'           => $this->skuId,
			'sku_code'         => $this->skuCode,
			'title'            => $this->title,
			'unit_price_cents' => $this->unitPrice->cents(),
			'qty'              => $this->qty,
			'line_total_cents' => $this->lineTotal()->cents(),
			'currency'         => $this->unitPrice->currency(),
		];
	}
}
