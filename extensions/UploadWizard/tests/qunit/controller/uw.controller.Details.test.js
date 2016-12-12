/*
 * This file is part of the MediaWiki extension DetailsWizard.
 *
 * DetailsWizard is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * DetailsWizard is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with DetailsWizard.  If not, see <http://www.gnu.org/licenses/>.
 */

( function ( uw, $ ) {
	QUnit.module( 'mw.uw.controller.Details', QUnit.newMwEnvironment() );

	function createTestUpload( sandbox, customDeedChooser, aborted ) {
		var stubs = {
			bascm: sandbox.stub(),
			cd: sandbox.stub(),
			ucdc: sandbox.stub()
		};

		return {
			chosenDeed: {
				name: customDeedChooser ? 'custom' : 'cc-by-sa-4.0'
			},

			createDetails: stubs.cd,

			details: {
				buildAndShowCopyMetadata: stubs.bascm,

				useCustomDeedChooser: stubs.ucdc
			},

			state: aborted ? 'aborted' : 'stashed',

			stubs: stubs
		};
	}

	QUnit.test( 'Constructor sanity test', 3, function ( assert ) {
		var step = new uw.controller.Details( {
			maxSimultaneousConnections: 1
		} );
		assert.ok( step );
		assert.ok( step instanceof uw.controller.Step );
		assert.ok( step.ui );
	} );

	QUnit.test( 'moveTo', 16, function ( assert ) {
		var step = new uw.controller.Details( {
				maxSimultaneousConnections: 1
			} ),
			testUpload = createTestUpload( this.sandbox ),
			stepUiStub = this.sandbox.stub( step.ui, 'moveTo' );

		step.moveTo( [ testUpload ] );

		assert.strictEqual( testUpload.stubs.bascm.called, false );
		assert.strictEqual( testUpload.stubs.ucdc.called, false );
		assert.ok( testUpload.stubs.cd.called );
		assert.ok( stepUiStub.called );

		testUpload = createTestUpload( this.sandbox, true );
		step.moveTo( [ testUpload ] );

		assert.strictEqual( testUpload.stubs.bascm.called, false );
		assert.ok( testUpload.stubs.ucdc.called );
		assert.ok( testUpload.stubs.cd.called );
		assert.ok( stepUiStub.called );

		testUpload = createTestUpload( this.sandbox );
		step.moveTo( [ testUpload, createTestUpload( this.sandbox ) ] );

		assert.ok( testUpload.stubs.bascm.called );
		assert.strictEqual( testUpload.stubs.ucdc.called, false );
		assert.ok( testUpload.stubs.cd.called );
		assert.ok( stepUiStub.called );

		testUpload = createTestUpload( this.sandbox );
		step.moveTo( [ testUpload, createTestUpload( this.sandbox, false, true ) ] );

		assert.strictEqual( testUpload.stubs.bascm.called, false );
		assert.strictEqual( testUpload.stubs.ucdc.called, false );
		assert.ok( testUpload.stubs.cd.called );
		assert.ok( stepUiStub.called );
	} );

	QUnit.test( 'canTransition', 3, function ( assert ) {
		var upload = {},
			step = new uw.controller.Details( {
				maxSimultaneousConnections: 1
			} );

		assert.strictEqual( step.canTransition( upload ), false );
		upload.state = 'details';
		assert.strictEqual( step.canTransition( upload ), true );
		upload.state = 'complete';
		assert.strictEqual( step.canTransition( upload ), false );
	} );

	QUnit.asyncTest( 'transitionAll', 4, function ( assert ) {
		var tostub, promise,
			donestub = this.sandbox.stub(),
			ds = [ $.Deferred(), $.Deferred(), $.Deferred() ],
			ps = [ ds[ 0 ].promise(), ds[ 1 ].promise(), ds[ 2 ].promise() ],
			calls = [],
			step;

		tostub = this.sandbox.stub( uw.controller.Details.prototype, 'transitionOne' );
		tostub.onFirstCall().returns( ps[ 0 ] );
		tostub.onSecondCall().returns( ps[ 1 ] );
		tostub.onThirdCall().returns( ps[ 2 ] );

		this.sandbox.stub( uw.controller.Details.prototype, 'canTransition' ).returns( true );

		step = new uw.controller.Details( {
			maxSimultaneousConnections: 3
		} );

		step.uploads = [
			{ id: 15 },
			undefined,
			{ id: 21 },
			{ id: 'aoeu' }
		];

		promise = step.transitionAll().done( donestub );
		setTimeout( function () {
			calls = [ tostub.getCall( 0 ), tostub.getCall( 1 ), tostub.getCall( 2 ) ];

			assert.strictEqual( calls[ 0 ].args[ 0 ].id, 15 );
			assert.strictEqual( calls[ 1 ].args[ 0 ].id, 21 );

			ds[ 0 ].resolve();
			ds[ 1 ].resolve();
			setTimeout( function () {
				assert.strictEqual( donestub.called, false );

				ds[ 2 ].resolve();
				setTimeout( function () {
					assert.ok( donestub.called );

					QUnit.start();
				} );
			} );
		} );
	} );

}( mediaWiki.uploadWizard, jQuery ) );
