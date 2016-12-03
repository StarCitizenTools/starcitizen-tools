/*global QUnit:false */
( function ( mw, $ ) {
	'use strict';

	var earthquakeSchema = {
			revision: 123,
			schema: {
				description: 'Record of a history earthquake',
				properties: {
					epicenter: {
						type: 'string',
						'enum': [ 'Valdivia', 'Sumatra', 'Kamchatka' ],
						required: true
					},
					magnitude: {
						type: 'number',
						required: true
					},
					article: {
						type: 'string'
					}
				}
			}
		},

		validationCases = [
			{
				args: {},
				regex: /^Missing property/,
				msg: 'Empty, omitting all optional and required fields.'
			},
			{
				args: {
					epicenter: 'Valdivia'
				},
				regex: /^Missing property/,
				msg: 'Empty, omitting one optional and one required field.'
			},
			{
				args: {
					epicenter: 'Valdivia',
					article: '[[1960 Valdivia earthquake]]'
				},
				regex: /^Missing property/,
				msg: 'Required fields must be present.'
			},
			{
				args: {
					epicenter: 'Valdivia',
					magnitude: '9.5'
				},
				regex: /wrong type for property/,
				msg: 'Values must be instances of declared type'
			},
			{
				args: {
					epicenter: 'Valdivia',
					magnitude: 9.5,
					depth: 33
				},
				regex: /^Undeclared property/,
				msg: 'Unrecognized fields fail validation'
			},
			{
				args: {
					epicenter: 'Tōhoku',
					magnitude: 9.0
				},
				regex: /is not one of/,
				msg: 'Enum fields constrain possible values'
			}
		];

	QUnit.module( 'ext.eventLogging', QUnit.newMwEnvironment( {
		setup: function () {
			this.suppressWarnings();
			mw.eventLog.declareSchema( 'earthquake', earthquakeSchema );
			mw.config.set( 'wgEventLoggingBaseUri', '#' );
		},
		teardown: function () {
			mw.eventLog.schemas = {};
			this.restoreWarnings();
		}
	} ) );

	QUnit.test( 'Configuration', 1, function ( assert ) {
		assert.ok( mw.config.exists( 'wgEventLoggingBaseUri' ), 'Global config var "wgEventLoggingBaseUri" exists' );
	} );

	QUnit.test( 'validate', validationCases.length + 1, function ( assert ) {
		var meta = mw.eventLog.getSchema( 'earthquake' ),
			errors = mw.eventLog.validate( {
				epicenter: 'Valdivia',
				magnitude: 9.5
			}, meta.schema );

		assert.propEqual( errors, [], 'Non-required fields may be omitted' );

		$.each( validationCases, function ( _, vCase ) {
			errors = mw.eventLog.validate( vCase.args, meta.schema );
			assert.ok( errors.join( '' ).match( vCase.regex ), vCase.msg );
		} );
	} );

	QUnit.test( 'inSample', 2, function ( assert ) {
		assert.strictEqual( mw.eventLog.inSample( 0 ), false );
		assert.strictEqual( mw.eventLog.inSample( 1 ), true );

		// Test the rest using randomTokenMatch() since we don't
		// want consistency in this case
	} );

	QUnit.test( 'randomTokenMatch', 2, function ( assert ) {
		var i, results = { 'true': 0, 'false': 0 };
		for ( i = 0; i < 100; i++ ) {
			results[ mw.eventLog.randomTokenMatch( 10 ) ]++;
		}
		assert.ok( results[ 'true' ] > 0 && results[ 'true' ] < 20, 'True: ' + results[ 'true' ] );
		assert.ok( results[ 'false' ] > 80 && results[ 'false' ] < 100, 'False: ' + results[ 'false' ] );
	} );

	QUnit.asyncTest( 'logEvent', 1, function ( assert ) {
		var event = {
			epicenter: 'Valdivia',
			magnitude: 9.5
		};

		mw.eventLog.logEvent( 'earthquake', event ).always( function ( e ) {
			QUnit.start();
			assert.deepEqual( e.event, event, 'logEvent promise resolves with event' );
		} );
	} );

	$.each( {
		'URL size is ok': {
			size: mw.eventLog.maxUrlSize,
			expected: undefined
		},
		'URL size is not ok': {
			size: mw.eventLog.maxUrlSize + 1,
			expected: 'Url exceeds maximum length'
		}
	}, function ( name, params ) {
		QUnit.test( name, 1, function ( assert ) {
			var url = new Array( params.size + 1 ).join( 'x' ),
				result = mw.eventLog.checkUrlSize( 'earthquake', url );
			assert.deepEqual( result, params.expected, name );
		} );
	} );

	QUnit.asyncTest( 'logTooLongEvent', 1, function ( assert ) {
		var event = {
			epicenter: 'Valdivia',
			magnitude: 9.5,
			article: new Array( mw.eventLog.maxUrlSize + 1 ).join( 'x' )
		};

		mw.eventLog.logEvent( 'earthquake', event ).always( function ( e, error ) {
			QUnit.start();
			assert.deepEqual( error, 'Url exceeds maximum length',
				'logEvent promise resolves with error' );
		} );
	} );

	QUnit.test( 'setDefaults', 1, function ( assert ) {
		var prepared, defaults;

		defaults = mw.eventLog.setDefaults( 'earthquake', {
			article: '[[1960 Valdivia earthquake]]',
			epicenter: 'Valdivia'
		} );

		prepared = mw.eventLog.prepare( 'earthquake', {
			article: '[[1575 Valdivia earthquake]]',
			magnitude: 9.5
		} );

		assert.deepEqual( prepared.event, {
			article: '[[1575 Valdivia earthquake]]',
			epicenter: 'Valdivia',
			magnitude: 9.5
		}, 'Logged event is annotated with defaults' );
	} );

	QUnit.module( 'ext.eventLogging: isInstanceOf()' );

	$.each( {
		'boolean': {
			valid: [ true, false ],
			invalid: [ undefined, null, 0, -1, 1, 'false' ]
		},
		integer: {
			valid: [ -12, 42, 0, 4294967296 ],
			invalid: [ 42.1, NaN, Infinity, '42', [ 42 ] ]
		},
		number: {
			valid: [ 12, 42.1, 0, Math.PI ],
			invalid: [ '42.1', NaN, [ 42 ], undefined ]
		},
		string: {
			valid: [ 'Hello', '', '-1' ],
			invalid: [ [], 0, true ]
		},
		timestamp: {
			valid: [ +new Date(), new Date() ],
			invalid: [ -1, 'yesterday', NaN ]
		},
		array: {
			valid: [ [], [ 42 ] ],
			invalid: [ -1, {}, undefined ]
		}
	}, function ( type, cases ) {
		var asserts = cases.valid.length + cases.invalid.length;

		QUnit.test( type, asserts, function ( assert ) {
			$.each( cases.valid, function ( index, value ) {
				assert.strictEqual( mw.eventLog.isInstanceOf( value, type ), true,
					JSON.stringify( value ) + ' is a ' + type );
			} );
			$.each( cases.invalid, function ( index, value ) {
				assert.strictEqual( mw.eventLog.isInstanceOf( value, type ), false,
					JSON.stringify( value ) + ' is not a ' + type );
			} );
		} );
	} );

}( mediaWiki, jQuery ) );
