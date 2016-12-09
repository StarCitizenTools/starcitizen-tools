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
	 * Represents the details step in the wizard.
	 *
	 * @class mw.uw.controller.Details
	 * @extends mw.uw.controller.Step
	 * @constructor
	 */
	uw.controller.Details = function UWControllerDetails( config ) {
		uw.controller.Step.call(
			this,
			new uw.ui.Details()
				.connect( this, {
					'start-details': 'startDetails',
					'finalize-details-after-removal': [ 'emit', 'finalize-details-after-removal' ]
				} ),
			config
		);

		this.stepName = 'details';
		this.finishState = 'complete';

		this.queue = new uw.ConcurrentQueue( {
			count: this.config.maxSimultaneousConnections,
			action: this.transitionOne.bind( this )
		} );
	};

	OO.inheritClass( uw.controller.Details, uw.controller.Step );

	/**
	 * Move to this step.
	 *
	 * @param {mw.UploadWizardUpload[]} uploads List of uploads being carried forward.
	 */
	uw.controller.Details.prototype.moveTo = function ( uploads ) {
		var successes = 0;

		$.each( uploads, function ( i, upload ) {
			if ( upload && upload.state !== 'aborted' && upload.state !== 'error' ) {
				successes++;

				if ( successes > 1 ) {
					// Break out of the loop, we have enough.
					return false;
				}
			}
		} );

		$.each( uploads, function ( i, upload ) {
			if ( upload === undefined ) {
				return;
			}

			upload.createDetails();

			if ( upload.fromURL || upload.chosenDeed.name === 'custom' ) {
				upload.details.useCustomDeedChooser();
			}

			// Show toggler to copy selected metadata if there's more than one successful upload
			if ( successes > 1 ) {
				uploads[ 0 ].details.buildAndShowCopyMetadata();
			}
		} );

		uw.controller.Step.prototype.moveTo.call( this, uploads );
	};

	uw.controller.Details.prototype.empty = function () {
		this.ui.empty();
	};

	/**
	 * Start details submit.
	 * TODO move the rest of the logic here from mw.UploadWizard
	 */
	uw.controller.Details.prototype.startDetails = function () {
		var details = this;

		this.valid().done( function ( valid ) {
			if ( valid ) {
				details.ui.hideEndButtons();
				details.submit();
				details.emit( 'start-details' );
			} else {
				details.emit( 'details-error' );
			}
		} );
	};

	/**
	 * Check details for validity.
	 *
	 * @return {jQuery.Promise}
	 */
	uw.controller.Details.prototype.valid = function () {
		var
			detailsController = this,
			validityPromises = [],
			warningValidityPromises = [],
			titles = {};

		$.each( this.uploads, function ( i, upload ) {
			if ( upload === undefined ) {
				return;
			}

			// Update any error/warning messages about all DetailsWidgets
			upload.details.checkValidity();

			warningValidityPromises.push( upload.details.getWarnings().then( function () {
				var i;
				for ( i = 0; i < arguments.length; i++ ) {
					if ( arguments[ i ].length ) {
						// One of the DetailsWidgets has warnings
						return $.Deferred().reject();
					}
				}
			} ) );

			validityPromises.push( upload.details.getErrors().then( function () {
				var i, title, hasErrors = false;

				for ( i = 0; i < arguments.length; i++ ) {
					if ( arguments[ i ].length ) {
						// One of the DetailsWidgets has errors
						hasErrors = true;
					}
				}

				// Seen this title before?
				title = upload.details.getTitle();
				title = title.getName() + '.' + mw.Title.normalizeExtension( title.getExtension() );
				if ( titles[ title ] ) {
					// Don't submit. Instead, set an error in details step.
					upload.details.setDuplicateTitleError();
					hasErrors = true;
				} else {
					titles[ title ] = true;
				}

				if ( hasErrors ) {
					return $.Deferred().reject();
				}
			} ) );
		} );

		return $.when.apply( $, validityPromises ).then(
			function () {
				return $.when.apply( $, warningValidityPromises ).then(
					// All uploads valid, no warnings
					function () {
						return $.Deferred().resolve( true );
					},
					// Valid, but with warnings, ask for confirmation
					function () {
						// Update warning count before dialog
						detailsController.showErrors();
						return detailsController.confirmationDialog();
					}
				);
			},
			function () {
				return $.Deferred().resolve( false );
			}
		);
	};

	uw.controller.Details.prototype.confirmationDialog = function () {
		return OO.ui.confirm( mw.message( 'mwe-upwiz-dialog-warning' ).text(), {
			title: mw.message( 'mwe-upwiz-dialog-title' ).text()
		} );
	};

	uw.controller.Details.prototype.canTransition = function ( upload ) {
		return (
			uw.controller.Step.prototype.canTransition.call( this, upload ) &&
			upload.state === 'details'
		);
	};

	/**
	 * Perform this step's changes on one upload.
	 *
	 * @return {jQuery.Promise}
	 */
	uw.controller.Details.prototype.transitionOne = function ( upload ) {
		return upload.details.submit();
	};

	/**
	 * Perform this step's changes on all uploads.
	 *
	 * @return {jQuery.Promise}
	 */
	uw.controller.Details.prototype.transitionAll = function () {
		var
			deferred = $.Deferred(),
			details = this;

		$.each( this.uploads, function ( i, upload ) {
			if ( upload === undefined ) {
				return;
			}
			if ( details.canTransition( upload ) ) {
				details.queue.addItem( upload );
			}
		} );

		this.queue.on( 'complete', deferred.resolve );
		this.queue.startExecuting();

		return deferred.promise();
	};

	/**
	 * Submit details to the API.
	 *
	 * @return {jQuery.Promise}
	 */
	uw.controller.Details.prototype.submit = function () {
		var details = this;

		$.each( this.uploads, function ( i, upload ) {
			// Skip empty uploads
			if ( upload === undefined ) {
				return;
			}

			// Clear error state
			if ( upload.state === 'error' ) {
				upload.state = 'details';
			}

			// Set details view to have correct title
			upload.details.setVisibleTitle( upload.details.getTitle().getMain() );
		} );

		// Disable edit interface
		this.ui.disableEdits();

		return this.transitionAll().then( function () {
			details.showErrors();

			if ( details.showNext() ) {
				details.moveFrom();
			}
		} );
	};

	/**
	 * Show warnings and errors in the form.
	 * See UI class for more.
	 */
	uw.controller.Details.prototype.showErrors = function () {
		this.ui.showWarnings(); // Scroll to the warning first so that any errors will have precedence
		this.ui.showErrors();
	};

}( mediaWiki, mediaWiki.uploadWizard, jQuery, OO ) );
