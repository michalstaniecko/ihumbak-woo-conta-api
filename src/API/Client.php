<?php
/**
 * HTTP client for the Conta.no API.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\API;

use Ihumbak\WooConta\Services\Logger;
use Ihumbak\WooConta\Services\Settings;
use WP_Error;

/**
 * Handles all HTTP communication with the Conta.no API.
 */
class Client {

	/**
	 * Request timeout in seconds.
	 *
	 * @var int
	 */
	private const TIMEOUT = 30;

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
	 * Constructor.
	 *
	 * @param Settings $settings Settings service.
	 * @param Logger   $logger   Logger service.
	 */
	public function __construct( Settings $settings, Logger $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Perform a GET request.
	 *
	 * @param string               $endpoint API endpoint path.
	 * @param array<string, mixed> $query    Optional query parameters.
	 * @return array<string, mixed>|WP_Error Decoded JSON response or WP_Error on failure.
	 */
	public function get( string $endpoint, array $query = [] ): array|WP_Error {
		$url = $this->build_url( $endpoint, $query );

		return $this->request( 'GET', $endpoint, [ 'url' => $url ] );
	}

	/**
	 * Perform a POST request with JSON body.
	 *
	 * @param string               $endpoint API endpoint path.
	 * @param array<string, mixed> $data     Request body data.
	 * @return array<string, mixed>|WP_Error Decoded JSON response or WP_Error on failure.
	 */
	public function post( string $endpoint, array $data = [] ): array|WP_Error {
		return $this->request(
			'POST',
			$endpoint,
			[ 'body' => wp_json_encode( $data ) ]
		);
	}

	/**
	 * Perform a PUT request with JSON body.
	 *
	 * @param string               $endpoint API endpoint path.
	 * @param array<string, mixed> $data     Request body data.
	 * @return array<string, mixed>|WP_Error Decoded JSON response or WP_Error on failure.
	 */
	public function put( string $endpoint, array $data = [] ): array|WP_Error {
		return $this->request(
			'PUT',
			$endpoint,
			[ 'body' => wp_json_encode( $data ) ]
		);
	}

	/**
	 * Perform a DELETE request.
	 *
	 * @param string $endpoint API endpoint path.
	 * @return array<string, mixed>|WP_Error Decoded JSON response or WP_Error on failure.
	 */
	public function delete( string $endpoint ): array|WP_Error {
		return $this->request( 'DELETE', $endpoint );
	}

	/**
	 * Core HTTP request method used by all public methods.
	 *
	 * @param string               $method   HTTP method (GET, POST, PUT, DELETE).
	 * @param string               $endpoint API endpoint path.
	 * @param array<string, mixed> $args     Additional request arguments.
	 * @return array<string, mixed>|WP_Error Decoded JSON response or WP_Error on failure.
	 */
	private function request( string $method, string $endpoint, array $args = [] ): array|WP_Error {
		$url = $args['url'] ?? $this->build_url( $endpoint );
		unset( $args['url'] );

		$args = array_merge(
			[
				'method'  => $method,
				'headers' => $this->get_default_headers(),
				'timeout' => self::TIMEOUT,
			],
			$args
		);

		/**
		 * Filter the API request arguments before sending.
		 *
		 * @param array<string, mixed> $args     Request arguments.
		 * @param string               $endpoint API endpoint path.
		 */
		$args = apply_filters( 'ihumbak_wca_api_request_args', $args, $endpoint );

		$this->logger->log_api_request( $method, $url, $args );

		$response = wp_remote_request( $url, $args );

		if ( ! is_wp_error( $response ) ) {
			$this->logger->log_api_response(
				$url,
				wp_remote_retrieve_response_code( $response ),
				wp_remote_retrieve_body( $response )
			);
		}

		return $this->parse_response( $response, $url );
	}

	/**
	 * Build the full API URL for a given endpoint.
	 *
	 * @param string               $endpoint API endpoint path.
	 * @param array<string, mixed> $query    Optional query parameters.
	 * @return string Full URL with base URL, endpoint, and query string.
	 */
	private function build_url( string $endpoint, array $query = [] ): string {
		$url = trailingslashit( $this->settings->get_base_url() ) . ltrim( $endpoint, '/' );

		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		return $url;
	}

	/**
	 * Get the default HTTP headers for API requests.
	 *
	 * @return array<string, string> Headers including API key and content type.
	 */
	private function get_default_headers(): array {
		return [
			'apiKey'       => $this->settings->get_api_key(),
			'Content-Type' => 'application/json',
			'Accept'       => 'application/json',
		];
	}

	/**
	 * Parse the HTTP response and handle errors.
	 *
	 * @param array<string, mixed>|WP_Error $response Raw WordPress HTTP response.
	 * @param string                        $url      Request URL for logging.
	 * @return array<string, mixed>|WP_Error Decoded JSON response or WP_Error on failure.
	 */
	private function parse_response( array|WP_Error $response, string $url ): array|WP_Error {
		if ( is_wp_error( $response ) ) {
			$this->logger->error(
				sprintf( 'HTTP request failed: %s', $response->get_error_message() ),
				[ 'url' => $url ]
			);

			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		$decoded = json_decode( $body, true );

		if ( $status_code >= 400 ) {
			return $this->parse_error_response( $body, $status_code );
		}

		if ( ! is_array( $decoded ) ) {
			return [];
		}

		return $decoded;
	}

	/**
	 * Parse a Conta API error response.
	 *
	 * Conta returns 422 errors in the following format:
	 * {"category":"CUSTOM","name":"customerNotFoundException","messages":{"EN":"...","NO":"..."},"properties":{}}
	 *
	 * @param string $body        Response body as JSON string.
	 * @param int    $status_code HTTP status code.
	 * @return WP_Error Structured error with Conta error name and message.
	 */
	private function parse_error_response( string $body, int $status_code ): WP_Error {
		$decoded    = json_decode( $body, true );
		$error_name = 'unknown_error';
		$message    = sprintf( 'API error: HTTP %d', $status_code );

		if ( is_array( $decoded ) ) {
			$error_name = $decoded['name'] ?? $error_name;

			if ( isset( $decoded['messages']['EN'] ) ) {
				$message = $decoded['messages']['EN'];
			}
		}

		$this->logger->error(
			sprintf( 'Conta API error [%d]: %s — %s', $status_code, $error_name, $message ),
			[
				'status_code' => $status_code,
				'body'        => $body,
			]
		);

		return new WP_Error(
			'conta_api_error',
			$message,
			[
				'status' => $status_code,
				'name'   => $error_name,
			]
		);
	}
}
