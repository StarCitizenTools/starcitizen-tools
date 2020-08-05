<?php

namespace MediaWiki\Extension\WikiSEO\Tests\Generator;

use HashConfig;
use MediaWikiTestCase;
use MultiConfig;
use OutputPage;
use RequestContext;
use Title;
use WebRequest;

class GeneratorTest extends MediaWikiTestCase {
	/**
	 * @return OutputPage
	 * @see    \OutputPageTest::newInstance()
	 */
	protected function newInstance( $config = [], WebRequest $request = null, $options = [] ) {
		$context = new RequestContext();

		$context->setConfig(
			new MultiConfig(
				[
				new HashConfig(
					$config + [
					'AppleTouchIcon'            => false,
					'DisableLangConversion'     => true,
					'EnableCanonicalServerLink' => false,
					'Favicon'                   => false,
					'Feed'                      => false,
					'LanguageCode'              => false,
					'ReferrerPolicy'            => false,
					'RightsPage'                => false,
					'RightsUrl'                 => false,
					'UniversalEditButton'       => false,
					]
				),
				$context->getConfig()
				]
			)
		);

		if ( !in_array( 'notitle', (array)$options ) ) {
			$context->setTitle( Title::newFromText( 'My test page' ) );
		}

		if ( $request ) {
			$context->setRequest( $request );
		}

		return new OutputPage( $context );
	}
}
