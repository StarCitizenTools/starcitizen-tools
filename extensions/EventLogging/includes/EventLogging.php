<?php
/**
 * PHP API for logging events
 *
 * @file
 * @ingroup Extensions
 * @ingroup EventLogging
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

class EventLogging {

	/** @var int flag indicating the user-agent should not be logged. **/
	const OMIT_USER_AGENT = 2;

	/**
	 * Transfer small data asynchronously using an HTTP POST.
	 * This is meant to match the Navigator.sendBeacon() API.
	 *
	 * @see https://w3c.github.io/beacon/#sec-sendBeacon-method
	 * @param string $url
	 * @param array $data
	 * @return bool
	 */
	public static function sendBeacon( $url, array $data = [] ) {
		DeferredUpdates::addCallableUpdate( function () use ( $url, $data ) {
			$options = $data ? [ 'postData' => $data ] : [];
			return Http::post( $url, $options );
		} );

		return true;
	}

	/**
	 * Emit an event via a sendBeacon POST to the event beacon endpoint.
	 *
	 * @param string $schemaName Schema name.
	 * @param int $revId revision ID of schema.
	 * @param array $event Map of event keys/vals.
	 * @param int $options Bitmask consisting of EventLogging::OMIT_USER_AGENT.
	 * @return bool Whether the event was logged.
	 */
	static function logEvent( $schemaName, $revId, $event, $options = 0 ) {
		global $wgDBname, $wgEventLoggingBaseUri;

		if ( !$wgEventLoggingBaseUri ) {
			return false;
		}

		$remoteSchema = new RemoteSchema( $schemaName, $revId );
		$schema = $remoteSchema->get();

		try {
			$isValid = is_array( $schema ) && self::schemaValidate( $event, $schema );
		} catch ( JsonSchemaException $e ) {
			$isValid = false;
		}

		$encapsulated = [
			'event'            => $event,
			'schema'           => $schemaName,
			'revision'         => $revId,
			'clientValidated'  => $isValid,
			'wiki'             => $wgDBname,
		];
		if ( isset( $_SERVER[ 'HTTP_HOST' ] ) ) {
			$encapsulated[ 'webHost' ] = $_SERVER[ 'HTTP_HOST' ];
		}
		if ( !( $options & self::OMIT_USER_AGENT ) && !empty( $_SERVER[ 'HTTP_USER_AGENT' ] ) ) {
			$encapsulated[ 'userAgent' ] = $_SERVER[ 'HTTP_USER_AGENT' ];
		}

		$json = static::serializeEvent( $encapsulated );
		$url = $wgEventLoggingBaseUri . '?' . rawurlencode( $json ) . ';';
		return self::sendBeacon( $url );
	}

	/**
	 *
	 * Converts the encapsulated event from an object to a string.
	 *
	 * @param array $encapsulatedEvent Encapsulated event
	 * @return string $json
	 */
	static function serializeEvent( $encapsulatedEvent ) {
		$event = $encapsulatedEvent['event'];

		if ( count( $event ) === 0 ) {
			// Ensure empty events are serialized as '{}' and not '[]'.
			$event = (object)$event;
		}

		$encapsulatedEvent['event'] = $event;

		// To make the resultant JSON easily extracted from a row of
		// space-separated values, we replace literal spaces with unicode
		// escapes. This is permitted by the JSON specs.
		$json = str_replace( ' ', '\u0020', FormatJson::encode( $encapsulatedEvent ) );

		return $json;
	}

	/**
	 * Validates object against JSON Schema.
	 *
	 * @throws JsonSchemaException If the object fails to validate.
	 * @param array $object Object to be validated.
	 * @param array $schema Schema to validate against (default: JSON Schema).
	 * @return bool True.
	 */
	public static function schemaValidate( $object, $schema = null ) {
		if ( $schema === null ) {
			// Default to JSON Schema
			$json = file_get_contents( dirname( __DIR__ ) . '/schemas/schemaschema.json' );
			$schema = FormatJson::decode( $json, true );
		}

		// We depart from the JSON Schema specification in disallowing by default
		// additional event fields not mentioned in the schema.
		// See <https://bugzilla.wikimedia.org/show_bug.cgi?id=44454> and
		// <https://tools.ietf.org/html/draft-zyp-json-schema-03#section-5.4>.
		if ( !array_key_exists( 'additionalProperties', $schema ) ) {
			$schema[ 'additionalProperties' ] = false;
		}

		$root = new JsonTreeRef( $object );
		$root->attachSchema( $schema );
		return $root->validate();
	}
}
