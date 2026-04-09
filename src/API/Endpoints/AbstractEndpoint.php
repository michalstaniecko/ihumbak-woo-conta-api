<?php
/**
 * Abstract base class for API endpoints.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\API\Endpoints;

use Ihumbak\WooConta\API\Client;
use Ihumbak\WooConta\Services\Settings;

/**
 * Base class for Conta API endpoint classes.
 */
abstract class AbstractEndpoint {

	/**
	 * HTTP client.
	 *
	 * @var Client
	 */
	protected Client $client;

	/**
	 * Settings service.
	 *
	 * @var Settings
	 */
	protected Settings $settings;

	/**
	 * Constructor.
	 *
	 * @param Client   $client   HTTP client.
	 * @param Settings $settings Settings service.
	 */
	public function __construct( Client $client, Settings $settings ) {
		$this->client   = $client;
		$this->settings = $settings;
	}

	/**
	 * Build an organization-scoped API path.
	 *
	 * @param string ...$parts Path segments after /invoice/organizations/{orgId}/.
	 * @return string Full API path.
	 */
	protected function build_path( string ...$parts ): string {
		$org_id = $this->settings->get_organization_id();
		$base   = '/invoice/organizations/' . $org_id;

		if ( count( $parts ) > 0 ) {
			return $base . '/' . implode( '/', $parts );
		}

		return $base;
	}
}
