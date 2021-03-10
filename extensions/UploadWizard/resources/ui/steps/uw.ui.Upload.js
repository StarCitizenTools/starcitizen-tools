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
	 * Represents the UI for the wizard's Upload step.
	 *
	 * @class uw.ui.Upload
	 * @extends uw.ui.Step
	 * @constructor
	 * @param {Object} config UploadWizard config object.
	 */
	uw.ui.Upload = function UWUIUpload( config ) {
		var upload = this;

		this.config = config;

		uw.ui.Step.call(
			this,
			'file'
		);

		this.$addFileContainer = $( '<div>' )
			.attr( 'id', 'mwe-upwiz-add-file-container' )
			.addClass( 'mwe-upwiz-add-files-0' );

		this.$uploadCtrl = $( '<div>' )
			.attr( 'id', 'mwe-upwiz-upload-ctrls' )
			.addClass( 'mwe-upwiz-file ui-helper-clearfix' )
			.append( this.$addFileContainer );

		this.addFile = new OO.ui.ButtonWidget( {
			id: 'mwe-upwiz-add-file',
			label: mw.message( 'mwe-upwiz-add-file-0-free' ).text(),
			flags: [ 'progressive', 'primary' ]
		} );

		this.$addFileContainer.append( this.addFile.$element );

		if ( this.isFlickrImportEnabled() ) {
			this.$flickrAddFileContainer = $( '<div>' )
				.attr( 'id', 'mwe-upwiz-upload-ctrl-flickr-container' );

			this.$uploadCenterDivide = $( '<p>' )
				.attr( 'id', 'mwe-upwiz-upload-ctr-divide' )
				.text( mw.message( 'mwe-upwiz-add-flickr-or' ).text() );

			this.addFlickrFile = new OO.ui.ButtonWidget( {
				id: 'mwe-upwiz-add-flickr-file',
				label: mw.message( 'mwe-upwiz-add-file-flickr' ).text(),
				flags: 'progressive'
			} ).on( 'click', function () {
				upload.flickrInterfaceInit();
				uw.eventFlowLogger.logEvent( 'flickr-upload-button-clicked' );
			} );

			this.$flickrAddFileContainer.append(
				this.$uploadCenterDivide,
				this.addFlickrFile.$element
			);

			this.$addFileContainer
				.append( this.$flickrAddFileContainer );

			this.$flickrSelectList = $( '<div>' )
				.attr( 'id', 'mwe-upwiz-flickr-select-list' );

			this.$flickrSelectListContainer = $( '<div>' )
				.attr( 'id', 'mwe-upwiz-flickr-select-list-container' )
				.addClass( 'ui-corner-all' )
				.append(
					$( '<div>' )
						.text( mw.message(
							'mwe-upwiz-multi-file-select2',
							config.maxUploads
						) ),
					this.$flickrSelectList
				);

			// Button to move on & upload the files that were selected
			this.flickrSelectButton = new OO.ui.ButtonWidget( {
				id: 'mwe-upwiz-select-flickr',
				label: mw.message( 'mwe-upwiz-add-file-0-free' ).text(),
				flags: [ 'progressive', 'primary' ]
			} );
			this.$flickrSelectListContainer.append( this.flickrSelectButton.$element );

			// A container holding a form
			this.$flickrContainer = $( '<div id="mwe-upwiz-upload-add-flickr-container"></div>' );

			// Form whose submit event will be listened to and prevented
			this.$flickrForm = $( '<form id="mwe-upwiz-flickr-url-form"></form>' )
				.appendTo( this.$flickrContainer )
				.on( 'submit', function () {
					var checker = new mw.FlickrChecker( upload, upload.flickrSelectButton );
					upload.flickrButton.setDisabled( true );
					upload.flickrChecker( checker );
					// TODO Any particular reason to stopPropagation ?
					return false;
				} );

			// The input that will hold a flickr URL entered by the user; will be appended to a form
			this.$flickrInput = $( '<input id="mwe-upwiz-flickr-input" type="text" />' )
				.attr( 'placeholder', mw.message( 'mwe-upwiz-flickr-input-placeholder' ).text() )
				.prependTo( this.$flickrForm );

			this.flickrButton = new OO.ui.ButtonInputWidget( {
				id: 'mwe-upwiz-upload-ctrl-flickr',
				label: mw.message( 'mwe-upwiz-add-flickr' ).text(),
				flags: [ 'progressive', 'primary' ],
				type: 'submit'
			} );
			this.$flickrForm.append( this.flickrButton.$element );

			// Add disclaimer
			$( '<div id="mwe-upwiz-flickr-disclaimer"></div>' )
				.html(
					mw.message( 'mwe-upwiz-flickr-disclaimer1' ).parse() +
					'<br/>' + mw.message( 'mwe-upwiz-flickr-disclaimer2' ).parse()
				)
				.appendTo( this.$flickrContainer );
		}

		this.nextStepButtonAllOk = new OO.ui.ButtonWidget( {
			label: mw.message( 'mwe-upwiz-next-file' ).text(),
			flags: [ 'progressive', 'primary' ]
		} ).on( 'click', function () {
			upload.emit( 'next-step' );
		} );

		this.retryButtonSomeFailed = new OO.ui.ButtonWidget( {
			label: mw.message( 'mwe-upwiz-file-retry' ).text(),
			flags: [ 'progressive' ]
		} ).on( 'click', function () {
			upload.hideEndButtons();
			upload.emit( 'retry' );
		} );

		this.nextStepButtonSomeFailed = new OO.ui.ButtonWidget( {
			label: mw.message( 'mwe-upwiz-next-file-despite-failures' ).text(),
			flags: [ 'progressive', 'primary' ]
		} ).on( 'click', function () {
			upload.emit( 'next-step' );
		} );

		this.retryButtonAllFailed = new OO.ui.ButtonWidget( {
			label: mw.message( 'mwe-upwiz-file-retry' ).text(),
			flags: [ 'progressive' ]
		} ).on( 'click', function () {
			upload.hideEndButtons();
			upload.emit( 'retry' );
		} );

		this.$fileList = $( '<div>' )
			.attr( 'id', 'mwe-upwiz-filelist' )
			.addClass( 'ui-corner-all' );

		this.$progress = $( '<div>' )
			.attr( 'id', 'mwe-upwiz-progress' )
			.addClass( 'ui-helper-clearfix' );

		this.addPreviousButton();
		this.addNextButton();
	};

	OO.inheritClass( uw.ui.Upload, uw.ui.Step );

	/**
	 * Set up the "Add files" button (#mwe-upwiz-add-file) to open a file selection dialog on click
	 * by means of a hidden `<input type="file">`.
	 *
	 * @param {jQuery} $element The element to append to
	 */
	uw.ui.Upload.prototype.setupFileInputCtrl = function ( $element ) {
		var $fileInputCtrl = $( '<input type="file" multiple name="file" class="mwe-upwiz-file-input" />' ),
			ui = this;

		// Check for iOS 5 Safari's lack of file uploads (T34328#364508).
		// While this looks extremely unlikely to be right, it actually is. Blame Apple.
		if ( $fileInputCtrl.prop( 'disabled' ) ) {
			$element.replaceWith(
				$( '<span>' ).msg( 'mwe-upwiz-file-upload-notcapable' )
			);

			return;
		}

		$element.find( '.mwe-upwiz-file-input' ).remove();
		$element.append( $fileInputCtrl );

		$fileInputCtrl.on( 'change', function () {
			ui.emit( 'files-added', $fileInputCtrl[ 0 ].files );

			// We can't clear the value of a file input, so replace the whole
			// thing with a new one
			ui.setupFileInputCtrl( $element );
		} );

		$fileInputCtrl.on( 'focus', function () {
			// In IE 11, focussing a file input (by clicking on it) displays a text cursor and scrolls
			// the cursor into view (in this case, it scrolls the button, which has 'overflow: hidden').
			// Since this messes with our custom styling (the file input has large dimensions and this
			// causes the label to scroll out of view), scroll the button back to top. (T192131)
			$element.prop( 'scrollTop', 0 );
		} );
	};

	uw.ui.Upload.prototype.showProgressBar = function () {
		this.$progress.show();
	};

	/**
	 * Updates the interface based on the number of uploads.
	 *
	 * @param {boolean} haveUploads Whether there are any uploads at all.
	 * @param {boolean} fewerThanMax Whether we can add more uploads.
	 */
	uw.ui.Upload.prototype.updateFileCounts = function ( haveUploads, fewerThanMax ) {
		this.$fileList.toggleClass( 'mwe-upwiz-filled-filelist', haveUploads );
		this.$addFileContainer.toggleClass( 'mwe-upwiz-add-files-0', !haveUploads );

		this.setAddButtonText( haveUploads );

		if ( haveUploads ) {
			// we have uploads ready to go, so allow us to proceed
			this.$addFileContainer.add( this.$buttons ).show();

			if ( this.isFlickrImportEnabled() ) {
				this.$uploadCenterDivide.hide();
			}

			// fix the rounded corners on file elements.
			// we want them to be rounded only when their edge touched the top or bottom of the filelist.
			this.$fileListings = this.$fileList.find( '.filled' );

			this.$visibleFileListings = this.$fileListings.find( '.mwe-upwiz-visible-file' );
			this.$visibleFileListings.removeClass( 'ui-corner-top ui-corner-bottom' );
			this.$visibleFileListings.first().addClass( 'ui-corner-top' );
			this.$visibleFileListings.last().addClass( 'ui-corner-bottom' );

			this.$fileListings.filter( ':odd' ).addClass( 'odd' );
			this.$fileListings.filter( ':even' ).removeClass( 'odd' );
		} else {
			this.hideEndButtons();

			if ( this.isFlickrImportEnabled() ) {
				this.$uploadCenterDivide.show();
			}
		}

		this.addFile.setDisabled( !fewerThanMax );

		if ( this.isFlickrImportEnabled() ) {
			this.addFlickrFile.setDisabled( !fewerThanMax );
		}

		this.addFile.$element.find( '.mwe-upwiz-file-input' ).prop( 'disabled', !fewerThanMax );
	};

	/**
	 * Changes the initial centered invitation button to something like "add another file"
	 *
	 * @param {boolean} more
	 */
	uw.ui.Upload.prototype.setAddButtonText = function ( more ) {
		var msg = 'mwe-upwiz-add-file-',
			fmsg = 'mwe-upwiz-add-file-flickr';

		if ( more ) {
			msg += 'n';
			fmsg += '-n';
		} else {
			msg += '0-free';
		}

		this.addFile.setLabel( mw.message( msg ).text() );

		// if Flickr uploading is available to this user, show the "add more files from flickr" button
		if ( this.isFlickrImportEnabled() ) {
			// changes the flickr add button to "add more files from flickr" if necessary.
			this.addFlickrFile.setLabel( mw.message( fmsg ).text() );
			// jQuery likes to restore the wrong 'display' value when doing .show()
			this.$flickrAddFileContainer.css( 'display', '' );
		}
	};

	uw.ui.Upload.prototype.load = function ( uploads ) {
		uw.ui.Step.prototype.load.call( this, uploads );

		if ( uploads.length === 0 ) {
			this.$fileList.removeClass( 'mwe-upwiz-filled-filelist' );
		}

		this.$div.prepend(
			$( '<div>' )
				.attr( 'id', 'mwe-upwiz-files' )
				.append(
					this.$flickrSelectListContainer,
					this.$fileList,
					this.$uploadCtrl
				)
		);

		// append <input type="file"> to button
		this.setupFileInputCtrl( this.addFile.$element.find( '.oo-ui-buttonElement-button' ) );

		// Show the upload button, and the add file button
		$( '#mwe-upwiz-upload-ctrls' ).show();
		$( '#mwe-upwiz-add-file' ).show();
	};

	uw.ui.Upload.prototype.displayUploads = function ( uploads ) {
		var
			thumbPromise,
			uploadInterfaceDivs = [];

		$.each( uploads, function ( i, upload ) {
			// We'll attach all interfaces to the DOM at once rather than one-by-one, for better
			// performance
			uploadInterfaceDivs.push( upload.ui.div );
		} );

		// Attach all interfaces to the DOM
		this.$fileList.append( $( uploadInterfaceDivs ) );

		// Display thumbnails, but not all at once because they're somewhat expensive to generate.
		// This will wait for each thumbnail to be complete before starting the next one.
		thumbPromise = $.Deferred().resolve();
		$.each( uploads, function ( i, upload ) {
			thumbPromise = thumbPromise.then( function () {
				var deferred = $.Deferred();
				setTimeout( function () {
					if ( this.movedFrom ) {
						// We're no longer displaying any of these thumbnails, stop
						deferred.reject();
					}
					upload.ui.showThumbnail().done( function () {
						deferred.resolve();
					} );
				} );
				return deferred.promise();
			} );
		} );
	};

	uw.ui.Upload.prototype.addNextButton = function () {
		var ui = this;

		this.nextButtonPromise.done( function () {
			ui.$buttons.append(
				$( '<div>' )
					.addClass( 'mwe-upwiz-file-next-all-ok mwe-upwiz-file-endchoice' )
					.append(
						new OO.ui.HorizontalLayout( {
							items: [
								new OO.ui.LabelWidget( {
									label: mw.message( 'mwe-upwiz-file-all-ok' ).text()
								} ),
								ui.nextStepButtonAllOk
							]
						} ).$element
					)
			);

			ui.$buttons.append(
				$( '<div>' )
					.addClass( 'mwe-upwiz-file-next-some-failed mwe-upwiz-file-endchoice' )
					.append(
						new OO.ui.HorizontalLayout( {
							items: [
								new OO.ui.LabelWidget( {
									label: mw.message( 'mwe-upwiz-file-some-failed' ).text()
								} ),
								ui.retryButtonSomeFailed,
								ui.nextStepButtonSomeFailed
							]
						} ).$element
					)
			);

			ui.$buttons.append(
				$( '<div>' )
					.addClass( 'mwe-upwiz-file-next-all-failed mwe-upwiz-file-endchoice' )
					.append(
						new OO.ui.HorizontalLayout( {
							items: [
								new OO.ui.LabelWidget( {
									label: mw.message( 'mwe-upwiz-file-all-failed' ).text()
								} ),
								ui.retryButtonAllFailed
							]
						} ).$element
					)
			);

			ui.$buttons.append( ui.$progress );
		} );
	};

	/**
	 * Hide the buttons for moving to the next step.
	 */
	uw.ui.Upload.prototype.hideEndButtons = function () {
		this.$div
			.find( '.mwe-upwiz-buttons .mwe-upwiz-file-endchoice' )
			.hide();
	};

	/**
	 * Shows an error dialog informing the user that some uploads have been omitted
	 * since they went over the max files limit.
	 *
	 * @param {number} filesUploaded The number of files that have been attempted to upload
	 */
	uw.ui.Upload.prototype.showTooManyFilesError = function ( filesUploaded ) {
		mw.errorDialog(
			mw.message(
				'mwe-upwiz-too-many-files-text',
				this.config.maxUploads,
				filesUploaded
			).text(),
			mw.message( 'mwe-upwiz-too-many-files' ).text()
		);
	};

	/**
	 * Shows an error dialog informing the user that an upload omitted because
	 * it is too large.
	 *
	 * @param {number} maxSize The max upload file size
	 * @param {number} size The actual upload file size
	 */
	uw.ui.Upload.prototype.showFileTooLargeError = function ( maxSize, size ) {
		mw.errorDialog(
			mw.message(
				'mwe-upwiz-file-too-large-text',
				uw.units.bytes( maxSize ),
				uw.units.bytes( size )
			).text(),
			mw.message( 'mwe-upwiz-file-too-large' ).text()
		);
	};

	/**
	 * @param {string} filename
	 * @param {string} extension
	 */
	uw.ui.Upload.prototype.showBadExtensionError = function ( filename, extension ) {
		var $errorMessage = $( '<p>' ).msg( 'mwe-upwiz-upload-error-bad-filename-extension', extension );
		this.showFilenameError( $errorMessage );
	};

	uw.ui.Upload.prototype.showMissingExtensionError = function () {
		var $errorMessage = $( '<p>' ).msg( 'mwe-upwiz-upload-error-bad-filename-no-extension' );
		this.showFilenameError(
			$( '<div>' ).append(
				$errorMessage,
				$( '<p>' ).msg( 'mwe-upwiz-allowed-filename-extensions' ),
				$( '<blockquote>' ).append( $( '<tt>' ).append(
					mw.UploadWizard.config.fileExtensions.join( ' ' )
				) )
			)
		);
	};

	/**
	 * @param {string} filename
	 * @param {string} basename
	 */
	uw.ui.Upload.prototype.showDuplicateError = function ( filename, basename ) {
		this.showFilenameError( $( '<p>' ).msg( 'mwe-upwiz-upload-error-duplicate-filename-error', basename ) );
	};

	/**
	 * @param {string} filename
	 */
	uw.ui.Upload.prototype.showUnparseableFilenameError = function ( filename ) {
		this.showFilenameError( mw.message( 'mwe-upwiz-unparseable-filename', filename ).escaped() );
	};

	/**
	 * Shows an error dialog informing the user that an upload has been omitted
	 * over its filename.
	 *
	 * @param {jQuery} $text The error message
	 */
	uw.ui.Upload.prototype.showFilenameError = function ( $text ) {
		var msgText;

		if ( $text instanceof jQuery ) {
			msgText = $text.text();
		} else {
			msgText = $text;
		}

		uw.eventFlowLogger.logError( 'file', { code: 'filename', message: msgText } );
		mw.errorDialog( $text );
	};

	/**
	 * Checks whether flickr import is enabled and the current user has the rights to use it
	 *
	 * @return {boolean}
	 */
	uw.ui.Upload.prototype.isFlickrImportEnabled = function () {
		return this.config.UploadFromUrl && this.config.flickrApiKey !== '';
	};

	/**
	 * Initiates the Interface to upload media from Flickr.
	 * Called when the user clicks on the 'Add images from Flickr' button.
	 */
	uw.ui.Upload.prototype.flickrInterfaceInit = function () {
		// Hide containers for selecting files, and show the flickr interface instead
		this.$addFileContainer.hide();
		this.$flickrAddFileContainer.hide();
		this.$flickrContainer.show();
		this.flickrSelectButton.$element.show();
		this.flickrButton.setDisabled( false );

		// Insert form into the page
		this.$div.find( '#mwe-upwiz-files' ).prepend( this.$flickrContainer );

		this.$flickrInput.focus();
	};

	/**
	 * Responsible for fetching license of the provided media.
	 *
	 * @param {mw.FlickrChecker} checker
	 */
	uw.ui.Upload.prototype.flickrChecker = function ( checker ) {
		var flickrInputUrl = this.$flickrInput.val();

		checker.getLicenses().done( function () {
			checker.checkFlickr( flickrInputUrl );
		} );
	};

	/**
	 * Reset the interface if there is a problem while fetching the images from
	 * the URL entered by the user.
	 */
	uw.ui.Upload.prototype.flickrInterfaceReset = function () {
		// first destroy it completely, then reshow the add button
		this.flickrInterfaceDestroy();
		this.flickrButton.setDisabled( false );
		this.$flickrContainer.show();
		this.flickrSelectButton.$element.show();
	};

	/**
	 * Removes the flickr interface.
	 */
	uw.ui.Upload.prototype.flickrInterfaceDestroy = function () {
		this.$flickrInput.val( '' );
		this.$flickrSelectList.empty();
		this.$flickrSelectListContainer.off();
		this.$flickrSelectListContainer.hide();
		this.$flickrContainer.hide();
		this.flickrButton.setDisabled( true );
		this.flickrSelectButton.$element.hide();
	};

}( mediaWiki, jQuery, mediaWiki.uploadWizard, OO ) );
