<?php
# Protect against web entry
if ( !defined( 'MEDIAWIKI' ) ) {
  exit;
}

/* DEBUG ONLY */
$wgShowExceptionDetails = true;

#Tidy HTML output
$wgUseTidy = true;
$wgTidyConfig = [ 'driver' => 'RemexHtml' ];

#General Settings
$wgSitename = "Star Citizen Wiki";
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
#$wgServer = ""; NOW PLACED IN EXTERNAL INCLUDES FOLDER

## Enable strict referrer policy
$wgReferrerPolicy = array('strict-origin-when-cross-origin', 'strict-origin');

## Output a canonical meta tag on every page
$wgEnableCanonicalServerLink = true;

## The URL path to static resources (images, scripts, etc.)
$wgResourceBasePath = $wgScriptPath;

## The URL path to the logo.  Make sure you change this from the default,
## or else you'll overwrite your logo when you upgrade!
$wgLogo = "$wgResourceBasePath/resources/assets/sitelogo.png";
$wgLogoHD = [
  "svg" => "$wgResourceBasePath/resources/assets/sitelogo.svg"
];
$wgFavicon = "$wgResourceBasePath/resources/assets/favicon.ico";
$wgAppleTouchIcon = "$wgResourceBasePath/resources/assets/apple-touch-icon.png";

## UPO means: this is also a user preference option
$wgEnableEmail = true;
$wgEnableUserEmail = true; # UPO

$wgEmergencyContact = "webmaster@starcitizen.tools";
$wgPasswordSender = "do-not-reply@starcitizen.tools";

$wgEnotifUserTalk = false; # UPO
$wgEnotifWatchlist = false; # UPO
$wgEmailAuthentication = true;

# Disable the real name field
$wgHiddenPrefs[] = 'realname';

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
# Use 404 handler to generate images on client request
$wgGenerateThumbnailOnParse = false;
$wgLocalFileRepo['transformVia404'] = true;
#$wgThumbnailScriptPath = "{$wgScriptPath}/thumb.php";
$wgUseImageMagick = true;
$wgThumbnailEpoch = "20190815000000";
$wgThumbnailScriptPath = false;
$wgIgnoreImageErrors = true;

$wgDefaultUserOptions['imagesize'] = 4; // image size 1280, 1024

$wgThumbLimits = array(
  120, // thumb size 1
  150, // thumb size 2
  180, // thumb size 3
  200, // thumb size 4
  250, // thumb size 5
  300 // thumb size 6
);

$wgDefaultUserOptions['thumbsize'] = 5; // thumb size 300

$wgMaxImageArea = 6.4e7;

# Gallery settings
$wgGalleryOptions = [
  'imagesPerRow' => 0, // Default number of images per-row in the gallery. 0: Adapt to screensize
  'imageWidth' => 180, // Width of the cells containing images in galleries (in "px")
  'imageHeight' => 180, // Height of the cells containing images in galleries (in "px")
  'captionLength' => true, // Length of caption to truncate (in characters) in special pages or when the showfilename parameter is used
                           // A value of 'true' will truncate the filename to one line using CSS.
                           // Deprecated since 1.28. Default value of 25 before 1.28.
  'showBytes' => true, // Show the filesize in bytes in categories
    'mode' => 'packed', // One of "traditional", "nolines", "packed", "packed-hover", "packed-overlay", "slideshow" (1.28+)
];

# InstantCommons allows wiki to use images from https://commons.wikimedia.org
$wgUseInstantCommons = true;

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

# Path to the GNU diff3 utility. Used for conflict resolution.
$wgDiff3 = "/usr/bin/diff3";

# The following permissions were set based on your choice in the installer
$wgAllowUserCss = true;

## Default skin: you can change the default skin. Use the internal symbolic
## names, ie 'vector', 'monobook':
$wgDefaultSkin = 'citizen';

# Enabled skins.
# The following skins were automatically enabled:
wfLoadSkin( 'Citizen' );

# Citizen skin config
# Enable Preconnect for the defined domain
$wgCitizenEnablePreconnect = true;
$wgCitizenPreconnectURL = 'https://www.google-analytics.com';
# CSP
$wgCitizenEnableCSP = true;
$wgCitizenCSPDirective = 'default-src \'none\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://commons.wikimedia.org https://www.mediawiki.org https://ajax.cloudflare.com/ https://*.starcitizen.tools https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/ https://www.google-analytics.com https://ssl.google-analytics.com; style-src \'self\' \'unsafe-inline\' https://*.starcitizen.tools https://commons.wikimedia.org https://www.mediawiki.org; img-src \'self\' data: https://www.google-analytics.com; font-src \'self\'; connect-src \'self\' https://*.starcitizen.tools https://www.google-analytics.com; manifest-src \'self\'; frame-src https://www.google.com/recaptcha/ https://www.youtube.com; frame-ancestors \'none\'; form-action \'self\'; upgrade-insecure-requests; base-uri \'self\'';
# HSTS
$wgCitizenEnableHSTS = true;
$wgCitizenHSTSMaxAge = 63072000; # 2 year
$wgCitizenHSTSIncludeSubdomains = true;
#$wgCitizenHSTSPreload = true;
# Enable the deny X-Frame-Options header
$wgCitizenEnableDenyXFrameOptions = true;
# Enable X-XSS-Protection header
$wgCitizenEnableXXSSProtection = true;
# Enable strict-origin-when-cross-origin referrer policy	
$wgCitizenEnableStrictReferrerPolicy = true;
# Feature policy
$wgCitizenEnableFeaturePolicy = true;
$wgCitizenFeaturePolicyDirective = 'autoplay \'none\'; camera \'none\'; fullscreen \'self\'; geolocation \'none\'; microphone \'none\'; midi \'none\'; payment \'none\'' ;
# FAB
$wgCitizenEnableButton = true;
$wgCitizenButtonLink = 'https://discord.gg/3kjftWK';
$wgCitizenButtonTitle = 'Contact us on Discord';
$wgCitizenButtonText = 'Discord';
# Page tools
$wgCitizenShowPageTools = 'login'; #Only show page tools if logged in
# Search description source
$wgCitizenSearchDescriptionSource = 'wikidata';
# Number of search results in suggestion
$wgCitizenMaxSearchResults = 6;

#Maintenance
#$wgReadOnly = 'Maintenance is underway. Website is on read-only mode';

#SVG Support
$wgFileExtensions[] = 'svg';
$wgAllowTitlesInSVG = true;
$wgSVGConverter = 'ImageMagick';

#=============================================== External Includes ===============================================

require_once("/home/www-data/external_includes/mysql_pw.php");
require_once("/home/www-data/external_includes/secret_keys.php");

#=============================================== Extension Load ===============================================

wfLoadExtension( 'ParserFunctions' );
wfLoadExtension( 'EmbedVideo' );
#wfLoadExtension( 'MsUpload' ); - No longer used
wfLoadExtension( 'InputBox' );
wfLoadExtension( 'WikiSEO' );
wfLoadExtension( 'Cite' );
wfLoadExtension( 'DynamicPageList' );
wfLoadExtension( 'Nuke' );
wfLoadExtension( 'CommonsMetadata' );
wfLoadExtension( 'ReplaceText' );
wfLoadExtension( 'TextExtracts' );
wfLoadExtension( 'Popups' );
wfLoadExtension( 'GoogleAnalytics' );
wfLoadExtension( 'RevisionSlider' );
wfLoadExtension( 'CheckUser' );
wfLoadExtension( 'Babel' );
wfLoadExtension( 'cldr' );
#wfLoadExtension( 'CleanChanges' );
wfLoadExtension( 'LocalisationUpdate' );
wfLoadExtension( 'UniversalLanguageSelector' );
wfLoadExtensions( array( 'ConfirmEdit', 'ConfirmEdit/ReCaptchaNoCaptcha' ) );
wfLoadExtension( 'CodeMirror' );
wfLoadExtension( 'CookieWarning' );
wfLoadExtension( 'UploadWizard' );
wfLoadExtension( 'MultimediaViewer' );
wfLoadExtension( 'Echo' );
wfLoadExtension( 'Flow' );
wfLoadExtension( 'Tabber' );
wfLoadExtension( 'RSS' );
wfLoadExtension( 'TemplateData' );
wfLoadExtension( 'PageImages' );
#wfLoadExtension( 'RelatedArticles' );
wfLoadExtension( 'VisualEditor' );
wfLoadExtension( 'Scribunto' );
wfLoadExtension( 'EventLogging' );
wfLoadExtension( 'Renameuser' );
wfLoadExtension( 'ExternalData' );
wfLoadExtension( 'TemplateStyles' );
wfLoadExtension( 'Variables' );
wfLoadExtension( 'Loops' );
wfLoadExtension( 'ShortDescription' );
wfLoadExtension( 'NativeSvgHandler' );
require_once "$IP/extensions/Translate/Translate.php";
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
#$wgMSU_useDragDrop = true;
#$wgMSU_showAutoCat = true;

#MultimediaViewer
$wgMediaViewerEnableByDefault = true;
$wgMediaViewerEnableByDefaultForAnonymous = true;

#ConfirmEdit
$wgCaptchaClass = 'ReCaptchaNoCaptcha';
$wgCaptchaTriggers['edit']          = true;
$wgCaptchaTriggers['create']        = true;

#CleanChanges
#$wgCCTrailerFilter = true;
#$wgCCUserFilter = false;
#$wgDefaultUserOptions['usenewrc'] = 1;

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

#Universal Language Selector
$wgULSGeoService = false;

#Google Analytics
$wgGoogleAnalyticsAccount = 'UA-48789297-5';
# No bot group analytics.
$wgGroupPermissions['bot']['noanalytics'] = true;

#ExternalData
# $edgCacheTable = 'ed_url_cache'; Need to run ExternalData.sql first
# $wgHTTPTimeout = 60; Set HTTP request timeout to 60s
$edgCacheExpireTime = 3 * 24 * 60 * 60;
$edgAllowExternalDataFrom = array('https://starcitizen.tools','http://starcitizendb.com/', 'https://scwdev.czen.me');
$edgExternalValueVerbose = false;

#Visual Editor
$wgDefaultUserOptions['visualeditor-enable'] = 1;
$wgDefaultUserOptions['visualeditor-editor'] = "visualeditor";
$wgDefaultUserOptions['visualeditor-newwikitext'] = 1;
$wgPrefs[] = 'visualeditor-enable';
$wgVisualEditorEnableWikitext = true;
$wgVisualEditorEnableDiffPage = true;
$wgVisualEditorUseSingleEditTab = true;
$wgVisualEditorEnableVisualSectionEditing = true;

#RelatedArticles 
# $wgRelatedArticlesFooterWhitelistedSkins = [ 'citizen', 'vector', 'timeless' ];
# Enable when moved to 1.3.4
# $wgRelatedArticlesDescriptionSource = 'textextracts';
# Enable when CirrusSearch is installed
# $wgRelatedArticlesUseCirrusSearch = true;
# $wgRelatedArticlesOnlyUseCirrusSearch = true;

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

#CookieWarning
$wgCookieWarningEnabled = true;
$wgCookieWarningGeoIPLookup = none;

#DynamicPageList
$wgDplSettings['recursiveTagParse'] = true;

#TemplateStyles
$wgTemplateStylesAllowedUrls = [
  "audio" => [
    "<^https://starcitizen\\.tools/>",
    "<^https://scwdev\\.czen\\.me/>"
  ],
  "image" => [
    "<^https://starcitizen\\.tools/>",
    "<^https://scwdev\\.czen\\.me/>"
  ],
  "svg" => [
    "<^https://starcitizen\\.tools/[^?#]*\\.svg(?:[?#]|$)>",
    "<^https://scwdev\\.czen\\.me/[^?#]*\\.svg(?:[?#]|$)>"
  ],
  "font" => [
    "<^https://starcitizen\\.tools/>",
    "<^https://scwdev\\.czen\\.me/>"
  ],
  "namespace" => [
      "<.>"
  ],
  "css" => []
];

#=============================================== Namespaces ===============================================
define("NS_COMMLINK", 3000);
define("NS_COMMLINK_TALK", 3001);
$wgExtraNamespaces[NS_COMMLINK] = "Comm-Link";
$wgExtraNamespaces[NS_COMMLINK_TALK] = "Comm-Link_talk";
$wgNamespacesWithSubpages[NS_COMMLINK] = true;
$wgNamespacesToBeSearchedDefault[NS_COMMLINK] = true;

define("NS_PROJMGMT", 3002);
define("NS_PROJMGMT_TALK", 3003);
$wgExtraNamespaces[NS_PROJMGMT] = "ProjMGMT";
$wgExtraNamespaces[NS_PROJMGMT_TALK] = "ProjMGMT_talk";
$wgNamespacesWithSubpages[NS_PROJMGMT] = true;

define("NS_ISSUE", 3004);
define("NS_ISSUE_TALK", 3005);
$wgExtraNamespaces[NS_ISSUE] = "Issue";
$wgExtraNamespaces[NS_ISSUE_TALK] = "Issue_talk";
$wgNamespacesWithSubpages[NS_ISSUE] = true;

define("NS_GUIDE", 3006);
define("NS_GUIDE_TALK", 3007);
$wgExtraNamespaces[NS_GUIDE] = "Guide";
$wgExtraNamespaces[NS_GUIDE_TALK] = "Guide_talk";
$wgNamespacesWithSubpages[NS_GUIDE] = true;
$wgNamespacesToBeSearchedDefault[NS_GUIDE] = true;


define("NS_ORG", 3008);
define("NS_ORG_TALK", 3009);
$wgExtraNamespaces[NS_ORG] = "ORG";
$wgExtraNamespaces[NS_ORG_TALK] = "ORG_talk";
$wgNamespacesWithSubpages[NS_ORG] = true;

define("NS_EVENT", 3010);
define("NS_EVENT_TALK", 3011);
$wgExtraNamespaces[NS_EVENT] = "EVENT";
$wgExtraNamespaces[NS_EVENT_TALK] = "EVENT_talk";
$wgNamespacesWithSubpages[NS_EVENT] = true;

# Citizen Star News Archive project
define("NS_CSN", 3012);
define("NS_CSN_TALK", 3013);
$wgExtraNamespaces[NS_CSN] = "CSN";
$wgExtraNamespaces[NS_CSN_TALK] = "CSN_talk";
$wgNamespacesWithSubpages[NS_CSN] = true;
$wgNamespacesToBeSearchedDefault[NS_CSN] = true;

define("NS_TRANSCRIPT", 3014);
define("NS_TRANSCRIPT_TALK", 3015);
$wgExtraNamespaces[NS_TRANSCRIPT] = "Transcript";
$wgExtraNamespaces[NS_TRANSCRIPT_TALK] = "Transcript_talk";
$wgNamespacesWithSubpages[NS_TRANSCRIPT] = true;

$wgExtraNamespaces[$wgPageTranslationNamespace]   = 'Translations';
$wgExtraNamespaces[$wgPageTranslationNamespace+1] = 'Translations_talk';

$wgNamespaceProtection[NS_TEMPLATE] = array( 'template-edit' );
$wgNamespaceProtection[NS_COMMLINK] = array( 'commlink-edit' );
$wgNamespaceProtection[NS_PROJMGMT] = array( 'projmgmt-edit' );
$wgNamespaceProtection[NS_ISSUE] = array( 'issue-edit' );
$wgNamespaceProtection[NS_GUIDE] = array( 'guide-edit' );
$wgNamespaceProtection[NS_ORG] = array( 'org-edit' );
$wgNamespaceProtection[NS_EVENT] = array( 'event-edit' );

$wgVisualEditorAvailableNamespaces = array(
  NS_MAIN     	=> true,
  NS_USER     	=> true,
  NS_HELP     	=> true,
  NS_PROJECT 	=> true,
  NS_COMMLINK 	=> true,
  NS_PROJMGMT 	=> true,
  NS_ISSUE    	=> true,
  NS_GUIDE    	=> true,
  NS_ORG      	=> true,
  NS_EVENT    	=> true,
  NS_CSN    	=> true,
  NS_TRANSCRIPT => true
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
$wgGroupPermissions['autoconfirmed']['upload_by_url'] = true;
$wgGroupPermissions['autoconfirmed']['createpage'] = true;
$wgGroupPermissions['autoconfirmed']['createtalk'] = true;

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
$wgGroupPermissions['sysop']['import'] = true;
$wgGroupPermissions['sysop']['importupload'] = true;

#=============================================== Footer ===============================================

$wgFooterIcons = [
    "poweredby" => [
        "mediawiki" => [
            "src" => "$wgResourceBasePath/skins/Citizen/resources/images/icons/image.svg", // placeholder to bypass default
            "url" => "https://www.mediawiki.org",
            "alt" => "Powered by MediaWiki",
        ]
    ],
    "monitoredby" => [
          "wikiapiary" => [
              "src" => "$wgResourceBasePath/skins/Citizen/resources/images/icons/image.svg", // placeholder to bypass default
              "url" => "https://wikiapiary.com/wiki/The_Star_Citizen_Wiki",
              "alt" => "Monitored By Wikiapiary",
          ]
    ],
/*
  "gdprcompliance" => [
        "gdpr" => [
            "src" => "$wgResourceBasePath/skins/Citizen/resources/images/icons/image.svg", // placeholder to bypass default
            "url" => "https://gdpr.eu",
            "alt" => "GDPR compliant",
        ]
    ],
*/
    "copyright" => [
        "copyright" => [
        "src" => "$wgResourceBasePath/skins/Citizen/resources/images/icons/image.svg", // placeholder to bypass default,
            "url" => $wgRightsUrl,
            "alt" => $wgRightsText,
      ]
    ],
    "madeby" => [
          "thecommunity" => [
              "src" => "$wgResourceBasePath/skins/Citizen/resources/images/icons/image.svg", // placeholder to bypass default
              "url" => "https://robertsspaceindustries.com",
              "alt" => "Made by the community",
          ]
    ],
    "partof" => [
        "starcitizentools" => [
            "src" => "$wgResourceBasePath/skins/Citizen/resources/images/icons/image.svg", // placeholder to bypass default
            "url" => "https://starcitizen.tools",
            "alt" => "Part of Star Citizen Tools",
        ]
    ]
];

# Add cookie statement to footer
$wgHooks['SkinTemplateOutputPageBeforeExec'][] = function( $sk, &$tpl ) {
  $tpl->set( 'cookiestatement', $sk->footerLink( 'cookiestatement', 'cookiestatementpage' ) );
  // or to add non-link text:
  $tpl->set( 'footertext', 'Text to show in footer' );
  $tpl->data['footerlinks']['places'][] = 'cookiestatement';
  return true;
};

#============================== Final External Includes ===============================================

require_once("/home/www-data/external_includes/misc_server_settings.php");
