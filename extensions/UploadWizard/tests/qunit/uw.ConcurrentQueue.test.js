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

( function ( uw ) {
	QUnit.module( 'uw.ConcurrentQueue', QUnit.newMwEnvironment() );

	// This is a bogus action that will be executed for every item added to the
	// queue. We just need to make sure that the action doesn't complete
	// immediately, or the order some methods are called in could be slightly
	// different (e.g. when adding a new item after one has just completed will
	// trigger the next one to execute, which would terminate immediately,
	// instead of giving time for a second new thingy to be added)
	function queueAction() {
		var deferred = $.Deferred();
		setTimeout( deferred.resolve, 10 );
		return deferred.promise();
	}

	// Asserts that the given stub functions were called in the given order.
	// SinonJS's assert.callOrder doesn't allow to check individual calls.
	function assertCalledInOrder() {
		var calls, i, currSpyCall, nextSpyCall;
		// Map stubs to specific calls
		calls = Array.prototype.map.call( arguments, function ( spy ) {
			if ( !spy.assertCallsInOrderLastCall ) {
				spy.assertCallsInOrderLastCall = 0;
			}
			return spy.getCall( spy.assertCallsInOrderLastCall++ );
		} );
		// Assert stuff
		for ( i = 0; i < calls.length - 1; i++ ) {
			currSpyCall = calls[ i ];
			nextSpyCall = calls[ i + 1 ];
			if ( currSpyCall ) {
				QUnit.assert.ok(
					currSpyCall.callId < ( nextSpyCall ? nextSpyCall.callId : -1 ),
					'Call ' + ( i + 1 ) + ' (callId ' + currSpyCall.callId + ') is in the right order'
				);
			} else {
				QUnit.assert.ok( false, 'Call ' + ( i + 1 ) + ' (never called) is in the right order' );
			}
		}
		QUnit.assert.ok(
			nextSpyCall,
			'Call ' + calls.length + ' is in the right order'
		);
	}

	QUnit.test( 'Basic behavior', function ( assert ) {
		var done, action, queue;
		done = assert.async();
		action = sinon.spy( queueAction );
		queue = new uw.ConcurrentQueue( {
			count: 3,
			action: action
		} );

		queue.on( 'progress', function () {
			QUnit.assert.ok( queue.running.length <= 3, 'No more than 3 items are executing' );
		} );

		queue.on( 'complete', function () {
			// All items executed
			sinon.assert.callCount( action, 5 );
			// All items executed in the expected order
			sinon.assert.calledWith( action.getCall( 0 ), 'a' );
			sinon.assert.calledWith( action.getCall( 1 ), 'b' );
			sinon.assert.calledWith( action.getCall( 2 ), 'c' );
			sinon.assert.calledWith( action.getCall( 3 ), 'd' );
			sinon.assert.calledWith( action.getCall( 4 ), 'e' );

			done();
		} );

		[ 'a', 'b', 'c', 'd', 'e' ].forEach( function ( v ) {
			queue.addItem( v );
		} );

		queue.startExecuting();
	} );

	QUnit.test( 'Event emitting', function ( assert ) {
		var done, changeHandler, progressHandler, completeHandler, queue;
		done = assert.async();
		changeHandler = sinon.stub();
		progressHandler = sinon.stub();
		completeHandler = sinon.stub();
		queue = new uw.ConcurrentQueue( {
			count: 3,
			action: queueAction
		} );

		queue.connect( null, {
			change: changeHandler,
			progress: progressHandler,
			complete: completeHandler
		} );

		queue.on( 'complete', function () {
			sinon.assert.callCount( changeHandler, 3 );
			sinon.assert.callCount( progressHandler, 3 );
			sinon.assert.callCount( completeHandler, 1 );

			assertCalledInOrder(
				changeHandler, // Added 'a'
				changeHandler, // Added 'b'
				changeHandler, // Added 'c'
				progressHandler, // Finished 'a', 'b' or 'c'
				progressHandler, // Finished 'a', 'b' or 'c'
				progressHandler, // Finished 'a', 'b' or 'c'
				completeHandler
			);

			done();
		} );

		queue.addItem( 'a' );
		queue.addItem( 'b' );
		queue.addItem( 'c' );
		queue.startExecuting();
	} );

	QUnit.test( 'Restarting a completed queue', function ( assert ) {
		var done, queue;
		done = assert.async();
		queue = new uw.ConcurrentQueue( {
			count: 3,
			action: queueAction
		} );

		queue.addItem( 'a' );
		queue.addItem( 'b' );
		queue.addItem( 'c' );

		queue.once( 'complete', function () {
			QUnit.assert.equal( queue.completed, true );
			queue.addItem( 'd' );
			queue.addItem( 'e' );

			queue.once( 'complete', function () {
				QUnit.assert.equal( queue.completed, true );
				done();
			} );

			queue.startExecuting();
		} );

		queue.startExecuting();
	} );

	QUnit.test( 'Empty queue completes', function ( assert ) {
		var done, queue;
		done = assert.async();
		queue = new uw.ConcurrentQueue( {
			count: 3,
			action: queueAction
		} );

		queue.on( 'complete', function () {
			QUnit.assert.equal( queue.completed, true );

			done();
		} );

		queue.startExecuting();
	} );

	QUnit.test( 'Adding new items while queue running', function ( assert ) {
		var done, changeHandler, progressHandler, completeHandler, queue;
		done = assert.async();
		changeHandler = sinon.stub();
		progressHandler = sinon.stub();
		completeHandler = sinon.stub();
		queue = new uw.ConcurrentQueue( {
			count: 2,
			action: queueAction
		} );

		queue.connect( null, {
			change: changeHandler,
			progress: progressHandler,
			complete: completeHandler
		} );

		queue.on( 'complete', function () {
			sinon.assert.callCount( changeHandler, 6 );
			sinon.assert.callCount( progressHandler, 6 );
			sinon.assert.callCount( completeHandler, 1 );

			assertCalledInOrder(
				changeHandler, // Added 'a'
				changeHandler, // Added 'b'
				changeHandler, // Added 'c'
				progressHandler, // Finished 'a' or 'b'
				changeHandler, // Added 'd'
				changeHandler, // Added 'e'
				progressHandler, // Finished 'a', 'b' or 'c'
				progressHandler, // Finished 'a', 'b', 'c' or 'd'
				progressHandler, // Finished 'a', 'b', 'c', 'd' or 'e'
				progressHandler, // Finished 'a', 'b', 'c', 'd' or 'e'
				changeHandler, // Added 'f'
				progressHandler, // Finished f'
				completeHandler
			);

			done();
		} );

		queue.addItem( 'a' );
		queue.addItem( 'b' );
		queue.addItem( 'c' );
		queue.once( 'progress', function () {
			queue.addItem( 'd' );
			queue.addItem( 'e' );
		} );
		queue.on( 'progress', function () {
			if ( queue.done.length === 5 ) {
				queue.addItem( 'f' );
			}
		} );
		queue.startExecuting();
	} );

	QUnit.test( 'Deleting items while queue running', function ( assert ) {
		var done, changeHandler, progressHandler, completeHandler, queue;
		done = assert.async();
		changeHandler = sinon.stub();
		progressHandler = sinon.stub();
		completeHandler = sinon.stub();
		queue = new uw.ConcurrentQueue( {
			count: 2,
			action: queueAction
		} );

		queue.connect( null, {
			change: changeHandler,
			progress: progressHandler,
			complete: completeHandler
		} );

		queue.on( 'complete', function () {
			sinon.assert.callCount( changeHandler, 8 );
			sinon.assert.callCount( progressHandler, 4 );
			sinon.assert.callCount( completeHandler, 1 );

			assertCalledInOrder(
				changeHandler, // Added 'a'
				changeHandler, // Added 'b'
				changeHandler, // Added 'c'
				changeHandler, // Added 'd'
				changeHandler, // Added 'e'
				changeHandler, // Added 'f'
				progressHandler, // Finished 'a' or 'b'
				changeHandler, // Removed first of the queued (not executing), which is 'd'
				progressHandler, // Finished 'a', 'b' or 'c'
				changeHandler, // Removed the last one queued (not executing), which is 'f'
				progressHandler, // Finished 'a', 'b', 'c' or 'e'
				progressHandler, // Finished 'a', 'b', 'c' or 'e'
				completeHandler
			);

			done();
		} );

		queue.addItem( 'a' );
		queue.addItem( 'b' );
		queue.addItem( 'c' );
		queue.addItem( 'd' );
		queue.addItem( 'e' );
		queue.addItem( 'f' );
		queue.once( 'progress', function () {
			queue.removeItem( queue.queued[ 0 ] );

			queue.once( 'progress', function () {
				queue.removeItem( queue.queued[ 0 ] );
			} );
		} );
		queue.startExecuting();
	} );

	QUnit.test( 'Deleting currently running item', function ( assert ) {
		var done, action, changeHandler, progressHandler, completeHandler, queue;
		done = assert.async();
		action = sinon.spy( queueAction );
		changeHandler = sinon.stub();
		progressHandler = sinon.stub();
		completeHandler = sinon.stub();
		queue = new uw.ConcurrentQueue( {
			count: 2,
			action: action
		} );

		queue.connect( null, {
			change: changeHandler,
			progress: progressHandler,
			complete: completeHandler
		} );

		queue.on( 'complete', function () {
			// Every item in the queue was executed...
			sinon.assert.callCount( action, 4 );

			sinon.assert.callCount( changeHandler, 5 );
			// ...but the one we removed wasn't registered as finished
			sinon.assert.callCount( progressHandler, 3 );
			sinon.assert.callCount( completeHandler, 1 );

			assertCalledInOrder(
				changeHandler, // Added 'a'
				changeHandler, // Added 'b'
				changeHandler, // Added 'c'
				changeHandler, // Added 'd'
				action, // Started 'a'
				action, // Started 'b'
				progressHandler, // Finished 'a' or 'b'
				changeHandler, // Removed first of the executing, which is 'a' or 'b'
				action, // Started 'c'
				action, // Started 'd' - note how two threads are running still
				progressHandler, // Finished 'c' or 'd'
				progressHandler, // Finished 'c' or 'd'
				completeHandler
			);

			done();
		} );

		queue.addItem( 'a' );
		queue.addItem( 'b' );
		queue.addItem( 'c' );
		queue.addItem( 'd' );
		queue.once( 'progress', function () {
			queue.removeItem( queue.running[ 0 ] );
		} );
		queue.startExecuting();
	} );

	QUnit.test( 'Adding a new item when almost done', function ( assert ) {
		var done, action, changeHandler, progressHandler, completeHandler, queue, onProgress;
		done = assert.async();
		// This test seems extra flaky and was occasionally failing, double the delays
		action = sinon.spy( queueAction );
		changeHandler = sinon.stub();
		progressHandler = sinon.stub();
		completeHandler = sinon.stub();
		queue = new uw.ConcurrentQueue( {
			count: 2,
			action: action
		} );

		queue.connect( null, {
			change: changeHandler,
			progress: progressHandler,
			complete: completeHandler
		} );

		queue.on( 'complete', function () {
			sinon.assert.callCount( action, 5 );
			sinon.assert.callCount( changeHandler, 5 );
			sinon.assert.callCount( progressHandler, 5 );
			sinon.assert.callCount( completeHandler, 1 );

			assertCalledInOrder(
				changeHandler, // Added 'a'
				changeHandler, // Added 'b'
				changeHandler, // Added 'c'
				changeHandler, // Added 'd'
				action, // Started 'a'
				action, // Started 'b'
				progressHandler, // Finished 'a' or 'b'
				action, // Started 'c'
				progressHandler, // Finished 'a', 'b' or 'c'
				action, // Started 'd'
				progressHandler, // Finished 'a', 'b', 'c' or 'd'
				changeHandler, // Added 'e'
				action, // Started 'e' -- this starts a new thread
				progressHandler, // Finished 'a', 'b', 'c', 'd' or 'e'
				progressHandler, // Finished 'a', 'b', 'c', 'd' or 'e'
				completeHandler
			);

			done();
		} );

		queue.addItem( 'a' );
		queue.addItem( 'b' );
		queue.addItem( 'c' );
		queue.addItem( 'd' );
		onProgress = function () {
			if ( queue.done.length === 3 ) {
				queue.addItem( 'e' );
				queue.off( 'progress', onProgress );
			}
		};
		queue.on( 'progress', onProgress );
		queue.startExecuting();
	} );

}( mw.uploadWizard ) );
