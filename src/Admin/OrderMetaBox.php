<?php
/**
 * Order meta box for Conta integration status and actions.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\Admin;

use Ihumbak\WooConta\Modules\CustomerSync;
use Ihumbak\WooConta\Modules\InvoiceSync;
use Ihumbak\WooConta\Modules\PaymentSync;
use WC_Order;
use WP_Post;

/**
 * Renders and handles the Conta integration meta box on the order edit screen.
 */
class OrderMetaBox {

	/**
	 * Invoice sync module.
	 *
	 * @var InvoiceSync
	 */
	private InvoiceSync $invoice_sync;

	/**
	 * Payment sync module.
	 *
	 * @var PaymentSync
	 */
	private PaymentSync $payment_sync;

	/**
	 * Constructor.
	 *
	 * @param InvoiceSync $invoice_sync Invoice sync module.
	 * @param PaymentSync $payment_sync Payment sync module.
	 */
	public function __construct( InvoiceSync $invoice_sync, PaymentSync $payment_sync ) {
		$this->invoice_sync = $invoice_sync;
		$this->payment_sync = $payment_sync;
	}

	/**
	 * Register hooks for the meta box and AJAX handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
		add_action( 'wp_ajax_ihumbak_wca_sync_order', [ $this, 'ajax_sync_order' ] );
		add_action( 'wp_ajax_ihumbak_wca_sync_payment', [ $this, 'ajax_sync_payment' ] );
	}

	/**
	 * Add the Conta integration meta box to order edit screens.
	 *
	 * Registers for both HPOS and legacy order screens.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		$screens = [ 'woocommerce_page_wc-orders', 'shop_order' ];

		foreach ( $screens as $screen ) {
			\add_meta_box(
				'ihumbak-wca-conta-status',
				__( 'Conta Integration', 'ihumbak-woo-conta-api' ),
				[ $this, 'render' ],
				$screen,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render the meta box content.
	 *
	 * @param WP_Post|WC_Order $post_or_order The post or order object.
	 * @return void
	 */
	public function render( $post_or_order ): void {
		$order = $this->get_order_from_request( $post_or_order );

		if ( ! $order instanceof WC_Order ) {
			return;
		}

		$sync_status  = $this->invoice_sync->get_sync_status( $order );
		$invoice_id   = $order->get_meta( InvoiceSync::META_INVOICE_ID );
		$invoice_no   = $order->get_meta( InvoiceSync::META_INVOICE_NO );
		$customer_id  = $order->get_meta( CustomerSync::META_CUSTOMER_ID );
		$sync_date    = $order->get_meta( InvoiceSync::META_SYNC_DATE );
		$sync_error   = $order->get_meta( InvoiceSync::META_SYNC_ERROR );
		$payment_done = $this->payment_sync->is_payment_synced( $order );
		$is_synced    = $this->invoice_sync->is_synced( $order );
		$order_id     = $order->get_id();

		$dash = '&#8212;';
		?>
		<div id="ihumbak-wca-meta-box" data-order-id="<?php echo esc_attr( (string) $order_id ); ?>">
			<table class="widefat striped" style="border:0;">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Sync Status', 'ihumbak-woo-conta-api' ); ?></strong></td>
						<td id="ihumbak-wca-sync-status">
							<?php $this->render_status_badge( $sync_status ); ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Invoice ID', 'ihumbak-woo-conta-api' ); ?></strong></td>
						<td id="ihumbak-wca-invoice-id">
							<?php echo $is_synced ? esc_html( (string) $invoice_id ) : wp_kses_post( $dash ); ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Invoice No', 'ihumbak-woo-conta-api' ); ?></strong></td>
						<td id="ihumbak-wca-invoice-no">
							<?php echo $is_synced ? esc_html( (string) $invoice_no ) : wp_kses_post( $dash ); ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Customer ID', 'ihumbak-woo-conta-api' ); ?></strong></td>
						<td id="ihumbak-wca-customer-id">
							<?php
							$customer_id_str = is_string( $customer_id ) ? $customer_id : '';
							echo '' !== $customer_id_str ? esc_html( $customer_id_str ) : wp_kses_post( $dash );
							?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Sync Date', 'ihumbak-woo-conta-api' ); ?></strong></td>
						<td id="ihumbak-wca-sync-date">
							<?php
							$sync_date_str = is_string( $sync_date ) ? $sync_date : '';
							echo '' !== $sync_date_str ? esc_html( $sync_date_str ) : wp_kses_post( $dash );
							?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Payment', 'ihumbak-woo-conta-api' ); ?></strong></td>
						<td id="ihumbak-wca-payment-status">
							<?php
							if ( $payment_done ) {
								echo '<span style="color:green;">' . esc_html__( 'Synced', 'ihumbak-woo-conta-api' ) . '</span>';
							} else {
								echo '<span style="color:#999;">' . esc_html__( 'Not synced', 'ihumbak-woo-conta-api' ) . '</span>';
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>

			<?php if ( 'error' === $sync_status && is_string( $sync_error ) && '' !== $sync_error ) : ?>
				<div id="ihumbak-wca-error" style="background:#fbeaea;border-left:4px solid #dc3232;padding:8px 12px;margin:12px 0;">
					<strong><?php esc_html_e( 'Error:', 'ihumbak-woo-conta-api' ); ?></strong>
					<?php echo esc_html( $sync_error ); ?>
				</div>
			<?php endif; ?>

			<div style="margin-top:12px;">
				<button type="button"
					class="button button-primary"
					id="ihumbak-wca-sync-order"
					style="width:100%;margin-bottom:8px;">
					<?php esc_html_e( 'Sync to Conta', 'ihumbak-woo-conta-api' ); ?>
				</button>

				<?php if ( $is_synced && ! $payment_done ) : ?>
					<button type="button"
						class="button"
						id="ihumbak-wca-sync-payment"
						style="width:100%;">
						<?php esc_html_e( 'Sync Payment', 'ihumbak-woo-conta-api' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<?php wp_nonce_field( 'ihumbak_wca_meta_box', 'ihumbak_wca_nonce' ); ?>
		</div>

		<script type="text/javascript">
			(function($) {
				var $box = $('#ihumbak-wca-meta-box');
				var orderId = $box.data('order-id');
				var nonce = $box.find('#ihumbak_wca_nonce').val();

				$box.on('click', '#ihumbak-wca-sync-order', function(e) {
					e.preventDefault();
					var $btn = $(this);
					$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Syncing...', 'ihumbak-woo-conta-api' ) ); ?>');

					$.post(ajaxurl, {
						action: 'ihumbak_wca_sync_order',
						order_id: orderId,
						_ajax_nonce: nonce
					}, function(response) {
						if (response.success) {
							$box.find('#ihumbak-wca-sync-status').html(response.data.status_badge);
							$box.find('#ihumbak-wca-invoice-id').text(response.data.invoice_id);
							$box.find('#ihumbak-wca-invoice-no').text(response.data.invoice_no);
							$box.find('#ihumbak-wca-customer-id').text(response.data.customer_id);
							$box.find('#ihumbak-wca-sync-date').text(response.data.sync_date);
							$box.find('#ihumbak-wca-error').remove();
							if (response.data.error) {
								$box.find('table').after(
									'<div id="ihumbak-wca-error" style="background:#fbeaea;border-left:4px solid #dc3232;padding:8px 12px;margin:12px 0;">' +
									'<strong><?php echo esc_js( __( 'Error:', 'ihumbak-woo-conta-api' ) ); ?></strong> ' +
									$('<span>').text(response.data.error).html() +
									'</div>'
								);
							}
						} else {
							alert(response.data || '<?php echo esc_js( __( 'Sync failed.', 'ihumbak-woo-conta-api' ) ); ?>');
						}
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Sync to Conta', 'ihumbak-woo-conta-api' ) ); ?>');
					}).fail(function() {
						alert('<?php echo esc_js( __( 'Request failed.', 'ihumbak-woo-conta-api' ) ); ?>');
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Sync to Conta', 'ihumbak-woo-conta-api' ) ); ?>');
					});
				});

				$box.on('click', '#ihumbak-wca-sync-payment', function(e) {
					e.preventDefault();
					var $btn = $(this);
					$btn.prop('disabled', true).text('<?php echo esc_js( __( 'Syncing...', 'ihumbak-woo-conta-api' ) ); ?>');

					$.post(ajaxurl, {
						action: 'ihumbak_wca_sync_payment',
						order_id: orderId,
						_ajax_nonce: nonce
					}, function(response) {
						if (response.success) {
							$box.find('#ihumbak-wca-payment-status').html(
								'<span style="color:green;"><?php echo esc_js( __( 'Synced', 'ihumbak-woo-conta-api' ) ); ?></span>'
							);
							$btn.remove();
						} else {
							alert(response.data || '<?php echo esc_js( __( 'Payment sync failed.', 'ihumbak-woo-conta-api' ) ); ?>');
							$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Sync Payment', 'ihumbak-woo-conta-api' ) ); ?>');
						}
					}).fail(function() {
						alert('<?php echo esc_js( __( 'Request failed.', 'ihumbak-woo-conta-api' ) ); ?>');
						$btn.prop('disabled', false).text('<?php echo esc_js( __( 'Sync Payment', 'ihumbak-woo-conta-api' ) ); ?>');
					});
				});
			})(jQuery);
		</script>
		<?php
	}

	/**
	 * AJAX handler for syncing an order to Conta.
	 *
	 * @return void
	 */
	public function ajax_sync_order(): void {
		check_ajax_referer( 'ihumbak_wca_meta_box' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'ihumbak-woo-conta-api' ), 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( 0 === $order_id ) {
			wp_send_json_error( __( 'Invalid order ID.', 'ihumbak-woo-conta-api' ), 400 );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( __( 'Order not found.', 'ihumbak-woo-conta-api' ), 404 );
		}

		$result = $this->invoice_sync->sync_order( $order );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// Re-read meta after sync.
		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( __( 'Order not found after sync.', 'ihumbak-woo-conta-api' ), 404 );
		}

		$sync_status = $this->invoice_sync->get_sync_status( $order );
		$sync_error  = $order->get_meta( InvoiceSync::META_SYNC_ERROR );

		wp_send_json_success(
			[
				'status_badge' => $this->get_status_badge_html( $sync_status ),
				'invoice_id'   => (string) $order->get_meta( InvoiceSync::META_INVOICE_ID ),
				'invoice_no'   => (string) $order->get_meta( InvoiceSync::META_INVOICE_NO ),
				'customer_id'  => (string) $order->get_meta( CustomerSync::META_CUSTOMER_ID ),
				'sync_date'    => (string) $order->get_meta( InvoiceSync::META_SYNC_DATE ),
				'error'        => is_string( $sync_error ) && '' !== $sync_error ? $sync_error : '',
			]
		);
	}

	/**
	 * AJAX handler for syncing a payment to Conta.
	 *
	 * @return void
	 */
	public function ajax_sync_payment(): void {
		check_ajax_referer( 'ihumbak_wca_meta_box' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( __( 'Permission denied.', 'ihumbak-woo-conta-api' ), 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

		if ( 0 === $order_id ) {
			wp_send_json_error( __( 'Invalid order ID.', 'ihumbak-woo-conta-api' ), 400 );
		}

		$order = wc_get_order( $order_id );

		if ( ! $order instanceof WC_Order ) {
			wp_send_json_error( __( 'Order not found.', 'ihumbak-woo-conta-api' ), 404 );
		}

		$result = $this->payment_sync->sync_payment( $order );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success(
			[
				'payment_synced' => true,
			]
		);
	}

	/**
	 * Get a WC_Order from a post or order object.
	 *
	 * Handles both legacy (WP_Post) and HPOS (WC_Order) edit screens.
	 *
	 * @param mixed $post_or_order The post or order object.
	 * @return WC_Order|null The WC_Order or null if not resolvable.
	 */
	private function get_order_from_request( mixed $post_or_order ): ?WC_Order {
		if ( $post_or_order instanceof WC_Order ) {
			return $post_or_order;
		}

		if ( $post_or_order instanceof WP_Post ) {
			$order = wc_get_order( $post_or_order->ID );

			if ( $order instanceof WC_Order ) {
				return $order;
			}
		}

		return null;
	}

	/**
	 * Render a sync status badge.
	 *
	 * @param string $status The sync status.
	 * @return void
	 */
	private function render_status_badge( string $status ): void {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is hardcoded HTML.
		echo $this->get_status_badge_html( $status );
	}

	/**
	 * Get the HTML for a sync status badge.
	 *
	 * @param string $status The sync status.
	 * @return string Badge HTML.
	 */
	private function get_status_badge_html( string $status ): string {
		switch ( $status ) {
			case 'synced':
				return '<span style="color:green;">' . esc_html__( 'Synced', 'ihumbak-woo-conta-api' ) . '</span>';
			case 'error':
				return '<span style="color:red;">' . esc_html__( 'Error', 'ihumbak-woo-conta-api' ) . '</span>';
			default:
				return '<span style="color:#999;">' . esc_html__( 'Not Synced', 'ihumbak-woo-conta-api' ) . '</span>';
		}
	}
}
