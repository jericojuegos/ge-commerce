<?php
/**
 * Use-Case Handler for Deleting a Coupon by ID.
 *
 * @package GeCommerce\Modules\Coupon\Application\Handlers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Coupon\Application\Handlers;

use GeCommerce\Modules\Coupon\Domain\Contracts\CouponRepositoryInterface;
use RuntimeException;

final class DeleteCouponHandler {

	public function __construct(
		private readonly CouponRepositoryInterface $repository
	) {}

	public function execute( int $id ): bool {
		if ( null === $this->repository->findById( $id ) ) {
			throw new RuntimeException( sprintf( 'Coupon %d not found.', $id ) );
		}

		return $this->repository->delete( $id );
	}
}
