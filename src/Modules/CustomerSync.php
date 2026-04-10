<?php
/**
 * Customer synchronization module.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\Modules;

use Ihumbak\WooConta\API\Endpoints\Customers;
use Ihumbak\WooConta\API\Models\Customer;
use Ihumbak\WooConta\Services\Logger;
use Ihumbak\WooConta\Services\Settings;
use WC_Order;
use WP_Error;

/**
 * Synchronizes WooCommerce customers to Conta.no.
 */
class CustomerSync {

	/**
	 * Order meta key for Conta customer ID.
	 *
	 * @var string
	 */
	public const META_CUSTOMER_ID = '_ihumbak_wca_customer_id';

	/**
	 * Customers API endpoint.
	 *
	 * @var Customers
	 */
	private Customers $customers;

	/**
	 * Logger service.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * In-memory cache of email → customer ID lookups.
	 *
	 * @var array<string, int>
	 */
	private array $cache = [];

	/**
	 * Constructor.
	 *
	 * @param Customers $customers Customers API endpoint.
	 * @param Logger    $logger    Logger service.
	 * @param Settings  $settings  Settings service.
	 */
	public function __construct( Customers $customers, Logger $logger, Settings $settings ) {
		$this->customers = $customers;
		$this->logger    = $logger;
		$this->settings  = $settings;
	}

	/**
	 * Sync a WooCommerce order's customer to Conta.
	 *
	 * Finds an existing Conta customer by email or creates a new one.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return int|WP_Error Conta customer ID on success, WP_Error on failure.
	 */
	public function sync_customer( WC_Order $order ): int|WP_Error {
		// Check order meta for existing customer ID.
		$existing_id = (int) $order->get_meta( self::META_CUSTOMER_ID );

		if ( $existing_id > 0 ) {
			// Verify customer still exists in Conta.
			$result = $this->customers->get( $existing_id );

			if ( ! is_wp_error( $result ) ) {
				$this->logger->debug(
					'Customer already synced',
					[
						'order_id'    => $order->get_id(),
						'customer_id' => $existing_id,
					]
				);
				return $existing_id;
			}

			// Customer not found in Conta, clear stale meta.
			$order->delete_meta_data( self::META_CUSTOMER_ID );
			$order->save();
		}

		$email = $order->get_billing_email();

		if ( empty( $email ) ) {
			return new WP_Error(
				'missing_email',
				__( 'Order does not have a billing email address.', 'ihumbak-woo-conta-api' )
			);
		}

		// Check in-memory cache.
		if ( isset( $this->cache[ $email ] ) ) {
			$cached_id = $this->cache[ $email ];
			$order->update_meta_data( self::META_CUSTOMER_ID, (string) $cached_id );
			$order->save();
			return $cached_id;
		}

		// Search for existing customer in Conta.
		$found_id = $this->find_by_email( $email );

		if ( null !== $found_id ) {
			$this->cache[ $email ] = $found_id;
			$order->update_meta_data( self::META_CUSTOMER_ID, (string) $found_id );
			$order->save();

			$this->logger->info(
				'Existing Conta customer found',
				[
					'order_id'    => $order->get_id(),
					'customer_id' => $found_id,
					'email'       => $email,
				]
			);

			return $found_id;
		}

		// Create new customer.
		$customer_id = $this->create_from_order( $order );

		if ( is_wp_error( $customer_id ) ) {
			return $customer_id;
		}

		$this->cache[ $email ] = $customer_id;
		$order->update_meta_data( self::META_CUSTOMER_ID, (string) $customer_id );
		$order->save();

		return $customer_id;
	}

	/**
	 * Search for an existing Conta customer by email.
	 *
	 * @param string $email Customer email address.
	 * @return int|null Conta customer ID or null if not found.
	 */
	public function find_by_email( string $email ): ?int {
		$result = $this->customers->search( $email, 1, 0 );

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Customer search failed',
				[
					'email' => $email,
					'error' => $result->get_error_message(),
				]
			);
			return null;
		}

		// Conta returns results in a 'results' array.
		$results = $result['results'] ?? [];

		if ( ! is_array( $results ) || 0 === count( $results ) ) {
			return null;
		}

		// Match by exact email.
		foreach ( $results as $customer ) {
			if ( is_array( $customer ) && isset( $customer['emailAddress'] ) && strtolower( (string) $customer['emailAddress'] ) === strtolower( $email ) ) {
				return (int) $customer['id'];
			}
		}

		return null;
	}

	/**
	 * Create a new Conta customer from a WooCommerce order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return int|WP_Error Conta customer ID on success, WP_Error on failure.
	 */
	public function create_from_order( WC_Order $order ): int|WP_Error {
		$customer = Customer::from_wc_order( $order, $this->settings->get_vat_number_field() );

		$data = $customer->to_array();

		/**
		 * Filter customer data before sending to Conta API.
		 *
		 * @param array<string, mixed> $data  Customer data.
		 * @param WC_Order             $order WooCommerce order.
		 */
		$data = apply_filters( 'ihumbak_wca_customer_data', $data, $order );

		$result = $this->customers->create( $data );

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Customer creation failed',
				[
					'order_id' => $order->get_id(),
					'error'    => $result->get_error_message(),
				]
			);
			return $result;
		}

		$customer_id = (int) ( $result['id'] ?? 0 );

		if ( 0 === $customer_id ) {
			return new WP_Error(
				'invalid_response',
				__( 'Conta API did not return a customer ID.', 'ihumbak-woo-conta-api' )
			);
		}

		$this->logger->info(
			'Customer created in Conta',
			[
				'order_id'    => $order->get_id(),
				'customer_id' => $customer_id,
			]
		);

		return $customer_id;
	}

	/**
	 * Update an existing Conta customer from a WooCommerce order.
	 *
	 * @param WC_Order $order       WooCommerce order.
	 * @param int      $customer_id Conta customer ID.
	 * @return array<string, mixed>|WP_Error Updated customer data or WP_Error.
	 */
	public function update_from_order( WC_Order $order, int $customer_id ): array|WP_Error {
		$customer = Customer::from_wc_order( $order, $this->settings->get_vat_number_field() );

		$data = $customer->to_array();

		/** This filter is documented in src/Modules/CustomerSync.php */
		$data = apply_filters( 'ihumbak_wca_customer_data', $data, $order );

		$result = $this->customers->update( $customer_id, $data );

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Customer update failed',
				[
					'order_id'    => $order->get_id(),
					'customer_id' => $customer_id,
					'error'       => $result->get_error_message(),
				]
			);
		} else {
			$this->logger->info(
				'Customer updated in Conta',
				[
					'order_id'    => $order->get_id(),
					'customer_id' => $customer_id,
				]
			);
		}

		return $result;
	}
}
