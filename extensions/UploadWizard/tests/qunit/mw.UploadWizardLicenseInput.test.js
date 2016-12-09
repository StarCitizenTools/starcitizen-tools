( function ( mw, $ ) {
	QUnit.module( 'ext.uploadWizardLicenseInput', QUnit.newMwEnvironment( {
		setup: function () {
			mw.UploadWizard.config = {
				licenses: {
					'cc-by-sa-3.0': {
						msg: 'mwe-upwiz-license-cc-by-sa-3.0',
						icons: [ 'cc-by', 'cc-sa' ],
						url: '//creativecommons.org/licenses/by-sa/3.0/',
						languageCodePrefix: 'deed.'
					}
				}
			};
		}
	} ) );

	QUnit.test( 'Smoke test', 1, function ( assert ) {
		var values,
			config = { type: 'or', licenses: [] },
			uwLicenseInput;

		uwLicenseInput = new mw.UploadWizardLicenseInput( values, config );
		$( '#qunit-fixture' ).append( uwLicenseInput.$element );
		assert.ok( uwLicenseInput, 'LicenseInput object created !' );
	} );

	QUnit.test( 'createInputs()', 2, function ( assert ) {
		var values,
			config = { type: 'or', licenses: [ 'cc-by-sa-3.0' ] },
			uwLicenseInput;

		uwLicenseInput = new mw.UploadWizardLicenseInput( values, config );
		$( '#qunit-fixture' ).append( uwLicenseInput.$element );

		// Check radio button is there
		assert.strictEqual( $( '.mwe-upwiz-copyright-info-radio' ).length, 1, 'Radio button created.' );

		// Check label is there
		assert.strictEqual( $( '.mwe-upwiz-copyright-info' ).length, 1, 'Label created.' );
	} );

	QUnit.test( 'createGroupedInputs()', 3, function ( assert ) {
		var values,
			config = {
				type: 'or',
				licenseGroups: [
					{
						head: 'mwe-upwiz-license-cc-head',
						subhead: 'mwe-upwiz-license-cc-subhead',
						licenses: [ 'cc-by-sa-3.0' ]
					}
				]
			},
			uwLicenseInput;

		uwLicenseInput = new mw.UploadWizardLicenseInput( values, config );
		$( '#qunit-fixture' ).append( uwLicenseInput.$element );

		// Check license group is there
		assert.strictEqual( $( '.mwe-upwiz-deed-license-group' ).length, 1, 'License group created.' );

		// Check subheader is there
		assert.strictEqual( $( '.mwe-upwiz-deed-license-group-subhead' ).length, 1, 'License subheader created.' );

		// Check license is there
		assert.strictEqual( $( '.mwe-upwiz-deed-license' ).length, 1, 'License created.' );
	} );

}( mediaWiki, jQuery ) );
