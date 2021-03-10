<?php

class RSSUtils {

	/**
	 * Output an error message, all wraped up nicely.
	 * @param String $errorMessageName The system message that this error is
	 * @param String|Array $param Error parameter (or parameters)
	 * @return String Html that is the error.
	 */
	public static function RSSError( $errorMessageName, $param = false ) {
		// Anything from a parser tag should use Content lang for message,
		// since the cache doesn't vary by user language: use ->inContentLanguage()
		// The ->parse() part makes everything safe from an escaping standpoint.

		return Html::rawElement( 'span', [ 'class' => 'error' ],
			"Extension:RSS -- Error: " . wfMessage( $errorMessageName )
				->inContentLanguage()->params( $param )->parse()
		);
	}

}
