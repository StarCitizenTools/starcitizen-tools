<?php
/**
 * ResourceLoader ext.CodeMirror module
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
 * @file
 */

/**
 * ResourceLoader module for ext.CodeMirror
 */
class ResourceLoaderCodeMirrorModule extends ResourceLoaderFileModule {
	/**
	 * @inheritDoc
	 */
	public function getScript( ResourceLoaderContext $context ) {
		return ResourceLoader::makeConfigSetScript(
				[ 'extCodeMirrorConfig' => $this->getFrontendConfiguraton() ]
			)
			. "\n"
			. parent::getScript( $context );
	}

	/**
	 * @inheritDoc
	 */
	public function supportsURLLoading() {
		// This module does not support loading URLs, because it inserts
		// JS config vars into the module by the getScript function.
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function enableModuleContentVersion() {
		return true;
	}

	/**
	 * Returns an array of variables for CodeMirror to work (tags and so on)
	 *
	 * @global Parser $wgParser
	 * @global Language $wgContLang
	 * @return array
	 */
	private function getFrontendConfiguraton() {
		global $wgParser, $wgContLang;

		// Use the content language, not the user language. (See T170130.)
		$lang = $wgContLang;
		$registry = ExtensionRegistry::getInstance();

		if ( !isset( $wgParser->mFunctionSynonyms ) ) {
			$wgParser->initialiseVariables();
			$wgParser->firstCallInit();
		}

		// initialize configuration
		$config = [
			'pluginModules' => $registry->getAttribute( 'CodeMirrorPluginModules' ),
			'tagModes' => $registry->getAttribute( 'CodeMirrorTagModes' ),
			'tags' => array_fill_keys( $wgParser->getTags(), true ),
			'doubleUnderscore' => [ [], [] ],
			'functionSynonyms' => $wgParser->mFunctionSynonyms,
			'urlProtocols' => $wgParser->mUrlProtocols,
			'linkTrailCharacters' => $lang->linkTrail(),
		];

		$mw = $lang->getMagicWords();
		foreach ( MagicWord::getDoubleUnderscoreArray()->names as $name ) {
			if ( isset( $mw[$name] ) ) {
				$caseSensitive = array_shift( $mw[$name] ) == 0 ? 0 : 1;
				foreach ( $mw[$name] as $n ) {
					$n = $caseSensitive ? $n : $lang->lc( $n );
					$config['doubleUnderscore'][$caseSensitive][$n] = $name;
				}
			} else {
				$config['doubleUnderscore'][0][] = $name;
			}
		}

		foreach ( MagicWord::getVariableIDs() as $name ) {
			if ( isset( $mw[$name] ) ) {
				$caseSensitive = array_shift( $mw[$name] ) == 0 ? 0 : 1;
				foreach ( $mw[$name] as $n ) {
					$n = $caseSensitive ? $n : $lang->lc( $n );
					$config['functionSynonyms'][$caseSensitive][$n] = $name;
				}
			}
		}

		return $config;
	}
}
