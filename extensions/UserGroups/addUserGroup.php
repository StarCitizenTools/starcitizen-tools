<?php
/**
 * Adds a user group either to a list of users or to the database.
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
 * @ingroup Maintenance
 * @author Withoutaname
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

require_once __DIR__ . '/Maintenance.php';

/**
 * Maintenance script to add a user group, either to a list of users or
 * to the database.
 *
 * @ingroup Maintenance
 */
class AddUserGroup extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Add a user group, either to a list of users " .
				"or to the database. User group must be an \"explicit\" type.";
		$this->addArg( 'group', "Name of the user group to add.", true );
		$this->addOption( 'log', "Whether or not to log the actions onwiki.", false, false );
		$this->addOption( 'performer', "If --log were set, you can also optionally " .
				"specify a username as the performer for the action. Otherwise," .
				"\"MediaWiki default\" is assumed.", false, true );
		$this->addOption( 'reason', "Any additional comments you want to provide as " .
				"the summary for the change, enclosed in quotes.", false, true );
		$this->addOption( 'todb', "Add this user group to the database. " .
				"If you use this argument, you must provided a comma-separated list" .
				"of user rights to associate with this user group. Cannot be used " .
				"with --touser.", false, true );
		$this->addOption( 'revoke', "If --todb was set, you can specify whether the " .
				"newly created user group should be revoke type. Revoke type groups " .
				"revoke their rights from users instead of granting them.", false, false );
		$this->addOption( 'touser', "Add this user group to a single user or to a " .
				"comma-separated list of users. Cannot be used with --todb.", false, true );
	}

	public function execute() {
		$groupname = $this->getArg( 0 );
		$usergroup = new UserGroup( $groupname, false );
		$log = $this->hasOption( 'log' );
		if ( $log ) {
			if ( $this->hasOption( 'performer' ) ) {
				$performer = User::newFromName( $this->getOption( 'performer' ), 'valid' );
				if ( !$performer->getId() ) {
					throw new MWException( "Invalid username given as the performer." );
				}
			} else {
				$performer = User::newFromName( 'MediaWiki default' );
			}
			$comment = '';
			if ( $this->hasOption( 'reason' ) ) {
				$comment = $this->getOption( 'reason' );
			}
		}
		if ( $this->hasOption( 'todb' ) ) {
			if ( $this->hasOption( 'touser' ) ) {
				throw new MWException( "Cannot use --todb in conjunction with --touser." );
			}
			if ( $usergroup->exists() ) {
				$this->error( "Error: This usergroup already exists, aborting...", true );
			}
			$rightsnames = explode( ',', $this->getOption( 'todb' ) );
			$this->output( "Creating new user group \"$groupname\".\n" );
			$addrights = array();
			foreach ( $rightsnames as $rightsname ) {
				$userright = new UserRight( $rightsname );
				if ( !$userright->exists() ) {
					$this->error( "Error: Userright \"$rightsname\" does not exist, skipping...\n" );
				} else {
					$addrights[] = $userright;
				}
			}
			if ( !$addrights ) {
				$this->error( "Could not create user group; no userrights were detected.", true );
			}
			$usergroup->addUserRights( $addrights );
			if ( $this->hasOption( 'revoke' ) ) {
				$usergroup->switchRevoke();
				$switched = true;
			}
			if ( $log ) {
				if ( !$performer->isAllowed( 'modifygroups' ) ) {
					throw new MWException( "Error: You do not have permission to modify user groups." );
				}
				SpecialUserGroups::addLogEntry( $performer, 'create', $usergroup, $comment,
					$addrights, null, $switched );
			}
			$usergroup->insert();
		} elseif ( $this->hasOption( 'touser' ) ) {
			if ( $this->hasOption( 'todb' ) ) {
				throw new MWException( "Cannot use --todb in conjunction with --touser." );
			}
			if ( !$usergroup->exists() ) {
				$this->error( "Error: User group specified does not exist.", true );
			}
			$usernames = explode( ',', $this->getOption( 'touser' ) );
			foreach ( $usernames as $username ) {
				if ( $log && $performer ) {
					$changeableGroups = $performer->changeableGroups();
					if ( !in_array( $groupname, $changeableGroups['add'], true ) ) {
						$this->error( "You do not have permission to add group \"$groupname\".", true );
					}
					if ( !in_array( $groupname, $changeableGroups['add-self'], true ) &&
						$username == $performer->getName() ) {
						$this->error( "You do not have permission to add group \"$groupname\" to yourself, skipping..." );
						continue;
					}
				}
				$this->output( "Adding [[User:$username]] to usergroup." );
				$user = User::newFromName( $username, 'valid' );
				if ( !$user || !$user->getId() ) {
					$this->error( "Error: Invalid user detected, skipping...\n" );
				} else {
					$oldGroups = $user->getGroups();
					$user->addGroup( $groupname );
					$newGroups = $user->getGroups();
					if ( $log ) {
						$logEntry = new ManualLogEntry( 'rights', 'rights' );
						$logEntry->setPerformer( $performer );
						$logEntry->setTarget( $user->getUserPage() );
						$logEntry->setComment( $comment );
						$logEntry->setParameters( array(
							'4::oldgroups' => $oldGroups,
							'5::newgroups' => $newGroups,
						) );
						$logid = $logEntry->insert();
						$logEntry->publish( $logid );
					}
				}
			}
		} else {
			$this->error( "Error: You have not specified where to allocate the new user group.", true );
		}

		$this->output( "\nDone!\n" );
	}
}

$maintClass = "AddUserGroup";
require_once RUN_MAINTENANCE_IF_MAIN;
