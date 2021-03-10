( function () {
	/**
	 * Represents an object which configures an html5 FormData object to upload.
	 * Large files are uploaded in chunks.
	 *
	 * @param {mw.UploadWizardUpload} upload
	 * @param {mw.Api} api
	 */
	mw.ApiUploadFormDataHandler = function ( upload, api ) {
		mw.ApiUploadHandler.call( this, upload, api );

		this.formData = {
			action: 'upload',
			stash: 1,
			format: 'json'
		};

		this.transport = new mw.FormDataTransport(
			this.api,
			this.formData
		).on( 'update-stage', function ( stage ) {
			upload.ui.setStatus( 'mwe-upwiz-' + stage );
		} );
	};

	OO.inheritClass( mw.ApiUploadFormDataHandler, mw.ApiUploadHandler );

	mw.ApiUploadFormDataHandler.prototype.abort = function () {
		this.transport.abort();
	};

	/**
	 * @return {jQuery.Promise}
	 */
	mw.ApiUploadFormDataHandler.prototype.submit = function () {
		var handler = this;

		return this.configureEditToken().then( function () {
			handler.beginTime = ( new Date() ).getTime();
			handler.upload.ui.setStatus( 'mwe-upwiz-transport-started' );
			handler.upload.ui.showTransportProgress();

			return handler.transport.upload( handler.upload.file, handler.upload.title.getMainText() )
				.progress( function ( fraction ) {
					if ( handler.upload.state === 'aborted' ) {
						handler.abort();
						return;
					}

					if ( fraction !== null ) {
						handler.upload.setTransportProgress( fraction );
					}
				} );
		} );
	};

	/**
	 * Obtain a fresh edit token.
	 * If successful, store token and call a callback.
	 *
	 * @return {jQuery.Promise}
	 */
	mw.ApiUploadFormDataHandler.prototype.configureEditToken = function () {
		var handler = this;

		return this.api.getEditToken().then( function ( token ) {
			handler.formData.token = token;
		} );
	};
}() );
