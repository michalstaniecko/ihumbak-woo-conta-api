<?php
/**
 * Settings service for Conta.no API integration.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\Services;

/**
 * Manages plugin settings stored in wp_options.
 */
class Settings {

	/**
	 * WordPress option name for plugin settings.
	 *
	 * @var string
	 */
	private const OPTION_NAME = 'ihumbak_wca_settings';

	/**
	 * Production API base URL.
	 *
	 * @var string
	 */
	private const BASE_URL_PRODUCTION = 'https://api.gateway.conta.no';

	/**
	 * Sandbox API base URL.
	 *
	 * @var string
	 */
	private const BASE_URL_SANDBOX = 'https://api.gateway.conta-sandbox.no';

	/**
	 * Get the default settings.
	 *
	 * @return array<string, mixed>
	 */
	private function get_defaults(): array {
		return [
			'api_key'                => '',
			'organization_id'        => 0,
			'environment'            => 'sandbox',
			'vat_mapping'            => [
				''             => 'high',
				'reduced-rate' => 'medium',
				'zero-rate'    => 'zero.rate',
			],
			'invoice_language'       => 'NO',
			'invoice_trigger_status' => 'processing',
			'delivery_method'        => 'EMAIL',
			'auto_sync_enabled'      => false,
			'vat_number_field'       => '',
		];
	}

	/**
	 * Get all settings with defaults merged.
	 *
	 * @return array<string, mixed>
	 */
	public function get_all(): array {
		$saved = get_option( self::OPTION_NAME, [] );

		if ( ! is_array( $saved ) ) {
			$saved = [];
		}

		// Also read from individual WC settings options (set by SettingsPage).
		$wc_overrides = [
			'api_key'                => get_option( 'ihumbak_wca_api_key', '' ),
			'environment'            => get_option( 'ihumbak_wca_environment', '' ),
			'organization_id'        => (int) get_option( 'ihumbak_wca_organization_id', 0 ),
			'invoice_language'       => get_option( 'ihumbak_wca_invoice_language', '' ),
			'invoice_trigger_status' => get_option( 'ihumbak_wca_trigger_status', '' ),
			'delivery_method'        => get_option( 'ihumbak_wca_delivery_method', '' ),
			'vat_number_field'       => get_option( 'ihumbak_wca_vat_number_field', '' ),
		];

		// Merge WC individual options over consolidated (non-empty values win).
		foreach ( $wc_overrides as $key => $value ) {
			if ( '' !== $value && 0 !== $value ) {
				$saved[ $key ] = $value;
			}
		}

		// Boolean options from WC (checkboxes store 'yes'/'no').
		$auto_sync_raw = get_option( 'ihumbak_wca_auto_sync', '' );
		if ( '' !== $auto_sync_raw ) {
			$saved['auto_sync_enabled'] = 'yes' === $auto_sync_raw;
		}

		$settings = wp_parse_args( $saved, $this->get_defaults() );

		/**
		 * Filter the plugin settings.
		 *
		 * @param array<string, mixed> $settings The merged settings array.
		 */
		return apply_filters( 'ihumbak_wca_settings', $settings );
	}

	/**
	 * Get the Conta API key.
	 *
	 * @return string
	 */
	public function get_api_key(): string {
		return (string) $this->get_all()['api_key'];
	}

	/**
	 * Get the Conta organization ID.
	 *
	 * @return int
	 */
	public function get_organization_id(): int {
		return (int) $this->get_all()['organization_id'];
	}

	/**
	 * Get the API environment ('sandbox' or 'production').
	 *
	 * @return string
	 */
	public function get_environment(): string {
		return (string) $this->get_all()['environment'];
	}

	/**
	 * Get the full API base URL based on the current environment.
	 *
	 * @return string
	 */
	public function get_base_url(): string {
		return 'production' === $this->get_environment()
			? self::BASE_URL_PRODUCTION
			: self::BASE_URL_SANDBOX;
	}

	/**
	 * Get the WooCommerce tax class to Conta VAT code mapping.
	 *
	 * @return array<string, string>
	 */
	public function get_vat_mapping(): array {
		return (array) $this->get_all()['vat_mapping'];
	}

	/**
	 * Get the invoice language code ('NO' or 'EN').
	 *
	 * @return string
	 */
	public function get_invoice_language(): string {
		return (string) $this->get_all()['invoice_language'];
	}

	/**
	 * Get the WooCommerce order status that triggers invoice creation.
	 *
	 * @return string
	 */
	public function get_invoice_trigger_status(): string {
		$status = (string) $this->get_all()['invoice_trigger_status'];

		// Strip 'wc-' prefix if present (WC settings store with prefix, hooks use without).
		if ( str_starts_with( $status, 'wc-' ) ) {
			$status = substr( $status, 3 );
		}

		return $status;
	}

	/**
	 * Get the invoice delivery method.
	 *
	 * @return string
	 */
	public function get_delivery_method(): string {
		return (string) $this->get_all()['delivery_method'];
	}

	/**
	 * Get the WooCommerce order meta key that stores the customer VAT number.
	 *
	 * @return string Meta key name, or empty string if not configured.
	 */
	public function get_vat_number_field(): string {
		return (string) $this->get_all()['vat_number_field'];
	}

	/**
	 * Check whether automatic invoice sync on order status change is enabled.
	 *
	 * @return bool True if auto-sync is enabled, false by default.
	 */
	public function is_auto_sync_enabled(): bool {
		return (bool) $this->get_all()['auto_sync_enabled'];
	}

	/**
	 * Update plugin settings.
	 *
	 * @param array<string, mixed> $settings Settings to save.
	 * @return bool True on success, false on failure.
	 */
	public function update( array $settings ): bool {
		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Check whether the plugin is configured with required credentials.
	 *
	 * @return bool True if API key and organization ID are set.
	 */
	public function is_configured(): bool {
		return '' !== $this->get_api_key() && 0 !== $this->get_organization_id();
	}
}
