<?php
/**
 * Organizations API endpoint.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\API\Endpoints;

use Ihumbak\WooConta\API\Client;
use WP_Error;

/**
 * Handles organization-related API calls.
 */
class Organizations {

	/**
	 * HTTP client.
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * Constructor.
	 *
	 * @param Client $client HTTP client.
	 */
	public function __construct( Client $client ) {
		$this->client = $client;
	}

	/**
	 * List accessible organizations.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function list(): array|WP_Error {
		return $this->client->get( '/invoice/organizations' );
	}
}
