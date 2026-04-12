<?php
/**
 * Invoice line data model for Conta.no API.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\API\Models;

use Ihumbak\WooConta\Services\VatMapper;

/**
 * Represents a single line item on a Conta.no invoice.
 */
class InvoiceLine {

	/**
	 * Conta product ID.
	 *
	 * @var ?int
	 */
	public ?int $product_id = null;

	/**
	 * Line item description.
	 *
	 * @var string
	 */
	public string $description = '';

	/**
	 * Price per unit excluding VAT.
	 *
	 * @var float
	 */
	public float $price = 0.0;

	/**
	 * Quantity.
	 *
	 * @var float
	 */
	public float $quantity = 1.0;

	/**
	 * Discount percentage (0-100).
	 *
	 * @var float
	 */
	public float $discount = 0.0;

	/**
	 * Conta VAT code.
	 *
	 * @var string
	 */
	public string $vat_code = 'high';

	/**
	 * Line number on the invoice.
	 *
	 * @var int
	 */
	public int $line_no = 1;

	/**
	 * Convert to Conta API format.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$data = [
			'description' => $this->description,
			'price'       => $this->price,
			'quantity'    => $this->quantity,
			'discount'    => $this->discount,
			'vatCode'     => $this->vat_code,
			'lineNo'      => $this->line_no,
		];

		if ( null !== $this->product_id ) {
			$data['productId'] = $this->product_id;
		}

		return $data;
	}

	/**
	 * Create an InvoiceLine from a WooCommerce order product item.
	 *
	 * @param \WC_Order_Item_Product $item       WooCommerce order item.
	 * @param int                    $line_no    Line number on the invoice.
	 * @param VatMapper              $vat_mapper VAT code mapping service.
	 * @param \WC_Order              $order      Parent order (used for refund adjustments).
	 * @return self
	 */
	public static function from_wc_item( \WC_Order_Item_Product $item, int $line_no, VatMapper $vat_mapper, \WC_Order $order ): self {
		$line              = new self();
		$line->description = $item->get_name();
		$line->line_no     = $line_no;

		$original_qty = (float) $item->get_quantity();
		$refunded_qty = (float) abs( $order->get_qty_refunded_for_item( $item->get_id() ) );
		$net_qty      = $original_qty - $refunded_qty;

		$line->quantity = $net_qty;

		$subtotal    = (float) $item->get_subtotal();
		$line->price = ( $original_qty > 0 ) ? $subtotal / $original_qty : 0.0;

		$tax_class      = $item->get_tax_class();
		$line->vat_code = $vat_mapper->get_conta_vat_code( $tax_class );

		// Calculate discount from subtotal vs total (both ex. tax), adjusted for refunds.
		$refunded_amount = (float) $order->get_total_refunded_for_item( $item->get_id() );
		$net_total       = (float) $item->get_total() - $refunded_amount;
		$net_subtotal    = $line->price * $net_qty;

		if ( $net_subtotal > 0 && $net_total < $net_subtotal ) {
			$line->discount = round( ( ( $net_subtotal - $net_total ) / $net_subtotal ) * 100, 2 );
		}

		return $line;
	}

	/**
	 * Create an InvoiceLine from a WooCommerce shipping item.
	 *
	 * @param \WC_Order_Item_Shipping $shipping   WooCommerce shipping item.
	 * @param int                     $line_no    Line number on the invoice.
	 * @param VatMapper               $vat_mapper VAT code mapping service.
	 * @return self
	 */
	public static function from_shipping( \WC_Order_Item_Shipping $shipping, int $line_no, VatMapper $vat_mapper ): self {
		$line              = new self();
		$line->description = 'Shipping: ' . $shipping->get_method_title();
		$line->price       = (float) $shipping->get_total();
		$line->quantity    = 1.0;
		$line->line_no     = $line_no;

		$tax_class = $shipping->get_tax_class();

		/**
		 * Filter the VAT code used for shipping invoice lines.
		 *
		 * @param string                 $vat_code The resolved Conta VAT code.
		 * @param \WC_Order_Item_Shipping $shipping The WooCommerce shipping item.
		 */
		$line->vat_code = (string) apply_filters(
			'ihumbak_wca_shipping_vat_code',
			$vat_mapper->get_conta_vat_code( $tax_class ),
			$shipping
		);

		return $line;
	}

	/**
	 * Create an InvoiceLine from a WooCommerce fee item.
	 *
	 * @param \WC_Order_Item_Fee $fee        WooCommerce fee item.
	 * @param int                $line_no    Line number on the invoice.
	 * @param VatMapper          $vat_mapper VAT code mapping service.
	 * @return self
	 */
	public static function from_fee( \WC_Order_Item_Fee $fee, int $line_no, VatMapper $vat_mapper ): self {
		$line              = new self();
		$line->description = $fee->get_name();
		$line->price       = (float) $fee->get_total();
		$line->quantity    = 1.0;
		$line->line_no     = $line_no;
		$line->vat_code    = $vat_mapper->get_conta_vat_code( $fee->get_tax_class() );

		return $line;
	}
}
