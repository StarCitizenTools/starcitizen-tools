<?php

/**
 * Generate a list of changes using an Enhanced system (use javascript).
 */
class NCL extends EnhancedChangesList {
	/**
	 * Determines which version of changes list to provide, or none.
	 */
	public static function hook( User $user, Skin $skin, &$list ) {
		$list = null;

		if ( defined( 'ULS_VERSION' ) ) {
			$skin->getOutput()->addModules( 'ext.cleanchanges.uls' );
		}

		/* allow override */
		$request = $skin->getRequest();
		if ( $request->getBool( 'cleanrc' ) ) {
			$list = new NCL( $skin );
		}
		if ( $request->getBool( 'newrc' ) ) {
			$list = new EnhancedChangesList( $skin );
		}
		if ( $request->getBool( 'oldrc' ) ) {
			$list = new OldChangesList( $skin );
		}

		if ( !$list && $user->getOption( 'usenewrc' ) ) {
			$list = new NCL( $skin );
		}

		if ( $list instanceof NCL ) {
			$skin->getOutput()->addModules( 'ext.cleanchanges' );
		}

		/* If some list was specified, stop processing */
		return $list === null;
	}

	protected static $userinfo = [];

	/**
	 * @param $vars array
	 * @return bool
	 */
	public static function addScriptVariables( &$vars ) {
		$vars += self::$userinfo;
		return true;
	}

	/**
	 * String that comes between page details and the user details. By default
	 * only larger space.
	 */
	protected $userSeparator = "\xc2\xa0 \xc2\xa0";

	/**
	 * Text direction, true for ltr and false for rtl
	 */
	protected $direction = true;

	/**
	 * @param IContextSource|Skin $skin
	 */
	public function __construct( $skin ) {
		$lang = $this->getLanguage();
		parent::__construct( $skin );
		$this->direction = !$lang->isRTL();
		$this->dir = $lang->getDirMark();
	}

	/**
	 * @return String
	 */
	public function beginRecentChangesList() {
		parent::beginRecentChangesList();
		$dir = $this->direction ? 'ltr' : 'rtl';
		return
			Xml::openElement(
				'div',
				[ 'style' => "direction: $dir" ]
			);
	}

	/**
	 * @return string
	 */
	public function endRecentChangesList() {
		return $this->recentChangesBlock() . '</div>';
	}

	/**
	 * @param RCCacheEntry $rc
	 * @return int
	 */
	protected function isLog( RCCacheEntry $rc = null ) {
		if ( $rc && $rc->getAttribute( 'rc_type' ) == RC_LOG ) {
			return 2;
		}
		return 0;
	}

	/**
	 * @param RCCacheEntry $rc
	 * @return string
	 */
	protected function getLogTitle( RCCacheEntry $rc ) {
		$logtype = $rc->getAttribute( 'rc_log_type' );
		$logpage = new LogPage( $logtype );
		$logname = $logpage->getName()->escaped();
		$titleObj = SpecialPage::getTitleFor( 'Log', $logtype );
		$link = Linker::link( $titleObj, $logname );
		return $this->msg( 'parentheses' )->rawParams( $link )->escaped();
	}

	/**
	 * Format a line for enhanced recentchange (aka with JavaScript and block of lines).
	 * @param RecentChange $baseRC
	 * @param bool $watched
	 * @return string
	 */
	public function recentChangesLine( &$baseRC, $watched = false ) {
		# Create a specialised object
		$rc = RCCacheEntry::newFromParent( $baseRC );

		// Extract most used variables
		$timestamp = $rc->getAttribute( 'rc_timestamp' );
		$titleObj = $rc->getTitle();
		$rc_id = $rc->getAttribute( 'rc_id' );

		$lang = $this->getLanguage();
		$date = $lang->date( $timestamp, /* adj */ true, /* format */ true );
		$time = $lang->time( $timestamp, /* adj */ true, /* format */ true );

		# Should patrol-related stuff be shown?
		$rc->unpatrolled = $this->showAsUnpatrolled( $rc );

		$logEntry = $this->isLog( $rc );
		if ( $logEntry ) {
			$clink = $this->getLogTitle( $rc );
		} elseif ( $rc->unpatrolled && $rc->getAttribute( 'rc_type' ) == RC_NEW ) {
			# Unpatrolled new page, give rc_id in query
			$clink = linker::linkKnown(
				$titleObj,
				null,
				[],
				[ 'rcid' => $rc_id ]
			);
		} else {
			$clink = Linker::linkKnown( $titleObj );
		}

		$rc->watched   = $watched;
		$rc->link      = $this->maybeWatchedLink( $clink, $watched );
		$rc->timestamp = $time;
		$rc->numberofWatchingusers = $baseRC->numberofWatchingusers;

		$rc->_reqCurId = [ 'curid' => $rc->getAttribute( 'rc_cur_id' ) ];
		$rc->_reqOldId = [ 'oldid' => $rc->getAttribute( 'rc_this_oldid' ) ];
		$this->makeLinks( $rc );

		// Make user links
		if ( self::isDeleted( $rc, Revision::DELETED_USER ) ) {
			$rc->_user = ' <span class="history-deleted">' .
				$this->msg( 'rev-deleted-user' )->escaped() .
				'</span>';
			$rc->_userInfo = '';
			self::$userinfo += [];
		} else {
			$rc->_user = Linker::userLink(
				$rc->getAttribute( 'rc_user' ),
				$rc->getAttribute( 'rc_user_text' )
			);
			$stuff = $this->userToolLinks(
				$rc->getAttribute( 'rc_user' ),
				$rc->getAttribute( 'rc_user_text' )
			);
			// TODO: userToolLinks can return ''
			self::$userinfo += $stuff[1];
			$rc->_userInfo = $stuff[0];
		}

		if ( !$this->isLog( $rc ) ) {
			$rc->_comment = $this->getComment( $rc );
		}

		$rc->_watching = $this->numberofWatchingusers( $baseRC->numberofWatchingusers );

		# If it's a new day, add the headline and flush the cache
		$ret = '';
		if ( $date !== $this->lastdate ) {
			# Process current cache
			$ret = $this->recentChangesBlock();
			$this->rc_cache = [];
			$ret .= Xml::element( 'h4', null, $date ) . "\n";
			$this->lastdate = $date;
		}

		# Put accumulated information into the cache, for later display
		# Page moves go on their own line
		if ( $logEntry ) {
			$secureName = $this->getLogTitle( $rc );
		} else {
			$secureName = $titleObj->getPrefixedDBkey();
		}
		$this->rc_cache[$secureName][] = $rc;

		return $ret;
	}

	/**
	 * @param RCCacheEntry $rc
	 */
	protected function makeLinks( RCCacheEntry $rc ) {
		/* These will be overriden with actual links below, if applicable */
		$rc->_curLink  = $this->message['cur'];
		$rc->_diffLink = $this->message['diff'];
		$rc->_lastLink = $this->message['last'];
		$rc->_histLink = $this->message['hist'];

		if ( !$this->isLog( $rc ) ) {
			# Make cur, diff and last links
			$querycur = [ 'diff' => 0 ] + $rc->_reqCurId + $rc->_reqOldId;
			$querydiff = [
				'diff'  => $rc->getAttribute( 'rc_this_oldid' ),
				'oldid' => $rc->getAttribute( 'rc_last_oldid' ),
				'rcid'  => $rc->unpatrolled ? $rc->getAttribute( 'rc_id' ) : '',
			] + $rc->_reqCurId;

			$rc->_curLink = Linker::linkKnown( $rc->getTitle(),
					$this->message['cur'], [], $querycur );

			if ( $rc->getAttribute( 'rc_type' ) != RC_NEW ) {
				$rc->_diffLink = Linker::linkKnown( $rc->getTitle(),
					$this->message['diff'], [], $querydiff );
			}

			if ( $rc->getAttribute( 'rc_last_oldid' ) != 0 ) {
				// This is not the first revision
				$rc->_lastLink = Linker::linkKnown( $rc->getTitle(),
					$this->message['last'], [], $querydiff );
			}

			$rc->_histLink = Linker::link( $rc->getTitle(),
				$this->message['hist'], [],
				$rc->_reqCurId + [ 'action' => 'history' ]
			);
		}
	}

	/**
	 * Enhanced RC group
	 * @param RCCacheEntry[] $block
	 * @return string
	 */
	protected function recentChangesBlockGroup( $block ) {
		# Collate list of users
		$isnew = false;
		$userlinks = [];
		$overrides = [ 'minor' => false, 'bot' => false ];
		$oldid = 0;
		foreach ( $block as $rcObj ) {
			$oldid = $rcObj->mAttribs['rc_last_oldid'];
			if ( $rcObj->mAttribs['rc_new'] ) {
				$isnew = $overrides['new'] = true;
			}
			$u = $rcObj->_user;
			if ( !isset( $userlinks[$u] ) ) {
				$userlinks[$u] = 0;
			}
			if ( $rcObj->unpatrolled ) {
				$overrides['patrol'] = true;
			}

			$userlinks[$u]++;
		}

		# Main line, flags and timestamp

		$info = Xml::tags( 'code', null,
			$this->getFlags( $block[0], $overrides ) . ' ' . $block[0]->timestamp );
		$rci = 'RCI' . $this->rcCacheIndex;
		$rcl = 'RCL' . $this->rcCacheIndex;
		$rcm = 'RCM' . $this->rcCacheIndex;
		$linkAttribs = [
			'data-mw-cleanchanges-level' => $rci,
			'data-mw-cleanchanges-other' => $rcm,
			'data-mw-cleanchanges-link' => $rcl,
			'href' => '#',
			'class' => 'mw-cleanchanges-showblock'
		];
		$tl =
		Xml::tags( 'span', [ 'id' => $rcm ],
			Xml::tags( 'a', $linkAttribs,
				$this->arrow( $this->direction ? 'r' : 'l' ) ) ) .
		Xml::tags( 'span', [ 'id' => $rcl, 'style' => 'display: none;' ],
			Xml::tags( 'a', $linkAttribs, $this->downArrow() ) );

		$items[] = $tl . $info;

		# Article link
		$items[] = $block[0]->link;

		$log = $this->isLog( $block[0] );
		if ( !$log ) {
			# Changes
			$n = count( $block );
			static $nchanges = [];
			if ( !isset( $nchanges[$n] ) ) {
				$nchanges[$n] = $this->msg( 'nchanges' )->numParams( $n )->escaped();
			}

			if ( !$isnew ) {
				$changes = Linker::linkKnown(
					$block[0]->getTitle(),
					$nchanges[$n],
					[],
					[
						'curid' => $block[0]->mAttribs['rc_cur_id'],
						'diff' => $block[0]->mAttribs['rc_this_oldid'],
						'oldid' => $oldid
					]
				);
			} else {
				$changes = $nchanges[$n];
			}

			$size = $this->getCharacterDifference( $block[0], $block[count( $block ) -1] );
			$items[] = $this->changeInfo( $changes, $block[0]->_histLink, $size );
		}

		$items[] = $this->userSeparator;

		# Sort the list and convert to text
		$items[] = $this->makeUserlinks( $userlinks );
		$items[] = $block[0]->_watching;

		$lines = Xml::tags( 'div', null, implode( " {$this->dir}", $items ) ) . "\n";

		# Sub-entries
		$lines .= Xml::tags( 'div',
			[ 'id' => $rci, 'style' => 'display: none;' ],
			$this->subEntries( $block )
		) . "\n";

		$this->rcCacheIndex++;
		return $lines . "\n";
	}

	/**
	 * Generate HTML for an arrow or placeholder graphic
	 * @param string $dir One of '', 'd', 'l', 'r'
	 * @param string $alt
	 * @param string $title
	 * @return string HTML "<img>" tag
	 */
	protected function arrow( $dir, $alt = '', $title = '' ) {
		global $wgExtensionAssetsPath;

		return Html::element(
			'img',
			[
				'src' => "$wgExtensionAssetsPath/CleanChanges/images/Arr_$dir.png",
				'width' => 12,
				'height' => 12,
				'alt' => $alt,
				'title' => $title,
			]
		);
	}

	/**
	 * Generate HTML for a right- or left-facing arrow,
	 * depending on language direction.
	 * @return string HTML "<img>" tag
	 */
	protected function sideArrow() {
		$dir = $this->getLanguage()->isRTL() ? 'l' : 'r';

		return $this->arrow( $dir, '+', $this->msg( 'rc-enhanced-expand' )->text() );
	}

	/**
	 * Generate HTML for a down-facing arrow
	 * depending on language direction.
	 * @return string HTML "<img>" tag
	 */
	protected function downArrow() {
		return $this->arrow( 'd', '-', $this->msg( 'rc-enhanced-hide' )->text() );
	}

	/**
	 * Generate HTML for a spacer image
	 * @return string HTML "<img>" tag
	 */
	protected function spacerArrow() {
		return $this->arrow( '', UtfNormal\Utils::codepointToUtf8( 0xa0 ) ); // non-breaking space
	}

	/**
	 * @param RCCacheEntry[] $block
	 * @return string
	 */
	protected function subEntries( array $block ) {
		$lines = '';
		foreach ( $block as $rcObj ) {
			$items = [];
			$log = $this->isLog( $rcObj );

			$time = $rcObj->timestamp;
			if ( !$log ) {
				$time = Linker::linkKnown(
					$rcObj->getTitle(),
					$rcObj->timestamp,
					[],
					$rcObj->_reqOldId + $rcObj->_reqCurId
				);
			}

			$info = $this->getFlags( $rcObj ) . ' ' . $time;
			$items[] = $this->spacerArrow() . Xml::tags( 'code', null, $info );

			if ( !$log ) {
				$cur  = $rcObj->_curLink;
				$last = $rcObj->_lastLink;

				if ( $block[0] === $rcObj ) {
					// no point diffing first to first
					$cur = $this->message['cur'];
				}

				$items[] = $this->changeInfo( $cur, $last, $this->getCharacterDifference( $rcObj ) );
			}

			$items[] = $this->userSeparator;

			if ( $this->isLog( $rcObj ) ) {
				$items[] = $this->insertLogEntry( $rcObj );
			} else {
				$items[] = $rcObj->_user;
				$items[] = $rcObj->_userInfo;
				$items[] = $rcObj->_comment;
			}

			$lines .= '<div>' . implode( " {$this->dir}", $items ) . "</div>\n";
		}
		return $lines;
	}

	/**
	 * @param string $diff
	 * @param string $hist
	 * @param mixed $size
	 * @return string
	 */
	protected function changeInfo( $diff, $hist, $size ) {
		if ( is_int( $size ) ) {
			$size = $this->wrapCharacterDifference( $size );
			// FIXME: i18n: Hard coded parentheses and spaces.
			return $this->msg( 'cleanchanges-rcinfo-3' )->rawParams( $diff, $hist, $size )->escaped();
		} else {
			return $this->msg( 'cleanchanges-rcinfo-2' )->rawParams( $diff, $hist )->escaped();
		}
	}

	/**
	 * Enhanced RC ungrouped line.
	 * @param RCCacheEntry $rcObj
	 * @return string a HTML formated line
	 */
	protected function recentChangesBlockLine( $rcObj ) {
		# Flag and Timestamp
		$info = $this->getFlags( $rcObj ) . ' ' . $rcObj->timestamp;
		$items[] = $this->spacerArrow() . Xml::tags( 'code', null, $info );

		# Article link
		$items[] = $rcObj->link;

		if ( !$this->isLog( $rcObj ) ) {
			$items[] = $this->changeInfo( $rcObj->_diffLink, $rcObj->_histLink,
				$this->getCharacterDifference( $rcObj )
			);
		}

		$items[] = $this->userSeparator;

		if ( $this->isLog( $rcObj ) ) {
			$items[] = $this->insertLogEntry( $rcObj );
		} else {
			$items[] = $rcObj->_user;
			$items[] = $rcObj->_userInfo;
			$items[] = $rcObj->_comment;
			$items[] = $rcObj->_watching;
		}

		return '<div>' . implode( " {$this->dir}", $items ) . "</div>\n";
	}

	/**
	 * @param RCCacheEntry $rc
	 * @return string
	 */
	public function getComment( RCCacheEntry $rc ) {
		$comment = $rc->getAttribute( 'rc_comment' );
		$action = '';
		if ( $comment === '' ) {
			return $action;
		} elseif ( self::isDeleted( $rc, LogPage::DELETED_COMMENT ) ) {
			$priviledged = $this->getUser()->isAllowed( 'deleterevision' );
			if ( $priviledged ) {
				return $action . ' <span class="history-deleted">' .
					Linker::formatComment( $comment ) .
					'</span>';
			}
			return $action . ' <span class="history-deleted">' .
				$this->msg( 'rev-deleted-comment' )->escaped() .
				'</span>';
		}
		return $action . Linker::commentBlock( $comment, $rc->getTitle() );
	}

	/**
	 * Enhanced user tool links, with javascript functionality.
	 * @param int $userId user id, 0 for anons
	 * @param string $userText username
	 * @return array|string Either an array of html and array of messages, or ''
	 *	[0]: html span and links to user tools
	 * 	[1]: array of escaped message strings
	 */
	public function userToolLinks( $userId, $userText ) {
		global $wgDisableAnonTalk;
		$talkable = !( $wgDisableAnonTalk && 0 == $userId );

		/*
		 * Assign each different user a running id. This is used to show user tool
		 * links on demand with javascript, to reduce page size when one user has
		 * multiple changes.
		 *
		 * $linkindex is the running id, and $users contain username -> html snippet
		 * for javascript.
		 */

		static $linkindex = 0;
		$linkindex++;

		static $users = [];
		$userindex = array_search( $userText, $users, true );
		if ( $userindex === false ) {
			$users[] = $userText;
			$userindex = count( $users ) -1;
		}

		global $wgExtensionAssetsPath;
		$image = Xml::element( 'img', [
			'src' => $wgExtensionAssetsPath . '/CleanChanges/images/showuserlinks.png',
			'alt' => $this->msg( 'cleanchanges-showuserlinks' )->text(),
			'title' => $this->msg( 'cleanchanges-showuserlinks' )->text(),
			'width' => '15',
			'height' => '11',
			]
		);

		$rci = 'RCUI' . $userindex;
		$rcl = 'RCUL' . $linkindex;
		$rcm = 'RCUM' . $linkindex;
		$linkAttribs = [
			'href' => '#',
			'class' => 'mw-cleanchanges-showuserinfo',
			'data-mw-userinfo-id' => $rci,
			'data-mw-userinfo-target' => $rcl
		];
		$tl  = Xml::tags( 'span', [ 'id' => $rcm ],
			Xml::tags( 'a', $linkAttribs, $image )
		);
		$tl .= Xml::element( 'span', [ 'id' => $rcl ], ' ' );

		$items = [];
		if ( $talkable ) {
			$items[] = Linker::userTalkLink( $userId, $userText );
		}
		if ( $userId ) {
			$targetPage = SpecialPage::getTitleFor( 'Contributions', $userText );
			$items[] = Linker::linkKnown( $targetPage,
				$this->msg( 'contribslink' )->escaped() );
		}
		if ( $this->getUser()->isAllowed( 'block' ) ) {
			$items[] = Linker::blockLink( $userId, $userText );
		}
		if ( $userId ) {
			$userrightsPage = new UserrightsPage();
			if ( $userrightsPage->userCanChangeRights( User::newFromId( $userId ) ) ) {
				$targetPage = SpecialPage::getTitleFor( 'Userrights', $userText );
				$items[] = Linker::linkKnown( $targetPage,
					$this->msg( 'cleanchanges-changerightslink' )->escaped() );
			}
		}

		if ( $items ) {
			$msg = $this->msg( 'parentheses' )
				->rawParams( $this->getLanguage()->pipeList( $items ) )
				->escaped();
			$data = [ "wgUserInfo$rci" => $msg ];

			return [ $tl, $data ];
		} else {
			return '';
		}
	}

	/**
	 * Makes aggregated list of contributors for a changes group.
	 * Example: [Usera; AnotherUser; ActiveUser ‎(2×); Userabc ‎(6×)]
	 */
	protected function makeUserlinks( $userlinks ) {
		/*
		 * User with least changes first, and fallback to alphabetical sorting if
		 * multiple users have same number of changes.
		 */
		krsort( $userlinks );
		asort( $userlinks );

		$users = [];
		foreach ( $userlinks as $userlink => $count ) {
			$text = $userlink;
			if ( $count > 1 ) {
				$lang = $this->getLanguage();
				$count = $lang->formatNum( $count );
				$text .= "{$lang->getDirMark()}×$count";
			}
			array_push( $users, $text );
		}
		$text = implode( '; ', $users );
		return $this->XMLwrapper( 'changedby', "[$text]", 'span', false );
	}

	/**
	 * @param RCCacheEntry $rc
	 * @param array $overrides
	 * @return string
	 */
	protected function getFlags( $rc, array $overrides = null ) {
		// @todo We assume all characters are of equal width, which they may be not
		$map = [
			# item  =>        field       letter-or-something
			'new'   => [ 'rc_new',   self::flag( 'newpage' ) ],
			'minor' => [ 'rc_minor', self::flag( 'minor' ) ],
			'bot'   => [ 'rc_bot',   self::flag( 'bot' ) ],
		];

		static $nothing = "\xc2\xa0";

		$items = [];
		foreach ( $map as $item => $data ) {
			list( $field, $flag ) = $data;
			$bool = isset( $overrides[$item] ) ? $overrides[$item] : $rc->getAttribute( $field );
			$items[] = $bool ? $flag : $nothing;
		}

		if ( $this->getUser()->useRCPatrol() ) {
			if ( isset( $overrides['patrol'] ) ) {
				$items[] = $overrides['patrol'] ? self::flag( 'unpatrolled' ) : $nothing;
			} elseif ( $this->showAsUnpatrolled( $rc ) ) {
				$items[] = self::flag( 'unpatrolled' );
			} else {
				$items[] = $nothing;
			}
		}

		return implode( '', $items );
	}

	/**
	 * @param RCCacheEntry $new
	 * @param RCCacheEntry|null $old
	 * @return mixed
	 */
	protected function getCharacterDifference( $new, $old = null ) {
		if ( $old === null ) {
			$old = $new;
		}

		$newSize = $new->getAttribute( 'rc_new_len' );
		$oldSize = $old->getAttribute( 'rc_old_len' );
		if ( $newSize === null || $oldSize === null ) {
			// @todo Return null instead of string here?
			return '';
		}

		return $newSize - $oldSize;
	}

	/**
	 * @param mixed $szdiff Character difference.
	 * @return string
	 */
	public function wrapCharacterDifference( $szdiff ) {
		global $wgRCChangedSizeThreshold;
		static $cache = [];
		if ( !isset( $cache[$szdiff] ) ) {
			// @todo FIXME: Hard coded text (+).
			$prefix = $szdiff > 0 ? '+' : '';
			$cache[$szdiff] = $prefix . $this->msg( 'rc-change-size',
				$this->getLanguage()->formatNum( $szdiff )
			)->text();
		}

		$tag = 'span';
		if ( abs( $szdiff ) > abs( $wgRCChangedSizeThreshold ) ) {
			$tag = 'strong';
		}

		if ( $szdiff === 0 ) {
			return $this->XMLwrapper( 'mw-plusminus-null', $cache[$szdiff], $tag );
		} elseif ( $szdiff > 0 ) {
			return $this->XMLwrapper( 'mw-plusminus-pos', $cache[$szdiff], $tag );
		}
		return $this->XMLwrapper( 'mw-plusminus-neg', $cache[$szdiff], $tag );
	}

	/**
	 * @param $class
	 * @param $content
	 * @param string $tag
	 * @param bool $escape
	 * @return string
	 */
	protected function XMLwrapper( $class, $content, $tag = 'span', $escape = true ) {
		if ( $escape ) {
			return Xml::element( $tag, [ 'class' => $class ], $content );
		}
		return Xml::tags( $tag, [ 'class' => $class ], $content );
	}
}
