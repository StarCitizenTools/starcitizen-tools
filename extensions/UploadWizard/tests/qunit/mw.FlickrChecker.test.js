( function ( mw ) {
	'use strict';

	QUnit.module( 'ext.uploadWizard/mw.FlickrChecker.test.js', QUnit.newMwEnvironment( {
		setup: function () {
			mw.FlickrChecker.fileNames = {};
		}
	} ) );

	function getInstance() {
		var wizard = new mw.UploadWizard( {} ),
		// FlickrChecker doesn't actually do much with the upload so we can omit some of its dependencies
			upload = new mw.UploadWizardUpload( wizard );
		return new mw.FlickrChecker( wizard, upload );
	}

	QUnit.test( 'getFilenameFromItem() simple case', 1, function () {
		var flickrChecker = getInstance();
		QUnit.equal(
			flickrChecker.getFilenameFromItem( 'foo', 123, 'johndoe' ),
			'foo.jpg'
		);
	} );

	QUnit.test( 'getFilenameFromItem() with empty title', 1, function () {
		var flickrChecker = getInstance();
		QUnit.equal(
			flickrChecker.getFilenameFromItem( '', 123, 'johndoe' ),
			'johndoe - 123.jpg'
		);
	} );

	QUnit.test( 'getFilenameFromItem() name conflict within instance', 2, function () {
		var flickrChecker = getInstance(),
			fileName = flickrChecker.getFilenameFromItem( 'foo', 123, 'johndoe' );
		QUnit.equal(
			flickrChecker.getFilenameFromItem( 'foo', 123, 'johndoe' ),
			'foo.jpg'
		);
		flickrChecker.reserveFileName( fileName );
		QUnit.equal(
			flickrChecker.getFilenameFromItem( 'foo', 123, 'johndoe' ),
			'foo - 123.jpg'
		);
	} );

	QUnit.test( 'getFilenameFromItem() name conflict between different instances', 2, function () {
		var flickrChecker = getInstance(),
			fileName = flickrChecker.getFilenameFromItem( 'foo', 123, 'johndoe' );
		QUnit.equal(
			flickrChecker.getFilenameFromItem( 'foo', 123, 'johndoe' ),
			'foo.jpg'
		);
		flickrChecker.reserveFileName( fileName );
		flickrChecker = getInstance();
		QUnit.equal(
			flickrChecker.getFilenameFromItem( 'foo', 123, 'johndoe' ),
			'foo - 123.jpg'
		);
	} );

	QUnit.test( 'setUploadDescription', 9, function ( assert ) {
		var flickrChecker = getInstance(),
			upload = {},
			sidstub = this.sandbox.stub( flickrChecker, 'setImageDescription' );

		flickrChecker.setUploadDescription( upload );
		assert.ok( sidstub.called );
		assert.ok( !upload.description );

		sidstub.reset();
		upload = {};
		flickrChecker.setUploadDescription( upload, 'Testing' );
		assert.strictEqual( upload.description, 'Testing' );
		assert.ok( !sidstub.called );

		sidstub.reset();
		upload = {};
		flickrChecker.setUploadDescription( upload, 'Testing | 1234' );
		assert.strictEqual( upload.description, 'Testing &#124; 1234' );
		assert.ok( !sidstub.called );

		upload = {};
		flickrChecker.setUploadDescription( upload, 'Testing | 1234 | 5678' );
		assert.strictEqual( upload.description, 'Testing &#124; 1234 &#124; 5678' );

		sidstub.reset();
		upload = {};
		flickrChecker.setUploadDescription( upload, '' );
		assert.ok( !sidstub.called );
		assert.strictEqual( upload.description, '' );
	} );
}( mediaWiki ) );
