<?php
/**
 * Base class for all user groups within %MediaWiki.
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
 * This class represents the User Groups feature within MediaWiki, and
 * includes both "core" or hardcoded user groups as well as custom or
 * user-defined user groups, implemeted through extensions. User Groups
 * are categories or array mappings of users to certain registered or
 * associated "User Rights". For more documentation, see
 * https://www.mediawiki.org/wiki/Manual:User_rights
 *
 * @ingroup User
 */
class UserGroup {
	/**
	 * Until there's a schema change in the user_groups table, we are still stuck with these globals
	 * global $wgAddGroups, $wgAutopromote, $wgGroupPermissions, $wgGroupsAddToSelf,
	 * $wgGroupsRemoveFromSelf, $wgImplicitGroups, $wgRemoveGroups, $wgRevokePermissions;
	 */

	/**
	 * @var array Array of ResultWrapper|bool objects taken form a database
	 * query of the user groups table.
	 */
	protected $dbrows;
	/**
	 * @var array An array of users belonging to this user group.
	 */
	protected $members = array();
	/**
	 * @var string The internal name for this user group, as used within
	 * MediaWiki itself.
	 */
	protected $nameInternal;
	/**
	 * @var string The localized name for this user group as used onwiki.
	 */
	protected $nameLocalized;
	/**
	 * @var bool Specifies whether or not this user group is able to grant
	 * user rights or revoke user rights. User groups are considered
	 * "revoke" types if they were defined by $wgRevokePermissions instead
	 * of $wgGroupPermissions.
	 */
	protected $revoke;
	/**
	 * @var array An array of user rights associated with this user group.
	 */
	protected $userrights = array();

	/**
	 * Returns the internal string name representing this user group.
	 *
	 * @return string|null
	 * @see self::getName()
	 */
	public function __toString() {
		return $this->getName();
	}

	/**
	 * Constructor for the UserGroup class, based on a given string as the
	 * internal name or the localized name.
	 *
	 * @param string $name The name to give this new object
	 * @param bool $localized Whether the name is internal (default) or a
	 * localized version
	 * @throws MWException If $name was not given a string
	 */
	public function __construct( $name, $localized = false ) {
		global $wgGroupPermissions, $wgRevokePermissions;

		if ( !is_string( $name ) ) {
			throw new MWException( "Constructor expects name to be a string." );
		}
		$this->clearInstanceCache();
		if ( $localized ) {
			$realname = function() use ( $name ) {
				$allGroups = self::listGroups();
				foreach ( $allGroups as $group ) {
					$internalname = $group->getName();
					$groupname = ( $internalname == '*' ) ? 'all' : $internalname;
					$message = wfMessage( "group-$groupname" );
					$groupnameLocalized = !$message->isBlank() ? $message->text() : $groupname;
					if ( $name === $groupname || $name === $groupnameLocalized ) {
						return $internalname;
					}
				}
				return null;
			};
			$this->nameInternal = $realname;
			$this->nameLocalized = $name;
		} else {
			$message = ( $name === '*' ) ? wfMessage( "group-all" ) : wfMessage( "group-$name" );
			$this->nameInternal = $name;
			$this->nameLocalized = !$message->isBlank() ? $message->text() : $name;
		}
		$this->loadFromDatabase( array( 'ug_group' => $this->nameInternal ) );
		if ( $this->exists() ) {
			$groupperms = array();
			$revokeperms = array();
			foreach ( $wgGroupPermissions as $group => $rights ) {
				if ( ( $group === $this->nameInternal ) && $rights ) {
					foreach ( $rights as $right => $bool ) {
						if ( $bool ) {
							$groupperms[] = $right;
						}
					}
				}
			}
			foreach ( $wgRevokePermissions as $group => $rights ) {
				if ( ( $group === $this->nameInternal ) && $rights ) {
					foreach ( $rights as $right => $bool ) {
						if ( $bool ) {
							$groupperms[] = $right;
						}
					}
				}
			}
			$rights = array_unique( array_merge( $groupperms, $revokeperms ) );
			foreach ( $rights as $right ) {
				$this->userrights[] = new UserRight( $right );
			}
		}
		if ( $this->dbrows ) {
			$userids = array();
			foreach ( $this->dbrows as $row ) {
				$userids[] = $row->ug_user;
			}
			$userids = array_unique( $userids );
			foreach ( $userids as $userid ) {
				$this->members[] = User::newFromId( $userid );
			}
		}
		$this->revoke = in_array( $this->nameInternal, array_keys( $wgRevokePermissions ), true );
	}

	/**
	 * Add users as members of this user group.
	 *
	 * @param int|string|array|User $users An array of string usernames,
	 * user ids or User objects for which to add members.
	 * @throws MWException If this user group was an implicit group
	 */
	public function addMembers( $usernames ) {
		if ( $this->isImplicit() ) {
			throw new MWException( "Cannot modify members in implicit groups." );
		}
		$usernames = (array)$usernames;

		$this->loadFromDatabase( array( 'ug_group' => $this->nameInternal ), true );
		foreach ( $usernames as $username ) {
			$user = is_int( $username ) ? User::newFromId( $username ) :
					( is_string( $username ) ? User::newFromName( $username, 'valid' ) :
					( ( $username instanceof User ) ? $username :
					null ) );
			if ( $user ) {
				if ( Hooks::run( 'UserAddGroup', array( $user, &$this->nameInternal ) ) &&
					$this->dbrows && $user->getId() ) {
					$dbw = wfGetDB( DB_MASTER );
					$dbw->insert(
						array( 'user_groups' ),
						array(
							'ug_group' => $this->nameInternal,
							'ug_user' => $user->getId(),
						),
						__METHOD__,
						array( 'IGNORE' )
					);
				}
				$this->members[] = $user;
				$this->members = array_unique( $this->members );

				// Refreshing the cache for this User object
				$user->loadGroups();
				$user->mGroups[] = $group;
				$user->mGroups = array_unique( $user->mGroups );
				$user->getEffectiveGroups( true );
				$user->mRights = null;
				$user->invalidateCache();
			}
		}
	}

	/**
	 * Associate user rights with this user group.
	 *
	 * @param string|array|UserRight $rights An array of the user rights
	 * to associate with this user group
	 */
	public function addUserRights( $rights ) {
		$rights = (array)$rights;
		foreach ( $rights as $userright ) {
			if ( !$userright ) {
				continue;
			}
			$userright = !( $userright instanceof UserRight ) ? new UserRight( $userright ) : $userright;
			if ( !$userright->exists() || $this->hasUserRight( $userright ) ) {
				continue;
			}
			$rightsname = $userright->getName();
			if ( $this->exists() ) {
				$defsettings = file_get_contents( __DIR__ . '/../../includes/DefaultSettings.php' );
				$localsettings = file_get_contents( __DIR__ . '/../../LocalSettings.php' );
				if ( $this->revoke ) {
					$strsearch = "\$wgRevokePermissions['{$this->nameInternal}']['{$rightsname}']";
				} else {
					$strsearch = "\$wgGroupPermissions['{$this->nameInternal}']['{$rightsname}']";
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
			$this->userrights[] = $userright;
		}
	}

	/**
	 * Automatically clear any data cached with this instance.
	 */
	private function clearInstanceCache() {
		$this->dbrows = null;
		$this->members = array();
		$this->nameInternal = null;
		$this->nameLocalized = null;
		$this->revoke = null;
		$this->userrights = array();
	}

	/**
	 * Delete this user group from both the user_groups table and
	 * from the global variables in LocalSettings.php.
	 *
	 * @throws MWException If this user group was an implicit group or
	 * if it could not be found
	 */
	public function delete() {
		if ( !$this->exists() ) {
			throw new MWException( "Could not delete; user group was not found." );
		}
		if ( $this->isImplicit() ) {
			throw new MWException( "Cannot delete implicit groups." );
		}

		if ( $this->members ) {
			$this->removeMembers( $this->members );
		}
		//
		$this->loadFromDatabase( array( 'ug_group' => $this->nameInternal ), true );
		if ( $this->dbrows ) {
			$dbw = wfGetDB( DB_MASTER );
			$dbw->delete(
				'user_groups',
				array( 'ug_group' => $this->nameInternal ),
				__METHOD__
			);
			foreach ( $this->dbrows as $row ) {
				$user = User::newFromId( $row->ug_user );
				$user->invalidateCache();
			}
		}

		$extensionfile = __DIR__ . '/UserGroups.php';
		$grouprights = $this->getUserRights();
		if ( $grouprights ) {
			$this->removeUserRights( $grouprights );
		}
		$strtoadd = "unset( \$wgAddGroups['{$this->nameInternal}'] );\n" .
					"unset( \$wgGroupPermissions['{$this->nameInternal}'] );\n" .
					"unset( \$wgGroupsAddToSelf['{$this->nameInternal}'] );\n" .
					"unset( \$wgGroupsRemoveFromSelf['{$this->nameInternal}'] );\n" .
					"unset( \$wgRemoveGroups['{$this->nameInternal}'] );\n" .
					"unset( \$wgRevokePermissions['{$this->nameInternal}'] );\n";
		file_put_contents( $extensionfile, $strtoadd, FILE_APPEND );
	}

	/**
	 * If this is a brand new user group, create it. The user group must
	 * have a few userrights already loaded with it; if it does not, the
	 * user group will not be created.
	 *
	 * @throws MWException If this usergroup already exists
	 */
	public function insert() {
		if ( $this->exists() ) {
			throw new MWException( "This user group already exists; please check your spelling." );
		}
		if ( $this->userrights ) {
			$extensionfile = __DIR__ . '/UserGroups.php';
			foreach ( $this->userrights as $userright ) {
				$rightsname = $userright->getName();
				if ( $this->revoke ) {
					$strtoadd = "\$wgRevokePermissions['{$this->nameInternal}']['{$rightsname}'] = true;\n";
				} else {
					$strtoadd = "\$wgGroupPermissions['{$this->nameInternal}']['{$rightsname}'] = true;\n";
				}
				file_put_contents( $extensionfile, $strtoadd, FILE_APPEND );
			}
			if ( $this->members ) {
				foreach ( $this->members as $member ) {
					$dbw = wfGetDB( DB_MASTER );
					$dbw->insert(
						'user_groups',
						array( 'ug_group' => $this->nameInternal, 'ug_user' => $member->getId() ),
						__METHOD__,
						array( 'IGNORE' )
					);
					$member->invalidateCache();
				}
			}
		}
	}

	/**
	 * Load information about the user_groups table from the database.
	 *
	 * @param string|array $conds Conditions for this select query
	 * @param bool $clear Set whether or not to clear any initial data
	 * held in $dbrows by this instance.
	 */
	public function loadFromDatabase( $conds = '', $clear = false ) {
		if ( $clear ) {
			$this->dbrows = null;
		}
		if ( is_null( $this->dbrows ) ) {
			$dbr = wfGetDB( DB_MASTER );
			$rows = $dbr->select(
				array( 'user_groups' ),
				array( '*' ),
				$conds,
				__METHOD__
			);
			foreach ( $rows as $row ) {
				$this->dbrows[] = $row;
			}
		}
	}

	/**
	 * Remove User objects from this user group through the database.
	 *
	 * @param int|string|array|User $users An array of string usernames,
	 * user ids or User objects from which to remove members
	 * @throws MWException If this user group was an implicit group
	 */
	public function removeMembers( $usernames ) {
		if ( $this->isImplicit() ) {
			throw new MWException( "Cannot modify members in implicit groups." );
		}
		$usernames = (array)$usernames;

		$this->loadFromDatabase( array( 'ug_group' => $this->nameInternal ), true );
		foreach ( $usernames as $username ) {
			$user = is_int( $username ) ? User::newFromId( $username ) :
					( is_string( $username ) ? User::newFromName( $username, 'valid' ) :
					( ( $username instanceof User ) ? $username :
					null ) );
			if ( $user ) {
				$user->load();
				if ( Hooks::run( 'UserRemoveGroup', array( $user, &$this->nameInternal ) ) &&
					$this->dbrows && $user->getId() ) {
					$dbw = wfGetDB( DB_MASTER );
					$dbw->delete(
						array( 'user_groups' ),
						array(
							'ug_group' => $this->nameInternal,
							'ug_user' => $user->getId(),
						),
						__METHOD__
					);
				}
				$this->members = array_diff( $this->members, array( $user ) );

				// Refreshing the cache for this User object
				$user->loadGroups();
				$user->mGroups = array_diff( $user->mGroups, array( $this->nameInternal ) );
				$user->getEffectiveGroups( true );
				$user->mRights = null;
				$user->invalidateCache();
			}
		}
	}

	/**
	 * Remove user right associations from this user group.
	 *
	 * @param string|array|UserRight $rights An array of the user rights
	 * to disassociate from this user group
	 */
	public function removeUserRights( $rights ) {
		$rights = (array)$rights;
		foreach ( $rights as $userright ) {
			if ( !$userright ) {
				continue;
			}
			$userright = !( $userright instanceof UserRight ) ? new UserRight( $userright ) : $userright;
			if ( !$userright->exists() || !$this->hasUserRight( $userright ) ) {
				continue;
			}
			$rightsname = $userright->getName();
			if ( $this->exists() ) {
				$defsettings = file_get_contents( __DIR__ . '/../../includes/DefaultSettings.php' );
				$localsettings = file_get_contents( __DIR__ . '/../../LocalSettings.php' );
				if ( $this->revoke ) {
					$strsearch = "\$wgRevokePermissions['{$this->nameInternal}']['{$rightsname}']";
				} else {
					$strsearch = "\$wgGroupPermissions['{$this->nameInternal}']['{$rightsname}']";
				}
				$extensionfile = __DIR__ . '/UserGroups.php';
				if ( $strsearch ) {
					if ( strpos( $defsettings, $strsearch ) || strpos( $localsettings, $strsearch ) ) {
						file_put_contents( $extensionfile, "unset( $strsearch );\n", FILE_APPEND );
					} else {
						$oldcontents = file_get_contents( $extensionfile );
						$newcontents = preg_replace( "/" . preg_quote( $strsearch ) . ".*;\n/", '', $oldcontents );
						file_put_contents( $extensionfile, $newcontents );
					}
				}
			}
		}
		$this->userrights = array_diff( $this->userrights, $rights );
	}

	/**
	 * Switch the "revoke" status of this user group from false to true and
	 * from true to false.
	 *
	 * @throws MWException If it is already defined in DefaultSettings or
	 * LocalSettings, in which case we cannot change it from here
	 */
	public function switchRevoke() {
		$defsettings = file_get_contents( __DIR__ . '/../../includes/DefaultSettings.php' );
		$localsettings = file_get_contents( __DIR__ . '/../../LocalSettings.php' );
		$strgroup = "\$wgGroupPermissions['{$this->nameInternal}']";
		$strrevoke = "\$wgRevokePermissions['{$this->nameInternal}']";
		if ( strpos( $defsettings, $strgroup ) || strpos( $defsettings, $strrevoke ) ||
			strpos( $localsettings, $strgroup ) || strpos( $localsettings, $strrevoke ) ) {
			throw new MWException( "Cannot switch user groups from or to revoke types " .
					"if they have been predefined by the localsettings of your installation." );
		} else {
			if ( $this->exists() ) {
				$extensionfile = __DIR__ . '/UserGroups.php';
				$oldcontents = file_get_contents( $extensionfile );
				if ( $this->revoke ) {
					$newcontents = str_replace( $strrevoke, $strgroup, $oldcontents );
				} else {
					$newcontents = str_replace( $strgroup, $strrevoke, $oldcontents );
				}
				file_put_contents( $extensionfile, $newcontents );
			}
			$this->revoke = !$this->revoke;
		}
	}

	/**
	 * Reverse deletion by removing all the unsets from the usergroup put in
	 * place by self::delete().
	 */
	public function undelete() {
		if ( $this->isDeleted() ) {
			$extensionfile = __DIR__ . '/UserGroups.php';
			$strsearch = "unset( \$wgAddGroups['{$this->nameInternal}'] );\n" .
						"unset( \$wgGroupPermissions['{$this->nameInternal}'] );\n" .
						"unset( \$wgGroupsAddToSelf['{$this->nameInternal}'] );\n" .
						"unset( \$wgGroupsRemoveFromSelf['{$this->nameInternal}'] );\n" .
						"unset( \$wgRemoveGroups['{$this->nameInternal}'] );\n" .
						"unset( \$wgRevokePermissions['{$this->nameInternal}'] );\n";
			$oldcontents = file_get_contents( $extensionfile );
			$newcontents = str_replace( $strsearch, '', $oldcontents );
			file_put_contents( $extensionfile, $newcontents );
		}
	}

	/**
	 * Returns an array of UserGroup objects that can change membership
	 * of this user group through Special:UserRights.
	 *
	 * @return array array( 'add' => array( groups that can add this to others ),
	 *     'remove' => array( groups that can remove this from others ),
	 *     'add-self' => array( groups that can add this to self ),
	 *     'remove-self' => array( groups that can remove this from self ) )
	 */
	public function changeableByGroups() {
		global $wgAddGroups, $wgRemoveGroups, $wgGroupsAddToSelf, $wgGroupsRemoveFromSelf;

		$bygroups = array(
			'add' => array(),
			'remove' => array(),
			'add-self' => array(),
			'remove-self' => array()
		);
		$allGroups = self::listGroups();
		foreach ( $allGroups as $group ) {
			$internalname = $group->getName();
			if ( $wgAddGroups[$internalname] === true ||
				in_array( $this->nameInternal, $wgAddGroups[$internalname], true ) ) {
					$bygroups['add'][] = $group;
			}
			if ( $wgRemoveGroups[$internalname] === true ||
				in_array( $this->nameInternal, $wgRemoveGroups[$internalname], true ) ) {
					$bygroups['remove'][] = $group;
			}
			if ( $wgGroupsAddToSelf[$internalname] === true ||
				in_array( $this->nameInternal, $wgGroupsAddToSelf[$internalname], true ) ) {
					$bygroups['add-self'][] = $group;
			}
			if ( $wgGroupsRemoveFromSelf[$internalname] === true ||
				in_array( $this->nameInternal, $wgGroupsRemoveFromSelf[$internalname], true ) ) {
					$bygroups['remove-self'][] = $group;
			}
		}
		return $bygroups;
	}

	/**
	 * Returns an array of UserGroup objects that this user group can
	 * change membership of through Special:UserRights.
	 *
	 * @return array array( 'add' => array( groups that can be added to others ),
	 *     'remove' => array( groups that can be removed from others ),
	 *     'add-self' => array( groups that can be added to self ),
	 *     'remove-self' => array( groups that can be removed from self ) )
	 */
	public function changeableGroups() {
		global $wgAddGroups, $wgRemoveGroups, $wgGroupsAddToSelf, $wgGroupsRemoveFromSelf;

		$togroups = array(
			'add' => array(),
			'remove' => array(),
			'add-self' => array(),
			'remove-self' => array()
		);
		$explicitGroups = self::listExplicitGroups();
		$addgroups = isset( $wgAddGroups[$this->nameInternal] ) ?
					$wgAddGroups[$this->nameInternal] : null;
		$removegroups = isset( $wgRemoveGroups[$this->nameInternal] ) ?
						$wgRemoveGroups[$this->nameInternal] : null;
		$addtoselfgroups = isset( $wgGroupsAddToSelf[$this->nameInternal] ) ?
							$wgGroupsAddToSelf[$this->nameInternal] : null;
		$removefromselfgroups = isset( $wgGroupsRemoveFromSelf[$this->nameInternal] ) ?
								$wgGroupsRemoveFromSelf[$this->nameInternal] : null;
		if ( $addgroups === true ) {
			$togroups['add'] = $explicitGroups;
		} elseif ( is_array( array_unique( $addgroups ) ) ) {
			foreach ( $addgroups as $groupname ) {
				$group = new UserGroup( $groupname, false );
				$togroups['add'] = $group;
			}
		}
		if ( $removegroups === true ) {
			$togroups['remove'] = $explicitGroups;
		} elseif ( is_array( array_unique( $removegroups ) ) ) {
			foreach ( $removegroups as $groupname ) {
				$group = new UserGroup( $groupname, false );
				$togroups['remove'][] = $group;
			}
		}
		if ( $addtoselfgroups === true ) {
			$togroups['add-self'] = $explicitGroups;
		} elseif ( is_array( array_unique( $addtoselfgroups ) ) ) {
			foreach ( $addtoselfgroups as $groupname ) {
				$group = new UserGroup( $groupname, false );
				$togroups['add-self'][] = $group;
			}
		}
		if ( $removefromselfgroups === true ) {
			$togroups['add'] = $explicitGroups;
		} elseif ( is_array( array_unique( $removefromselfgroups ) ) ) {
			foreach ( $removefromselfgroups as $groupname ) {
				$group = new UserGroup( $groupname, false );
				$togroups['remove-self'][] = $group;
			}
		}
		return $togroups;
	}

	/**
	 * Existence check to see if this user group exists either in the
	 * database or in any of the defined global variables.
	 *
	 * @return bool True if this user group is mentioned, false otherwise
	 */
	public function exists() {
		global $wgAddGroups, $wgAutopromote, $wgGroupPermissions, $wgGroupsAddToSelf,
		$wgGroupsRemoveFromSelf, $wgImplicitGroups, $wgRemoveGroups, $wgRevokePermissions;

		$this->loadFromDatabase( array( 'ug_group' => $this->nameInternal ), true );
		if ( $this->dbrows ) {
			return true;
		}
		if ( in_array( $this->nameInternal, array_keys( $wgAddGroups ), true ) ||
			in_array( $this->nameInternal, array_keys( $wgAutopromote ), true ) ||
			in_array( $this->nameInternal, array_keys( $wgGroupPermissions ), true ) ||
			in_array( $this->nameInternal, array_keys( $wgGroupsAddToSelf ), true ) ||
			in_array( $this->nameInternal, array_keys( $wgGroupsRemoveFromSelf ), true ) ||
			in_array( $this->nameInternal, $wgImplicitGroups, true ) ||
			in_array( $this->nameInternal, array_keys( $wgRemoveGroups ), true ) ||
			in_array( $this->nameInternal, array_keys( $wgRevokePermissions ), true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Returns the localized name for this user group, as used onwiki.
	 *
	 * @return string The localized name, from a Message.php object
	 */
	public function getLocalizedName() {
		return $this->nameLocalized ?: null;
	}

	/**
	 * Returns all the User objects that are members of this user group.
	 *
	 * @throws MWException If this user group was an implicit group
	 * @return array An array of the User objects belonging to this group,
	 * or null if none can be found.
	 */
	public function getMembers() {
		if ( $this->isImplicit() ) {
			throw new MWException( "Cannot retrieve membership information from implicit groups." );
		}
		return $this->members ?: null;
	}

	/**
	 * Returns the localized name describing this user group's membership.
	 *
	 * @param string $username Username for gender (since 1.19)
	 * @return string The localized membership name,
	 * or the internal name if none can be found
	 */
	public function getMembershipName( $username = '#' ) {
		$message = wfMessage( "group-$this->nameInternal-member", $username );
		return !$message->isBlank() ? $message->inContentLanguage()->text() : $this->nameInternal;
	}

	/**
	 * Returns the internal string name representing this user group, as
	 * used by MediaWiki itself.
	 *
	 * @return string|null The internal name used by this user group,
	 * or null if none can be found.
	 */
	public function getName() {
		return $this->nameInternal ?: null;
	}

	/**
	 * Return the localized wiki page associated with this user group.
	 *
	 * @return string The localized wiki page associated with this group.
	 */
	public function getTitlePage() {
		$message = wfMessage( "grouppage-$this->nameInternal" );
		return !$message->isBlank() ?
				$message->inContentLanguage()->text() :
				MWNamespace::getCanonicalName( NS_PROJECT ) . ':' . $this->nameInternal;
	}

	/**
	 * Returns an array of user rights associated with this user group.
	 *
	 * @return array|null Array of the user rights associated,
	 * or null if none can be found.
	 */
	public function getUserRights() {
		return $this->userrights ?: null;
	}

	/**
	 * Checks if this user group has the given user as one of its members.
	 *
	 * @param User $user A User object to check
	 * @throws MWException If this user group was an implicit group
	 * @return bool True if the given user was found, false otherwise
	 */
	public function hasMember( User $user ) {
		if ( $this->isImplicit() ) {
			throw new MWException( "Cannot retrieve membership information from implicit groups." );
		}
		return in_array( $user, $this->members );
	}

	/**
	 * Checks if the given user right is associated with this user group.
	 *
	 * @param UserRight $userright A UserRight object to check
	 * @return bool True if the given userright is associated with this group,
	 * false otherwise
	 */
	public function hasUserRight( UserRight $userright ) {
		return in_array( $userright, $this->userrights );
	}

	/**
	 * Checks if this user group has been deleted by searching for unsets
	 * that were put in place by self::delete().
	 *
	 * @return bool True if the unsets were found, false otherwise
	 */
	public function isDeleted() {
		$extensionfile = file_get_contents( __DIR__ . '/UserGroups.php' );
		$strsearch = "unset( \$wgAddGroups['{$this->nameInternal}'] );\n" .
					"unset( \$wgGroupPermissions['{$this->nameInternal}'] );\n" .
					"unset( \$wgGroupsAddToSelf['{$this->nameInternal}'] );\n" .
					"unset( \$wgGroupsRemoveFromSelf['{$this->nameInternal}'] );\n" .
					"unset( \$wgRemoveGroups['{$this->nameInternal}'] );\n" .
					"unset( \$wgRevokePermissions['{$this->nameInternal}'] );\n";
		if ( strpos( $extensionfile, $strsearch ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Checks if this user group is considered an "implicit" user group.
	 * "Implicit" user groups are defined by $wgImplicitGroups.
	 *
	 * @return bool True if this is an implicit user group, false otherwise
	 */
	public function isImplicit() {
		global $wgImplicitGroups;
		return in_array( $this->nameInternal, $wgImplicitGroups, true );
	}

	/**
	 * Checks if this user group can revoke certain user rights, also known as
	 * "revoke" user groups.
	 *
	 * @return bool True if this is a "revoke" user group, false otherwise
	 */
	public function isRevokeGroup() {
		return $this->revoke ?: null;
	}

	/**
	 * Returns an array of all user groups defined as "explicit".
	 * "Explicit" user groups are simply those not in $wgImplicitGroups.
	 *
	 * @return array UserGroup Array of UserGroup objects defined as "explicit"
	 */
	public static function listExplicitGroups() {
		$allGroups = self::listGroups();
		$implicitGroups = self::listImplicitGroups();
		$this->dbrows = null;
		return array_diff( $allGroups, $implicitGroups );
	}

	/**
	 * Returns an array of all the user groups in the database.
	 *
	 * @return array UserGroup An array of all user groups
	 */
	public static function listGroups() {
		global $wgAddGroups, $wgAutopromote, $wgGroupPermissions, $wgGroupsAddToSelf,
		$wgGroupsRemoveFromSelf, $wgImplicitGroups, $wgRemoveGroups, $wgRevokePermissions;

		$dbgroups = array();
		$dbr = wfGetDB( DB_MASTER );
		$res = $dbr->select(
			array( 'user_groups' ),
			array( '*' ),
			null,
			__METHOD__
		);
		foreach ( $res as $row ) {
			$dbgroups[] = $row->ug_group;
		}

		$defsettingsgroups = array();
		foreach ( $wgAddGroups as $performer => $targets ) {
			$defsettingsgroups[] = $performer;
			foreach ( $targets as $target ) {
				$defsettingsgroups[] = $target;
			}
		}
		$defsettingsgroups = array_merge( $defsettingsgroups, array_keys( $wgAutopromote ) );
		$defsettingsgroups = array_merge( $defsettingsgroups, array_keys( $wgGroupPermissions ) );
		foreach ( $wgGroupsAddToSelf as $performer => $targets ) {
			$defsettingsgroups[] = $performer;
			foreach ( $targets as $target ) {
				$defsettingsgroups[] = $target;
			}
		}
		foreach ( $wgGroupsRemoveFromSelf as $performer => $targets ) {
			$defsettingsgroups[] = $performer;
			foreach ( $targets as $target ) {
				$defsettingsgroups[] = $target;
			}
		}
		$defsettingsgroups = array_merge( $defsettingsgroups, $wgImplicitGroups );
		foreach ( $wgRemoveGroups as $performer => $targets ) {
			$defsettingsgroups[] = $performer;
			foreach ( $targets as $target ) {
				$defsettingsgroups[] = $target;
			}
		}
		$defsettingsgroups = array_merge( $defsettingsgroups, array_keys( $wgRevokePermissions ) );

		$allGroupNames = array_unique( array_merge( $dbgroups, $defsettingsgroups ) );
		asort( $allGroupNames );
		foreach ( $allGroupNames as $groupname ) {
			$allGroups[] = new UserGroup( $groupname, false );
		}
		return $allGroups;
	}

	/**
	 * Returns an array of all the user groups in the database, along with their
	 * associated user rights as array values. Does not include what user groups
	 * each may be able to affect through the 'userrights' user right, for that
	 * see self::changeableGroups().
	 *
	 * @return array An array mapping of each user group as keys to arrays of
	 * string representations of the associated user rights as values
	 * @see self::changeableGroups()
	 */
	public static function listGroupsAndRights() {
		global $wgGroupPermissions, $wgRevokePermissions;

		$allGroupsAndRights = array();
		$othergroups = array();
		$otheruserrights = array();
		$allGroups = self::listGroups();
		$allUserRights = UserRight::getAllRights();
		foreach ( $allGroups as $group ) {
			$othergroups[] = $group->getName();
		}
		foreach ( $allUserRights as $userright ) {
			$otheruserrights[] = $userright->getName();
		}
		foreach ( $wgGroupPermissions as $group => $rights ) {
			$allGroupsAndRights[] = $group;
			foreach ( $rights as $right ) {
				$allGroupsAndRights[$group][] = $right;
			}
			$otheruserrights = array_diff( $otheruserrights, $rights );
		}
		$othergroups = array_diff( $othergroups, array_keys( $wgGroupPermissions ) );
		foreach ( $wgRevokePermissions as $group => $rights ) {
			$allGroupsAndRights[] = $group;
			foreach ( $rights as $right ) {
				$allGroupsAndRights[$group][] = $right;
			}
			$otheruserrights = array_diff( $otheruserrights, $rights );
		}
		$othergroups = array_diff( $othergroups, array_keys( $wgRevokePermissions ) );

		if ( $othergroups ) {
			foreach ( $othergroups as $group ) {
				$allGroupsAndRights[] = $group;
			}
		}
		if ( $otheruserrights ) {
			$allGroupsAndRights[] = new UserGroup( "", false );
			foreach ( $otheruserrights as $right ) {
				$allGroupsAndRights[""][] = $right;
			}
		}

		return $allGroupsAndRights;
	}

	/**
	 * Returns an array of all user groups defined as "implicit".
	 * "Implicit" user groups are defined by $wgImplicitGroups.
	 *
	 * @return array UserGroup Array of "implicit" user groups
	 */
	public static function listImplicitGroups() {
		global $wgImplicitGroups;

		$implicitGroups = array();
		foreach ( $wgImplicitGroups as $group ) {
			$implicitGroups[] = new UserGroup( $group, false );
		}
		return $implicitGroups;
	}

	/**
	 * Returns an array of all user groups that revoke certain user rights.
	 *
	 * @return array UserGroup Array of user groups in $wgRevokePermissions
	 */
	public static function listRevokeGroups() {
		global $wgRevokePermissions;

		$revokeGroups = array();
		foreach ( $wgRevokePermissions as $group => $rights ) {
			$revokeGroups[] = new UserGroup( $group, false );
		}
		return $revokeGroups;
	}
}
