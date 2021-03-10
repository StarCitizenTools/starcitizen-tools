( function () {
	/**
	 * Represents an object which send a direct request to the MediaWiki API.
	 * This is used when there is no actual file payload (eg. Flickr import)
	 *
	 * @param {mw.UploadWizardUpload} upload current upload
	 * @param {mw.Api} api
	 */
	mw.ApiUploadPostHandler = function ( upload, api ) {
		mw.ApiUploadHandler.call( this, upload, api );

		this.request = null;
	};

	OO.inheritClass( mw.ApiUploadPostHandler, mw.ApiUploadHandler );

	mw.ApiUploadPostHandler.prototype.abort = function () {
		this.request.abort();
	};

	/**
	 * @return {jQuery.Promise}
	 */
	mw.ApiUploadPostHandler.prototype.submit = function () {
		var tempname = this.upload.getFilename(),
			ext = tempname.split( '.' ).pop();

		// Limit filename length to 240 bytes (limit hardcoded in UploadBase.php).
		if ( tempname.length > 240 ) {
			tempname = tempname.substr( 0, 240 - ext.length - 1 ) + '.' + ext;
		}

		this.upload.ui.setStatus( 'mwe-upwiz-transport-started' );

		this.request = this.api.postWithToken( 'csrf', {
			action: 'upload',
			stash: 1,
			ignorewarnings: 1,
			url: this.upload.file.url,
			filename: tempname
		} );

		return this.request;
	};
}() );
