<?php
/**
 * VAT code mapper for Conta.no API integration.
 *
 * Maps WooCommerce tax classes to Conta.no VAT codes used
 * when creating invoices and products via the API.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\Services;

/**
 * Maps WooCommerce tax classes to Conta VAT codes.
 */
class VatMapper {

	/**
	 * Invoice VAT codes supported by Conta.no.
	 *
	 * @var array<string, string>
	 */
	private const INVOICE_VAT_CODES = [
		'no.vat'    => 'No VAT (0%)',
		'high'      => 'High rate (25%)',
		'medium'    => 'Medium rate (15%)',
		'low'       => 'Low rate (12%)',
		'zero.rate' => 'Zero rate (0%)',
		'exempted'  => 'Exempted',
		'export'    => 'Export (0%)',
	];

	/**
	 * Product VAT codes supported by Conta.no.
	 *
	 * @var array<string, string>
	 */
	private const PRODUCT_VAT_CODES = [
		'output.high'      => 'Output high (25%)',
		'output.medium'    => 'Output medium (15%)',
		'output.low'       => 'Output low (12%)',
		'output.zero.rate' => 'Output zero rate',
		'output.exempted'  => 'Output exempted',
		'output.export'    => 'Output export',
		'output.no.vat'    => 'Output no VAT',
		'input.no.vat'     => 'Input no VAT',
	];

	/**
	 * Default WooCommerce tax class to Conta VAT code mapping.
	 *
	 * @var array<string, string>
	 */
	private const DEFAULT_MAPPING = [
		''             => 'high',
		'reduced-rate' => 'medium',
		'zero-rate'    => 'zero.rate',
	];

	/**
	 * Fallback VAT code when no mapping is found.
	 *
	 * @var string
	 */
	private const FALLBACK_CODE = 'high';

	/**
	 * Settings service instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings service for retrieving VAT mapping configuration.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Get the Conta VAT code for a WooCommerce tax class.
	 *
	 * Merges the default mapping with any custom mapping from plugin settings,
	 * then looks up the given tax class. Returns the fallback code if the tax
	 * class is not found in the merged mapping.
	 *
	 * @param string $wc_tax_class WooCommerce tax class identifier (e.g. '', 'reduced-rate').
	 * @return string Conta invoice VAT code.
	 */
	public function get_conta_vat_code( string $wc_tax_class ): string {
		$custom_mapping = $this->settings->get_vat_mapping();
		$mapping        = array_merge( self::DEFAULT_MAPPING, $custom_mapping );

		$mapped_code = $mapping[ $wc_tax_class ] ?? self::FALLBACK_CODE;

		/**
		 * Filter the resolved Conta VAT code for a WooCommerce tax class.
		 *
		 * @param string $mapped_code  The resolved Conta VAT code.
		 * @param string $wc_tax_class The WooCommerce tax class being mapped.
		 */
		$mapped_code = (string) apply_filters( 'ihumbak_wca_vat_code', $mapped_code, $wc_tax_class );

		return $mapped_code;
	}

	/**
	 * Get available invoice VAT codes.
	 *
	 * @return array<string, string> Associative array of code => label.
	 */
	public function get_available_invoice_codes(): array {
		return self::INVOICE_VAT_CODES;
	}

	/**
	 * Get available product VAT codes.
	 *
	 * @return array<string, string> Associative array of code => label.
	 */
	public function get_available_product_codes(): array {
		return self::PRODUCT_VAT_CODES;
	}

	/**
	 * Get the default WooCommerce tax class to Conta VAT code mapping.
	 *
	 * @return array<string, string> Associative array of WC tax class => Conta VAT code.
	 */
	public function get_default_mapping(): array {
		return self::DEFAULT_MAPPING;
	}
}
