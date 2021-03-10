/**
 * Represents the upload -- in its local and remote state. (Possibly those could be separate objects too...)
 * This is our 'model' object if we are thinking MVC. Needs to be better factored, lots of feature envy with the UploadWizard
 * states:
 *   'new' 'transporting' 'transported' 'metadata' 'stashed' 'details' 'submitting-details' 'complete' 'error'
 * should fork this into two -- local and remote, e.g. filename
 */
( function ( uw ) {
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
	 * @param {uw.controller.Step} controller
	 * @param {File} file
	 */
	mw.UploadWizardUpload = function MWUploadWizardUpload( controller, file ) {
		OO.EventEmitter.call( this );

		this.index = mw.UploadWizardUpload.prototype.count;
		mw.UploadWizardUpload.prototype.count++;

		this.controller = controller;
		this.api = controller.api;
		this.file = file;
		this.state = 'new';
		this.imageinfo = {};
		this.title = undefined;
		this.thumbnailPromise = {};

		this.fileKey = undefined;

		// this should be moved to the interface, if we even keep this
		this.transportWeight = 1; // default all same

		// details
		this.ui = new mw.UploadWizardUploadInterface( this )
			.connect( this, {
				/*
				 * This may be confusing!
				 * This object also has a `remove` method, which will also be
				 * called when an upload is removed. But an upload can be
				 * removed for multiple reasons (one being clicking the "remove"
				 * button, which triggers this event - but another could be
				 * removing faulty uploads).
				 * To simplify things, we'll always initiate the remove from the
				 * controllers, so we'll relay this event to the controllers,
				 * which will then eventually come back to call `remove` on this
				 * object.
				 */
				'upload-removed': [ 'emit', 'remove-upload' ]
			} );
	};

	OO.mixinClass( mw.UploadWizardUpload, OO.EventEmitter );

	// Upload handler
	mw.UploadWizardUpload.prototype.uploadHandler = null;

	// increments with each upload
	mw.UploadWizardUpload.prototype.count = 0;

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
	 * Remove this upload. n.b. we trigger a removeUpload this is usually triggered from
	 */
	mw.UploadWizardUpload.prototype.remove = function () {
		// remove the div that passed along the trigger
		this.ui.$div.remove();

		this.state = 'aborted';
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
		this.ui.$div.trigger( 'transportProgressEvent' );
	};

	/**
	 * Stop the upload -- we have failed for some reason
	 *
	 * @param {string} code Error code from API
	 * @param {string} html Error message
	 * @param {jQuery} [$additionalStatus]
	 */
	mw.UploadWizardUpload.prototype.setError = function ( code, html, $additionalStatus ) {
		if ( this.state === 'aborted' ) {
			// There's no point in reporting an error anymore.
			return;
		}
		this.state = 'error';
		this.transportProgress = 0;
		this.ui.showError( code, html, $additionalStatus );
		uw.eventFlowLogger.logError( 'file', { code: code, message: html } );
	};

	/**
	 * Called from any upload success condition
	 *
	 * @param {Object} result -- result of AJAX call
	 */
	mw.UploadWizardUpload.prototype.setSuccess = function ( result ) {
		this.state = 'transported';
		this.transportProgress = 1;

		this.ui.setStatus( 'mwe-upwiz-getting-metadata' );

		this.extractUploadInfo( result.upload );
		this.state = 'stashed';
		this.ui.showStashed();

		this.emit( 'success' );
		// check all uploads, if they're complete, show the next button
		// TODO Make wizard connect to 'success' event
		this.controller.showNext();
	};

	/**
	 * Get just the filename.
	 *
	 * @return {string}
	 */
	mw.UploadWizardUpload.prototype.getFilename = function () {
		if ( this.file.fileName ) {
			return this.file.fileName;
		} else {
			// this property has a different name in FF vs Chrome.
			return this.file.name;
		}
	};

	/**
	 * Get the basename of a path.
	 * For error conditions, returns the empty string.
	 *
	 * @return {string} basename
	 */
	mw.UploadWizardUpload.prototype.getBasename = function () {
		var path = this.getFilename();

		if ( path === undefined || path === null ) {
			return '';
		}

		// find index of last path separator in the path, add 1. (If no separator found, yields 0)
		// then take the entire string after that.
		return path.substr( Math.max( path.lastIndexOf( '/' ), path.lastIndexOf( '\\' ) ) + 1 );
	};

	/**
	 * Sanitize and set the title of the upload.
	 *
	 * @param {string} title Unsanitized title.
	 */
	mw.UploadWizardUpload.prototype.setTitle = function ( title ) {
		this.title = mw.Title.newFromFileName( title );
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
	 * @private
	 * @return {jQuery.Promise} A promise, resolved when we're done
	 */
	mw.UploadWizardUpload.prototype.extractMetadataFromJpegMeta = function () {
		var binReader, jpegmeta,
			deferred = $.Deferred(),
			upload = this;
		if ( this.file && this.file.type === 'image/jpeg' ) {
			binReader = new FileReader();
			binReader.onerror = function () {
				deferred.resolve();
			};
			binReader.onload = function () {
				var binStr, arr, i, meta;
				if ( binReader.result === null ) {
					// Contrary to documentation, this sometimes fires for unsuccessful loads (T136235)
					deferred.resolve();
					return;
				}
				if ( typeof binReader.result === 'string' ) {
					binStr = binReader.result;
				} else {
					// Array buffer; convert to binary string for the library.
					/* global Uint8Array */
					arr = new Uint8Array( binReader.result );
					binStr = '';
					for ( i = 0; i < arr.byteLength; i++ ) {
						binStr += String.fromCharCode( arr[ i ] );
					}
				}
				try {
					jpegmeta = require( 'mediawiki.libs.jpegmeta' );
					meta = jpegmeta( binStr, upload.file.fileName );
					// eslint-disable-next-line camelcase, no-underscore-dangle
					meta._binary_data = null;
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

		for ( key in imageinfo ) {
			// we get metadata as list of key-val pairs; convert to object for easier lookup. Assuming that EXIF fields are unique.
			if ( key === 'metadata' ) {
				if ( this.imageinfo.metadata === undefined ) {
					this.imageinfo.metadata = {};
				}
				if ( imageinfo.metadata && imageinfo.metadata.length ) {
					imageinfo.metadata.forEach( function ( pair ) {
						if ( pair !== undefined ) {
							upload.imageinfo.metadata[ pair.name.toLowerCase() ] = pair.value;
						}
					} );
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
			if ( props.indexOf( 'url' ) === -1 ) {
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
				Object.keys( data.query.pages ).forEach( function ( pageId ) {
					var page = data.query.pages[ pageId ];
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
			if ( props.indexOf( 'url' ) === -1 ) {
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
	 * @return {mw.ApiUploadFormDataHandler|mw.ApiUploadPostHandler} upload handler object
	 */
	mw.UploadWizardUpload.prototype.getUploadHandler = function () {
		var constructor; // must be the name of a function in 'mw' namespace

		if ( !this.uploadHandler ) {
			constructor = 'ApiUploadFormDataHandler';
			if ( mw.UploadWizard.config.debug ) {
				mw.log( 'mw.UploadWizard::getUploadHandler> ' + constructor );
			}
			if ( this.file.fromURL ) {
				constructor = 'ApiUploadPostHandler';
			}
			this.uploadHandler = new mw[ constructor ]( this, this.api );
		}
		return this.uploadHandler;
	};

	/**
	 * Explicitly fetch a thumbnail for a stashed upload of the desired width.
	 *
	 * @private
	 * @param {number} width Desired width of thumbnail
	 * @param {number} height Maximum height of thumbnail
	 * @return {jQuery.Promise} Promise resolved with a HTMLImageElement, or null if thumbnail
	 *     couldn't be generated
	 */
	mw.UploadWizardUpload.prototype.getApiThumbnail = function ( width, height ) {
		var deferred = $.Deferred();

		function thumbnailPublisher( thumbnails ) {
			if ( thumbnails === null ) {
				// the api call failed somehow, no thumbnail data.
				deferred.resolve( null );
			} else {
				// ok, the api callback has returned us information on where the thumbnail(s) ARE, but that doesn't mean
				// they are actually there yet. Keep trying to set the source ( which should trigger "error" or "load" event )
				// on the image. If it loads publish the event with the image. If it errors out too many times, give up and publish
				// the event with a null.
				thumbnails.forEach( function ( thumb ) {
					var timeoutMs, image;

					if ( thumb.thumberror || ( !( thumb.thumburl && thumb.thumbwidth && thumb.thumbheight ) ) ) {
						mw.log.warn( 'mw.UploadWizardUpload::getThumbnail> Thumbnail error or missing information' );
						deferred.resolve( null );
						return;
					}

					// executing this should cause a .load() or .error() event on the image
					function setSrc() {
						// IE 11 and Opera 12 will not, ever, re-request an image that they have already loaded
						// once, regardless of caching headers. Append bogus stuff to the URL to make it work.
						image.src = thumb.thumburl + '?' + Math.random();
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

					// and, go!
					setSrc();
				} );
			}
		}

		if ( this.state !== 'complete' ) {
			this.getStashImageInfo( thumbnailPublisher, [ 'url' ], width, height );
		} else {
			this.getImageInfo( thumbnailPublisher, [ 'url' ], width, height );
		}

		return deferred.promise();
	};

	/**
	 * Return the orientation of the image in degrees. Relies on metadata that
	 * may have been extracted at filereader stage, or after the upload when we fetch metadata. Default returns 0.
	 *
	 * @return {number} orientation in degrees: 0, 90, 180 or 270
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
	 * @private
	 * @param {HTMLImageElement} image
	 * @param {Object} constraints Width & height properties
	 * @return {number}
	 */
	mw.UploadWizardUpload.prototype.getScalingFromConstraints = function ( image, constraints ) {
		var scaling = 1;
		Object.keys( constraints ).forEach( function ( dim ) {
			var s,
				constraint = constraints[ dim ];
			if ( constraint && image[ dim ] > constraint ) {
				s = constraint / image[ dim ];
				if ( s < scaling ) {
					scaling = s;
				}
			}
		} );
		return scaling;
	};

	/**
	 * Given an image (already loaded), dimension constraints
	 * return canvas object scaled & transformed ( & rotated if metadata indicates it's needed )
	 *
	 * @private
	 * @param {HTMLImageElement} image
	 * @param {Object} constraints Width & height constraints
	 * @return {HTMLCanvasElement|null}
	 */
	mw.UploadWizardUpload.prototype.getTransformedCanvasElement = function ( image, constraints ) {
		var angle, scaling, width, height,
			dimensions, dx, dy, x, y, $canvas, ctx,
			scaleConstraints = constraints,
			rotation = 0;

		// if this wiki can rotate images to match their EXIF metadata,
		// we should do the same in our preview
		if ( mw.config.get( 'wgFileCanRotate' ) ) {
			angle = this.getOrientationDegrees();
			rotation = angle ? 360 - angle : 0;
		}

		// swap scaling constraints if needed by rotation...
		if ( rotation === 90 || rotation === 270 ) {
			scaleConstraints = {};
			if ( 'height' in constraints ) {
				scaleConstraints.width = constraints.height;
			}
			if ( 'width' in constraints ) {
				scaleConstraints.height = constraints.width;
			}
		}

		scaling = this.getScalingFromConstraints( image, scaleConstraints );

		width = image.width * scaling;
		height = image.height * scaling;

		dimensions = { width: width, height: height };
		if ( rotation === 90 || rotation === 270 ) {
			dimensions = { width: height, height: width };
		}

		// Start drawing at offset 0,0
		dx = 0;
		dy = 0;

		switch ( rotation ) {
			// If a rotation is applied, the direction of the axis
			// changes as well. You can derive the values below by
			// drawing on paper an axis system, rotate it and see
			// where the positive axis direction is
			case 90:
				x = dx;
				y = dy - height;
				break;
			case 180:
				x = dx - width;
				y = dy - height;
				break;
			case 270:
				x = dx - width;
				y = dy;
				break;
			default:
				x = dx;
				y = dy;
				break;
		}

		$canvas = $( '<canvas>' ).attr( dimensions );
		ctx = $canvas[ 0 ].getContext( '2d' );
		ctx.clearRect( dx, dy, width, height );
		ctx.rotate( rotation / 180 * Math.PI );
		try {
			// Calling #drawImage likes to throw all kinds of ridiculous exceptions in various browsers,
			// including but not limited to:
			// * (Firefox) NS_ERROR_NOT_AVAILABLE:
			// * (Internet Explorer / Edge) Not enough storage is available to complete this operation.
			// * (Internet Explorer / Edge) Unspecified error.
			// * (Internet Explorer / Edge) The GPU device instance has been suspended. Use GetDeviceRemovedReason to determine the appropriate action.
			// * (Safari) IndexSizeError: Index or size was negative, or greater than the allowed value.
			// There is nothing we can do about this. It's okay though, there just won't be a thumbnail.
			ctx.drawImage( image, x, y, width, height );
		} catch ( err ) {
			uw.eventFlowLogger.maybeLogFirefoxCanvasException( err, image );
			return null;
		}

		return $canvas;
	};

	/**
	 * Return a browser-scaled image element, given an image and constraints.
	 *
	 * @private
	 * @param {HTMLImageElement} image
	 * @param {Object} constraints Width and height properties
	 * @return {HTMLImageElement} with same src, but different attrs
	 */
	mw.UploadWizardUpload.prototype.getBrowserScaledImageElement = function ( image, constraints ) {
		var scaling = this.getScalingFromConstraints( image, constraints );
		return $( '<img>' )
			.attr( {
				width: parseInt( image.width * scaling, 10 ),
				height: parseInt( image.height * scaling, 10 ),
				src: image.src
			} );
	};

	/**
	 * Return an element suitable for the preview of a certain size. Uses canvas when possible
	 *
	 * @private
	 * @param {HTMLImageElement} image
	 * @param {number} width
	 * @param {number} height
	 * @return {HTMLCanvasElement|HTMLImageElement}
	 */
	mw.UploadWizardUpload.prototype.getScaledImageElement = function ( image, width, height ) {
		var constraints = {},
			transform;

		if ( width ) {
			constraints.width = width;
		}
		if ( height ) {
			constraints.height = height;
		}

		if ( mw.canvas.isAvailable() ) {
			transform = this.getTransformedCanvasElement( image, constraints );
			if ( transform ) {
				return transform;
			}
		}

		// No canvas support or canvas drawing failed mysteriously, fall back
		return this.getBrowserScaledImageElement( image, constraints );
	};

	/**
	 * Acquire a thumbnail for this upload.
	 *
	 * @param {number} width
	 * @param {number} height
	 * @return {jQuery.Promise} Promise resolved with the HTMLImageElement or HTMLCanvasElement
	 *   containing a thumbnail, or resolved with `null` when one can't be produced
	 */
	mw.UploadWizardUpload.prototype.getThumbnail = function ( width, height ) {
		var upload = this,
			deferred = $.Deferred();

		if ( this.thumbnailPromise[ width + 'x' + height ] ) {
			return this.thumbnailPromise[ width + 'x' + height ];
		}
		this.thumbnailPromise[ width + 'x' + height ] = deferred.promise();

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
			.then( upload.makePreview.bind( upload, width ) )
			.done( imageCallback )
			.fail( function () {
				// Can't generate the thumbnail locally, get the thumbnail via API after
				// the file is uploaded. Queries are cached, so if this thumbnail was
				// already fetched for some reason, we'll get it immediately.
				if ( upload.state !== 'new' && upload.state !== 'transporting' && upload.state !== 'error' ) {
					upload.getApiThumbnail( width, height ).done( imageCallback );
				} else {
					upload.once( 'success', function () {
						upload.getApiThumbnail( width, height ).done( imageCallback );
					} );
				}
			} );

		return this.thumbnailPromise[ width + 'x' + height ];
	};

	/**
	 * Notification that the file input has changed and it's fine...set info.
	 */
	mw.UploadWizardUpload.prototype.fileChangedOk = function () {
		this.ui.fileChangedOk( this.imageinfo, this.file );
	};

	/**
	 * Make a preview for the file.
	 *
	 * @private
	 * @param {number} width
	 * @return {jQuery.Promise}
	 */
	mw.UploadWizardUpload.prototype.makePreview = function ( width ) {
		var first, video, url, dataUrlReader,
			deferred = $.Deferred(),
			upload = this;

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
							canvas.width = width;
							canvas.height = Math.round( canvas.width * video.videoHeight / video.videoWidth );
							context = canvas.getContext( '2d' );
							try {
								// More ridiculous exceptions, see the comment in #getTransformedCanvasElement
								context.drawImage( video, 0, 0, canvas.width, canvas.height );
							} catch ( err ) {
								uw.eventFlowLogger.maybeLogFirefoxCanvasException( err, video );
								deferred.reject();
							}
							upload.loadImage( canvas.toDataURL(), deferred );
							upload.URL().revokeObjectURL( video.url );
						}, 500 );
					}
				} );
				url = this.URL().createObjectURL( this.file );
				video.src = url;
				// If we can't get a frame within 10 seconds, something is probably seriously wrong.
				// This can happen for broken files where we can't actually seek to the time we wanted.
				setTimeout( function () {
					deferred.reject();
					upload.URL().revokeObjectURL( video.url );
				}, 10000 );
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

		return deferred.promise();
	};

	/**
	 * Loads an image preview.
	 *
	 * @param {string} url
	 * @param {jQuery.Deferred} deferred
	 */
	mw.UploadWizardUpload.prototype.loadImage = function ( url, deferred ) {
		var image = document.createElement( 'img' );
		image.onload = function () {
			deferred.resolve( image );
		};
		image.onerror = function () {
			deferred.reject();
		};
		try {
			image.src = url;
		} catch ( er ) {
			// On Internet Explorer 11 and Edge, this occasionally causes an exception (possibly
			// localised) like "Not enough storage is available to complete this operation". (T136239)
			deferred.reject();
		}
	};

	/**
	 * Check if the file is previewable.
	 *
	 * @return {boolean}
	 */
	mw.UploadWizardUpload.prototype.isPreviewable = function () {
		return this.file && mw.fileApi.isPreviewableFile( this.file );
	};

	/**
	 * Finds the right URL object to use.
	 *
	 * @return {URL}
	 */
	mw.UploadWizardUpload.prototype.URL = function () {
		return window.URL || window.webkitURL || window.mozURL;
	};

	/**
	 * Checks if this upload is a video.
	 *
	 * @return {boolean}
	 */
	mw.UploadWizardUpload.prototype.isVideo = function () {
		return mw.fileApi.isPreviewableVideo( this.file );
	};

}( mw.uploadWizard ) );
