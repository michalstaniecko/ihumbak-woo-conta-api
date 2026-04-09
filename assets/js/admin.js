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
				var orgs = response.data.organizations || [];
				var html = '<span class="ihumbak-wca-success">&#10004; Connected!</span>';

				if ( orgs.length > 0 ) {
					html += '<div class="ihumbak-wca-orgs" style="margin-top:10px;">';
					html += '<strong>Select organization:</strong><br>';
					html += '<select id="ihumbak_wca_org_select" style="margin-top:5px;min-width:300px;">';
					html += '<option value="">— Select —</option>';

					var currentOrgId = $( '#ihumbak_wca_organization_id' ).val();

					for ( var i = 0; i < orgs.length; i++ ) {
						var selected = ( String( orgs[ i ].id ) === String( currentOrgId ) ) ? ' selected' : '';
						html += '<option value="' + orgs[ i ].id + '"' + selected + '>';
						html += orgs[ i ].name + ' (ID: ' + orgs[ i ].id + ')';
						html += '</option>';
					}

					html += '</select>';
					html += '</div>';
				}

				$result.html( html );

				// Auto-fill Organization ID when selection changes.
				$result.find( '#ihumbak_wca_org_select' ).on( 'change', function () {
					$( '#ihumbak_wca_organization_id' ).val( $( this ).val() );
				} );
			} else {
				$result.html(
					'<span class="ihumbak-wca-error">&#10008; Error: ' + response.data.message + '</span>'
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
