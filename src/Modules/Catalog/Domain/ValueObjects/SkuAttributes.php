<?php
/**
 * Immutable SKU variable attributes value object.
 *
 * @package GeCommerce\Modules\Catalog\Domain\ValueObjects
 */

declare(strict_types=1);

namespace GeCommerce\Modules\Catalog\Domain\ValueObjects;

use InvalidArgumentException;

final class SkuAttributes {

	/**
	 * Normalized attribute map (key => value).
	 *
	 * @var array<string, string>
	 */
	private readonly array $attributes;

	/**
	 * @param array<string, scalar> $attributes Raw attribute map.
	 */
	public function __construct( array $attributes = [] ) {
		$normalized = [];
		foreach ( $attributes as $key => $value ) {
			$cleanKey   = strtolower( trim( (string) $key ) );
			$cleanValue = trim( (string) $value );
			if ( '' === $cleanKey || '' === $cleanValue ) {
				throw new InvalidArgumentException( 'SKU attribute keys and values must be non-empty.' );
			}
			$normalized[ $cleanKey ] = $cleanValue;
		}
		ksort( $normalized );
		$this->attributes = $normalized;
	}

	/**
	 * Hydrate from JSON string stored in `attributes_json`.
	 */
	public static function fromJson( string $json ): self {
		if ( '' === trim( $json ) ) {
			return new self( [] );
		}

		$decoded = json_decode( $json, true );
		if ( ! is_array( $decoded ) ) {
			throw new InvalidArgumentException( 'Invalid SKU attributes JSON.' );
		}

		return new self( $decoded );
	}

	/**
	 * Serialize attributes to deterministic JSON.
	 */
	public function toJson(): string {
		$encoded = json_encode( $this->attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		return false === $encoded ? '{}' : $encoded;
	}

	/**
	 * Return normalized attribute map.
	 *
	 * @return array<string, string>
	 */
	public function toArray(): array {
		return $this->attributes;
	}
}
