<?php
/**
 * WooCommerce order hooks integration.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\Modules;

use Ihumbak\WooConta\Services\Logger;
use Ihumbak\WooConta\Services\Settings;
use WC_Order;

/**
 * Hooks into WooCommerce order lifecycle to trigger Conta sync.
 */
class OrderHooks {

	/**
	 * Action Scheduler group name.
	 *
	 * @var string
	 */
	private const SCHEDULER_GROUP = 'ihumbak-woo-conta-api';

	/**
	 * Transient prefix for bulk action results.
	 *
	 * @var string
	 */
	private const BULK_RESULT_TRANSIENT = 'ihumbak_wca_bulk_result_';

	/**
	 * InvoiceSync module.
	 *
	 * @var InvoiceSync
	 */
	private InvoiceSync $invoice_sync;

	/**
	 * PaymentSync module.
	 *
	 * @var PaymentSync
	 */
	private PaymentSync $payment_sync;

	/**
	 * Settings service.
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
	 * @param InvoiceSync $invoice_sync InvoiceSync module.
	 * @param PaymentSync $payment_sync PaymentSync module.
	 * @param Settings    $settings     Settings service.
	 * @param Logger      $logger       Logger service.
	 */
	public function __construct(
		InvoiceSync $invoice_sync,
		PaymentSync $payment_sync,
		Settings $settings,
		Logger $logger
	) {
		$this->invoice_sync = $invoice_sync;
		$this->payment_sync = $payment_sync;
		$this->settings     = $settings;
		$this->logger       = $logger;
	}

	/**
	 * Register all WooCommerce hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Order status change hooks.
		$trigger_status = $this->settings->get_invoice_trigger_status();
		add_action( 'woocommerce_order_status_' . $trigger_status, [ $this, 'on_invoice_trigger' ], 10, 1 );
		add_action( 'woocommerce_order_status_completed', [ $this, 'on_order_completed' ], 10, 1 );
		add_action( 'woocommerce_order_status_refunded', [ $this, 'on_order_refunded' ], 10, 1 );

		// Action Scheduler handlers.
		add_action( 'ihumbak_wca_sync_invoice', [ $this, 'handle_sync_invoice' ], 10, 1 );
		add_action( 'ihumbak_wca_sync_payment', [ $this, 'handle_sync_payment' ], 10, 1 );
		add_action( 'ihumbak_wca_create_credit_note', [ $this, 'handle_create_credit_note' ], 10, 1 );

		// Bulk actions on orders list.
		add_filter( 'bulk_actions-woocommerce_page_wc-orders', [ $this, 'add_bulk_action' ] );
		add_filter( 'bulk_actions-edit-shop_order', [ $this, 'add_bulk_action' ] );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', [ $this, 'handle_bulk_action' ], 10, 3 );
		add_filter( 'handle_bulk_actions-edit-shop_order', [ $this, 'handle_bulk_action' ], 10, 3 );

		// Admin notices for bulk action results.
		add_action( 'admin_notices', [ $this, 'display_bulk_action_notice' ] );
	}

	/**
	 * Handle order status change to the invoice trigger status.
	 *
	 * Schedules invoice creation via Action Scheduler.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function on_invoice_trigger( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! $this->should_sync_order( $order ) ) {
			return;
		}

		if ( $this->invoice_sync->is_synced( $order ) ) {
			return;
		}

		$this->schedule_action( 'ihumbak_wca_sync_invoice', $order_id );
	}

	/**
	 * Handle order status change to completed.
	 *
	 * Schedules payment registration via Action Scheduler.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function on_order_completed( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! $this->should_sync_order( $order ) ) {
			return;
		}

		if ( $this->payment_sync->is_payment_synced( $order ) ) {
			return;
		}

		// Schedule invoice sync first if not yet synced, then payment.
		if ( ! $this->invoice_sync->is_synced( $order ) ) {
			$this->schedule_action( 'ihumbak_wca_sync_invoice', $order_id );
		}

		$this->schedule_action( 'ihumbak_wca_sync_payment', $order_id );
	}

	/**
	 * Handle order status change to refunded.
	 *
	 * Schedules credit note creation via Action Scheduler.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function on_order_refunded( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! $this->invoice_sync->is_synced( $order ) ) {
			return;
		}

		$this->schedule_action( 'ihumbak_wca_create_credit_note', $order_id );
	}

	/**
	 * Handle scheduled invoice sync action.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_sync_invoice( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			$this->logger->error( 'Scheduled invoice sync failed: order not found', [ 'order_id' => $order_id ] );
			return;
		}

		$result = $this->invoice_sync->sync_order( $order );

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Scheduled invoice sync failed',
				[
					'order_id' => $order_id,
					'error'    => $result->get_error_message(),
				]
			);
		}
	}

	/**
	 * Handle scheduled payment sync action.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_sync_payment( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			$this->logger->error( 'Scheduled payment sync failed: order not found', [ 'order_id' => $order_id ] );
			return;
		}

		$result = $this->payment_sync->sync_payment( $order );

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Scheduled payment sync failed',
				[
					'order_id' => $order_id,
					'error'    => $result->get_error_message(),
				]
			);
		}
	}

	/**
	 * Handle scheduled credit note creation action.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return void
	 */
	public function handle_create_credit_note( int $order_id ): void {
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			$this->logger->error( 'Scheduled credit note failed: order not found', [ 'order_id' => $order_id ] );
			return;
		}

		$result = $this->invoice_sync->create_credit_note( $order );

		if ( is_wp_error( $result ) ) {
			$this->logger->error(
				'Scheduled credit note failed',
				[
					'order_id' => $order_id,
					'error'    => $result->get_error_message(),
				]
			);
		}
	}

	/**
	 * Add "Sync to Conta" bulk action to orders list.
	 *
	 * @param array<string, string> $actions Existing bulk actions.
	 * @return array<string, string> Modified bulk actions.
	 */
	public function add_bulk_action( array $actions ): array {
		$actions['ihumbak_wca_sync'] = __( 'Sync to Conta', 'ihumbak-woo-conta-api' );
		return $actions;
	}

	/**
	 * Handle the "Sync to Conta" bulk action.
	 *
	 * @param string $redirect_url Redirect URL after action.
	 * @param string $action       The action being taken.
	 * @param int[]  $order_ids    Selected order IDs.
	 * @return string Modified redirect URL.
	 */
	public function handle_bulk_action( string $redirect_url, string $action, array $order_ids ): string {
		if ( 'ihumbak_wca_sync' !== $action ) {
			return $redirect_url;
		}

		$success = 0;
		$errors  = 0;

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order instanceof WC_Order ) {
				++$errors;
				continue;
			}

			if ( ! $this->should_sync_order( $order ) ) {
				continue;
			}

			// Sync invoice.
			if ( ! $this->invoice_sync->is_synced( $order ) ) {
				$result = $this->invoice_sync->sync_order( $order );

				if ( is_wp_error( $result ) ) {
					++$errors;
					continue;
				}
			}

			// Sync payment if order is paid.
			if ( $order->is_paid() && ! $this->payment_sync->is_payment_synced( $order ) ) {
				$payment_result = $this->payment_sync->sync_payment( $order );

				if ( is_wp_error( $payment_result ) ) {
					$this->logger->warning(
						'Bulk sync: payment failed',
						[
							'order_id' => $order_id,
							'error'    => $payment_result->get_error_message(),
						]
					);
				}
			}

			++$success;
		}

		// Store results in transient for admin notice.
		$user_id = get_current_user_id();
		set_transient(
			self::BULK_RESULT_TRANSIENT . $user_id,
			[
				'success' => $success,
				'errors'  => $errors,
			],
			60
		);

		return $redirect_url;
	}

	/**
	 * Display admin notice after bulk sync action.
	 *
	 * @return void
	 */
	public function display_bulk_action_notice(): void {
		$user_id   = get_current_user_id();
		$transient = self::BULK_RESULT_TRANSIENT . $user_id;
		$result    = get_transient( $transient );

		if ( ! is_array( $result ) ) {
			return;
		}

		delete_transient( $transient );

		$success = (int) ( $result['success'] ?? 0 );
		$errors  = (int) ( $result['errors'] ?? 0 );

		if ( $success > 0 ) {
			$message = sprintf(
				/* translators: %d: number of orders synced */
				_n(
					'%d order synced to Conta.',
					'%d orders synced to Conta.',
					$success,
					'ihumbak-woo-conta-api'
				),
				$success
			);

			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html( $message )
			);
		}

		if ( $errors > 0 ) {
			$message = sprintf(
				/* translators: %d: number of orders that failed to sync */
				_n(
					'%d order failed to sync to Conta.',
					'%d orders failed to sync to Conta.',
					$errors,
					'ihumbak-woo-conta-api'
				),
				$errors
			);

			printf(
				'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
				esc_html( $message )
			);
		}
	}

	/**
	 * Check if an order should be synced.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return bool True if the order should be synced.
	 */
	private function should_sync_order( WC_Order $order ): bool {
		if ( ! $this->settings->is_configured() ) {
			return false;
		}

		/**
		 * Filter whether an order should be synced to Conta.
		 *
		 * @param bool     $should_sync Whether the order should be synced.
		 * @param WC_Order $order       WooCommerce order.
		 */
		return (bool) apply_filters( 'ihumbak_wca_should_sync_order', true, $order );
	}

	/**
	 * Schedule an async action via WooCommerce Action Scheduler.
	 *
	 * @param string $hook     Action hook name.
	 * @param int    $order_id WooCommerce order ID.
	 * @return void
	 */
	private function schedule_action( string $hook, int $order_id ): void {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time(),
				$hook,
				[ $order_id ],
				self::SCHEDULER_GROUP
			);

			$this->logger->debug(
				'Action scheduled',
				[
					'hook'     => $hook,
					'order_id' => $order_id,
				]
			);
		} else {
			// Fallback: execute synchronously if Action Scheduler not available.
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Hook names are prefixed (ihumbak_wca_*).
			do_action( $hook, $order_id );
		}
	}
}
