<?php
/**
 * Tabber
 * Tabber Hooks Class
 *
 * @author		Eric Fortin, Alexia E. Smith
 * @license		GPL
 * @package		Tabber
 * @link		https://www.mediawiki.org/wiki/Extension:Tabber
 */
class TabberHooks {
	/**
	 * Sets up this extension's parser functions.
	 *
	 * @param Parser $parser Parser object passed as a reference.
	 * @return bool
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		$parser->setHook( 'tabber', 'TabberHooks::renderTabber' );
		return true;
	}

	/**
	 * Renders the necessary HTML for a <tabber> tag.
	 *
	 * @param string $input The input URL between the beginning and ending tags.
	 * @param array $args Array of attribute arguments on that beginning tag.
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string HTML
	 */
	public static function renderTabber( $input, array $args, Parser $parser, PPFrame $frame ) {
		$parser->getOutput()->addModules( 'ext.tabber' );
		$key = md5( $input );
		$arr = explode( '|-|', $input );
		$htmlTabs = '';
		foreach ( $arr as $tab ) {
			$htmlTabs .= self::buildTab( $tab, $parser, $frame );
		}
		$HTML = '<div id="tabber-' . $key . '" class="tabber">' . $htmlTabs . '</div>';
		return $HTML;
	}

	/**
	 * Build individual tab.
	 *
	 * @param string Tab information
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string HTML
	 */
	private static function buildTab( $tab = '', Parser $parser, PPFrame $frame ) {
		$tab = trim( $tab );
		if ( empty( $tab ) ) {
			return $tab;
		}
		list( $tabName, $tabBody ) = explode( '=', $tab, 2 );
		$tabBody = $parser->recursiveTagParse( $tabBody, $frame );
		$tab = '
			<div class="tabbertab" title="' . htmlspecialchars( $tabName ) . '">
				<p>' . $tabBody . '</p>
			</div>';
		return $tab;
	}
}
