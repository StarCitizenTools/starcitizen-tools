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
	QUnit.module( 'mw.UploadWizard', QUnit.newMwEnvironment() );

	QUnit.test( 'constructor sanity test', 1, function ( assert ) {
		var wizard = new mw.UploadWizard( {} );
		assert.ok( wizard );
	} );

	QUnit.test( 'sanitizeFilename', 3, function ( assert ) {
		var oldchars = mw.config.get( 'wgIllegalFileChars', '' );

		assert.strictEqual(
			mw.UploadWizard.sanitizeFilename( '#winning at 100%.jpg' ),
			'-winning at 100-.jpg'
		);

		assert.strictEqual(
			mw.UploadWizard.sanitizeFilename( 'perfectly acceptable filename.jpg' ),
			'perfectly acceptable filename.jpg'
		);

		mw.config.set( 'wgIllegalFileChars', 'f' );
		assert.strictEqual(
			mw.UploadWizard.sanitizeFilename( 'free the forest from fires.jpg' ),
			'-ree the -orest -rom -ires.jpg'
		);

		mw.config.set( 'wgIllegalFileChars', oldchars );
	} );
}( mediaWiki, jQuery ) );
