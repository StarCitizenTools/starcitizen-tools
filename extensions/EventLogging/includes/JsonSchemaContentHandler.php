<?php
/**
 * JSON Schema Content Handler
 *
 * @file
 * @ingroup Extensions
 * @ingroup EventLogging
 *
 * @author Ori Livneh <ori@wikimedia.org>
 */

class JsonSchemaContentHandler extends JsonContentHandler {

	public function __construct( $modelId = 'JsonSchema' ) {
		parent::__construct( $modelId, [ CONTENT_FORMAT_JSON ] );
	}

	public function canBeUsedOn( Title $title ) {
		return $title->inNamespace( NS_SCHEMA );
	}

	protected function getContentClass() {
		return JsonSchemaContent::class;
	}
}
