/*
 * This file is part of the MediaWiki extension DeedWizard.
 *
 * DeedWizard is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * DeedWizard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with DeedWizard.  If not, see <http://www.gnu.org/licenses/>.
 */

( function ( uw ) {
	QUnit.module( 'uw.controller.Deed', QUnit.newMwEnvironment() );

	QUnit.test( 'Constructor sanity test', function ( assert ) {
		var step = new uw.controller.Deed();
		assert.ok( step );
		assert.ok( step instanceof uw.controller.Step );
		assert.ok( step.ui );
	} );

	QUnit.test( 'load', function ( assert ) {
		var step = new uw.controller.Deed(
				new mw.Api(),
				{ licensing: { thirdParty: { type: 'test', licenses: [] } } }
			),
			ststub = this.sandbox.stub().returns( $.Deferred().promise() ),
			uploads = [
				{ file: { fromURL: true }, getThumbnail: ststub, on: function () {}, title: mw.Title.newFromText( 'Test1.jpg', 6 ) },
				{ file: {}, getThumbnail: ststub, on: function () {}, title: mw.Title.newFromText( 'Test2.jpg', 6 ) },
				{ file: { fromURL: true }, getThumbnail: ststub, on: function () {}, title: mw.Title.newFromText( 'Test3.jpg', 6 ) },
				{ file: {}, getThumbnail: ststub, on: function () {}, title: mw.Title.newFromText( 'Test4.jpg', 6 ) }
			];

		this.sandbox.stub( step.ui, 'load' );
		step.load( uploads );

		assert.strictEqual( ststub.callCount, 2 );
	} );
}( mw.uploadWizard ) );
