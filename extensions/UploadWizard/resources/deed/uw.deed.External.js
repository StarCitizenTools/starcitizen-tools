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
	 * @param {Object} config The UW config
	 * @param {mw.UploadWizardUpload} upload Uploads that this deed refers to
	 * @class uw.deed.External
	 * @constructor
	 */
	uw.deed.External = function UWDeedExternal( config, upload ) {
		uw.deed.Custom.call( this, config, upload );

		this.licenseInput = new mw.UploadWizardLicenseInput(
			config.licensing.thirdParty,
			1,
			upload.api
		);
		this.licenseInput.$element.addClass( 'mwe-upwiz-External-deed' );
		this.licenseInputField = new uw.FieldLayout( this.licenseInput );
		this.licenseInput.setDefaultValues();
	};

	OO.inheritClass( uw.deed.External, uw.deed.Custom );

	uw.deed.External.prototype.unload = function () {
		this.licenseInput.unload();
	};

	/**
	 * @return {uw.FieldLayout[]} Fields that need validation
	 */
	uw.deed.External.prototype.getFields = function () {
		return [ this.licenseInputField ];
	};

	/**
	 * @inheritdoc
	 */
	uw.deed.External.prototype.getLicenseWikiText = function () {
		if ( this.upload.file.licenseValue ) {
			return this.upload.file.licenseValue + this.licenseInput.getWikiText();
		} else {
			return this.licenseInput.getWikiText();
		}
	};

	/**
	 * @return {Object}
	 */
	uw.deed.External.prototype.getSerialized = function () {
		return $.extend( uw.deed.Custom.prototype.getSerialized.call( this ), {
			license: this.licenseInput.getSerialized()
		} );
	};

	/**
	 * @param {Object} serialized
	 */
	uw.deed.External.prototype.setSerialized = function ( serialized ) {
		uw.deed.Custom.prototype.setSerialized.call( this, serialized );

		if ( serialized.license ) {
			this.licenseInput.setSerialized( serialized.license );
		}
	};
}( mw.uploadWizard ) );
