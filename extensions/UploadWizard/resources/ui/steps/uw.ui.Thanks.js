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
	 * Represents the UI for the wizard's Thanks step.
	 *
	 * @class uw.ui.Thanks
	 * @extends uw.ui.Step
	 * @constructor
	 * @param {Object} config
	 */
	uw.ui.Thanks = function UWUIThanks( config ) {
		var $header,
			beginButtonTarget,
			thanks = this,
			userGroups = mw.config.get( 'wgUserGroups' );

		this.config = config;

		uw.ui.Step.call(
			this,
			'thanks'
		);

		this.$div.prepend(
			$( '<div>' ).attr( 'id', 'mwe-upwiz-thanks' )
		);

		if ( this.isObjectReferenceGiven() ) {
			this.getDelayNotice().prependTo( this.$div );
		}

		$( '<p>' )
			.addClass( 'mwe-upwiz-thanks-explain' )
			.msg( 'mwe-upwiz-thanks-explain' )
			.prependTo( this.$div );

		$header = $( '<h3>' )
			.addClass( 'mwe-upwiz-thanks-header' )
			.prependTo( this.$div );

		if ( !this.config.display || !this.config.display.thanksLabel ) {
			$header.text( mw.message( 'mwe-upwiz-thanks-intro' ).text() );
		} else {
			$header.html( this.config.display.thanksLabel );
		}

		this.homeButton = new OO.ui.ButtonWidget( {
			label: this.getButtonConfig( 'homeButton', 'label' ) || mw.message( 'mwe-upwiz-home' ).text(),
			href: this.getButtonConfig( 'homeButton', 'target' ) || mw.config.get( 'wgArticlePath' ).replace( '$1', '' )
		} );

		this.beginButton = new OO.ui.ButtonWidget( {
			label: this.getButtonConfig( 'beginButton', 'label' ) || mw.message( 'mwe-upwiz-upload-another' ).text(),
			flags: [ 'progressive', 'primary' ]
		} );

		// TODO: make the step order configurable by campaign definitions instead of using these hacks
		beginButtonTarget = this.getButtonConfig( 'beginButton', 'target' );
		if ( !beginButtonTarget || ( beginButtonTarget === 'dropObjref' && !this.isObjectReferenceGiven() ) ) {
			this.beginButton.on( 'click', function () {
				thanks.emit( 'next-step' );
			} );
		} else {
			if ( beginButtonTarget === 'dropObjref' ) {
				beginButtonTarget = this.dropParameterFromURL( location.href, 'updateList' );
			}
			this.beginButton.setHref( beginButtonTarget );
		}
		this.beginButton.on( 'click', function () {
			mw.DestinationChecker.clearCache();
		} );

		this.buttonGroup = new OO.ui.HorizontalLayout( {
			items: [ this.homeButton, this.beginButton ]
		} );

		this.$buttons.append( this.buttonGroup.$element );

		// If appropriate, add a dismissable Machine Vision CTA above content.
		mw.loader.using( 'ext.MachineVision.config' ).then( function ( require ) {
			var machineVisionConfig = require( 'ext.MachineVision.config' );
			if ( machineVisionConfig && userGroups &&
				machineVisionConfig.showComputerAidedTaggingCallToAction === true &&
				userGroups.indexOf( 'autoconfirmed' ) !== -1 &&
				( machineVisionConfig.testersOnly === false ||
					userGroups.indexOf( 'machinevision-tester' ) !== -1 ) ) {
				thanks.mvCtaCheckbox = new OO.ui.CheckboxInputWidget( { value: 'Notify me' } )
					.connect( thanks, { change: thanks.onMvCtaCheckboxChange } );
				thanks.addMachineVisionCta();
			}
		} );
	};

	OO.inheritClass( uw.ui.Thanks, uw.ui.Step );

	/**
	 * Adds an upload to the Thanks interface.
	 *
	 * @param {mw.UploadWizardUpload} upload
	 */
	uw.ui.Thanks.prototype.addUpload = function ( upload ) {
		var thumbWikiText, $thanksDiv, $thumbnailWrapDiv, $thumbnailDiv, $thumbnailCaption, $thumbnailLink;

		thumbWikiText = '[[' + [
			upload.details.getTitle().getPrefixedText(),
			'thumb',
			upload.details.getThumbnailCaption()
		].join( '|' ) + ']]';

		$thanksDiv = $( '<div>' )
			.addClass( 'mwe-upwiz-thanks ui-helper-clearfix' );
		$thumbnailWrapDiv = $( '<div>' )
			.addClass( 'mwe-upwiz-thumbnail-side' )
			.appendTo( $thanksDiv );
		$thumbnailDiv = $( '<div>' )
			.addClass( 'mwe-upwiz-thumbnail' )
			.appendTo( $thumbnailWrapDiv );
		$thumbnailCaption = $( '<div>' )
			.css( { 'text-align': 'center', 'font-size': 'small' } )
			.appendTo( $thumbnailWrapDiv );
		$thumbnailLink = $( '<a>' )
			.text( upload.details.getTitle().getMainText() )
			.appendTo( $thumbnailCaption );

		$( '<div>' )
			.addClass( 'mwe-upwiz-data' )
			.appendTo( $thanksDiv )
			.append(
				this.makeReadOnlyInput( thumbWikiText, mw.message( 'mwe-upwiz-thanks-wikitext' ).text(), true ),
				this.makeReadOnlyInput( upload.imageinfo.descriptionurl, mw.message( 'mwe-upwiz-thanks-url' ).text() )
			);

		// This must match the CSS dimensions of .mwe-upwiz-thumbnail
		upload.getThumbnail( 120, 120 ).done( function ( thumb ) {
			mw.UploadWizard.placeThumbnail( $thumbnailDiv, thumb );
		} );

		// Set the thumbnail links so that they point to the image description page
		$thumbnailLink.add( $thumbnailDiv.find( '.mwe-upwiz-thumbnail-link' ) ).attr( {
			href: upload.imageinfo.descriptionurl,
			target: '_blank'
		} );

		this.$div.find( '.mwe-upwiz-buttons' ).before( $thanksDiv );
	};

	/**
	 * Add a call to action to opt into notifications for the machine vision
	 * tool, and to visit the tool to tag popular uploads.
	 */
	uw.ui.Thanks.prototype.addMachineVisionCta = function () {
		var mvCtaDismissedKey = 'upwiz_mv_cta_dismissed',
			$mvCtaDiv,
			$mvCtaDismiss,
			$mvCtaContent,
			$mvCtaCheckboxSection,
			mvCtaCheckboxField;

		// If the user has already opted into notifications or dismissed the
		// CTA previously, don't show this.
		if ( Number( mw.user.options.get( 'echo-subscriptions-web-machinevision' ) ) === 1 ||
			Number( mw.user.options.get( mvCtaDismissedKey ) ) === 1 ) {
			return;
		}

		// Wrapper div.
		$mvCtaDiv = $( '<div>' ).addClass( 'mwe-upwiz-mv-cta' );

		// Add dismiss icon button.
		$mvCtaDismiss = new OO.ui.ButtonWidget( {
			classes: [ 'mwe-upwiz-mv-cta-dismiss' ],
			icon: 'close',
			invisibleLabel: true,
			label: mw.message( 'mwe-upwiz-mv-cta-dismiss' ).text(),
			title: mw.message( 'mwe-upwiz-mv-cta-dismiss' ).text()
		} ).on( 'click', function () {
			$mvCtaDiv.remove();

			// Set user preference to not show this again.
			new mw.Api().saveOption( mvCtaDismissedKey, 1 );
			mw.user.options.set( mvCtaDismissedKey, 1 );
		} );
		$mvCtaDismiss.$element.appendTo( $mvCtaDiv );

		// Add icon div.
		$mvCtaDiv.append( $( '<div>' ).addClass( 'mwe-upwiz-mv-cta-icon' ) );

		// Add wrapper for everything to the right of the icon.
		$mvCtaContent = $( '<div>' )
			.addClass( 'mwe-upwiz-mv-cta-content' )
			.appendTo( $mvCtaDiv );

		// Add heading.
		$mvCtaContent.append( $( '<h3>' )
			.addClass( 'mwe-upwiz-mv-cta-heading' )
			.msg( 'mwe-upwiz-mv-cta-heading' )
		);

		// Add description text.
		$mvCtaContent.append( $( '<p>' )
			.addClass( 'mwe-upwiz-mv-cta-description' )
			.msg( 'mwe-upwiz-mv-cta-description' )
		);

		// Add wrapper checkbox and confirmation message.
		$mvCtaCheckboxSection = $( '<div>' )
			.addClass( 'mwe-upwiz-mv-cta-checkbox-section' )
			.appendTo( $mvCtaContent );

		// Add checkbox field layout.
		mvCtaCheckboxField = new OO.ui.FieldLayout( this.mvCtaCheckbox, {
			label: mw.message( 'mwe-upwiz-mv-cta-checkbox-label' ).text(),
			align: 'inline'
		} );
		mvCtaCheckboxField.$element.appendTo( $mvCtaCheckboxSection );

		// Add final CTA to go to the MV tool.
		$mvCtaContent.append( $( '<p>' )
			.addClass( 'mwe-upwiz-mv-cta-final-cta' )
			.msg( 'mwe-upwiz-mv-cta-final-cta' )
		);

		// Add entire element above the main content of this step.
		this.$div.find( '.mwe-upwiz-thanks-header' ).before( $mvCtaDiv );
	};

	/**
	 * Handle user preference update when user checks box to opt into or out of
	 * notifications for the machine vision tool.
	 */
	uw.ui.Thanks.prototype.onMvCtaCheckboxChange = function () {
		var self = this,
			selected = this.mvCtaCheckbox.isSelected(),
			key = 'echo-subscriptions-web-machinevision',
			value = selected ? 1 : 0,
			message = selected ?
				'mwe-upwiz-mv-cta-user-preference-set' :
				'mwe-upwiz-mv-cta-user-preference-unset',
			$mvCtaCheckboxSection = this.$div.find( '.mwe-upwiz-mv-cta-checkbox-section' );

		// Remove existing confirmation message if there is one.
		this.$div.find( '.mwe-upwiz-mv-cta-confirmation' ).remove();

		// Disable the checkbox until API call finishes.
		this.mvCtaCheckbox.setDisabled( true );

		// Update the user preference in the database and in local storage.
		// Only authenticated users can use UW, so no need to check isAnon().
		new mw.Api().saveOption( key, value )
			.done( function () {
				// Show the appropriate message.
				$mvCtaCheckboxSection.append( $( '<p>' )
					.addClass( 'mwe-upwiz-mv-cta-confirmation' )
					.msg( message )
				);
				mw.user.options.set( key, value );
			} )
			.fail( function () {
				message = 'mwe-upwiz-mv-cta-user-preference-set-failed';
				$mvCtaCheckboxSection.append( $( '<p>' )
					.addClass( 'mwe-upwiz-mv-cta-confirmation' )
					.msg( message )
				);
			} )
			.always( function () {
				self.mvCtaCheckbox.setDisabled( false );
			} );
	};

	/**
	 * Make an mw.widgets.CopyTextLayout, which features a button
	 * to copy the text provided.
	 *
	 * @param {string} value Text it will contain
	 * @param {string} label Label
	 * @param {string} [useEditFont] Use edit font (for wikitext values)
	 * @return {jQuery}
	 */
	uw.ui.Thanks.prototype.makeReadOnlyInput = function ( value, label, useEditFont ) {
		var copyText = new mw.widgets.CopyTextLayout( {
			align: 'top',
			label: label,
			copyText: value
		} );

		if ( useEditFont ) {
			copyText.textInput.$element.addClass( 'mw-editfont-' + mw.user.options.get( 'editfont' ) );
		}

		return copyText.$element;
	};

	/**
	 * Get button configuration options from a campaign definition
	 *
	 * @param {string} buttonName name of the button as defined in campaign configuration
	 * @param {string} configField name of the button's attributes
	 * @return {Object|undefined}
	 */
	uw.ui.Thanks.prototype.getButtonConfig = function ( buttonName, configField ) {
		if ( !this.config.display || !this.config.display[ buttonName ] ) {
			return;
		}

		return this.config.display[ buttonName ][ configField ];
	};

	/**
	 * Drops a parameter from the given url
	 *
	 * @param {string} url URL from which to drop a parameter
	 * @param {string} paramName parameter to be dropped
	 * @return {string}
	 * @private
	 */
	uw.ui.Thanks.prototype.dropParameterFromURL = function ( url, paramName ) {
		var newUrl = new mw.Uri( url );
		if ( newUrl.query ) {
			delete newUrl.query[ paramName ];
			delete newUrl.query[ paramName + '[]' ];
		}
		return newUrl.toString();
	};

	uw.ui.Thanks.prototype.getDelayNotice = function () {
		var $delayNotice = $( '<p>' )
			.addClass( 'mwe-upwiz-thanks-update-delay' )
			.msg( 'mwe-upwiz-objref-notice-update-delay' );

		if ( this.config.display && this.config.display.noticeUpdateDelay ) {
			$delayNotice.html( this.config.display.noticeUpdateDelay );
		}
		return $delayNotice;
	};

	uw.ui.Thanks.prototype.isObjectReferenceGiven = function () {
		return this.config.defaults && this.config.defaults.objref !== '';
	};

}( mw.uploadWizard ) );
