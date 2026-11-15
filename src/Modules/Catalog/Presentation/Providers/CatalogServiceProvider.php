<?php
/**
 * ServiceProvider for the Catalog & SKUs Module.
 *
 * @package GeCommerce\Modules\Catalog\Presentation\Providers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Catalog\Presentation\Providers;

use GeCommerce\Core\Container;
use GeCommerce\Core\ServiceProvider;
use GeCommerce\Modules\Catalog\Application\Handlers\DeleteProductHandler;
use GeCommerce\Modules\Catalog\Application\Handlers\GetProductHandler;
use GeCommerce\Modules\Catalog\Application\Handlers\ListProductsHandler;
use GeCommerce\Modules\Catalog\Application\Handlers\SaveProductHandler;
use GeCommerce\Modules\Catalog\Domain\Contracts\ProductRepositoryInterface;
use GeCommerce\Modules\Catalog\Infrastructure\Repositories\WpdbProductRepository;
use GeCommerce\Modules\Catalog\Presentation\Rest\CatalogRestController;

final class CatalogServiceProvider extends ServiceProvider {

	public function register( Container $container ): void {
		$container->singleton(
			ProductRepositoryInterface::class,
			static fn(): ProductRepositoryInterface => new WpdbProductRepository()
		);

		$container->singleton(
			ListProductsHandler::class,
			static fn( Container $c ): ListProductsHandler => new ListProductsHandler(
				$c->get( ProductRepositoryInterface::class )
			)
		);

		$container->singleton(
			GetProductHandler::class,
			static fn( Container $c ): GetProductHandler => new GetProductHandler(
				$c->get( ProductRepositoryInterface::class )
			)
		);

		$container->singleton(
			SaveProductHandler::class,
			static fn( Container $c ): SaveProductHandler => new SaveProductHandler(
				$c->get( ProductRepositoryInterface::class )
			)
		);

		$container->singleton(
			DeleteProductHandler::class,
			static fn( Container $c ): DeleteProductHandler => new DeleteProductHandler(
				$c->get( ProductRepositoryInterface::class )
			)
		);

		$container->singleton(
			CatalogRestController::class,
			static fn( Container $c ): CatalogRestController => new CatalogRestController(
				$c->get( ListProductsHandler::class ),
				$c->get( GetProductHandler::class ),
				$c->get( SaveProductHandler::class ),
				$c->get( DeleteProductHandler::class )
			)
		);
	}

	public function boot( Container $container ): void {
		if ( function_exists( 'add_action' ) ) {
			\add_action(
				'rest_api_init',
				[ $container->get( CatalogRestController::class ), 'registerRoutes' ]
			);
		}
	}
}
