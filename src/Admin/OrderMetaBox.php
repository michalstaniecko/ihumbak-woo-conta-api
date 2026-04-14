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
use Ihumbak\WooConta\Services\Settings;
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
	 * Customer sync module.
	 *
	 * @var CustomerSync
	 */
	private CustomerSync $customer_sync;

	/**
	 * Plugin settings service.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param InvoiceSync  $invoice_sync  Invoice sync module.
	 * @param PaymentSync  $payment_sync  Payment sync module.
	 * @param CustomerSync $customer_sync Customer sync module.
	 * @param Settings     $settings      Plugin settings service.
	 */
	public function __construct( InvoiceSync $invoice_sync, PaymentSync $payment_sync, CustomerSync $customer_sync, Settings $settings ) {
		$this->invoice_sync  = $invoice_sync;
		$this->payment_sync  = $payment_sync;
		$this->customer_sync = $customer_sync;
		$this->settings      = $settings;
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
		add_action( 'wp_ajax_ihumbak_wca_manual_invoice', [ $this, 'ajax_manual_invoice' ] );
		add_action( 'wp_ajax_ihumbak_wca_search_customers', [ $this, 'ajax_search_customers' ] );
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
		$is_assigned  = $is_synced || 'manual' === $sync_status;
		$order_id     = $order->get_id();

		$invoice_type = $order->get_meta( InvoiceSync::META_INVOICE_TYPE );
		$dash         = '&#8212;';
		?>
		<div id="ihumbak-wca-meta-box" data-order-id="<?php echo esc_attr( (string) $order_id ); ?>">
			<div id="ihumbak-wca-sync-overlay">
				<span class="spinner"></span>
				<span class="ihumbak-wca-sync-message"></span>
			</div>
			<table class="widefat striped" style="border:0;">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Sync Status', 'ihumbak-woo-conta-api' ); ?></strong></td>
						<td id="ihumbak-wca-sync-status" data-status="<?php echo esc_attr( $sync_status ); ?>">
							<?php $this->render_status_badge( $sync_status ); ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Invoice ID', 'ihumbak-woo-conta-api' ); ?></strong></td>
						<td id="ihumbak-wca-invoice-id">
							<?php echo $is_assigned ? esc_html( (string) $invoice_id ) : wp_kses_post( $dash ); ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Invoice No', 'ihumbak-woo-conta-api' ); ?></strong></td>
						<td id="ihumbak-wca-invoice-no">
							<?php echo $is_assigned ? esc_html( (string) $invoice_no ) : wp_kses_post( $dash ); ?>
						</td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Invoice Type', 'ihumbak-woo-conta-api' ); ?></strong></td>
						<td id="ihumbak-wca-invoice-type">
							<?php
							$invoice_type_str = is_string( $invoice_type ) ? $invoice_type : '';
							echo $is_assigned && '' !== $invoice_type_str ? esc_html( $invoice_type_str ) : wp_kses_post( $dash );
							?>
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
				<?php if ( ! $is_assigned ) : ?>
					<button type="button"
						class="button button-primary ihumbak-wca-sync-order-btn"
						style="width:100%;margin-bottom:8px;">
						<?php esc_html_e( 'Create Invoice', 'ihumbak-woo-conta-api' ); ?>
					</button>
				<?php endif; ?>

				<div id="ihumbak-wca-customer-selection" style="display:none;"></div>

				<?php if ( $is_assigned && ! $payment_done ) : ?>
					<button type="button"
						class="button"
						id="ihumbak-wca-sync-payment"
						style="width:100%;">
						<?php esc_html_e( 'Sync Payment', 'ihumbak-woo-conta-api' ); ?>
					</button>
				<?php endif; ?>
			</div>

			<div id="ihumbak-wca-manual-assign" style="margin-top:12px;border-top:1px solid #ddd;padding-top:12px;">
				<a href="#" id="ihumbak-wca-manual-toggle" style="text-decoration:none;">
					<?php esc_html_e( 'Assign invoice manually', 'ihumbak-woo-conta-api' ); ?> &darr;
				</a>
				<div id="ihumbak-wca-manual-form" style="display:none;margin-top:8px;">
					<p style="margin:0 0 6px;">
						<label for="ihumbak-wca-manual-invoice-id" style="display:block;font-weight:600;margin-bottom:2px;">
							<?php esc_html_e( 'Invoice ID', 'ihumbak-woo-conta-api' ); ?>
						</label>
						<input type="text" id="ihumbak-wca-manual-invoice-id" style="width:100%;"
							placeholder="<?php esc_attr_e( 'Conta invoice ID', 'ihumbak-woo-conta-api' ); ?>" />
					</p>
					<p style="margin:0 0 8px;">
						<label for="ihumbak-wca-manual-invoice-no" style="display:block;font-weight:600;margin-bottom:2px;">
							<?php esc_html_e( 'Invoice No', 'ihumbak-woo-conta-api' ); ?>
						</label>
						<input type="text" id="ihumbak-wca-manual-invoice-no" style="width:100%;"
							placeholder="<?php esc_attr_e( 'e.g. 10042', 'ihumbak-woo-conta-api' ); ?>" />
					</p>
					<button type="button" class="button button-primary" id="ihumbak-wca-manual-save"
						style="width:100%;margin-bottom:4px;">
						<?php esc_html_e( 'Save', 'ihumbak-woo-conta-api' ); ?>
					</button>
					<a href="#" id="ihumbak-wca-manual-cancel" style="display:block;text-align:center;margin-top:4px;">
						<?php esc_html_e( 'Cancel', 'ihumbak-woo-conta-api' ); ?>
					</a>
				</div>
			</div>

			<?php wp_nonce_field( 'ihumbak_wca_meta_box', 'ihumbak_wca_nonce' ); ?>
		</div>

		<style>
			#ihumbak-wca-customer-selection { font-size:12px; }
			.ihumbak-wca-card {
				border:1px solid #ddd; border-radius:4px; padding:8px 10px; margin-bottom:6px;
				cursor:pointer; position:relative;
			}
			.ihumbak-wca-card:hover { border-color:#2271b1; }
			.ihumbak-wca-card.ihumbak-wca-card--selected { border-color:#2271b1; background:#f0f7ff; }
			.ihumbak-wca-card.ihumbak-wca-card--order { background:#f9f9f9; border-style:dashed; cursor:default; }
			.ihumbak-wca-card label { display:flex; align-items:flex-start; gap:6px; cursor:pointer; margin:0; }
			.ihumbak-wca-card input[type="radio"] { margin-top:2px; flex-shrink:0; }
			.ihumbak-wca-card-body { flex:1; min-width:0; }
			.ihumbak-wca-card-name { font-weight:600; word-break:break-word; }
			.ihumbak-wca-card-detail { color:#646970; word-break:break-all; }
			.ihumbak-wca-card-row { display:flex; gap:8px; flex-wrap:wrap; }
			.ihumbak-wca-selection-actions { margin-top:8px; text-align:center; }
			.ihumbak-wca-selection-actions .button { margin:0 4px; }
			#ihumbak-wca-meta-box { position: relative; }
			#ihumbak-wca-sync-overlay {
				display: none;
				position: absolute;
				top: 0; left: 0; right: 0; bottom: 0;
				background: rgba(255, 255, 255, 0.7);
				z-index: 10;
				align-items: center;
				justify-content: center;
				flex-direction: column;
				gap: 8px;
			}
			#ihumbak-wca-sync-overlay.active { display: flex; }
			#ihumbak-wca-sync-overlay .spinner { visibility: visible; float: none; margin: 0; }
			.ihumbak-wca-sync-message { font-weight: 600; color: #2271b1; font-size: 13px; }
		</style>

		<script type="text/javascript">
			(function($) {
				var $box = $('#ihumbak-wca-meta-box');
				var orderId = $box.data('order-id');
				var nonce = $box.find('#ihumbak_wca_nonce').val();

				function escHtml(str) {
					if (!str) return '';
					return $('<span>').text(str).html();
				}

				var syncInFlight = false;

				function beforeUnloadHandler(e) {
					e.preventDefault();
					e.returnValue = '';
				}

				function showSyncOverlay(message) {
					syncInFlight = true;
					$box.find('#ihumbak-wca-sync-overlay .ihumbak-wca-sync-message').text(message);
					$box.find('#ihumbak-wca-sync-overlay').addClass('active');
					$box.find('button, input, select, a').not('#ihumbak-wca-sync-overlay *').prop('disabled', true).css('pointer-events', 'none');
					window.addEventListener('beforeunload', beforeUnloadHandler);
				}

				function hideSyncOverlay() {
					syncInFlight = false;
					$box.find('#ihumbak-wca-sync-overlay').removeClass('active');
					$box.find('button, input, select, a').prop('disabled', false).css('pointer-events', '');
					window.removeEventListener('beforeunload', beforeUnloadHandler);
				}

				function doSyncOrder(selectedCustomerId, forceCreate) {
					showSyncOverlay('<?php echo esc_js( __( 'Creating invoice...', 'ihumbak-woo-conta-api' ) ); ?>');

					var postData = {
						action: 'ihumbak_wca_sync_order',
						order_id: orderId,
						ihumbak_wca_nonce: nonce
					};

					if (selectedCustomerId > 0) {
						postData.selected_customer_id = selectedCustomerId;
					}
					if (forceCreate) {
						postData.force_create_customer = 1;
					}

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: postData,
						dataType: 'json',
						success: function(response) {
							hideSyncOverlay();
							if (response.success) {
								$box.find('#ihumbak-wca-sync-status').html(response.data.status_badge);
								$box.find('#ihumbak-wca-invoice-id').text(response.data.invoice_id);
								$box.find('#ihumbak-wca-invoice-no').text(response.data.invoice_no);
								$box.find('#ihumbak-wca-invoice-type').text(response.data.invoice_type);
								$box.find('#ihumbak-wca-customer-id').text(response.data.customer_id);
								$box.find('#ihumbak-wca-sync-date').text(response.data.sync_date);
								$box.find('#ihumbak-wca-error').remove();
								$box.find('.ihumbak-wca-sync-order-btn').remove();
								$box.find('#ihumbak-wca-customer-selection').empty().hide();
								if (response.data.error) {
									$box.find('table').after(
										'<div id="ihumbak-wca-error" style="background:#fbeaea;border-left:4px solid #dc3232;padding:8px 12px;margin:12px 0;">' +
										'<strong><?php echo esc_js( __( 'Error:', 'ihumbak-woo-conta-api' ) ); ?></strong> ' +
										escHtml(response.data.error) +
										'</div>'
									);
								}
							} else {
								var errorMsg = (response.data && response.data.message) ? response.data.message : (response.data || '<?php echo esc_js( __( 'Sync failed.', 'ihumbak-woo-conta-api' ) ); ?>');
								alert(errorMsg);
							}
						},
						error: function(xhr, status, error) {
							hideSyncOverlay();
							alert('<?php echo esc_js( __( 'AJAX Error:', 'ihumbak-woo-conta-api' ) ); ?> ' + status + ' - ' + error);
						}
					});
				}

				function buildCardDetail(items) {
					var parts = [];
					for (var i = 0; i < items.length; i++) {
						if (items[i].val) {
							parts.push('<span class="ihumbak-wca-card-detail">' + escHtml(items[i].val) + '</span>');
						}
					}
					return parts.length ? '<div class="ihumbak-wca-card-row">' + parts.join('') + '</div>' : '';
				}

				function showCustomerSelection(customers, orderBilling) {
					var $sel = $box.find('#ihumbak-wca-customer-selection');
					var html = '<p style="font-weight:600;margin:0 0 8px;">'
						+ '<?php echo esc_js( __( 'Multiple matching customers found. Please select:', 'ihumbak-woo-conta-api' ) ); ?>'
						+ '</p>';

					// Order billing card for comparison.
					html += '<div class="ihumbak-wca-card ihumbak-wca-card--order">';
					html += '<div class="ihumbak-wca-card-body">';
					html += '<div class="ihumbak-wca-card-detail" style="font-style:italic;margin-bottom:2px;"><?php echo esc_js( __( 'Order billing data:', 'ihumbak-woo-conta-api' ) ); ?></div>';
					html += '<div class="ihumbak-wca-card-name">' + escHtml(orderBilling.company || orderBilling.name) + '</div>';
					html += buildCardDetail([
						{val: orderBilling.email},
						{val: orderBilling.vat ? 'Org: ' + orderBilling.vat : ''},
						{val: orderBilling.city}
					]);
					html += '</div></div>';

					// Customer cards with radio buttons.
					for (var i = 0; i < customers.length; i++) {
						var c = customers[i];
						var checked = (i === 0) ? ' checked' : '';
						var selectedClass = (i === 0) ? ' ihumbak-wca-card--selected' : '';
						html += '<div class="ihumbak-wca-card' + selectedClass + '">';
						html += '<label>';
						html += '<input type="radio" name="ihumbak_wca_customer" value="' + c.id + '"' + checked + ' />';
						html += '<div class="ihumbak-wca-card-body">';
						html += '<div class="ihumbak-wca-card-name">' + escHtml(c.name) + ' <span class="ihumbak-wca-card-detail">#' + c.id + '</span></div>';
						html += buildCardDetail([
							{val: c.email},
							{val: c.orgNo ? 'Org: ' + c.orgNo : ''},
							{val: c.city}
						]);
						html += '</div></label></div>';
					}

					// "Create new customer" option.
					html += '<div class="ihumbak-wca-card">';
					html += '<label>';
					html += '<input type="radio" name="ihumbak_wca_customer" value="0" />';
					html += '<div class="ihumbak-wca-card-body">';
					html += '<div class="ihumbak-wca-card-name" style="font-style:italic;"><?php echo esc_js( __( 'Create new customer from order data', 'ihumbak-woo-conta-api' ) ); ?></div>';
					html += '</div></label></div>';

					html += '<div class="ihumbak-wca-selection-actions">';
					html += '<button type="button" class="button button-primary" id="ihumbak-wca-use-selected">'
						+ '<?php echo esc_js( __( 'Use Selected', 'ihumbak-woo-conta-api' ) ); ?></button>';
					html += ' <button type="button" class="button" id="ihumbak-wca-cancel-selection">'
						+ '<?php echo esc_js( __( 'Cancel', 'ihumbak-woo-conta-api' ) ); ?></button>';
					html += '</div>';

					$sel.html(html).slideDown(200);
					$box.find('.ihumbak-wca-sync-order-btn').hide();

					// Highlight selected card.
					$sel.on('change', 'input[name="ihumbak_wca_customer"]', function() {
						$sel.find('.ihumbak-wca-card').removeClass('ihumbak-wca-card--selected');
						$(this).closest('.ihumbak-wca-card').addClass('ihumbak-wca-card--selected');
					});
				}

				// Sync order button click — Phase 1: search for matching customers.
				$box.on('click', '.ihumbak-wca-sync-order-btn', function(e) {
					e.preventDefault();
					if (syncInFlight) return;
					showSyncOverlay('<?php echo esc_js( __( 'Searching customers...', 'ihumbak-woo-conta-api' ) ); ?>');

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'ihumbak_wca_search_customers',
							order_id: orderId,
							ihumbak_wca_nonce: nonce
						},
						dataType: 'json',
						success: function(response) {
							if (!response.success) {
								hideSyncOverlay();
								alert(response.data || '<?php echo esc_js( __( 'Customer search failed.', 'ihumbak-woo-conta-api' ) ); ?>');
								return;
							}

							var count = response.data.count;

							if (count <= 1) {
								// 0 or 1 match: proceed directly (overlay stays active, doSyncOrder updates message).
								var selectedId = (count === 1) ? response.data.customers[0].id : 0;
								doSyncOrder(selectedId, false);
								return;
							}

							// Multiple matches: hide overlay and show selection UI.
							hideSyncOverlay();
							showCustomerSelection(response.data.customers, response.data.order_billing);
						},
						error: function(xhr, status, error) {
							hideSyncOverlay();
							alert('<?php echo esc_js( __( 'AJAX Error:', 'ihumbak-woo-conta-api' ) ); ?> ' + status + ' - ' + error);
						}
					});
				});

				// Customer selection — Use Selected button.
				$box.on('click', '#ihumbak-wca-use-selected', function(e) {
					e.preventDefault();
					var $sel = $box.find('#ihumbak-wca-customer-selection');
					var selectedId = parseInt($sel.find('input[name="ihumbak_wca_customer"]:checked').val(), 10) || 0;
					var forceCreate = (selectedId === 0);

					$sel.slideUp(200);
					doSyncOrder(selectedId, forceCreate);
				});

				// Customer selection — Cancel button.
				$box.on('click', '#ihumbak-wca-cancel-selection', function(e) {
					e.preventDefault();
					$box.find('#ihumbak-wca-customer-selection').slideUp(200);
					$box.find('.ihumbak-wca-sync-order-btn').show().prop('disabled', false);
				});

				$box.on('click', '#ihumbak-wca-manual-toggle', function(e) {
					e.preventDefault();
					$('#ihumbak-wca-manual-form').slideToggle(200);
				});

				$box.on('click', '#ihumbak-wca-manual-cancel', function(e) {
					e.preventDefault();
					$('#ihumbak-wca-manual-form').slideUp(200);
				});

				$box.on('click', '#ihumbak-wca-manual-save', function(e) {
					e.preventDefault();
					var invoiceId = $.trim($('#ihumbak-wca-manual-invoice-id').val());
					var invoiceNo = $.trim($('#ihumbak-wca-manual-invoice-no').val());

					if (!invoiceId && !invoiceNo) {
						alert('<?php echo esc_js( __( 'Please enter at least an Invoice ID or Invoice Number.', 'ihumbak-woo-conta-api' ) ); ?>');
						return;
					}

					var currentStatus = $box.find('#ihumbak-wca-sync-status').data('status');
					if (currentStatus === 'synced') {
						if (!confirm('<?php echo esc_js( __( 'This order is already synced via API. Overwriting will replace the existing invoice data. Continue?', 'ihumbak-woo-conta-api' ) ); ?>')) {
							return;
						}
					}

					if (syncInFlight) return;
					showSyncOverlay('<?php echo esc_js( __( 'Saving invoice assignment...', 'ihumbak-woo-conta-api' ) ); ?>');

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'ihumbak_wca_manual_invoice',
							order_id: orderId,
							invoice_id: invoiceId,
							invoice_no: invoiceNo,
							ihumbak_wca_nonce: nonce
						},
						dataType: 'json',
						success: function(response) {
							hideSyncOverlay();
							if (response.success) {
								$box.find('#ihumbak-wca-sync-status').data('status', 'manual').html(response.data.status_badge);
								$box.find('#ihumbak-wca-invoice-id').text(response.data.invoice_id || '\u2014');
								$box.find('#ihumbak-wca-invoice-no').text(response.data.invoice_no || '\u2014');
								$box.find('#ihumbak-wca-sync-date').text(response.data.sync_date);
								$box.find('#ihumbak-wca-error').remove();
								$box.find('.ihumbak-wca-sync-order-btn').remove();
								$('#ihumbak-wca-manual-form').slideUp(200);
								$('#ihumbak-wca-manual-invoice-id').val('');
								$('#ihumbak-wca-manual-invoice-no').val('');
							} else {
								var errorMsg = (response.data && response.data.message) ? response.data.message : (response.data || '<?php echo esc_js( __( 'Save failed.', 'ihumbak-woo-conta-api' ) ); ?>');
								alert(errorMsg);
							}
						},
						error: function(xhr, status, error) {
							hideSyncOverlay();
							alert('AJAX Error: ' + status + ' - ' + error);
						}
					});
				});

				$box.on('click', '#ihumbak-wca-sync-payment', function(e) {
					e.preventDefault();
					if (syncInFlight) return;
					var $btn = $(this);
					showSyncOverlay('<?php echo esc_js( __( 'Syncing payment...', 'ihumbak-woo-conta-api' ) ); ?>');

					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'ihumbak_wca_sync_payment',
							order_id: orderId,
							ihumbak_wca_nonce: nonce
						},
						dataType: 'json',
						success: function(response) {
							hideSyncOverlay();
							if (response.success) {
								$box.find('#ihumbak-wca-payment-status').html(
									'<span style="color:green;"><?php echo esc_js( __( 'Synced', 'ihumbak-woo-conta-api' ) ); ?></span>'
								);
								$btn.remove();
							} else {
								alert(response.data || '<?php echo esc_js( __( 'Payment sync failed.', 'ihumbak-woo-conta-api' ) ); ?>');
							}
						},
						error: function(xhr, status, error) {
							hideSyncOverlay();
							alert('AJAX Error: ' + status + ' - ' + error);
						}
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
		check_ajax_referer( 'ihumbak_wca_meta_box', 'ihumbak_wca_nonce' );

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

		$selected_customer_id  = isset( $_POST['selected_customer_id'] ) ? absint( $_POST['selected_customer_id'] ) : 0;
		$force_create_customer = ! empty( $_POST['force_create_customer'] );

		$result = $this->invoice_sync->sync_order( $order, '', $selected_customer_id, $force_create_customer );

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
				'invoice_type' => (string) $order->get_meta( InvoiceSync::META_INVOICE_TYPE ),
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
		check_ajax_referer( 'ihumbak_wca_meta_box', 'ihumbak_wca_nonce' );

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
	 * AJAX handler for manually assigning invoice data to an order.
	 *
	 * @return void
	 */
	public function ajax_manual_invoice(): void {
		check_ajax_referer( 'ihumbak_wca_meta_box', 'ihumbak_wca_nonce' );

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

		$invoice_id = isset( $_POST['invoice_id'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice_id'] ) ) : '';
		$invoice_no = isset( $_POST['invoice_no'] ) ? sanitize_text_field( wp_unslash( $_POST['invoice_no'] ) ) : '';

		if ( '' === $invoice_id && '' === $invoice_no ) {
			wp_send_json_error( __( 'Please provide at least an Invoice ID or Invoice Number.', 'ihumbak-woo-conta-api' ) );
		}

		$order->update_meta_data( InvoiceSync::META_INVOICE_ID, $invoice_id );
		$order->update_meta_data( InvoiceSync::META_INVOICE_NO, $invoice_no );

		$order->update_meta_data( InvoiceSync::META_SYNC_STATUS, 'manual' );
		$order->update_meta_data( InvoiceSync::META_SYNC_DATE, gmdate( 'c' ) );
		$order->delete_meta_data( InvoiceSync::META_SYNC_ERROR );
		$order->save();

		wp_send_json_success(
			[
				'status_badge' => $this->get_status_badge_html( 'manual' ),
				'invoice_id'   => $invoice_id,
				'invoice_no'   => $invoice_no,
				'sync_date'    => (string) $order->get_meta( InvoiceSync::META_SYNC_DATE ),
			]
		);
	}

	/**
	 * AJAX handler for searching Conta customers matching an order.
	 *
	 * Returns all matching customers so the admin can select one before invoice creation.
	 *
	 * @return void
	 */
	public function ajax_search_customers(): void {
		check_ajax_referer( 'ihumbak_wca_meta_box', 'ihumbak_wca_nonce' );

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

		$matches = $this->customer_sync->find_all_matches( $order );

		if ( is_wp_error( $matches ) ) {
			wp_send_json_error( $matches->get_error_message() );
		}

		$order_billing = [
			'name'     => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'company'  => $order->get_billing_company(),
			'email'    => $order->get_billing_email(),
			'phone'    => $order->get_billing_phone(),
			'address'  => $order->get_billing_address_1(),
			'city'     => $order->get_billing_city(),
			'postcode' => $order->get_billing_postcode(),
			'vat'      => $this->get_order_vat_number( $order ),
		];

		$customers = [];
		foreach ( $matches as $customer ) {
			$customers[] = [
				'id'       => (int) ( $customer['id'] ?? 0 ),
				'name'     => (string) ( $customer['name'] ?? '' ),
				'email'    => (string) ( $customer['emailAddress'] ?? '' ),
				'orgNo'    => (string) ( $customer['orgNo'] ?? '' ),
				'phone'    => (string) ( $customer['phoneNo'] ?? '' ),
				'address'  => (string) ( $customer['customerAddressLine1'] ?? '' ),
				'city'     => (string) ( $customer['customerAddressCity'] ?? '' ),
				'postcode' => (string) ( $customer['customerAddressPostcode'] ?? '' ),
			];
		}

		wp_send_json_success(
			[
				'customers'     => $customers,
				'order_billing' => $order_billing,
				'count'         => count( $customers ),
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
	 * Get the VAT number from the order based on the configured meta field.
	 *
	 * @param WC_Order $order WooCommerce order.
	 * @return string VAT number or empty string.
	 */
	private function get_order_vat_number( WC_Order $order ): string {
		$vat_field = $this->settings->get_vat_number_field();

		if ( '' === $vat_field ) {
			return '';
		}

		return (string) $order->get_meta( $vat_field );
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
			case 'manual':
				return '<span style="color:#2271b1;">&#9998; ' . esc_html__( 'Manual', 'ihumbak-woo-conta-api' ) . '</span>';
			case 'error':
				return '<span style="color:red;">' . esc_html__( 'Error', 'ihumbak-woo-conta-api' ) . '</span>';
			default:
				return '<span style="color:#999;">' . esc_html__( 'Not Synced', 'ihumbak-woo-conta-api' ) . '</span>';
		}
	}
}
