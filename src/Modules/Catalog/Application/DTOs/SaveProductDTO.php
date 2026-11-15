<?php
/**
 * Input DTO for creating or updating a Product with SKUs.
 *
 * @package GeCommerce\Modules\Catalog\Application\DTOs
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Catalog\Application\DTOs;

final class SaveProductDTO {

	/**
	 * @param list<SkuInputDTO> $skus
	 */
	public function __construct(
		public readonly ?int $id,
		public readonly string $slug,
		public readonly string $title,
		public readonly string $description,
		public readonly string $status,
		public readonly array $skus = []
	) {}
}
