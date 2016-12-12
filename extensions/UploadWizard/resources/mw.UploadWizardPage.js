/*
 * This script is run on [[Special:UploadWizard]].
 * Configures and creates an interface for uploading files in multiple steps, hence "wizard".
 *
 * Tries to transform Javascript globals dumped on us by the SpecialUploadWizard.php into a more
 * compact configuration, owned by the UploadWizard created.
 */

// Create UploadWizard
( function ( mw, $ ) {

	function isCompatible() {
		var
			profile = $.client.profile(),
			// Firefox < 7.0 sends an empty string as filename for Blobs in FormData.
			// requests. https://bugzilla.mozilla.org/show_bug.cgi?id=649150
			badFormDataBlobs = profile.name === 'firefox' && profile.versionNumber < 7;

		return !!(
			window.FileReader &&
			window.FormData &&
			window.File &&
			window.File.prototype.slice &&
			!badFormDataBlobs
		);
	}

	mw.UploadWizardPage = function () {

		var uploadWizard,
			config = mw.config.get( 'UploadWizardConfig' );

		// Default configuration value that cannot be removed
		config.maxUploads = config.maxUploads || 10;

		// Remove the initial spinner
		$( '#mwe-first-spinner' ).remove();

		if ( $( '#upload-wizard' ).length === 0 ) {
			mw.log( 'UploadWizard is disabled, nothing to do.' );
			return;
		}

		if ( !isCompatible() ) {
			// Display the same error message as for grade-C browsers
			$( '.mwe-upwiz-unavailable' ).show();
			return;
		}

		uploadWizard = new mw.UploadWizard( config );
		uploadWizard.createInterface( '#upload-wizard' );
	};

	$( document ).ready( function () {
		// show page.
		mw.UploadWizardPage();
	} );

} )( mediaWiki, jQuery );
