<?php
/**
 * REST API Controller for Catalog Products & SKUs (`/wp-json/ge-commerce/v1/products`).
 *
 * @package GeCommerce\Modules\Catalog\Presentation\Rest
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Catalog\Presentation\Rest;

use GeCommerce\Modules\Catalog\Application\DTOs\SaveProductDTO;
use GeCommerce\Modules\Catalog\Application\DTOs\SkuInputDTO;
use GeCommerce\Modules\Catalog\Application\Handlers\DeleteProductHandler;
use GeCommerce\Modules\Catalog\Application\Handlers\GetProductHandler;
use GeCommerce\Modules\Catalog\Application\Handlers\ListProductsHandler;
use GeCommerce\Modules\Catalog\Application\Handlers\SaveProductHandler;
use GeCommerce\Modules\Catalog\Domain\Entities\Product;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class CatalogRestController {

	private const NAMESPACE = 'ge-commerce/v1';

	public function __construct(
		private readonly ListProductsHandler $listHandler,
		private readonly GetProductHandler $getHandler,
		private readonly SaveProductHandler $saveHandler,
		private readonly DeleteProductHandler $deleteHandler
	) {}

	public function registerRoutes(): void {
		\register_rest_route(
			self::NAMESPACE,
			'/products',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'listProducts' ],
					'permission_callback' => [ $this, 'canManageCatalog' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'createProduct' ],
					'permission_callback' => [ $this, 'canManageCatalog' ],
				],
			]
		);

		\register_rest_route(
			self::NAMESPACE,
			'/products/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'getProduct' ],
					'permission_callback' => [ $this, 'canManageCatalog' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'updateProduct' ],
					'permission_callback' => [ $this, 'canManageCatalog' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'deleteProduct' ],
					'permission_callback' => [ $this, 'canManageCatalog' ],
				],
			]
		);
	}

	public function canManageCatalog( WP_REST_Request $request ): bool|WP_Error {
		if ( \current_user_can( 'manage_options' ) || \current_user_can( 'manage_ge_commerce' ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			\__( 'You do not have permission to manage GE Commerce catalog.', 'ge-commerce' ),
			[ 'status' => 403 ]
		);
	}

	public function listProducts( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$page    = max( 1, \absint( $request->get_param( 'page' ) ?? 1 ) );
		$perPage = max( 1, \absint( $request->get_param( 'per_page' ) ?? 20 ) );
		$rawStat = $request->get_param( 'status' );
		$status  = is_string( $rawStat ) && '' !== $rawStat ? \sanitize_text_field( $rawStat ) : null;

		try {
			$result = $this->listHandler->execute( $page, $perPage, $status );
			return new WP_REST_Response(
				[
					'ok'   => true,
					'data' => [
						'items' => array_map( static fn( Product $p ): array => $p->toArray(), $result['items'] ),
						'total' => $result['total'],
					],
				],
				200
			);
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'catalog_validation_error', \esc_html( $e->getMessage() ), [ 'status' => 422 ] );
		} catch ( Throwable $e ) {
			return new WP_Error( 'catalog_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}

	public function getProduct( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = \absint( $request->get_param( 'id' ) );

		try {
			$product = $this->getHandler->execute( $id );
			return new WP_REST_Response( [ 'ok' => true, 'data' => $product->toArray() ], 200 );
		} catch ( RuntimeException $e ) {
			return new WP_Error( 'catalog_not_found', \esc_html( $e->getMessage() ), [ 'status' => 404 ] );
		} catch ( Throwable $e ) {
			return new WP_Error( 'catalog_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}

	public function createProduct( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		return $this->handleSave( null, $request, 201 );
	}

	public function updateProduct( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id = \absint( $request->get_param( 'id' ) );
		return $this->handleSave( $id, $request, 200 );
	}

	public function deleteProduct( WP_REST_Request $request ): WP_REST_Response|WP_Error {
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
			return new WP_Error( 'catalog_not_found', \esc_html( $e->getMessage() ), [ 'status' => 404 ] );
		} catch ( Throwable $e ) {
			return new WP_Error( 'catalog_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}

	private function handleSave( ?int $id, WP_REST_Request $request, int $successStatus ): WP_REST_Response|WP_Error {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$title       = \sanitize_text_field( (string) ( $params['title'] ?? '' ) );
		$rawSlug     = (string) ( $params['slug'] ?? $title );
		$slug        = \sanitize_title( $rawSlug );
		$description = \wp_kses_post( (string) ( $params['description'] ?? '' ) );
		$status      = \sanitize_text_field( (string) ( $params['status'] ?? 'draft' ) );
		$rawSkus     = is_array( $params['skus'] ?? null ) ? $params['skus'] : [];

		$skuDtos = [];
		foreach ( $rawSkus as $rawSku ) {
			if ( ! is_array( $rawSku ) ) {
				continue;
			}

			$cleanAttrs = [];
			if ( is_array( $rawSku['attributes'] ?? null ) ) {
				foreach ( $rawSku['attributes'] as $k => $v ) {
					$cleanAttrs[ \sanitize_text_field( (string) $k ) ] = \sanitize_text_field( (string) $v );
				}
			}

			$skuDtos[] = new SkuInputDTO(
				id: isset( $rawSku['id'] ) && is_numeric( $rawSku['id'] ) && (int) $rawSku['id'] > 0 ? (int) $rawSku['id'] : null,
				skuCode: \sanitize_text_field( (string) ( $rawSku['sku_code'] ?? '' ) ),
				attributes: $cleanAttrs,
				priceCents: (int) ( $rawSku['price_cents'] ?? 0 ),
				availableQty: (int) ( $rawSku['available_qty'] ?? 0 ),
				reservedQty: max( 0, (int) ( $rawSku['reserved_qty'] ?? 0 ) )
			);
		}

		try {
			$product = $this->saveHandler->execute(
				new SaveProductDTO(
					id: $id,
					slug: $slug,
					title: $title,
					description: $description,
					status: $status,
					skus: $skuDtos
				)
			);

			return new WP_REST_Response(
				[
					'ok'   => true,
					'data' => $product->toArray(),
				],
				$successStatus
			);
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'catalog_validation_error', \esc_html( $e->getMessage() ), [ 'status' => 422 ] );
		} catch ( RuntimeException $e ) {
			return new WP_Error( 'catalog_not_found', \esc_html( $e->getMessage() ), [ 'status' => 404 ] );
		} catch ( Throwable $e ) {
			return new WP_Error( 'catalog_persistence_error', \esc_html( $e->getMessage() ), [ 'status' => 500 ] );
		}
	}
}
