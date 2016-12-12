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

( function ( mw ) {
	QUnit.module( 'mw.fileApi', QUnit.newMwEnvironment() );

	QUnit.test( 'isPreviewableFile', 6, function ( assert ) {
		var testFile = {};

		testFile.type = 'image/png';
		testFile.size = 5 * 1024 * 1024;
		assert.strictEqual( mw.fileApi.isPreviewableFile( testFile ), true );

		testFile.type = 'image/gif';
		assert.strictEqual( mw.fileApi.isPreviewableFile( testFile ), true );

		testFile.type = 'image/jpeg';
		assert.strictEqual( mw.fileApi.isPreviewableFile( testFile ), true );

		testFile.size = 11 * 1024 * 1024;
		assert.strictEqual( mw.fileApi.isPreviewableFile( testFile ), false );

		testFile.size = 5 * 1024 * 1024;
		testFile.type = 'unplayable/type';
		assert.strictEqual( mw.fileApi.isPreviewableFile( testFile ), false );

		this.sandbox.stub( mw.fileApi, 'isPreviewableVideo' ).returns( true );
		assert.strictEqual( mw.fileApi.isPreviewableFile( testFile ), true );
	} );

	QUnit.test( 'isPreviewableVideo', 4, function ( assert ) {
		var result, testFile = {},
			fakeVideo = {
				canPlayType: this.sandbox.stub().returns( 'yes' )
			};

		this.sandbox.stub( document, 'createElement' ).returns( fakeVideo );
		result = mw.fileApi.isPreviewableVideo( testFile );
		document.createElement.restore();

		assert.strictEqual( result, true );
		assert.strictEqual( fakeVideo.canPlayType.callCount, 1 );

		fakeVideo.canPlayType = this.sandbox.stub().returns( 'no' );
		this.sandbox.stub( document, 'createElement' ).returns( fakeVideo );
		result = mw.fileApi.isPreviewableVideo( testFile );
		document.createElement.restore();

		assert.strictEqual( result, false );
		assert.strictEqual( fakeVideo.canPlayType.callCount, 1 );
	} );

}( mediaWiki ) );
