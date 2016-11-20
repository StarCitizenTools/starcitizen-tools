<?php
/**
 * Static functions for extension.
 *
 * @file
 * @author Robert Leverington
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

/**
 * Static functions for Babel extension.
 */
class BabelStatic {
	/**
	 * Registers the parser function hook.
	 *
	 * @param $parser Parser
	 *
	 * @return bool True.
	 */
	public static function onParserFirstCallInit( $parser ) {
		$parser->setFunctionHook( 'babel', [ 'Babel', 'Render' ] );

		return true;
	}
}
