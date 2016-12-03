/*!
 * @module ext.eventLogging.core
 * @author Ori Livneh <ori@wikimedia.org>
 */
( function ( mw, $ ) {
	'use strict';

	var self,

	// `baseUrl` corresponds to $wgEventLoggingBaseUri, as declared
	// in EventLogging.php. If the default value of 'false' has not
	// been overridden, events will not be sent to the server.
	baseUrl = mw.config.get( 'wgEventLoggingBaseUri' );

	/**
	 * Client-side EventLogging API.
	 *
	 * The main API is `mw.eventLog.logEvent`. Most other methods represent internal
	 * functionality, which is exposed only to ease debugging code and writing tests.
	 *
	 * Instances of `ResourceLoaderSchemaModule` indicate a dependency on this module and
	 * declare themselves via the declareSchema method.
	 *
	 * Developers should not load this module directly, but work with schema modules instead.
	 * Schema modules will load this module as a dependency.
	 *
	 * @private
	 * @class mw.eventLog.Core
	 * @singleton
	 */
	self = {

		/**
		 * Schema registry. Schemas that have been declared explicitly via
		 * `eventLog.declareSchema` or implicitly by being referenced in an
		 * `eventLog.logEvent` call are stored in this object.
		 *
		 * @private
		 * @property schemas
		 * @type Object
		 */
		schemas: {},

		/**
		 * Maximum length in chars that a beacon URL can have.
		 * Relevant:
		 *
		 * - Length that browsers support (http://stackoverflow.com/a/417184/319266)
		 * - Length that proxies support (e.g. Varnish)
		 * - varnishlog (shm_reclen)
		 * - varnishkafka
		 *
		 * @private
		 * @property maxUrlSize
		 * @type Number
		 */
		maxUrlSize: 2000,

		/**
		 * Load a schema from the schema registry.
		 * If the schema does not exist, it will be initialised.
		 *
		 * @private
		 * @param {string} schemaName Name of schema.
		 * @return {Object} Schema object.
		 */
		getSchema: function ( schemaName ) {
			if ( !self.schemas.hasOwnProperty( schemaName ) ) {
				self.schemas[ schemaName ] = { schema: { title: schemaName } };
			}
			return self.schemas[ schemaName ];
		},

		/**
		 * Register a schema so that it can be used to validate events.
		 * `ResourceLoaderSchemaModule` instances generate JavaScript code that
		 * invokes this method.
		 *
		 * @private
		 * @param {string} schemaName Name of schema.
		 * @param {Object} meta An object describing a schema:
		 * @param {number} meta.revision Revision ID.
		 * @param {Object} meta.schema The schema itself.
		 * @return {Object} The registered schema.
		 */
		declareSchema: function ( schemaName, meta ) {
			return $.extend( true, self.getSchema( schemaName ), meta );
		},

		/**
		 * Checks whether a JavaScript value conforms to a specified
		 * JSON Schema type.
		 *
		 * @private
		 * @param {Object} value Object to test.
		 * @param {string} type JSON Schema type.
		 * @return {boolean} Whether value is instance of type.
		 */
		isInstanceOf: function ( value, type ) {
			var jsType = $.type( value );
			switch ( type ) {
			case 'integer':
				return jsType === 'number' && value % 1 === 0;
			case 'number':
				return jsType === 'number' && isFinite( value );
			case 'timestamp':
				return jsType === 'date' || ( jsType === 'number' && value >= 0 && value % 1 === 0 );
			default:
				return jsType === type;
			}
		},

		/**
		 * Check whether a JavaScript object conforms to a JSON Schema.
		 *
		 * @private
		 * @param {Object} obj Object to validate.
		 * @param {Object} schema JSON Schema object.
		 * @return {Array} An array of validation errors (empty if valid).
		 */
		validate: function ( obj, schema ) {
			var key, val, prop,
				errors = [];

			if ( !schema || !schema.properties ) {
				errors.push( 'Missing or empty schema' );
				return errors;
			}

			for ( key in obj ) {
				if ( !schema.properties.hasOwnProperty( key ) ) {
					errors.push( mw.format( 'Undeclared property "$1"', key ) );
				}
			}

			for ( key in schema.properties ) {
				prop = schema.properties[ key ];

				if ( !obj.hasOwnProperty( key ) ) {
					if ( prop.required ) {
						errors.push( mw.format( 'Missing property "$1"', key ) );
					}
					continue;
				}
				val = obj[ key ];

				if ( !( self.isInstanceOf( val, prop.type ) ) ) {
					errors.push( mw.format(
						'Value $1 is the wrong type for property "$2" ($3 expected)',
						JSON.stringify( val ), key, prop.type
					) );
					continue;
				}

				if ( prop[ 'enum' ] && $.inArray( val, prop[ 'enum' ] ) === -1 ) {
					errors.push( mw.format(
						'Value $1 for property "$2" is not one of $3',
						JSON.stringify( val ), key, JSON.stringify( prop[ 'enum' ] )
					) );
				}
			}

			return errors;
		},

		/**
		 * Sets default property values for events belonging to a particular schema.
		 * If default values have already been declared, the new defaults are merged
		 * on top.
		 *
		 * @param {string} schemaName The name of the schema.
		 * @param {Object} schemaDefaults A map of property names to default values.
		 * @return {Object} Combined defaults for schema.
		 */
		setDefaults: function ( schemaName, schemaDefaults ) {
			return self.declareSchema( schemaName, { defaults: schemaDefaults } );
		},

		/**
		 * Prepares an event for dispatch by filling defaults for any missing
		 * properties and by encapsulating the event object in an object which
		 * contains metadata about the event itself.
		 *
		 * @private
		 * @param {string} schemaName Canonical schema name.
		 * @param {Object} eventData Event instance.
		 * @return {Object} Encapsulated event.
		 */
		prepare: function ( schemaName, eventData ) {
			var schema = self.getSchema( schemaName ),
				event = $.extend( true, {}, schema.defaults, eventData ),
				errors = self.validate( event, schema.schema );

			while ( errors.length ) {
				mw.track( 'eventlogging.error', mw.format( '[$1] $2', schemaName, errors.pop() ) );
			}

			return {
				event: event,
				revision: schema.revision || -1,
				schema: schemaName,
				webHost: location.hostname,
				wiki: mw.config.get( 'wgDBname' )
			};
		},

		/**
		 * Constructs the EventLogging URI based on the base URI and the
		 * encoded and stringified data.
		 *
		 * @private
		 * @param {Object} data Payload to send
		 * @return {string|boolean} The URI to log the event.
		 */
		makeBeaconUrl: function ( data ) {
			var queryString = encodeURIComponent( JSON.stringify( data ) );
			return baseUrl + '?' + queryString + ';';
		},

		/**
		 * Checks whether a beacon url is short enough,
		 * so that it does not get truncated by varnishncsa.
		 *
		 * @private
		 * @param {string} schemaName Canonical schema name.
		 * @param {string} url Beacon url.
		 * @return {string|undefined} The error message in case of error.
		 */
		checkUrlSize: function ( schemaName, url ) {
			var message;
			if ( url.length > self.maxUrlSize ) {
				message = 'Url exceeds maximum length';
				mw.eventLog.logFailure( schemaName, 'urlSize' );
				mw.track( 'eventlogging.error', mw.format( '[$1] $2', schemaName, message ) );
				return message;
			}
		},

		/**
		 * Transfer data to a remote server by making a lightweight HTTP
		 * request to the specified URL.
		 *
		 * If the user expressed a preference not to be tracked, or if
		 * $wgEventLoggingBaseUri is unset, this method is a no-op.
		 *
		 * @param {string} url URL to request from the server.
		 * @return undefined
		 */
		sendBeacon: ( /1|yes/.test( navigator.doNotTrack ) || !baseUrl )
			? $.noop
			: navigator.sendBeacon
				? function ( url ) { try { navigator.sendBeacon( url ); } catch ( e ) {} }
				: function ( url ) { document.createElement( 'img' ).src = url; },

		/**
		 * Construct and transmit to a remote server a record of some event
		 * having occurred. Events are represented as JavaScript objects that
		 * conform to a JSON Schema. The schema describes the properties the
		 * event object may (or must) contain and their type. This method
		 * represents the public client-side API of EventLogging.
		 *
		 * @param {string} schemaName Canonical schema name.
		 * @param {Object} eventData Event object.
		 * @return {jQuery.Promise} jQuery Promise object for the logging call.
		 */
		logEvent: function ( schemaName, eventData ) {
			var event = self.prepare( schemaName, eventData ),
				url = self.makeBeaconUrl( event ),
				sizeError = self.checkUrlSize( schemaName, url ),
				deferred = $.Deferred();

			if ( !sizeError ) {
				self.sendBeacon( url );
				deferred.resolveWith( event, [ event ] );
			} else {
				deferred.rejectWith( event, [ event, sizeError ] );
			}
			return deferred.promise();
		},

		/**
		 * Increment the error count in statsd for this schema.
		 *
		 * Should be called instead of logEvent in case of an error.
		 *
		 * @param {string} schemaName
		 * @param {string} errorCode
		 */
		logFailure: function ( schemaName, errorCode ) {
			// Record this failure as a simple counter. By default "counter.*" goes nowhere.
			// The WikimediaEvents extension sends it to statsd.
			mw.track( 'counter.eventlogging.client_errors.' + schemaName + '.' + errorCode );
		}

	};

	// Output validation errors to the browser console, if available.
	mw.trackSubscribe( 'eventlogging.error', function ( topic, error ) {
		mw.log.error( error );
	} );

	/**
	 * @class mw.eventLog
	 * @mixins mw.eventLog.Core
	 */
	$.extend( mw.eventLog, self );

}( mediaWiki, jQuery ) );
