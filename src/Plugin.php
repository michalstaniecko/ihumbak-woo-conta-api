<?php
/**
 * Main plugin class.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta;

use Ihumbak\WooConta\Admin\OrderMetaBox;
use Ihumbak\WooConta\Admin\SettingsPage;
use Ihumbak\WooConta\API\Client;
use Ihumbak\WooConta\API\Endpoints\Customers;
use Ihumbak\WooConta\API\Endpoints\Invoices;
use Ihumbak\WooConta\API\Endpoints\Payments;
use Ihumbak\WooConta\Modules\CustomerSync;
use Ihumbak\WooConta\Modules\InvoiceSync;
use Ihumbak\WooConta\Modules\OrderHooks;
use Ihumbak\WooConta\Modules\PaymentSync;
use Ihumbak\WooConta\Modules\Updates\UpdateService;
use Ihumbak\WooConta\Services\Logger;
use Ihumbak\WooConta\Services\Settings;
use Ihumbak\WooConta\Services\VatMapper;

/**
 * Plugin bootstrap class.
 */
final class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Settings service.
	 *
	 * @var Settings|null
	 */
	private ?Settings $settings = null;

	/**
	 * Logger service.
	 *
	 * @var Logger|null
	 */
	private ?Logger $logger = null;

	/**
	 * VAT mapper service.
	 *
	 * @var VatMapper|null
	 */
	private ?VatMapper $vat_mapper = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {}

	/**
	 * Initialize the plugin.
	 *
	 * @return void
	 */
	public function init(): void {
		$this->register_services();

		add_action( 'init', [ $this, 'load_textdomain' ] );
	}

	/**
	 * Register plugin services and modules.
	 *
	 * @return void
	 */
	private function register_services(): void {
		// Foundation services.
		$this->settings   = new Settings();
		$this->logger     = new Logger();
		$this->vat_mapper = new VatMapper( $this->settings );

		// Auto-updater.
		$update_service = new UpdateService();
		if ( $update_service->is_enabled() ) {
			$update_service->init();
		}

		// API client and endpoints.
		$client    = new Client( $this->settings, $this->logger );
		$customers = new Customers( $client, $this->settings );
		$invoices  = new Invoices( $client, $this->settings );
		$payments  = new Payments( $client, $this->settings );

		// Sync modules.
		$customer_sync = new CustomerSync( $customers, $this->logger, $this->settings );
		$invoice_sync  = new InvoiceSync( $invoices, $customer_sync, $this->vat_mapper, $this->settings, $this->logger );
		$payment_sync  = new PaymentSync( $payments, $invoices, $this->settings, $this->logger );

		// WooCommerce order hooks.
		$order_hooks = new OrderHooks( $invoice_sync, $payment_sync, $this->settings, $this->logger );
		$order_hooks->register();

		// Admin UI (only in admin context).
		if ( is_admin() ) {
			$settings_page = new SettingsPage( $this->settings, $this->logger, $this->vat_mapper );
			$settings_page->init();

			$order_meta_box = new OrderMetaBox( $invoice_sync, $payment_sync );
			$order_meta_box->register();
		}
	}

	/**
	 * Get the Settings service.
	 *
	 * @return Settings
	 */
	public function settings(): Settings {
		if ( null === $this->settings ) {
			$this->settings = new Settings();
		}

		return $this->settings;
	}

	/**
	 * Get the Logger service.
	 *
	 * @return Logger
	 */
	public function logger(): Logger {
		if ( null === $this->logger ) {
			$this->logger = new Logger();
		}

		return $this->logger;
	}

	/**
	 * Get the VatMapper service.
	 *
	 * @return VatMapper
	 */
	public function vat_mapper(): VatMapper {
		if ( null === $this->vat_mapper ) {
			$this->vat_mapper = new VatMapper( $this->settings() );
		}

		return $this->vat_mapper;
	}

	/**
	 * Load plugin text domain for translations.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'ihumbak-woo-conta-api',
			false,
			dirname( plugin_basename( IHUMBAK_WCA_FILE ) ) . '/languages'
		);
	}

	/**
	 * Get plugin version.
	 *
	 * @return string
	 */
	public function get_version(): string {
		return IHUMBAK_WCA_VERSION;
	}

	/**
	 * Get plugin path.
	 *
	 * @return string
	 */
	public function get_path(): string {
		return IHUMBAK_WCA_PATH;
	}

	/**
	 * Get plugin URL.
	 *
	 * @return string
	 */
	public function get_url(): string {
		return IHUMBAK_WCA_URL;
	}
}
