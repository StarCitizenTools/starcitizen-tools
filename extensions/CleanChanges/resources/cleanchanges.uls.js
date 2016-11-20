/*!
 *
 * @author Niklas Laxström
 * @license GPL-2.0+
 */
( function ( $, mw ) {
	'use strict';

	function useULS( $trigger ) {
		var update, $selected, $clear, $button = $( '<span>' );

		$button
			.addClass( 'ext-cc-language-selector__trigger' );

		$clear = $( '<span>' )
			.text( 'X' )
			.addClass( 'ext-cc-language-selector__clear' );

		$trigger.hide().after(
			$( '<span>' )
				.addClass( 'ext-cc-language-selector' )
				.append( $button, $clear )
		);

		update = function ( value ) {
			$selected = $trigger.children( ':selected' );
			if ( value === '' ) {
				$button.text( $selected.text() );
				$clear.hide();
			} else {
				$button.text( $.uls.data.getAutonym( value ) );
				$clear.show();
			}
		};

		update( $trigger.val().replace( '/', '' ) );

		$clear.on( 'click', function () {
			$trigger.val( '' );
			update( '' );
			$( this ).hide();
		} );

		$button.uls( {
			onSelect: function ( language ) {
				$trigger.val( '/' + language );
				update( language );
			},
			quickList: mw.uls.getFrequentLanguageList
		} );
	}

	$( document ).ready( function () {
		var $trigger = $( '#sp-rc-language' );

		if ( $trigger.length ) {
			mw.loader.using( 'ext.uls.mediawiki', function () {
				useULS( $trigger );
			} );
		}
	} );
}( jQuery, mediaWiki ) );
