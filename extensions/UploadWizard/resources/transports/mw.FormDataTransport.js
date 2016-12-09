( function ( mw, $, OO ) {
	/**
	 * Represents a "transport" for files to upload; using HTML5 FormData.
	 *
	 * @constructor
	 * @class mw.FormDataTransport
	 * @mixins OO.EventEmitter
	 * @param {string} postUrl URL to post to.
	 * @param {Object} formData Additional form fields required for upload api call
	 * @param {Object} [config]
	 * @param {Object} [config.chunkSize]
	 * @param {Object} [config.maxPhpUploadSize]
	 * @param {Object} [config.useRetryTimeout]
	 */
	mw.FormDataTransport = function ( postUrl, formData, config ) {
		this.config = config || mw.UploadWizard.config;

		OO.EventEmitter.call( this );

		this.formData = formData;
		this.aborted = false;
		this.api = new mw.Api();

		this.postUrl = postUrl;
		// Set chunk size to configured chunk size or max php size,
		// whichever is smaller.
		this.chunkSize = Math.min( this.config.chunkSize, this.config.maxPhpUploadSize );
		this.maxRetries = 2;
		this.retries = 0;
		this.firstPoll = false;
	};

	OO.mixinClass( mw.FormDataTransport, OO.EventEmitter );

	mw.FormDataTransport.prototype.abort = function () {
		this.aborted = true;

		if ( this.xhr ) {
			this.xhr.abort();
		}
	};

	/**
	 * Creates an XHR and sets some generic event handlers on it.
	 *
	 * @param {jQuery.Deferred} deferred Object to send events to.
	 * @return {XMLHttpRequest}
	 */
	mw.FormDataTransport.prototype.createXHR = function ( deferred ) {
		var xhr = new XMLHttpRequest(),
			transport = this;

		xhr.upload.addEventListener( 'progress', function ( evt ) {
			var fraction;
			if ( evt.lengthComputable ) {
				fraction = parseFloat( evt.loaded / evt.total );
			} else {
				fraction = null;
			}
			deferred.notify( fraction );
		}, false );

		xhr.addEventListener( 'abort', function ( evt ) {
			deferred.reject( transport.parseResponse( evt ) );
		}, false );

		return xhr;
	};

	/**
	 * Creates a FormData object suitable for upload.
	 *
	 * @param {string} filename
	 * @param {number} [offset] For chunked uploads
	 * @return {FormData}
	 */
	mw.FormDataTransport.prototype.createFormData = function ( filename, offset ) {
		var formData = new FormData();

		$.each( this.formData, function ( key, value ) {
			formData.append( key, value );
		} );

		formData.append( 'filename', filename );

		// ignorewarnings is turned on, since warnings are presented in a
		// later step and this transport doesn't know how to deal with them.
		// Also, it's important to allow people to upload files with (for
		// example) blacklisted names, and then rename them later in the
		// wizard.
		formData.append( 'ignorewarnings', true );

		formData.append( 'offset', offset || 0 );

		return formData;
	};

	/**
	 * Sends data in a FormData object through an XHR.
	 *
	 * @param {XMLHttpRequest} xhr
	 * @param {FormData} formData
	 */
	mw.FormDataTransport.prototype.sendData = function ( xhr, formData ) {
		xhr.open( 'POST', this.postUrl, true );
		xhr.send( formData );
	};

	/**
	 * Start the upload with the provided file.
	 *
	 * @return {jQuery.Promise}
	 */
	mw.FormDataTransport.prototype.upload = function ( file ) {
		var formData, deferred, ext, totalUploaded,
			chunkSize = this.chunkSize,
			transport = this;

		// use timestamp + filename to avoid conflicts on server
		this.tempname = ( new Date() ).getTime().toString() + mw.UploadWizard.sanitizeFilename( file.name );
		// remove unicode characters, tempname is only used during upload
		this.tempname = this.tempname.split( '' ).map( function ( c ) {
			return c.charCodeAt( 0 ) > 128 ? '_' : c;
		} ).join( '' );
		// Also limit length to 240 bytes (limit hardcoded in UploadBase.php).
		if ( this.tempname.length > 240 ) {
			ext = this.tempname.split( '.' ).pop();
			this.tempname = this.tempname.substr( 0, 240 - ext.length - 1 ) + '.' + ext;
		}

		if ( file.size > chunkSize ) {
			totalUploaded = 0;
			// The progress notifications give us per-chunk progress, filter them to get progress
			// for the whole file
			return this.uploadChunk( file, 0 ).then( null, null, function ( fraction ) {
				if ( fraction === 1 ) {
					// We completed a chunk
					totalUploaded += chunkSize;
					fraction = 0;
				}
				return ( totalUploaded + fraction * chunkSize ) / file.size;
			} );
		} else {
			deferred = $.Deferred();
			this.xhr = this.createXHR( deferred );
			this.xhr.addEventListener( 'load', function ( evt ) {
				deferred.resolve( transport.parseResponse( evt ) );
			}, false );
			this.xhr.addEventListener( 'error', function ( evt ) {
				deferred.reject( transport.parseResponse( evt ) );
			}, false );

			formData = this.createFormData( this.tempname );
			formData.append( 'file', file );

			this.sendData( this.xhr, formData );

			return deferred.promise();
		}
	};

	/**
	 * Upload a single chunk.
	 *
	 * @param {File} file
	 * @param {number} offset Offset in bytes.
	 * @return {jQuery.Promise}
	 */
	mw.FormDataTransport.prototype.uploadChunk = function ( file, offset ) {
		var formData,
			deferred = $.Deferred(),
			transport = this,
			bytesAvailable = file.size,
			chunk;

		if ( this.aborted ) {
			if ( this.xhr ) {
				this.xhr.abort();
			}
			return;
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

		this.xhr = this.createXHR( deferred );
		this.xhr.addEventListener( 'load', deferred.resolve, false );
		this.xhr.addEventListener( 'error', deferred.reject, false );

		formData = this.createFormData( this.tempname, offset );

		// only enable async if file is larger 10Mb
		if ( bytesAvailable > 10 * 1024 * 1024 ) {
			formData.append( 'async', true );
		}

		if ( this.filekey ) {
			formData.append( 'filekey', this.filekey );
		}
		formData.append( 'filesize', bytesAvailable );
		formData.append( 'chunk', chunk );

		this.sendData( this.xhr, formData );

		return deferred.promise().then( function ( evt ) {
			return transport.parseResponse( evt );
		}, function ( evt ) {
			return transport.parseResponse( evt );
		} ).then( function ( response ) {
			if ( response.upload && response.upload.filekey ) {
				transport.filekey = response.upload.filekey;
			}

			if ( response.upload && response.upload.result ) {
				switch ( response.upload.result ) {
					case 'Continue':
						// Reset retry counter
						transport.retries = 0;
						// Start uploading next chunk
						return transport.uploadChunk( file, response.upload.offset );
					case 'Success':
						// Just pass the response through.
						return response;
					case 'Poll':
						// Need to retry with checkStatus.
						return transport.retryWithMethod( 'checkStatus' );
				}
			} else {
				// Failed to upload, try again in 3 seconds
				return transport.maybeRetry(
					'on unknown response',
					response,
					'uploadChunk',
					file, offset
				);
			}
		}, function ( response ) {
			return transport.maybeRetry(
				'on error event',
				response,
				'uploadChunk',
				file, offset
			);
		} );
	};

	/**
	 * Handle possible retry event - rejected if maximum retries already fired.
	 *
	 * @param {string} contextMsg
	 * @param {Object} response
	 * @param {string} retryMethod
	 * @param {File} [file]
	 * @param {number} [offset]
	 * @return {jQuery.Promise}
	 */
	mw.FormDataTransport.prototype.maybeRetry = function ( contextMsg, response, retryMethod, file, offset ) {
		this.retries++;

		if ( this.tooManyRetries() ) {
			mw.log.warn( 'Max retries exceeded ' + contextMsg );
			return $.Deferred().reject( response );
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
			params = {};

		if ( this.aborted ) {
			return;
		}

		if ( !this.firstPoll ) {
			this.firstPoll = ( new Date() ).getTime();
		}
		$.each( this.formData, function ( key, value ) {
			params[ key ] = value;
		} );
		params.checkstatus =  true;
		params.filekey =  this.filekey;
		return this.api.post( params )
			.then( function ( response ) {
				if ( response.upload && response.upload.result === 'Poll' ) {
					// If concatenation takes longer than 10 minutes give up
					if ( ( ( new Date() ).getTime() - transport.firstPoll ) > 10 * 60 * 1000 ) {
						return $.Deferred().reject( {
							code: 'server-error',
							info: 'unknown server error'
						} );
					} else {
						if ( response.upload.stage === undefined && window.console ) {
							window.console.log( 'Unable to check file\'s status' );
							return $.Deferred().reject();
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
			} );
	};

	/**
	 * Parse response from the server.
	 *
	 * @param {Event} evt
	 * @return {Object}
	 */
	mw.FormDataTransport.prototype.parseResponse = function ( evt ) {
		var response;

		try {
			response = $.parseJSON( evt.target.responseText );
		} catch ( e ) {
			response = {
				error: {
					code: evt.target.code,
					info: evt.target.responseText
				}
			};
		}

		return response;
	};

}( mediaWiki, jQuery, OO ) );
