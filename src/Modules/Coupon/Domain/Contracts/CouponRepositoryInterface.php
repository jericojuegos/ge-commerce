<?php
/**
 * Coupon Repository Port Interface.
 *
 * @package GeCommerce\Modules\Coupon\Domain\Contracts
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Coupon\Domain\Contracts;

use GeCommerce\Modules\Coupon\Domain\Entities\Coupon;

interface CouponRepositoryInterface {

	public function findById( int $id ): ?Coupon;

	public function findByCode( string $code ): ?Coupon;

	/**
	 * @return list<Coupon>
	 */
	public function findAll( int $limit = 50, int $offset = 0 ): array;

	public function count(): int;

	public function save( Coupon $coupon ): Coupon;

	public function delete( int $id ): bool;

	public function codeExists( string $code, ?int $excludeId = null ): bool;

	public function incrementUsage( string $code ): void;
}
