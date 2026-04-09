<?php
/**
 * Customer data model for Conta.no API.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\API\Models;

/**
 * Represents a customer in the Conta.no system.
 */
class Customer {

	/**
	 * Conta customer ID.
	 *
	 * @var ?int
	 */
	public ?int $id = null;

	/**
	 * Customer name (person or company).
	 *
	 * @var string
	 */
	public string $name = '';

	/**
	 * Customer type: INDIVIDUAL or ORGANIZATION.
	 *
	 * @var string
	 */
	public string $customer_type = 'INDIVIDUAL';

	/**
	 * Email address.
	 *
	 * @var string
	 */
	public string $email_address = '';

	/**
	 * Phone number.
	 *
	 * @var string
	 */
	public string $phone_no = '';

	/**
	 * Organization number.
	 *
	 * @var string
	 */
	public string $org_no = '';

	/**
	 * Address line 1.
	 *
	 * @var string
	 */
	public string $address_line1 = '';

	/**
	 * Address line 2.
	 *
	 * @var string
	 */
	public string $address_line2 = '';

	/**
	 * Postcode.
	 *
	 * @var string
	 */
	public string $address_postcode = '';

	/**
	 * City.
	 *
	 * @var string
	 */
	public string $address_city = '';

	/**
	 * Country code.
	 *
	 * @var string
	 */
	public string $address_country = '';

	/**
	 * Delivery method (EMAIL, PRINT, etc.).
	 *
	 * @var string
	 */
	public string $delivery_method = 'EMAIL';

	/**
	 * Convert to Conta API format.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$data = [
			'name'                    => $this->name,
			'customerType'            => $this->customer_type,
			'emailAddress'            => $this->email_address,
			'phoneNo'                 => $this->phone_no,
			'orgNo'                   => $this->org_no,
			'customerAddressLine1'    => $this->address_line1,
			'customerAddressLine2'    => $this->address_line2,
			'customerAddressPostcode' => $this->address_postcode,
			'customerAddressCity'     => $this->address_city,
			'customerAddressCountry'  => $this->address_country,
			'deliveryMethod'          => $this->delivery_method,
		];

		if ( null !== $this->id ) {
			$data['id'] = $this->id;
		}

		return $data;
	}

	/**
	 * Create a Customer from Conta API response data.
	 *
	 * @param array<string, mixed> $data Conta API response array.
	 * @return self
	 */
	public static function from_array( array $data ): self {
		$customer                   = new self();
		$customer->id               = isset( $data['id'] ) ? (int) $data['id'] : null;
		$customer->name             = (string) ( $data['name'] ?? '' );
		$customer->customer_type    = (string) ( $data['customerType'] ?? 'INDIVIDUAL' );
		$customer->email_address    = (string) ( $data['emailAddress'] ?? '' );
		$customer->phone_no         = (string) ( $data['phoneNo'] ?? '' );
		$customer->org_no           = (string) ( $data['orgNo'] ?? '' );
		$customer->address_line1    = (string) ( $data['customerAddressLine1'] ?? '' );
		$customer->address_line2    = (string) ( $data['customerAddressLine2'] ?? '' );
		$customer->address_postcode = (string) ( $data['customerAddressPostcode'] ?? '' );
		$customer->address_city     = (string) ( $data['customerAddressCity'] ?? '' );
		$customer->address_country  = (string) ( $data['customerAddressCountry'] ?? '' );
		$customer->delivery_method  = (string) ( $data['deliveryMethod'] ?? 'EMAIL' );

		return $customer;
	}

	/**
	 * Create a Customer from a WooCommerce order's billing data.
	 *
	 * @param \WC_Order $order WooCommerce order instance.
	 * @return self
	 */
	public static function from_wc_order( \WC_Order $order ): self {
		$customer = new self();

		$company = $order->get_billing_company();

		if ( '' !== $company ) {
			$customer->customer_type = 'ORGANIZATION';
			$customer->name          = $company;
		} else {
			$customer->customer_type = 'INDIVIDUAL';
			$customer->name          = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		}

		$customer->email_address    = $order->get_billing_email();
		$customer->phone_no         = $order->get_billing_phone();
		$customer->address_line1    = $order->get_billing_address_1();
		$customer->address_line2    = $order->get_billing_address_2();
		$customer->address_postcode = $order->get_billing_postcode();
		$customer->address_city     = $order->get_billing_city();
		$customer->address_country  = $order->get_billing_country();

		return $customer;
	}

	/**
	 * Validate required fields for the Conta API.
	 *
	 * @return array<int, string> List of missing required field names (Conta API names).
	 */
	public function validate(): array {
		$missing = [];

		if ( '' === $this->name ) {
			$missing[] = 'name';
		}

		if ( '' === $this->customer_type ) {
			$missing[] = 'customerType';
		}

		if ( '' === $this->address_line1 ) {
			$missing[] = 'customerAddressLine1';
		}

		if ( '' === $this->address_postcode ) {
			$missing[] = 'customerAddressPostcode';
		}

		if ( '' === $this->address_city ) {
			$missing[] = 'customerAddressCity';
		}

		return $missing;
	}
}
