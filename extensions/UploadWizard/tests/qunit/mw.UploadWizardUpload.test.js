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

( function () {
	QUnit.module( 'mw.UploadWizardUpload', QUnit.newMwEnvironment() );

	function createUpload( filename ) {
		var upload,
			oldconf = mw.UploadWizard.config;

		mw.UploadWizard.config = {};

		upload = new mw.UploadWizardUpload( {
			api: {
				defaults: {
					ajax: {}
				}
			}
		}, {
			name: filename
		} );

		mw.UploadWizard.config = oldconf;

		return upload;
	}

	QUnit.test( 'constructor sanity test', function ( assert ) {
		var upload = createUpload();

		assert.ok( upload );
	} );

	QUnit.test( 'getBasename', function ( assert ) {
		var upload;

		upload = createUpload( 'path/to/filename.png' );
		assert.strictEqual( upload.getBasename(), 'filename.png', 'Path is stripped' );

		upload = createUpload( 'filename.png' );
		assert.strictEqual( upload.getBasename(), 'filename.png', 'Only filename is left alone' );

		upload = createUpload( '///////////' );
		assert.strictEqual( upload.getBasename(), '', 'Nonsensical path is just removed' );
	} );
}() );
