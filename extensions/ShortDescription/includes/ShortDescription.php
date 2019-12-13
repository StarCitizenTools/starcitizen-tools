<?php

class ShortDescriptionHooks {
	// Register any render callbacks with the parser
	public static function onParserFirstCallInit( Parser $parser ) {

		// Create a function hook associating the "example" magic word with rendershortdesc()
		$parser->setFunctionHook( 'shortdesc', [ self::class, 'rendershortdesc' ] );
	}

	// Render the output of {{SHORTDESC:}}.
	public static function rendershortdesc( Parser $parser, $param1 = '', $param2 = '', $param3 = '' ) {

	// The input parameters are wikitext with templates expanded.
	// The output should be wikitext too.
	$output = "test response";

	return $output;
   }
}