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
	 * Tutorial step controller.
	 *
	 * @class
	 * @extends uw.controller.Step
	 * @param {mw.Api} api
	 * @param {Object} config UploadWizard config object.
	 */
	uw.controller.Tutorial = function UWControllerTutorial( api, config ) {
		var controller = this;

		this.skipPreference = Boolean( mw.user.options.get( 'upwiz_skiptutorial' ) );
		this.newSkipPreference = this.skipPreference;
		this.skipped = false;

		uw.controller.Step.call(
			this,
			new uw.ui.Tutorial()
				.on( 'skip-tutorial-click', function ( skipped ) {
					// indicate that the skip preference has changed, so we can
					// alter the preference when we move to another step
					controller.newSkipPreference = skipped;
					if ( skipped ) {
						uw.eventFlowLogger.logTutorialAction( 'skip-check' );
					} else {
						uw.eventFlowLogger.logTutorialAction( 'skip-uncheck' );
					}
				} )

				.on( 'helpdesk-click', function () {
					uw.eventFlowLogger.logTutorialAction( 'helpdesk-click' );
				} ),
			api,
			config
		);

		this.stepName = 'tutorial';

		this.ui.setSelected( this.skipPreference );
	};

	OO.inheritClass( uw.controller.Tutorial, uw.controller.Step );

	/**
	 * Set the skip tutorial user preference via the options API
	 *
	 * @param {boolean} skip
	 */
	uw.controller.Tutorial.prototype.setSkipPreference = function ( skip ) {
		var controller = this,
			allowCloseWindow = mw.confirmCloseWindow( {
				message: function () { return mw.message( 'mwe-upwiz-prevent-close-wait' ).text(); }
			} );

		this.api.postWithToken( 'options', {
			action: 'options',
			change: skip ? 'upwiz_skiptutorial=1' : 'upwiz_skiptutorial'
		} ).done( function () {
			allowCloseWindow.release();
			controller.skipPreference = skip;
		} ).fail( function ( code, err ) {
			mw.notify( err.textStatus );
		} );
	};

	uw.controller.Tutorial.prototype.load = function ( uploads ) {
		// tutorial can be skipped via preference, or config (e.g. campaign config)
		var shouldSkipTutorial = this.skipPreference || ( this.config.tutorial && this.config.tutorial.skip );

		uw.controller.Step.prototype.load.call( this, uploads );

		uw.eventFlowLogger.logTutorialAction( 'load' );

		// we only want to skip the tutorial once - if we come back to it, we
		// don't want it to get auto-skipped again
		if ( !this.skipped && shouldSkipTutorial ) {
			this.skipped = true;
			uw.eventFlowLogger.logSkippedStep( this.stepName );
			this.moveNext();
		}
	};

	uw.controller.Tutorial.prototype.moveNext = function () {
		uw.eventFlowLogger.logTutorialAction( 'continue' );
		uw.controller.Step.prototype.moveNext.call( this );
	};

	uw.controller.Tutorial.prototype.unload = function () {
		if ( this.skipPreference !== this.newSkipPreference ) {
			this.setSkipPreference( this.newSkipPreference );
		}

		uw.controller.Step.prototype.unload.call( this );
	};

	uw.controller.Tutorial.prototype.hasData = function () {
		return false;
	};

}( mw.uploadWizard ) );
