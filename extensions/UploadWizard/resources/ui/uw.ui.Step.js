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
	 * Represents a generic UI for a step.
	 *
	 * @class uw.ui.Step
	 * @mixins OO.EventEmitter
	 * @constructor
	 * @param {string} name The name of this step
	 */
	uw.ui.Step = function UWUIStep( name ) {
		OO.EventEmitter.call( this );

		this.name = name;

		this.$buttons = $( '<div>' ).addClass( 'mwe-upwiz-buttons' );

		this.$div = $( '<div>' )
			.attr( 'id', 'mwe-upwiz-stepdiv-' + this.name )
			.addClass( 'mwe-upwiz-stepdiv' )
			.hide();

		// eslint-disable-next-line no-jquery/no-global-selector
		$( '#mwe-upwiz-content' ).append( this.$div );

		// this will make sure that buttons will only be added if they've been
		// set in the controller, otherwise there's nowhere to go...
		this.nextButtonPromise = $.Deferred();
		this.previousButtonPromise = $.Deferred();
	};

	OO.mixinClass( uw.ui.Step, OO.EventEmitter );

	/**
	 * Initialize this step.
	 *
	 * @param {mw.UploadWizardUpload[]} uploads
	 */
	uw.ui.Step.prototype.load = function ( uploads ) {
		// eslint-disable-next-line no-jquery/no-global-selector
		var offset = $( 'h1' ).first().offset();

		this.movedFrom = false;

		this.uploads = uploads;
		this.$div.append( this.$buttons ).show();

		// eslint-disable-next-line no-jquery/no-global-selector
		$( 'html, body' ).animate( {
			scrollTop: offset.top,
			scrollLeft: offset.left
		}, 'slow' );
	};

	/**
	 * Cleanup this step.
	 */
	uw.ui.Step.prototype.unload = function () {
		this.movedFrom = true;

		this.$div.children().detach();
	};

	uw.ui.Step.prototype.enableNextButton = function () {
		this.nextButtonPromise.resolve();
	};

	uw.ui.Step.prototype.enablePreviousButton = function () {
		this.previousButtonPromise.resolve();
	};

	/**
	 * Add a 'next' button to the step's button container
	 */
	uw.ui.Step.prototype.addNextButton = function () {
		var ui = this;

		this.nextButton = new OO.ui.ButtonWidget( {
			classes: [ 'mwe-upwiz-button-next' ],
			label: mw.message( 'mwe-upwiz-next' ).text(),
			flags: [ 'progressive', 'primary' ]
		} ).on( 'click', function () {
			ui.emit( 'next-step' );
		} );

		this.nextButtonPromise.done( function () {
			ui.$buttons.append( ui.nextButton.$element );
		} );
	};

	/**
	 * Add a 'previous' button to the step's button container
	 */
	uw.ui.Step.prototype.addPreviousButton = function () {
		var ui = this;

		this.previousButton = new OO.ui.ButtonWidget( {
			classes: [ 'mwe-upwiz-button-previous' ],
			label: mw.message( 'mwe-upwiz-previous' ).text()
		} ).on( 'click', function () {
			ui.emit( 'previous-step' );
		} );

		this.previousButtonPromise.done( function () {
			ui.$buttons.append( ui.previousButton.$element );
		} );
	};
}( mw.uploadWizard ) );
