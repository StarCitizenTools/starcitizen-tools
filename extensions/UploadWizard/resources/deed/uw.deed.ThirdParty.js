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
			undefined,
			this.config.licensing.thirdParty,
			this.uploadCount,
			api
		);
		this.licenseInput.$element.addClass( 'mwe-upwiz-deed-license-groups' );
		this.licenseInput.setDefaultValues();
		this.licenseInputField = new uw.FieldLayout( this.licenseInput, {
			label: mw.message( 'mwe-upwiz-source-thirdparty-cases', this.uploadCount ).text()
		} );

		if ( this.threeDCount > 0 ) {
			this.patentAgreementField = this.getPatentAgreementField( uploads );
		}
	};

	OO.inheritClass( uw.deed.ThirdParty, uw.deed.Abstract );

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
		var $defaultLicense, defaultLicense, defaultLicenseNum, defaultType, collapsible,
			$formFields = $( '<div class="mwe-upwiz-deed-form-internal" />' );

		this.$form = $( '<form>' );

		defaultType = this.config.licensing.defaultType;

		if ( this.uploadCount > 1 ) {
			$formFields.append( $( '<div>' ).msg( 'mwe-upwiz-source-thirdparty-custom-multiple-intro' ) );
		}

		$formFields.append(
			$( '<div class="mwe-upwiz-source-thirdparty-custom-multiple-intro" />' ),
			$( '<div class="mwe-upwiz-thirdparty-fields" />' )
				.append( this.sourceInputField.$element ),
			$( '<div class="mwe-upwiz-thirdparty-fields" />' )
				.append( this.authorInputField.$element ),
			$( '<div class="mwe-upwiz-thirdparty-license" />' )
				.append( this.licenseInputField.$element )
		);

		if ( this.threeDCount > 0 ) {
			$formFields.append( this.patentAgreementField.$element );
		}

		this.$form.append( $formFields );

		$selector.append( this.$form );

		if ( defaultType === 'thirdparty' ) {
			defaultLicense = this.config.licensing.thirdParty.defaults;

			defaultLicenseNum = this.findLicenseRecursively( this.config, defaultLicense );

			if ( defaultLicenseNum ) {
				$defaultLicense = $( '#license' + defaultLicenseNum );
				collapsible = $defaultLicense
					.closest( '.mwe-upwiz-deed-license-group' )
					.data( 'mw-collapsible' );
				if ( collapsible ) {
					collapsible.expand();
				}
				$defaultLicense.prop( 'checked', true );
			}
		}
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

	/**
	 * Runs through the third-party license groups and finds the
	 * relevant ID for that license. Probably really hacky.
	 * TODO do this properly once we build the license links properly
	 *
	 * @param {Object} config
	 * @param {string} license
	 * @return {string|false}
	 */
	uw.deed.ThirdParty.prototype.findLicenseRecursively = function ( config, license ) {
		var val,
			count = 0;

		$.each( this.config.licensing.thirdParty.licenseGroups, function ( i, licenseGroup ) {
			$.each( licenseGroup.licenses, function ( j, licenseCandidate ) {
				if ( licenseCandidate === license ) {
					val = '2_' + count;
					return false;
				}

				count++;
			} );

			if ( val !== undefined ) {
				return false;
			}
		} );

		return val;
	};
}( mediaWiki, mediaWiki.uploadWizard, jQuery, OO ) );
