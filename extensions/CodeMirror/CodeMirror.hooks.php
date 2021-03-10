<?php

class CodeMirrorHooks {

	/** @var null|array Cached version of global variables, if available, otherwise null */
	private static $globalVariableScript = null;
	/** @var null|boolean Saves, if CodeMirror should be loaded on this page or not */
	private static $isEnabled = null;
	/** @var array values passed from other extensions for use in self::getGlobalVariables() */
	private static $extModes = array();

	/**
	 * ResourceLoaderRegisterModules hook handler to conditionally register CodeMirror modules
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderRegisterModules
	 *
	 * @param ResourceLoader &$rl The ResourceLoader object
	 *
	 * @return bool Always true
	 */
	public static function onResourceLoaderRegisterModules( ResourceLoader $rl ) {
		$codeMirrorResourceTemplate = array(
			'localBasePath' => __DIR__ . '/resources',
			'remoteExtPath' => 'CodeMirror/resources',
		);

		self::$extModes = array(
			'tag' => array(
				'pre' => 'mw-tag-pre',
				'nowiki' => 'mw-tag-nowiki',
			),
			'func' => array(),
			'data' => array(),
		);
		$extResources = array(
			'scripts' => array(),
			'styles' => array(),
			'messages' => array(),
			'dependencies' => array( 'ext.CodeMirror.lib' => true ),
		);

		// Check if WikiEditor is installed and add it as a dependency
		// FIXME: Is there no better solution doing it?
		$resourceModules = $rl->getConfig()->get( 'ResourceModules' );
		if ( isset( $resourceModules['ext.wikiEditor'] ) ) {
			$extResources['dependencies']['ext.wikiEditor'] = true;
		}

		// enable other extensions to add additional resources and modes
		Hooks::run( 'CodeMirrorGetAdditionalResources', array( &$extResources, &self::$extModes ) );

		// Prepare array of resources for ResourceLoader
		$codeMirror = array(
			'scripts' => array_keys( $extResources['scripts'] ),
			'styles' => array_keys( $extResources['styles'] ),
			'messages' => array_keys( $extResources['messages'] ),
			'dependencies' => array_keys( $extResources['dependencies'] ),
			'group' => 'ext.CodeMirror',
		) + $codeMirrorResourceTemplate;

		$rl->register( array( 'ext.CodeMirror.other' => $codeMirror ) );

		return true;
	}

	/**
	 * Checks, if CodeMirror should be loaded on this page or not.
	 *
	 * @param IContextSource $context The current ContextSource object
	 * @return boolean
	 */
	private static function isCodeMirrorEnabled( IContextSource $context ) {
		global $wgCodeMirrorEnableFrontend;

		// Check, if we already checked, if page action is editing, if not, do it now
		if ( is_null( self::$isEnabled ) ) {
			// edit can be 'edit' and 'submit'
			self::$isEnabled = $wgCodeMirrorEnableFrontend &&
				in_array(
					Action::getActionName( $context ),
					array( 'edit', 'submit' )
				);
		}

		return self::$isEnabled;

	}

	/**
	 * Returns an array of variables for CodeMirror to work (tags and so on)
	 *
	 * @param IContextSource $context The current ContextSource object
	 * @return array
	 */
	public static function getGlobalVariables( IContextSource $context ) {
		global $wgParser;

		// if we already created these variable array, return it
		if ( !self::$globalVariableScript ) {
			$contObj = $context->getLanguage();

			if ( !isset( $wgParser->mFunctionSynonyms ) ) {
				$wgParser->initialiseVariables();
				$wgParser->firstCallInit();
			}

			// initialize global vars
			$globalVariableScript = array(
				'ExtModes' => self::$extModes,
				'Tags' => array_fill_keys( $wgParser->getTags(), true ),
				'DoubleUnderscore' => array( array(), array() ),
				'FunctionSynonyms' => $wgParser->mFunctionSynonyms,
				'UrlProtocols' => $wgParser->mUrlProtocols,
				'LinkTrailCharacters' =>  $contObj->linkTrail(),
			);

			$mw = $contObj->getMagicWords();
			foreach ( MagicWord::getDoubleUnderscoreArray()->names as $name ) {
				if ( isset( $mw[$name] ) ) {
					$caseSensitive = array_shift( $mw[$name] ) == 0 ? 0 : 1;
					foreach ( $mw[$name] as $n ) {
						$globalVariableScript['DoubleUnderscore'][$caseSensitive][ $caseSensitive ? $n : $contObj->lc( $n ) ] = $name;
					}
				} else {
					$globalVariableScript['DoubleUnderscore'][0][] = $name;
				}
			}

			foreach ( MagicWord::getVariableIDs() as $name ) {
				if ( isset( $mw[$name] ) ) {
					$caseSensitive = array_shift( $mw[$name] ) == 0 ? 0 : 1;
					foreach ( $mw[$name] as $n ) {
						$globalVariableScript['FunctionSynonyms'][$caseSensitive][ $caseSensitive ? $n : $contObj->lc( $n ) ] = $name;
					}
				}
			}

			// prefix all variables and save it into class variable
			foreach ( $globalVariableScript as $key=> $value ) {
				self::$globalVariableScript["extCodeMirror$key"] = $value;
			}
		}

		return self::$globalVariableScript;
	}

	/**
	 * MakeGlobalVariablesScript hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/MakeGlobalVariablesScript
	 *
	 * @param array $vars
	 * @param OutputPage $out
	 *
	 * @return bool Always true
	 */
	public static function onMakeGlobalVariablesScript( array &$vars, OutputPage $out ) {
		$context = $out->getContext();
		// add CodeMirror vars only for edit pages
		if ( self::isCodeMirrorEnabled( $context ) ) {
			$vars += self::getGlobalVariables( $context );
		}
	}

	/**
	 * BeforePageDisplay hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 *
	 * @return bool Always true
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		if ( self::isCodeMirrorEnabled( $out->getContext() ) ) {
			$out->addModules( 'ext.CodeMirror.init' );
		}
	}

	/**
	 * GetPreferences hook handler
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetPreferences
	 *
	 * @param User $user
	 * @param array $defaultPreferences
	 *
	 * @return bool Always true
	 */
	public static function onGetPreferences( User $user, &$defaultPreferences ) {
		// CodeMirror is enabled by default for users.
		// It can be changed by adding '$wgDefaultUserOptions['usecodemirror'] = 0;' into LocalSettings.php
		$defaultPreferences['usecodemirror'] = array(
			'type' => 'api',
			'default' => '1',
		);
		return true;
	}

}
