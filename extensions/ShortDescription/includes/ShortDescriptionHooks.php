<?php

class ShortDescriptionHooks {

	// Extracted from WikiBase
	// Register the "shortdesc" magic word
	private function registerShortDescHandler( Parser $parser ) {
		$parser->setFunctionHook(
			'shortdesc',
			[ ShortDescHandler::class, 'handle' ],
			Parser::SFH_NO_HASH
		);
	}

	// Register any render callbacks with the parser
	public static function onParserFirstCallInit( Parser $parser ) {

		// Create a function hook associating the "getshortdesc" magic word with rendershortdesc()
		$parser->setFunctionHook( 'getshortdesc', [ self::class, 'rendershortdesc' ] );
	}

	// Render the output of {{#GETSHORTDESC}}.
	public static function rendershortdesc( Parser $parser, $param1 = '', $param2 = '', $param3 = '' ) {

	// The input parameters are wikitext with templates expanded.
	// The output should be wikitext too.
	$output = "this should be where the description is";

	return $output;
   }
}