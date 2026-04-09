<?php
/**
 * Payment data model for Conta.no API.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\API\Models;

/**
 * Represents a payment registration in the Conta.no system.
 */
class Payment {

	/**
	 * Payment date (YYYY-MM-DD).
	 *
	 * @var string
	 */
	public string $date = '';

	/**
	 * Payment amount.
	 *
	 * @var float
	 */
	public float $amount = 0.0;

	/**
	 * Payment description.
	 *
	 * @var string
	 */
	public string $description = '';

	/**
	 * Conta bank account ID.
	 *
	 * @var ?int
	 */
	public ?int $bank_account_id = null;

	/**
	 * Convert to Conta API format.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		$data = [
			'date'        => $this->date,
			'amount'      => $this->amount,
			'description' => $this->description,
		];

		if ( null !== $this->bank_account_id ) {
			$data['bankAccountId'] = $this->bank_account_id;
		}

		return $data;
	}

	/**
	 * Create a Payment from a WooCommerce order.
	 *
	 * @param \WC_Order $order WooCommerce order instance.
	 * @return self
	 */
	public static function from_wc_order( \WC_Order $order ): self {
		$payment = new self();

		$date_paid     = $order->get_date_paid();
		$payment->date = ( null !== $date_paid ) ? $date_paid->date( 'Y-m-d' ) : gmdate( 'Y-m-d' );

		$payment->amount      = (float) $order->get_total();
		$payment->description = 'WooCommerce Order #' . $order->get_order_number();

		return $payment;
	}
}
