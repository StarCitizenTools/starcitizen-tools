<?php
/**
 * Base class for all user rights within %MediaWiki.
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
 * @ingroup User
 * @author Withoutaname
 * @link https://www.mediawiki.org/wiki/Manual:User_rights Documentation
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

/**
 * Class to represent all userrights that determine and control access
 * to MediaWiki, including custom user-defined userrights developed from
 * extensions. MediaWiki first checks to see if a user has any of these
 * user rights before granting them certain levels of access to some of
 * the internal processes, such as editing, uploading and deleting.
 *
 * @ingroup User
 */
class UserRight {
	/**
	 * Globals related to user rights
	 * global $wgAvailableRights, $wgGroupPermissions, $wgNamespaceProtection,
	 * $wgRestrictionTypes, $wgRevokePermissions;
	 */

	/**
	 * @var array An array of stdClass|bool objects from a database query.
	 */
	private $dbrows;

	/**
	 * Cached array of all user rights defined by core.
	 * Each of these should have a corresponding message "right-$right".
	 * @showinitializer
	 */
	private static $mCoreRights = array(
		'apihighlimits',
		'autoconfirmed',
		'autopatrol',
		'bigdelete',
		'block',
		'blockemail',
		'bot',
		'browsearchive',
		'createaccount',
		'createpage',
		'createtalk',
		'delete',
		'deletedhistory',
		'deletedtext',
		'deletelogentry',
		'deleterevision',
		'edit',
		'editinterface',
		'editprotected',
		'editmyoptions',
		'editmyprivateinfo',
		'editmyusercss',
		'editmyuserjs',
		'editmywatchlist',
		'editsemiprotected',
		'editusercssjs', #deprecated
		'editusercss',
		'edituserjs',
		'hideuser',
		'import',
		'importupload',
		'ipblock-exempt',
		'markbotedits',
		'mergehistory',
		'minoredit',
		'move',
		'movefile',
		'move-categorypages',
		'move-rootuserpages',
		'move-subpages',
		'nominornewtalk',
		'noratelimit',
		'override-export-depth',
		'passwordreset',
		'patrol',
		'patrolmarks',
		'protect',
		'proxyunbannable',
		'purge',
		'read',
		'reupload',
		'reupload-own',
		'reupload-shared',
		'rollback',
		'sendemail',
		'siteadmin',
		'suppressionlog',
		'suppressredirect',
		'suppressrevision',
		'unblockself',
		'undelete',
		'unwatchedpages',
		'upload',
		'upload_by_url',
		'userrights',
		'userrights-interwiki',
		'viewmyprivateinfo',
		'viewmywatchlist',
		'writeapi',
	);

	/**
	 * @var string Name of this user right
	 */
	private $name;

	/**
	 * Returns the name of this user right as its string representation.
	 *
	 * @return string|null
	 * @see self::getName()
	 */
	public function __toString() {
		return $this->getName();
	}

	/**
	 * Constructs a UserRight object given a string as its name.
	 */
	public function __construct( $name ) {
		if ( !is_string( $name ) ) {
			throw new MWException( "Constructor expects argument to be a string." );
		}
		$this->name = $name;
	}

	/**
	 * Given an array of UserGroup objects, associate this
	 * UserRight with each of the user groups.
	 *
	 * @param array $groups Array of UserGroup objects to
	 * associate this right with
	 */
	public function addToGroups( $groups, $revoke = false ) {
		$groups = !is_array( $groups ) ? array( $groups ) : $groups;
		foreach ( $groups as $group ) {
			if ( !( $group instanceof UserGroup ) ) {
				$this->output( "Not a UserGroup object. Skipping $group..." );
				continue;
			}
			$groupname = $group->getName();
			$rightsname = $this->name;
			$defsettings = file_get_contents( __DIR__ . '/../../includes/DefaultSettings.php' );
			$localsettings = file_get_contents( __DIR__ . '/../../LocalSettings.php' );
			if ( $group->isRevokeGroup() ) {
				$strsearch = "\$wgRevokePermissions[\'{$groupname}\'][\'{$rightsname}\']";
			} else {
				$strsearch = "\$wgGroupPermissions[\'{$groupname}\'][\'{$rightsname}\']";
			}
			$extensionfile = __DIR__ . '/UserGroups.php';
			if ( $strsearch ) {
				if ( strpos( $defsettings, $strsearch ) || strpos( $localsettings, $strsearch ) ) {
					$oldcontents = file_get_contents( $extensionfile );
					$newcontents = str_replace( "unset( $strsearch );\n", '', $oldcontents );
					file_put_contents( $extensionfile, $newcontents );
				} else {
					file_put_contents( $extensionfile, "$strsearch = true;\n", FILE_APPEND );
				}
			}
		}
	}

	/**
	 * Existence check for this user right.
	 *
	 * @return bool True if this user right was found, false otherwise
	 */
	public function exists() {
		return in_array( $this, self::getAllRights() );
	}

	/**
	 * Returns an array of the namespaces this user right affects.
	 *
	 * @return array An array of Namespace.php constants
	 */
	public function getAffectedNamespaces() {
		global $wgNamespaceProtection;

		$namespaces = array();
		foreach ( $wgNamespaceProtection as $mwnamespace => $rights ) {
			if ( $mwnamespace ) {
				if ( ( is_array( $rights ) && in_array( $this->name, $rights, true ) ) ||
					( is_string( $rights ) && $this->name === $rights ) ) {
					$namespaces[] = $mwnamespace;
				}
			}
		}
		if ( $this->name === 'editinterface' ) {
			$namespaces[] = NS_MEDIAWIKI;
		}
		$namespaces = array_unique( $namespaces );
		asort( $namespaces );
		return $namespaces;
	}

	/**
	 * Returns a Message object which can be used to translate or
	 * retrieve a localized description for this user right.
	 *
	 * @return Message A Message.php object
	 */
	public function getDescription() {
		return wfMessage( "right-$this->name" );
	}

	/**
	 * Returns an array of user groups that this user right is
	 * currently associated with.
	 *
	 * @return array An array of UserGroup objects
	 */
	public function getGroups() {
		global $wgGroupPermissions, $wgRevokePermissions;

		$groups = array();
		$groupnames = array();
		foreach ( $wgGroupPermissions as $group => $rights ) {
			if ( $rights ) {
				foreach ( $rights as $right => $boolvalue ) {
					if ( $boolvalue && ( $this->name === $right->getName() ) ) {
						$groupnames[] = $group;
					}
				}
			}
		}
		foreach ( $wgRevokePermissions as $group => $rights ) {
			if ( $rights ) {
				foreach ( $rights as $right => $boolvalue ) {
					if ( $boolvalue && ( $this->name === $right->getName() ) ) {
						$groupnames[] = $group;
					}
				}
			}
		}
		$groupnames = array_unique( $groupnames );

		foreach ( $groupnames as $groupname ) {
			$groups[] = new UserGroup( $groupname, false );
		}
		return $groups;
	}

	/**
	 * Returns a string representing the name of this user right.
	 *
	 * @return string|null The name of this user right,
	 * or null if none was found.
	 */
	public function getName() {
		return $this->name ?: null;
	}

	/**
	 * Returns an array of users that have this user right, however
	 * only if this user right is not part of $wgImplicitGroups.
	 *
	 * @return array An array of User objects
	 */
	public function getUsers() {
		$users = array();
		$groups = $this->getGroups();
		foreach ( $groups as $group ) {
			if ( $group->isImplicit() ) {
				return null;
			}
			$members = $group->getMembers();
			foreach ( $members as $member ) {
				$users[] = $member;
			}
		}
		return $users;
	}

	/**
	 * Check to see if this user right is associated with a given
	 * user group.
	 *
	 * @param UserGroup The UserGroup object to check
	 *
	 * @return bool True if it is associated with the user group,
	 * false otherwise
	 */
	public function inUserGroup( UserGroup $group ) {
		$otherloadedgroups = $this->getGroups();
		foreach ( $otherloadedgroups as $othergroup ) {
			if ( $group->getName() === $othergroup->getName() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Checks whether or not this user right is part of core.
	 *
	 * @return bool True if it is part of core, false otherwise
	 */
	public function isCore() {
		return in_array( $this->name, self::$mCoreRights, true );
	}

	/**
	 * Returns an array of all user rights objects from the database.
	 *
	 * @return array An array of available UserRight objects
	 */
	public static function getAllRights() {
		global $wgAvailableRights, $wgGroupPermissions, $wgNamespaceProtection, $wgRevokePermissions;

		$userrights = array();
		$userrightsnames = array();
		foreach ( $wgAvailableRights as $right ) {
			$userrightsnames[] = $right;
		}
		foreach ( $wgGroupPermissions as $group => $rights ) {
			if ( $rights ) {
				foreach ( $rights as $right => $boolvalue ) {
					$userrightsnames[] = $right;
				}
			}
		}
		foreach ( $wgNamespaceProtection as $mwnamespace => $rights ) {
			if ( is_array( $rights ) ) {
				$userrightsnames = array_merge( array_unique( $userrightsnames ), array_unique( $rights ) );
			} elseif ( is_string( $rights ) ) {
				$userrightsnames[] = $rights;
			}
		}
		foreach ( $wgRevokePermissions as $group => $rights ) {
			if ( $rights ) {
				foreach ( $rights as $right => $boolvalue ) {
					$userrightsnames[] = $right;
				}
			}
		}

		$userrightsnames = array_unique( $userrightsnames );
		foreach ( $userrightsnames as $rightsname ) {
			$userrights[] = new UserRight( $rightsname );
		}

		asort( $userrights );
		return $userrights;
	}

	/**
	 * Returns an array of all user rights in core as objects.
	 *
	 * @return array An array of UserRight objects from core
	 */
	public static function getCoreRights() {
		$corerights = array();
		foreach ( self::$mCoreRights as $rightsname ) {
			$corerights[] = new UserRight( $rightsname );
		}
		asort( $corerights );
		return $corerights;
	}

	/**
	 * Returns an array of all user rights not defined by core.
	 *
	 * @return array An array of UserRight objects not from core
	 */
	public static function getOtherRights() {
		$otherrights = array_diff( self::getAllRights(), self::getCoreRights() );
		asort( $otherrights );
		return $otherrights;
	}

	/**
	 * Returns an array of all user rights objects that were revoked.
	 * Revoked user rights are defined in $wgRevokePermissions.
	 *
	 * @return array An array of UserRight objects that were revoked
	 */
	public static function getRevokedRights() {
		global $wgRevokePermissions;

		$revokerights = array();
		$revokerightsnames = array();
		foreach ( $wgRevokePermissions as $group => $rights ) {
			if ( $rights ) {
				foreach ( $rights as $right => $boolvalue ) {
					if ( $boolvalue ) {
						$revokerightsnames[] = $right;
					}
				}
			}
		}
		$revokerightsnames = array_unique( $revokerightsnames );
		foreach ( $revokerightsnames as $rightsname ) {
			$revokerights[] = new UserRight( $rightsname );
		}
		asort( $revokerights );
		return $revokerights;
	}
}
