( function ( mw ) {
	/**
	 * Represents an object which configures a form to upload its files via an firefogg talking to the MediaWiki API.
	 *
	 * @param {mw.UploadWizardUploadInterface} upload
	 */

	mw.FirefoggHandler = function ( upload, api ) {
		this.upload = upload;
		this.api = api;
	};

	mw.FirefoggHandler.prototype = {
		// The transport object
		transport: null, // lazy init

		// Setup local pointer to firefogg instance
		getFogg: function () {
			if ( !this.fogg ) {
				this.fogg = new window.Firefogg();
			}
			return this.fogg;
		},

		getTransport: function () {
			var file, transport,
				upload = this.upload;

			if ( upload.file ) {
				file = upload.file;
			} else if ( upload.providedFile ) {
				file = upload.providedFile;
			} else {
				mw.log.warn( 'Firefogg tried to upload a file but was unable to find one.' );
				return false;
			}

			if ( !this.transport ) {
				transport = new mw.FirefoggTransport(
					file,
					this.api,
					this.getFogg()
				);

				this.transport = transport;
			}

			return this.transport;
		},

		/**
		 * If chunks are disabled transcode then upload else
		 * upload and transcode at the same time
		 *
		 * @return {jQuery.Promise}
		 */
		start: function () {
			var title,
				transport = this.getTransport(),
				handler = this,
				upload = this.upload;

			mw.log( 'mw.FirefoggHandler::start> Upload start!' );

			// pass file to Firefogg
			if ( this.upload.file ) {
				this.getFogg().setInput( this.upload.file );

				// This is required to get the right requestedTitle in UploadWizardUpload
				title = this.getTransport().getFileName();

				this.upload.setTitle( title );
			}
			this.beginTime = ( new Date() ).getTime();
			this.upload.ui.setStatus( 'mwe-upwiz-transport-started' );
			this.upload.ui.showTransportProgress();
			return this.getTransport().upload()
				.progress( function ( data ) {
					if ( data === 'encoding' ) {
						upload.ui.setStatus( 'mwe-upwiz-encoding' );
					} else if ( upload.state === 'aborted' ) {
						handler.getFogg().cancel();
					} else {
						upload.setTransportProgress( data.progress );
						upload.ui.setStatus( 'mwe-upwiz-encoding' );
					}
				} ).then( function ( file ) {
					// Encoding finished, now transport.
					upload.ui.setStatus( 'mwe-upwiz-uploading' );
					upload.file = file;
					transport.uploadHandler = new mw.ApiUploadFormDataHandler( upload, handler.api );
					return transport.uploadHandler.start();
				}, function ( result ) {
					mw.log( 'FirefoggTransport::getTransport> Transport done ' + JSON.stringify( result ) );
					upload.setTransported( result );
				} );
		}
	};
}( mediaWiki ) );
