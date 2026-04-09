<?php
/**
 * Payments API endpoint.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\API\Endpoints;

use WP_Error;

/**
 * Handles payment-related API calls.
 */
class Payments extends AbstractEndpoint {

	/**
	 * Create a payment for an invoice.
	 *
	 * @param int                  $invoice_id Invoice ID.
	 * @param array<string, mixed> $data       Payment data.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( int $invoice_id, array $data ): array|WP_Error {
		return $this->client->post(
			$this->build_path( 'invoices', (string) $invoice_id, 'payments' ),
			$data
		);
	}

	/**
	 * Create a foreign currency payment for an invoice.
	 *
	 * @param int                  $invoice_id Invoice ID.
	 * @param array<string, mixed> $data       Payment data.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_foreign_currency( int $invoice_id, array $data ): array|WP_Error {
		return $this->client->post(
			$this->build_path( 'invoices', (string) $invoice_id, 'payments', 'foreign-currency' ),
			$data
		);
	}

	/**
	 * Get payments for an invoice.
	 *
	 * @param int $invoice_id Invoice ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_for_invoice( int $invoice_id ): array|WP_Error {
		return $this->client->get(
			$this->build_path( 'invoices', (string) $invoice_id, 'payments' )
		);
	}

	/**
	 * Delete a payment.
	 *
	 * @param int $payment_id Payment ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function delete( int $payment_id ): array|WP_Error {
		return $this->client->delete(
			$this->build_path( 'payments', (string) $payment_id )
		);
	}

	/**
	 * Search payments across all invoices.
	 *
	 * @param int $hits Results per page.
	 * @param int $page Page number (0-based).
	 * @return array<string, mixed>|WP_Error
	 */
	public function search( int $hits = 10, int $page = 0 ): array|WP_Error {
		return $this->client->get(
			$this->build_path( 'invoice-payments' ),
			[
				'hits' => $hits,
				'page' => $page,
			]
		);
	}
}
