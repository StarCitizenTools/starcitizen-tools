( function ( mw, $ ) {
	'use strict';

	/**
	 * The class allows inheriting classes to log events based on a sampling
	 * rate if sampling is enabled.
	 *
	 * If the schema uses different sampling rates for different events, `samplingRate`
	 * can also be passed to individual events.
	 *
	 * How to use:
	 *
	 *     var mySchema = new mw.eventLog.Schema( 'Name', 0.01, { skin: 'minerva' } );
	 *     // Log the following event at the default sampling rate of 0.01.
	 *     mySchema.log( { action: 'viewed' } );
	 *     // Log the following event at the sampling rate of 0.2.
	 *     mySchema.log( { action: 'clicked' }, 0.2 );
	 *
	 * @class mw.eventLog.Schema
	 * @constructor
	 * @param {string} name Schema name to log to.
	 * @param {number} [samplingRate=1] The rate at which sampling is performed.
	 *  The values are between 0 and 1 inclusive.
	 * @param {Object} [defaults] A set of defaults to log to the schema. Once
	 *  these defaults are set the values will be logged along with any additional
	 *  fields that are passed to the log method.
	 */
	function Schema( name, samplingRate, defaults ) {
		if ( !name ) {
			throw new Error( 'name is required' );
		}

		this.name = name;
		this.populationSize = samplingRate !== undefined ? ( 1 / samplingRate ) : 1;
		this.defaults = defaults || {};
	}

	/**
	 * Log an event via the EventLogging subscriber.
	 *
	 * @param {Object} data Data to log
	 * @param {number} [samplingRate] Number between 0 and 1.
	 *  Defaults to `this.samplingRate`.
	 */
	Schema.prototype.log = function ( data, samplingRate ) {
		// Convert rate to population size
		var pop = samplingRate !== undefined ? ( 1 / samplingRate ) : this.populationSize;

		if ( mw.eventLog.inSample( pop ) ) {
			mw.track(
				'event.' + this.name,
				$.extend( {}, this.defaults, data )
			);
		}
	};

	mw.eventLog.Schema = Schema;

}( mediaWiki, jQuery ) );
