<?php
/**
 * Invoice synchronization module.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\Modules;

use Ihumbak\WooConta\API\Endpoints\Invoices;
use Ihumbak\WooConta\API\Models\Invoice;
use Ihumbak\WooConta\Services\Logger;
use Ihumbak\WooConta\Services\Settings;
use Ihumbak\WooConta\Services\VatMapper;
use WC_Order;
use WP_Error;

/**
 * Synchronizes WooCommerce orders to Conta.no invoices.
 */
class InvoiceSync {

	/**
	 * Order meta key for Conta invoice ID.
	 *
	 * @var string
	 */
	public const META_INVOICE_ID = '_ihumbak_wca_invoice_id';

	/**
	 * Order meta key for Conta invoice number.
	 *
	 * @var string
	 */
	public const META_INVOICE_NO = '_ihumbak_wca_invoice_no';

	/**
	 * Order meta key for sync status.
	 *
	 * @var string
	 */
	public const META_SYNC_STATUS = '_ihumbak_wca_sync_status';

	/**
	 * Order meta key for sync date.
	 *
	 * @var string
	 */
	public const META_SYNC_DATE = '_ihumbak_wca_sync_date';

	/**
	 * Order meta key for sync error message.
	 *
	 * @var string
	 */
	public const META_SYNC_ERROR = '_ihumbak_wca_sync_error';

	/**
	 * Order meta key for Conta invoice type (NORMAL or CASH).
	 *
	 * @var string
	 */
	public const META_INVOICE_TYPE = '_ihumbak_wca_invoice_type';

	/**
	 * Invoices API endpoint.
	 *
	 * @var Invoices
	 */
	private Invoices $invoices;

	/**
	 * Customer sync module.
	 *
	 * @var CustomerSync
	 */
	private CustomerSync $customer_sync;

	/**
	 * VAT mapper service.
	 *
	 * @var VatMapper
	 */
	private VatMapper $vat_mapper;

	/**
	 * Plugin settings service.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Logger service.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Invoices     $invoices      Invoices API endpoint.
	 * @param CustomerSync $customer_sync Customer sync module.
	 * @param VatMapper    $vat_mapper    VAT mapper service.
	 * @param Settings     $settings      Plugin settings service.
	 * @param Logger       $logger        Logger service.
	 */
	public function __construct(
		Invoices $invoices,
		CustomerSync $customer_sync,
		VatMapper $vat_mapper,
		Settings $settings,
		Logger $logger
	) {
		$this->invoices      = $invoices;
		$this->customer_sync = $customer_sync;
		$this->vat_mapper    = $vat_mapper;
		$this->settings      = $settings;
		$this->logger        = $logger;
	}

	/**
	 * Detect the invoice type based on whether the customer has a VAT number.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string 'NORMAL' if VAT number is present, 'CASH' otherwise.
	 */
	public function detect_invoice_type( WC_Order $order ): string {
		$vat_field = $this->settings->get_vat_number_field();

		if ( '' === $vat_field ) {
			return 'CASH';
		}

		$vat_number = (string) $order->get_meta( $vat_field );

		return '' !== $vat_number ? 'NORMAL' : 'CASH';
	}

	/**
	 * Sync a WooCommerce order to Conta as an invoice.
	 *
	 * If the order is already synced, returns the existing invoice data from Conta.
	 * Otherwise, syncs the customer, builds the invoice, and creates it via the API.
	 *
	 * @param WC_Order $order                 WooCommerce order.
	 * @param string   $invoice_type          Invoice type: 'NORMAL', 'CASH', or '' for auto-detect.
	 * @param int      $selected_customer_id  Optional pre-selected Conta customer ID from admin UI.
	 * @param bool     $force_create_customer Force-create a new customer, skipping search.
	 * @return array<string, mixed>|WP_Error Invoice data on success, WP_Error on failure.
	 */
	public function sync_order( WC_Order $order, string $invoice_type = '', int $selected_customer_id = 0, bool $force_create_customer = false ): array|WP_Error {
		// If already synced, return existing invoice data from Conta.
		if ( $this->is_synced( $order ) ) {
			$invoice_id = (int) $order->get_meta( self::META_INVOICE_ID );

			$this->logger->debug(
				'Order already synced to Conta',
				[
					'order_id'   => $order->get_id(),
					'invoice_id' => $invoice_id,
				]
			);

			return $this->invoices->get( $invoice_id );
		}

		// Sync customer first.
		$customer_id = $this->customer_sync->sync_customer( $order, $selected_customer_id, $force_create_customer );

		if ( is_wp_error( $customer_id ) ) {
			$this->store_sync_error( $order, $customer_id->get_error_message() );
			return $customer_id;
		}

		// Resolve invoice type.
		if ( '' === $invoice_type ) {
			$invoice_type = $this->detect_invoice_type( $order );
		}

		/**
		 * Filter the invoice type before creating the invoice in Conta.
		 *
		 * @param string   $invoice_type Invoice type: 'NORMAL' or 'CASH'.
		 * @param WC_Order $order        WooCommerce order.
		 */
		$invoice_type = apply_filters( 'ihumbak_wca_invoice_type', $invoice_type, $order );

		// Build invoice from order.
		$invoice       = Invoice::from_wc_order( $order, $customer_id, $this->vat_mapper, $this->settings );
		$invoice->type = $invoice_type;
		$data          = $invoice->to_array();

		/**
		 * Filter invoice data before sending to Conta API.
		 *
		 * @param array<string, mixed> $data  Invoice data.
		 * @param WC_Order             $order WooCommerce order.
		 */
		$data = apply_filters( 'ihumbak_wca_invoice_data', $data, $order );

		// Create invoice in Conta.
		$result = $this->invoices->create( $data );

		if ( is_wp_error( $result ) ) {
			$this->store_sync_error( $order, $result->get_error_message() );
			return $result;
		}

		// Store success meta.
		$invoice_id = (int) ( $result['id'] ?? 0 );
		$invoice_no = (int) ( $result['invoiceNo'] ?? 0 );

		$order->update_meta_data( self::META_INVOICE_ID, (string) $invoice_id );
		$order->update_meta_data( self::META_INVOICE_NO, (string) $invoice_no );
		$order->update_meta_data( self::META_INVOICE_TYPE, $invoice_type );
		$order->update_meta_data( self::META_SYNC_STATUS, 'synced' );
		$order->update_meta_data( self::META_SYNC_DATE, gmdate( 'c' ) );
		$order->delete_meta_data( self::META_SYNC_ERROR );
		$order->save();

		$this->logger->info(
			'Invoice created in Conta',
			[
				'order_id'   => $order->get_id(),
				'invoice_id' => $invoice_id,
				'invoice_no' => $invoice_no,
			]
		);

		return $result;
	}

	/**
	 * Get the sync status for an order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string Sync status: 'synced', 'error', or 'pending'.
	 */
	public function get_sync_status( WC_Order $order ): string {
		$status = $order->get_meta( self::META_SYNC_STATUS );

		if ( is_string( $status ) && '' !== $status ) {
			return $status;
		}

		return 'pending';
	}

	/**
	 * Check if an order has been synced to Conta.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return bool True if the order has a valid Conta invoice ID.
	 */
	public function is_synced( WC_Order $order ): bool {
		$invoice_id = (int) $order->get_meta( self::META_INVOICE_ID );

		return $invoice_id > 0;
	}

	/**
	 * Create a credit note for an already-synced order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array<string, mixed>|WP_Error Credit note data on success, WP_Error on failure.
	 */
	public function create_credit_note( WC_Order $order ): array|WP_Error {
		if ( ! $this->is_synced( $order ) ) {
			return new WP_Error(
				'invoice_not_synced',
				__( 'Cannot create credit note: order has not been synced to Conta.', 'ihumbak-woo-conta-api' )
			);
		}

		$invoice_id = (int) $order->get_meta( self::META_INVOICE_ID );

		$data = [
			'creditNoteDate'        => gmdate( 'Y-m-d' ),
			'removePaymentsIfExist' => true,
		];

		$result = $this->invoices->create_credit_note( $invoice_id, $data );

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Credit note creation failed',
				[
					'order_id'   => $order->get_id(),
					'invoice_id' => $invoice_id,
					'error'      => $result->get_error_message(),
				]
			);
		} else {
			$this->logger->info(
				'Credit note created in Conta',
				[
					'order_id'   => $order->get_id(),
					'invoice_id' => $invoice_id,
				]
			);
		}

		return $result;
	}

	/**
	 * Store a sync error in order meta.
	 *
	 * @param WC_Order $order   WooCommerce order.
	 * @param string   $message Error message.
	 * @return void
	 */
	private function store_sync_error( WC_Order $order, string $message ): void {
		$order->update_meta_data( self::META_SYNC_STATUS, 'error' );
		$order->update_meta_data( self::META_SYNC_ERROR, $message );
		$order->update_meta_data( self::META_SYNC_DATE, gmdate( 'c' ) );
		$order->save();

		$this->logger->error(
			'Invoice sync failed',
			[
				'order_id' => $order->get_id(),
				'error'    => $message,
			]
		);
	}
}
