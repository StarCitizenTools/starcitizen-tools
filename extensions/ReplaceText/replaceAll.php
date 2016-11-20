#!/usr/bin/php
<?php
/**
 * Insert jobs into the job queue to replace text bits.
 * Or execute immediately... your choice.
 *
 * Copyright © 2014 Mark A. Hershberger <mah@nichework.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * PHP version 5
 *
 * @file
 * @category Maintenance
 * @package  ReplaceText
 * @author   Mark A. Hershberger <mah@nichework.com>
 * @license  GPLv3 http://www.gnu.org/copyleft/gpl.html
 * @link     https://www.mediawiki.org/wiki/Extension:Replace_Text
 *
 */

require_once( dirname( __FILE__ ) . '/../../maintenance/Maintenance.php' );

/**
 * Maintenance script that generates a plaintext link dump.
 *
 * @ingroup Maintenance
 * @SuppressWarnings(StaticAccess)
 * @SuppressWarnings(LongVariable)
 */
class ReplaceText extends Maintenance {
	protected $user;
	protected $target;
	protected $replacement;
	protected $summaryMsg;
	protected $namespaces;
	protected $category;
	protected $prefix;
	protected $useRegex;
	protected $titles;
	protected $defaultContinue;

	public function __construct() {
		parent::__construct();
		$this->mDescription = "CLI utility to replace text wherever it is ".
			"found in the wiki.";

		$this->addArg( "target", "Target text to find.", false );
		$this->addArg( "replace", "Text to replace.", false );

		$this->addOption( "dry-run", "Only find the texts, don't replace.",
			false, false, 'n' );
		$this->addOption( "regex", "This is a regex (false).",
			false, false, 'r' );
		$this->addOption( "user", "The user to attribute this to (uid 1).",
			false, true, 'u' );
		$this->addOption( "yes", "Skip all prompts with an assumed 'yes'.",
			false, false, 'y' );
		$this->addOption( "summary", "Alternate edit summary. (%r is where to ".
			" place the replacement text, %f the text to look for.)",
			false, true, 's' );
		$this->addOption( "ns", "Comma separated namespaces to search in. ".
			"(Main)" );
		$this->addOption( "replacements", "File containing the list of replacements to " .
			"be made.  Fields in the file are tab-separated.  See --show-file-format " .
			"for more information.",
			false, true, "f" );
		$this->addOption( "show-file-format", "Show a description of the file format to ".
			"use with --replacements.", false, false );
		$this->addOption( "debug", "Display replacements being made.", false, false );

		$this->addOption( "listns", "List out the namespaces on this wiki.",
			false, false );
	}

	protected function getUser() {
		$userReplacing = $this->getOption( "user", 1 );

		$user = is_numeric( $userReplacing ) ?
			User::newFromId( $userReplacing ) :
			User::newFromName( $userReplacing );

		if ( get_class( $user ) !== 'User' ) {
			$this->error(
				"Couldn't translate '$userReplacing' to a user.", true
			);
		}

		return $user;
	}

	protected function getTarget() {
		$ret = $this->getArg( 0 );
		if ( !$ret ) {
			$this->error( "You have to specify a target.", true );
		}
		return array( $ret );
	}

	protected function getReplacement() {
		$ret = $this->getArg( 1 );
		if ( !$ret ) {
			$this->error( "You have to specify replacement text.", true );
		}
		return array( $ret );
	}

	protected function getReplacements() {
		$file = $this->getOption( "replacements" );
		if ( !$file ) {
			return false;
		}

		if ( !is_readable( $file ) ) {
			throw new MWException( "File does not exist or is not readable: $file\n" );
		}

		$handle = fopen( $file, "r" );
		if ( $handle === false ) {
			throw new MWException( "Trouble opening file: $file\n" );
			return false;
		}

		$this->defaultContinue = true;
		while ( ( $line = fgets( $handle ) ) !== false ) {
			$field = explode( "\t", $line );
			if ( !isset( $field[1] ) ) {
				continue;
			}

			$this->target[] = $field[0];
			$this->replacement[] = $field[1];
			$this->useRegex[] = isset( $field[2] ) ? true : false;
		}
		return true;
	}

	protected function shouldContinueByDefault() {
		if ( !is_bool( $this->defaultContinue ) ) {
			$this->defaultContinue =
				$this->getOption( "yes" ) ?
				true :
				false;
		}
		return $this->defaultContinue;
	}

	protected function getSummary() {
		$msg = wfMessage( 'replacetext_editsummary' )->
			rawParams( $this->target )->rawParams( $this->replacement );
		if ( $this->getOption( "summary" ) !== null ) {
			$msg = str_replace( array( '%f', '%r' ),
				array( $this->target, $this->replacement ),
				$this->getOption( "summary" ) );
		}
		return $msg;
	}

	protected function listNamespaces() {
		echo "Index\tNamespace\n";
		$nsList = MWNamespace::getCanonicalNamespaces();
		ksort( $nsList );
		foreach ( $nsList as $int => $val ) {
			if ($val == "") {
				$val = "(main)";
			}
			echo " $int\t$val\n";
		}
	}

	protected function showFileFormat() {
echo <<<EOF

The format of the replacements file is tab separated with three fields.
Any line that does not have a tab is ignored and can be considered a comment.

Fields are:

 1. String to search for.
 2. String to replace found text with.
 3. (optional) The presence of this field indicates that the previous two
	are considered a regular expression.

Example:

This is a comment
TARGET	REPLACE
regex(p*)	Count the Ps; \\1	true


EOF;

	}

	protected function getNamespaces() {
		$namespaces = array( NS_MAIN );
		$names = $this->getOption( "ns" );
		$namespace = MWNamespace::getCanonicalNamespaces();
		$namespace[0] = "main";
		$nsflip = array_flip( $namespace );
		if ( $names ) {
			$namespaces =
				array_filter(
					array_map(
						function( $namespace ) use ( $namespace, $nsflip ) {
							if ( is_numeric( $namespace )
									&& isset( $namespace[ $namespace ] ) ) {
								return intval( $namespace );
							}
							$namespace = strtolower( $namespace );
								var_dump($nsflip[$namespace]);
							if ( isset( $nsflip[ $namespace ] ) ) {
								return $nsflip[ $namespace ];
							}
							return null;
						}, explode( ",", $names ) ),
					function( $val ) {
						return $val !== null;
					}
				);
		}
		return $namespaces;
	}

	protected function getCategory() {
		$cat = null;
		return $cat;
	}

	protected function getPrefix() {
		$prefix = null;
		return $prefix;
	}

	protected function useRegex() {
		return array( $this->getOption( "regex" ) );
	}

	protected function getTitles( $res ) {
		if ( count( $this->titles ) == 0 ) {
			$this->titles = array();
			while ( $row = $res->fetchObject() ) {
				$this->titles[] = Title::makeTitleSafe(
					$row->page_namespace,
					$row->page_title
				);
			}
		}
		return $this->titles;
	}

	protected function listTitles( $res ) {
		$ret = false;
		foreach ( $this->getTitles( $res ) as $title ) {
			$ret = true;
			echo "$title\n";
		}
		return $ret;
	}

	protected function replaceTitles( $res, $target, $replacement, $useRegex ) {
		foreach ( $this->getTitles( $res ) as $title ) {
			$param = array(
				'target_str'      => $target,
				'replacement_str' => $replacement,
				'use_regex'       => $useRegex,
				'user_id'         => $this->user->getId(),
				'edit_summary'    => $this->summaryMsg,
			);
			echo "Replacing on $title... ";
			$job = new ReplaceTextJob( $title, $param, 0 );
			if ( $job->run() !== true ) {
				$this->error( "Trouble on the page '$title'." );
			}
			echo "done.\n";
		}
	}

	protected function getReply( $question ) {
		$reply = "";
		if ( $this->shouldContinueByDefault() ) {
			return true;
		}
		while ( $reply !== "y" && $reply !== "n" ) {
			$reply = $this->readconsole( "$question (Y/N) " );
			$reply = substr( strtolower( $reply ), 0, 1 );
		}
		return $reply === "y";
	}

	protected function localSetup() {
		if ( $this->getOption( "listns" ) ) {
			$this->listNamespaces();
			return false;
		}
		if ( $this->getOption( "show-file-format" ) ) {
			$this->showFileFormat();
			return false;
		}
		$this->user = $this->getUser();
		if ( ! $this->getReplacements() ) {
			$this->target = $this->getTarget();
			$this->replacement = $this->getReplacement();
			$this->useRegex = $this->useRegex();
		}
		$this->summaryMsg = $this->getSummary();
		$this->namespaces = $this->getNamespaces();
		$this->category = $this->getCategory();
		$this->prefix = $this->getPrefix();
		return true;
	}

	public function execute() {
		global $wgShowExceptionDetails;
		$wgShowExceptionDetails = true;

		if ( $this->localSetup() ) {
			foreach ( array_keys( $this->target ) as $index ) {
				$target = $this->target[$index];
				$replacement = $this->replacement[$index];
				$useRegex = $this->useRegex[$index];

				if ( $this->getOption( "debug" ) ) {
					echo "Replacing '$target' with '$replacement'";
					if ( $useRegex ) {
						echo " as regular expression.";
					}
					echo "\n";
				}
				$res = ReplaceTextSearch::doSearchQuery( $target,
					$this->namespaces, $this->category, $this->prefix, $useRegex );

				if ( $res->numRows() === 0 ) {
					$this->error( "No targets found to replace.", true );
				}
				if ( !$this->shouldContinueByDefault() && $this->listTitles( $res ) ) {
					if ( !$this->getReply( "Replace instances on these pages?" ) ) {
						return;
					}
				}
				$comment = "";
				if ( $this->getOption( "user", null ) === null ) {
					$comment = " (Use --user to override)";
				}
				if ( !$this->getReply( "Attribute changes to the user '{$this->user}'?$comment" ) ) {
					return;
				}
				if ( $res->numRows() > 0 ) {
					$this->replaceTitles( $res, $target, $replacement, $useRegex );
				}
			}
		}
	}
}

$maintClass = "ReplaceText";
require_once RUN_MAINTENANCE_IF_MAIN;
