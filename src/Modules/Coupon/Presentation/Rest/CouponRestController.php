<?php
/**
 * REST API Controller for Coupons (`/wp-json/ge-commerce/v1/coupons`).
 *
 * @package GeCommerce\Modules\Coupon\Presentation\Rest
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Coupon\Presentation\Rest;

use GeCommerce\Modules\Coupon\Application\DTOs\SaveCouponDTO;
use GeCommerce\Modules\Coupon\Application\Handlers\DeleteCouponHandler;
use GeCommerce\Modules\Coupon\Application\Handlers\GetCouponHandler;
use GeCommerce\Modules\Coupon\Application\Handlers\ListCouponsHandler;
use GeCommerce\Modules\Coupon\Application\Handlers\SaveCouponHandler;
use GeCommerce\Modules\Coupon\Domain\Entities\Coupon;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class CouponRestController {

	private const NAMESPACE = 'ge-commerce/v1';

	public function __construct(
		private readonly ListCouponsHandler $listHandler,
		private readonly GetCouponHandler $getHandler,
		private readonly SaveCouponHandler $saveHandler,
		private readonly DeleteCouponHandler $deleteHandler
	) {}

	public function registerRoutes(): void {
		\register_rest_route(
			self::NAMESPACE,
			'/coupons',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'listCoupons' ],
					'permission_callback' => [ $this, 'canManageCoupons' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'createCoupon' ],
					'permission_callback' => [ $this, 'canManageCoupons' ],
				],
			]
		);

		\register_rest_route(
			self::NAMESPACE,
			'/coupons/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getCoupon' ],
					'permission_callback' => [ $this, 'canManageCoupons' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'updateCoupon' ],
					'permission_callback' => [ $this, 'canManageCoupons' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'deleteCoupon' ],
					'permission_callback' => [ $this, 'canManageCoupons' ],
				],
			]
		);
	}

	public function canManageCoupons( WP_REST_Request $request ): bool|WP_Error {
		if ( \current_user_can( 'manage_options' ) || \current_user_can( 'manage_ge_commerce' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			\__( 'You do not have permission to manage GE Commerce coupons.', 'ge-commerce' ),
			[ 'status' => 403 ]
		);
	}

	public function listCoupons( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$page    = max( 1, \absint( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = max( 1, \absint( $request->get_param( 'per_page' ) ?? 20 ) );

		try {
			$result = $this->listHandler->execute( $page, $perPage );
			return new WP_REST_Response(
				[
					'ok'   => true,
					'data' => [
						'items' => array_map( static fn( Coupon $c ): array => $c->toArray(), $result['items'] ),
						'total' => $result['total'],
					],
				],
				200
			);
		} catch ( Throwable $e ) {
			return new WP_Error( 'coupon_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}

	public function getCoupon( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = \absint( $request->get_param( 'id' ) );

		try {
			$coupon = $this->getHandler->execute( $id );
			return new WP_REST_Response( [ 'ok' => true, 'data' => $coupon->toArray() ], 200 );
		} catch ( RuntimeException $e ) {
			return new WP_Error( 'coupon_not_found', \esc_html( $e->getMessage() ), [ 'status' => 404 ] );
		} catch ( Throwable $e ) {
			return new WP_Error( 'coupon_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}

	public function createCoupon( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->handleSave( null, $request, 201 );
	}

	public function updateCoupon( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = \absint( $request->get_param( 'id' ) );
		return $this->handleSave( $id, $request, 200 );
	}

	public function deleteCoupon( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = \absint( $request->get_param( 'id' ) );

		try {
			$deleted = $this->deleteHandler->execute( $id );
			return new WP_REST_Response(
				[
					'ok'   => true,
					'data' => [
						'deleted' => $deleted,
						'id'      => $id,
					],
				],
				200
			);
		} catch ( RuntimeException $e ) {
			return new WP_Error( 'coupon_not_found', \esc_html( $e->getMessage() ), [ 'status' => 404 ] );
		} catch ( Throwable $e ) {
			return new WP_Error( 'coupon_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}

	private function handleSave( ?int $id, WP_REST_Request $request, int $successStatus ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$code          = \sanitize_text_field( (string) ( $params['code'] ?? '' ) );
		$discountType  = \sanitize_text_field( (string) ( $params['discount_type'] ?? 'fixed_cents' ) );
		$amountValue   = (int) ( $params['amount_value'] ?? 0 );
		$minOrderCents = (int) ( $params['min_order_cents'] ?? 0 );
		$usageLimit    = isset( $params['usage_limit'] ) && '' !== $params['usage_limit'] && null !== $params['usage_limit']
			? (int) $params['usage_limit']
			: null;
		$expiresAt     = isset( $params['expires_at'] ) && '' !== trim( (string) $params['expires_at'] )
			? \sanitize_text_field( (string) $params['expires_at'] )
			: null;

		try {
			$coupon = $this->saveHandler->execute(
				new SaveCouponDTO(
					id: $id,
					code: $code,
					discountType: $discountType,
					amountValue: $amountValue,
					minOrderCents: $minOrderCents,
					usageLimit: $usageLimit,
					expiresAt: $expiresAt
				)
			);

			return new WP_REST_Response(
				[
					'ok'   => true,
					'data' => $coupon->toArray(),
				],
				$successStatus
			);
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'coupon_validation_error', \esc_html( $e->getMessage() ), [ 'status' => 422 ] );
		} catch ( RuntimeException $e ) {
			return new WP_Error( 'coupon_not_found', \esc_html( $e->getMessage() ), [ 'status' => 404 ] );
		} catch ( Throwable $e ) {
			return new WP_Error( 'coupon_persistence_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}
}
