<?php
/**
 * Logger service for Conta.no API integration.
 *
 * @package Ihumbak\WooConta
 */

declare(strict_types=1);

namespace Ihumbak\WooConta\Services;

use WC_Logger_Interface;

/**
 * Wraps WC_Logger to provide structured logging for the plugin.
 */
class Logger {

	/**
	 * Log source identifier for WooCommerce logs.
	 *
	 * @var string
	 */
	private const LOG_SOURCE = 'ihumbak-woo-conta-api';

	/**
	 * WooCommerce logger instance.
	 *
	 * @var WC_Logger_Interface|null
	 */
	private ?WC_Logger_Interface $logger = null;

	/**
	 * Get the WooCommerce logger instance, lazy-loading on first use.
	 *
	 * @return WC_Logger_Interface
	 */
	private function get_logger(): WC_Logger_Interface {
		if ( null === $this->logger ) {
			$this->logger = wc_get_logger();
		}

		return $this->logger;
	}

	/**
	 * Log an informational message.
	 *
	 * @param string               $message The log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public function info( string $message, array $context = [] ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Log an error message.
	 *
	 * @param string               $message The log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public function error( string $message, array $context = [] ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Log a debug message.
	 *
	 * @param string               $message The log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public function debug( string $message, array $context = [] ): void {
		$this->log( 'debug', $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * @param string               $message The log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	public function warning( string $message, array $context = [] ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Log an outgoing API request.
	 *
	 * Masks sensitive data such as API keys before writing to the log.
	 *
	 * @param string               $method HTTP method (GET, POST, etc.).
	 * @param string               $url    Request URL.
	 * @param array<string, mixed> $args   Request arguments including headers and body.
	 * @return void
	 */
	public function log_api_request( string $method, string $url, array $args = [] ): void {
		$safe_args = $this->mask_sensitive_data( $args );

		$this->log(
			'info',
			sprintf( 'API Request: %s %s', $method, $url ),
			$safe_args
		);
	}

	/**
	 * Log an incoming API response.
	 *
	 * Truncates the response body to a maximum of 1000 characters for debug output.
	 *
	 * @param string $url         Request URL.
	 * @param int    $status_code HTTP status code.
	 * @param string $body        Response body.
	 * @return void
	 */
	public function log_api_response( string $url, int $status_code, string $body ): void {
		$truncated_body = mb_substr( $body, 0, 1000 );

		$this->log(
			'debug',
			sprintf( 'API Response: %s [%d]', $url, $status_code ),
			[
				'status_code' => $status_code,
				'body'        => $truncated_body,
			]
		);
	}

	/**
	 * Central logging method used by all public methods.
	 *
	 * @param string               $level   Log level (debug, info, warning, error).
	 * @param string               $message The log message.
	 * @param array<string, mixed> $context Additional context data.
	 * @return void
	 */
	private function log( string $level, string $message, array $context = [] ): void {
		$formatted = $message;

		if ( ! empty( $context ) ) {
			$formatted .= ' ' . $this->format_context( $context );
		}

		$this->get_logger()->log(
			$level,
			$formatted,
			[ 'source' => self::LOG_SOURCE ]
		);
	}

	/**
	 * Format context array as a human-readable string.
	 *
	 * @param array<string, mixed> $context Context key-value pairs.
	 * @return string Formatted string like [key=value, key2=value2].
	 */
	private function format_context( array $context ): string {
		$parts = [];

		foreach ( $context as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = wp_json_encode( $value );
			}

			$parts[] = sprintf( '%s=%s', $key, (string) $value );
		}

		return '[' . implode( ', ', $parts ) . ']';
	}

	/**
	 * Recursively mask sensitive data in an array.
	 *
	 * Replaces values for keys named 'apiKey' with '***'.
	 *
	 * @param array<string, mixed> $data Data to sanitize.
	 * @return array<string, mixed> Sanitized data.
	 */
	private function mask_sensitive_data( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( 'apiKey' === $key ) {
				$data[ $key ] = '***';
			} elseif ( is_array( $value ) ) {
				$data[ $key ] = $this->mask_sensitive_data( $value );
			}
		}

		return $data;
	}
}
