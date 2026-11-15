<?php
/**
 * Input DTO for Cart & Tax Quote Simulation.
 *
 * @package GeCommerce\Modules\Cart\Application\DTOs
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Application\DTOs;

final class QuotePreviewDTO {

	public function __construct(
		public readonly int $subtotalCents,
		public readonly ?string $couponCode = null,
		public readonly string $country = 'US',
		public readonly string $region = 'CA',
		public readonly string $shippingMethod = 'standard',
		public readonly ?string $nowUtc = null
	) {}
}
