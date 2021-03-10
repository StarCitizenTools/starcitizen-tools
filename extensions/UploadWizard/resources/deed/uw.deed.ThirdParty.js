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
	 * Set up the form and deed object for the deed option that says these uploads are the work of a third party.
	 *
	 * @class uw.deed.ThirdParty
	 * @constructor
	 * @param {Object} config The UW config
	 * @param {mw.UploadWizardUpload[]} uploads Array of uploads that this deed refers to
	 * @param {mw.Api} api API object - useful for doing previews
	 */
	uw.deed.ThirdParty = function UWDeedThirdParty( config, uploads, api ) {
		var deed = this;

		uw.deed.Abstract.call( this, 'thirdparty', config );

		this.uploadCount = uploads.length;
		this.threeDCount = uploads.filter( this.needsPatentAgreement.bind( this ) ).length;

		this.sourceInput = new OO.ui.MultilineTextInputWidget( {
			autosize: true,
			classes: [ 'mwe-source' ],
			name: 'source'
		} );
		this.sourceInput.$input.attr( 'id', 'mwe-source-' + this.getInstanceCount() );
		// See uw.DetailsWidget
		this.sourceInput.getErrors = function () {
			var
				errors = [],
				minLength = deed.config.minSourceLength,
				maxLength = deed.config.maxSourceLength,
				text = this.getValue().trim();

			if ( text === '' ) {
				errors.push( mw.message( 'mwe-upwiz-error-blank' ) );
			} else if ( text.length < minLength ) {
				errors.push( mw.message( 'mwe-upwiz-error-too-short', minLength ) );
			} else if ( text.length > maxLength ) {
				errors.push( mw.message( 'mwe-upwiz-error-too-long', maxLength ) );
			}

			return $.Deferred().resolve( errors ).promise();
		};
		// See uw.DetailsWidget
		this.sourceInput.getWarnings = function () {
			return $.Deferred().resolve( [] ).promise();
		};
		this.sourceInputField = new uw.FieldLayout( this.sourceInput, {
			label: mw.message( 'mwe-upwiz-source' ).text(),
			help: mw.message( 'mwe-upwiz-tooltip-source' ).text(),
			required: true
		} );

		this.authorInput = new OO.ui.MultilineTextInputWidget( {
			autosize: true,
			classes: [ 'mwe-author' ],
			name: 'author'
		} );
		this.authorInput.$input.attr( 'id', 'mwe-author-' + this.getInstanceCount() );
		// See uw.DetailsWidget
		this.authorInput.getErrors = function () {
			var
				errors = [],
				minLength = deed.config.minAuthorLength,
				maxLength = deed.config.maxAuthorLength,
				text = this.getValue().trim();

			if ( text === '' ) {
				errors.push( mw.message( 'mwe-upwiz-error-blank' ) );
			} else if ( text.length < minLength ) {
				errors.push( mw.message( 'mwe-upwiz-error-too-short', minLength ) );
			} else if ( text.length > maxLength ) {
				errors.push( mw.message( 'mwe-upwiz-error-too-long', maxLength ) );
			}

			return $.Deferred().resolve( errors ).promise();
		};
		// See uw.DetailsWidget
		this.authorInput.getWarnings = function () {
			return $.Deferred().resolve( [] ).promise();
		};
		this.authorInputField = new uw.FieldLayout( this.authorInput, {
			label: mw.message( 'mwe-upwiz-author' ).text(),
			help: mw.message( 'mwe-upwiz-tooltip-author' ).text(),
			required: true
		} );

		this.licenseInput = new mw.UploadWizardLicenseInput(
			this.config.licensing.thirdParty,
			this.uploadCount,
			api
		);
		this.licenseInput.$element.addClass( 'mwe-upwiz-deed-license-groups' );
		this.licenseInput.setDefaultValues();
		this.licenseInputField = new uw.FieldLayout( this.licenseInput, {
			label: mw.message( 'mwe-upwiz-source-thirdparty-cases', this.uploadCount ).text(),
			required: true
		} );

		if ( this.threeDCount > 0 ) {
			this.patentAgreementField = this.getPatentAgreementField( uploads );
		}
	};

	OO.inheritClass( uw.deed.ThirdParty, uw.deed.Abstract );

	uw.deed.ThirdParty.prototype.unload = function () {
		this.licenseInput.unload();
	};

	/**
	 * @return {uw.FieldLayout[]} Fields that need validation
	 */
	uw.deed.ThirdParty.prototype.getFields = function () {
		var fields = [ this.authorInputField, this.sourceInputField, this.licenseInputField ];
		if ( this.threeDCount > 0 ) {
			fields.push( this.patentAgreementField );
		}
		return fields;
	};

	uw.deed.ThirdParty.prototype.setFormFields = function ( $selector ) {
		var $formFields = $( '<div>' ).addClass( 'mwe-upwiz-deed-form-internal' );

		this.$form = $( '<form>' );

		if ( this.uploadCount > 1 ) {
			$formFields.append( $( '<div>' ).msg( 'mwe-upwiz-source-thirdparty-custom-multiple-intro' ) );
		}

		$formFields.append(
			$( '<div>' ).addClass( 'mwe-upwiz-source-thirdparty-custom-multiple-intro' ),
			$( '<div>' ).addClass( 'mwe-upwiz-thirdparty-fields' )
				.append( this.sourceInputField.$element ),
			$( '<div>' ).addClass( 'mwe-upwiz-thirdparty-fields' )
				.append( this.authorInputField.$element ),
			$( '<div>' ).addClass( 'mwe-upwiz-thirdparty-license' )
				.append( this.licenseInputField.$element )
		);

		if ( this.threeDCount > 0 ) {
			$formFields.append( this.patentAgreementField.$element );
		}

		this.$form.append( $formFields );

		$selector.append( this.$form );
	};

	/**
	 * @inheritdoc
	 */
	uw.deed.Abstract.prototype.getSourceWikiText = function () {
		return this.sourceInput.getValue();
	};

	/**
	 * @inheritdoc
	 */
	uw.deed.Abstract.prototype.getAuthorWikiText = function () {
		return this.authorInput.getValue();
	};

	/**
	 * @inheritdoc
	 */
	uw.deed.Abstract.prototype.getLicenseWikiText = function ( upload ) {
		var wikitext = this.licenseInput.getWikiText();

		if ( this.needsPatentAgreement( upload ) ) {
			wikitext += '\n{{' + this.config.patents.template + '}}';
		}

		return wikitext;
	};

	/**
	 * @return {Object}
	 */
	uw.deed.ThirdParty.prototype.getSerialized = function () {
		return $.extend( uw.deed.Abstract.prototype.getSerialized.call( this ), {
			source: this.sourceInput.getValue(),
			author: this.authorInput.getValue(),
			license: this.licenseInput.getSerialized()
		} );
	};

	/**
	 * @param {Object} serialized
	 */
	uw.deed.ThirdParty.prototype.setSerialized = function ( serialized ) {
		uw.deed.Abstract.prototype.setSerialized.call( this, serialized );

		if ( serialized.source ) {
			this.sourceInput.setValue( serialized.source );
		}
		if ( serialized.author ) {
			this.authorInput.setValue( serialized.author );
		}
		if ( serialized.license ) {
			this.licenseInput.setSerialized( serialized.license );
		}
	};
}( mw.uploadWizard ) );
