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

( function ( mw, uw, $, OO ) {

	/**
	 * Upload step controller.
	 *
	 * @class uw.controller.Upload
	 * @extends uw.controller.Step
	 * @constructor
	 * @param {Object} config UploadWizard config object.
	 */
	uw.controller.Upload = function UWControllerUpload( config ) {
		uw.controller.Step.call(
			this,
			new uw.ui.Upload( config )
				.connect( this, {
					retry: 'retry',
					'flickr-ui-init': [ 'emit', 'flickr-ui-init' ]
				} ),
			config
		);

		this.stepName = 'file';
		this.finishState = 'stashed';

		this.queue = new uw.ConcurrentQueue( {
			count: this.config.maxSimultaneousConnections,
			action: this.transitionOne.bind( this )
		} );
		this.queue.on( 'complete', this.showNext.bind( this ) );
	};

	OO.inheritClass( uw.controller.Upload, uw.controller.Step );

	/**
	 * Updates the upload step data when a file is added or removed.
	 */
	uw.controller.Upload.prototype.updateFileCounts = function ( uploads ) {
		var fewerThanMax, haveUploads,
			max = this.config.maxUploads;

		this.ui.hideEndButtons();

		haveUploads = uw.controller.Step.prototype.updateFileCounts.call( this, uploads );

		fewerThanMax = this.uploads.length < max;

		this.ui.updateFileCounts( haveUploads, fewerThanMax );

		if ( !haveUploads ) {
			this.emit( 'no-uploads' );
		}
	};

	/**
	 * Shows an error dialog informing the user that some uploads have been omitted
	 * since they went over the max files limit.
	 *
	 * @param {number} filesUploaded integer - the number of files that have been attempted to upload
	 */
	uw.controller.Upload.prototype.showTooManyFilesWarning = function ( filesUploaded ) {
		this.ui.showTooManyFilesWarning( filesUploaded );
	};

	uw.controller.Upload.prototype.empty = function () {
		this.ui.empty();
	};

	uw.controller.Upload.prototype.moveTo = function () {
		this.updateFileCounts( [] );
		uw.controller.Step.prototype.moveTo.call( this );
		this.progressBar = undefined;
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
	 */
	uw.controller.Upload.prototype.progressBarEmptyOrFinished = function () {
		return !this.progressBar || this.progressBar.finished === true;
	};

	/**
	 * Update success count on the progress bar.
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

		$.each( this.uploads, function ( i, upload ) {
			if ( upload === undefined ) {
				return;
			}

			if ( upload.state === 'error' ) {
				// reset any uploads in error state back to be shiny & new
				upload.state = 'new';
				upload.ui.clearIndicator();
				upload.ui.clearStatus();
				// and queue them
				controller.queueUpload( upload );
			}
		} );

		this.startQueuedUploads();
	};

}( mediaWiki, mediaWiki.uploadWizard, jQuery, OO ) );
