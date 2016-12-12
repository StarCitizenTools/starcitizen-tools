/**
* Object that reperesents the entire multi-step Upload Wizard
*/

( function ( mw, uw, $, OO ) {

	mw.UploadWizard = function ( config ) {
		var maxSimPref, wizard = this;

		this.uploads = [];
		this.api = new mw.Api( { ajax: { timeout: 0 } } );

		// making a sort of global for now, should be done by passing in config or fragments of config
		// when needed elsewhere
		mw.UploadWizard.config = config;
		// Shortcut for local references
		this.config = config;

		this.steps = {};

		maxSimPref = mw.user.options.get( 'upwiz_maxsimultaneous' );

		if ( maxSimPref !== 'default' ) {
			if ( maxSimPref > 0 ) {
				config.maxSimultaneousConnections = maxSimPref;
			} else {
				config.maxSimultaneousConnections = 1;
			}
		}

		this.maxSimultaneousConnections = config.maxSimultaneousConnections;

		this.allowCloseWindow = mw.confirmCloseWindow( {
			message: function () {
				return mw.message( 'mwe-upwiz-prevent-close' ).text();
			},

			test: function () {
				return !wizard.isComplete();
			}
		} );

		if ( mw.UploadWizard.config.enableFirefogg && mw.Firefogg.isInstalled() ) {
			// update the "valid" extension to include firefogg transcode extensions:
			mw.UploadWizard.config.fileExtensions = $.merge(
				mw.UploadWizard.config.fileExtensions,
				mw.UploadWizard.config.transcodeExtensionList
			);
		}
	};

	mw.UploadWizard.DEBUG = true;

	mw.UploadWizard.userAgent = 'UploadWizard';

	mw.UploadWizard.prototype = {
		stepNames: [ 'tutorial', 'file', 'deeds', 'details', 'thanks' ],

		/**
		 * Reset the entire interface so we can upload more stuff
		 * (depends on updateFileCounts to reset the interface when uploads go down to 0)
		 * Depending on whether we split uploading / detailing, it may actually always be as simple as loading a URL
		 */
		reset: function () {
			if ( this.hasLoadedBefore ) {
				// this is counterintuitive, but the count needs to start at -1 to allow for the empty upload created on the first step.
				mw.UploadWizardUpload.prototype.count = -1;
			}

			this.showDeed = false;
			this.removeMatchingUploads( function () { return true; } );
			this.hasLoadedBefore = true;
			this.$fileInputCtrl = this.setupFileInputCtrl();

			// hide flickr uploading button if user doesn't have permissions
			if ( !mw.UploadWizard.config.UploadFromUrl || mw.UploadWizard.config.flickrApiKey === '' ) {
				$( '#mwe-upwiz-upload-ctrl-flickr-container, #mwe-upwiz-flickr-select-list-container' ).hide();
			}
		},

		/**
		 * Set up the "Add files" button (#mwe-upwiz-add-file) to open a file selection dialog on click
		 * by means of a hidden `<input type="file">`.
		 *
		 * @return {jQuery} The input field
		 */
		setupFileInputCtrl: function () {
			var
				$fileInputCtrl,
				wizard = this;

			$fileInputCtrl = $( '<input type="file" multiple name="file" class="mwe-upwiz-file-input" />' );

			// #mwe-upwiz-add-file is a ButtonWidget constructed somewhere else, so this is hacky.
			// But it's less bad than how this was done before.
			$( '#mwe-upwiz-add-file .oo-ui-buttonElement-button' ).append( $fileInputCtrl );

			$fileInputCtrl.on( 'change', function () {
				var
					totalSize, uploadObj, thumbPromise,
					uploadObjs = [],
					uploadInterfaceDivs = [],
					files = $fileInputCtrl[ 0 ].files,
					totalFiles = ( files ? files.length : 1 ) + wizard.uploads.length,
					tooManyFiles = totalFiles > wizard.config.maxUploads;

				if ( tooManyFiles ) {
					wizard.steps.file.showTooManyFilesWarning( totalFiles );
					return;
				}

				totalSize = 0;
				$.each( files, function ( i, file ) {
					totalSize += file.size;
				} );

				$.each( files, function ( i, file ) {
					uploadObj = wizard.addUpload( file, totalSize > 10000000 );
					uploadObjs.push( uploadObj );
					// We'll attach all interfaces to the DOM at once rather than one-by-one, for better
					// performance
					uploadInterfaceDivs.push( uploadObj.ui.div );
				} );

				// Attach all interfaces to the DOM
				$( '#mwe-upwiz-filelist' ).append( $( uploadInterfaceDivs ) );

				// Display thumbnails, but not all at once because they're somewhat expensive to generate.
				// This will wait for each thumbnail to be complete before starting the next one.
				thumbPromise = $.Deferred().resolve();
				$.each( uploadObjs, function ( i, uploadObj ) {
					thumbPromise = thumbPromise.then( function () {
						var deferred = $.Deferred();
						setTimeout( function () {
							if ( wizard.steps.file.movedFrom ) {
								// We're no longer displaying any of these thumbnails, stop
								deferred.reject();
							}
							uploadObj.ui.showThumbnail().done( function () {
								deferred.resolve();
							} );
						} );
						return deferred.promise();
					} );
				} );

				// We can't clear the value of a file input, so replace the whole thing with a new one.
				wizard.$fileInputCtrl = wizard.setupFileInputCtrl();

				uw.eventFlowLogger.logUploadEvent( 'uploads-added', { quantity: files.length } );
			} );

			return $fileInputCtrl;
		},

		/**
		 * Resets wizard state and moves to the file step.
		 */
		bailAndMoveToFile: function () {
			// destroy the flickr interface if it exists
			this.flickrInterfaceDestroy();

			// fix various other pages that may have state
			$.each( this.steps, function ( i, step ) {
				step.empty();
			} );
		},

		/**
		 * Create the basic interface to make an upload in this div
		 */
		createInterface: function ( selector ) {
			this.ui = new uw.ui.Wizard( selector );

			this.initialiseSteps();

			// "select" the first step - highlight, make it visible, hide all others
			this.steps.tutorial.moveTo();
		},

		/**
		 * Initialise the steps in the wizard
		 */
		initialiseSteps: function () {
			var wizard = this;

			this.steps = {
				tutorial: new uw.controller.Tutorial( this.api, this.config ),

				file: new uw.controller.Upload( this.config )
					.on( 'flickr-ui-init', function () {
						wizard.flickrInterfaceInit();
						uw.eventFlowLogger.logEvent( 'flickr-upload-button-clicked' );
					} )

					.on( 'load', function () {
						wizard.reset();

						// Check for iOS 5 Safari's lack of file uploads (T34328#364508).
						// While this looks extremely unlikely to be right, it actually is. Blame Apple.
						if ( $( '<input type="file">' ).prop( 'disabled' ) ) {
							$( '#mwe-upwiz-stepdiv-file' ).replaceWith(
								$( '<span>' ).msg( 'mwe-upwiz-file-upload-notcapable' )
							);
							$( '#mwe-upwiz-add-file' ).hide();
						}
					} ),

				deeds: new uw.controller.Deed( this.api, this.config )
					.on( 'load', function () {
						wizard.removeErrorUploads();
					} ),

				details: new uw.controller.Details( this.config )
					.on( 'details-error', function () {
						wizard.steps.details.showErrors();
					} )

					.on( 'finalize-details-after-removal', function () {
						wizard.removeErrorUploads();
						wizard.steps.details.moveFrom();
					} ),

				thanks: new uw.controller.Thanks( this.config )
					.on( 'reset-wizard', function () {
						wizard.reset();
					} )
			};

			$.each( this.steps, function ( name, step ) {
				step
					.on( 'no-uploads', function () {
						wizard.bailAndMoveToFile();
					} );
			} );

			this.steps.tutorial.setNextStep( this.steps.file );
			this.steps.file.setNextStep( this.steps.deeds );
			this.steps.deeds.setNextStep( this.steps.details );
			this.steps.details.setNextStep( this.steps.thanks );
			this.steps.thanks.setNextStep( this.steps.file );

			$( '#mwe-upwiz-steps' ).arrowSteps();
		},

		/**
		 * Initiates the Interface to upload media from Flickr.
		 * Called when the user clicks on the 'Add images from Flickr' button.
		 */
		flickrInterfaceInit: function () {
			var $disclaimer,
				wizard = this,
				checker = new mw.FlickrChecker( this, this.upload ),
				// The input that will hold a flickr URL entered by the user; will be appended to a form
				$flickrInput = $( '<input id="mwe-upwiz-flickr-input" class="ui-helper-center-fix" type="text" />' ),
				// A container holding a form
				$flickrContainer = $( '<div id="mwe-upwiz-upload-add-flickr-container"></div>' ),
				// Form whose submit event will be listened to and prevented
				$flickrForm = $( '<form id="mwe-upwiz-flickr-url-form"></form>' )
					.appendTo( $flickrContainer ),
				flickrButton = new OO.ui.ButtonInputWidget( {
					id: 'mwe-upwiz-upload-ctrl-flickr',
					label: mw.message( 'mwe-upwiz-add-flickr' ).text(),
					flags: [ 'progressive', 'primary' ],
					type: 'submit'
				} );

			$flickrForm.append( flickrButton.$element );

			// Hide containers for selecting files
			$( '#mwe-upwiz-add-file-container, #mwe-upwiz-upload-ctrl-flickr-container' ).hide();

			// Add placeholder text to the Flickr URL input field
			$flickrInput.placeholder( mw.message( 'mwe-upwiz-flickr-input-placeholder' ).text() );

			// Insert form into the page
			$( '#mwe-upwiz-files' ).prepend( $flickrContainer );

			// Add disclaimer
			$disclaimer = mw.message( 'mwe-upwiz-flickr-disclaimer1' ).parse() +
				'<br/>' + mw.message( 'mwe-upwiz-flickr-disclaimer2' ).parse();
			$disclaimer = $( '<div id="mwe-upwiz-flickr-disclaimer"></div>' ).html( $disclaimer );
			$( '#mwe-upwiz-upload-add-flickr-container' ).append( $disclaimer );

			// Save temporarily
			this.flickrButton = flickrButton;

			// Insert input field into the form and set up submit action
			$flickrForm.prepend( $flickrInput ).submit( function () {
				flickrButton.setDisabled( true );
				wizard.flickrChecker( checker );
				// TODO Any particular reason to stopPropagation ?
				return false;
			} );

			$flickrInput.focus();
		},

		/**
		 * Responsible for fetching license of the provided media.
		 */
		flickrChecker: function ( checker ) {
			var flickrInputUrl = $( '#mwe-upwiz-flickr-input' ).val();
			checker.getLicenses().done( function () {
				checker.checkFlickr( flickrInputUrl );
			} );
		},

		/**
		 * Reset the interface if there is a problem while fetching the images from the URL entered by the user.
		 */
		flickrInterfaceReset: function () {
			// first destroy it completely, then reshow the add button
			this.flickrInterfaceDestroy();
			this.flickrButton.setDisabled( false );
			$( '#mwe-upwiz-upload-add-flickr-container' ).show();
			$( '#mwe-upwiz-upload-add-flickr' ).prop( 'disabled', false );
		},

		/**
		 * Removes the flickr interface.
		 */
		flickrInterfaceDestroy: function () {
			$( '#mwe-upwiz-flickr-input' ).val( '' );
			$( '#mwe-upwiz-flickr-select-list' ).empty();
			$( '#mwe-upwiz-flickr-select-list-container' ).unbind();
			$( '#mwe-upwiz-select-flickr' ).remove();
			$( '#mwe-upwiz-flickr-select-list-container' ).hide();
			$( '#mwe-upwiz-upload-add-flickr-container' ).hide();
			$( '#mwe-upwiz-upload-add-flickr' ).prop( 'disabled', true );
		},

		/**
		 * Create the upload interface, a handler to transport it to the server, and UI for the upload
		 * itself; and immediately fill it with a file and add it to the list of uploads.
		 *
		 * @param {File} file
		 * @return {UploadWizardUpload|false} The new upload, or false if it can't be added
		 */
		addUpload: function ( file ) {
			var upload,
				wizard = this;

			if ( this.uploads.length >= this.config.maxUploads ) {
				return false;
			}

			upload = new mw.UploadWizardUpload( this )
				.on( 'filled', function () {
					wizard.setUploadFilled( upload );
				} )

				.on( 'filename-accepted', function () {
					wizard.steps.file.updateFileCounts( wizard.uploads );
				} );

			upload.connect( this, {
				'remove-upload': [ 'removeUpload', upload ]
			} );

			upload.fill( file );
			upload.checkFile( upload.ui.getFilename(), file );

			return upload;
		},

		/**
		 * When an upload is filled with a real file, accept it in the wizard's list of uploads
		 * and set up some other interfaces
		 *
		 * @param {UploadWizardUpload} upload
		 */
		setUploadFilled: function ( upload ) {
			this.uploads.push( upload );
			this.steps.file.updateFileCounts( this.uploads );
			// Start uploads now, no reason to wait--leave the remove button alone
			this.steps.file.queueUpload( upload );
			this.steps.file.startQueuedUploads();
		},

		/**
		 * Remove an upload from our array of uploads, and the HTML UI
		 * We can remove the HTML UI directly, as jquery will just get the parent.
		 * We need to grep through the array of uploads, since we don't know the current index.
		 * We need to update file counts for obvious reasons.
		 *
		 * @param {UploadWizardUpload} upload
		 */
		removeUpload: function ( upload ) {
			// remove the div that passed along the trigger
			var $div = $( upload.ui.div ),
				index;

			$div.unbind(); // everything
			$div.remove();
			// and do what we in the wizard need to do after an upload is removed
			// Remove the upload from the uploads array (modify in-place, as this is shared among various
			// things that rely on having current information).
			index = this.uploads.indexOf( upload );
			if ( index !== -1 ) {
				this.uploads.splice( index, 1 );
			}

			// TODO We should only be doing this for whichever step is currently active
			this.steps.file.queue.removeItem( upload );
			this.steps.details.queue.removeItem( upload );

			this.steps.file.updateFileCounts( this.uploads );

			if ( this.uploads && this.uploads.length !== 0 ) {
				// check all uploads, if they're complete, show the next button
				this.steps.file.showNext();
			}
		},

		/**
		 * Clear out uploads that are in error mode, perhaps before proceeding to the next step
		 */
		removeErrorUploads: function () {
			this.removeMatchingUploads( function ( upload ) {
				return upload.state === 'error';
			} );
		},

		/**
		 * This is useful to clean out file inputs that we don't want for some reason (error, empty...)
		 * We are using a second array to iterate, because we will be splicing the main one, _this.uploads
		 *
		 * @param {Function} criterion Function to test the upload, returns boolean; true if should be removed
		 */
		removeMatchingUploads: function ( criterion ) {
			var toRemove = [];

			$.each( this.uploads, function ( i, upload ) {
				if ( upload === undefined ) {
					return;
				}
				if ( criterion( upload ) ) {
					toRemove.push( upload );
				}
			} );

			$.each( toRemove, function ( i, upload ) {
				if ( upload === undefined ) {
					return;
				}
				upload.remove();
			} );
		},

		/**
		 * Helper function to check whether the upload process is totally
		 * complete and we can safely leave the window.
		 */
		isComplete: function () {
			var complete = true;

			$.each( this.steps, function ( i, step ) {
				if ( !step.isComplete() ) {
					complete = false;
					return false;
				}
			} );

			return complete;
		}
	};

	/**
	 * Sanitizes a filename for use as a File: page title
	 *
	 * @static
	 * @param {string} filename Pre-sanitization filename.
	 * @return {string} Filename sanitized for use as a title.
	 */
	mw.UploadWizard.sanitizeFilename = function ( filename ) {
		var illegalCharRegex = new RegExp( '[' + mw.config.get( 'wgIllegalFileChars', '' ) + '#:%]', 'g' );
		return filename.replace( illegalCharRegex, '-' );
	};

	/**
	 * Get the own work and third party licensing deeds if they are needed.
	 *
	 * @static
	 * @since 1.2
	 * @param {number} uploadsLength
	 * @param {Object} config The UW config object.
	 * @return {mw.UploadWizardDeed[]}
	 */
	mw.UploadWizard.getLicensingDeeds = function ( uploadsLength, config ) {
		var deeds = [],
			doOwnWork = false,
			doThirdParty = false;

		this.api = this.api || new mw.Api( { ajax: { timeout: 0 } } );

		if ( config.licensing.ownWorkDefault === 'choice' ) {
			doOwnWork = doThirdParty = true;
		} else if ( config.licensing.ownWorkDefault === 'own' ) {
			doOwnWork = true;
		} else {
			doThirdParty = true;
		}

		if ( doOwnWork ) {
			deeds.push( new mw.UploadWizardDeedOwnWork( uploadsLength, this.api, config ) );
		}
		if ( doThirdParty ) {
			deeds.push( new mw.UploadWizardDeedThirdParty( uploadsLength, this.api, config ) );
		}

		return deeds;
	};

	/**
	 * Helper method to put a thumbnail somewhere.
	 *
	 * @param {string|jQuery} selector String representing a jQuery selector, or a jQuery object
	 * @param {HTMLCanvasElement|HTMLImageElement|null} image
	 */
	mw.UploadWizard.placeThumbnail = function ( selector, image ) {
		if ( image === null ) {
			$( selector ).addClass( 'mwe-upwiz-file-preview-broken' );
			return;
		}

		$( selector )
			.css( { background: 'none' } )
			.html(
				$( '<a>' )
					.addClass( 'mwe-upwiz-thumbnail-link' )
					.append( image )
			);
	};

} )( mediaWiki, mediaWiki.uploadWizard, jQuery, OO );
