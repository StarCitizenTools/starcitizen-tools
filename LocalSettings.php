<?php
# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
	exit;
}

#General Settings
$wgSitename = "Star Citizen";
$wgMetaNamespace = "Star_Citizen";
$wgAllowSiteCSSOnRestrictedPages = true;

## The URL base path to the directory containing the wiki;
## defaults for all runtime URL paths are based off of this.
## For more information on customizing the URLs
## (like /w/index.php/Page_title to /wiki/Page_title) please see:
## https://www.mediawiki.org/wiki/Manual:Short_URL
$wgScriptPath = "";
$wgScriptExtension = "$wgScriptPath/index.php";
$wgRedirectScript   = "$wgScriptPath/redirect.php";
$wgArticlePath = "/$1";

$wgShowSQLErrors = false;
$wgDebugDumpSql = false;
$wgDebugComments = false;

## The protocol and server name to use in fully-qualified URLs
$wgServer = "https://starcitizen.tools";

## The URL path to static resources (images, scripts, etc.)
$wgResourceBasePath = $wgScriptPath;

## The URL path to the logo.  Make sure you change this from the default,
## or else you'll overwrite your logo when you upgrade!
$wgLogo = "$wgResourceBasePath/resources/assets/sclogo.png";

## UPO means: this is also a user preference option
$wgEnableEmail = true;
$wgEnableUserEmail = true; # UPO

$wgEmergencyContact = "webmaster@starcitizen.tools";
$wgPasswordSender = "do-not-reply@starcitizen.tools";

$wgEnotifUserTalk = false; # UPO
$wgEnotifWatchlist = false; # UPO
$wgEmailAuthentication = true;

# MySQL table options to use during installation or update
$wgDBTableOptions = "ENGINE=InnoDB, DEFAULT CHARSET=utf8";

# Experimental charset support for MySQL 5.0.
$wgDBmysql5 = false;

## Shared memory settings
$wgMainCacheType = 'redis';
$wgSessionCacheType = 'redis';
$wgMemCachedServers = array();

## To enable image uploads, make sure the 'images' directory
## is writable, then set this to true:
$wgEnableUploads = true;
$wgGenerateThumbnailOnParse = true;
$wgUseImageMagick = true;
$wgImageMagickConvertCommand = "/usr/bin/convert";

$wgMaxImageArea = 6.4e7;

# InstantCommons allows wiki to use images from https://commons.wikimedia.org
$wgUseInstantCommons = false;

## If you use ImageMagick (or any other shell command) on a
## Linux server, this will need to be set to the name of an
## available UTF-8 locale
$wgShellLocale = "en_US.utf8";

## If you want to use image uploads under safe mode,
## create the directories images/archive, images/thumb and
## images/temp, and make them all writable. Then uncomment
## this, if it's not already uncommented:
#$wgHashedUploadDirectory = false;

## Set $wgCacheDirectory to a writable directory on the web server
## to make your wiki go slightly faster. The directory should not
## be publically accessible from the web.
$wgCacheDirectory = "$IP/cache";

# Site language code, should be one of the list in ./languages/Names.php
$wgLanguageCode = "en";

## For attaching licensing metadata to pages, and displaying an
## appropriate copyright notice / icon. GNU Free Documentation
## License and Creative Commons licenses are supported so far.
$wgRightsPage = ""; # Set to the title of a wiki page that describes your license/copyright
$wgRightsUrl = "https://creativecommons.org/licenses/by-sa/4.0/";
$wgRightsText = "Creative Commons Attribution-ShareAlike";
$wgRightsIcon = "$wgResourceBasePath/resources/assets/licenses/cc-by-sa.png";
$wgFavicon = "$wgScriptPath/favicon.ico";

# Path to the GNU diff3 utility. Used for conflict resolution.
$wgDiff3 = "/usr/bin/diff3";

# The following permissions were set based on your choice in the installer
$wgAllowUserCss = true;

## Default skin: you can change the default skin. Use the internal symbolic
## names, ie 'vector', 'monobook':
$wgDefaultSkin = "vector";

# Enabled skins.
# The following skins were automatically enabled:
wfLoadSkin( 'Vector' );
#wfLoadSkin( 'Citizen' );

#Maintenance
#$wgReadOnly = 'Maintenance is underway. Website is on read-only mode';

#SVG Support
$wgFileExtensions[] = 'svg';
$wgAllowTitlesInSVG = true;
$wgSVGConverter = 'ImageMagick';

#Javascript
$wgResourceModules['WaveJS'] = array(
    'scripts' => array('waves.min.js'),
);

function onBeforePageDisplay( OutputPage &$out, Skin &$skin )
{
    $script = '<script></script>';
    $out->addHeadItem("head script", $script);
    $out->addModules('WaveJS');
    return true;
};

$wgHooks['BeforePageDisplay'][] ='onBeforePageDisplay';

#=============================================== External Includes ===============================================

require_once("/home/www-data/external_includes/mysql_pw.php");
require_once("/home/www-data/external_includes/secret_keys.php");

#=============================================== Extension Load ===============================================

wfLoadExtension( 'ParserFunctions' );
wfLoadExtension( 'EmbedVideo' );
wfLoadExtension( 'MsUpload' );
wfLoadExtension( 'InputBox' );
wfLoadExtension( 'WikiSEO' );
wfLoadExtension( 'Cite' );
wfLoadExtension("DynamicPageList");
wfLoadExtension( 'Nuke' );
wfLoadExtension( 'CommonsMetadata' );
wfLoadExtension( 'ReplaceText' );
wfLoadExtension( 'TextExtracts' );
wfLoadExtension( 'Popups' );
wfLoadExtension( 'RevisionSlider' );
wfLoadExtension( 'CheckUser' );
wfLoadExtension( 'Babel' );
wfLoadExtension( 'cldr' );
#wfLoadExtension( 'CleanChanges' );
wfLoadExtension( 'LocalisationUpdate' );
wfLoadExtension( 'UniversalLanguageSelector' );
wfLoadExtensions( array( 'ConfirmEdit', 'ConfirmEdit/ReCaptchaNoCaptcha' ) );
wfLoadExtension( 'CodeMirror' );
require_once "$IP/extensions/CSS/CSS.php";
require_once "$IP/extensions/Tabber/Tabber.php";
require_once "$IP/extensions/RSS/RSS.php";
require_once "$IP/extensions/PageImages/PageImages.php";
require_once "$IP/extensions/MultimediaViewer/MultimediaViewer.php";
require_once "$IP/extensions/Echo/Echo.php";
require_once "$IP/extensions/Flow/Flow.php";
require_once "$IP/extensions/Translate/Translate.php";
require_once "$IP/extensions/googleAnalytics/googleAnalytics.php";
require_once "$IP/extensions/VisualEditor/VisualEditor.php";
require_once "$IP/extensions/Scribunto/Scribunto.php";
require_once( "$IP/extensions/UploadWizard/UploadWizard.php" );
require_once "$IP/extensions/EventLogging/EventLogging.php";
require_once "$IP/extensions/ExternalData/ExternalData.php";
require_once "$IP/extensions/Renameuser/Renameuser.php";
require_once "$IP/extensions/NativeSvgHandler/NativeSvgHandler.php";
#require_once "$IP/extensions/Lazyload/Lazyload.php";
#require_once "$IP/extensions/Antispam/Antispam.php";

#=============================================== Extension Config ===============================================

#Flow
$wgFlowEditorList = array( 'visualeditor', 'none' );
$wgFlowContentFormat = 'html';

#UploadWizard
$wgApiFrameOptions = 'SAMEORIGIN';
$wgAllowCopyUploads = true;
$wgCopyUploadsDomains = array( '*.flickr.com', '*.staticflickr.com' );
$wgUploadNavigationUrl = '/Special:UploadWizard';
$wgUploadWizardConfig = array(
	'debug' => false,
	'altUploadForm' => 'Special:Upload',
	'fallbackToAltUploadForm' => false,
	'enableFormData' => true,
	'enableMultipleFiles' => true,
	'enableMultiFileSelect' => false,
	'tutorial' => array(
	 	'skip' => true
		),
	'maxUploads' => 15,
	'fileExtensions' => $wgFileExtensions,
	'flickrApiUrl' => 'https://secure.flickr.com/services/rest/?',
	);

#TextExtracts
$wgExtractsRemoveClasses[] = 'dd';
$wgExtractsRemoveClasses[] = 'dablink';
$wgExtractsRemoveClasses[] = 'translate';

#MsUpload
$wgMSU_useDragDrop = true;
$wgMSU_showAutoCat = true;

#MultimediaViewer
$wgMediaViewerEnableByDefault = true;
$wgMediaViewerEnableByDefaultForAnonymous = true;

#ConfirmEdit
$wgCaptchaClass = 'ReCaptchaNoCaptcha';
$wgCaptchaTriggers['edit']          = true;
$wgCaptchaTriggers['create']        = true;

#CleanChanges
$wgCCTrailerFilter = true;
$wgCCUserFilter = false;
$wgDefaultUserOptions['usenewrc'] = 1;

#Translate
$wgLocalisationUpdateDirectory = "$IP/cache";
$wgTranslateDocumentationLanguageCode = 'qqq';
$wgExtraLanguageNames['qqq'] = 'Message documentation'; # No linguistic content. Used for documenting messages
$wgTranslatePageTranslationULS = true; # Display localized page based on ULS language

$wgTranslateBlacklist = array(
    '*' => array( // All groups
      'en' => 'English is the source language.',
      'zh-cn' => 'This langauge is disabled.',
      'zh-sg' => 'This langauge is disabled.',
      'zh-hk' => 'This langauge is disabled.',
      'zh-mo' => 'This langauge is disabled.',
      'zh-tw' => 'This langauge is disabled.',
      'zh-yue' => 'This langauge is disabled.',
      'zh-my' => 'This langauge is disabled.',
      'zh' => 'This langauge is disabled.',
    ),
);

#Google Analytics
$wgGoogleAnalyticsAccount = 'UA-48789297-5';

#ExternalData
# $edgCacheTable = 'ed_url_cache'; Need to run ExternalData.sql first
$edgCacheExpireTime = 3 * 24 * 60 * 60;
$edgAllowExternalDataFrom = array('https://starcitizen.tools','http://starcitizendb.com/','http://pledgetrack.rabbitsraiders.net');
$edgExternalValueVerbose = false;

#Visual Editor
$wgDefaultUserOptions['visualeditor-enable'] = 1;
$wgHiddenPrefs[] = 'visualeditor-enable';

#Eventlogging
$wgEventLoggingBaseUri = 'https://starcitizen.tools:8080/event.gif';
$wgEventLoggingFile = '/var/log/mediawiki/events.log';

#Scribunto
$wgScribuntoDefaultEngine = 'luasandbox';

#Echo
$wgAllowHTMLEmail = true;

#Redis
/** @see RedisBagOStuff for a full explanation of these options. **/
$wgObjectCaches['redis'] = array(
    'class'                => 'RedisBagOStuff',
    'servers'              => array( '127.0.0.1:6379' ),
    // 'connectTimeout'    => 1,
    // 'persistent'        => false,
    // 'password'          => 'secret',
    // 'automaticFailOver' => true,
);

#$wgJobTypeConf['default'] = array(
#  'class'          => 'JobQueueRedis',
#  'redisServer'    => '127.0.0.1:6379',

#  'claimTTL'       => 3600
#);

#parsoid
$wgVirtualRestConfig['modules']['parsoid'] = array(
  // URL to the Parsoid instance
  // Use port 8142 if you use the Debian package
  'url' => 'http://localhost:8142',
  'domain' => 'localhost'
);

#CodeMirror
$wgDefaultUserOptions['usecodemirror'] = 0;

#=============================================== Namespaces ===============================================
define("NS_COMMLINK", 3000);
define("NS_COMMLINK_TALK", 3001);
$wgExtraNamespaces[NS_COMMLINK] = "Comm-Link";
$wgExtraNamespaces[NS_COMMLINK_TALK] = "Comm-Link_talk";
$wgNamespacesWithSubpages[NS_COMMLINK] = true;
$wgNamespacesToBeSearchedDefault[NS_COMMLINK] = true;
$wgNamespaceContentModels[NS_COMMLINK_TALK] = CONTENT_MODEL_FLOW_BOARD;

define("NS_PROJMGMT", 3002);
define("NS_PROJMGMT_TALK", 3003);
$wgExtraNamespaces[NS_PROJMGMT] = "ProjMGMT";
$wgExtraNamespaces[NS_PROJMGMT_TALK] = "ProjMGMT_talk";
$wgNamespacesWithSubpages[NS_PROJMGMT] = true;
#$wgNamespacesToBeSearchedDefault[NS_PROJMGMT] = true;
$wgNamespaceContentModels[NS_PROJMGMT_TALK] = CONTENT_MODEL_FLOW_BOARD;

define("NS_ISSUE", 3004);
define("NS_ISSUE_TALK", 3005);
$wgExtraNamespaces[NS_ISSUE] = "Issue";
$wgExtraNamespaces[NS_ISSUE_TALK] = "Issue_talk";
$wgNamespacesWithSubpages[NS_ISSUE] = true;
#$wgNamespacesToBeSearchedDefault[NS_ISSUE] = true;
$wgNamespaceContentModels[NS_ISSUE_TALK] = CONTENT_MODEL_FLOW_BOARD;

define("NS_GUIDE", 3006);
define("NS_GUIDE_TALK", 3007);
$wgExtraNamespaces[NS_GUIDE] = "Guide";
$wgExtraNamespaces[NS_GUIDE_TALK] = "Guide_talk";
$wgNamespacesWithSubpages[NS_GUIDE] = true;
$wgNamespacesToBeSearchedDefault[NS_GUIDE] = true;
$wgNamespaceContentModels[NS_GUIDE_TALK] = CONTENT_MODEL_FLOW_BOARD;

define("NS_ORG", 3008);
define("NS_ORG_TALK", 3009);
$wgExtraNamespaces[NS_ORG] = "ORG";
$wgExtraNamespaces[NS_ORG_TALK] = "ORG_talk";
$wgNamespacesWithSubpages[NS_ORG] = true;
#$wgNamespacesToBeSearchedDefault[NS_ORG] = true;
$wgNamespaceContentModels[NS_ORG_TALK] = CONTENT_MODEL_FLOW_BOARD;

define("NS_EVENT", 3010);
define("NS_EVENT_TALK", 3011);
$wgExtraNamespaces[NS_EVENT] = "EVENT";
$wgExtraNamespaces[NS_EVENT_TALK] = "EVENT_talk";
$wgNamespacesWithSubpages[NS_EVENT] = true;
#$wgNamespacesToBeSearchedDefault[NS_EVENT] = true;
$wgNamespaceContentModels[NS_EVENT_TALK] = CONTENT_MODEL_FLOW_BOARD;

# Citizen Star News Archive project
define("NS_CSN", 3012);
define("NS_CSN_TALK", 3013);
$wgExtraNamespaces[NS_CSN] = "CSN";
$wgExtraNamespaces[NS_CSN_TALK] = "CSN_talk";
$wgNamespacesWithSubpages[NS_CSN] = true;
$wgNamespacesToBeSearchedDefault[NS_CSN] = true;
$wgNamespaceContentModels[NS_CSN_TALK] = CONTENT_MODEL_FLOW_BOARD;

$wgExtraNamespaces[$wgPageTranslationNamespace]   = 'Translations';
$wgExtraNamespaces[$wgPageTranslationNamespace+1] = 'Translations_talk';

$wgNamespaceContentModels[NS_TALK] = CONTENT_MODEL_FLOW_BOARD;
$wgNamespaceContentModels[NS_USER_TALK] = CONTENT_MODEL_FLOW_BOARD;
$wgNamespaceContentModels[NS_COMMLINK_TALK] = CONTENT_MODEL_FLOW_BOARD;
$wgNamespaceContentModels[NS_PROJECT_TALK] = CONTENT_MODEL_FLOW_BOARD;
$wgNamespaceContentModels[NS_FILE_TALK] = CONTENT_MODEL_FLOW_BOARD;
$wgNamespaceContentModels[NS_MEDIAWIKI_TALK] = CONTENT_MODEL_FLOW_BOARD;
$wgNamespaceContentModels[NS_TEMPLATE_TALK] = CONTENT_MODEL_FLOW_BOARD;
$wgNamespaceContentModels[NS_HELP_TALK] = CONTENT_MODEL_FLOW_BOARD;
$wgNamespaceContentModels[NS_CATEGORY_TALK] = CONTENT_MODEL_FLOW_BOARD;
$wgNamespaceContentModels[$wgPageTranslationNamespace+1] = CONTENT_MODEL_FLOW_BOARD;

$wgNamespaceProtection[NS_TEMPLATE] = array( 'template-edit' );
$wgNamespaceProtection[NS_COMMLINK] = array( 'commlink-edit' );
$wgNamespaceProtection[NS_PROJMGMT] = array( 'projmgmt-edit' );
$wgNamespaceProtection[NS_ISSUE] = array( 'issue-edit' );
$wgNamespaceProtection[NS_GUIDE] = array( 'guide-edit' );
$wgNamespaceProtection[NS_ORG] = array( 'org-edit' );
$wgNamespaceProtection[NS_EVENT] = array( 'event-edit' );

$wgVisualEditorAvailableNamespaces = array(
	NS_MAIN     => true,
	NS_USER     => true,
	NS_HELP     => true,
	NS_PROJECT  => true,
	NS_COMMLINK => true,
	NS_PROJMGMT => true,
	NS_ISSUE    => true,
	NS_GUIDE    => true,
	NS_ORG      => true,
	NS_EVENT    => true,
	NS_CSN    => true
);

#=============================================== Permissions ===============================================
$wgAutopromote = array(
	"autoconfirmed" => array( "&",
		array( APCOND_EDITCOUNT, &$wgAutoConfirmCount ),
		array( APCOND_AGE, &$wgAutoConfirmAge ),
    APCOND_EMAILCONFIRMED,
	),
  "Trusted" => array( "&",
    array( APCOND_EDITCOUNT, 300),
    array( APCOND_INGROUPS, "Verified"),
  ),
);

#all
$wgGroupPermissions['*']['createaccount'] = true;
$wgGroupPermissions['*']['edit'] = false;
$wgGroupPermissions['*']['createpage'] = false;
$wgGroupPermissions['*']['writeapi'] = false;
$wgGroupPermissions['*']['flow-hide'] = false;
$wgGroupPermissions['*']['createtalk'] = false;

#user
$wgGroupPermissions['user']['edit'] = true;
$wgGroupPermissions['user']['purge'] = false;
$wgGroupPermissions['user']['createpage'] = false;
$wgGroupPermissions['user']['createtalk'] = false;
$wgGroupPermissions['user']['minoredit'] = false;
$wgGroupPermissions['user']['move'] = false;
$wgGroupPermissions['user']['movefile'] = false;
$wgGroupPermissions['user']['move-categorypages'] = false;
$wgGroupPermissions['user']['move-rootuserpages'] = false;
$wgGroupPermissions['user']['move-subpages'] = false;
$wgGroupPermissions['user']['reupload'] = false;
$wgGroupPermissions['user']['reupload-own'] = false;
$wgGroupPermissions['user']['guide-edit'] = true;

#ORG Editor
$wgGroupPermissions['ORG-Editor']['org-edit'] = true;

#autoconfirmed
$wgAutoConfirmAge = 86400*3; // three days
$wgAutoConfirmCount = 20;
$wgGroupPermissions['autoconfirmed'] = $wgGroupPermissions['user'];
$wgGroupPermissions['autoconfirmed']['upload_by_url'] = true;
$wgGroupPermissions['autoconfirmed']['createpage'] = true;
$wgGroupPermissions['autoconfirmed']['createtalk'] = true;
$wgGroupPermissions['autoconfirmed']['edit'] = true;

#verified
$wgGroupPermissions['Verified'] = $wgGroupPermissions['autoconfirmed'];
$wgGroupPermissions['Verified']['skipcaptcha'] = true;
$wgGroupPermissions['Verified']['purge'] = true;
$wgGroupPermissions['Verified']['reupload'] = true;
$wgGroupPermissions['Verified']['reupload-own'] = true;
$wgGroupPermissions['Verified']['minoredit'] = true;
$wgGroupPermissions['Verified']['event-edit'] = true;

#translator
$wgGroupPermissions['Translator']['translate'] = true;
$wgGroupPermissions['Translator']['translate-messagereview'] = true;

#trusted
$wgGroupPermissions['Trusted'] = $wgGroupPermissions['Verified'];
$wgGroupPermissions['Trusted']['patrol'] = true;
$wgGroupPermissions['Trusted']['move'] = true;
$wgGroupPermissions['Trusted']['movefile'] = true;
$wgGroupPermissions['Trusted']['move-categorypages'] = true;
$wgGroupPermissions['Trusted']['writeapi'] = true;
$wgGroupPermissions['Trusted']['sendemail'] = true;
$wgGroupPermissions['Trusted']['commlink-edit'] = true;
$wgGroupPermissions['Trusted']['issue-edit'] = true;
$wgGroupPermissions['Trusted']['projmgmt-edit'] = true;
$wgGroupPermissions['Trusted']['move-subpages'] = true;

#editor
$wgGroupPermissions['Editor'] = $wgGroupPermissions['Trusted'];
$wgAddGroups['Editor'] = array( 'Verified', 'Translator', 'ORG-Editor' );
$wgGroupPermissions['Editor']['template-edit'] = true;
$wgGroupPermissions['Editor']['rollback'] = true;
$wgGroupPermissions['Editor']['protect'] = true;
$wgGroupPermissions['Editor']['editprotected'] = true;
$wgGroupPermissions['Editor']['suppressredirect'] = true;
$wgGroupPermissions['Editor']['autopatrol'] = true;
$wgGroupPermissions['Editor']['checkuser'] = true;
$wgGroupPermissions['Editor']['translate-proofr'] = true;
$wgGroupPermissions['Editor']['translate-manage'] = true;
$wgGroupPermissions['Editor']['translate'] = true;
$wgGroupPermissions['Editor']['pagetranslation'] = true;
$wgGroupPermissions['Editor']['translate-groupreview'] = true;
$wgGroupPermissions['Editor']['delete'] = true;
$wgGroupPermissions['Editor']['bigdelete'] = true;
$wgGroupPermissions['Editor']['deletedhistory'] = true;
$wgGroupPermissions['Editor']['deletedtext'] = true;
$wgGroupPermissions['Editor']['block'] = true;
$wgGroupPermissions['Editor']['undelete'] = true;
$wgGroupPermissions['Editor']['mergehistory'] = true;
$wgGroupPermissions['Editor']['browsearchive'] = true;
$wgGroupPermissions['Editor']['noratelimit'] = true;
$wgGroupPermissions['Editor']['flow-delete'] = true;
$wgGroupPermissions['Editor']['flow-lock'] = true;
$wgGroupPermissions['Editor']['move-rootuserpages'] = true;
$wgGroupPermissions['Editor']['org-edit'] = true;

#sysop
$wgGroupPermissions['sysop'] = $wgGroupPermissions['Editor'];
$wgGroupPermissions['sysop']['userrights'] = true;
$wgGroupPermissions['sysop']['siteadmin'] = true;
$wgGroupPermissions['sysop']['checkuser-log'] = true;
$wgGroupPermissions['sysop']['nuke'] = true;
$wgGroupPermissions['sysop']['editinterface'] = true;
$wgGroupPermissions['sysop']['delete'] = true;
$wgGroupPermissions['sysop']['renameuser'] = true;
