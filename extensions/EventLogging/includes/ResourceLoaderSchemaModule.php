<?php
/**
 * ResourceLoaderModule subclass for making remote schemas
 * available as JavaScript submodules to client-side code.
 *
 * @file
 *
 * @ingroup Extensions
 * @ingroup EventLogging
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

/**
 * Packages a remote schema as a JavaScript ResourceLoader module.
 */
class ResourceLoaderSchemaModule extends ResourceLoaderModule {

	/** @var RemoteSchema $schema **/
	public $schema;

	/**
	 * Constructor; invoked by ResourceLoader.
	 * Ensures that the 'schema' and 'revision' keys were set on the
	 * $wgResourceModules member array representing this module.
	 *
	 * Example:
	 * @code
	 *  $wgResourceModules[ 'schema.person' ] = array(
	 *      'class'    => 'ResourceLoaderSchemaModule',
	 *      'schema'   => 'Person',
	 *      'revision' => 4703006,
	 *  );
	 * @endcode
	 *
	 * @throws Exception if 'schema' or 'revision' keys are missing.
	 * @param array $args
	 */
	function __construct( $args ) {
		foreach ( [ 'schema', 'revision' ] as $key ) {
			if ( !isset( $args[ $key ] ) ) {
				throw new Exception( "ResourceLoaderSchemaModule params must set '$key' key." );
			}
		}

		if ( !is_int( $args['revision'] ) ) {
			// Events will not validate on the Python server if this is defined
			// wrong.  Enforce it here as well, so it can be more easily caught
			// during local development.
			throw new Exception( "Revision for schema \"{$args['schema']}\" must be given as an integer" );
		}

		$this->schema = new RemoteSchema( $args['schema'], $args['revision'] );
		$this->targets = [ 'desktop', 'mobile' ];
	}

	/**
	 * @param ResourceLoaderContext $context
	 * @return array Module names
	 */
	public function getDependencies( ResourceLoaderContext $context = null ) {
		return [ 'ext.eventLogging' ];
	}

	/**
	 * Get the definition summary for this module.
	 *
	 * @param ResourceLoaderContext $context
	 * @return array
	 */
	public function getDefinitionSummary( ResourceLoaderContext $context ) {
		$summary = parent::getDefinitionSummary( $context );
		$summary[] = [
			'revision' => $this->schema->revision,
		];
		return $summary;
	}

	/**
	 * Generates JavaScript module code from schema.
	 * Retrieves a schema and generates a JavaScript expression which,
	 * when run in the browser, adds it to mw.eventLog.schemas. Adds an
	 * empty schema if the schema could not be retrieved.
	 * @param ResourceLoaderContext $context
	 * @return string: JavaScript code.
	 */
	public function getScript( ResourceLoaderContext $context ) {
		$schema = $this->schema->jsonSerialize();
		efStripKeyRecursive( $schema, 'description' );
		$params = [ $this->schema->title, $schema ];
		return Xml::encodeJsCall( 'mediaWiki.eventLog.declareSchema', $params );
	}
}
