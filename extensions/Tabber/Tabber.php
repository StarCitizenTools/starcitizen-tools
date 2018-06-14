<?php
/**
 * Tabber
 * Tabber Main File
 *
 * @author		Eric Fortin, Alexia E. Smith
 * @license		GPL
 * @package		Tabber
 * @link		https://www.mediawiki.org/wiki/Extension:Tabber
 */
/******************************************/
/* Credits                                */
/******************************************/
$wgExtensionCredits['parserhook'][] = [
	'path'				=> __FILE__,
	'name'				=> 'Tabber',
	'author'			=> ['Eric Fortin', 'Alexia E. Smith'],
	'url'				=> 'https://www.mediawiki.org/wiki/Extension:Tabber',
	'descriptionmsg'	=> 'tabber-desc',
	'version'			=> '2.4'
];

/******************************************/
/* Language Strings, Hooks  */
/******************************************/
$wgMessagesDirs['Tabber']					= __DIR__ . '/i18n';
$wgAutoloadClasses['TabberHooks']			= __DIR__ . '/Tabber.hooks.php';
$wgHooks['ParserFirstCallInit'][]			= 'TabberHooks::onParserFirstCallInit';
$wgResourceModules['ext.tabber']			= [
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'Tabber',
	'styles'		=> ['css/tabber.css'],
	'scripts'		=> ['js/tabber.js']
];
