<?php
/**
 * WooCommerce Settings tab for Conta.no integration.
 *
 * Registers a custom tab in WooCommerce > Settings and provides
 * connection and invoice configuration fields with AJAX test functionality.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\Admin;

use Ihumbak\WooConta\API\Client;
use Ihumbak\WooConta\API\Endpoints\Organizations;
use Ihumbak\WooConta\Services\Logger;
use Ihumbak\WooConta\Services\Settings;
use Ihumbak\WooConta\Services\VatMapper;

/**
 * WooCommerce settings tab for Conta.no integration.
 */
class SettingsPage {

	/**
	 * Tab identifier used for WooCommerce settings routing.
	 *
	 * @var string
	 */
	private const TAB_ID = 'ihumbak_wca';

	/**
	 * Settings service instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Logger service instance.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * VAT mapper service.
	 *
	 * @var VatMapper
	 */
	private VatMapper $vat_mapper;

	/**
	 * Constructor.
	 *
	 * @param Settings  $settings   Settings service.
	 * @param Logger    $logger     Logger service.
	 * @param VatMapper $vat_mapper VAT mapper service.
	 */
	public function __construct( Settings $settings, Logger $logger, VatMapper $vat_mapper ) {
		$this->settings   = $settings;
		$this->logger     = $logger;
		$this->vat_mapper = $vat_mapper;
	}

	/**
	 * Register hooks for the settings tab and AJAX handler.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'woocommerce_settings_tabs_array', [ $this, 'add_settings_tab' ], 50 );
		add_action( 'woocommerce_settings_tabs_' . self::TAB_ID, [ $this, 'render_settings' ] );
		add_action( 'woocommerce_update_options_' . self::TAB_ID, [ $this, 'save_settings' ] );
		add_action( 'wp_ajax_ihumbak_wca_test_connection', [ $this, 'ajax_test_connection' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Add the Conta Integration tab to WooCommerce settings.
	 *
	 * @param array<string, string> $tabs Existing WooCommerce settings tabs.
	 * @return array<string, string> Modified tabs array.
	 */
	public function add_settings_tab( array $tabs ): array {
		$tabs[ self::TAB_ID ] = __( 'Conta Integration', 'ihumbak-woo-conta-api' );

		return $tabs;
	}

	/**
	 * Render the settings fields for the Conta Integration tab.
	 *
	 * @return void
	 */
	public function render_settings(): void {
		\WC_Admin_Settings::output_fields( $this->get_settings_fields() );
	}

	/**
	 * Save the settings fields when the form is submitted.
	 *
	 * @return void
	 */
	public function save_settings(): void {
		\WC_Admin_Settings::save_fields( $this->get_settings_fields() );

		$this->sync_to_settings_service();
	}

	/**
	 * Synchronize individual WC options into the Settings service option.
	 *
	 * WooCommerce saves each field as its own wp_option. This method reads
	 * them and writes a consolidated array that the Settings service expects.
	 *
	 * @return void
	 */
	private function sync_to_settings_service(): void {
		$current = $this->settings->get_all();

		$current['api_key']                = get_option( 'ihumbak_wca_api_key', '' );
		$current['environment']            = get_option( 'ihumbak_wca_environment', 'sandbox' );
		$current['organization_id']        = (int) get_option( 'ihumbak_wca_organization_id', 0 );
		$current['invoice_language']       = get_option( 'ihumbak_wca_invoice_language', 'NO' );
		$current['invoice_trigger_status'] = get_option( 'ihumbak_wca_trigger_status', 'wc-processing' );
		$current['delivery_method']        = get_option( 'ihumbak_wca_delivery_method', 'EMAIL' );
		$current['auto_sync_enabled']      = 'yes' === get_option( 'ihumbak_wca_auto_sync', 'no' );
		$current['vat_number_field']       = get_option( 'ihumbak_wca_vat_number_field', '' );
		$current['personal_message_template'] = get_option( 'ihumbak_wca_personal_message_template', '' );

		$current['vat_mapping'] = [
			''             => get_option( 'ihumbak_wca_vat_standard', 'high' ),
			'reduced-rate' => get_option( 'ihumbak_wca_vat_reduced_rate', 'medium' ),
			'zero-rate'    => get_option( 'ihumbak_wca_vat_zero_rate', 'zero.rate' ),
		];

		$this->settings->update( $current );
	}

	/**
	 * Handle the AJAX test connection request.
	 *
	 * Verifies the nonce, creates a temporary API client with the submitted
	 * credentials, and attempts to list organizations from Conta.
	 *
	 * @return void
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'ihumbak_wca_test_connection' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ihumbak-woo-conta-api' ) ] );
		}

		$api_key     = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$environment = isset( $_POST['environment'] ) ? sanitize_text_field( wp_unslash( $_POST['environment'] ) ) : 'sandbox';

		if ( '' === $api_key ) {
			wp_send_json_error( [ 'message' => __( 'API key is required.', 'ihumbak-woo-conta-api' ) ] );
		}

		// Temporarily update the settings for the test request.
		$current = $this->settings->get_all();
		$this->settings->update(
			array_merge(
				$current,
				[
					'api_key'     => $api_key,
					'environment' => $environment,
				]
			)
		);

		$client        = new Client( $this->settings, $this->logger );
		$organizations = new Organizations( $client );
		$result        = $organizations->list();

		// Restore original settings.
		$this->settings->update( $current );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		// Build organizations list for the response.
		$orgs = [];
		foreach ( $result as $org ) {
			if ( is_array( $org ) && isset( $org['id'], $org['name'] ) ) {
				$orgs[] = [
					'id'   => (int) $org['id'],
					'name' => (string) $org['name'],
				];
			}
		}

		wp_send_json_success(
			[
				'message'       => __( 'Connection successful!', 'ihumbak-woo-conta-api' ),
				'organizations' => $orgs,
			]
		);
	}

	/**
	 * Enqueue admin JavaScript and CSS on the WooCommerce settings page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook_suffix ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading tab parameter for asset loading only.
		$tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : '';
		if ( self::TAB_ID !== $tab ) {
			return;
		}

		wp_enqueue_style(
			'ihumbak-wca-admin',
			IHUMBAK_WCA_URL . 'assets/css/admin.css',
			[],
			IHUMBAK_WCA_VERSION
		);

		wp_enqueue_script(
			'ihumbak-wca-admin',
			IHUMBAK_WCA_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			IHUMBAK_WCA_VERSION,
			true
		);

		wp_localize_script(
			'ihumbak-wca-admin',
			'ihumbak_wca_admin',
			[
				'nonce' => wp_create_nonce( 'ihumbak_wca_test_connection' ),
			]
		);
	}

	/**
	 * Get the WooCommerce settings fields for the Conta Integration tab.
	 *
	 * @return array<int, array<string, mixed>> Array of WC settings field definitions.
	 */
	private function get_settings_fields(): array {
		return [
			// Connection section.
			[
				'type' => 'title',
				'name' => __( 'Connection Settings', 'ihumbak-woo-conta-api' ),
				'desc' => __( 'Configure your Conta.no API credentials.', 'ihumbak-woo-conta-api' ),
				'id'   => 'ihumbak_wca_connection',
			],
			[
				'type'     => 'text',
				'id'       => 'ihumbak_wca_api_key',
				'name'     => __( 'API Key', 'ihumbak-woo-conta-api' ),
				'desc'     => __( 'Your Conta.no API key.', 'ihumbak-woo-conta-api' ),
				'desc_tip' => true,
			],
			[
				'type'    => 'select',
				'id'      => 'ihumbak_wca_environment',
				'name'    => __( 'Environment', 'ihumbak-woo-conta-api' ),
				'options' => [
					'sandbox'    => __( 'Sandbox', 'ihumbak-woo-conta-api' ),
					'production' => __( 'Production', 'ihumbak-woo-conta-api' ),
				],
				'default' => 'sandbox',
			],
			[
				'type' => 'text',
				'id'   => 'ihumbak_wca_organization_id',
				'name' => __( 'Organization ID', 'ihumbak-woo-conta-api' ),
				'desc' => __( 'Enter the organization ID from your Conta.no account.', 'ihumbak-woo-conta-api' ),
			],
			[
				'type' => 'sectionend',
				'id'   => 'ihumbak_wca_connection',
			],

			// Test connection button (rendered as custom HTML).
			[
				'type' => 'title',
				'name' => '',
				'desc' => '<div class="ihumbak-wca-settings">'
					. '<div class="test-connection-wrapper">'
					. '<button type="button" id="ihumbak_wca_test_connection" class="button button-secondary">'
					. esc_html__( 'Test Connection', 'ihumbak-woo-conta-api' )
					. '</button>'
					. '<span id="ihumbak_wca_connection_result" class="connection-result"></span>'
					. '</div></div>',
				'id'   => 'ihumbak_wca_test_connection_section',
			],
			[
				'type' => 'sectionend',
				'id'   => 'ihumbak_wca_test_connection_section',
			],

			// Invoice section.
			[
				'type' => 'title',
				'name' => __( 'Invoice Settings', 'ihumbak-woo-conta-api' ),
				'desc' => __( 'Configure invoice generation options.', 'ihumbak-woo-conta-api' ),
				'id'   => 'ihumbak_wca_invoice',
			],
			[
				'type'    => 'select',
				'id'      => 'ihumbak_wca_invoice_language',
				'name'    => __( 'Invoice Language', 'ihumbak-woo-conta-api' ),
				'options' => [
					'NO' => __( 'Norwegian', 'ihumbak-woo-conta-api' ),
					'EN' => __( 'English', 'ihumbak-woo-conta-api' ),
				],
				'default' => 'NO',
			],
			[
				'type'    => 'select',
				'id'      => 'ihumbak_wca_trigger_status',
				'name'    => __( 'Sync Trigger Status', 'ihumbak-woo-conta-api' ),
				'desc'    => __( 'WooCommerce order status that triggers invoice creation in Conta.', 'ihumbak-woo-conta-api' ),
				'options' => function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : [],
				'default' => 'wc-processing',
			],
			[
				'type'    => 'select',
				'id'      => 'ihumbak_wca_delivery_method',
				'name'    => __( 'Delivery Method', 'ihumbak-woo-conta-api' ),
				'desc'    => __( 'How Conta should deliver the invoice.', 'ihumbak-woo-conta-api' ),
				'options' => [
					'EMAIL'          => __( 'Email', 'ihumbak-woo-conta-api' ),
					'DO_NOT_DELIVER' => __( 'Do Not Deliver', 'ihumbak-woo-conta-api' ),
					'MAIL'           => __( 'Postal Mail', 'ihumbak-woo-conta-api' ),
				],
				'default' => 'EMAIL',
			],
			[
				'type'    => 'text',
				'id'      => 'ihumbak_wca_vat_number_field',
				'name'    => __( 'VAT Number Field', 'ihumbak-woo-conta-api' ),
				'desc'    => __( 'WooCommerce order meta key that contains the customer VAT number (e.g. <code>_billing_vat_number</code>, <code>_vat_number</code>). Leave empty to skip.', 'ihumbak-woo-conta-api' ),
				'default' => '',
			],
			[
				'type'    => 'text',
				'id'      => 'ihumbak_wca_personal_message_template',
				'name'    => __( 'Invoice Personal Message', 'ihumbak-woo-conta-api' ),
				'desc'    => __( 'Message shown on the invoice PDF. Available placeholders: <code>{order_id}</code>, <code>{payment_method}</code>. Max 300 characters. Leave empty to skip.', 'ihumbak-woo-conta-api' ),
				'default' => 'WooCommerce Order #{order_id} ({payment_method})',
			],
			[
				'type'    => 'checkbox',
				'id'      => 'ihumbak_wca_auto_sync',
				'name'    => __( 'Auto-sync invoices', 'ihumbak-woo-conta-api' ),
				'desc'    => __( 'Automatically create invoices when order status changes. When disabled, use the "Sync to Conta" button on each order.', 'ihumbak-woo-conta-api' ),
				'default' => 'no',
			],
			[
				'type' => 'sectionend',
				'id'   => 'ihumbak_wca_invoice',
			],

			// VAT mapping section.
			[
				'type' => 'title',
				'name' => __( 'VAT Mapping', 'ihumbak-woo-conta-api' ),
				'desc' => __( 'Map WooCommerce tax classes to Conta.no VAT codes.', 'ihumbak-woo-conta-api' ),
				'id'   => 'ihumbak_wca_vat_mapping',
			],
			[
				'type'    => 'select',
				'id'      => 'ihumbak_wca_vat_standard',
				'name'    => __( 'Standard Rate', 'ihumbak-woo-conta-api' ),
				'desc'    => __( 'Conta VAT code for the standard WooCommerce tax class.', 'ihumbak-woo-conta-api' ),
				'options' => $this->vat_mapper->get_available_invoice_codes(),
				'default' => 'high',
			],
			[
				'type'    => 'select',
				'id'      => 'ihumbak_wca_vat_reduced_rate',
				'name'    => __( 'Reduced Rate', 'ihumbak-woo-conta-api' ),
				'desc'    => __( 'Conta VAT code for the reduced-rate tax class.', 'ihumbak-woo-conta-api' ),
				'options' => $this->vat_mapper->get_available_invoice_codes(),
				'default' => 'medium',
			],
			[
				'type'    => 'select',
				'id'      => 'ihumbak_wca_vat_zero_rate',
				'name'    => __( 'Zero Rate', 'ihumbak-woo-conta-api' ),
				'desc'    => __( 'Conta VAT code for the zero-rate tax class.', 'ihumbak-woo-conta-api' ),
				'options' => $this->vat_mapper->get_available_invoice_codes(),
				'default' => 'zero.rate',
			],
			[
				'type' => 'sectionend',
				'id'   => 'ihumbak_wca_vat_mapping',
			],
		];
	}
}
