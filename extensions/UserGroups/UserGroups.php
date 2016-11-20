<?php
/**
 * Extension to provide finer control over user groups and rights.
 *
 * Copyright © 2014 Withoutaname
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
 * @ingroup Extensions
 * @author Withoutaname
 * @link https://www.mediawiki.org/wiki/Extension:UserGroups Documentation
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	echo <<<EOT
To use this extension, please include the following line in your LocalSettings.php:
require_once( "\$IP/extensions/UserGroups/UserGroups.php" );
EOT;
	exit( 1 );
}

$wgExtensionCredits['other'][] = array(
	'author' => 'Withoutaname',
	'descriptionmsg' => 'usergroups-desc',
	'name' => 'UserGroups',
	'path' => __FILE__,
	'url' => 'https://www.mediawiki.org/wiki/Extension:UserGroups',
	'version' => '1.0.0',
	'license-name' => 'GPL-2.0+',
);

// For internationalization
$wgExtensionMessagesFiles['SpecialUserGroupsAliases'] = __DIR__ . '/UserGroups.alias.php';
$wgLogTypes[] = 'usergroups';
$wgLogNames['usergroups'] = 'usergroups-log-name';
$wgLogHeaders['usergroups'] = 'usergroups-log-header';
$wgLogActionsHandlers['usergroups/*'] = 'LogFormatter';
$wgMessagesDirs['UserGroups'] = __DIR__ . '/i18n';
$wgSpecialPages['UserGroups'] = 'SpecialUserGroups';

// For autoloading
$wgAutoloadClasses['AddUserGroup'] = __DIR__ . '/addUserGroup.php';
$wgAutoloadClasses['SpecialUserGroups'] = __DIR__ . '/SpecialUserGroups.php';
$wgAutoloadClasses['UserGroup'] = __DIR__ . '/UserGroup.php';
$wgAutoloadClasses['UserRight'] = __DIR__ . '/UserRights.php';

// New permissions
$wgAvailableRights[] = 'modifygroups';
$wgGroupPermissions['bureaucrat']['modifygroups'] = true;

// This section is used by the extension to modify user groups on your wiki.
// Unless you know what you're doing, PLEASE DO NOT EDIT THIS SECTION DIRECTLY!
