( function ( mw, uw, $, OO ) {

	/**
	 * A queue that will execute the asynchronous function `action` for each item in the queue in
	 * order, taking care not to allow more than `count` instances to be executing at the same time.
	 *
	 * Items can be added or removed (#addItem, #removeItem) while the queue is already being
	 * executed.
	 *
	 * @mixins OO.EventEmitter
	 * @param {Object} options
	 * @param {Function} options.action Action to execute for each item, must return a Promise
	 * @param {number} options.count Number of functions to execute concurrently
	 */
	uw.ConcurrentQueue = function UWConcurrentQueue( options ) {
		OO.EventEmitter.call( this );

		this.count = options.count;
		this.action = options.action;

		this.queued = [];
		this.running = [];
		this.done = [];
		this.runningPromises = [];

		this.completed = false;
		this.executing = false;
	};
	OO.initClass( uw.ConcurrentQueue );
	OO.mixinClass( uw.ConcurrentQueue, OO.EventEmitter );

	/**
	 * A 'progress' event is emitted when one of the functions' promises is resolved or rejected.
	 *
	 * @event progress
	 */

	/**
	 * A 'complete' event is emitted when all of the functions' promises have been resolved or rejected.
	 *
	 * @event complete
	 */

	/**
	 * A 'change' event is emitted when an item is added to or removed from the queue.
	 *
	 * @event change
	 */

	/**
	 * Add an item to the queue.
	 *
	 * @param {Object} item
	 * @return {boolean} true
	 */
	uw.ConcurrentQueue.prototype.addItem = function ( item ) {
		this.queued.push( item );
		this.emit( 'change' );
		if ( this.executing ) {
			this.executeNext();
		}
		return true;
	};

	/**
	 * Remove an item from the queue.
	 *
	 * While it's possible to remove an item that is being executed, it doesn't stop the execution.
	 *
	 * @param {Object} item
	 * @return {boolean} Whether the item was removed
	 */
	uw.ConcurrentQueue.prototype.removeItem = function ( item ) {
		var index, found;

		found = false;

		index = this.queued.indexOf( item );
		if ( index !== -1 ) {
			this.queued.splice( index, 1 );
			found = true;
		}

		index = this.done.indexOf( item );
		if ( index !== -1 ) {
			this.done.splice( index, 1 );
			found = true;
		}

		index = this.running.indexOf( item );
		if ( index !== -1 ) {
			// Try aborting the promise if possible
			if ( this.runningPromises[ index ].abort ) {
				this.runningPromises[ index ].abort();
			}
			this.running.splice( index, 1 );
			this.runningPromises.splice( index, 1 );
			// Ensure we're still using as many threads as requested
			this.executeNext();
			found = true;
		}

		if ( found ) {
			this.emit( 'change' );
			this.checkIfComplete();
		}

		return found;
	};

	/**
	 * @private
	 */
	uw.ConcurrentQueue.prototype.promiseComplete = function ( item ) {
		var index;
		index = this.running.indexOf( item );
		// Check that this item wasn't removed while it was being executed
		if ( index !== -1 ) {
			this.running.splice( index, 1 );
			this.runningPromises.splice( index, 1 );
			this.done.push( item );
			this.emit( 'progress' );
		}

		this.checkIfComplete();

		this.executeNext();
	};

	/**
	 * @private
	 */
	uw.ConcurrentQueue.prototype.executeNext = function () {
		var item, execute, callback,
			queue = this;
		if ( this.running.length === this.count || !this.executing ) {
			return;
		}
		item = this.queued.shift();
		if ( !item ) {
			return;
		}
		this.running.push( item );
		this.runningPromises.push( {} );
		callback = this.promiseComplete.bind( this, item );
		execute = this.action.bind( null, item );
		// We don't want to accidentally recurse if the promise completes immediately
		setTimeout( function () {
			var index, promise;
			// We have to check the index again, one of the running items might have completed
			index = queue.running.indexOf( item );
			// If we're not in the array at all, it means that execution was aborted, do nothing
			if ( index !== -1 ) {
				promise = execute();
				queue.runningPromises[ index ] = promise;
				promise.always( callback );
			}
		} );
	};

	/**
	 * Start executing the queue. If the queue is already executing, do nothing.
	 *
	 * When the queue finishes executing, a 'complete' event will be emitted.
	 */
	uw.ConcurrentQueue.prototype.startExecuting = function () {
		var i;
		if ( this.executing ) {
			return;
		}
		this.completed = false;
		this.executing = true;
		for ( i = 0; i < this.count; i++ ) {
			this.executeNext();
		}
		// In case the queue was empty
		setTimeout( this.checkIfComplete.bind( this ) );
	};

	/**
	 * Abort executing the queue. Remove all queued items and abort running ones.
	 */
	uw.ConcurrentQueue.prototype.abortExecuting = function () {
		while ( this.queued.length > 0 ) {
			this.removeItem( this.queued[ 0 ] );
		}
		while ( this.running.length > 0 ) {
			this.removeItem( this.running[ 0 ] );
		}
	};

	/**
	 * @private
	 */
	uw.ConcurrentQueue.prototype.checkIfComplete = function () {
		if ( this.running.length === 0 ) {
			if ( !this.completed ) {
				this.completed = true;
				this.executing = false;
				this.emit( 'complete' );
			}
		}
	};

} )( mediaWiki, mediaWiki.uploadWizard, jQuery, OO );
