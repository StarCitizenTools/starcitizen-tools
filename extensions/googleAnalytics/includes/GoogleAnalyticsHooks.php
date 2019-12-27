<?php

class GoogleAnalyticsHooks {

	/**
	 * @param Skin $skin
	 * @param string $text
	 * @return bool
	 */
	public static function onSkinAfterBottomScripts( Skin $skin, &$text = '' ) {
		$config = ConfigFactory::getDefaultInstance()->makeConfig( 'googleanalytics' );

		$account = $config->get( 'GoogleAnalyticsAccount' );
		$anonymizeIP = $config->get( 'GoogleAnalyticsAnonymizeIP' );
		$otherCode = $config->get( 'GoogleAnalyticsOtherCode' );
		$ignoreNsIDs = $config->get( 'GoogleAnalyticsIgnoreNsIDs' );
		$ignorePages = $config->get( 'GoogleAnalyticsIgnorePages' );
		$ignoreSpecials = $config->get( 'GoogleAnalyticsIgnoreSpecials' );

		if ( $skin->getUser()->isAllowed( 'noanalytics' ) ) {
			$text .= "<!-- Web analytics code inclusion is disabled for this user. -->\r\n";
			return true;
		}

		$ignoreSpecialss = array_filter( $ignoreSpecials, function ( $v ) use ( $skin ) {
			return $skin->getTitle()->isSpecial( $v );
		} );
		if ( count( $ignoreSpecialss ) > 0
			|| in_array( $skin->getTitle()->getNamespace(), $ignoreNsIDs, true )
			|| in_array( $skin->getTitle()->getPrefixedText(), $ignorePages, true ) ) {
			$text .= "<!-- Web analytics code inclusion is disabled for this page. -->\r\n";
			return true;
		}

		$appended = false;

		if ( $account !== '' ) {
			$text .= <<<EOD
<script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');

  ga('create', '
EOD
. $account . <<<EOD
', 'auto');

EOD
. ( $anonymizeIP ? "  ga('set', 'anonymizeIp', true);\r\n" : "" ) . <<<EOD
  ga('send', 'pageview');

</script>

EOD;
			$appended = true;
		}

		if ( $otherCode !== '' ) {
			$text .= $otherCode . "\r\n";
			$appended = true;
		}

		if ( !$appended ) {
			$text .= "<!-- No web analytics configured. -->\r\n";
		}

		return true;
	}

	public static function onUnitTestsList( array &$files ) {
		// @codeCoverageIgnoreStart
		$directoryIterator = new RecursiveDirectoryIterator( __DIR__ . '/tests/' );

		/**
		 * @var SplFileInfo $fileInfo
		 */
		$ourFiles = [];
		foreach ( new RecursiveIteratorIterator( $directoryIterator ) as $fileInfo ) {
			if ( substr( $fileInfo->getFilename(), -8 ) === 'Test.php' ) {
				$ourFiles[] = $fileInfo->getPathname();
			}
		}

		$files = array_merge( $files, $ourFiles );
		return true;
		// @codeCoverageIgnoreEnd
	}
}
