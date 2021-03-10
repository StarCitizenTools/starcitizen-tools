<?php
/**
 * Code for automatic creation of categories.
 *
 * @file
 * @author Robert Leverington
 * @author Robin Pepermans
 * @author Niklas Laxström
 * @author Brian Wolff
 * @author Purodha Blissenbach
 * @author Sam Reed
 * @author Siebrand Mazeland
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */

/**
 * Class for automatic creation of Babel category pages.
 */
class BabelAutoCreate {
	/**
	 * @var User
	 */
	protected static $user = false;

	public static function onUserGetReservedNames( &$names ) {
		$names[] = 'msg:babel-autocreate-user';

		return true;
	}

	/**
	 * Create category.
	 *
	 * @param string $category Name of category to create.
	 * @param string $code Code of language that the category is for.
	 * @param string $level Level that the category is for.
	 */
	public static function create( $category, $code, $level = null ) {
		$category = strip_tags( $category );
		$title = Title::makeTitleSafe( NS_CATEGORY, $category );
		if ( $title === null || $title->exists() ) {
			return;
		}
		global $wgLanguageCode;
		$language = BabelLanguageCodes::getName( $code, $wgLanguageCode );
		$params = [ $language, $code ];
		if ( $level === null ) {
			$text = wfMessage( 'babel-autocreate-text-main', $params )->inContentLanguage()->text();
		} else {
			array_unshift( $params, $level );
			$text = wfMessage( 'babel-autocreate-text-levels', $params )->inContentLanguage()->text();
		}

		$user = self::user();
		# Do not add a message if the username is invalid or if the account that adds it, is blocked
		if ( !$user || $user->isBlocked() ) {
			return;
		}

		if ( !$title->quickUserCan( 'create', $user ) ) {
			return; # The Babel AutoCreate account is not allowed to create the page
		}

		/* $article->doEdit will call $wgParser->parse.
		 * Calling Parser::parse recursively is baaaadd... (bug 29245)
		 * @todo FIXME: surely there is a better way?
		 */
		global $wgParser, $wgParserConf;
		$oldParser = $wgParser;
		$parserClass = $wgParserConf['class'];
		$wgParser = new $parserClass( $wgParserConf );

		$url = wfMessage( 'babel-url' )->inContentLanguage()->plain();
		$article = new WikiPage( $title );
		$article->doEdit(
			$text,
			wfMessage( 'babel-autocreate-reason', $url )->text(),
			EDIT_FORCE_BOT,
			false,
			$user
		);

		$wgParser = $oldParser;
	}

	/**
	 * Get user object.
	 *
	 * @return User User object for autocreate user.
	 */
	public static function user() {
		if ( !self::$user ) {
			$userName = wfMessage( 'babel-autocreate-user' )->inContentLanguage()->plain();
			if ( method_exists( 'User', 'newSystemUser' ) ) {
				self::$user = User::newSystemUser( $userName, [ 'steal' => true ] );
			} else {
				self::$user = User::newFromName( $userName );
				if ( self::$user && !self::$user->isLoggedIn() ) {
					self::$user->addToDatabase();
				}
			}
		}

		return self::$user;
	}
}
