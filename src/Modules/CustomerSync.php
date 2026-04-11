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
	 * In-memory cache of VAT number → customer ID lookups.
	 *
	 * @var array<string, int>
	 */
	private array $vat_cache = [];

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
	 * Finds an existing Conta customer by VAT number or email, creates a new one if not found.
	 * Updates existing customer data if it has changed.
	 *
	 * @param WC_Order $order                WooCommerce order.
	 * @param int      $selected_customer_id Optional pre-selected Conta customer ID from admin UI.
	 * @param bool     $force_create         Force-create a new customer, skipping search.
	 * @return int|WP_Error Conta customer ID on success, WP_Error on failure.
	 */
	public function sync_customer( WC_Order $order, int $selected_customer_id = 0, bool $force_create = false ): int|WP_Error {
		// Step 0: If force-creating, skip all search and go straight to creation.
		if ( $force_create ) {
			$this->logger->info(
				'Force-creating new customer (admin selected "Create new")',
				[ 'order_id' => $order->get_id() ]
			);

			$customer_id = $this->create_from_order( $order );

			if ( is_wp_error( $customer_id ) ) {
				return $customer_id;
			}

			$email      = $order->get_billing_email();
			$vat_number = $this->get_order_vat_number( $order );
			$this->save_customer_meta( $order, $customer_id, $email, $vat_number );

			return $customer_id;
		}

		// Step 1: Check order meta for existing customer ID.
		$existing_id = (int) $order->get_meta( self::META_CUSTOMER_ID );

		if ( $existing_id > 0 ) {
			$result = $this->customers->get( $existing_id );

			if ( ! is_wp_error( $result ) ) {
				$this->logger->debug(
					'Customer already synced, checking for updates',
					[
						'order_id'    => $order->get_id(),
						'customer_id' => $existing_id,
					]
				);
				$this->maybe_update_customer( $order, $existing_id );
				return $existing_id;
			}

			// Customer not found in Conta, clear stale meta.
			$this->logger->info(
				'Stored customer ID is stale, re-searching',
				[
					'order_id'    => $order->get_id(),
					'customer_id' => $existing_id,
				]
			);
			$order->delete_meta_data( self::META_CUSTOMER_ID );
			$order->save();
		}

		// Step 1b: If a specific customer was selected via the admin UI, use it directly.
		if ( $selected_customer_id > 0 ) {
			$result = $this->customers->get( $selected_customer_id );

			if ( is_wp_error( $result ) ) {
				return new WP_Error(
					'selected_customer_not_found',
					sprintf(
						/* translators: %d: Conta customer ID */
						__( 'Selected customer ID %d not found in Conta.', 'ihumbak-woo-conta-api' ),
						$selected_customer_id
					)
				);
			}

			$this->logger->info(
				'Using manually selected customer',
				[
					'order_id'    => $order->get_id(),
					'customer_id' => $selected_customer_id,
				]
			);

			$email      = $order->get_billing_email();
			$vat_number = $this->get_order_vat_number( $order );
			$this->maybe_update_customer( $order, $selected_customer_id );
			$this->save_customer_meta( $order, $selected_customer_id, $email, $vat_number );

			return $selected_customer_id;
		}

		// Step 2: Extract identifiers.
		$email      = $order->get_billing_email();
		$vat_number = $this->get_order_vat_number( $order );

		if ( empty( $email ) ) {
			return new WP_Error(
				'missing_email',
				__( 'Order does not have a billing email address.', 'ihumbak-woo-conta-api' )
			);
		}

		// Step 3: Check in-memory caches.
		if ( '' !== $vat_number && isset( $this->vat_cache[ $vat_number ] ) ) {
			$cached_id = $this->vat_cache[ $vat_number ];
			$this->logger->debug(
				'Customer found in VAT cache',
				[
					'vat_number'  => $vat_number,
					'customer_id' => $cached_id,
				]
			);
			$this->maybe_update_customer( $order, $cached_id );
			$this->save_customer_meta( $order, $cached_id, $email, $vat_number );
			return $cached_id;
		}

		if ( isset( $this->cache[ $email ] ) ) {
			$cached_id = $this->cache[ $email ];
			$this->logger->debug(
				'Customer found in email cache',
				[
					'email'       => $email,
					'customer_id' => $cached_id,
				]
			);
			$this->maybe_update_customer( $order, $cached_id );
			$this->save_customer_meta( $order, $cached_id, $email, $vat_number );
			return $cached_id;
		}

		// Step 4: Search by VAT number (B2B customers).
		if ( '' !== $vat_number ) {
			$this->logger->info(
				'Searching Conta customer by VAT number',
				[
					'order_id'   => $order->get_id(),
					'vat_number' => $vat_number,
				]
			);

			$found_id = $this->find_by_vat_number( $vat_number );

			if ( is_wp_error( $found_id ) ) {
				return $found_id;
			}

			if ( null !== $found_id ) {
				$this->logger->info(
					'Existing Conta customer matched by VAT number',
					[
						'order_id'    => $order->get_id(),
						'customer_id' => $found_id,
						'vat_number'  => $vat_number,
					]
				);
				$this->maybe_update_customer( $order, $found_id );
				$this->save_customer_meta( $order, $found_id, $email, $vat_number );
				return $found_id;
			}
		}

		// Step 5: Search by email.
		$this->logger->info(
			'Searching Conta customer by email',
			[
				'order_id' => $order->get_id(),
				'email'    => $email,
			]
		);

		$found_id = $this->find_by_email( $email );

		if ( is_wp_error( $found_id ) ) {
			return $found_id;
		}

		if ( null !== $found_id ) {
			$this->logger->info(
				'Existing Conta customer matched by email',
				[
					'order_id'    => $order->get_id(),
					'customer_id' => $found_id,
					'email'       => $email,
				]
			);
			$this->maybe_update_customer( $order, $found_id );
			$this->save_customer_meta( $order, $found_id, $email, $vat_number );
			return $found_id;
		}

		// Step 6: Create new customer.
		$this->logger->info(
			'No existing customer found, creating new',
			[ 'order_id' => $order->get_id() ]
		);

		$customer_id = $this->create_from_order( $order );

		if ( is_wp_error( $customer_id ) ) {
			return $customer_id;
		}

		$this->save_customer_meta( $order, $customer_id, $email, $vat_number );

		return $customer_id;
	}

	/**
	 * Save customer ID to order meta and populate caches.
	 *
	 * @param WC_Order $order       WooCommerce order.
	 * @param int      $customer_id Conta customer ID.
	 * @param string   $email       Customer email.
	 * @param string   $vat_number  Customer VAT number (may be empty).
	 */
	private function save_customer_meta( WC_Order $order, int $customer_id, string $email, string $vat_number ): void {
		$this->cache[ $email ] = $customer_id;

		if ( '' !== $vat_number ) {
			$this->vat_cache[ $vat_number ] = $customer_id;
		}

		$order->update_meta_data( self::META_CUSTOMER_ID, (string) $customer_id );
		$order->save();
	}

	/**
	 * Search for an existing Conta customer by email.
	 *
	 * @param string $email Customer email address.
	 * @return int|WP_Error|null Conta customer ID, null if not found, WP_Error on API failure.
	 */
	public function find_by_email( string $email ): int|WP_Error|null {
		$result = $this->customers->search( $email, 10, 0 );

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Customer search by email failed',
				[
					'email' => $email,
					'error' => $result->get_error_message(),
				]
			);
			return $result;
		}

		// Conta returns results in a 'hits' array.
		$results = $result['hits'] ?? [];

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
	 * Search for an existing Conta customer by VAT/organization number.
	 *
	 * @param string $vat_number VAT or organization number.
	 * @return int|WP_Error|null Conta customer ID, null if not found, WP_Error on API failure.
	 */
	public function find_by_vat_number( string $vat_number ): int|WP_Error|null {
		$result = $this->customers->search( $vat_number, 10, 0 );

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Customer search by VAT number failed',
				[
					'vat_number' => $vat_number,
					'error'      => $result->get_error_message(),
				]
			);
			return $result;
		}

		$results = $result['hits'] ?? [];

		if ( ! is_array( $results ) || 0 === count( $results ) ) {
			return null;
		}

		$normalized_vat = $this->normalize_vat( $vat_number );

		foreach ( $results as $customer ) {
			if ( is_array( $customer ) && isset( $customer['orgNo'] ) && $this->normalize_vat( (string) $customer['orgNo'] ) === $normalized_vat ) {
				$this->logger->debug(
					'Customer matched by VAT number',
					[
						'vat_number'  => $vat_number,
						'customer_id' => (int) $customer['id'],
					]
				);
				return (int) $customer['id'];
			}
		}

		return null;
	}

	/**
	 * Normalize a VAT/organization number for comparison.
	 *
	 * Strips spaces, dashes, and Norwegian prefixes/suffixes (NO, MVA).
	 *
	 * @param string $value Raw VAT number.
	 * @return string Digits-only normalized value.
	 */
	private function normalize_vat( string $value ): string {
		$value = strtoupper( trim( $value ) );
		$value = str_replace( [ ' ', '-', '.', 'NO', 'MVA' ], '', $value );

		return preg_replace( '/[^0-9]/', '', $value );
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

	/**
	 * Update customer in Conta if order data differs from existing data.
	 *
	 * @param WC_Order $order       WooCommerce order.
	 * @param int      $customer_id Conta customer ID.
	 */
	private function maybe_update_customer( WC_Order $order, int $customer_id ): void {
		$existing = $this->customers->get( $customer_id );

		if ( is_wp_error( $existing ) ) {
			$this->logger->warning(
				'Could not fetch customer for update check',
				[
					'customer_id' => $customer_id,
					'error'       => $existing->get_error_message(),
				]
			);
			return;
		}

		$customer = Customer::from_wc_order( $order, $this->settings->get_vat_number_field() );
		$desired  = $customer->to_array();

		/** This filter is documented in src/Modules/CustomerSync.php */
		$desired = apply_filters( 'ihumbak_wca_customer_data', $desired, $order );

		$changes = $this->detect_changes( $existing, $desired );

		if ( empty( $changes ) ) {
			$this->logger->debug(
				'Customer data unchanged, skipping update',
				[ 'customer_id' => $customer_id ]
			);
			return;
		}

		$this->logger->info(
			'Customer data changed, updating in Conta',
			[
				'customer_id'    => $customer_id,
				'changed_fields' => array_keys( $changes ),
			]
		);

		$this->update_from_order( $order, $customer_id );
	}

	/**
	 * Detect differences between existing Conta customer and desired data.
	 *
	 * @param array<string, mixed> $existing Conta API customer data.
	 * @param array<string, mixed> $desired  Desired customer data from order.
	 * @return array<string, array{old: string, new: string}> Changed fields.
	 */
	private function detect_changes( array $existing, array $desired ): array {
		$compare_keys = [
			'name',
			'customerType',
			'emailAddress',
			'phoneNo',
			'orgNo',
			'customerAddressLine1',
			'customerAddressLine2',
			'customerAddressPostcode',
			'customerAddressCity',
			'customerAddressCountry',
		];

		$changes = [];

		foreach ( $compare_keys as $key ) {
			$old = (string) ( $existing[ $key ] ?? '' );
			$new = (string) ( $desired[ $key ] ?? '' );

			if ( $old !== $new ) {
				$changes[ $key ] = [
					'old' => $old,
					'new' => $new,
				];
			}
		}

		return $changes;
	}

	/**
	 * Extract VAT number from a WooCommerce order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string VAT number or empty string.
	 */
	public function get_order_vat_number( WC_Order $order ): string {
		$vat_field = $this->settings->get_vat_number_field();

		if ( '' === $vat_field ) {
			return '';
		}

		return trim( (string) $order->get_meta( $vat_field ) );
	}

	/**
	 * Find all matching Conta customers for an order by VAT number and email.
	 *
	 * Searches by VAT number first (if present), then by email, deduplicates by
	 * customer ID, and returns the full customer arrays for the selection UI.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array<int, array<string, mixed>>|WP_Error Array of customer arrays keyed by ID, or WP_Error on API failure.
	 */
	public function find_all_matches( WC_Order $order ): array|WP_Error {
		$email      = $order->get_billing_email();
		$vat_number = $this->get_order_vat_number( $order );
		$matches    = [];

		// Search by VAT number for B2B customers.
		if ( '' !== $vat_number ) {
			$result = $this->customers->search( $vat_number, 10, 0 );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$normalized_vat = $this->normalize_vat( $vat_number );
			$hits           = $result['hits'] ?? [];

			if ( is_array( $hits ) ) {
				foreach ( $hits as $customer ) {
					if ( is_array( $customer ) && isset( $customer['orgNo'] ) && $this->normalize_vat( (string) $customer['orgNo'] ) === $normalized_vat ) {
						$matches[ (int) $customer['id'] ] = $customer;
					}
				}
			}
		}

		// Search by email.
		if ( '' !== $email ) {
			$result = $this->customers->search( $email, 10, 0 );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$hits = $result['hits'] ?? [];

			if ( is_array( $hits ) ) {
				foreach ( $hits as $customer ) {
					if ( is_array( $customer ) && isset( $customer['emailAddress'] ) && strtolower( (string) $customer['emailAddress'] ) === strtolower( $email ) ) {
						$customer_id = (int) $customer['id'];
						if ( ! isset( $matches[ $customer_id ] ) ) {
							$matches[ $customer_id ] = $customer;
						}
					}
				}
			}
		}

		$this->logger->debug(
			'Customer search found matches',
			[
				'order_id' => $order->get_id(),
				'count'    => count( $matches ),
			]
		);

		return array_values( $matches );
	}
}
