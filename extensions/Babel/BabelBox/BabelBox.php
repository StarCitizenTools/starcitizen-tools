<?php
/**
 * Contains interface code.
 *
 * @file
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

namespace MediaWiki\Babel\BabelBox;

/**
 * Interface for babel boxes.
 */
interface BabelBox {

	/**
	 * Return the babel box code.
	 *
	 * @return string HTML
	 */
	public function render();

}
