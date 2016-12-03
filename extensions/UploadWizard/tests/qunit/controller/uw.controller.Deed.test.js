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

( function ( $, mw, uw ) {
	QUnit.module( 'mw.uw.controller.Deed', QUnit.newMwEnvironment() );

	QUnit.test( 'Constructor sanity test', 3, function ( assert ) {
		var step = new uw.controller.Deed();
		assert.ok( step );
		assert.ok( step instanceof uw.controller.Step );
		assert.ok( step.ui );
	} );

	QUnit.test( 'moveTo', 1, function ( assert ) {
		var step = new uw.controller.Deed(
				new mw.Api(),
				{ licensing: { thirdParty: { type: 'test', licenses: [] } } }
			),
			ststub = this.sandbox.stub().returns( $.Deferred().promise() ),
			uploads = [
				{ fromURL: true, getThumbnail: ststub },
				{ getThumbnail: ststub },
				{ fromURL: true, getThumbnail: ststub },
				{ getThumbnail: ststub }
			];

		this.sandbox.stub( step.ui, 'moveTo' );
		step.moveTo( uploads );

		assert.strictEqual( ststub.callCount, 2 );
	} );
}( jQuery, mediaWiki, mediaWiki.uploadWizard ) );
