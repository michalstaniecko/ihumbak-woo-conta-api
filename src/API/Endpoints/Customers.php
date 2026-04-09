<?php
/**
 * Customers API endpoint.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\API\Endpoints;

use WP_Error;

/**
 * Handles customer-related API calls.
 */
class Customers extends AbstractEndpoint {

	/**
	 * Search customers.
	 *
	 * @param string $query Search query (email, name, etc.).
	 * @param int    $hits  Results per page.
	 * @param int    $page  Page number (0-based).
	 * @return array<string, mixed>|WP_Error
	 */
	public function search( string $query, int $hits = 10, int $page = 0 ): array|WP_Error {
		return $this->client->get(
			$this->build_path( 'customers' ),
			[
				'q'    => $query,
				'hits' => $hits,
				'page' => $page,
			]
		);
	}

	/**
	 * Create a new customer.
	 *
	 * @param array<string, mixed> $data Customer data.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( array $data ): array|WP_Error {
		return $this->client->post( $this->build_path( 'customers' ), $data );
	}

	/**
	 * Get a customer by ID.
	 *
	 * @param int $id Customer ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get( int $id ): array|WP_Error {
		return $this->client->get( $this->build_path( 'customers', (string) $id ) );
	}

	/**
	 * Update a customer.
	 *
	 * @param int                  $id   Customer ID.
	 * @param array<string, mixed> $data Customer data.
	 * @return array<string, mixed>|WP_Error
	 */
	public function update( int $id, array $data ): array|WP_Error {
		return $this->client->put( $this->build_path( 'customers', (string) $id ), $data );
	}
}
