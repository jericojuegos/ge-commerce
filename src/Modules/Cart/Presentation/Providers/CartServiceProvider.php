<?php
/**
 * ServiceProvider for the Cart, Tax & Shipping Module.
 *
 * @package GeCommerce\Modules\Cart\Presentation\Providers
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Presentation\Providers;

use GeCommerce\Core\Container;
use GeCommerce\Core\ServiceProvider;
use GeCommerce\Modules\Cart\Application\Handlers\CalculateCartTotalsHandler;
use GeCommerce\Modules\Cart\Application\Handlers\GetCartHandler;
use GeCommerce\Modules\Cart\Application\Handlers\MergeGuestCartHandler;
use GeCommerce\Modules\Cart\Application\Handlers\SaveZoneConfigHandler;
use GeCommerce\Modules\Cart\Application\Handlers\UpdateCartHandler;
use GeCommerce\Modules\Cart\Domain\Contracts\CartSessionRepositoryInterface;
use GeCommerce\Modules\Cart\Domain\Contracts\ZoneConfigRepositoryInterface;
use GeCommerce\Modules\Cart\Infrastructure\Repositories\WpdbCartSessionRepository;
use GeCommerce\Modules\Cart\Infrastructure\Repositories\WpZoneConfigRepository;
use GeCommerce\Modules\Cart\Presentation\Rest\CartRestController;
use GeCommerce\Shared\Contracts\CatalogLookupInterface;
use GeCommerce\Shared\Contracts\CouponEvaluatorInterface;

final class CartServiceProvider extends ServiceProvider {

	public function register( Container $container ): void {
		$container->singleton(
			CartSessionRepositoryInterface::class,
			static fn(): CartSessionRepositoryInterface => new WpdbCartSessionRepository()
		);

		$container->singleton(
			ZoneConfigRepositoryInterface::class,
			static fn(): ZoneConfigRepositoryInterface => new WpZoneConfigRepository()
		);

		$container->singleton(
			CalculateCartTotalsHandler::class,
			static fn( Container $c ): CalculateCartTotalsHandler => new CalculateCartTotalsHandler(
				$c->get( CouponEvaluatorInterface::class ),
				$c->get( ZoneConfigRepositoryInterface::class )
			)
		);

		$container->singleton(
			GetCartHandler::class,
			static fn( Container $c ): GetCartHandler => new GetCartHandler(
				$c->get( CartSessionRepositoryInterface::class ),
				$c->get( CatalogLookupInterface::class ),
				$c->get( CalculateCartTotalsHandler::class )
			)
		);

		$container->singleton(
			UpdateCartHandler::class,
			static fn( Container $c ): UpdateCartHandler => new UpdateCartHandler(
				$c->get( CartSessionRepositoryInterface::class ),
				$c->get( CatalogLookupInterface::class ),
				$c->get( CouponEvaluatorInterface::class ),
				$c->get( GetCartHandler::class ),
				$c->get( CalculateCartTotalsHandler::class )
			)
		);

		$container->singleton(
			MergeGuestCartHandler::class,
			static fn( Container $c ): MergeGuestCartHandler => new MergeGuestCartHandler(
				$c->get( CartSessionRepositoryInterface::class ),
				$c->get( CatalogLookupInterface::class ),
				$c->get( GetCartHandler::class ),
				$c->get( CalculateCartTotalsHandler::class )
			)
		);

		$container->singleton(
			SaveZoneConfigHandler::class,
			static fn( Container $c ): SaveZoneConfigHandler => new SaveZoneConfigHandler(
				$c->get( ZoneConfigRepositoryInterface::class )
			)
		);

		$container->singleton(
			CartRestController::class,
			static fn( Container $c ): CartRestController => new CartRestController(
				$c->get( GetCartHandler::class ),
				$c->get( UpdateCartHandler::class ),
				$c->get( MergeGuestCartHandler::class ),
				$c->get( CalculateCartTotalsHandler::class ),
				$c->get( ZoneConfigRepositoryInterface::class ),
				$c->get( SaveZoneConfigHandler::class )
			)
		);
	}

	public function boot( Container $container ): void {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		\add_action(
			'rest_api_init',
			[ $container->get( CartRestController::class ), 'registerRoutes' ]
		);

		\add_action(
			'wp_login',
			static function ( string $userLogin, mixed $user ) use ( $container ): void {
				if ( ! $user instanceof \WP_User || $user->ID <= 0 ) {
					return;
				}

				$cookieKey = CartRestController::COOKIE_NAME;
				$guestKey  = isset( $_COOKIE[ $cookieKey ] ) && is_string( $_COOKIE[ $cookieKey ] )
					? \sanitize_text_field( trim( $_COOKIE[ $cookieKey ] ) )
					: '';

				if ( '' === $guestKey || 1 !== preg_match( '/^[a-f0-9]{32,64}$/', $guestKey ) ) {
					return;
				}

				$mergeHandler = $container->get( MergeGuestCartHandler::class );
				$mergeHandler->execute( $guestKey, (int) $user->ID );

				if ( ! headers_sent() ) {
					setcookie(
						$cookieKey,
						'',
						[
							'expires'  => time() - 3600,
							'path'     => '/',
							'secure'   => \is_ssl(),
							'httponly' => true,
							'samesite' => 'Lax',
						]
					);
				}
			},
			10,
			2
		);
	}
}
