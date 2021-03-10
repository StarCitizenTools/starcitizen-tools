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
	 * @class uw.deed.Custom
	 * @constructor
	 */
	uw.deed.Custom = function UWDeedCustom( config, upload ) {
		uw.deed.Abstract.call( this, 'custom', config );

		this.upload = upload;
	};

	OO.inheritClass( uw.deed.Custom, uw.deed.Abstract );

	/**
	 * @inheritdoc
	 */
	uw.deed.Custom.prototype.getSourceWikiText = function () {
		if ( typeof this.upload.file.sourceURL !== 'undefined' ) {
			return this.upload.file.sourceURL;
		} else {
			return this.upload.file.url;
		}
	};

	/**
	 * @inheritdoc
	 */
	uw.deed.Custom.prototype.getAuthorWikiText = function () {
		return this.upload.file.author;
	};

	/**
	 * @inheritdoc
	 */
	uw.deed.Custom.prototype.getLicenseWikiText = function () {
		return this.upload.file.licenseValue;
	};
}( mw.uploadWizard ) );
