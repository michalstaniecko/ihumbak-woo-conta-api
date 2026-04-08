<?php
/**
 * Plugin update service.
 *
 * Integrates with yahnis-elsts/plugin-update-checker to enable
 * automatic updates from GitHub Releases.
 *
 * @package Ihumbak\WooConta\Modules\Updates
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\Modules\Updates;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Handles automatic plugin updates from GitHub.
 */
class UpdateService {

	/**
	 * Default GitHub repository URL.
	 */
	private const REPOSITORY_URL = 'https://github.com/michalstaniecko/ihumbak-woo-conta-api/';

	/**
	 * Check if updates are enabled.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		if ( defined( 'IHUMBAK_WCA_DISABLE_UPDATES' ) && IHUMBAK_WCA_DISABLE_UPDATES ) {
			return false;
		}

		return (bool) apply_filters( 'ihumbak_wca_updates_enabled', true );
	}

	/**
	 * Initialize the update checker.
	 *
	 * @return void
	 */
	public function init(): void {
		$repository_url = $this->get_repository_url();
		$plugin_file    = $this->get_plugin_file();

		$update_checker = PucFactory::buildUpdateChecker(
			$repository_url,
			$plugin_file,
			'ihumbak-woo-conta-api'
		);

		// Download ZIP from GitHub Releases instead of source archive.
		if ( method_exists( $update_checker, 'getVcsApi' ) ) {
			$api = $update_checker->getVcsApi();
			if ( method_exists( $api, 'enableReleaseAssets' ) ) {
				$api->enableReleaseAssets();
			}
		}

		// Set GitHub access token for private repos.
		$access_token = $this->get_access_token();
		if ( $access_token && method_exists( $update_checker, 'setAuthentication' ) ) {
			$update_checker->setAuthentication( $access_token );
		}

		// Allow modifying update info.
		$update_checker->addResultFilter(
			/**
			 * Filter the update info object.
			 *
			 * @param object $info Update info.
			 * @return object
			 */
			function ( $info ) {
				return apply_filters( 'ihumbak_wca_update_info', $info );
			}
		);
	}

	/**
	 * Get the repository URL.
	 *
	 * @return string
	 */
	private function get_repository_url(): string {
		return (string) apply_filters( 'ihumbak_wca_update_repository_url', self::REPOSITORY_URL );
	}

	/**
	 * Get the main plugin file path.
	 *
	 * @return string
	 */
	private function get_plugin_file(): string {
		if ( defined( 'IHUMBAK_WCA_FILE' ) ) {
			return IHUMBAK_WCA_FILE;
		}

		return dirname( __DIR__, 3 ) . '/ihumbak-woo-conta-api.php';
	}

	/**
	 * Get GitHub access token.
	 *
	 * @return string
	 */
	private function get_access_token(): string {
		$token = '';

		if ( defined( 'IHUMBAK_WCA_GITHUB_ACCESS_TOKEN' ) ) {
			$token = IHUMBAK_WCA_GITHUB_ACCESS_TOKEN;
		}

		return (string) apply_filters( 'ihumbak_wca_github_access_token', $token );
	}
}
