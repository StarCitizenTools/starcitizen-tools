<?php
/**
 * Contains code for inner items which are not babel boxes.
 *
 * @file
 * @author Robert Leverington
 * @author Robin Pepermans
 * @author Niklas Laxström
 * @author Brian Wolff
 * @author Purodha Blissenbach
 * @author Sam Reed
 * @author Siebrand Mazeland
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

namespace MediaWiki\Babel\BabelBox;

/**
 * Class for inner items which are not babel boxes.
 */
class NotBabelBox implements BabelBox {

	/**
	 * @var string
	 */
	private $dir;

	/**
	 * @var string
	 */
	private $content;

	/**
	 * Construct a non-babel box.
	 *
	 * @param string $dir HTML 'dir' attribute
	 * @param string $content What's inside the box, in wikitext format.
	 */
	public function __construct( $dir, $content ) {
		$this->dir = $dir;
		$this->content = $content;
	}

	/**
	 * Return the babel box code.
	 *
	 * @return string A single non-babel box, in wikitext format.
	 */
	public function render() {
		$notabox = <<<EOT
<div class="mw-babel-notabox" dir="{$this->dir}">{$this->content}</div>
EOT;

		return $notabox;
	}

}
