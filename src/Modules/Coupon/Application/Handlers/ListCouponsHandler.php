<?php
/**
 * Use-Case Handler for Listing Coupons.
 *
 * @package GeCommerce\Modules\Coupon\Application\Handlers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Coupon\Application\Handlers;

use GeCommerce\Modules\Coupon\Domain\Contracts\CouponRepositoryInterface;
use GeCommerce\Modules\Coupon\Domain\Entities\Coupon;

final class ListCouponsHandler {

	public function __construct(
		private readonly CouponRepositoryInterface $repository
	) {}

	/**
	 * @return array{items: list<Coupon>, total: int}
	 */
	public function execute( int $page = 1, int $perPage = 20 ): array {
		$page    = max( 1, $page );
		$perPage = min( 100, max( 1, $perPage ) );
		$offset  = ( $page - 1 ) * $perPage;

		return [
			'items' => $this->repository->findAll( $perPage, $offset ),
			'total' => $this->repository->count(),
		];
	}
}
