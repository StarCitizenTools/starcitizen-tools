<?php
/**
 * Contains interface code.
 *
 * @file
 * @license GPL-2.0-or-later
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

	/**
	 * Return categories that should be added to
	 * the ParserOutput. Note that calling this
	 * method may have side effects, like auto
	 * creating those categories.
	 *
	 * @return string[] [ category => sort key ], sort key is false for default
	 */
	public function getCategories();

}
