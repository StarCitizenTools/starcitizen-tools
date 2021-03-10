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
	 * Represents the UI for the wizard.
	 *
	 * @class uw.ui.Wizard
	 * @mixins OO.EventEmitter
	 * @constructor
	 * @param {string} selector Where to put all of the wizard interface.
	 */
	uw.ui.Wizard = function UWUIWizard( selector ) {
		OO.EventEmitter.call( this );

		this.$div = $( '<div>' )
			.attr( 'id', 'mwe-upwiz-content' );

		$( selector ).append(
			this.$div,
			$( '<div>' ).addClass( 'mwe-upwiz-clearing' )
		);

		this.initHeader( mw.UploadWizard.config );
	};

	OO.mixinClass( uw.ui.Wizard, OO.EventEmitter );

	/**
	 * Initializes the static stuff above the wizard.
	 *
	 * @param {Object} config
	 */
	uw.ui.Wizard.prototype.initHeader = function ( config ) {
		var feedbackLink,
			// eslint-disable-next-line no-jquery/no-global-selector
			$contentSub = $( '#contentSub' );

		if ( config.feedbackLink ) {
			// Preferred. Send user to bug tracker (defaults to UW's own
			// Phabricator project)
			feedbackLink = config.feedbackLink;
		} else if ( config.feedbackPage ) {
			// Backwards compatibility...send user to talk page to give
			// feedback.
			feedbackLink = mw.util.getUrl( config.feedbackPage );
		}

		if ( feedbackLink ) {
			this.$feedbackLink = $( '<a>' )
				.addClass( 'contentSubLink' )
				.prop( 'href', feedbackLink )
				.msg( 'mwe-upwiz-feedback-prompt' );

			$contentSub.append( this.$feedbackLink );
		}

		if ( config.alternativeUploadToolsPage ) {
			this.$alternativeUploads = $( '<a>' )
				.addClass( 'contentSubLink' )
				.prop( 'href', new mw.Title( config.alternativeUploadToolsPage ).getUrl() )
				.msg( 'mwe-upwiz-subhead-alternatives' );

			$contentSub.append( this.$alternativeUploads );
		}

		if ( config.altUploadForm ) {
			this.initAltUploadForm( config.altUploadForm );
		}

		// Separate each link in the header with a dot.
		// eslint-disable-next-line no-jquery/no-sizzle
		$contentSub.find( '.contentSubLink:not(:last)' ).after( '&nbsp;&middot;&nbsp;' );
	};

	/**
	 * Initializes a link to the alternate upload form, if any.
	 *
	 * @param {Object|string} configAltUploadForm A link or map of languages to links, pointing at an alternate form.
	 */
	uw.ui.Wizard.prototype.initAltUploadForm = function ( configAltUploadForm ) {
		var altUploadForm, userLanguage, title;

		if ( typeof configAltUploadForm === 'object' ) {
			userLanguage = mw.config.get( 'wgUserLanguage' );

			if ( configAltUploadForm[ userLanguage ] ) {
				altUploadForm = configAltUploadForm[ userLanguage ];
			} else if ( configAltUploadForm.default ) {
				altUploadForm = configAltUploadForm.default;
			}
		} else {
			altUploadForm = configAltUploadForm;
		}

		// altUploadForm is expected to be a page title like 'Commons:Upload', so convert to URL
		if ( typeof altUploadForm === 'string' && altUploadForm.length > 0 ) {
			try {
				title = new mw.Title( altUploadForm );

				$( '<a>' )
					.msg( 'mwe-upwiz-subhead-alt-upload' )
					.addClass( 'contentSubLink' )
					.attr( 'href', title.getUrl() )
					.appendTo( '#contentSub' );
			} catch ( e ) {
				// page was empty, or impossible on this wiki (missing namespace or some other issue). Give up.
			}
		}
	};

	/**
	 * Initializes the arrow steps above the wizard.
	 *
	 * @param {Object.<uw.controller.Step>} steps
	 */
	uw.ui.Wizard.prototype.initialiseSteps = function ( steps ) {
		var $steps = $( '<ul>' )
				.attr( 'id', 'mwe-upwiz-steps' )
				.addClass( 'ui-helper-clearfix' )
				.insertBefore( '#mwe-upwiz-content' ),
			sortedSteps = this.sortSteps( Object.keys( steps ).map( function ( key ) {
				return steps[ key ];
			} ) );

		sortedSteps.forEach( function ( step ) {
			var $arrow = $( '<li>' )
				.attr( 'id', 'mwe-upwiz-step-' + step.stepName )
				.append(
					$( '<div>' ).text( mw.message( 'mwe-upwiz-step-' + step.stepName ).text() )
				);
			$steps.append( $arrow );

			// once a (new) step loads, highlight it
			step.on( 'load', function ( $arrow ) {
				$steps.arrowStepsHighlight( $arrow );
			}.bind( step, $arrow ) );
		} );

		$steps.arrowSteps();
	};

	/**
	 * Sorts the steps in the order they'll actually be used.
	 *
	 * @param {uw.controller.Step[]} steps
	 * @return {uw.controller.Step[]}
	 */
	uw.ui.Wizard.prototype.sortSteps = function ( steps ) {
		var first = steps[ 0 ],
			sorted,
			i;

		// find the very first step (element at position [0] is not guaranteed
		// to be first (it was just added first)
		// The actual internal relationship is defined in previousStep & nextStep
		// properties ...)
		while ( first.previousStep !== null ) {
			first = first.previousStep;
		}

		sorted = [ first ];
		for ( i = 1; i < steps.length; i++ ) {
			sorted.push( sorted[ i - 1 ].nextStep );
		}

		return sorted;
	};

}( mw.uploadWizard ) );
