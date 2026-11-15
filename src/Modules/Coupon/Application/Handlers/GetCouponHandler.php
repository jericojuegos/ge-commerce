<?php
/**
 * Use-Case Handler for Retrieving a Single Coupon by ID.
 *
 * @package GeCommerce\Modules\Coupon\Application\Handlers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Coupon\Application\Handlers;

use GeCommerce\Modules\Coupon\Domain\Contracts\CouponRepositoryInterface;
use GeCommerce\Modules\Coupon\Domain\Entities\Coupon;
use RuntimeException;

final class GetCouponHandler {

	public function __construct(
		private readonly CouponRepositoryInterface $repository
	) {}

	public function execute( int $id ): Coupon {
		$coupon = $this->repository->findById( $id );
		if ( null === $coupon ) {
			throw new RuntimeException( sprintf( 'Coupon %d not found.', $id ) );
		}

		return $coupon;
	}
}
