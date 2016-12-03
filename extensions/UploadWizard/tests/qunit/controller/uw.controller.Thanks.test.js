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

( function ( mw, uw ) {
	QUnit.module( 'mw.uw.controller.Thanks', QUnit.newMwEnvironment() );

	QUnit.test( 'Constructor sanity test', 3, function ( assert ) {
		var step = new uw.controller.Thanks( { display: { thanksLabel: 'Thanks!' } } );
		assert.ok( step );
		assert.ok( step instanceof uw.controller.Step );
		assert.ok( step.ui );
	} );

	QUnit.test( 'moveTo', 1, function ( assert ) {
		var step = new uw.controller.Thanks( {} ),
			auStub = this.sandbox.stub( step.ui, 'addUpload' );

		this.sandbox.stub( step.ui, 'moveTo' );
		step.moveTo( [ 1, 2, 3 ] );

		assert.strictEqual( auStub.callCount, 3 );
	} );

	QUnit.test( 'Custom button configuration', 4, function ( assert ) {
		var config = {
				display: {
					homeButton: {
						label: 'This is just a test',
						target: 'https://wiki.example.com/wiki/Main_Page'
					},
					beginButton: {
						label: 'Let me start again',
						target: 'https://commons.wikimedia.org/wiki/Special:UploadWizard'
					}
				}
			},
			uiThanks = new uw.ui.Thanks( config );

		assert.equal(
			uiThanks.homeButton.getLabel(),
			'This is just a test',
			'The label of the home button matches the configured text.'
		);

		assert.equal(
			uiThanks.homeButton.getHref(),
			'https://wiki.example.com/wiki/Main_Page',
			'The target of the home button matches the configured URL.'
		);

		assert.equal(
			uiThanks.beginButton.getLabel(),
			'Let me start again',
			'The label of the begin button matches the configured text.'
		);

		assert.equal(
			uiThanks.beginButton.getHref(),
			'https://commons.wikimedia.org/wiki/Special:UploadWizard',
			'The target of the begin button matches the configured URL.'
		);

	} );

	QUnit.test( 'Method drops the given parameter', 1, function ( assert ) {
		var uiThanks = new uw.ui.Thanks( {} ),
			locationHref = 'https://commons.wikimedia.org/wiki/Special:UploadWizard?campaign=somecampaign&objref=testRef|MyPage|342&updateList=1&somevar=someval';

		assert.equal(
			uiThanks.dropParameterFromURL( locationHref, 'updateList' ),
			'https://commons.wikimedia.org/wiki/Special:UploadWizard?campaign=somecampaign&objref=testRef%7CMyPage%7C342&somevar=someval',
			'The href of the begin button does not contain the updateList parameter.'
		);
	} );

}( mediaWiki, mediaWiki.uploadWizard ) );
