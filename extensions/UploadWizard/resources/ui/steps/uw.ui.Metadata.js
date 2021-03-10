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

( function ( uw ) {
	/**
	 * Represents the UI for the wizard's Metadata step.
	 *
	 * @class uw.ui.Metadata
	 * @extends uw.ui.Step
	 * @constructor
	 */
	uw.ui.Metadata = function UWUIMetadata() {
		var self = this;

		uw.ui.Step.call(
			this,
			'metadata'
		);

		this.nextButton = new OO.ui.ButtonWidget( {
			label: mw.message( 'mwe-upwiz-publish-metadata' ).text(),
			flags: [ 'progressive', 'primary' ]
		} ).on( 'click', function () {
			self.emit( 'submit' );
		} );

		this.skipButton = new OO.ui.ButtonWidget( {
			label: mw.message( 'mwe-upwiz-skip-metadata' ).text(),
			framed: false
		} ).on( 'click', function () {
			self.emit( 'next-step' );
		} );

		this.addPreviousButton();
		this.addNextButton();

		this.$content = $( '<div>' ).addClass( 'ui-helper-clearfix' );
	};

	OO.inheritClass( uw.ui.Metadata, uw.ui.Step );

	/**
	 * @inheritdoc
	 */
	uw.ui.Metadata.prototype.load = function ( uploads ) {
		this.$div.empty().append( this.$content );

		uw.ui.Step.prototype.load.call( this, uploads );

		this.$content.before(
			$( '<h3>' ).addClass( 'mwe-upwiz-metadata-title' ).text( mw.msg( 'mwe-upwiz-metadata-title' ) ),
			new OO.ui.PopupButtonWidget( {
				classes: [ 'mwe-upwiz-metadata-help' ],
				icon: 'info',
				framed: false,
				popup: {
					padded: true,
					$content: $( '<div>' ).append(
						$( '<p>' ).text( mw.msg( 'mwe-upwiz-metadata-help-title' ) ),
						$( '<p>' ).text( mw.msg( 'mwe-upwiz-metadata-help-content' ) )
					)
				}
			} ).$element
		).show();

		this.$content.after(
			$( '<div>' )
				.addClass( 'mwe-upwiz-license-metadata ui-corner-all' )
				.append(
					$( '<h4>' ).text( mw.msg( 'mwe-upwiz-license-metadata-statements-title' ) ),
					$( '<p>' ).append( mw.message( 'mwe-upwiz-license-metadata-content' ).parseDom() )
					// wikitext links in i18n messages don't support target=_blank, but we
					// really don't want to take people away from their uploads...
						.find( 'a' ).attr( 'target', '_blank' ).end()
				)
		);
	};

	/**
	 * @param {jQuery} $element
	 */
	uw.ui.Metadata.prototype.renderContent = function ( $element ) {
		this.$content.empty().append( $element );
	};

	/**
	 * @param {boolean} disabled
	 */
	uw.ui.Metadata.prototype.disableNextButton = function ( disabled ) {
		this.nextButton.setDisabled( disabled );
	};

	/**
	 * @inheritdoc
	 */
	uw.ui.Metadata.prototype.addNextButton = function () {
		var self = this;

		this.nextButtonPromise.done( function () {
			self.$buttons.append(
				$( '<div>' )
					.addClass( 'mwe-upwiz-file-next-all-ok mwe-upwiz-metadata-endchoice' )
					.append(
						new OO.ui.HorizontalLayout( {
							items: [
								self.skipButton,
								self.nextButton
							]
						} ).$element
					)
			);
		} );
	};

	/**
	 * Show a general error message above the publish button.
	 */
	uw.ui.Metadata.prototype.showPublishError = function () {
		this.error = new OO.ui.MessageWidget( {
			type: 'error',
			label: mw.message( 'mwe-upwiz-publish-metadata-error' ).parse(),
			classes: [ 'mwe-upwiz-metadata-publish-error' ]
		} );
		this.$buttons.before( this.error.$element );
	};

	/**
	 * Remove the error message widget.
	 */
	uw.ui.Metadata.prototype.hidePublishError = function () {
		if ( this.error ) {
			this.error.$element.remove();
			delete this.error;
		}
	};

}( mw.uploadWizard ) );
