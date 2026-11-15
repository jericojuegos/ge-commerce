<?php
/**
 * Pure PHP Product Aggregate Root.
 *
 * @package GeCommerce\Modules\Catalog\Domain\Entities
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Catalog\Domain\Entities;

use GeCommerce\Modules\Catalog\Domain\ValueObjects\ProductStatus;
use InvalidArgumentException;

final class Product {

	private readonly string $slug;

	private readonly string $title;

	/**
	 * @var list<Sku>
	 */
	private readonly array $skus;

	/**
	 * @param list<Sku> $skus
	 */
	public function __construct(
		private readonly ?int $id,
		string $slug,
		string $title,
		private readonly string $description,
		private readonly ProductStatus $status,
		array $skus = [],
		private readonly ?string $createdAt = null,
		private readonly ?string $updatedAt = null
	) {
		$cleanSlug  = strtolower( trim( $slug ) );
		$cleanTitle = trim( $title );

		if ( '' === $cleanTitle ) {
			throw new InvalidArgumentException( 'Product title cannot be empty.' );
		}
		if ( '' === $cleanSlug ) {
			throw new InvalidArgumentException( 'Product slug cannot be empty.' );
		}
		if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $cleanSlug ) ) {
			throw new InvalidArgumentException( 'Product slug must contain lowercase alphanumeric characters and hyphens only.' );
		}

		$seenCodes = [];
		$validSkus = [];
		foreach ( $skus as $sku ) {
			if ( ! $sku instanceof Sku ) {
				throw new InvalidArgumentException( 'All product SKUs must be instances of Sku.' );
			}
			$code = $sku->skuCode();
			if ( isset( $seenCodes[ $code ] ) ) {
				throw new InvalidArgumentException( sprintf( 'Duplicate SKU code "%s" within product.', $code ) );
			}
			$seenCodes[ $code ] = true;
			$validSkus[]        = $sku;
		}

		$this->slug  = $cleanSlug;
		$this->title = $cleanTitle;
		$this->skus  = $validSkus;
	}

	public function id(): ?int {
		return $this->id;
	}

	public function slug(): string {
		return $this->slug;
	}

	public function title(): string {
		return $this->title;
	}

	public function description(): string {
		return $this->description;
	}

	public function status(): ProductStatus {
		return $this->status;
	}

	/**
	 * @return list<Sku>
	 */
	public function skus(): array {
		return $this->skus;
	}

	public function createdAt(): ?string {
		return $this->createdAt;
	}

	public function updatedAt(): ?string {
		return $this->updatedAt;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return [
			'id'          => $this->id,
			'slug'        => $this->slug,
			'title'       => $this->title,
			'description' => $this->description,
			'status'      => $this->status->value,
			'skus'        => array_map( static fn( Sku $sku ): array => $sku->toArray(), $this->skus ),
			'created_at'  => $this->createdAt,
			'updated_at'  => $this->updatedAt,
		];
	}
}
