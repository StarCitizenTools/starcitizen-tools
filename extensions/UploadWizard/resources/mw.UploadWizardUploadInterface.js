	( function ( mw, uw, $, OO ) {
	/**
	 * Create an interface fragment corresponding to a file input, suitable for Upload Wizard.
	 *
	 * @class mw.UploadWizardUploadInterface
	 * @mixins OO.EventEmitter
	 * @constructor
	 * @param {mw.UploadWizardUpload} upload
	 */
	mw.UploadWizardUploadInterface = function MWUploadWizardUploadInterface( upload ) {
		var
			ui = this;

		OO.EventEmitter.call( this );

		this.upload = upload;

		// may need to collaborate with the particular upload type sometimes
		// for the interface, as well as the uploadwizard. OY.
		this.$div = $( '<div class="mwe-upwiz-file"></div>' );
		this.div = this.$div.get( 0 );

		this.isFilled = false;

		this.$indicator = $( '<div class="mwe-upwiz-file-indicator"></div>' );

		this.visibleFilenameDiv = $( '<div class="mwe-upwiz-visible-file"></div>' )
			.append( this.$indicator )
			.append(
				'<div class="mwe-upwiz-visible-file-filename">' +
					'<div class="mwe-upwiz-file-preview"/>' +
						'<div class="mwe-upwiz-file-texts">' +
							'<div class="mwe-upwiz-visible-file-filename-text"/>' +
							'<div class="mwe-upwiz-file-status-line">' +
								'<div class="mwe-upwiz-file-status"></div>' +
							'</div>' +
						'</div>' +
					'</div>'
			);

		this.removeCtrl = new OO.ui.ButtonWidget( {
			label: mw.message( 'mwe-upwiz-remove' ).text(),
			title: mw.message( 'mwe-upwiz-remove-upload' ).text(),
			flags: 'destructive',
			icon: 'remove',
			framed: false
		} ).on( 'click', function () {
			ui.upload.remove();
		} );

		if ( mw.UploadWizard.config.defaults && mw.UploadWizard.config.defaults.objref !== '' ) {
			this.$imagePicker = this.createImagePickerField(
				this.upload.index,
				mw.UploadWizard.config.defaults.updateList === ''
			);
			this.visibleFilenameDiv.find( '.mwe-upwiz-file-status-line' )
				.append( this.$imagePicker );
		}

		this.visibleFilenameDiv.find( '.mwe-upwiz-file-status-line' )
			.append( this.removeCtrl.$element );

		this.$form = $( '<form>' )
				.addClass( 'mwe-upwiz-form' )
				.append( this.visibleFilenameDiv );

		$( this.div ).append( this.$form );

		// this.progressBar = ( no progress bar for individual uploads yet )
		// we bind to the ui div since unbind doesn't work for non-DOM objects
		$( this.div ).bind( 'transportProgressEvent', function () { ui.showTransportProgress(); } );
	};

	OO.mixinClass( mw.UploadWizardUploadInterface, OO.EventEmitter );

	/**
	 * Manually fill the file input with a file.
	 *
	 * @param {File} providedFile
	 */
	mw.UploadWizardUploadInterface.prototype.fill = function ( providedFile ) {
		this.providedFile = providedFile;
		this.clearErrors();
	};

	/**
	 * Change the graphic indicator at the far end of the row for this file
	 *
	 * @param {string} statusClass Corresponds to a class mwe-upwiz-status which changes style of indicator.
	 */
	mw.UploadWizardUploadInterface.prototype.showIndicator = function ( statusClass ) {
		this.clearIndicator();
		// add the desired class and make it visible, if it wasn't already.
		this.$indicator.addClass( 'mwe-upwiz-status-' + statusClass ).css( 'visibility', 'visible' );
	};

	/**
	 * Reset the graphic indicator
	 */
	mw.UploadWizardUploadInterface.prototype.clearIndicator = function () {
		var ui = this;
		$.each( this.$indicator.attr( 'class' ).split( /\s+/ ), function ( i, className ) {
			if ( className.match( /^mwe-upwiz-status/ ) ) {
				ui.$indicator.removeClass( className );
			}
		} );
	};

	/**
	 * Set the status line for this upload with an internationalized message string.
	 *
	 * @param {string} msgKey Key for the message
	 * @param {Array} args Array of values, in case any need to be fed to the image.
	 */
	mw.UploadWizardUploadInterface.prototype.setStatus = function ( msgKey, args ) {
		var $s;
		if ( args === undefined ) {
			args = [];
		}
		// get the status line for our upload
		$s = $( this.div ).find( '.mwe-upwiz-file-status' );
		$s.msg( msgKey, args ).show();
	};

	/**
	 * Set status line directly with a string
	 *
	 * @param {string} s
	 */
	mw.UploadWizardUploadInterface.prototype.setStatusString = function ( s ) {
		$( this.div ).find( '.mwe-upwiz-file-status' ).text( s ).show();
	};

	/**
	 * Set additional status information
	 *
	 * @param {jQuery} [$status] If not given or null, additional status is cleared
	 */
	mw.UploadWizardUploadInterface.prototype.setAdditionalStatus = function ( $status ) {
		if ( this.$additionalStatus ) {
			this.$additionalStatus.remove();
		}
		this.$additionalStatus = $status;
		if ( this.$additionalStatus ) {
			$( this.div ).find( '.mwe-upwiz-file-status' ).after( this.$additionalStatus );
		}
	};

	/**
	 * Clear the status line for this upload (hide it, in case there are paddings and such which offset other things.)
	 */
	mw.UploadWizardUploadInterface.prototype.clearStatus = function () {
		$( this.div ).find( '.mwe-upwiz-file-status' ).hide();
		this.setAdditionalStatus( null );
	};

	/**
	 * Put the visual state of an individual upload into "progress"
	 *
	 * @param {number} fraction The fraction of progress. Float between 0 and 1
	 */
	mw.UploadWizardUploadInterface.prototype.showTransportProgress = function () {
		// if fraction available, update individual progress bar / estimates, etc.
		this.showIndicator( 'progress' );
		this.setStatus( 'mwe-upwiz-uploading' );
		this.setAdditionalStatus( null );
	};

	/**
	 * Show that upload is transported
	 */
	mw.UploadWizardUploadInterface.prototype.showStashed = function () {
		this.showIndicator( 'stashed' );
		this.setStatus( 'mwe-upwiz-stashed-upload' );
		this.setAdditionalStatus( null );
	};

	/**
	 * Show that transport has failed
	 *
	 * @param {string} code Error code from API
	 * @param {string|Object} info Extra info
	 * @param {jQuery} [$additionalStatus]
	 */
	mw.UploadWizardUploadInterface.prototype.showError = function ( code, info, $additionalStatus ) {
		var msgKey, args;

		this.showIndicator( 'error' );
		// is this an error that we expect to have a message for?

		if ( code === 'http' && info.textStatus === 'timeout' ) {
			code = 'timeout';
		}

		if ( $.inArray( code, mw.Api.errors ) !== -1 ) {
			msgKey = 'api-error-' + code;
			args = $.makeArray( info );
		} else if ( code === 'unknown-warning' ) {
			msgKey = 'api-error-unknown-warning';
			args = $.makeArray( info );
		} else {
			msgKey = 'api-error-unknown-code';
			args = [ code ].concat( $.makeArray( info ) );
		}
		this.setStatus( msgKey, args );
		this.setAdditionalStatus( $additionalStatus );
	};

	/**
	 * Get just the filename.
	 *
	 * @return {string}
	 */
	mw.UploadWizardUploadInterface.prototype.getFilename = function () {
		if ( this.providedFile.fileName ) {
			return this.providedFile.fileName;
		} else {
			// this property has a different name in FF vs Chrome.
			return this.providedFile.name;
		}
	};

	/**
	 * Run this when the value of the file input has changed and we know it's acceptable -- this
	 * will update interface to show as much info as possible, including preview.
	 * n.b. in older browsers we only will know the filename
	 *
	 * @param {Object} imageinfo
	 * @param {File} file
	 * @param {boolean} fromURL
	 */
	mw.UploadWizardUploadInterface.prototype.fileChangedOk = function ( imageinfo, file, fromURL ) {
		var statusItems = [];

		this.updateFilename();

		// set the status string - e.g. "256 Kb, 100 x 200"
		if ( imageinfo && imageinfo.width && imageinfo.height ) {
			statusItems.push( imageinfo.width + '\u00d7' + imageinfo.height );
		}

		if ( file && !fromURL ) {
			statusItems.push( mw.units.bytes( file.size ) );
		}

		this.clearStatus();
		this.setStatusString( statusItems.join( ' \u00b7 ' ) );
	};

	/**
	 * Display thumbnail preview.
	 *
	 * @return {jQuery.Promise} Promise resolved when the thumbnail is displayed or when displaying it
	 *     fails
	 */
	mw.UploadWizardUploadInterface.prototype.showThumbnail = function () {
		var
			$preview = $( this.div ).find( '.mwe-upwiz-file-preview' ),
			deferred = $.Deferred();
		this.upload.getThumbnail(
			mw.UploadWizard.config.thumbnailWidth,
			mw.UploadWizard.config.thumbnailMaxHeight
		).done( function ( thumb ) {
			mw.UploadWizard.placeThumbnail( $preview, thumb );
			deferred.resolve();
		} );
		return deferred.promise();
	};

	mw.UploadWizardUploadInterface.prototype.fileChangedError = function ( code, info ) {
		var filename = this.getFilename();

		this.providedFile = null;

		if ( code === 'ext' ) {
			this.showBadExtensionError( filename, info );
		} else if ( code === 'noext' ) {
			this.showMissingExtensionError( filename );
		} else if ( code === 'dup' ) {
			this.showDuplicateError( filename, info );
		} else if ( code === 'unparseable' ) {
			this.showUnparseableFilenameError( filename );
		} else {
			this.showUnknownError( code, filename );
		}
	};

	mw.UploadWizardUploadInterface.prototype.showUnparseableFilenameError = function ( filename ) {
		this.showFilenameError( mw.message( 'mwe-upwiz-unparseable-filename', filename ).escaped() );
	};

	mw.UploadWizardUploadInterface.prototype.showBadExtensionError = function ( filename, extension ) {
		var $errorMessage;
		// Check if firefogg should be recommended to be installed ( user selects an extension that can be converted)
		if ( mw.UploadWizard.config.enableFirefogg &&
			$.inArray( extension.toLowerCase(), mw.UploadWizard.config.transcodeExtensionList ) !== -1
		) {
			$errorMessage = $( '<p>' ).msg( 'mwe-upwiz-upload-error-bad-extension-video-firefogg',
					mw.Firefogg.getFirefoggInstallUrl(),
					'https://commons.wikimedia.org/wiki/Help:Converting_video'
				);
		} else {
			$errorMessage = $( '<p>' ).msg( 'mwe-upwiz-upload-error-bad-filename-extension', extension );
		}
		this.showFilenameError( $errorMessage );
	};

	mw.UploadWizardUploadInterface.prototype.showMissingExtensionError = function () {
		this.showExtensionError( $( '<p>' ).msg( 'mwe-upwiz-upload-error-bad-filename-no-extension' ) );
	};

	mw.UploadWizardUploadInterface.prototype.showExtensionError = function ( $errorMessage ) {
		this.showFilenameError(
			$( '<div></div>' ).append(
				$errorMessage,
				$( '<p>' ).msg( 'mwe-upwiz-allowed-filename-extensions' ),
				$( '<blockquote>' ).append( $( '<tt>' ).append(
					mw.UploadWizard.config.fileExtensions.join( ' ' )
				) )
			)
		);
	};

	mw.UploadWizardUploadInterface.prototype.showDuplicateError = function ( filename, basename ) {
		this.showFilenameError( $( '<p>' ).msg( 'mwe-upwiz-upload-error-duplicate-filename-error', basename ) );
	};

	mw.UploadWizardUploadInterface.prototype.showFilenameError = function ( $text ) {
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
	 * this does two things:
	 *   1 ) since the file input has been hidden with some clever CSS ( to avoid x-browser styling issues ),
	 *	  update the visible filename
	 *
	 *   2 ) update the underlying "title" which we are targeting to add to mediawiki.
	 *	  TODO silently fix to have unique filename? unnecessary at this point...
	 */
	mw.UploadWizardUploadInterface.prototype.updateFilename = function () {
		var $div,
			path = this.getFilename();
		// get basename of file; some browsers do this C:\fakepath\something
		path = path.replace( /\w:.*\\(.*)$/, '$1' );

		// visible filename
		this.$form.find( '.mwe-upwiz-visible-file-filename-text' )
			.text( mw.UploadWizard.sanitizeFilename( path ) );

		if ( !this.isFilled ) {
			$div = $( this.div );
			this.isFilled = true;
			$div.addClass( 'filled' );
			this.emit( 'upload-filled' );
		} else {
			this.emit( 'filename-accepted' );
		}
	};

	/**
	 * Remove any complaints we had about errors and such
	 * XXX this should be changed to something Theme compatible
	 */
	mw.UploadWizardUploadInterface.prototype.clearErrors = function () {
		$( this.div ).removeClass( 'mwe-upwiz-upload-error' );
	};

	/**
	* Create a checkbox to process the object reference parameter
	*
	* @param {number} index Number of the file for which the field is being created
	* @param {boolean} setDisabled Disable in case there already is an image in the referring list
	* @return {jQuery} A `div` containing a checkbox, label, and optional notice
	*/
	mw.UploadWizardUploadInterface.prototype.createImagePickerField = function ( index, setDisabled ) {
		var $fieldContainer = $( '<div>' ).attr( {
			'class': 'mwe-upwiz-objref-pick-image'
		} ),
		attributes = {
			type: 'checkbox',
			'class': 'imgPicker',
			id: 'imgPicker' + index,
			disabled: false,
			checked: false
		};

		if ( setDisabled ) {
			attributes.disabled = 'disabled';
		} else if ( index === 0 ) {
			attributes.checked = 'checked';
		}

		$fieldContainer.append(
			$( '<input>' ).attr( attributes ).on( 'click', function () {
				$( this )
					.prop( 'checked', true )
					.closest( '.mwe-upwiz-file' )
					.siblings()
					.find( '.imgPicker' )
					.prop( 'checked', false );
			} ),

			$( '<label>' ).attr( {
				'for': 'imgPicker' + index
			} ).text( this.getPickImageLabel() )
		);

		if ( setDisabled ) {
			$fieldContainer.append(
				$( '<div>' ).attr( {
					'class': 'mwe-upwiz-objref-notice-existing-image'
				} ).text( this.getExistingImageNotice() )
			);
		}

		return $fieldContainer;
	};

	mw.UploadWizardUploadInterface.prototype.getExistingImageNotice = function () {
		if ( mw.UploadWizard.config && mw.UploadWizard.config.display && mw.UploadWizard.config.display.noticeExistingImage ) {
			return mw.UploadWizard.config.display.noticeExistingImage;
		} else {
			return mw.message( 'mwe-upwiz-objref-notice-existing-image' ).text();
		}
	};

	mw.UploadWizardUploadInterface.prototype.getPickImageLabel = function () {
		if ( mw.UploadWizard.config && mw.UploadWizard.config.display && mw.UploadWizard.config.display.labelPickImage ) {
			return mw.UploadWizard.config.display.labelPickImage;
		} else {
			return mw.message( 'mwe-upwiz-objref-pick-image' ).text();
		}
	};

}( mediaWiki, mediaWiki.uploadWizard, jQuery, OO ) );
