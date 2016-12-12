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
	uw.controller.Tutorial = function UWControllerTutorial( api, config ) {
		var controller = this;
		this.shouldSkipTutorial = false;
		this.api = api;

		uw.controller.Step.call(
			this,
			new uw.ui.Tutorial()
				.on( 'skip-tutorial-click', function ( skipped ) {
					controller.shouldSkipTutorial = skipped;
					if ( skipped ) {
						( new mw.UploadWizardTutorialEvent( 'skip-check' ) ).dispatch();
					} else {
						( new mw.UploadWizardTutorialEvent( 'skip-uncheck' ) ).dispatch();
					}
				} )

				.on( 'helpdesk-click', function () {
					( new mw.UploadWizardTutorialEvent( 'helpdesk-click' ) ).dispatch();
				} ),
			config
		);

		this.stepName = 'tutorial';
	};

	OO.inheritClass( uw.controller.Tutorial, uw.controller.Step );

	/**
	 * Set the skip tutorial user preference via the options API
	 */
	uw.controller.Tutorial.prototype.setSkipPreference = function () {
		var api = this.api,
			allowCloseWindow = mw.confirmCloseWindow( {
				message: function () { return mw.message( 'mwe-upwiz-prevent-close-wait' ).text(); }
			} );

		api.postWithToken( 'options', {
			action: 'options',
			change: 'upwiz_skiptutorial=1'
		} ).done( function () {
			allowCloseWindow.release();
		} ).fail( function ( code, err ) {
			mw.notify( err.textStatus );
		} );
	};

	uw.controller.Tutorial.prototype.moveTo = function () {
		var tconf = mw.config.get( 'UploadWizardConfig' ).tutorial;

		if (
			mw.user.options.get( 'upwiz_skiptutorial' ) ||
			( tconf && tconf.skip )
		) {
			this.skip();
		} else {
			uw.controller.Step.prototype.moveTo.call( this );
		}
	};

	uw.controller.Tutorial.prototype.moveFrom = function () {
		( new mw.UploadWizardTutorialEvent( 'continue' ) ).dispatch();

		// if the skip checkbox is checked, set the skip user preference
		if ( this.shouldSkipTutorial ) {
			this.setSkipPreference();
		}

		uw.controller.Step.prototype.moveFrom.call( this );
	};

	uw.controller.Tutorial.prototype.isComplete = function () {
		return true;
	};

}( mediaWiki, mediaWiki.uploadWizard, jQuery, OO ) );
