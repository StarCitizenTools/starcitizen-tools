/*
 * This file is part of the MediaWiki extension UploadWizard.
 *
 * UploadWizard is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * UploadWizard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with UploadWizard.  If not, see <http://www.gnu.org/licenses/>.
 */

( function ( uw ) {

	/**
	 * Upload step controller.
	 *
	 * @class
	 * @extends uw.controller.Step
	 * @param {mw.Api} api
	 * @param {Object} config UploadWizard config object.
	 */
	uw.controller.Upload = function UWControllerUpload( api, config ) {
		var step = this;

		uw.controller.Step.call(
			this,
			new uw.ui.Upload( config )
				.connect( this, {
					retry: 'retry'
				} ),
			api,
			config
		);

		this.stepName = 'file';
		this.finishState = 'stashed';

		this.queue = new uw.ConcurrentQueue( {
			count: this.config.maxSimultaneousConnections,
			action: this.transitionOne.bind( this )
		} );
		this.queue.on( 'complete', this.showNext.bind( this ) );

		this.ui.on( 'files-added', function ( files ) {
			var totalFiles = files.length + step.uploads.length,
				tooManyFiles = totalFiles > step.config.maxUploads;

			if ( tooManyFiles ) {
				step.ui.showTooManyFilesError( totalFiles );
			} else {
				step.addFiles( files );
			}
		} );
	};

	OO.inheritClass( uw.controller.Upload, uw.controller.Step );

	/**
	 * Updates the upload step data when a file is added or removed.
	 */
	uw.controller.Upload.prototype.updateFileCounts = function () {
		var fewerThanMax, haveUploads,
			max = this.config.maxUploads;

		haveUploads = this.uploads.length > 0;
		fewerThanMax = this.uploads.length < max;

		this.updateProgressBarCount( this.uploads.length );
		this.ui.updateFileCounts( haveUploads, fewerThanMax );
	};

	uw.controller.Upload.prototype.load = function ( uploads ) {
		var controller = this;

		uw.controller.Step.prototype.load.call( this, uploads );
		this.updateFileCounts();
		this.startProgressBar();

		// make sure queue is empty before starting this step
		this.queue.abortExecuting();

		if ( uploads.length > 0 ) {
			/*
			 * If we have uploads already, we'll want to to update the "next"
			 * buttons accordingly. showNext() does that, but relies on upload
			 * state being set correctly.
			 * Since every step overwrites the upload state, we'll need to reset
			 * it to reflect the correct upload success state.
			 * If other files are to be added, the showNext() callback will deal
			 * with new uploads, and still understand the existing files that
			 * we've just reset the state for.
			 */
			uploads.forEach( function ( upload ) {
				upload.state = upload.fileKey === undefined ? 'error' : controller.finishState;
			} );

			this.showNext();
		}
	};

	uw.controller.Upload.prototype.moveNext = function () {
		this.removeErrorUploads();

		uw.controller.Step.prototype.moveNext.call( this );
	};

	/**
	 * Starts the upload progress bar.
	 */
	uw.controller.Upload.prototype.startProgressBar = function () {
		this.ui.showProgressBar();
		this.progressBar = new mw.GroupProgressBar( this.ui.$progress,
			this.uploads,
			[ 'stashed' ],
			[ 'error' ],
			'transportProgress',
			'transportWeight' );
		this.progressBar.start();
	};

	/**
	 * Starts progress bar if there's not an existing one.
	 */
	uw.controller.Upload.prototype.maybeStartProgressBar = function () {
		if ( this.progressBarEmptyOrFinished() ) {
			this.startProgressBar();
		}
	};

	/**
	 * Check if there is a vacancy for a new progress bar.
	 *
	 * @return {boolean}
	 */
	uw.controller.Upload.prototype.progressBarEmptyOrFinished = function () {
		return !this.progressBar || this.progressBar.finished === true;
	};

	/**
	 * Update success count on the progress bar.
	 *
	 * @param {number} okCount
	 */
	uw.controller.Upload.prototype.updateProgressBarCount = function ( okCount ) {
		if ( this.progressBar ) {
			this.progressBar.showCount( okCount );
		}
	};

	uw.controller.Upload.prototype.canTransition = function ( upload ) {
		return (
			uw.controller.Step.prototype.canTransition.call( this, upload ) &&
			upload.state === 'new'
		);
	};

	/**
	 * Perform this step's changes on one upload.
	 *
	 * @param {mw.UploadWizardUpload} upload
	 * @return {jQuery.Promise}
	 */
	uw.controller.Upload.prototype.transitionOne = function ( upload ) {
		var promise = upload.start();
		this.maybeStartProgressBar();
		return promise;
	};

	/**
	 * Queue an upload object to be uploaded.
	 *
	 * @param {mw.UploadWizardUpload} upload
	 */
	uw.controller.Upload.prototype.queueUpload = function ( upload ) {
		if ( this.canTransition( upload ) ) {
			this.queue.addItem( upload );
		}
	};

	/**
	 * Kick off the upload processes.
	 */
	uw.controller.Upload.prototype.startQueuedUploads = function () {
		this.queue.startExecuting();
	};

	uw.controller.Upload.prototype.retry = function () {
		var controller = this;
		uw.eventFlowLogger.logEvent( 'retry-uploads-button-clicked' );

		this.uploads.forEach( function ( upload ) {
			if ( upload.state === 'error' ) {
				// reset any uploads in error state back to be shiny & new
				upload.state = 'new';
				upload.ui.clearStatus();
				// and queue them
				controller.queueUpload( upload );
			}
		} );

		this.startQueuedUploads();
	};

	/**
	 * Create the upload interface, a handler to transport it to the server, and UI for the upload
	 * itself; and immediately fill it with a file and add it to the list of uploads.
	 *
	 * @param {File} file
	 * @return {mw.UploadWizardUpload|boolean} The new upload, or false if it can't be added
	 */
	uw.controller.Upload.prototype.addFile = function ( file ) {
		var upload;

		if ( this.uploads.length >= this.config.maxUploads ) {
			return false;
		}

		upload = new mw.UploadWizardUpload( this, file );

		if ( !this.validateFile( upload ) ) {
			return false;
		}

		upload.fileChangedOk();

		// attach controller-specific event handlers (they're automatically
		// bound on load already, but we've only just added these files...)
		this.bindUploadHandlers( upload );

		this.setUploadFilled( upload );

		return upload;
	};

	/**
	 * Do everything that needs to be done to start uploading a file. Calls #addFile, then appends
	 * each mw.UploadWizardUploadInterface to the DOM and queues thumbnails to be generated.
	 *
	 * @param {FileList} files
	 */
	uw.controller.Upload.prototype.addFiles = function ( files ) {
		var
			uploadObj,
			i,
			file,
			uploadObjs = [],
			controller = this;

		for ( i = 0; i < files.length; i++ ) {
			file = files[ i ];
			uploadObj = controller.addFile( file );
			if ( uploadObj ) {
				uploadObjs.push( uploadObj );
			}
		}

		this.ui.displayUploads( uploadObjs );
		this.updateFileCounts();

		uw.eventFlowLogger.logUploadEvent( 'uploads-added', { quantity: files.length } );
	};

	/**
	 * Remove an upload from our array of uploads, and the HTML UI
	 * We can remove the HTML UI directly, as jquery will just get the parent.
	 * We need to grep through the array of uploads, since we don't know the current index.
	 * We need to update file counts for obvious reasons.
	 *
	 * @param {mw.UploadWizardUpload} upload
	 */
	uw.controller.Upload.prototype.removeUpload = function ( upload ) {
		uw.controller.Step.prototype.removeUpload.call( this, upload );

		this.queue.removeItem( upload );

		this.updateFileCounts();

		// check all uploads, if they're complete, show the next button
		this.showNext();
	};

	/**
	 * When an upload is filled with a real file, accept it in the list of uploads
	 * and set up some other interfaces
	 *
	 * @param {mw.UploadWizardUpload} upload
	 */
	uw.controller.Upload.prototype.setUploadFilled = function ( upload ) {
		this.addUpload( upload );
		// Start uploads now, no reason to wait--leave the remove button alone
		this.queueUpload( upload );
		this.startQueuedUploads();
	};

	/**
	 * Checks for file validity.
	 *
	 * @param {mw.UploadWizardUpload} upload
	 * @return {boolean} Error in [code, info] format, or empty [] for no errors
	 */
	uw.controller.Upload.prototype.validateFile = function ( upload ) {
		var extension,
			i,
			actualMaxSize = mw.UploadWizard.config.maxMwUploadSize,

			// Check if filename is acceptable
			// TODO sanitize filename
			filename = upload.getFilename(),
			basename = upload.getBasename();

		// check to see if this file has already been selected for upload
		for ( i = 0; i < this.uploads.length; i++ ) {
			if ( upload !== this.uploads[ i ] && filename === this.uploads[ i ].getFilename() ) {
				this.ui.showDuplicateError( filename, basename );
				return false;
			}
		}

		// check if the filename is valid
		upload.setTitle( basename );
		if ( !upload.title ) {
			if ( basename.indexOf( '.' ) === -1 ) {
				this.ui.showMissingExtensionError( filename );
				return false;
			} else {
				this.ui.showUnparseableFilenameError( filename );
				return false;
			}
		}

		// check if extension is acceptable
		extension = upload.title.getExtension();
		if ( !extension ) {
			this.ui.showMissingExtensionError( filename );
			return false;
		}

		if (
			mw.UploadWizard.config.fileExtensions !== null &&
			mw.UploadWizard.config.fileExtensions.indexOf( extension.toLowerCase() ) === -1
		) {
			this.ui.showBadExtensionError( filename, extension );
			return false;
		}

		// make sure the file isn't too large
		// TODO need a way to find the size of the Flickr image
		if ( upload.file.size ) {
			upload.transportWeight = upload.file.size;
			if ( upload.transportWeight > actualMaxSize ) {
				this.ui.showFileTooLargeError( actualMaxSize, upload.transportWeight );
				return false;
			}
		}

		return true;
	};

}( mw.uploadWizard ) );
