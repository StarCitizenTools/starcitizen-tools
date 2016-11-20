<?php
/**
 * Implements Special:UserGroups
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
 * @ingroup SpecialPage
 * @author Withoutaname
 * @link https://www.mediawiki.org/wiki/Manual:User_rights Documentation
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

/**
 * Special page allowing others to create, modify, delete and rename
 * user groups.
 */
class SpecialUserGroups extends SpecialPage {
	/**
	 * @var array An array of all UserGroup objects.
	 */
	protected $allGroups = array();
	/**
	 * @var User The user performing the action, provided by context.
	 */
	protected $user;
	/**
	 * @var UserGroup The target UserGroup for modification.
	 */
	protected $usergroup;

	public function __construct() {
		parent::__construct( 'UserGroups', 'modifygroups', true );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Show this special page
	 *
	 * @param string|null $par String if any subpage provided, else null
	 * @throws UserBlockedError|PermissionsError
	 */
	public function execute( $par ) {
		$this->allGroups = UserGroup::listGroups();
		asort( $this->allGroups );
		$this->user = $this->getUser();

		$this->checkReadOnly();
		$this->checkPermissions();

		$this->setHeaders();
		$this->outputHeader();

		$this->getOutput()->setPageTitle( $this->msg( 'usergroups' ) );
		$this->getOutput()->setArticleRelated( false );
		$this->getOutput()->enableClientCache( false );
		$this->buildHeader();
		if ( $par !== null ) {
			$this->buildMainForm( $par );
		}
		$this->saveGroupChanges();
	}

	/**
	 * Add a log entry to RecentChanges/Watchlist to record changes
	 * made to user groups.
	 *
	 * @param User $user The User performing this change
	 * @param string $subtype The subtype of the change, could be one of
	 * "create", "modify" or "delete"
	 * @param UserGroup $usergroup The target user group
	 * @param string $comment Additional comments for the log entry
	 * @param array $addrights The userrights added to the group
	 * @param array $removerights The userrights removed from the group
	 * @param bool $switched Whether this usergroup had been switched
	 * between revoke and non-revoke group types
	 */
	public static function addLogEntry( User $user, $subtype, UserGroup $usergroup, $comment = null,
		$addrights = array(), $removerights = array(), $switched = false ) {
		$logentry = new ManualLogEntry( 'usergroups', $subtype );
		$logentry->setPerformer( $user );
		$logentry->setTarget( parent::getTitleFor( 'UserGroups', $usergroup->getName() ) );
		$logentry->setComment( $comment );
		$addrights = $addrights ? implode( ', ', $addrights ) :
					wfMessage( 'usergroups-log-none' )->text();
		$removerights = $removerights ? implode( ', ', $removerights ) :
						wfMessage( 'usergroups-log-none' )->text();
		if ( $switched ) {
			if ( $subtype === 'create' ) {
				$switched = wfMessage( 'usergroups-log-revoke' )->text();
			} elseif ( $subtype === 'modify' ) {
				if ( $usergroup->isRevokeGroup() ) {
					$switched = wfMessage( 'usergroups-log-revoke-switchon' )->text();
				} else {
					$switched = wfMessage( 'usergroups-log-revoke-switchoff' )->text();
				}
			}
		}
		$logentry->setParameters( array(
			'4::groupname' => $usergroup->getName(),
			'5::addrights' => $addrights,
			'6::removerights' => $removerights,
			'7::switched' => $switched ?: '',
		) );
		$logid = $logentry->insert();
		$logentry->publish( $logid );
	}

	/**
	 * Build the header for the special page. The header will display a
	 * short message followed by a dropdown box for the user groups.
	 */
	protected function buildHeader() {
		global $wgScript;
		$grouplist = '';
		$title = $this->getPageTitle()->getLocalURL();
		$options = Xml::option( '', '' ) . "\n";
		if ( $this->allGroups ) {
			foreach ( $this->allGroups as $group ) {
				$internalname = $group->getName();
				$groupname = ( $internalname == '*' ) ? 'all' : $internalname;
				$groupnameLocalized = $group->getLocalizedName();
				$options .= Xml::option( $groupnameLocalized, $title . '/' . $groupname ) . "\n";
			}
		}
		$options .= Xml::option( $this->msg( 'usergroups-createnew' ),
					$title . '/' . 'new' );
		$this->getOutput()->addHTML(
			'
			' .
			Html::inlineScript( '
				$(function() {
					$("#groupname").change(function() {
						location = $("#groupname option:selected").val();
					});
				});
			' ) .
			"\n" .
			Xml::openElement(
				'form',
				array(
					'method' => 'get',
					'action' => $this->getPageTitle()->getLocalURL(),
					'name' => 'selectgroup',
					'id' => 'usergroups-header'
				)
			) .
			Html::hidden( 'title', $this->getPageTitle()->getPrefixedText() ) .
			"\n" .
			Xml::openElement( 'p' ) .
			Xml::span(
				$this->msg( 'usergroups-editgroup-desc' ),
				'usergroups-header-text'
			) .
			Xml::closeElement( 'p' ) .
			"\n" .
			Xml::openElement( 'select', array( 'name' => 'groupname', 'id' => 'groupname' ) ) .
			"\n" .
			$options .
			"\n" .
			Xml::closeElement( 'select' ) .
			Xml::closeElement( 'form' ) .
			"\n"
		);
	}

	/**
	 * Build the main form for the special page.
	 *
	 * @param string|null $subpage Subpage of this special page
	 */
	protected function buildMainForm( $subpage ) {
		$groupnames = array();
		if ( $this->allGroups ) {
			foreach ( $this->allGroups as $group ) {
				$internalname = $group->getName();
				$groupnames[] = ( $internalname == '*' ) ? 'all' : $internalname;
			}
		}
		if ( !( in_array( $subpage, $groupnames, true ) || ( $subpage === 'new' ) ) ) {
			$status = Status::newFatal( 'nosuchusergroup', $subpage );
			$this->getOutput()->addWikiText( $status->getWikiText() );
		} else {
			$this->getOutput()->addHTML(
				Xml::openElement(
					'form',
					array(
						'method' => 'post',
						'action' => $this->getPageTitle()->getLocalURL(),
						'name' => 'modifygroup',
						'id' => 'usergroups-body'
					)
				)
			);
			$deleteButton = '';
			if ( $subpage === 'new' ) {
				$this->getOutput()->addHTML(
					"\n" .
					Html::element( 'br' ) .
					Html::element( 'hr' ) .
					Html::element( 'br' ) .
					"\n" .
					Xml::inputLabel(
						$this->msg( 'usergroups-editgroup-newgroup' ),
						'newgroup',
						'newgroupname',
						30,
						null,
						array( 'autofocus' => true, 'text-align' => 'right' )
					)
				);
			} else {
				$this->usergroup = ( $subpage === 'all' ) ? new UserGroup( '*', false ) : new UserGroup( $subpage, false );
				if ( !$this->usergroup->isImplicit() ) {
					$deleteButton = Xml::check(
						'wpUsergroupDelete',
						false,
						array( 'id' => 'wpUsergroupDelete' )
					) .
					"&#160;" .
					Html::rawElement(
						'label',
						array( 'for' => 'wpUsergroupDelete' ),
						$this->msg( 'usergroups-editgroup-delete' )
					);
				}
			}
			$allUserRights = UserRight::getAllRights();
			$checkboxes = "\n";
			$index = 0;
			$revoke = false;
			foreach ( $allUserRights as $userright ) {
				$index++;
				$checked = false;
				if ( $this->usergroup ) {
					if ( $this->usergroup->hasUserRight( $userright ) ) {
						$checked = true;
					}
					if ( $this->usergroup->isRevokeGroup() ) {
						$revoke = true;
					}
				}
				$rightsname = $userright->getName();
				$message = $this->msg( "right-$rightsname" );
				$parentheses = $this->msg( "parentheses", $rightsname );
				$checkboxes .= Html::rawElement( 'li', array( 'class' => 'mw-usergroups-editrights' ),
				Xml::check(
					"wpUserrightsEdit-$rightsname",
					$checked,
					array( 'id' => "wpUserrightsEdit-$rightsname" )
				) .
				"&#160;" .
				Html::rawElement(
					'label',
					array( 'for' => "wpUserrightsEdit-$rightsname" ),
					( !$message->isBlank() ? $message->text() : $rightsname ) .
					' ' . Xml::tags( 'code', null, $parentheses )
				) ) . "\n";
				if ( ceil( count( $allUserRights ) / 2 ) == $index ) {
					$checkboxes .= "</ul></td><td><ul>" . "\n";
				}
			}
			$this->getOutput()->addHTML(
				Xml::fieldset(
					$this->msg( 'usergroups-editgroup-userrights' ),
					"<table><tbody><tr><td><ul>" .
					$checkboxes .
					"</ul></td></tr></tbody></table>",
					array( 'class' => 'usergroups-editpermissions' )
				) .
				Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() ) .
				Html::hidden( 'wpGroupPage', $subpage ) .
				Xml::check(
					'wpUserrightsRevoke',
					$revoke,
					array( 'id' => 'wpUserrightsRevoke' )
				) .
				"&#160;" .
				Html::rawElement(
					'label',
					array( 'for' => 'wpUserrightsRevoke' ),
					$this->msg( 'usergroups-editgroup-revoke' )
				) .
				Html::element( 'br' ) .
				"\n" .
				$deleteButton .
				Html::element( 'br' ) .
				"\n" .
				Html::rawElement( 'td', array( 'class' => 'mw-label' ),
					Xml::label( $this->msg( 'usergroups-editgroup-reason' ), 'wpReason' )
				) .
				"&#160;" .
				Html::rawElement( 'td', array( 'class' => 'mw-input' ),
					Xml::input(
						'wpReason',
						60,
						'',
						array( 'id' => 'wpReason', 'maxlength' => 255 )
					)
				) .
				"\n" .
				Html::element( 'br' ) .
				"\n" .
				Html::rawElement( 'td', array( 'class' => 'mw-submit' ),
					Xml::submitButton(
						$this->msg( 'usergroups-editgroup-save' ),
						array( 'name' => 'savegroupchanges', 'style' => 'float:right' )
					)
				)
			);
			$this->getOutput()->addHTML( Xml::closeElement( 'form' ) );
		}
	}

	/**
	 * Save the changes made to the user groups to the wiki.
	 *
	 * @throws ErrorPageError If the user inputs invalid characters for
	 * the new user group name
	 * @throws MWException If the new user group name matches an already
	 * existing user group name
	 */
	protected function saveGroupChanges() {
		$request = $this->getRequest();
		$groupid = $request->getVal( 'wpGroupPage' );
		if ( $request->wasPosted() &&
			$request->getCheck( 'savegroupchanges' ) &&
			$this->user->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
			if ( $groupid === 'new' ) {
				$newgroupname = $request->getVal( 'newgroup' );
				if ( $newgroupname === null || $newgroupname === '' ||
					preg_match( Title::getTitleInvalidRegex(), $newgroupname ) ) {
						throw new ErrorPageError( 'invalidusergroupname-title', 'invalidusergroupname-error' );
				}
				$this->usergroup = new UserGroup( $newgroupname, false );
				if ( $this->usergroup->exists() ) {
					throw new MWException( $this->msg( "usergroupnameexists-error" )->text() );
				}
			} else {
				$this->usergroup = ( $groupid === 'all' ) ? new UserGroup( '*', false ) : new UserGroup( $groupid, false );
			}
			if ( $this->usergroup ) {
				$reason = $request->getVal( 'wpReason' ) ?: null;
				if ( $request->getCheck( 'wpUsergroupDelete' ) ) {
					$this->usergroup->delete();
					self::addLogEntry( $this->user, "delete", $this->usergroup, $reason );
					unset( $this->usergroup );
				} else {
					if ( ( !$this->usergroup->isRevokeGroup() && $request->getCheck( 'wpUserrightsRevoke' ) ) ||
						( $this->usergroup->isRevokeGroup() && !$request->getCheck( 'wpUserrightsRevoke' ) ) ) {
						$this->usergroup->switchRevoke();
						$switched = true;
					}
					$oldrights = $this->usergroup->getUserRights();
					$newrights = array();
					$addrights = array();
					$removerights = array();
					$allUserrights = UserRight::getAllRights();
					foreach ( $allUserrights as $userright ) {
						$rightsname = $userright->getName();
						if ( $request->getCheck( "wpUserrightsEdit-$rightsname" ) ) {
							$newrights[] = new UserRight( $rightsname );
						}
					}
					if ( !( $oldrights == $newrights ) || $switched ) {
						if ( !$oldrights ) {
							$addrights = $newrights;
						} else {
							foreach ( $allUserrights as $userright ) {
								$rightsname = $userright->getName();
								if ( $request->getCheck( "wpUserrightsEdit-$rightsname" ) &&
									!in_array( $userright, $oldrights ) ) {
									$addrights[] = $userright;
								}
								if ( !$request->getCheck( "wpUserrightsEdit-$rightsname" )
									&& in_array( $userright, $oldrights ) ) {
									$removerights[] = $userright;
								}
							}
							$this->usergroup->removeUserRights( $removerights );
							self::addLogEntry( $this->user, "modify", $this->usergroup, $reason,
								$addrights, $removerights, $switched );
						}
						$this->usergroup->addUserRights( $addrights );
						if ( !$this->usergroup->exists() ) {
							if ( $this->usergroup->isDeleted() ) {
								$this->usergroup->undelete();
							}
							$this->usergroup->insert();
							self::addLogEntry( $this->user, "create", $this->usergroup, $reason,
								$addrights, null, $switched );
						}
					}
				}
			}
			$successpage = $this->getPageTitle()->getFullURL( array( 'success' => 1 ) );
			$this->getOutput()->redirect( $successpage );
		}
	}

	protected function getGroupName() {
		return 'users';
	}
}
