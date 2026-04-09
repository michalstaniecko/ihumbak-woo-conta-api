<?php
/**
 * Invoice data model for Conta.no API.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\API\Models;

use Ihumbak\WooConta\Services\Settings;
use Ihumbak\WooConta\Services\VatMapper;

/**
 * Represents an invoice in the Conta.no system.
 */
class Invoice {

	/**
	 * Conta invoice ID.
	 *
	 * @var ?int
	 */
	public ?int $id = null;

	/**
	 * Conta invoice number.
	 *
	 * @var ?int
	 */
	public ?int $invoice_no = null;

	/**
	 * Conta customer ID.
	 *
	 * @var int
	 */
	public int $customer_id = 0;

	/**
	 * Invoice line items.
	 *
	 * @var array<int, InvoiceLine>
	 */
	public array $invoice_lines = [];

	/**
	 * Invoice date (YYYY-MM-DD).
	 *
	 * @var string
	 */
	public string $invoice_date = '';

	/**
	 * Invoice due date (YYYY-MM-DD).
	 *
	 * @var string
	 */
	public string $invoice_due_date = '';

	/**
	 * Invoice type (NORMAL, CREDIT, etc.).
	 *
	 * @var string
	 */
	public string $type = 'NORMAL';

	/**
	 * Invoice language code.
	 *
	 * @var string
	 */
	public string $invoice_language = 'NO';

	/**
	 * Invoice currency code.
	 *
	 * @var string
	 */
	public string $invoice_currency = 'NOK';

	/**
	 * Customer reference (e.g., WC order number).
	 *
	 * @var string
	 */
	public string $customer_reference = '';

	/**
	 * Personal message on the invoice.
	 *
	 * @var string
	 */
	public string $personal_message = '';

	/**
	 * Delivery address line.
	 *
	 * @var string
	 */
	public string $delivery_address = '';

	/**
	 * Delivery postcode.
	 *
	 * @var string
	 */
	public string $delivery_postcode = '';

	/**
	 * Delivery city.
	 *
	 * @var string
	 */
	public string $delivery_city = '';

	/**
	 * Delivery country code.
	 *
	 * @var string
	 */
	public string $delivery_country = '';

	/**
	 * Invoice status from Conta.
	 *
	 * @var ?string
	 */
	public ?string $status = null;

	/**
	 * Total amount including VAT.
	 *
	 * @var ?float
	 */
	public ?float $sum_total = null;

	/**
	 * Net amount excluding VAT.
	 *
	 * @var ?float
	 */
	public ?float $sum_net = null;

	/**
	 * VAT amount.
	 *
	 * @var ?float
	 */
	public ?float $sum_vat = null;

	/**
	 * Whether to show discount column on the invoice.
	 *
	 * @var bool
	 */
	public bool $show_discount = false;

	/**
	 * Convert to Conta API format.
	 *
	 * Only includes non-null and non-empty fields.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$data = [];

		if ( null !== $this->id ) {
			$data['id'] = $this->id;
		}

		if ( null !== $this->invoice_no ) {
			$data['invoiceNo'] = $this->invoice_no;
		}

		if ( 0 !== $this->customer_id ) {
			$data['customerId'] = $this->customer_id;
		}

		if ( [] !== $this->invoice_lines ) {
			$data['invoiceLines'] = array_map(
				static fn( InvoiceLine $line ): array => $line->to_array(),
				$this->invoice_lines,
			);
		}

		if ( '' !== $this->invoice_date ) {
			$data['invoiceDate'] = $this->invoice_date;
		}

		if ( '' !== $this->invoice_due_date ) {
			$data['invoiceDueDate'] = $this->invoice_due_date;
		}

		if ( '' !== $this->type ) {
			$data['type'] = $this->type;
		}

		if ( '' !== $this->invoice_language ) {
			$data['invoiceLanguage'] = $this->invoice_language;
		}

		if ( '' !== $this->invoice_currency ) {
			$data['invoiceCurrency'] = $this->invoice_currency;
		}

		if ( '' !== $this->customer_reference ) {
			$data['customerReference'] = $this->customer_reference;
		}

		if ( '' !== $this->personal_message ) {
			$data['personalMessage'] = $this->personal_message;
		}

		if ( '' !== $this->delivery_address ) {
			$data['deliveryAddress'] = $this->delivery_address;
		}

		if ( '' !== $this->delivery_postcode ) {
			$data['deliveryPostcode'] = $this->delivery_postcode;
		}

		if ( '' !== $this->delivery_city ) {
			$data['deliveryCity'] = $this->delivery_city;
		}

		if ( '' !== $this->delivery_country ) {
			$data['deliveryCountry'] = $this->delivery_country;
		}

		if ( null !== $this->status ) {
			$data['status'] = $this->status;
		}

		if ( null !== $this->sum_total ) {
			$data['sumTotal'] = $this->sum_total;
		}

		if ( null !== $this->sum_net ) {
			$data['sumNet'] = $this->sum_net;
		}

		if ( null !== $this->sum_vat ) {
			$data['sumVat'] = $this->sum_vat;
		}

		$data['showDiscount'] = $this->show_discount;

		return $data;
	}

	/**
	 * Create an Invoice from Conta API response data.
	 *
	 * @param array<string, mixed> $data Conta API response array.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$invoice                     = new self();
		$invoice->id                 = isset( $data['id'] ) ? (int) $data['id'] : null;
		$invoice->invoice_no         = isset( $data['invoiceNo'] ) ? (int) $data['invoiceNo'] : null;
		$invoice->customer_id        = (int) ( $data['customerId'] ?? 0 );
		$invoice->invoice_date       = (string) ( $data['invoiceDate'] ?? '' );
		$invoice->invoice_due_date   = (string) ( $data['invoiceDueDate'] ?? '' );
		$invoice->type               = (string) ( $data['type'] ?? 'NORMAL' );
		$invoice->invoice_language   = (string) ( $data['invoiceLanguage'] ?? 'NO' );
		$invoice->invoice_currency   = (string) ( $data['invoiceCurrency'] ?? 'NOK' );
		$invoice->customer_reference = (string) ( $data['customerReference'] ?? '' );
		$invoice->personal_message   = (string) ( $data['personalMessage'] ?? '' );
		$invoice->delivery_address   = (string) ( $data['deliveryAddress'] ?? '' );
		$invoice->delivery_postcode  = (string) ( $data['deliveryPostcode'] ?? '' );
		$invoice->delivery_city      = (string) ( $data['deliveryCity'] ?? '' );
		$invoice->delivery_country   = (string) ( $data['deliveryCountry'] ?? '' );
		$invoice->status             = isset( $data['status'] ) ? (string) $data['status'] : null;
		$invoice->sum_total          = isset( $data['sumTotal'] ) ? (float) $data['sumTotal'] : null;
		$invoice->sum_net            = isset( $data['sumNet'] ) ? (float) $data['sumNet'] : null;
		$invoice->sum_vat            = isset( $data['sumVat'] ) ? (float) $data['sumVat'] : null;
		$invoice->show_discount      = (bool) ( $data['showDiscount'] ?? false );

		if ( isset( $data['invoiceLines'] ) && is_array( $data['invoiceLines'] ) ) {
			foreach ( $data['invoiceLines'] as $line_data ) {
				if ( ! is_array( $line_data ) ) {
					continue;
				}

				$line              = new InvoiceLine();
				$line->product_id  = isset( $line_data['productId'] ) ? (int) $line_data['productId'] : null;
				$line->description = (string) ( $line_data['description'] ?? '' );
				$line->price       = (float) ( $line_data['price'] ?? 0.0 );
				$line->quantity    = (float) ( $line_data['quantity'] ?? 1.0 );
				$line->discount    = (float) ( $line_data['discount'] ?? 0.0 );
				$line->vat_code    = (string) ( $line_data['vatCode'] ?? 'high' );
				$line->line_no     = (int) ( $line_data['lineNo'] ?? 1 );

				$invoice->invoice_lines[] = $line;
			}
		}

		return $invoice;
	}

	/**
	 * Create an Invoice from a WooCommerce order.
	 *
	 * @param \WC_Order $order       WooCommerce order instance.
	 * @param int       $customer_id Conta customer ID.
	 * @param VatMapper $vat_mapper  VAT code mapping service.
	 * @param Settings  $settings    Plugin settings service.
	 * @return self
	 */
	public static function from_wc_order(
		\WC_Order $order,
		int $customer_id,
		VatMapper $vat_mapper,
		Settings $settings
	): self {
		$invoice              = new self();
		$invoice->customer_id = $customer_id;

		// Dates.
		$order_date = $order->get_date_created();
		$date       = ( null !== $order_date ) ? $order_date->date( 'Y-m-d' ) : gmdate( 'Y-m-d' );

		$invoice->invoice_date = $date;

		$due_date_offset           = $settings->get_due_date_offset();
		$due_timestamp             = strtotime( $date . ' +' . $due_date_offset . ' days' );
		$invoice->invoice_due_date = ( false !== $due_timestamp ) ? gmdate( 'Y-m-d', $due_timestamp ) : $date;

		// Currency and language.
		$invoice->invoice_currency = $order->get_currency();
		$invoice->invoice_language = $settings->get_invoice_language();

		// Customer reference.
		$invoice->customer_reference = (string) $order->get_order_number();

		// Build invoice lines.
		$line_no       = 1;
		$has_discount  = false;
		$invoice_lines = [];

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$line = InvoiceLine::from_wc_item( $item, $line_no, $vat_mapper );

			if ( $line->discount > 0 ) {
				$has_discount = true;
			}

			$invoice_lines[] = $line;
			++$line_no;
		}

		foreach ( $order->get_items( 'shipping' ) as $shipping ) {
			if ( ! $shipping instanceof \WC_Order_Item_Shipping ) {
				continue;
			}

			$invoice_lines[] = InvoiceLine::from_shipping( $shipping, $line_no );
			++$line_no;
		}

		foreach ( $order->get_items( 'fee' ) as $fee ) {
			if ( ! $fee instanceof \WC_Order_Item_Fee ) {
				continue;
			}

			$invoice_lines[] = InvoiceLine::from_fee( $fee, $line_no, $vat_mapper );
			++$line_no;
		}

		$invoice->invoice_lines = $invoice_lines;
		$invoice->show_discount = $has_discount;

		// Delivery address from shipping.
		$invoice->delivery_address  = $order->get_shipping_address_1();
		$invoice->delivery_postcode = $order->get_shipping_postcode();
		$invoice->delivery_city     = $order->get_shipping_city();
		$invoice->delivery_country  = $order->get_shipping_country();

		return $invoice;
	}
}
