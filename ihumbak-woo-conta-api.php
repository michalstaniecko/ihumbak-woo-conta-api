<?php
/**
 * Plugin Name:       iHumbak WooConta API
 * Plugin URI:        https://github.com/michalstaniecko/ihumbak-woo-conta-api
 * Description:       WooCommerce integration with Conta.no — send orders to Conta for invoice generation.
 * Version:           0.2.1
 * Author:            Michal Staniecko
 * Author URI:        https://github.com/michalstaniecko
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ihumbak-woo-conta-api
 * Domain Path:       /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * WC requires at least: 8.0
 * WC tested up to:   9.6
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'IHUMBAK_WCA_VERSION', '0.2.1' );
define( 'IHUMBAK_WCA_FILE', __FILE__ );
define( 'IHUMBAK_WCA_PATH', plugin_dir_path( __FILE__ ) );
define( 'IHUMBAK_WCA_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader.
if ( file_exists( IHUMBAK_WCA_PATH . 'vendor/autoload.php' ) ) {
	require_once IHUMBAK_WCA_PATH . 'vendor/autoload.php';
}

/**
 * Declare HPOS compatibility.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}
);

/**
 * Initialize the plugin after all plugins are loaded.
 */
add_action(
	'plugins_loaded',
	function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', 'ihumbak_wca_woocommerce_missing_notice' );
			return;
		}

		\Ihumbak\WooConta\Plugin::instance()->init();
	}
);

/**
 * Admin notice when WooCommerce is not active.
 *
 * @return void
 */
function ihumbak_wca_woocommerce_missing_notice(): void {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			printf(
				/* translators: %s: WooCommerce plugin name */
				esc_html__( '%1$s requires %2$s to be installed and active.', 'ihumbak-woo-conta-api' ),
				'<strong>iHumbak WooConta API</strong>',
				'<strong>WooCommerce</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Plugin activation hook.
 *
 * @return void
 */
function ihumbak_wca_activate(): void {
	if ( ! get_option( 'ihumbak_wca_settings' ) ) {
		add_option(
			'ihumbak_wca_settings',
			[
				'api_key'         => '',
				'organization_id' => '',
				'environment'     => 'sandbox',
			]
		);
	}
}
register_activation_hook( __FILE__, 'ihumbak_wca_activate' );

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function ihumbak_wca_deactivate(): void {
	// Clean up scheduled events if any.
}
register_deactivation_hook( __FILE__, 'ihumbak_wca_deactivate' );
