<?php
/**
 * Invoice Number column on the WooCommerce orders list table.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\Admin;

use Ihumbak\WooConta\Modules\InvoiceSync;

/**
 * Registers and renders an "Invoice Number" column on both the HPOS and legacy
 * WooCommerce orders list screens.
 */
class OrdersListColumn {

	/**
	 * Column identifier used as the array key and hook parameter.
	 *
	 * @var string
	 */
	private const COLUMN_KEY = 'ihumbak_wca_invoice_no';

	/**
	 * Register hooks for both HPOS and legacy order list screens.
	 *
	 * WooCommerce dispatches only the relevant hook for the active screen, so
	 * registering both unconditionally is safe.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'manage_woocommerce_page_wc-orders_columns', [ $this, 'add_column_hpos' ] );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', [ $this, 'render_column_hpos' ], 10, 2 );

		add_filter( 'manage_edit-shop_order_columns', [ $this, 'add_column_legacy' ] );
		add_action( 'manage_shop_order_posts_custom_column', [ $this, 'render_column_legacy' ], 10, 2 );
	}

	/**
	 * Append the Invoice Number column for the HPOS orders screen.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Modified columns.
	 */
	public function add_column_hpos( array $columns ): array {
		$columns[ self::COLUMN_KEY ] = __( 'Invoice Number', 'ihumbak-woo-conta-api' );

		return $columns;
	}

	/**
	 * Render the Invoice Number column cell for the HPOS orders screen.
	 *
	 * @param string $column Column key.
	 * @param mixed  $order  Current order object (WC_Order on HPOS screens).
	 * @return void
	 */
	public function render_column_hpos( string $column, $order ): void {
		if ( self::COLUMN_KEY !== $column ) {
			return;
		}

		$this->render_value( $order instanceof \WC_Order ? $order : null );
	}

	/**
	 * Append the Invoice Number column for the legacy (CPT) orders screen.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Modified columns.
	 */
	public function add_column_legacy( array $columns ): array {
		$columns[ self::COLUMN_KEY ] = __( 'Invoice Number', 'ihumbak-woo-conta-api' );

		return $columns;
	}

	/**
	 * Render the Invoice Number column cell for the legacy orders screen.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID of the current order.
	 * @return void
	 */
	public function render_column_legacy( string $column, int $post_id ): void {
		if ( self::COLUMN_KEY !== $column ) {
			return;
		}

		$order = wc_get_order( $post_id );

		$this->render_value( $order instanceof \WC_Order ? $order : null );
	}

	/**
	 * Output the invoice number value or an em dash placeholder.
	 *
	 * @param \WC_Order|null $order Order instance, or null when not resolvable.
	 * @return void
	 */
	private function render_value( ?\WC_Order $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			echo '&mdash;';
			return;
		}

		$value = trim( (string) $order->get_meta( InvoiceSync::META_INVOICE_NO ) );

		if ( '' !== $value ) {
			echo esc_html( $value );
		} else {
			echo '&mdash;';
		}
	}
}
