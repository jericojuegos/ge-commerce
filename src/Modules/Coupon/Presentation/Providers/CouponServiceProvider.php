<?php
/**
 * ServiceProvider for the Coupons Module.
 *
 * @package GeCommerce\Modules\Coupon\Presentation\Providers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Coupon\Presentation\Providers;

use GeCommerce\Core\Container;
use GeCommerce\Core\ServiceProvider;
use GeCommerce\Modules\Coupon\Application\Handlers\DeleteCouponHandler;
use GeCommerce\Modules\Coupon\Application\Handlers\GetCouponHandler;
use GeCommerce\Modules\Coupon\Application\Handlers\ListCouponsHandler;
use GeCommerce\Modules\Coupon\Application\Handlers\SaveCouponHandler;
use GeCommerce\Modules\Coupon\Application\Services\CouponEvaluator;
use GeCommerce\Modules\Coupon\Domain\Contracts\CouponRepositoryInterface;
use GeCommerce\Modules\Coupon\Infrastructure\Repositories\WpdbCouponRepository;
use GeCommerce\Modules\Coupon\Presentation\Rest\CouponRestController;
use GeCommerce\Shared\Contracts\CouponEvaluatorInterface;

final class CouponServiceProvider extends ServiceProvider {

	public function register( Container $container ): void {
		$container->singleton(
			CouponRepositoryInterface::class,
			static fn(): CouponRepositoryInterface => new WpdbCouponRepository()
		);

		$container->singleton(
			CouponEvaluatorInterface::class,
			static fn( Container $c ): CouponEvaluatorInterface => new CouponEvaluator(
				$c->get( CouponRepositoryInterface::class )
			)
		);

		$container->singleton(
			ListCouponsHandler::class,
			static fn( Container $c ): ListCouponsHandler => new ListCouponsHandler(
				$c->get( CouponRepositoryInterface::class )
			)
		);

		$container->singleton(
			GetCouponHandler::class,
			static fn( Container $c ): GetCouponHandler => new GetCouponHandler(
				$c->get( CouponRepositoryInterface::class )
			)
		);

		$container->singleton(
			SaveCouponHandler::class,
			static fn( Container $c ): SaveCouponHandler => new SaveCouponHandler(
				$c->get( CouponRepositoryInterface::class )
			)
		);

		$container->singleton(
			DeleteCouponHandler::class,
			static fn( Container $c ): DeleteCouponHandler => new DeleteCouponHandler(
				$c->get( CouponRepositoryInterface::class )
			)
		);

		$container->singleton(
			CouponRestController::class,
			static fn( Container $c ): CouponRestController => new CouponRestController(
				$c->get( ListCouponsHandler::class ),
				$c->get( GetCouponHandler::class ),
				$c->get( SaveCouponHandler::class ),
				$c->get( DeleteCouponHandler::class )
			)
		);
	}

	public function boot( Container $container ): void {
		if ( function_exists( 'add_action' ) ) {
			\add_action(
				'rest_api_init',
				[ $container->get( CouponRestController::class ), 'registerRoutes' ]
			);
		}
	}
}
