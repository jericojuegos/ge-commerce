<?php
/**
 * Product lifecycle status enum.
 *
 * @package GeCommerce\Modules\Catalog\Domain\ValueObjects
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Catalog\Domain\ValueObjects;

enum ProductStatus: string {

	case DRAFT     = 'draft';
	case PUBLISHED = 'published';
	case ARCHIVED  = 'archived';
}
