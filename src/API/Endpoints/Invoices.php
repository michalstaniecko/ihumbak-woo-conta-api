<?php
/**
 * Invoices API endpoint.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\API\Endpoints;

use WP_Error;

/**
 * Handles invoice-related API calls.
 */
class Invoices extends AbstractEndpoint {

	/**
	 * Search invoices.
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

		return $this->client->get( $this->build_path( 'invoices' ), $params );
	}

	/**
	 * Create a new invoice.
	 *
	 * @param array<string, mixed> $data Invoice data.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( array $data ): array|WP_Error {
		return $this->client->post( $this->build_path( 'invoices' ), $data );
	}

	/**
	 * Create a new invoice draft.
	 *
	 * @param array<string, mixed> $data Invoice draft data.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_draft( array $data ): array|WP_Error {
		return $this->client->post( $this->build_path( 'invoice-drafts' ), $data );
	}

	/**
	 * Get an invoice by ID.
	 *
	 * @param int $id Invoice ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get( int $id ): array|WP_Error {
		return $this->client->get( $this->build_path( 'invoices', (string) $id ) );
	}

	/**
	 * Get an invoice draft by ID.
	 *
	 * @param int $id Invoice draft ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_draft( int $id ): array|WP_Error {
		return $this->client->get( $this->build_path( 'invoice-drafts', (string) $id ) );
	}

	/**
	 * Create a credit note for an invoice.
	 *
	 * @param int                  $invoice_id Invoice ID to credit.
	 * @param array<string, mixed> $data       Credit note data.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_credit_note( int $invoice_id, array $data ): array|WP_Error {
		return $this->client->post(
			$this->build_path( 'invoices', (string) $invoice_id, 'credit-note' ),
			$data
		);
	}

	/**
	 * Get allowed delivery methods for invoices.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_allowed_delivery_methods(): array|WP_Error {
		return $this->client->get( $this->build_path( 'invoices', 'allowed-delivery-methods' ) );
	}
}
