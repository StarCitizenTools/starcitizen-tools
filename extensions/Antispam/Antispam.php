<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die;
}

require_once('cleantalk.class.php' );

/**
 * cleantalk.org is a cloud antispam service 
 *
 * @see http://stopforumspam.com/faq
 * @author Denis Shagimuratov <shagimuratov@cleantalk.org>
 * @license GPL v2 or higher
 */

/**
 * Access key for cleantalk.org
 * @see https://clenatalk.org/register
 */
$wgCTAccessKey = 'e8a4e4yhe7u4';

/**
 * Check registrations, when there is errors with connection to service
 * true - option enabled
 * false - option disabled
 */
$wgCTCheckNoConnect = true;

/**
 * Cloud URL 
 */
$wgCTServerURL = 'http://moderate.cleantalk.org/api2.0';

/**
 * Extension agent name and version 
 */
$wgCTAgent = 'mediawiki-17';

/**
 * Extension name 
 */
$wgCTExtName = 'Anti-spam by CleanTalk';

/**
 * Field name for JavaScript test 
 */
$wgCTHiddenFieldName = 'ct_checkjs';

/**
 * Field name for JavaScript test 
 */
$wgCTSubmitTimeLabel = 'ct_submit_time';

/**
 * Default value for hidden field 
 */
$wgCTHiddenFieldDefault = 0;

/**
 * Admin notificaction account ID 
 */
$wgCTAdminAccountId = 1;

/**
 * Admin notificactions interval in seconds
 */
$wgCTAdminNotificaionInteval = 10800;

/**
 * Show link to CleanTalk
 * Enabling this option places a small link under the comment form that lets others know what anti-spam tool protects your site.
 */
$wgCTShowLink = true;

/**
 * SpamFireWall
 * Enabling this option makes SpamFileWall feature active.
 */
$wgCTSFW = false;

/**
 * Edit new edits only
 * Check all edits or new edits only.
 */
$wgCTNewEditsOnly = false;

/**
 * Minimal edits count to skip edit checking
 * Checking will skipped for users with getEditCount() more than this value.
 * It doesn'f affect when $wgCTNewEditsOnly == true
 */
$wgCTMinEditCount = 10;

/**
 * Extension settings store file 
 */
$wgCTDataStoreFile = __DIR__ . '/Antispam.store.dat';

$wgExtensionCredits['antispam'][] = array(
	'path' => __FILE__,
	'name' => $wgCTExtName, 
	'author' => 'Denis Shagimuratov',
	'url' => 'https://www.mediawiki.org/wiki/Extension:Antispam',
	'descriptionmsg' => 'cleantalk-desc',
	'version' => '1.7',
);

$wgAutoloadClasses['CTBody'] = __DIR__ . '/Antispam.body.php';
$wgAutoloadClasses['CTHooks'] = __DIR__ . '/Antispam.hooks.php';

$wgHooks['AbortNewAccount'][] = 'CTHooks::onAbortNewAccount';
$wgHooks['UserCreateForm'][] = 'CTHooks::onUserCreateForm';

$wgHooks['EditFilter'][] = 'CTHooks::onEditFilter';
$wgHooks['EditPage::showEditForm:initial'][] = 'CTHooks::onShowEditForm';

$wgHooks['UploadForm:initial'][] = 'CTHooks::onShowUploadForm';
$wgHooks['UploadVerifyFile'][] = 'CTHooks::onUploadFilter';

// Skip edit test for Administrators
$wgGroupPermissions['sysop']['cleantalk-bypass'] = true;

$wgHooks["SkinAfterBottomScripts"][] = "CTHooks::onSkinAfterBottomScripts";
