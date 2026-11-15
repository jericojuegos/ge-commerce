<?php
/**
 * Input DTO for creating or updating a SKU.
 *
 * @package GeCommerce\Modules\Catalog\Application\DTOs
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Catalog\Application\DTOs;

final class SkuInputDTO {

	/**
	 * @param array<string, scalar> $attributes
	 */
	public function __construct(
		public readonly ?int $id,
		public readonly string $skuCode,
		public readonly array $attributes,
		public readonly int $priceCents,
		public readonly int $availableQty,
		public readonly int $reservedQty = 0
	) {}
}
