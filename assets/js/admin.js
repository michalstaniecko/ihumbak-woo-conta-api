/**
 * Admin JavaScript for Conta.no integration settings.
 *
 * Handles the AJAX test connection button on the WooCommerce settings page.
 *
 * @package Ihumbak\WooConta
 */

/* global jQuery, ajaxurl, ihumbak_wca_admin */
jQuery( function ( $ ) {
	'use strict';

	$( '#ihumbak_wca_test_connection' ).on( 'click', function ( e ) {
		e.preventDefault();

		var $button = $( this );
		var $result = $( '#ihumbak_wca_connection_result' );

		$button.prop( 'disabled', true ).text( ihumbak_wca_admin.testing || 'Testing...' );
		$result.html( '' );

		$.post( ajaxurl, {
			action: 'ihumbak_wca_test_connection',
			_ajax_nonce: ihumbak_wca_admin.nonce,
			api_key: $( '#ihumbak_wca_api_key' ).val(),
			environment: $( '#ihumbak_wca_environment' ).val()
		}, function ( response ) {
			$button.prop( 'disabled', false ).text( 'Test Connection' );

			if ( response.success ) {
				$result.html(
					'<span class="ihumbak-wca-success">' +
					'Connected! Organizations: ' + response.data.count +
					'</span>'
				);
			} else {
				$result.html(
					'<span class="ihumbak-wca-error">' +
					'Error: ' + response.data.message +
					'</span>'
				);
			}
		} ).fail( function () {
			$button.prop( 'disabled', false ).text( 'Test Connection' );
			$result.html(
				'<span class="ihumbak-wca-error">Request failed. Please try again.</span>'
			);
		} );
	} );
} );
