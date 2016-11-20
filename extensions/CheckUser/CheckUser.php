<?php

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'CheckUser' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['CheckUser'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['CheckUserAliases'] = __DIR__ . '/CheckUser.alias.php';
	/* wfWarn(
		'Deprecated PHP entry point used for CheckUser extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	); */
	return;
} else {
	die( 'This version of the CheckUser extension requires MediaWiki 1.25+' );
}

// Global declarations and documentation kept for IDEs and PHP documentors.
// This code is never executed.

/**
 * Legacy variable, no longer used. Used to point to a file in the server where
 * CheckUser would log all queries done through Special:CheckUser.
 * If this file exists, the installer will try to import data from this file to
 * the 'cu_log' table in the database.
 */
$wgCheckUserLog = '/home/wikipedia/logs/checkuser.log';

/** How long to keep CU data (in seconds)? */
$wgCUDMaxAge = 3 * 30 * 24 * 3600; // 3 months

/** Mass block limits */
$wgCheckUserMaxBlocks = 200;

/**
 * Set this to true if you want to force checkusers into giving a reason for
 * each check they do through Special:CheckUser.
 */
$wgCheckUserForceSummary = false;

/** Shortest CIDR limits that can be checked in any individual range check */
$wgCheckUserCIDRLimit = array(
	'IPv4' => 16,
	'IPv6' => 32,
);

/**
 * Public key to encrypt private data that may need to be read later
 * Generate a public key with something like:
 * `openssl genrsa -out cu.key 2048; openssl rsa -in cu.key -pubout > cu.pub`
 * and paste the contents of cu.pub here
 */
$wgCUPublicKey = '';
