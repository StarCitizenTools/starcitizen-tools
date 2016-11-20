<?php

class CheckUser extends SpecialPage {
	/**
	 * @var null|array $message Used to cache frequently used messages
	 */
	protected $message = null;

	public function __construct() {
		parent::__construct( 'CheckUser', 'checkuser' );
	}

	public function doesWrites() {
		return true; // logging
	}

	public function execute( $subpage ) {
		$this->setHeaders();
		$this->checkPermissions();
		// Logging and blocking requires writing so stop from here if read-only mode
		$this->checkReadOnly();

		$out = $this->getOutput();
		$request = $this->getRequest();

		if ( $this->getUser()->isAllowed( 'checkuser-log' ) ) {
			$subtitleLink = Linker::linkKnown(
				SpecialPage::getTitleFor( 'CheckUserLog' ),
				$this->msg( 'checkuser-showlog' )->escaped()
			);
			$out->addSubtitle( $subtitleLink );
		}

		$user = $request->getText( 'user', $request->getText( 'ip', $subpage ) );
		$user = trim( $user );
		$reason = $request->getText( 'reason' );
		$blockreason = $request->getText( 'blockreason', '' );
		$disableUserTalk = $request->getBool( 'blocktalk', false );
		$disableEmail = $request->getBool( 'blockemail', false );
		$checktype = $request->getVal( 'checktype' );
		$period = $request->getInt( 'period' );
		$users = $request->getArray( 'users' );
		$tag = $request->getBool( 'usetag' ) ?
			trim( $request->getVal( 'tag' ) ) : '';
		$talkTag = $request->getBool( 'usettag' ) ?
			trim( $request->getVal( 'talktag' ) ) : '';

		$blockParams = array(
			'reason' => $blockreason,
			'talk' => $disableUserTalk,
			'email' => $disableEmail,
		);

		$ip = $name = $xff = '';
		$m = array();
		if ( IP::isIPAddress( $user ) ) {
			// A single IP address or an IP range
			$ip = IP::sanitizeIP( $user );
		} elseif ( preg_match( '/^(.+)\/xff$/', $user, $m ) && IP::isIPAddress( $m[1] ) ) {
			// A single IP address or range with XFF string included
			$xff = IP::sanitizeIP( $m[1] );
		} else {
			// A user?
			$name = $user;
		}

		$this->showIntroductoryText();
		$this->showForm( $user, $reason, $checktype, $ip, $xff, $name, $period );

		// Perform one of the various submit operations...
		if ( $request->wasPosted() ) {
			if ( !$this->getUser()->matchEditToken( $request->getVal( 'wpEditToken' ) ) ) {
				$out->wrapWikiMsg( '<div class="error">$1</div>', 'checkuser-token-fail' );
			} elseif ( $request->getVal( 'action' ) === 'block' ) {
				$this->doMassUserBlock( $users, $blockParams, $tag, $talkTag );
			} elseif ( !$this->checkReason( $reason ) ) {
				$out->addWikiMsg( 'checkuser-noreason' );
			} elseif ( $checktype == 'subuserips' ) {
				$this->doUserIPsRequest( $name, $reason, $period );
			} elseif ( $xff && $checktype == 'subedits' ) {
				$this->doIPEditsRequest( $xff, true, $reason, $period );
			} elseif ( $ip && $checktype == 'subedits' ) {
				$this->doIPEditsRequest( $ip, false, $reason, $period );
			} elseif ( $name && $checktype == 'subedits' ) {
				$this->doUserEditsRequest( $user, $reason, $period );
			} elseif ( $xff && $checktype == 'subipusers' ) {
				$this->doIPUsersRequest( $xff, true, $reason, $period, $tag, $talkTag );
			} elseif ( $checktype == 'subipusers' ) {
				$this->doIPUsersRequest( $ip, false, $reason, $period, $tag, $talkTag );
			}
		}
		// Add CIDR calculation convenience JS form
		$this->addJsCIDRForm();
		$out->addModules( 'ext.checkUser' );
	}

	protected function showIntroductoryText() {
		global $wgCheckUserCIDRLimit;
		$this->getOutput()->addWikiText(
			$this->msg( 'checkuser-summary',
				$wgCheckUserCIDRLimit['IPv4'],
				$wgCheckUserCIDRLimit['IPv6']
			)->text()
		);
	}

	/**
	 * Show the CheckUser query form
	 *
	 * @param string $user
	 * @param string $reason
	 * @param string $checktype
	 * @param string $ip
	 * @param string $xff
	 * @param string $name
	 * @param int $period
	 */
	protected function showForm( $user, $reason, $checktype, $ip, $xff, $name, $period ) {
		$action = htmlspecialchars( $this->getPageTitle()->getLocalUrl() );
		// Fill in requested type if it makes sense
		$encipusers = $encedits = $encuserips = 0;
		if ( $checktype == 'subipusers' && ( $ip || $xff ) ) {
			$encipusers = 1;
		} elseif ( $checktype == 'subuserips' && $name ) {
			$encuserips = 1;
		} elseif ( $checktype == 'subedits' ) {
			$encedits = 1;
		// Defaults otherwise
		} elseif ( $ip || $xff ) {
			$encedits = 1;
		} else {
			$encuserips = 1;
		}

		$form = Xml::openElement( 'form', array( 'action' => $action,
			'name' => 'checkuserform', 'id' => 'checkuserform', 'method' => 'post' ) );
		$form .= '<fieldset><legend>' . $this->msg( 'checkuser-query' )->escaped() . '</legend>';
		$form .= Xml::openElement( 'table', array( 'style' => 'border:0' ) );
		$form .= '<tr>';
		$form .= '<td>' . $this->msg( 'checkuser-target' )->escaped() . '</td>';
		// User field should fit things like "2001:0db8:85a3:08d3:1319:8a2e:0370:7344/100/xff"
		$form .= '<td>' . Xml::input( 'user', 46, $user, array( 'id' => 'checktarget' ) );
		$form .= '&#160;' . $this->getPeriodMenu( $period ) . '</td>';
		$form .= '</tr><tr>';
		$form .= '<td></td>';
		$form .= Xml::openElement( 'td', array( 'class' => 'checkuserradios' ) );
		$form .= Xml::openElement( 'table', array( 'style' => 'border:0' ) );
		$form .= '<tr>';
		$form .= '<td>' .
			Xml::radio( 'checktype', 'subuserips', $encuserips, array( 'id' => 'subuserips' ) );
		$form .= ' ' . Xml::label( $this->msg( 'checkuser-ips' )->text(), 'subuserips' ) . '</td>';
		$form .= '<td>' .
			Xml::radio( 'checktype', 'subedits', $encedits, array( 'id' => 'subedits' ) );
		$form .= ' ' . Xml::label( $this->msg( 'checkuser-edits' )->text(), 'subedits' ) . '</td>';
		$form .= '<td>' .
			Xml::radio( 'checktype', 'subipusers', $encipusers, array( 'id' => 'subipusers' ) );
		$form .= ' ' . Xml::label( $this->msg( 'checkuser-users' )->text(), 'subipusers' ) . '</td>';
		$form .= '</tr>';
		$form .= Xml::closeElement( 'table' );
		$form .= Xml::closeElement( 'td' );
		$form .= '</tr><tr>';
		$form .= '<td>' . $this->msg( 'checkuser-reason' )->escaped() . '</td>';
		$form .= '<td>' . Xml::input( 'reason', 46, $reason,
			array( 'maxlength' => '150', 'id' => 'checkreason' ) );
		$form .= '&#160; &#160;' . Xml::submitButton( $this->msg( 'checkuser-check' )->text(),
			array( 'id' => 'checkusersubmit', 'name' => 'checkusersubmit' ) ) . '</td>';
		$form .= '</tr>';
		$form .= Xml::closeElement( 'table' );
		$form .= '</fieldset>';
		$form .= Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
		$form .= Xml::closeElement( 'form' );

		$this->getOutput()->addHTML( $form );
	}

	/**
	 * Get a selector of time period options
	 * @param int $selected Currently selected option
	 * @return string
	 */
	protected function getPeriodMenu( $selected ) {
		$s = '<label for="period">' . $this->msg( 'checkuser-period' )->escaped() . '</label>&#160;';
		$s .= Xml::openElement(
			'select',
			array( 'name' => 'period', 'id' => 'period', 'style' => 'margin-top:.2em;' )
		);
		$s .= Xml::option( $this->msg( 'checkuser-week-1' )->text(), 7, $selected === 7 );
		$s .= Xml::option( $this->msg( 'checkuser-week-2' )->text(), 14, $selected === 14 );
		$s .= Xml::option( $this->msg( 'checkuser-month' )->text(), 31, $selected === 31 );
		$s .= Xml::option( $this->msg( 'checkuser-all' )->text(), 0, $selected === 0 );
		$s .= Xml::closeElement( 'select' ) . "\n";
		return $s;
	}

	/**
	 * Make a quick JS form for admins to calculate block ranges
	 */
	protected function addJsCIDRForm() {
		$s = '<fieldset id="mw-checkuser-cidrform" style="display:none; clear:both;">' .
			'<legend>' . $this->msg( 'checkuser-cidr-label' )->escaped() . '</legend>';
		$s .= '<textarea id="mw-checkuser-iplist" dir="ltr" rows="5" cols="50"></textarea><br />';
		$s .= $this->msg( 'checkuser-cidr-res' )->escaped() . '&#160;' .
			Xml::input( 'mw-checkuser-cidr-res', 35, '', array( 'id' => 'mw-checkuser-cidr-res' ) ) .
			'&#160;<strong id="mw-checkuser-ipnote"></strong>';
		$s .= '</fieldset>';
		$this->getOutput()->addHTML( $s );
	}

	/**
	 * @param string $reason
	 * @return bool
	 */
	protected function checkReason( $reason ) {
		global $wgCheckUserForceSummary;
		return ( !$wgCheckUserForceSummary || strlen( $reason ) );
	}

	/**
	 * As we use the same small set of messages in various methods and that
	 * they are called often, we call them once and save them in $this->message
	 */
	protected function preCacheMessages() {
		if ( $this->message === null ) {
			$msgKeys = array( 'diff', 'hist', 'minoreditletter', 'newpageletter', 'blocklink', 'log' );
			foreach ( $msgKeys as $msg ) {
				$this->message[$msg] = $this->msg( $msg )->escaped();
			}
		}
	}

	/**
	 * Block a list of selected users
	 * @param array $users
	 * @param array $blockParams
	 * @param string $tag
	 * @param string $talkTag
	 */
	protected function doMassUserBlock( $users, $blockParams, $tag = '', $talkTag = '' ) {
		global $wgCheckUserMaxBlocks;
		$usersCount = count( $users );
		if ( !$this->getUser()->isAllowed( 'block' ) || $this->getUser()->isBlocked()
			|| !$usersCount
		) {
			$this->getOutput()->addWikiMsg( 'checkuser-block-failure' );
			return;
		} elseif ( $usersCount > $wgCheckUserMaxBlocks ) {
			$this->getOutput()->addWikiMsg( 'checkuser-block-limit' );
			return;
		} elseif ( !$blockParams['reason'] ) {
			$this->getOutput()->addWikiMsg( 'checkuser-block-noreason' );
			return;
		}

		$blockedUsers = self::doMassUserBlockInternal( $users, $blockParams, $tag, $talkTag );
		$blockedCount = count( $blockedUsers );
		if ( $blockedCount > 0 ) {
			$lang = $this->getLanguage();
			$this->getOutput()->addWikiMsg( 'checkuser-block-success',
				$lang->listToText( $blockedUsers ),
				$lang->formatNum( $blockedCount )
			);
		} else {
			$this->getOutput()->addWikiMsg( 'checkuser-block-failure' );
		}
	}

	/**
	 * Block a list of selected users
	 *
	 * @param string[] $users
	 * @param array $blockParams
	 * @param string $tag replaces user pages
	 * @param string $talkTag replaces user talk pages
	 * @return string[] List of html-safe usernames which were actually were blocked
	 */
	public static function doMassUserBlockInternal( $users, array $blockParams,
		$tag = '', $talkTag = '' ) {
		global $wgBlockAllowsUTEdit, $wgUser;

		$blockSize = 0;
		$safeUsers = array();
		foreach ( $users as $name ) {
			// Enforce limits
			$blockSize++;
			// Lets not go *too* fast
			if ( $blockSize >= 20 ) {
				$blockSize = 0;
				wfWaitForSlaves( 5 );
			}

			$u = User::newFromName( $name, false );
			// Do some checks to make sure we can block this user first
			if ( $u === null ) {
				// Invalid user
				continue;
			}
			$isIP = IP::isIPAddress( $u->getName() );
			if ( !$u->getId() && !$isIP ) {
				// Not a registered user or an IP
				continue;
			}

			if ( $u->isBlocked() ) {
				// If the user is already blocked, just leave it as is
				continue;
			}

			$userTitle = $u->getUserPage();
			$userTalkTitle = $u->getTalkPage();
			$safeUsers[] = "[[{$userTitle->getPrefixedText()}|{$userTitle->getText()}]]";
			$expirestr = $isIP ? '1 week' : 'indefinite';
			$expiry = SpecialBlock::parseExpiryInput( $expirestr );

			// Create the block
			$block = new Block();
			$block->setTarget( $u );
			$block->setBlocker( $wgUser );
			$block->mReason = $blockParams['reason'];
			$block->mExpiry = $expiry;
			$block->isHardblock( !$isIP );
			$block->isAutoblocking( true );
			$block->prevents( 'createaccount', true );
			$block->prevents( 'sendemail',
				( SpecialBlock::canBlockEmail( $wgUser ) && $blockParams['email'] )
			);
			$block->prevents( 'editownusertalk', ( !$wgBlockAllowsUTEdit || $blockParams['talk'] ) );
			$status = $block->insert();

			// Prepare log parameters for the block
			$logParams = array();
			$logParams['5::duration'] = $expirestr;
			$logParams['6::flags'] = self::userBlockLogFlags( $isIP, $blockParams );

			$logEntry = new ManualLogEntry( 'block', 'block' );
			$logEntry->setTarget( $userTitle );
			$logEntry->setComment( $blockParams['reason'] );
			$logEntry->setPerformer( $wgUser );
			$logEntry->setParameters( $logParams );
			$blockIds = array_merge( array( $status['id'] ), $status['autoIds'] );
			$logEntry->setRelations( array( 'ipb_id' => $blockIds ) );
			$logEntry->publish( $logEntry->insert() );

			// Tag user page and user talk page
			self::tagPage( $userTitle, $tag, $blockParams['reason'] );
			self::tagPage( $userTalkTitle, $talkTag, $blockParams['reason'] );
		}

		return $safeUsers;
	}

	/**
	 * Return a comma-delimited list of "flags" to be passed to the block log.
	 * Flags are 'anononly', 'nocreate', 'noemail' and 'nousertalk'.
	 * @param bool $anonOnly
	 * @param array $blockParams
	 * @return string
	 */
	protected static function userBlockLogFlags( $anonOnly, array $blockParams ) {
		global $wgBlockAllowsUTEdit;
		$flags = array();

		if ( $anonOnly ) {
			$flags[] = 'anononly';
		}

		$flags[] = 'nocreate';

		if ( $blockParams['email'] ) {
			$flags[] = 'noemail';
		}

		if ( $wgBlockAllowsUTEdit && $blockParams['talk'] ) {
			$flags[] = 'nousertalk';
		}

		return implode( ',', $flags );
	}

	/**
	 * Make an edit to the given page with the tag provided
	 *
	 * @param Title $title
	 * @param string $tag
	 * @param string $summary
	 */
	protected static function tagPage( Title $title, $tag, $summary ) {
		// Check length to avoid mistakes
		if ( strlen( $tag ) > 2 ) {
			$page = WikiPage::factory( $title );
			$flags = 0;
			if ( $page->exists() ) {
				$flags |= EDIT_MINOR;
			}
			$page->doEditContent( new WikitextContent( $tag ), $summary, $flags );
		}
	}

	/**
	 * Give a "no matches found for X" message.
	 * If $checkLast, then mention the last edit by this user or IP.
	 *
	 * @param $userName
	 * @param bool $checkLast
	 * @return string
	 */
	protected function noMatchesMessage( $userName, $checkLast = true ) {
		if ( $checkLast ) {
			$dbr = wfGetDB( DB_SLAVE );
			$user_id = User::idFromName( $userName );
			if ( $user_id ) {
				$revEdit = $dbr->selectField( 'revision',
					'rev_timestamp',
					array( 'rev_user' => $user_id ),
					__METHOD__,
					array( 'ORDER BY' => 'rev_timestamp DESC' )
				);
				$logEdit = $dbr->selectField( 'logging',
					'log_timestamp',
					array( 'log_user' => $user_id ),
					__METHOD__,
					array( 'ORDER BY' => 'log_timestamp DESC' )
				);
			} else {
				$revEdit = $dbr->selectField( 'revision',
					'rev_timestamp',
					array( 'rev_user_text' => $userName ),
					__METHOD__,
					array( 'ORDER BY' => 'rev_timestamp DESC' )
				);
				$logEdit = false; // no log_user_text index
			}
			$lastEdit = max( $revEdit, $logEdit );
			if ( $lastEdit ) {
				$lastEditTime = wfTimestamp( TS_MW, $lastEdit );
				$lang = $this->getLanguage();
				// FIXME: don't pass around parsed messages
				return $this->msg( 'checkuser-nomatch-edits',
					$lang->date( $lastEditTime, true ),
					$lang->time( $lastEditTime, true )
				)->parseAsBlock();
			}
		}
		return $this->msg( 'checkuser-nomatch' )->parseAsBlock();
	}

	/**
	 * Show all the IPs used by a user
	 *
	 * @param string $user
	 * @param string $reason
	 * @param int $period
	 */
	protected function doUserIPsRequest( $user, $reason = '', $period = 0 ) {
		$out = $this->getOutput();

		$userTitle = Title::newFromText( $user, NS_USER );
		if ( !is_null( $userTitle ) ) {
			// normalize the username
			$user = $userTitle->getText();
		}
		// IPs are passed in as a blank string
		if ( !$user ) {
			$out->addWikiMsg( 'nouserspecified' );
			return;
		}
		// Get ID, works better than text as user may have been renamed
		$user_id = User::idFromName( $user );

		// If user is not IP or nonexistent
		if ( !$user_id ) {
			$out->addWikiMsg( 'nosuchusershort', $user );
			return;
		}

		// Record check...
		if ( !self::addLogEntry( 'userips', 'user', $user, $reason, $user_id ) ) {
			// FIXME: addWikiMsg
			$out->addHTML( '<p>' . $this->msg( 'checkuser-log-fail' )->escaped() . '</p>' );
		}

		$dbr = wfGetDB( DB_SLAVE );
		$time_conds = $this->getTimeConds( $period );
		// Ordering by the latest timestamp makes a small filesort on the IP list

		$ret = $dbr->select(
			'cu_changes',
			array(
				'cuc_ip',
				'cuc_ip_hex',
				'COUNT(*) AS count',
				'MIN(cuc_timestamp) AS first',
				'MAX(cuc_timestamp) AS last',
			),
			array( 'cuc_user' => $user_id, $time_conds ),
			__METHOD__,
			array(
				'ORDER BY' => 'last DESC',
				'GROUP BY' => 'cuc_ip,cuc_ip_hex',
				'LIMIT' => 5001,
				'USE INDEX' => 'cuc_user_ip_time',
			)
		);

		if ( !$dbr->numRows( $ret ) ) {
			$s = $this->noMatchesMessage( $user ) . "\n";
		} else {
			$ips_edits = array();
			$counter = 0;
			foreach ( $ret as $row ) {
				if ( $counter >= 5000 ) {
					$out->addWikiMsg( 'checkuser-limited' );
					break;
				}
				$ips_edits[$row->cuc_ip] = $row->count;
				$ips_first[$row->cuc_ip] = $row->first;
				$ips_last[$row->cuc_ip] = $row->last;
				$ips_hex[$row->cuc_ip] = $row->cuc_ip_hex;
				++$counter;
			}
			// Count pinging might take some time...make sure it is there
			wfSuppressWarnings();
			set_time_limit( 60 );
			wfRestoreWarnings();

			$s = '<div id="checkuserresults"><ul>';
			foreach ( $ips_edits as $ip => $edits ) {
				$s .= '<li>';
				$s .= $this->getSelfLink( $ip,
					array(
						'user' => $ip,
						'reason' => $reason,
					)
				);
				$s .= ' ' . $this->msg( 'parentheses' )->rawParams(
						Linker::linkKnown(
							SpecialPage::getTitleFor( 'Block', $ip ),
							$this->msg( 'blocklink' )->escaped()
						)
					)->escaped();
				$s .= ' ' . $this->getTimeRangeString( $ips_first[$ip], $ips_last[$ip] ) . ' ';
				$s .= ' <strong>[' . $edits . ']</strong>';

				// If we get some results, it helps to know if the IP in general
				// has a lot more edits, e.g. "tip of the iceberg"...
				$ipedits = $dbr->estimateRowCount( 'cu_changes', '*',
					array( 'cuc_ip_hex' => $ips_hex[$ip], $time_conds ),
					__METHOD__ );
				// If small enough, get a more accurate count
				if ( $ipedits <= 1000 ) {
					$ipedits = $dbr->selectField( 'cu_changes', 'COUNT(*)',
						array( 'cuc_ip_hex' => $ips_hex[$ip], $time_conds ),
						__METHOD__ );
				}
				if ( $ipedits > $ips_edits[$ip] ) {
					$s .= ' <i>(' . $this->msg( 'checkuser-ipeditcount', $ipedits )->escaped() . ')</i>';
				}

				// If this IP is blocked, give a link to the block log
				$s .= $this->getIPBlockInfo( $ip );
				$s .= '<div style="margin-left:5%">';
				$s .= '<small>' . $this->msg( 'checkuser-toollinks', urlencode( $ip ) )->parse() . '</small>';
				$s .= '</div>';
				$s .= "</li>\n";
			}
			$s .= '</ul></div>';
		}
		$out->addHTML( $s );
	}

	protected function getIPBlockInfo( $ip ) {
		$block = Block::newFromTarget( null, $ip, false );
		if ( $block instanceof Block ) {
			return $this->getBlockFlag( $block );
		}
		return '';
	}

	/**
	 * Get a link to block information about the passed block for displaying to the user.
	 *
	 * @param Block $block
	 * @return string
	 */
	protected function getBlockFlag( Block $block ) {
		if ( $block->getType() == Block::TYPE_AUTO ) {
			$ret = Linker::linkKnown(
				SpecialPage::getTitleFor( 'BlockList' ),
				$this->msg( 'checkuser-blocked' )->escaped(),
				array(),
				array( 'wpTarget' => "#{$block->getId()}" )
			);
		} else {
			$userPage = Title::makeTitle( NS_USER, $block->getTarget() );
			$ret = Linker::linkKnown(
				SpecialPage::getTitleFor( 'Log' ),
				$this->msg( 'checkuser-blocked' )->escaped(),
				array(),
				array(
					'type' => 'block',
					'page' => $userPage->getPrefixedText()
				)
			);
		}

		// Add the blocked range if the block is on a range
		if ( $block->getType() == Block::TYPE_RANGE ) {
			$ret .= ' - ' . htmlspecialchars( $block->getTarget() );
		}

		return '<strong>' .
			$this->msg( 'parentheses' )->rawParams( $ret )->escaped()
			. '</strong>';
	}

	/**
	 * Shows all changes made by an IP address or range
	 *
	 * @param string $ip
	 * @param bool $xfor if query is for XFF
	 * @param string $reason
	 * @param int $period
	 */
	protected function doIPEditsRequest( $ip, $xfor = false, $reason = '', $period = 0 ) {
		$out = $this->getOutput();
		$dbr = wfGetDB( DB_SLAVE );

		// Invalid IPs are passed in as a blank string
		$ip_conds = self::getIpConds( $dbr, $ip, $xfor );
		if ( !$ip || $ip_conds === false ) {
			$out->addWikiMsg( 'badipaddress' );
			return;
		}

		$logType = $xfor ? 'ipedits-xff' : 'ipedits';

		// Record check in the logs
		if ( !self::addLogEntry( $logType, 'ip', $ip, $reason ) ) {
			$out->addWikiMsg( 'checkuser-log-fail' );
		}

		$ip_conds = $dbr->makeList( $ip_conds, LIST_AND );
		$time_conds = $this->getTimeConds( $period );
		// Ordered in descent by timestamp. Can cause large filesorts on range scans.
		// Check how many rows will need sorting ahead of time to see if this is too big.
		// Also, if we only show 5000, too many will be ignored as well.
		$index = $xfor ? 'cuc_xff_hex_time' : 'cuc_ip_hex_time';
		if ( strpos( $ip, '/' ) !== false ) {
			// Quick index check only OK if no time constraint
			if ( $period ) {
				$rangecount = $dbr->selectField( 'cu_changes', 'COUNT(*)',
					array( $ip_conds, $time_conds ),
					__METHOD__,
					array( 'USE INDEX' => $index ) );
			} else {
				$rangecount = $dbr->estimateRowCount( 'cu_changes', '*',
					array( $ip_conds ),
					__METHOD__,
					array( 'USE INDEX' => $index ) );
			}
			// Sorting might take some time...make sure it is there
			wfSuppressWarnings();
			set_time_limit( 60 );
			wfRestoreWarnings();
		}
		$counter = 0;
		// See what is best to do after testing the waters...
		if ( isset( $rangecount ) && $rangecount > 5000 ) {
			$ret = $dbr->select(
				'cu_changes',
				array(
					'cuc_ip_hex',
					'COUNT(*) AS count',
					'MIN(cuc_timestamp) AS first',
					'MAX(cuc_timestamp) AS last'
				),
				array( $ip_conds, $time_conds ),
				__METHOD__,
				array(
					'GROUP BY' => 'cuc_ip_hex',
					'ORDER BY' => 'cuc_ip_hex',
					'LIMIT' => 5001,
					'USE INDEX' => $index,
				)
			);
			// List out each IP that has edits
			$s = $this->msg( 'checkuser-too-many' )->parseAsBlock();
			$s .= '<ol>';
			foreach ( $ret as $row ) {
				if ( $counter >= 5000 ) {
					$out->addWikiMsg( 'checkuser-limited' );
					break;
				}
				// Convert the IP hexes into normal form
				if ( strpos( $row->cuc_ip_hex, 'v6-' ) !== false ) {
					$ip = substr( $row->cuc_ip_hex, 3 );
					$ip = IP::HextoOctet( $ip );
				} else {
					$ip = long2ip( Wikimedia\base_convert( $row->cuc_ip_hex, 16, 10, 8 ) );
				}
				$s .= '<li>';
				$s .= $this->getSelfLink( $ip,
					array(
						'user' => $ip,
						'reason' => $reason,
						'checktype' => 'subipusers'
					)
				);
				$s .= ' ' . $this->getTimeRangeString( $row->first, $row->last ) . ' ';
				$s .= ' [<strong>' . $row->count . "</strong>]</li>\n";
				++$counter;
			}
			$s .= '</ol>';

			$out->addHTML( $s );
			return;
		} elseif ( isset( $rangecount ) && !$rangecount ) {
			$s = $this->noMatchesMessage( $ip, !$xfor ) . "\n";
			$out->addHTML( $s );
			return;
		}

		// OK, do the real query...

		$ret = $dbr->select(
			'cu_changes',
			array(
				'cuc_namespace', 'cuc_title', 'cuc_user', 'cuc_user_text', 'cuc_comment', 'cuc_actiontext',
				'cuc_timestamp', 'cuc_minor', 'cuc_page_id', 'cuc_type', 'cuc_this_oldid',
				'cuc_last_oldid', 'cuc_ip', 'cuc_xff', 'cuc_agent'
			),
			array( $ip_conds, $time_conds ),
			__METHOD__,
			array(
				'ORDER BY' => 'cuc_timestamp DESC',
				'LIMIT' => 5001,
				'USE INDEX' => $index,
			)
		);

		if ( !$dbr->numRows( $ret ) ) {
			$s = $this->noMatchesMessage( $ip, !$xfor ) . "\n";
		} else {
			// Cache common messages
			$this->preCacheMessages();
			// Try to optimize this query
			$lb = new LinkBatch;
			foreach ( $ret as $row ) {
				$userText = str_replace( ' ', '_', $row->cuc_user_text );
				$lb->add( $row->cuc_namespace, $row->cuc_title );
				$lb->add( NS_USER, $userText );
				$lb->add( NS_USER_TALK, $userText );
			}
			$lb->execute();
			$ret->seek( 0 );
			// List out the edits
			$s = '<div id="checkuserresults">';
			foreach ( $ret as $row ) {
				if ( $counter >= 5000 ) {
					$out->addWikiMsg( 'checkuser-limited' );
					break;
				}
				$s .= $this->CUChangesLine( $row, $reason );
				++$counter;
			}
			$s .= '</ul></div>';
		}

		$out->addHTML( $s );
	}

	/**
	 * @param ResultWrapper $rows Results with cuc_namespace and cuc_title field
	 */
	protected function doLinkCache( $rows ) {
		$lb = new LinkBatch();
		$lb->setCaller( __METHOD__ );
		foreach ( $rows as $row ) {
			$lb->add( $row->cuc_namespace, $row->cuc_title );
		}
		$lb->execute();
		$rows->seek( 0 );
	}

	/**
	 * Shows all changes made by a particular user
	 *
	 * @param string $user
	 * @param string $reason
	 * @param int $period
	 */
	protected function doUserEditsRequest( $user, $reason = '', $period = 0 ) {
		$out = $this->getOutput();

		$userTitle = Title::newFromText( $user, NS_USER );
		if ( !is_null( $userTitle ) ) {
			// normalize the username
			$user = $userTitle->getText();
		}
		// IPs are passed in as a blank string
		if ( !$user ) {
			$out->addWikiMsg( 'nouserspecified' );
			return;
		}
		// Get ID, works better than text as user may have been renamed
		$user_id = User::idFromName( $user );

		// If user is not IP or nonexistent
		if ( !$user_id ) {
			$s = $this->msg( 'nosuchusershort', $user )->parseAsBlock();
			$out->addHTML( $s );
			return;
		}

		// Record check...
		if ( !self::addLogEntry( 'useredits', 'user', $user, $reason, $user_id ) ) {
			$out->addHTML( '<p>' . $this->msg( 'checkuser-log-fail' )->escaped() . '</p>' );
		}

		$dbr = wfGetDB( DB_SLAVE );
		$user_cond = "cuc_user = '$user_id'";
		$time_conds = $this->getTimeConds( $period );
		// Ordered in descent by timestamp. Causes large filesorts if there are many edits.
		// Check how many rows will need sorting ahead of time to see if this is too big.
		// If it is, sort by IP,time to avoid the filesort.
		if ( $period ) {
			$count = $dbr->selectField( 'cu_changes', 'COUNT(*)',
				array( $user_cond, $time_conds ),
				__METHOD__,
				array( 'USE INDEX' => 'cuc_user_ip_time' ) );
		} else {
			$count = $dbr->estimateRowCount( 'cu_changes', '*',
				array( $user_cond, $time_conds ),
				__METHOD__,
				array( 'USE INDEX' => 'cuc_user_ip_time' ) );
		}
		// Cache common messages
		$this->preCacheMessages();
		// See what is best to do after testing the waters...
		if ( $count > 5000 ) {
			$out->addHTML( $this->msg( 'checkuser-limited' )->parse() );

			$ret = $dbr->select(
				'cu_changes',
				'*',
				array( $user_cond, $time_conds ),
				__METHOD__,
				array(
					'ORDER BY' => 'cuc_ip ASC, cuc_timestamp DESC',
					'LIMIT' => 5000,
					'USE INDEX' => 'cuc_user_ip_time'
				)
			);
			// Try to optimize this query
			$this->doLinkCache( $ret );
			$s = '';
			foreach ( $ret as $row ) {
				$ip = htmlspecialchars( $row->cuc_ip );
				if ( !$ip ) {
					continue;
				}
				if ( !isset( $lastIP ) ) {
					$lastIP = $row->cuc_ip;
					$s .= "\n<h2>$ip</h2>\n<div class=\"special\">";
				} elseif ( $lastIP != $row->cuc_ip ) {
					$s .= "</ul></div>\n<h2>$ip</h2>\n<div class=\"special\">";
					$lastIP = $row->cuc_ip;
					unset( $this->lastdate ); // start over
				}
				$s .= $this->CUChangesLine( $row, $reason );
			}
			$s .= '</ul></div>';

			$out->addHTML( $s );
			return;
		}
		// Sorting might take some time...make sure it is there
		wfSuppressWarnings();
		set_time_limit( 60 );
		wfRestoreWarnings();

		// OK, do the real query...

		$ret = $dbr->select(
			'cu_changes',
			'*',
			array( $user_cond, $time_conds ),
			__METHOD__,
			array(
				'ORDER BY' => 'cuc_timestamp DESC',
				'LIMIT' => 5000,
				'USE INDEX' => 'cuc_user_ip_time'
			)
		);
		if ( !$dbr->numRows( $ret ) ) {
			$s = $this->noMatchesMessage( $user ) . "\n";
		} else {
			$this->doLinkCache( $ret );
			// List out the edits
			$s = '<div id="checkuserresults">';
			foreach ( $ret as $row ) {
				$s .= $this->CUChangesLine( $row, $reason );
			}
			$s .= '</ul></div>';
		}

		$out->addHTML( $s );
	}

	/**
	 * Lists all users in recent changes who used an IP, newest to oldest down
	 * Outputs usernames, latest and earliest found edit date, and count
	 * List unique IPs used for each user in time order, list corresponding user agent
	 *
	 * @param string $ip
	 * @param bool $xfor
	 * @param string $reason
	 * @param int $period
	 * @param string $tag
	 * @param string $talkTag
	 */
	protected function doIPUsersRequest( $ip, $xfor = false, $reason = '', $period = 0, $tag = '', $talkTag = '' ) {
		global $wgMemc;
		$out = $this->getOutput();
		$dbr = wfGetDB( DB_SLAVE );

		// Invalid IPs are passed in as a blank string
		$ip_conds = self::getIpConds( $dbr, $ip, $xfor );
		if ( !$ip || $ip_conds === false ) {
			$out->addWikiMsg( 'badipaddress' );
			return;
		}

		$logType = $xfor ? 'ipusers-xff' : 'ipusers';

		// Log the check...
		if ( !self::addLogEntry( $logType, 'ip', $ip, $reason ) ) {
			$out->addHTML( '<p>' . $this->msg( 'checkuser-log-fail' )->escaped() . '</p>' );
		}

		$ip_conds = $dbr->makeList( $ip_conds, LIST_AND );
		$time_conds = $this->getTimeConds( $period );
		$index = $xfor ? 'cuc_xff_hex_time' : 'cuc_ip_hex_time';
		// Ordered in descent by timestamp. Can cause large filesorts on range scans.
		// Check how many rows will need sorting ahead of time to see if this is too big.
		if ( strpos( $ip, '/' ) !== false ) {
			// Quick index check only OK if no time constraint
			if ( $period ) {
				$rangecount = $dbr->selectField( 'cu_changes', 'COUNT(*)',
					array( $ip_conds, $time_conds ),
					__METHOD__,
					array( 'USE INDEX' => $index ) );
			} else {
				$rangecount = $dbr->estimateRowCount( 'cu_changes', '*',
					array( $ip_conds ),
					__METHOD__,
					array( 'USE INDEX' => $index ) );
			}
			// Sorting might take some time...make sure it is there
			wfSuppressWarnings();
			set_time_limit( 120 );
			wfRestoreWarnings();
		}
		// Are there too many edits?
		if ( isset( $rangecount ) && $rangecount > 10000 ) {
			$ret = $dbr->select(
				'cu_changes',
				array(
					'cuc_ip_hex', 'COUNT(*) AS count',
					'MIN(cuc_timestamp) AS first', 'MAX(cuc_timestamp) AS last'
				),
				array( $ip_conds, $time_conds ),
				__METHOD__,
				array(
					'GROUP BY' => 'cuc_ip_hex',
					'ORDER BY' => 'cuc_ip_hex',
					'LIMIT' => 5001,
					'USE INDEX' => $index,
				)
			);
			// List out each IP that has edits
			$s = '<h5>' . $this->msg( 'checkuser-too-many' )->escaped() . '</h5>';
			$s .= '<ol>';
			$counter = 0;
			foreach ( $ret as $row ) {
				if ( $counter >= 5000 ) {
					$out->addHTML( $this->msg( 'checkuser-limited' )->parseAsBlock() );
					break;
				}
				// Convert the IP hexes into normal form
				if ( strpos( $row->cuc_ip_hex, 'v6-' ) !== false ) {
					$ip = substr( $row->cuc_ip_hex, 3 );
					$ip = IP::hexToOctet( $ip );
				} else {
					$ip = long2ip( Wikimedia\base_convert( $row->cuc_ip_hex, 16, 10, 8 ) );
				}
				$s .= '<li>';
				$s .= $this->getSelfLink( $ip,
					array(
						'user' => $ip,
						'reason' => $reason,
						'checktype' => 'subipusers'
					)
				);
				$s .= ' ' . $this->getTimeRangeString( $row->first, $row->last ) . ' ';
				// @todo FIXME: Hard coded brackets.
				$s .= ' [<strong>' . $row->count . "</strong>]</li>\n";
				++$counter;
			}
			$s .= '</ol>';

			$out->addHTML( $s );
			return;
		} elseif ( isset( $rangecount ) && !$rangecount ) {
			$s = $this->noMatchesMessage( $ip, !$xfor ) . "\n";
			$out->addHTML( $s );
			return;
		}

		// OK, do the real query...
		$ret = $dbr->select(
			'cu_changes',
			array(
				'cuc_user_text', 'cuc_timestamp', 'cuc_user', 'cuc_ip', 'cuc_agent', 'cuc_xff'
			),
			array( $ip_conds, $time_conds ),
			__METHOD__,
			array(
				'ORDER BY' => 'cuc_timestamp DESC',
				'LIMIT' => 10000,
				'USE INDEX' => $index,
			)
		);

		$users_first = $users_last = $users_edits = $users_ids = array();
		if ( !$dbr->numRows( $ret ) ) {
			$s = $this->noMatchesMessage( $ip, !$xfor ) . "\n";
		} else {
			foreach ( $ret as $row ) {
				if ( !array_key_exists( $row->cuc_user_text, $users_edits ) ) {
					$users_last[$row->cuc_user_text] = $row->cuc_timestamp;
					$users_edits[$row->cuc_user_text] = 0;
					$users_ids[$row->cuc_user_text] = $row->cuc_user;
					$users_infosets[$row->cuc_user_text] = array();
					$users_agentsets[$row->cuc_user_text] = array();
				}
				$users_edits[$row->cuc_user_text] += 1;
				$users_first[$row->cuc_user_text] = $row->cuc_timestamp;
				// Treat blank or NULL xffs as empty strings
				$xff = empty( $row->cuc_xff ) ? null : $row->cuc_xff;
				$xff_ip_combo = array( $row->cuc_ip, $xff );
				// Add this IP/XFF combo for this username if it's not already there
				if ( !in_array( $xff_ip_combo, $users_infosets[$row->cuc_user_text] ) ) {
					$users_infosets[$row->cuc_user_text][] = $xff_ip_combo;
				}
				// Add this agent string if it's not already there; 10 max.
				if ( count( $users_agentsets[$row->cuc_user_text] ) < 10 ) {
					if ( !in_array( $row->cuc_agent, $users_agentsets[$row->cuc_user_text] ) ) {
						$users_agentsets[$row->cuc_user_text][] = $row->cuc_agent;
					}
				}
			}

			// @todo FIXME: This form (and checkboxes) shouldn't be initiated for users without 'block' right
			$action = htmlspecialchars( $this->getPageTitle()->getLocalURL( 'action=block' ) );
			$s = "<form name='checkuserblock' id='checkuserblock' action=\"$action\" method='post'>";
			$s .= '<div id="checkuserresults"><ul>';
			foreach ( $users_edits as $name => $count ) {
				$s .= '<li>';
				$s .= Xml::check( 'users[]', false, array( 'value' => $name ) ) . '&#160;';
				// Load user object
				$user = User::newFromName( $name, false );
				// Add user tool links
				$s .= Linker::userLink( -1, $name ) . Linker::userToolLinks( -1, $name );
				// Add CheckUser link
				$s .= ' ' . $this->msg( 'parentheses' )->rawParams(
					$this->getSelfLink(
						$this->msg( 'checkuser-check' )->text(),
						array(
							'user' => $name,
							'reason' => $reason
						)
					)
				)->escaped();
				// Show edit time range
				$s .= ' ' . $this->getTimeRangeString( $users_first[$name], $users_last[$name] ) . ' ';
				// Total edit count
				// @todo FIXME: i18n issue: Hard coded brackets.
				$s .= ' [<strong>' . $count . '</strong>]<br />';
				// Check if this user or IP is blocked. If so, give a link to the block log...
				$ip = IP::isIPAddress( $name ) ? $name : '';
				$flags = $this->userBlockFlags( $ip, $users_ids[$name], $user );
				// Check how many accounts the user made recently
				if ( $ip ) {
					$key = wfMemcKey( 'acctcreate', 'ip', $ip );
					$count = intval( $wgMemc->get( $key ) );
					if ( $count ) {
						// @todo FIXME: i18n issue: Hard coded brackets.
						$flags[] = '<strong>[' . $this->msg( 'checkuser-accounts' )->numParams( $count )->escaped() . ']</strong>';
					}
				}
				$s .= implode( ' ', $flags );
				$s .= '<ol>';
				// List out each IP/XFF combo for this username
				for ( $i = ( count( $users_infosets[$name] ) - 1 ); $i >= 0; $i-- ) {
					$set = $users_infosets[$name][$i];
					// IP link
					$s .= '<li>';
					$s .= $this->getSelfLink( $set[0], array( 'user' => $set[0] ) );
					// XFF string, link to /xff search
					if ( $set[1] ) {
						// Flag our trusted proxies
						list( $client ) = CheckUserHooks::getClientIPfromXFF( $set[1] );
						// XFF was trusted if client came from it
						$trusted = ( $client === $row->cuc_ip );
						$c = $trusted ? '#F0FFF0' : '#FFFFCC';
						$s .= '&#160;&#160;&#160;<span style="background-color: ' . $c . '"><strong>XFF</strong>: ';
						$s .= $this->getSelfLink( $set[1], array( 'user' => $client . '/xff' ) ) . '</span>';
					}
					$s .= "</li>\n";
				}
				$s .= '</ol><br /><ol>';
				// List out each agent for this username
				for ( $i = ( count( $users_agentsets[$name] ) - 1 ); $i >= 0; $i-- ) {
					$agent = $users_agentsets[$name][$i];
					$s .= '<li><i>' . htmlspecialchars( $agent ) . "</i></li>\n";
				}
				$s .= '</ol>';
				$s .= '</li>';
			}
			$s .= "</ul></div>\n";
			if ( $this->getUser()->isAllowed( 'block' ) && !$this->getUser()->isBlocked() ) {
				// FIXME: The block <form> is currently added for users without 'block' right
				// - only the user-visible form is shown appropriately
				$s .= $this->getBlockForm( $tag, $talkTag );
				$s .= Html::hidden( 'wpEditToken', $this->getUser()->getEditToken() );
			}
			$s .= "</form>\n";
		}

		$out->addHTML( $s );
	}

	/**
	 * @param string $tag
	 * @param string $talkTag
	 * @return string
	 */
	protected function getBlockForm( $tag, $talkTag ) {
		global $wgBlockAllowsUTEdit;

		$s = "<fieldset>\n";
		$s .= '<legend>' . $this->msg( 'checkuser-massblock' )->escaped() . "</legend>\n";
		$s .= $this->msg( 'checkuser-massblock-text' )->parseAsBlock() . "\n";
		$s .= '<table><tr>' .
			'<td>' . Xml::check( 'usetag', false, array( 'id' => 'usetag' ) ) . '</td>' .
			'<td>' . Xml::label( $this->msg( 'checkuser-blocktag' )->escaped(), 'usetag' ) . '</td>' .
			'<td>' . Xml::input( 'tag', 46, $tag, array( 'id' => 'blocktag' ) ) . '</td>' .
			'</tr><tr>' .
			'<td>' . Xml::check( 'usettag', false, array( 'id' => 'usettag' ) ) . '</td>' .
			'<td>' . Xml::label( $this->msg( 'checkuser-blocktag-talk' )->escaped(), 'usettag' ) . '</td>' .
			'<td>' . Xml::input( 'talktag', 46, $talkTag, array( 'id' => 'talktag' ) ) . '</td>';
		if ( $wgBlockAllowsUTEdit ) {
			$s .= '</tr><tr>' .
				'<td>' . Xml::check( 'blocktalk', false, array( 'id' => 'blocktalk' ) ) . '</td>' .
				'<td>' . Xml::label( $this->msg( 'checkuser-blocktalk' )->escaped(), 'blocktalk' ) . '</td>';
		}
		if ( SpecialBlock::canBlockEmail( $this->getUser() ) ) {
			$s .= '</tr><tr>' .
				'<td>' . Xml::check( 'blockemail', false, array( 'id' => 'blockemail' ) ) . '</td>' .
				'<td>' . Xml::label( $this->msg( 'checkuser-blockemail' )->escaped(), 'blockemail' ) . '</td>';
		}
		$s .= '</tr></table>';
		$s .= '<p>' . $this->msg( 'checkuser-reason' )->escaped() . '&#160;';
		$s .= Xml::input( 'blockreason', 46, '', array( 'maxlength' => '150', 'id' => 'blockreason' ) );
		$s .= '&#160;' . Xml::submitButton( $this->msg( 'checkuser-massblock-commit' )->escaped(),
			array( 'id' => 'checkuserblocksubmit', 'name' => 'checkuserblock' ) ) . "</p>\n";
		$s .= "</fieldset>\n";

		return $s;
	}

	/**
	 * Get an HTML link (<a> element) to Special:CheckUser
	 *
	 * @param string $text content to use within <a> tag
	 * @param array $params query parameters to use in the URL
	 * @return string
	 */
	private function getSelfLink( $text, array $params ) {
		static $title;
		if ( $title === null ) {
			$title = $this->getPageTitle();
		}
		return Linker::linkKnown(
			$title,
			htmlspecialchars( $text ),
			array(),
			$params
		);
	}

	/**
	 * @param $ip
	 * @param $userId
	 * @param User $user
	 * @return array
	 */
	protected function userBlockFlags( $ip, $userId, $user ) {
		$flags = array();

		$block = Block::newFromTarget( $user, $ip, false );
		if ( $block instanceof Block ) {
			// Locally blocked
			$flags[] = $this->getBlockFlag( $block );
		} elseif ( $ip == $user->getName() && $user->isBlockedGlobally( $ip ) ) {
			// Globally blocked IP
			$flags[] = '<strong>(' . $this->msg( 'checkuser-gblocked' )->escaped() . ')</strong>';
		} elseif ( self::userWasBlocked( $user->getName() ) ) {
			// Previously blocked
			$userpage = $user->getUserPage();
			$blocklog = Linker::linkKnown(
				SpecialPage::getTitleFor( 'Log' ),
				$this->msg( 'checkuser-wasblocked' )->escaped(),
				array(),
				array(
					'type' => 'block',
					'page' => $userpage->getPrefixedText()
				)
			);
			// @todo FIXME: Hard coded parentheses.
			$flags[] = '<strong>(' . $blocklog . ')</strong>';
		}

		// Show if account is local only
		if ( $user->getId() &&
			CentralIdLookup::factory()->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW ) === 0
		) {
			// @todo FIXME: i18n issue: Hard coded parentheses.
			$flags[] = '<strong>(' . $this->msg( 'checkuser-localonly' )->escaped() . ')</strong>';
		}
		// Check for extra user rights...
		if ( $userId ) {
			if ( $user->isLocked() ) {
				// @todo FIXME: i18n issue: Hard coded parentheses.
				$flags[] = '<b>(' . $this->msg( 'checkuser-locked' )->escaped() . ')</b>';
			}
			$list = array();
			foreach ( $user->getGroups() as $group ) {
				$list[] = self::buildGroupLink( $group, $user->getName() );
			}
			$groups = $this->getLanguage()->commaList( $list );
			if ( $groups ) {
				// @todo FIXME: i18n issue: Hard coded parentheses.
				$flags[] = '<i>(' . $groups . ')</i>';
			}
		}

		return $flags;
	}

	/**
	 * Get a streamlined recent changes line with IP data
	 *
	 * @param stdClass $row
	 * @param string $reason
	 * @return string
	 */
	protected function CUChangesLine( $row, $reason ) {
		static $flagCache;
		// Add date headers as needed
		$date = $this->getLanguage()->date( wfTimestamp( TS_MW, $row->cuc_timestamp ), true, true );
		if ( !isset( $this->lastdate ) ) {
			$this->lastdate = $date;
			$line = "\n<h4>$date</h4>\n<ul class=\"special\">";
		} elseif ( $date != $this->lastdate ) {
			$line = "</ul>\n<h4>$date</h4>\n<ul class=\"special\">";
			$this->lastdate = $date;
		} else {
			$line = '';
		}
		$line .= '<li>';
		// Create diff/hist/page links
		$line .= $this->getLinksFromRow( $row );
		// Show date
		$line .= ' . . ' .
			$this->getLanguage()->time( wfTimestamp( TS_MW, $row->cuc_timestamp ), true, true )
			. ' . . ';
		// Userlinks
		$line .= Linker::userLink( $row->cuc_user, $row->cuc_user_text );
		$line .= Linker::userToolLinks( $row->cuc_user, $row->cuc_user_text );
		// Get block info
		if ( isset( $flagCache[$row->cuc_user_text] ) ) {
			$flags = $flagCache[$row->cuc_user_text];
		} else {
			$user = User::newFromName( $row->cuc_user_text, false );
			$ip = IP::isIPAddress( $row->cuc_user_text ) ? $row->cuc_user_text : '';
			$flags = $this->userBlockFlags( $ip, $row->cuc_user, $user );
			$flagCache[$row->cuc_user_text] = $flags;
		}
		// Add any block information
		if ( count( $flags ) ) {
			$line .= ' ' . implode( ' ', $flags );
		}
		// Action text, hackish ...
		if ( $row->cuc_actiontext ) {
			$line .= ' ' . Linker::formatComment( $row->cuc_actiontext ) . ' ';
		}
		// Comment
		$line .= Linker::commentBlock( $row->cuc_comment );
		$line .= '<br />&#160; &#160; &#160; &#160; <small>';
		// IP
		$line .= ' <strong>IP</strong>: ';
		$line .= $this->getSelfLink( $row->cuc_ip,
			array(
				'user' => $row->cuc_ip,
				'reason' => $reason
			)
		);
		// XFF
		if ( $row->cuc_xff != null ) {
			// Flag our trusted proxies
			list( $client ) = CheckUserHooks::getClientIPfromXFF( $row->cuc_xff );
			$trusted = ( $client === $row->cuc_ip ); // XFF was trusted if client came from it
			$c = $trusted ? '#F0FFF0' : '#FFFFCC';
			$line .= '&#160;&#160;&#160;';
			$line .= '<span class="mw-checkuser-xff" style="background-color: ' . $c . '">' .
				'<strong>XFF</strong>: ';
			$line .= $this->getSelfLink( $row->cuc_xff,
				array(
					'user' => $client . '/xff',
					'reason' => $reason
				)
			);
			$line .= '</span>';
		}
		// User agent
		$line .= '&#160;&#160;&#160;<span class="mw-checkuser-agent" style="color:#888;">' .
			htmlspecialchars( $row->cuc_agent ) . '</span>';

		$line .= "</small></li>\n";

		return $line;
	}

	/**
	 * Get formatted timestamp(s) to show the time of first and last change.
	 * If both timestamps are the same, it will be shown only once.
	 *
	 * @param string $first Timestamp of the first change
	 * @param string $last Timestamp of the last change
	 * @return string
	 */
	protected function getTimeRangeString( $first, $last ) {
		$s = $this->getFormattedTimestamp( $first );
		if ( $first !== $last ) {
			// @todo i18n issue - hardcoded string
			$s .= ' -- ';
			$s .= $this->getFormattedTimestamp( $last );
		}
		return $this->msg( 'parentheses' )->rawParams( $s )->escaped();
	}

	/**
	 * Get a formatted timestamp string in the current language
	 * for displaying to the user.
	 *
	 * @param string $timestamp
	 * @return string
	 */
	protected function getFormattedTimestamp( $timestamp ) {
		return $this->getLanguage()->timeanddate(
			wfTimestamp( TS_MW, $timestamp ), true
		);
	}

	/**
	 * @param stdClass $row
	 * @return string diff, hist and page other links related to the change
	 */
	protected function getLinksFromRow( $row ) {
		$links = array();
		// Log items
		if ( $row->cuc_type == RC_LOG ) {
			$title = Title::makeTitle( $row->cuc_namespace, $row->cuc_title );
			// @todo FIXME: Hard coded parentheses.
			$links['log'] = '(' . Linker::linkKnown(
				SpecialPage::getTitleFor( 'Log' ),
				$this->message['log'],
				array(),
				array( 'page' => $title->getPrefixedText() )
			) . ')';
		} else {
			$title = Title::makeTitle( $row->cuc_namespace, $row->cuc_title );
			// New pages
			if ( $row->cuc_type == RC_NEW ) {
				$links['diff'] = '(' . $this->message['diff'] . ') ';
			} else {
				// Diff link
				// @todo FIXME: Hard coded parentheses.
				$links['diff'] = ' (' . Linker::linkKnown(
					$title,
					$this->message['diff'],
					array(),
					array(
						'curid' => $row->cuc_page_id,
						'diff' => $row->cuc_this_oldid,
						'oldid' => $row->cuc_last_oldid
					)
				) . ') ';
			}
			// History link
			// @todo FIXME: Hard coded parentheses.
			$links['history'] = ' (' . Linker::linkKnown(
				$title,
				$this->message['hist'],
				array(),
				array(
					'curid' => $row->cuc_page_id,
					'action' => 'history'
				)
			) . ') . . ';
			// Some basic flags
			if ( $row->cuc_type == RC_NEW ) {
				$links['newpage'] = '<span class="newpage">' . $this->message['newpageletter'] . '</span>';
			}
			if ( $row->cuc_minor ) {
				$links['minor'] = '<span class="minor">' . $this->message['minoreditletter'] . '</span>';
			}
			// Page link
			$links['title'] = Linker::link( $title );
		}

		Hooks::run( 'SpecialCheckUserGetLinksFromRow', array( $this, $row, &$links ) );
		if ( is_array( $links ) ) {
			return implode( ' ', $links );
		} else {
			wfDebugLog( __CLASS__,
				__METHOD__ . ': Expected array from SpecialCheckUserGetLinksFromRow $links param,'
				. ' but received ' . gettype( $links )
			);
			return '';
		}
	}

	protected static function userWasBlocked( $name ) {
		$userpage = Title::makeTitle( NS_USER, $name );
		return (bool)wfGetDB( DB_SLAVE )->selectField( 'logging', '1',
			array(
				'log_type' => array( 'block', 'suppress' ),
				'log_action' => 'block',
				'log_namespace' => $userpage->getNamespace(),
				'log_title' => $userpage->getDBkey()
			),
			__METHOD__,
			array( 'USE INDEX' => 'page_time' ) );
	}

	/**
	 * Format a link to a group description page
	 *
	 * @param string $group
	 * @param string $username
	 * @return string
	 */
	protected static function buildGroupLink( $group, $username ) {
		static $cache = array();
		if ( !isset( $cache[$group] ) ) {
			$cache[$group] = User::makeGroupLinkHtml( $group, User::getGroupMember( $group, $username ) );
		}
		return $cache[$group];
	}

	/**
	 * @param DatabaseBase $db
	 * @param string $ip
	 * @param string|bool $xfor
	 * @return array|false array for valid conditions, false if invalid
	 */
	public static function getIpConds( $db, $ip, $xfor = false ) {
		global $wgCheckUserCIDRLimit;
		$type = $xfor ? 'xff' : 'ip';
		$matches = array();
		if ( preg_match( '#^(\d+\.\d+\.\d+\.\d+)/(\d+)$#', $ip, $matches ) ) {
			// IPv4 CIDR, 16-32 bits
			if ( $matches[2] < $wgCheckUserCIDRLimit['IPv4'] || $matches[2] > 32 ) {
				return false; // invalid
			}
			list( $start, $end ) = IP::parseRange( $ip );
			return array( 'cuc_' . $type . '_hex BETWEEN ' . $db->addQuotes( $start ) . ' AND ' . $db->addQuotes( $end ) );
		} elseif ( preg_match( '#^\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}/(\d+)$#', $ip, $matches ) ) {
			// IPv6 CIDR, 32-128 bits
			if ( $matches[1] < $wgCheckUserCIDRLimit['IPv6'] || $matches[1] > 128 ) {
				return false; // invalid
			}
			list( $start, $end ) = IP::parseRange( $ip );
			return array( 'cuc_' . $type . '_hex BETWEEN ' . $db->addQuotes( $start ) . ' AND ' . $db->addQuotes( $end ) );
		} elseif (
			// 32 bit IPv4
			preg_match( '#^(\d+)\.(\d+)\.(\d+)\.(\d+)$#', $ip ) ||
			// 128 bit IPv6
			preg_match( '#^\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}:\w{1,4}$#', $ip )
		) {
			return array( "cuc_{$type}_hex" => IP::toHex( $ip ) );
		}
		// Throw away this query, incomplete IP, these don't get through the entry point anyway
		return false;
	}

	protected function getTimeConds( $period ) {
		if ( !$period ) {
			return '1 = 1';
		}
		$dbr = wfGetDB( DB_SLAVE );
		$cutoff_unixtime = time() - ( $period * 24 * 3600 );
		$cutoff_unixtime = $cutoff_unixtime - ( $cutoff_unixtime % 86400 );
		$cutoff = $dbr->addQuotes( $dbr->timestamp( $cutoff_unixtime ) );
		return "cuc_timestamp > $cutoff";
	}

	public static function addLogEntry( $logType, $targetType, $target, $reason, $targetID = 0 ) {
		global $wgUser;

		if ( $targetType == 'ip' ) {
			list( $rangeStart, $rangeEnd ) = IP::parseRange( $target );
			$targetHex = $rangeStart;
			if ( $rangeStart == $rangeEnd ) {
				$rangeStart = $rangeEnd = '';
			}
		} else {
			$targetHex = $rangeStart = $rangeEnd = '';
		}

		$dbw = wfGetDB( DB_MASTER );
		$dbw->insert( 'cu_log',
			array(
				'cul_id' => $dbw->nextSequenceValue( 'cu_log_cul_id_seq' ),
				'cul_timestamp' => $dbw->timestamp(),
				'cul_user' => $wgUser->getId(),
				'cul_user_text' => $wgUser->getName(),
				'cul_reason' => $reason,
				'cul_type' => $logType,
				'cul_target_id' => $targetID,
				'cul_target_text' => $target,
				'cul_target_hex' => $targetHex,
				'cul_range_start' => $rangeStart,
				'cul_range_end' => $rangeEnd,
			),
			__METHOD__
		);

		// @todo FIXME: Callers expect this to return false on failure
		return true;
	}

	/**
	 * Return an array of subpages beginning with $search that this special page will accept.
	 *
	 * @param string $search Prefix to search for
	 * @param int $limit Maximum number of results to return (usually 10)
	 * @param int $offset Number of results to skip (usually 0)
	 * @return string[] Matching subpages
	 */
	public function prefixSearchSubpages( $search, $limit, $offset ) {
		if ( !class_exists( 'UserNamePrefixSearch' ) ) { // check for version 1.27
			return array();
		}
		$user = User::newFromName( $search );
		if ( !$user ) {
			// No prefix suggestion for invalid user
			return array();
		}
		// Autocomplete subpage as user list - public to allow caching
		return UserNamePrefixSearch::search( 'public', $search, $limit, $offset );
	}

	protected function getGroupName() {
		return 'users';
	}
}

