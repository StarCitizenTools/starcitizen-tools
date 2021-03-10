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
	 * The thanks step.
	 *
	 * @class
	 * @extends uw.controller.Step
	 * @param {mw.Api} api
	 * @param {Object} config UploadWizard config object.
	 */
	uw.controller.Thanks = function UWControllerThanks( api, config ) {
		uw.controller.Step.call(
			this,
			new uw.ui.Thanks( config ),
			api,
			config
		);

		this.stepName = 'thanks';
	};

	OO.inheritClass( uw.controller.Thanks, uw.controller.Step );

	uw.controller.Thanks.prototype.load = function ( uploads ) {
		var thanks = this;

		uw.controller.Step.prototype.load.call( this, uploads );

		if ( uploads.length === 0 ) {
			// We got here after the user removed all uploads; just restart from "Upload" step
			uw.eventFlowLogger.logSkippedStep( this.stepName );
			this.moveNext();
			return;
		}

		uploads.forEach( function ( upload ) {
			thanks.ui.addUpload( upload );
		} );
	};

	uw.controller.Thanks.prototype.moveNext = function () {
		// remove all existing uploads before moving on
		mw.UploadWizardUpload.prototype.count = 0;
		this.removeUploads( this.uploads );

		uw.controller.Step.prototype.moveNext.call( this );
	};

	uw.controller.Thanks.prototype.hasData = function () {
		return false;
	};

}( mw.uploadWizard ) );
