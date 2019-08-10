<?php

namespace Octfx\WikiSEO;

use MWException;
use OutputPage;
use Parser;

class Hooks {
	/**
	 * Extracts the generated seo html comments form the page and adds them as meta tags
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 *
	 * @param OutputPage $out
	 */
	public static function onBeforePageDisplay( OutputPage $out ) {
		$seo = new WikiSEO();
		$tags = TagParser::extractSeoDataFromHtml( $out->getHTML() );
		$seo->setMetadataArray( $tags );
		$seo->addMetadataToPage( $out );
	}

	/**
	 * Register parser hooks.
	 * <seo> and {{#seo:}}
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ParserFirstCallInit
	 * @see https://www.mediawiki.org/wiki/Manual:Parser_functions
	 *
	 * @param Parser $parser
	 *
	 * @throws MWException
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'seo', 'Octfx\WikiSEO\WikiSEO::fromTag' );

		$parser->setFunctionHook( 'seo', 'Octfx\WikiSEO\WikiSEO::fromParserFunction', Parser::SFH_OBJECT_ARGS );
	}
}