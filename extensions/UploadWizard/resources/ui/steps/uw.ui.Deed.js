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

( function ( mw, $, uw, OO ) {
	/**
	 * Represents the UI for the wizard's Deed step.
	 *
	 * @class uw.ui.Deed
	 * @extends uw.ui.Step
	 * @constructor
	 */
	uw.ui.Deed = function UWUIDeed() {
		uw.ui.Step.call(
			this,
			'deeds'
		);

		this.$div.prepend(
			$( '<div>' )
				.attr( 'id', 'mwe-upwiz-deeds-thumbnails' )
				.addClass( 'ui-helper-clearfix' ),
			$( '<div>' )
				.attr( 'id', 'mwe-upwiz-deeds' )
				.addClass( 'ui-helper-clearfix' ),
			$( '<div>' )
				.attr( 'id', 'mwe-upwiz-deeds-custom' )
				.addClass( 'ui-helper-clearfix' )
		);

		this.addNextButton();

		this.nextButton.$element.hide();
	};

	OO.inheritClass( uw.ui.Deed, uw.ui.Step );
}( mediaWiki, jQuery, mediaWiki.uploadWizard, OO ) );
