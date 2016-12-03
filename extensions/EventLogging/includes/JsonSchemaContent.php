<?php
/**
 * JSON Schema Content Model
 *
 * @file
 * @ingroup Extensions
 * @ingroup EventLogging
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

/**
 * Represents the content of a JSON Schema article.
 */
class JsonSchemaContent extends JsonContent {

	const DEFAULT_RECURSION_LIMIT = 3;

	public function __construct( $text, $modelId = 'JsonSchema' ) {
		parent::__construct( $text, $modelId );
	}

	/**
	 * Resolve a JSON reference to a schema.
	 * @param string $ref Schema reference with format 'Title/Revision'
	 * @return array|bool
	 */
	public static function resolve( $ref ) {
		list( $title, $revId ) = explode( '/', $ref );
		$rs = new RemoteSchema( $title, $revId );
		return $rs->get();
	}

	/**
	 * Recursively resolve references in a schema.
	 * @param array $schema Schema object to expand
	 * @param int $recursionLimit Maximum recursion limit
	 * @return array Expanded schema object
	 */
	public static function expand( $schema,
			$recursionLimit = JsonSchemaContent::DEFAULT_RECURSION_LIMIT ) {
		return array_map( function ( $value ) use( $recursionLimit ) {
			if ( is_array( $value ) && $recursionLimit > 0 ) {
				if ( isset( $value['$ref'] ) ) {
					$value = JsonSchemaContent::resolve( $value['$ref'] );
				}
				return JsonSchemaContent::expand( $value, $recursionLimit - 1 );
			} else {
				return $value;
			}
		}, $schema );
	}

	/**
	 * Decodes the JSON schema into a PHP associative array.
	 * @return array Schema array
	 */
	public function getJsonData() {
		return FormatJson::decode( $this->getNativeData(), true );
	}

	/**
	 * @throws JsonSchemaException If content is invalid
	 * @return bool True if valid
	 */
	public function validate() {
		$schema = $this->getJsonData();
		if ( !is_array( $schema ) ) {
			throw new JsonSchemaException( wfMessage( 'eventlogging-invalid-json' )->parse() );
		}
		return efSchemaValidate( $schema );
	}

	/**
	 * @return bool Whether content is valid JSON Schema.
	 */
	public function isValid() {
		try {
			return parent::isValid() && $this->validate();
		} catch ( JsonSchemaException $e ) {
			return false;
		}
	}

	/**
	 * Constructs HTML representation of a single key-value pair.
	 * Override this to support $ref
	 * @param string $key
	 * @param mixed $val
	 * @return string HTML
	 */
	public function objectRow( $key, $val ) {
		if ( $key === '$ref' ) {
			$valParts = explode( '/', $val, 2 );
			if ( !isset( $valParts[1] ) ) {
				$revId = $valParts[1];
				$title = Revision::newFromId( $revId )->getTitle();
				$link = Linker::link( $title, htmlspecialchars( $val ), [],
					[ 'oldid' => $revId ] );

				$th = Xml::elementClean( 'th', [], $key );
				$td = Xml::tags( 'td', [ 'class' => 'value' ], $link );
				return Html::rawElement( 'tr', [], $th . $td );
			}
		}

		return parent::objectRow( $key, $val );
	}

	/**
	 * Generate generic PHP and JavaScript code strings showing how to
	 * use a schema.
	 * @param string $dbKey DB key of schema article
	 * @param int $revId Revision ID of schema article
	 * @return array Nested array with each sub-array having a language, header
	 *  (message key), and code
	 */
	public function getCodeSamples( $dbKey, $revId ) {
		return [
			[
				'language' => 'php',
				'header' => 'eventlogging-code-sample-logging-on-server-side',
				'code' => "EventLogging::logEvent( '$dbKey', $revId, \$event );",
			], [
				'language' => 'php',
				'header' => 'eventlogging-code-sample-module-setup',
				'code' => "\$wgEventLoggingSchemas[ '{$dbKey}' ] = {$revId};",
			], [
				'language' => 'javascript',
				'header' => 'eventlogging-code-sample-logging-on-client-side',
				'code' => "mw.eventLog.logEvent( '{$dbKey}', { /* ... */ } );",
			],
		];
	}

	/**
	 * Wraps HTML representation of content.
	 *
	 * If the schema already exists and if the SyntaxHiglight GeSHi
	 * extension is installed, use it to render code snippets
	 * showing how to use schema.
	 *
	 * @see https://mediawiki.org/wiki/Extension:SyntaxHighlight_GeSHi
	 *
	 * @param Title $title
	 * @param int|null $revId Revision ID
	 * @param ParserOptions|null $options
	 * @param bool $generateHtml Whether or not to generate HTML
	 * @return ParserOutput
	 */
	public function getParserOutput( Title $title, $revId = null,
		ParserOptions $options = null, $generateHtml = true ) {
		$out = parent::getParserOutput( $title, $revId, $options, $generateHtml );

		if ( $revId !== null && class_exists( 'SyntaxHighlight_GeSHi' ) ) {
			$html = '';
			$highlighter = new SyntaxHighlight_GeSHi();
			foreach ( self::getCodeSamples( $title->getDBkey(), $revId ) as $sample ) {
				$lang = $sample['language'];
				$code = $sample['code'];
				$highlighted = $highlighter->highlight( $code, $lang )->getValue();
				$html .= Html::element( 'h2',
					[],
					wfMessage( $sample['header'] )->text()
				) . $highlighted;
			}
			// The glyph is '< >' from the icon font 'Entypo' (see ../modules).
			$html = Xml::tags( 'div', [ 'class' => 'mw-json-schema-code-glyph' ], '&#xe714;' ) .
				Xml::tags( 'div', [ 'class' => 'mw-json-schema-code-samples' ], $html );
			$out->setIndicator( 'schema-code-samples', $html );
			$out->addModules( [ 'ext.eventLogging.jsonSchema', 'ext.pygments' ] );
			$out->addModuleStyles( 'ext.eventLogging.jsonSchema.styles' );
		}

		return $out;
	}
}
