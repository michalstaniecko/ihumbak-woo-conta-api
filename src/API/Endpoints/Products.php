<?php
/**
 * Products API endpoint.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\API\Endpoints;

use WP_Error;

/**
 * Handles product-related API calls.
 */
class Products extends AbstractEndpoint {

	/**
	 * Search products.
	 *
	 * @param string $query Search query.
	 * @param int    $hits  Results per page.
	 * @param int    $page  Page number (0-based).
	 * @return array<string, mixed>|WP_Error
	 */
	public function search( string $query = '', int $hits = 10, int $page = 0 ): array|WP_Error {
		$params = [
			'hits' => $hits,
			'page' => $page,
		];

		if ( '' !== $query ) {
			$params['q'] = $query;
		}

		return $this->client->get( $this->build_path( 'products' ), $params );
	}

	/**
	 * Create a new product.
	 *
	 * @param array<string, mixed> $data Product data.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( array $data ): array|WP_Error {
		return $this->client->post( $this->build_path( 'products' ), $data );
	}

	/**
	 * Get a product by ID.
	 *
	 * @param int $id Product ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get( int $id ): array|WP_Error {
		return $this->client->get( $this->build_path( 'products', (string) $id ) );
	}

	/**
	 * Update a product.
	 *
	 * @param int                  $id   Product ID.
	 * @param array<string, mixed> $data Product data.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( int $id, array $data ): array|WP_Error {
		return $this->client->put( $this->build_path( 'products', (string) $id ), $data );
	}
}
