<?php
/**
 * Contains code for inner items which render as empty strings.
 *
 * @file
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

namespace MediaWiki\Babel\BabelBox;

/**
 * Class for inner items which render as empty strings.
 */
class NullBabelBox implements BabelBox {

	/**
	 * Return the babel box code.
	 *
	 * @return string Empty string
	 */
	public function render() {
		return '';
	}

}
