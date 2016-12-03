( function ( mw, uw ) {
	/**
	 * Represents an object which send a direct request to the MediaWiki API.
	 * This is used when there is no actual file payload (eg. Flickr import)
	 *
	 * @param {mw.UploadWizardUpload} upload current upload
	 * @param {mw.Api} api
	 */
	mw.ApiUploadPostHandler = function ( upload, api ) {
		this.upload = upload;
		this.api = api;
	};

	mw.ApiUploadPostHandler.prototype = {
		start: function () {
			var handler = this;

			this.beginTime = ( new Date() ).getTime();
			this.upload.ui.setStatus( 'mwe-upwiz-transport-started' );

			return this.api.postWithToken( 'csrf', {
				action: 'upload',
				stash: 1,
				ignorewarnings: 1,
				url: this.upload.providedFile.url,
				filename: this.beginTime.toString() + this.upload.filename
			} )
			.fail( function ( code, info, result ) {
				uw.eventFlowLogger.logApiError( 'file', result );
				handler.upload.setError( code, info );
			} )
			.done( function ( result ) {
				if ( !result || result.error || ( result.upload && result.upload.warnings ) ) {
					uw.eventFlowLogger.logApiError( 'file', result );
				}
				handler.upload.setTransported( result );
			} );
		}
	};
}( mediaWiki, mediaWiki.uploadWizard ) );
