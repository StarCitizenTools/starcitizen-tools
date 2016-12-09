/*
 * This file is part of the MediaWiki extension UploadWizard.
 *
 * UploadWizard is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * UploadWizard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with UploadWizard.  If not, see <http://www.gnu.org/licenses/>.
 */

( function ( mw, $, uw, OO ) {
	/**
	 * Checkbox with popup information.
	 */
	function PopupCheckboxInputWidget( config ) {
		// Parent constructor
		PopupCheckboxInputWidget.parent.call( this, config );

		// Mixin constructors
		OO.ui.mixin.PopupElement.call( this, config );

		// Events
		this.connect( this, { change: 'onChange' } );

		// Initialization
		this.$element
			.addClass( 'oo-ui-popupCheckboxInputWidget' )
			.attr( 'aria-haspopup', 'true' )
			.append( this.popup.$element );
	}
	OO.inheritClass( PopupCheckboxInputWidget, OO.ui.CheckboxInputWidget );
	OO.mixinClass( PopupCheckboxInputWidget, OO.ui.mixin.PopupElement );
	PopupCheckboxInputWidget.prototype.onChange = function () {
		this.popup.toggle( this.isSelected() );
	};

	/**
	 * Represents the UI for the wizard's Tutorial step.
	 *
	 * @class uw.ui.Tutorial
	 * @extends uw.ui.Step
	 * @constructor
	 */
	uw.ui.Tutorial = function UWUITutorial() {
		var ui = this;

		uw.ui.Step.call(
			this,
			'tutorial'
		);

		this.$div.prepend(
			$( '<div>' )
				.attr( 'id', 'mwe-upwiz-tutorial' )
				.append(
					// TODO move this to JavaScript, too.
					$( '#mwe-upwiz-tutorial-html' ).show()
				)
		);

		// 'Skip tutorial' checkbox
		this.skipCheckbox = new PopupCheckboxInputWidget( {
			id: 'mwe-upwiz-skip',
			// Add a friendly "Here's how to get it back" tooltip for users who check the "Skip next time" checkbox
			popup: {
				$content: $( '<p>' ).msg(
					'mwe-upwiz-tooltip-skiptutorial',
					mw.config.get( 'wgServer' ) + mw.util.getUrl( 'Special:Preferences' ) + '#mw-prefsection-uploads',
					mw.message( 'prefs-uploads' ).text(),
					mw.message( 'prefs-upwiz-interface' ).text()
				),
				autoClose: false,
				padded: true
			}
		} );
		this.skipCheckboxLabel = new OO.ui.LabelWidget( {
			input: this.skipCheckbox,
			label: mw.message( 'mwe-upwiz-skip-tutorial-future' ).text()
		} );

		this.skipCheckbox.on( 'change', function () {
			ui.emit( 'skip-tutorial-click', ui.skipCheckbox.isSelected() );
		} );

		// Helpdesk link click
		$( '#mwe-upwiz-tutorial-helpdesk' ).click( function () {
			ui.emit( 'helpdesk-click' );
		} );

		this.addNextButton();

		this.$div.find( '.mwe-upwiz-buttons' ).append(
			new OO.ui.HorizontalLayout( {
				items: [ this.skipCheckbox, this.skipCheckboxLabel, this.nextButton ]
			} ).$element
		);
	};

	OO.inheritClass( uw.ui.Tutorial, uw.ui.Step );

}( mediaWiki, jQuery, mediaWiki.uploadWizard, OO ) );
