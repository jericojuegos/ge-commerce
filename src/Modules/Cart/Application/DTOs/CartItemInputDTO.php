<?php
/**
 * Input DTO for Cart Line Item Operations.
 *
 * @package GeCommerce\Modules\Cart\Application\DTOs
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Application\DTOs;

final class CartItemInputDTO {

	public function __construct(
		public readonly int $skuId,
		public readonly int $qty
	) {}
}
