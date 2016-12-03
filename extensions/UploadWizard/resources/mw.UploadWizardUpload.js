/**
 * Represents the upload -- in its local and remote state. (Possibly those could be separate objects too...)
 * This is our 'model' object if we are thinking MVC. Needs to be better factored, lots of feature envy with the UploadWizard
 * states:
 *   'new' 'transporting' 'transported' 'metadata' 'stashed' 'details' 'submitting-details' 'complete' 'error'
 * should fork this into two -- local and remote, e.g. filename
 */
( function ( mw, uw, $, OO ) {

	var NS_FILE = mw.config.get( 'wgNamespaceIds' ).file;

	/**
	 * Constructor for objects representing uploads. The workhorse of this entire extension.
	 *
	 * The upload knows nothing of other uploads. It manages its own interface, and transporting its own data, to
	 * the server.
	 *
	 * Upload objects are usually created without a file, they are just associated with a form.
	 * There is an "empty" fileInput which is invisibly floating above certain buttons in the interface, like "Add a file". When
	 * this fileInput gets a file, this upload becomes 'filled'.
	 *
	 * @class mw.UploadWizardUpload
	 * @mixins OO.EventEmitter
	 * @constructor
	 * @param {UploadWizard} wizard
	 */
	mw.UploadWizardUpload = function MWUploadWizardUpload( wizard ) {
		var upload = this;

		OO.EventEmitter.call( this );

		this.index = mw.UploadWizardUpload.prototype.count;
		mw.UploadWizardUpload.prototype.count++;

		this.wizard = wizard;
		this.api = wizard.api;
		this.state = 'new';
		this.thumbnailPublishers = {};
		this.imageinfo = {};
		this.title = undefined;
		this.mimetype = undefined;
		this.extension = undefined;
		this.filename = undefined;
		this.file = undefined;
		this.ignoreWarning = {};
		this.fromURL = false;
		this.previewPromise = null;

		this.fileKey = undefined;

		// this should be moved to the interface, if we even keep this
		this.transportWeight = 1; // default all same
		this.detailsWeight = 1; // default all same

		// details
		this.ui = new mw.UploadWizardUploadInterface( this )
			.connect( this, {
				'file-changed': [ 'emit', 'file-changed', upload ],
				'filename-accepted': [ 'emit', 'filename-accepted' ]
			} )

			.on( 'upload-filled', function () {
				upload.emit( 'filled' );
			} );
	};

	OO.mixinClass( mw.UploadWizardUpload, OO.EventEmitter );

	// Upload handler
	mw.UploadWizardUpload.prototype.uploadHandler = null;

	// increments with each upload
	mw.UploadWizardUpload.prototype.count = 0;

	/**
	 * Manually fill the file input with a file.
	 *
	 * @param {File} providedFile
	 */
	mw.UploadWizardUpload.prototype.fill = function ( providedFile ) {
		this.providedFile = providedFile;

		// check to see if the File is being uploaded from a 3rd party URL.
		if ( providedFile.fromURL ) {
			this.fromURL = true;
		}

		this.ui.fill( providedFile );
	};

	mw.UploadWizardUpload.prototype.acceptDeed = function () {
		this.deed.applyDeed( this );
	};

	/**
	 * start
	 *
	 * @return {jQuery.Promise}
	 */
	mw.UploadWizardUpload.prototype.start = function () {
		this.setTransportProgress( 0.0 );

		// handler -- usually ApiUploadFormDataHandler
		this.handler = this.getUploadHandler();
		return this.handler.start();
	};

	/**
	 *  remove this upload. n.b. we trigger a removeUpload this is usually triggered from
	 */
	mw.UploadWizardUpload.prototype.remove = function () {
		this.state = 'aborted';
		if ( this.deedPreview ) {
			this.deedPreview.remove();
		}
		if ( this.details && this.details.div ) {
			this.details.div.remove();
		}
		// we signal to the wizard to update itself, which has to delete the
		// final vestige of this upload
		this.emit( 'remove-upload' );
	};

	/**
	 * Wear our current progress, for observing processes to see
	 *
	 * @param {number} fraction
	 */
	mw.UploadWizardUpload.prototype.setTransportProgress = function ( fraction ) {
		if ( this.state === 'aborted' ) {
			// We shouldn't be transporting anything anymore.
			return;
		}
		this.state = 'transporting';
		this.transportProgress = fraction;
		$( this.ui.div ).trigger( 'transportProgressEvent' );
	};

	/**
	 * Stop the upload -- we have failed for some reason
	 */
	mw.UploadWizardUpload.prototype.setError = function ( code, info, additionalStatus ) {
		if ( this.state === 'aborted' ) {
			// There's no point in reporting an error anymore.
			return;
		}
		this.state = 'error';
		this.transportProgress = 0;
		this.ui.showError( code, info, additionalStatus );
		uw.eventFlowLogger.logError( 'file', { code: code, message: info } );
	};

	/**
	 * Resume the upload, assume that whatever error(s) we got were benign.
	 */
	mw.UploadWizardUpload.prototype.removeErrors = function ( code ) {
		this.ignoreWarning[ code ] = true;
		this.start();
	};

	/**
	 * To be executed when an individual upload finishes. Processes the result and updates step 2's details
	 *
	 * @param {Object} result The API result in parsed JSON form
	 */
	mw.UploadWizardUpload.prototype.setTransported = function ( result ) {
		// default error state
		var comma, warnCode,
			code = 'unknown',
			info = 'unknown';

		if ( this.state === 'aborted' ) {
			return;
		}

		if ( result.error ) {
			// If there was an error, we can't really do anything else, so let's get out while we can.
			if ( result.error.code ) {
				code = result.error.code;
			}
			if ( code === 'badtoken' ) {
				this.api.badToken( 'csrf' );
				// Try again once
				if ( !this.ignoreWarning[ code ] ) {
					this.removeErrors( code );
					return;
				}
			}
			if ( code === 'filetype-banned' && result.error.blacklisted ) {
				code = 'filetype-banned-type';
				comma = mw.message( 'comma-separator' ).text();
				info = [
					result.error.blacklisted.join( comma ),
					result.error.allowed.join( comma ),
					result.error.allowed.length,
					result.error.blacklisted.length
				];
			} else if ( result.error.info ) {
				info = result.error.info;
			}
			this.setError( code, info );
			return;
		}

		result.upload = result.upload || {};
		result.upload.warnings = result.upload.warnings || {};

		for ( warnCode in result.upload.warnings ) {
			if ( !this.ignoreWarning[ warnCode ] && this.state !== 'error' ) {
				switch ( warnCode ) {
					case 'page-exists':
					case 'exists':
					case 'exists-normalized':
					case 'was-deleted':
					case 'badfilename':
						// we ignore these warnings, because the title is not our final title.
						break;
					case 'duplicate':
					case 'duplicate-archive':
						code = warnCode;
						this.setDuplicateError( warnCode, result.upload.warnings[ warnCode ] );
						break;
					default:
						// we have an unknown warning, so let's say what we know
						code = 'unknown-warning';
						if ( typeof result.upload.warnings[ warnCode ] === 'string' ) {
							// tack the original error code onto the warning info
							info = warnCode + mw.message( 'colon-separator' ).text() + result.upload.warnings[ warnCode ];
						} else {
							info = result.upload.warnings[ warnCode ];
						}
						this.setError( code, info );
						break;
				}
			}
		}

		if ( this.state !== 'error' ) {
			if ( result.upload && result.upload.result === 'Success' ) {
				if ( result.upload.imageinfo ) {
					this.setSuccess( result );
				} else {
					this.setError( 'noimageinfo', info );
				}
			} else if ( result.upload && result.upload.result === 'Warning' ) {
				throw new Error( 'Your browser got back a Warning result from the server. Please file a bug.' );
			} else {
				this.setError( code, info );
			}
		}
	};

	/**
	 * Helper function to generate duplicate errors in a possibly collapsible list.
	 * Works with existing duplicates and deleted dupes.
	 *
	 * @param {string} code Error code, should have matching strings in .i18n.php
	 * @param {Object} resultDuplicate Portion of the API error result listing duplicates
	 */
	mw.UploadWizardUpload.prototype.setDuplicateError = function ( code, resultDuplicate ) {
		var duplicates, $ul, $extra, uploadDuplicate;

		if ( typeof resultDuplicate === 'object' ) {
			duplicates = resultDuplicate;
		} else if ( typeof resultDuplicate === 'string' ) {
			duplicates = [ resultDuplicate ];
		}

		$ul = $( '<ul>' );
		$.each( duplicates, function ( i, filename ) {
			var href, $a, params = {};

			try {
				$a = $( '<a>' ).text( filename );
				if ( code === 'duplicate-archive' ) {
					$a.addClass( 'new' );
					params = { action: 'edit', redlink: '1' };
				}
				href = mw.Title.makeTitle( NS_FILE, filename ).getUrl( params );
				$a.attr( { href: href, target: '_blank' } );
			} catch ( e ) {
				// For example, if the file was revdeleted
				$a = $( '<em>' )
					.text( mw.msg( 'mwe-upwiz-deleted-duplicate-unknown-filename' ) );
			}
			$ul.append( $( '<li>' ).append( $a ) );
		} );

		if ( duplicates.length > 1 ) {
			$ul.makeCollapsible( { collapsed: true } );
		}

		$extra = $ul;
		if ( code === 'duplicate-archive' ) {
			uploadDuplicate = new OO.ui.ButtonWidget( {
				label: mw.message( 'mwe-upwiz-override' ).text(),
				title: mw.message( 'mwe-upwiz-override-upload' ).text(),
				flags: 'progressive',
				framed: false
			} ).on( 'click', function () {
				this.removeErrors( 'duplicate-archive' );
			}.bind( this ) );
			$extra = $extra.add( uploadDuplicate.$element );
		}

		this.setError( code, [ duplicates.length ], $extra );
	};

	/**
	 * Called from any upload success condition
	 *
	 * @param {Mixed} result -- result of AJAX call
	 */
	mw.UploadWizardUpload.prototype.setSuccess = function ( result ) {
		this.state = 'transported';
		this.transportProgress = 1;

		this.ui.setStatus( 'mwe-upwiz-getting-metadata' );

		if ( result.upload ) {
			this.extractUploadInfo( result.upload );
			this.state = 'stashed';
			this.ui.showStashed();

			this.emit( 'success' );
			// check all uploads, if they're complete, show the next button
			// TODO Make wizard connect to 'success' event
			this.wizard.steps.file.showNext();
		} else {
			this.setError( 'noimageinfo' );
		}
	};

	/**
	 * Get the basename of a path.
	 * For error conditions, returns the empty string.
	 *
	 * @param {string} path
	 * @return {string} basename
	 */
	mw.UploadWizardUpload.prototype.getBasename = function ( path ) {
		if ( path === undefined || path === null ) {
			return '';
		}

		// find index of last path separator in the path, add 1. (If no separator found, yields 0)
		// then take the entire string after that.
		return path.substr( Math.max( path.lastIndexOf( '/' ), path.lastIndexOf( '\\' ) ) + 1 );
	};

	/**
	 * Shows a filename error in the UI.
	 *
	 * @param {string} code Short code for i18n strings
	 * @param {string} info More information
	 */
	mw.UploadWizardUpload.prototype.fileNameErr = function ( code, info ) {
		this.hasError = true;
		this.ui.fileChangedError( code, info );
	};

	/**
	 * Called when the file is entered into the file input, bound to its change() event.
	 * Checks for file validity, then extracts metadata.
	 * Error out if filename or its contents are determined to be unacceptable
	 * Proceed to thumbnail extraction and image info if acceptable
	 *
	 * We changed the behavior here to be a little more sane. Now any errors
	 * will cause the fileChangedOk not to be called, and if you have a special
	 * case where an error should be ignored, you can simply find that error
	 * and delete it from the third parameter of the error callback. The end.
	 *
	 * @param {string} filename The filename
	 * @param {Object} file File, if available
	 */
	mw.UploadWizardUpload.prototype.checkFile = function ( filename, file ) {
		var duplicate, extension,
			actualMaxSize,
			upload = this,

			// Check if filename is acceptable
			// TODO sanitize filename
			basename = this.getBasename( filename );

		function finishCallback() {
			if ( upload && upload.ui ) {
				upload.fileChangedOk();
			} else {
				setTimeout( finishCallback, 200 );
			}
		}

		// Eternal optimism
		this.hasError = false;

		// check to see if the file has already been selected for upload.
		duplicate = false;
		$.each( this.wizard.uploads, function ( i, thisupload ) {
			if ( thisupload !== undefined && upload !== thisupload && filename === thisupload.filename ) {
				duplicate = true;
				return false;
			}
		} );

		if ( duplicate ) {
			this.fileNameErr( 'dup', basename );
			return;
		}

		this.setTitle( basename );

		if ( !this.title ) {
			if ( basename.indexOf( '.' ) === -1 ) {
				this.fileNameErr( 'noext', null );
			} else {
				this.fileNameErr( 'unparseable', null );
			}
			return;
		}

		// Check if extension is acceptable
		extension = this.title.getExtension();
		if ( !extension ) {
			this.fileNameErr( 'noext', null );
		} else {
			if (
				mw.UploadWizard.config.fileExtensions !== null &&
				$.inArray( extension.toLowerCase(), mw.UploadWizard.config.fileExtensions ) === -1
			) {
				this.fileNameErr( 'ext', extension );
			} else {
				// Split this into a separate case, if the error above got ignored,
				// we want to still trudge forward.

				// Extract more info via File API
				this.file = file;

				actualMaxSize = mw.UploadWizard.config.maxMwUploadSize;

				// make sure the file isn't too large
				// XXX need a way to find the size of the Flickr image
				if ( !this.fromURL ) {
					this.transportWeight = this.file.size;
					if ( this.transportWeight > actualMaxSize ) {
						this.showMaxSizeWarning( this.transportWeight, actualMaxSize );
						return;
					}
				}
				if ( this.imageinfo === undefined ) {
					this.imageinfo = {};
				}
				this.filename = filename;
				if ( this.hasError === false ) {
					finishCallback();
				}
			}
		}
	};

	/**
	 * Sanitize and set the title of the upload.
	 *
	 * @param {string} title Unsanitized title.
	 */
	mw.UploadWizardUpload.prototype.setTitle = function ( title ) {
		this.title = mw.Title.newFromFileName( mw.UploadWizard.sanitizeFilename( title ) );
	};

	/**
	 * Shows an error dialog informing the user that the selected file is to large
	 *
	 * @param {number} size Size of the file in bytes
	 * @param {number} maxSize Maximum file size
	 */
	mw.UploadWizardUpload.prototype.showMaxSizeWarning = function ( size, maxSize ) {
		mw.errorDialog(
			mw.message(
				'mwe-upwiz-file-too-large-text',
				mw.units.bytes( maxSize ),
				mw.units.bytes( size )
			).text(),
			mw.message( 'mwe-upwiz-file-too-large' ).text()
		);
	};

	/**
	 * Extract some JPEG metadata that we need to render thumbnails (EXIF rotation mostly).
	 *
	 * For JPEGs, we use the JsJpegMeta library in core to extract metadata,
	 * including EXIF tags. This is done asynchronously once each file has been
	 * read.
	 *
	 * For all other file types, we don't need or want to run this, and this function does nothing.
	 *
	 * @return {jQuery.Promise} A promise, resolved when we're done
	 */
	mw.UploadWizardUpload.prototype.extractMetadataFromJpegMeta = function () {
		var binReader,
			deferred = $.Deferred(),
			upload = this;
		if ( this.file && this.file.type === 'image/jpeg' ) {
			binReader = new FileReader();
			binReader.onload = function () {
				var binStr, arr, i, meta;
				if ( typeof binReader.result === 'string' ) {
					binStr = binReader.result;
				} else {
					// Array buffer; convert to binary string for the library.
					arr = new Uint8Array( binReader.result );
					binStr = '';
					for ( i = 0; i < arr.byteLength; i++ ) {
						binStr += String.fromCharCode( arr[ i ] );
					}
				}
				try {
					meta = mw.libs.jpegmeta( binStr, upload.file.fileName );
					// jscs:disable requireCamelCaseOrUpperCaseIdentifiers, disallowDanglingUnderscores
					meta._binary_data = null;
					// jscs:enable
				} catch ( e ) {
					meta = null;
				}
				upload.extractMetadataFromJpegMetaCallback( meta );
				deferred.resolve();
			};
			if ( 'readAsBinaryString' in binReader ) {
				binReader.readAsBinaryString( upload.file );
			} else if ( 'readAsArrayBuffer' in binReader ) {
				binReader.readAsArrayBuffer( upload.file );
			}
		} else {
			deferred.resolve();
		}
		return deferred.promise();
	};

	/**
	 * Map fields from jpegmeta's metadata return into our format (which is more like the imageinfo returned from the API
	 *
	 * @param {Object} meta As returned by jpegmeta
	 */
	mw.UploadWizardUpload.prototype.extractMetadataFromJpegMetaCallback = function ( meta ) {
		var pixelHeightDim, pixelWidthDim, degrees;

		if ( meta !== undefined && meta !== null && typeof meta === 'object' ) {
			if ( this.imageinfo === undefined ) {
				this.imageinfo = {};
			}
			if ( this.imageinfo.metadata === undefined ) {
				this.imageinfo.metadata = {};
			}
			if ( meta.tiff && meta.tiff.Orientation ) {
				this.imageinfo.metadata.orientation = meta.tiff.Orientation.value;
			}
			if ( meta.general ) {
				pixelHeightDim = 'height';
				pixelWidthDim = 'width';
				// this must be called after orientation is set above. If no orientation set, defaults to 0
				degrees = this.getOrientationDegrees();

				// jpegmeta reports pixelHeight & width
				if ( degrees === 90 || degrees === 270 ) {
					pixelHeightDim = 'width';
					pixelWidthDim = 'height';
				}
				if ( meta.general.pixelHeight ) {
					this.imageinfo[ pixelHeightDim ] = meta.general.pixelHeight.value;
				}
				if ( meta.general.pixelWidth ) {
					this.imageinfo[ pixelWidthDim ] = meta.general.pixelWidth.value;
				}
			}
		}
	};

	/**
	 * Accept the result from a successful API upload transport, and fill our own info
	 *
	 * @param {Object} resultUpload The JSON object from a successful API upload result.
	 */
	mw.UploadWizardUpload.prototype.extractUploadInfo = function ( resultUpload ) {
		if ( resultUpload.filekey ) {
			this.fileKey = resultUpload.filekey;
		}

		if ( resultUpload.imageinfo ) {
			this.extractImageInfo( resultUpload.imageinfo );
		} else if ( resultUpload.stashimageinfo ) {
			this.extractImageInfo( resultUpload.stashimageinfo );
		}

	};

	/**
	 * Extract image info into our upload object
	 * Image info is obtained from various different API methods
	 * This may overwrite metadata obtained from FileReader.
	 *
	 * @param {Object} imageinfo JSON object obtained from API result.
	 */
	mw.UploadWizardUpload.prototype.extractImageInfo = function ( imageinfo ) {
		var key,
			upload = this;

		function setMetadata( i, pair ) {
			if ( pair !== undefined ) {
				upload.imageinfo.metadata[ pair.name.toLowerCase() ] = pair.value;
			}
		}

		for ( key in imageinfo ) {
			// we get metadata as list of key-val pairs; convert to object for easier lookup. Assuming that EXIF fields are unique.
			if ( key === 'metadata' ) {
				if ( this.imageinfo.metadata === undefined ) {
					this.imageinfo.metadata = {};
				}
				if ( imageinfo.metadata && imageinfo.metadata.length ) {
					$.each( imageinfo.metadata, setMetadata );
				}
			} else {
				this.imageinfo[ key ] = imageinfo[ key ];
			}
		}
	};

	/**
	 * Get information about stashed images
	 *
	 * See API documentation for prop=stashimageinfo for what 'props' can contain
	 *
	 * @param {Function} callback Called with null if failure, with imageinfo data structure if success
	 * @param {Array} props Properties to extract
	 * @param {number} [width] Width of thumbnail. Will force 'url' to be added to props
	 * @param {number} [height] Height of thumbnail. Will force 'url' to be added to props
	 */
	mw.UploadWizardUpload.prototype.getStashImageInfo = function ( callback, props, width, height ) {
		var params = {
			prop: 'stashimageinfo',
			siifilekey: this.fileKey,
			siiprop: props.join( '|' )
		};

		function ok( data ) {
			if ( !data || !data.query || !data.query.stashimageinfo ) {
				mw.log.warn( 'mw.UploadWizardUpload::getStashImageInfo> No data?' );
				callback( null );
				return;
			}
			callback( data.query.stashimageinfo );
		}

		function err( code ) {
			mw.log.warn( 'mw.UploadWizardUpload::getStashImageInfo> ' + code );
			callback( null );
		}

		if ( props === undefined ) {
			props = [];
		}

		if ( width !== undefined || height !== undefined ) {
			if ( !$.inArray( 'url', props ) ) {
				props.push( 'url' );
			}
			if ( width !== undefined ) {
				params.siiurlwidth = width;
			}
			if ( height !== undefined ) {
				params.siiurlheight = height;
			}
		}

		this.api.get( params ).done( ok ).fail( err );
	};

	/**
	 * Get information about published images
	 * (There is some overlap with getStashedImageInfo, but it's different at every stage so it's clearer to have separate functions)
	 * See API documentation for prop=imageinfo for what 'props' can contain
	 *
	 * @param {Function} callback Called with null if failure, with imageinfo data structure if success
	 * @param {Array} props Properties to extract
	 * @param {number} [width] Width of thumbnail. Will force 'url' to be added to props
	 * @param {number} [height] Height of thumbnail. Will force 'url' to be added to props
	 */
	mw.UploadWizardUpload.prototype.getImageInfo = function ( callback, props, width, height ) {
		var requestedTitle, params;

		function ok( data ) {
			var found;

			if ( data && data.query && data.query.pages ) {
				found = false;
				$.each( data.query.pages, function ( pageId, page ) {
					if ( page.title && page.title === requestedTitle && page.imageinfo ) {
						found = true;
						callback( page.imageinfo );
						return false;
					}
				} );
				if ( found ) {
					return;
				}
			}

			mw.log.warn( 'mw.UploadWizardUpload::getImageInfo> No data matching ' + requestedTitle + ' ?' );
			callback( null );
		}

		function err( code ) {
			mw.log.warn( 'mw.UploadWizardUpload::getImageInfo> ' + code );
			callback( null );
		}

		if ( props === undefined ) {
			props = [];
		}

		requestedTitle = this.title.getPrefixedText();
		params = {
			prop: 'imageinfo',
			titles: requestedTitle,
			iiprop: props.join( '|' )
		};

		if ( width !== undefined || height !== undefined ) {
			if ( !$.inArray( 'url', props ) ) {
				props.push( 'url' );
			}
			if ( width !== undefined ) {
				params.iiurlwidth = width;
			}
			if ( height !== undefined ) {
				params.iiurlheight = height;
			}
		}

		this.api.get( params ).done( ok ).fail( err );
	};

	/**
	 * Get the upload handler per browser capabilities
	 *
	 * @return {FirefoggHandler|ApiUploadFormDataHandler|ApiUploadPostHandler} upload handler object
	 */
	mw.UploadWizardUpload.prototype.getUploadHandler = function () {
		var constructor;  // must be the name of a function in 'mw' namespace

		if ( !this.uploadHandler ) {
			if ( mw.UploadWizard.config.enableFirefogg && mw.Firefogg.isInstalled() ) {
				constructor = 'FirefoggHandler';
			} else {
				constructor = 'ApiUploadFormDataHandler';
			}
			if ( mw.UploadWizard.config.debug ) {
				mw.log( 'mw.UploadWizard::getUploadHandler> ' + constructor );
			}
			if ( this.fromURL ) {
				constructor = 'ApiUploadPostHandler';
			}
			this.uploadHandler = new mw[ constructor ]( this, this.api );
		}
		return this.uploadHandler;
	};

	/**
	 * Explicitly fetch a thumbnail for a stashed upload of the desired width.
	 *
	 * @param {number} width desired width of thumbnail (height will scale to match, if not given)
	 * @param {number} [height] maximum height of thumbnail
	 * @return {jQuery.Promise} Promise resolved with a HTMLImageElement, or null if thumbnail
	 *     couldn't be generated
	 */
	mw.UploadWizardUpload.prototype.getApiThumbnail = function ( width, height ) {
		var deferred,
			key = width + '|' + height;

		if ( this.thumbnailPublishers[ key ] ) {
			return this.thumbnailPublishers[ key ];
		}

		deferred = $.Deferred();

		function thumbnailPublisher( thumbnails ) {
			if ( thumbnails === null ) {
				// the api call failed somehow, no thumbnail data.
				deferred.resolve( null );
			} else {
				// ok, the api callback has returned us information on where the thumbnail(s) ARE, but that doesn't mean
				// they are actually there yet. Keep trying to set the source ( which should trigger "error" or "load" event )
				// on the image. If it loads publish the event with the image. If it errors out too many times, give up and publish
				// the event with a null.
				$.each( thumbnails, function ( i, thumb ) {
					var timeoutMs, image;

					if ( thumb.thumberror || ( !( thumb.thumburl && thumb.thumbwidth && thumb.thumbheight ) ) ) {
						mw.log.warn( 'mw.UploadWizardUpload::getThumbnail> Thumbnail error or missing information' );
						deferred.resolve( null );
						return;
					}

					// try to load this image with exponential backoff
					// if the delay goes past 8 seconds, it gives up and publishes the event with null
					timeoutMs = 100;
					image = document.createElement( 'img' );
					image.width = thumb.thumbwidth;
					image.height = thumb.thumbheight;
					$( image )
						.on( 'load', function () {
							// publish the image to anyone who wanted it
							deferred.resolve( image );
						} )
						.on( 'error', function () {
							// retry with exponential backoff
							if ( timeoutMs < 8000 ) {
								setTimeout( function () {
									timeoutMs = timeoutMs * 2 + Math.round( Math.random() * ( timeoutMs / 10 ) );
									setSrc();
								}, timeoutMs );
							} else {
								deferred.resolve( null );
							}
						} );

					// executing this should cause a .load() or .error() event on the image
					function setSrc() {
						// IE 11 and Opera 12 will not, ever, re-request an image that they have already loaded
						// once, regardless of caching headers. Append bogus stuff to the URL to make it work.
						image.src = thumb.thumburl + '?' + Math.random();
					}

					// and, go!
					setSrc();
				} );
			}
		}

		if ( !height ) {
			// For action=query&prop=stashimageinfo / action=query&prop=imageinfo,
			// height of '-1' is the same as not given, while '0' gives a one-pixel-wide thumbnail
			height = -1;
		}

		if ( this.state !== 'complete' ) {
			this.getStashImageInfo( thumbnailPublisher, [ 'url' ], width, height );
		} else {
			this.getImageInfo( thumbnailPublisher, [ 'url' ], width, height );
		}

		this.thumbnailPublishers[ key ] = deferred.promise();
		return this.thumbnailPublishers[ key ];
	};

	/**
	 * Return the orientation of the image in degrees. Relies on metadata that
	 * may have been extracted at filereader stage, or after the upload when we fetch metadata. Default returns 0.
	 *
	 * @return {Integer} orientation in degrees: 0, 90, 180 or 270
	 */
	mw.UploadWizardUpload.prototype.getOrientationDegrees = function () {
		var orientation = 0;
		if ( this.imageinfo && this.imageinfo.metadata && this.imageinfo.metadata.orientation ) {
			switch ( this.imageinfo.metadata.orientation ) {
				case 8:
					// 'top left' -> 'left bottom'
					orientation = 90;
					break;
				case 3:
					// 'top left' -> 'bottom right'
					orientation = 180;
					break;
				case 6:
					// 'top left' -> 'right top'
					orientation = 270;
					break;
				default:
					// 'top left' -> 'top left'
					orientation = 0;
					break;

			}
		}
		return orientation;
	};

	/**
	 * Fit an image into width & height constraints with scaling factor
	 *
	 * @param {HTMLImageElement} image
	 * @param {Object} constraints Width & height properties
	 * @return {number}
	 */
	mw.UploadWizardUpload.prototype.getScalingFromConstraints = function ( image, constraints ) {
		var scaling = 1;
		$.each( [ 'width', 'height' ], function ( i, dim ) {
			var s;
			if ( constraints[ dim ] && image[ dim ] > constraints[ dim ] ) {
				s = constraints[ dim ] / image[ dim ];
				if ( s < scaling ) {
					scaling = s;
				}
			}
		} );
		return scaling;
	};

	/**
	 * Given an image (already loaded), dimension constraints
	 * return canvas object scaled & transformedi ( & rotated if metadata indicates it's needed )
	 *
	 * @param {HTMLImageElement} image
	 * @param {Object} constraints Width & height constraints
	 * @return {HTMLCanvasElement}
	 */
	mw.UploadWizardUpload.prototype.getTransformedCanvasElement = function ( image, constraints ) {
		var angle, scaleConstraints, scaling, width, height,
			dx, dy, x, y, $canvas, ctx,
			rotation = 0;

		// if this wiki can rotate images to match their EXIF metadata,
		// we should do the same in our preview
		if ( mw.config.get( 'wgFileCanRotate' ) ) {
			angle = this.getOrientationDegrees();
			rotation = angle ? 360 - angle : 0;
		}

		// swap scaling constraints if needed by rotation...
		if ( rotation === 90 || rotation === 270 ) {
			scaleConstraints = {
				width: constraints.height,
				height: constraints.width
			};
		} else {
			scaleConstraints = {
				width: constraints.width,
				height: constraints.height
			};
		}

		scaling = this.getScalingFromConstraints( image, constraints );

		width = image.width * scaling;
		height = image.height * scaling;

		// Determine the offset required to center the image
		dx = ( constraints.width - width ) / 2;
		dy = ( constraints.height - height ) / 2;

		switch ( rotation ) {
			// If a rotation is applied, the direction of the axis
			// changes as well. You can derive the values below by
			// drawing on paper an axis system, rotate it and see
			// where the positive axis direction is
			case 90:
				x = dx;
				y = dy - constraints.height;
				break;
			case 180:
				x = dx - constraints.width;
				y = dy - constraints.height;
				break;
			case 270:
				x = dx - constraints.width;
				y = dy;
				break;
			default:
				x = dx;
				y = dy;
				break;
		}

		$canvas = $( '<canvas></canvas>' ).attr( constraints );
		ctx = $canvas[ 0 ].getContext( '2d' );
		ctx.clearRect( 0, 0, width, height );
		ctx.rotate( rotation / 180 * Math.PI );
		ctx.drawImage( image, x, y, width, height );

		return $canvas;
	};

	/**
	 * Return a browser-scaled image element, given an image and constraints.
	 *
	 * @param {HTMLImageElement} image
	 * @param {Object} constraints Width and height properties
	 * @return {HTMLImageElement} with same src, but different attrs
	 */
	mw.UploadWizardUpload.prototype.getBrowserScaledImageElement = function ( image, constraints ) {
		var scaling = this.getScalingFromConstraints( image, constraints );
		return $( '<img/>' )
			.attr( {
				width:  parseInt( image.width * scaling, 10 ),
				height: parseInt( image.height * scaling, 10 ),
				src:	image.src
			} )
			.css( {
				'margin-top': ( parseInt( ( constraints.height - image.height * scaling ) / 2, 10 ) ).toString() + 'px'
			} );
	};

	/**
	 * Return an element suitable for the preview of a certain size. Uses canvas when possible
	 *
	 * @param {HTMLImageElement} image
	 * @param {Integer} width
	 * @param {Integer} height
	 * @return {HTMLCanvasElement|HTMLImageElement}
	 */
	mw.UploadWizardUpload.prototype.getScaledImageElement = function ( image, width, height ) {
		var constraints;
		if ( width === undefined || width === null || width <= 0 ) {
			width = mw.UploadWizard.config.thumbnailWidth;
		}
		constraints = {
			width: parseInt( width, 10 ),
			height: ( height === undefined ? null : parseInt( height, 10 ) )
		};

		return mw.canvas.isAvailable()
			? this.getTransformedCanvasElement( image, constraints )
			: this.getBrowserScaledImageElement( image, constraints );
	};

	/**
	 * Acquire a thumbnail of given dimensions for this upload.
	 *
	 * @param {number} width Width constraint
	 * @param {number} [height] Height constraint
	 * @return {jQuery.Promise} Promise resolved with the HTMLImageElement or HTMLCanvasElement
	 *   containing a thumbnail, or resolved with `null` when one can't be produced
	 */
	mw.UploadWizardUpload.prototype.getThumbnail = function ( width, height ) {
		var upload = this,
			deferred = $.Deferred();

		/**
		 * @param {HTMLImageElement|null} image
		 */
		function imageCallback( image ) {
			if ( image === null ) {
				upload.ui.setStatus( 'mwe-upwiz-thumbnail-failed' );
				deferred.resolve( image );
				return;
			}

			image = upload.getScaledImageElement( image, width, height );
			deferred.resolve( image );
		}

		this.extractMetadataFromJpegMeta()
			.then( upload.makePreview.bind( upload ) )
			.done( imageCallback )
			.fail( function () {
				// Can't generate the thumbnail locally, get the thumbnail via API after
				// the file is uploaded. Queries are cached, so if this thumbnail was
				// already fetched for some reason, we'll get it immediately.
				if ( upload.state !== 'new' && upload.state !== 'transporting' ) {
					upload.getApiThumbnail( width, height ).done( imageCallback );
				} else {
					upload.once( 'success', function () {
						upload.getApiThumbnail( width, height ).done( imageCallback );
					} );
				}
			} );

		return deferred.promise();
	};

	mw.UploadWizardUpload.prototype.createDetails = function () {
		this.details = new mw.UploadWizardDetails( this, $( '#mwe-upwiz-macro-files' ) );
		this.details.populate();
		this.details.attach();
	};

	/**
	 * Notification that the file input has changed and it's fine...set info.
	 */
	mw.UploadWizardUpload.prototype.fileChangedOk = function () {
		this.ui.fileChangedOk( this.imageinfo, this.file, this.fromURL );
	};

	/**
	 * Make a preview for the file.
	 */
	mw.UploadWizardUpload.prototype.makePreview = function () {
		var first, video, url, dataUrlReader,
			deferred = $.Deferred(),
			upload = this;

		// don't run this repeatedly.
		if ( this.previewPromise ) {
			return this.previewPromise;
		}
		this.previewPromise = deferred.promise();

		// do preview if we can
		if ( this.isPreviewable() ) {
			// open video and get frame via canvas
			if ( this.isVideo() ) {
				first = true;
				video = document.createElement( 'video' );

				video.addEventListener( 'loadedmetadata', function () {
					// seek 2 seconds into video or to half if shorter
					video.currentTime = Math.min( 2, video.duration / 2 );
					video.volume = 0;
				} );
				video.addEventListener( 'seeked', function () {
					// Firefox 16 sometimes does not work on first seek, seek again
					if ( first ) {
						first = false;
						video.currentTime = Math.min( 2, video.duration / 2 );

					} else {
						// Chrome sometimes shows black frames if grabbing right away.
						// wait 500ms before grabbing frame
						setTimeout( function () {
							var context,
								canvas = document.createElement( 'canvas' );
							canvas.width = 100;
							canvas.height = Math.round( canvas.width * video.videoHeight / video.videoWidth );
							context = canvas.getContext( '2d' );
							context.drawImage( video, 0, 0, canvas.width, canvas.height );
							upload.loadImage( canvas.toDataURL(), deferred );
							upload.URL().revokeObjectURL( video.url );
						}, 500 );
					}
				} );
				url = this.URL().createObjectURL( this.file );
				video.src = url;
			} else {
				dataUrlReader = new FileReader();
				dataUrlReader.onload = function () {
					// this step (inserting image-as-dataurl into image object) is slow for large images, which
					// is why this is optional and has a control attached to it to load the preview.
					upload.loadImage( dataUrlReader.result, deferred );
				};
				dataUrlReader.readAsDataURL( this.file );
			}
		} else {
			deferred.reject();
		}

		return this.previewPromise;
	};

	/**
	 * Loads an image preview.
	 */
	mw.UploadWizardUpload.prototype.loadImage = function ( url, deferred ) {
		var image = document.createElement( 'img' );
		image.onload = function () {
			deferred.resolve( image );
		};
		image.onerror = function () {
			deferred.reject();
		};
		image.src = url;
	};

	/**
	 * Check if the file is previewable.
	 */
	mw.UploadWizardUpload.prototype.isPreviewable = function () {
		return this.file && mw.fileApi.isPreviewableFile( this.file );
	};

	/**
	 * Finds the right URL object to use.
	 */
	mw.UploadWizardUpload.prototype.URL = function () {
		return window.URL || window.webkitURL || window.mozURL;
	};

	/**
	 * Checks if this upload is a video.
	 */
	mw.UploadWizardUpload.prototype.isVideo = function () {
		return mw.fileApi.isPreviewableVideo( this.file );
	};

} )( mediaWiki, mediaWiki.uploadWizard, jQuery, OO );
