<?php
/**
 * Immutable Cross-Module SKU Snapshot DTO.
 *
 * @package GeCommerce\Shared\Contracts
 */

declare(strict_types=1);

namespace GeCommerce\Shared\Contracts;

use GeCommerce\Shared\ValueObjects\Money;

final class SkuSnapshot {

	public function __construct(
		public readonly int $skuId,
		public readonly int $productId,
		public readonly string $skuCode,
		public readonly string $productTitle,
		public readonly Money $price,
		public readonly int $availableQty,
		public readonly bool $isPublished
	) {}
}
