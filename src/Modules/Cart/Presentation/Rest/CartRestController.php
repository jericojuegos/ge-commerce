<?php
/**
 * REST API Controller for Session Cart, Tax/Shipping Zones & Quote Simulator.
 *
 * @package GeCommerce\Modules\Cart\Presentation\Rest
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Cart\Presentation\Rest;

use GeCommerce\Modules\Cart\Application\DTOs\CartItemInputDTO;
use GeCommerce\Modules\Cart\Application\DTOs\QuotePreviewDTO;
use GeCommerce\Modules\Cart\Application\Handlers\CalculateCartTotalsHandler;
use GeCommerce\Modules\Cart\Application\Handlers\GetCartHandler;
use GeCommerce\Modules\Cart\Application\Handlers\MergeGuestCartHandler;
use GeCommerce\Modules\Cart\Application\Handlers\SaveZoneConfigHandler;
use GeCommerce\Modules\Cart\Application\Handlers\UpdateCartHandler;
use GeCommerce\Modules\Cart\Domain\Contracts\ZoneConfigRepositoryInterface;
use GeCommerce\Modules\Cart\Domain\ValueObjects\ShippingZone;
use GeCommerce\Modules\Cart\Domain\ValueObjects\TaxZone;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class CartRestController {

	private const NAMESPACE   = 'ge-commerce/v1';
	public const COOKIE_NAME  = 'gec_session_key';

	public function __construct(
		private readonly GetCartHandler $getCartHandler,
		private readonly UpdateCartHandler $updateCartHandler,
		private readonly MergeGuestCartHandler $mergeGuestCartHandler,
		private readonly CalculateCartTotalsHandler $totalsHandler,
		private readonly ZoneConfigRepositoryInterface $zoneRepo,
		private readonly SaveZoneConfigHandler $saveZoneHandler
	) {}

	public function registerRoutes(): void {
		\register_rest_route(
			self::NAMESPACE,
			'/tax-shipping-zones',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getZones' ],
					'permission_callback' => [ $this, 'canManageStoreConfig' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'saveZones' ],
					'permission_callback' => [ $this, 'canManageStoreConfig' ],
				],
			]
		);

		\register_rest_route(
			self::NAMESPACE,
			'/cart/quote-preview',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'quotePreview' ],
					'permission_callback' => [ $this, 'canManageStoreConfig' ],
				],
			]
		);

		\register_rest_route(
			self::NAMESPACE,
			'/cart',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getCart' ],
					'permission_callback' => [ $this, 'canAccessCartSession' ],
				],
			]
		);

		\register_rest_route(
			self::NAMESPACE,
			'/cart/items',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'addItem' ],
					'permission_callback' => [ $this, 'canAccessCartSession' ],
				],
			]
		);

		\register_rest_route(
			self::NAMESPACE,
			'/cart/items/(?P<sku_id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'updateItemQty' ],
					'permission_callback' => [ $this, 'canAccessCartSession' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'removeItem' ],
					'permission_callback' => [ $this, 'canAccessCartSession' ],
				],
			]
		);

		\register_rest_route(
			self::NAMESPACE,
			'/cart/coupon',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'applyCoupon' ],
					'permission_callback' => [ $this, 'canAccessCartSession' ],
				],
			]
		);

		\register_rest_route(
			self::NAMESPACE,
			'/cart/destination',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'setDestination' ],
					'permission_callback' => [ $this, 'canAccessCartSession' ],
				],
			]
		);

		\register_rest_route(
			self::NAMESPACE,
			'/cart/merge',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'mergeGuestCart' ],
					'permission_callback' => [ $this, 'canMergeCart' ],
				],
			]
		);
	}

	public function canManageStoreConfig( WP_REST_Request $request ): bool|WP_Error {
		if ( \current_user_can( 'manage_options' ) || \current_user_can( 'manage_ge_commerce' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			\__( 'You do not have permission to manage tax & shipping zones.', 'ge-commerce' ),
			[ 'status' => 403 ]
		);
	}

	public function canMergeCart( WP_REST_Request $request ): bool|WP_Error {
		if ( ! \is_user_logged_in() || \get_current_user_id() <= 0 ) {
			return new WP_Error(
				'rest_unauthorized',
				\__( 'You must be logged in to merge a guest cart.', 'ge-commerce' ),
				[ 'status' => 401 ]
			);
		}

		return true;
	}

	public function canAccessCartSession( WP_REST_Request $request ): bool|WP_Error {
		if ( \is_user_logged_in() ) {
			return true;
		}

		$provided = $this->readGuestSessionToken( $request );
		if ( null !== $provided && ! $this->isValidSessionToken( $provided ) ) {
			return new WP_Error(
				'invalid_cart_session',
				\__( 'Malformed cart session key.', 'ge-commerce' ),
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	public function getZones( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response(
			[
				'ok'   => true,
				'data' => [
					'tax_zones'      => array_map( static fn( TaxZone $z ): array => $z->toArray(), $this->zoneRepo->getTaxZones() ),
					'shipping_zones' => array_map( static fn( ShippingZone $z ): array => $z->toArray(), $this->zoneRepo->getShippingZones() ),
				],
			],
			200
		);
	}

	public function saveZones( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$rawTax      = is_array( $params['tax_zones'] ?? null ) ? $params['tax_zones'] : [];
		$rawShipping = is_array( $params['shipping_zones'] ?? null ) ? $params['shipping_zones'] : [];

		$cleanTax = [];
		foreach ( $rawTax as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$cleanTax[] = [
				'country'  => \sanitize_text_field( (string) ( $row['country'] ?? '' ) ),
				'region'   => \sanitize_text_field( (string) ( $row['region'] ?? '*' ) ),
				'rate_bps' => (int) ( $row['rate_bps'] ?? 0 ),
				'label'    => \sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
			];
		}

		$cleanShipping = [];
		foreach ( $rawShipping as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$cleanShipping[] = [
				'country'         => \sanitize_text_field( (string) ( $row['country'] ?? '' ) ),
				'region'          => \sanitize_text_field( (string) ( $row['region'] ?? '*' ) ),
				'method'          => \sanitize_text_field( (string) ( $row['method'] ?? 'standard' ) ),
				'flat_rate_cents' => (int) ( $row['flat_rate_cents'] ?? 0 ),
				'free_over_cents' => isset( $row['free_over_cents'] ) && '' !== $row['free_over_cents'] && null !== $row['free_over_cents']
					? (int) $row['free_over_cents']
					: null,
				'label'           => \sanitize_text_field( (string) ( $row['label'] ?? '' ) ),
			];
		}

		try {
			$saved = $this->saveZoneHandler->execute( $cleanTax, $cleanShipping );
			return new WP_REST_Response(
				[
					'ok'   => true,
					'data' => [
						'tax_zones'      => array_map( static fn( TaxZone $z ): array => $z->toArray(), $saved['tax_zones'] ),
						'shipping_zones' => array_map( static fn( ShippingZone $z ): array => $z->toArray(), $saved['shipping_zones'] ),
					],
				],
				200
			);
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'zone_validation_error', \esc_html( $e->getMessage() ), [ 'status' => 422 ] );
		} catch ( Throwable $e ) {
			return new WP_Error( 'zone_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}

	public function quotePreview( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$subtotalCents  = (int) ( $params['subtotal_cents'] ?? 0 );
		$couponCode     = isset( $params['coupon_code'] ) && '' !== trim( (string) $params['coupon_code'] )
			? \sanitize_text_field( (string) $params['coupon_code'] )
			: null;
		$country        = \sanitize_text_field( (string) ( $params['country'] ?? 'US' ) );
		$region         = \sanitize_text_field( (string) ( $params['region'] ?? 'CA' ) );
		$shippingMethod = \sanitize_text_field( (string) ( $params['shipping_method'] ?? 'standard' ) );

		try {
			$totals = $this->totalsHandler->forPreview(
				new QuotePreviewDTO(
					subtotalCents: $subtotalCents,
					couponCode: $couponCode,
					country: $country,
					region: $region,
					shippingMethod: $shippingMethod
				)
			);

			return new WP_REST_Response( [ 'ok' => true, 'data' => $totals->toArray() ], 200 );
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'quote_validation_error', \esc_html( $e->getMessage() ), [ 'status' => 422 ] );
		} catch ( Throwable $e ) {
			return new WP_Error( 'quote_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}

	public function getCart( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		[ $sessionKey, $userId ] = $this->resolveSessionContext( $request );

		try {
			$res = $this->getCartHandler->execute( $sessionKey, $userId );
			return $this->cartResponse( $res, $userId, $sessionKey );
		} catch ( Throwable $e ) {
			return new WP_Error( 'cart_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}

	public function addItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		[ $sessionKey, $userId ] = $this->resolveSessionContext( $request );
		$params                  = $this->readBody( $request );
		$skuId                   = \absint( $params['sku_id'] ?? 0 );
		$qty                     = (int) ( $params['qty'] ?? 1 );

		try {
			$res = $this->updateCartHandler->addItem( $sessionKey, $userId, new CartItemInputDTO( $skuId, $qty ) );
			return $this->cartResponse( $res, $userId, $sessionKey );
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'cart_validation_error', \esc_html( $e->getMessage() ), [ 'status' => 422 ] );
		} catch ( RuntimeException $e ) {
			return new WP_Error( 'cart_not_found', \esc_html( $e->getMessage() ), [ 'status' => 404 ] );
		} catch ( Throwable $e ) {
			return new WP_Error( 'cart_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}

	public function updateItemQty( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		[ $sessionKey, $userId ] = $this->resolveSessionContext( $request );
		$skuId                   = \absint( $request->get_param( 'sku_id' ) );
		$params                  = $this->readBody( $request );
		$qty                     = (int) ( $params['qty'] ?? 0 );

		try {
			$res = $this->updateCartHandler->setItemQty( $sessionKey, $userId, new CartItemInputDTO( $skuId, $qty ) );
			return $this->cartResponse( $res, $userId, $sessionKey );
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'cart_validation_error', \esc_html( $e->getMessage() ), [ 'status' => 422 ] );
		} catch ( RuntimeException $e ) {
			return new WP_Error( 'cart_not_found', \esc_html( $e->getMessage() ), [ 'status' => 404 ] );
		} catch ( Throwable $e ) {
			return new WP_Error( 'cart_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}

	public function removeItem( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		[ $sessionKey, $userId ] = $this->resolveSessionContext( $request );
		$skuId                   = \absint( $request->get_param( 'sku_id' ) );

		try {
			$res = $this->updateCartHandler->removeItem( $sessionKey, $userId, $skuId );
			return $this->cartResponse( $res, $userId, $sessionKey );
		} catch ( Throwable $e ) {
			return new WP_Error( 'cart_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}

	public function applyCoupon( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		[ $sessionKey, $userId ] = $this->resolveSessionContext( $request );
		$params                  = $this->readBody( $request );
		$rawCode                 = $params['code'] ?? null;
		$code                    = is_string( $rawCode ) ? \sanitize_text_field( $rawCode ) : null;

		try {
			$res = $this->updateCartHandler->applyCoupon( $sessionKey, $userId, $code );
			return $this->cartResponse( $res, $userId, $sessionKey );
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'coupon_validation_error', \esc_html( $e->getMessage() ), [ 'status' => 422 ] );
		} catch ( Throwable $e ) {
			return new WP_Error( 'cart_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}

	public function setDestination( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		[ $sessionKey, $userId ] = $this->resolveSessionContext( $request );
		$params                  = $this->readBody( $request );
		$country                 = \sanitize_text_field( (string) ( $params['country'] ?? 'US' ) );
		$region                  = \sanitize_text_field( (string) ( $params['region'] ?? '*' ) );
		$shippingMethod          = \sanitize_text_field( (string) ( $params['shipping_method'] ?? 'standard' ) );

		try {
			$res = $this->updateCartHandler->setDestination( $sessionKey, $userId, $country, $region, $shippingMethod );
			return $this->cartResponse( $res, $userId, $sessionKey );
		} catch ( Throwable $e ) {
			return new WP_Error( 'cart_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}

	public function mergeGuestCart( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$userId         = \get_current_user_id();
		$params         = $this->readBody( $request );
		$guestSession   = $this->readGuestSessionToken( $request );
		$bodySessionKey = isset( $params['guest_session_key'] ) ? \sanitize_text_field( (string) $params['guest_session_key'] ) : '';

		$keyToMerge = $guestSession ?? $bodySessionKey;
		if ( '' !== $keyToMerge && ! $this->isValidSessionToken( $keyToMerge ) ) {
			return new WP_Error( 'invalid_cart_session', \__( 'Malformed guest session key.', 'ge-commerce' ), [ 'status' => 422 ] );
		}

		try {
			$res = $this->mergeGuestCartHandler->execute( $keyToMerge, $userId );
			$this->clearGuestCookie();
			return $this->cartResponse( $res, $userId, $res['cart']->sessionKey() );
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'cart_merge_error', \esc_html( $e->getMessage() ), [ 'status' => 422 ] );
		} catch ( Throwable $e ) {
			return new WP_Error( 'cart_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}

	/**
	 * @return array{0: string, 1: int}
	 */
	private function resolveSessionContext( WP_REST_Request $request ): array {
		$userId = \get_current_user_id();
		if ( $userId > 0 ) {
			return [ 'user_' . $userId, $userId ];
		}

		$token = $this->readGuestSessionToken( $request );
		if ( null === $token || ! $this->isValidSessionToken( $token ) ) {
			$token = bin2hex( random_bytes( 24 ) );
			$this->setGuestCookie( $token );
		}

		return [ $token, 0 ];
	}

	private function readGuestSessionToken( WP_REST_Request $request ): ?string {
		$header = $request->get_header( 'X-GEC-Session' );
		if ( is_string( $header ) && '' !== trim( $header ) ) {
			return \sanitize_text_field( trim( $header ) );
		}

		if ( isset( $_COOKIE[ self::COOKIE_NAME ] ) && is_string( $_COOKIE[ self::COOKIE_NAME ] ) && '' !== trim( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			return \sanitize_text_field( trim( $_COOKIE[ self::COOKIE_NAME ] ) );
		}

		return null;
	}

	private function isValidSessionToken( string $token ): bool {
		return 1 === preg_match( '/^[a-f0-9]{32,64}$/', $token );
	}

	private function setGuestCookie( string $token ): void {
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_NAME,
				$token,
				[
					'expires'  => time() + ( 7 * 86400 ),
					'path'     => '/',
					'secure'   => \is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				]
			);
		}
	}

	private function clearGuestCookie(): void {
		if ( ! headers_sent() ) {
			setcookie(
				self::COOKIE_NAME,
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
	}

	/**
	 * @return array<string, mixed>
	 */
	private function readBody( WP_REST_Request $request ): array {
		$params = $request->get_json_params();
		if ( is_array( $params ) ) {
			return $params;
		}
		$fallback = $request->get_params();
		return is_array( $fallback ) ? $fallback : [];
	}

	/**
	 * @param array{cart: \GeCommerce\Modules\Cart\Domain\Entities\Cart, totals: \GeCommerce\Modules\Cart\Domain\ValueObjects\CartTotals} $result
	 */
	private function cartResponse( array $result, int $userId, string $sessionKey ): WP_REST_Response {
		$response = new WP_REST_Response(
			[
				'ok'   => true,
				'data' => [
					'cart'   => $result['cart']->toArray(),
					'totals' => $result['totals']->toArray(),
				],
			],
			200
		);

		if ( 0 === $userId ) {
			$response->header( 'X-GEC-Session', $sessionKey );
		}

		return $response;
	}
}
