<?php
/**
 * Main plugin class.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta;

use Ihumbak\WooConta\Modules\Updates\UpdateService;

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
		$update_service = new UpdateService();
		if ( $update_service->is_enabled() ) {
			$update_service->init();
		}
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
