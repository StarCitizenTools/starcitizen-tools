( function () {
	/**
	 * Represents a "transport" for files to upload; using HTML5 FormData.
	 *
	 * @constructor
	 * @class mw.FormDataTransport
	 * @mixins OO.EventEmitter
	 * @param {mw.Api} api
	 * @param {Object} formData Additional form fields required for upload api call
	 * @param {Object} [config]
	 * @param {Object} [config.chunkSize]
	 * @param {Object} [config.maxPhpUploadSize]
	 * @param {Object} [config.useRetryTimeout]
	 */
	mw.FormDataTransport = function ( api, formData, config ) {
		this.config = config || mw.UploadWizard.config;

		OO.EventEmitter.call( this );

		this.formData = formData;
		this.aborted = false;
		this.api = api;

		// Set chunk size to configured chunk size or max php size,
		// whichever is smaller.
		this.chunkSize = Math.min( this.config.chunkSize, this.config.maxPhpUploadSize );
		this.maxRetries = 2;
		this.retries = 0;
		this.firstPoll = false;

		// running API request
		this.request = null;
	};

	OO.mixinClass( mw.FormDataTransport, OO.EventEmitter );

	mw.FormDataTransport.prototype.abort = function () {
		this.aborted = true;

		if ( this.request ) {
			this.request.abort();
		}
	};

	/**
	 * Submits an upload to the API.
	 *
	 * @param {Object} params Request params
	 * @return {jQuery.Promise}
	 */
	mw.FormDataTransport.prototype.post = function ( params ) {
		var deferred = $.Deferred();

		this.request = this.api.post( params, {
			/*
			 * $.ajax is not quite equiped to handle File uploads with params.
			 * The most convenient way would be to submit it with a FormData
			 * object, but mw.Api will already do that for us: it'll transform
			 * params if it encounters a multipart/form-data POST request, and
			 * submit it accordingly!
			 *
			 * @see https://developer.mozilla.org/en-US/docs/Web/API/XMLHttpRequest/Using_XMLHttpRequest#Submitting_forms_and_uploading_files
			 */
			contentType: 'multipart/form-data',
			/*
			 * $.ajax also has no progress event that will allow us to figure
			 * out how much of the upload has already gone out, so let's add it!
			 */
			xhr: function () {
				var xhr = $.ajaxSettings.xhr();
				xhr.upload.addEventListener( 'progress', function ( evt ) {
					var fraction = null;
					if ( evt.lengthComputable ) {
						fraction = parseFloat( evt.loaded / evt.total );
					}
					deferred.notify( fraction );
				}, false );
				return xhr;
			}
		} );

		// just pass on success & failures
		this.request.then( deferred.resolve, deferred.reject );

		return deferred.promise();
	};

	/**
	 * Creates the upload API params.
	 *
	 * @param {string} filename
	 * @param {number} [offset] For chunked uploads
	 * @return {Object}
	 */
	mw.FormDataTransport.prototype.createParams = function ( filename, offset ) {
		var params = OO.cloneObject( this.formData );

		$.extend( params, {
			filename: filename,

			// ignorewarnings is turned on, since warnings are presented in a
			// later step and this transport doesn't know how to deal with them.
			// Also, it's important to allow people to upload files with (for
			// example) blacklisted names, and then rename them later in the
			// wizard.
			ignorewarnings: true,

			offset: offset || 0
		} );

		return params;
	};

	/**
	 * Start the upload with the provided file.
	 *
	 * @param {File} file
	 * @param {string} tempFileName
	 * @return {jQuery.Promise}
	 */
	mw.FormDataTransport.prototype.upload = function ( file, tempFileName ) {
		var params, ext;

		this.tempname = tempFileName;
		// Limit length to 240 bytes (limit hardcoded in UploadBase.php).
		if ( this.tempname.length > 240 ) {
			ext = this.tempname.split( '.' ).pop();
			this.tempname = this.tempname.substr( 0, 240 - ext.length - 1 ) + '.' + ext;
		}

		if ( file.size > this.chunkSize ) {
			return this.chunkedUpload( file );
		} else {
			params = this.createParams( this.tempname );
			params.file = file;
			return this.post( params );
		}
	};

	/**
	 * This function exists to safely chain several hundred promises without using .then() or nested
	 * promises. We might divide a 4 GB file into 800 chunks of 5 MB each.
	 *
	 * In jQuery 2.x, nested promises result in nested call stacks when resolving/rejecting/notifying
	 * the last promise in the chain and listening on the first one, and browsers have call stack
	 * limits low enough that we previously ran into them for files around a couple hundred megabytes
	 * (the worst is Firefox 47 with a limit of 1024 calls).
	 *
	 * @param {File} file
	 * @return {jQuery.Promise} Promise which behaves identically to a regular non-chunked upload
	 *   promise from #upload
	 */
	mw.FormDataTransport.prototype.chunkedUpload = function ( file ) {
		var
			offset,
			prevPromise = $.Deferred().resolve(),
			deferred = $.Deferred(),
			fileSize = file.size,
			chunkSize = this.chunkSize,
			transport = this;

		for ( offset = 0; offset < fileSize; offset += chunkSize ) {
			// Capture offset in a closure
			// eslint-disable-next-line no-loop-func
			( function ( offset ) {
				var
					newPromise = $.Deferred(),
					isLastChunk = offset + chunkSize >= fileSize,
					thisChunkSize = isLastChunk ? ( fileSize % chunkSize ) : chunkSize;
				prevPromise.done( function () {
					transport.uploadChunk( file, offset )
						.done( isLastChunk ? deferred.resolve : newPromise.resolve )
						.fail( deferred.reject )
						.progress( function ( fraction ) {
							// The progress notifications give us per-chunk progress.
							// Calculate progress for the whole file.
							deferred.notify( ( offset + fraction * thisChunkSize ) / fileSize );
						} );
				} );
				prevPromise = newPromise;
			}( offset ) );
		}

		return deferred.promise();
	};

	/**
	 * Upload a single chunk.
	 *
	 * @param {File} file
	 * @param {number} offset Offset in bytes.
	 * @return {jQuery.Promise}
	 */
	mw.FormDataTransport.prototype.uploadChunk = function ( file, offset ) {
		var params = this.createParams( this.tempname, offset ),
			transport = this,
			bytesAvailable = file.size,
			chunk;

		if ( this.aborted ) {
			return $.Deferred().reject( 'aborted', {
				errors: [ {
					code: 'aborted',
					html: mw.message( 'api-error-aborted' ).parse()
				} ]
			} );
		}

		// Slice API was changed and has vendor prefix for now
		// new version now require start/end and not start/length
		if ( file.mozSlice ) {
			chunk = file.mozSlice( offset, offset + this.chunkSize, file.type );
		} else if ( file.webkitSlice ) {
			chunk = file.webkitSlice( offset, offset + this.chunkSize, file.type );
		} else {
			chunk = file.slice( offset, offset + this.chunkSize, file.type );
		}

		// only enable async if file is larger 10Mb
		if ( bytesAvailable > 10 * 1024 * 1024 ) {
			params.async = true;
		}

		// If offset is 0, we're uploading the file from scratch. filekey may be set if we're retrying
		// the first chunk. The API errors out if a filekey is given with zero offset (as it's
		// nonsensical). TODO Why do we need to retry in this case, if we managed to upload something?
		if ( this.filekey && offset !== 0 ) {
			params.filekey = this.filekey;
		}
		params.filesize = bytesAvailable;
		params.chunk = chunk;

		return this.post( params ).then( function ( response ) {
			if ( response.upload && response.upload.filekey ) {
				transport.filekey = response.upload.filekey;
			}

			if ( response.upload && response.upload.result ) {
				switch ( response.upload.result ) {
					case 'Continue':
						// Reset retry counter
						transport.retries = 0;
						/* falls through */
					case 'Success':
						// Just pass the response through.
						return response;
					case 'Poll':
						// Need to retry with checkStatus.
						return transport.retryWithMethod( 'checkStatus' );
				}
			} else {
				return transport.maybeRetry(
					'on unknown response',
					response.error ? response.error.code : 'unknown-error',
					response,
					'uploadChunk',
					file, offset
				);
			}
		}, function ( code, result ) {
			// Ain't this some great machine readable output eh
			if (
				result.errors &&
				result.errors[ 0 ].code === 'stashfailed' &&
				result.errors[ 0 ].html === mw.message( 'apierror-stashfailed-complete' ).parse()
			) {
				return transport.retryWithMethod( 'checkStatus' );
			}

			// Failed to upload, try again in 3 seconds
			// This is really dumb, we should only do this for cases where retrying has a chance to work
			// (so basically, network failures). If your upload was blocked by AbuseFilter you're
			// shafted anyway. But some server-side errors really are temporary...
			return transport.maybeRetry(
				'on error event',
				code,
				result,
				'uploadChunk',
				file, offset
			);
		} );
	};

	/**
	 * Handle possible retry event - rejected if maximum retries already fired.
	 *
	 * @param {string} contextMsg
	 * @param {string} code
	 * @param {Object} response
	 * @param {string} retryMethod
	 * @param {File} [file]
	 * @param {number} [offset]
	 * @return {jQuery.Promise}
	 */
	mw.FormDataTransport.prototype.maybeRetry = function ( contextMsg, code, response, retryMethod, file, offset ) {
		this.retries++;

		if ( this.tooManyRetries() ) {
			mw.log.warn( 'Max retries exceeded ' + contextMsg );
			return $.Deferred().reject( code, response );
		} else if ( this.aborted ) {
			return $.Deferred().reject( code, response );
		} else {
			mw.log( 'Retry #' + this.retries + ' ' + contextMsg );
			return this.retryWithMethod( retryMethod, file, offset );
		}
	};

	/**
	 * Have we retried too many times already?
	 *
	 * @return {boolean}
	 */
	mw.FormDataTransport.prototype.tooManyRetries = function () {
		return this.maxRetries > 0 && this.retries >= this.maxRetries;
	};

	/**
	 * Either retry uploading or checking the status.
	 *
	 * @param {'uploadChunk'|'checkStatus'} methodName
	 * @param {File} [file]
	 * @param {number} [offset]
	 * @return {jQuery.Promise}
	 */
	mw.FormDataTransport.prototype.retryWithMethod = function ( methodName, file, offset ) {
		var
			transport = this,
			retryDeferred = $.Deferred(),
			retry = function () {
				transport[ methodName ]( file, offset ).then( retryDeferred.resolve, retryDeferred.reject );
			};

		if ( this.config.useRetryTimeout !== false ) {
			setTimeout( retry, 3000 );
		} else {
			retry();
		}

		return retryDeferred.promise();
	};

	/**
	 * Check the status of the upload.
	 *
	 * @return {jQuery.Promise}
	 */
	mw.FormDataTransport.prototype.checkStatus = function () {
		var transport = this,
			params = OO.cloneObject( this.formData );

		if ( this.aborted ) {
			return $.Deferred().reject( 'aborted', {
				errors: [ {
					code: 'aborted',
					html: mw.message( 'api-error-aborted' ).parse()
				} ]
			} );
		}

		if ( !this.firstPoll ) {
			this.firstPoll = ( new Date() ).getTime();
		}
		params.checkstatus = true;
		params.filekey = this.filekey;
		this.request = this.api.post( params )
			.then( function ( response ) {
				if ( response.upload && response.upload.result === 'Poll' ) {
					// If concatenation takes longer than 10 minutes give up
					if ( ( ( new Date() ).getTime() - transport.firstPoll ) > 10 * 60 * 1000 ) {
						return $.Deferred().reject( 'server-error', { errors: [ {
							code: 'server-error',
							html: mw.message( 'api-clientside-error-timeout' ).parse()
						} ] } );
					} else {
						if ( response.upload.stage === undefined ) {
							mw.log.warn( 'Unable to check file\'s status' );
							return $.Deferred().reject( 'server-error', { errors: [ {
								code: 'server-error',
								html: mw.message( 'api-clientside-error-invalidresponse' ).parse()
							} ] } );
						} else {
							// Statuses that can be returned:
							// * queued
							// * publish
							// * assembling
							transport.emit( 'update-stage', response.upload.stage );
							return transport.retryWithMethod( 'checkStatus' );
						}
					}
				}

				return response;
			}, function ( code, result ) {
				return $.Deferred().reject( code, result );
			} );

		return this.request;
	};
}() );
