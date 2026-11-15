<?php
/**
 * Cart Session Repository Port Interface.
 *
 * @package GeCommerce\Modules\Cart\Domain\Contracts
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Domain\Contracts;

use GeCommerce\Modules\Cart\Domain\Entities\Cart;

interface CartSessionRepositoryInterface {

	public function findBySessionKey( string $sessionKey, ?string $nowUtc = null ): ?Cart;

	public function findByUserId( int $userId, ?string $nowUtc = null ): ?Cart;

	public function save( Cart $cart ): Cart;

	public function deleteBySessionKey( string $sessionKey ): bool;

	public function purgeExpired( ?string $nowUtc = null ): int;
}
