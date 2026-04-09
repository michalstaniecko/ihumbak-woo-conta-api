<?php
/**
 * Payment synchronization module.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\Modules;

use Ihumbak\WooConta\API\Endpoints\Invoices;
use Ihumbak\WooConta\API\Endpoints\Payments;
use Ihumbak\WooConta\API\Models\Payment;
use Ihumbak\WooConta\Services\Logger;
use Ihumbak\WooConta\Services\Settings;
use WC_Order;
use WP_Error;

/**
 * Synchronizes WooCommerce order payments to Conta.no.
 */
class PaymentSync {

	/**
	 * Order meta key indicating payment has been synced to Conta.
	 *
	 * @var string
	 */
	public const META_PAYMENT_SYNCED = '_ihumbak_wca_payment_synced';

	/**
	 * Payments API endpoint.
	 *
	 * @var Payments
	 */
	private Payments $payments;

	/**
	 * Invoices API endpoint.
	 *
	 * @var Invoices
	 */
	private Invoices $invoices;

	/**
	 * Settings service.
	 *
	 * Reserved for future payment configuration (e.g. default bank account).
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
	 * @param Payments $payments Payments API endpoint.
	 * @param Invoices $invoices Invoices API endpoint.
	 * @param Settings $settings Settings service.
	 * @param Logger   $logger   Logger service.
	 */
	public function __construct( Payments $payments, Invoices $invoices, Settings $settings, Logger $logger ) {
		$this->payments = $payments;
		$this->invoices = $invoices;
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Sync a WooCommerce order payment to Conta.
	 *
	 * Creates a payment record against the corresponding Conta invoice.
	 * Handles foreign currency detection and already-paid invoice states.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return array<string, mixed>|WP_Error Payment response on success, WP_Error on failure.
	 */
	public function sync_payment( WC_Order $order ): array|WP_Error {
		if ( $this->is_payment_synced( $order ) ) {
			return new WP_Error(
				'already_synced',
				__( 'Payment has already been synced to Conta.', 'ihumbak-woo-conta-api' )
			);
		}

		$invoice_id = (int) $order->get_meta( InvoiceSync::META_INVOICE_ID );

		if ( 0 === $invoice_id ) {
			return new WP_Error(
				'no_invoice',
				__( 'Order does not have a Conta invoice ID.', 'ihumbak-woo-conta-api' )
			);
		}

		// Verify invoice exists and is not already paid.
		$invoice = $this->invoices->get( $invoice_id );

		if ( is_wp_error( $invoice ) ) {
			$this->logger->error(
				'Failed to retrieve invoice for payment sync',
				[
					'order_id'   => $order->get_id(),
					'invoice_id' => $invoice_id,
					'error'      => $invoice->get_error_message(),
				]
			);
			return $invoice;
		}

		if ( isset( $invoice['status'] ) && 'CLOSED_BY_PAYMENT' === $invoice['status'] ) {
			$order->update_meta_data( self::META_PAYMENT_SYNCED, '1' );
			$order->save();

			$this->logger->info(
				'Invoice already paid in Conta, marking payment as synced',
				[
					'order_id'   => $order->get_id(),
					'invoice_id' => $invoice_id,
				]
			);

			return $invoice;
		}

		$data     = Payment::from_wc_order( $order )->to_array();
		$currency = $order->get_currency();

		if ( 'NOK' !== $currency ) {
			$result = $this->payments->create_foreign_currency( $invoice_id, $data );
		} else {
			$result = $this->payments->create( $invoice_id, $data );
		}

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Payment sync failed',
				[
					'order_id'   => $order->get_id(),
					'invoice_id' => $invoice_id,
					'currency'   => $currency,
					'error'      => $result->get_error_message(),
				]
			);
			return $result;
		}

		$order->update_meta_data( self::META_PAYMENT_SYNCED, '1' );
		$order->save();

		$this->logger->info(
			'Payment synced to Conta',
			[
				'order_id'    => $order->get_id(),
				'invoice_id'  => $invoice_id,
				'currency'    => $currency,
				'environment' => $this->settings->get_environment(),
			]
		);

		return $result;
	}

	/**
	 * Check if a payment has already been synced for an order.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return bool True if payment is already synced.
	 */
	public function is_payment_synced( WC_Order $order ): bool {
		return '1' === $order->get_meta( self::META_PAYMENT_SYNCED );
	}
}
