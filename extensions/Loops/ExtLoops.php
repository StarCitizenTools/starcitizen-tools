<?php

/**
 * Class representing extension 'Loops', containing all parser functions and other
 * extension logic stuff.
 */
class ExtLoops {
	const VERSION = '1.0.0-beta';

	/**
	 * Sets up parser functions
	 *
	 * @since 0.4
	 */
	public static function init( Parser &$parser ) {
		/*
		 * Some functions in this extension require Variables 2.x to work properly.
		 * This function can be used to make sure Variables 2.x is installed.
		 * However, because of the limitations of Extension.json, this will only work
		 * if we can use Variables 2.3+
		 *
		 * TODO: When bumping required MediaWiki version to 1.32 (constraint added in 1.32), use
		 * ExtensionRegistry::isLoaded( 'Variables', '>= 2.3' ) instead.
		 */
		$varVersion = ExtensionRegistry::getInstance()->getAllThings()['Variables']['version'] ?? null;
		if ( $varVersion === null || !version_compare( $varVersion, '2.3', '>=' ) ) {
			/*
			 * If Variables 2.3+ is not installed, we can't use certain functions.
			 * Make sure they are disabled:
			 */
			global $egLoopsEnabledFunctions;
			$disabledFunctions = [ 'loop', 'forargs', 'fornumargs' ];
			$egLoopsEnabledFunctions = array_diff( $egLoopsEnabledFunctions, $disabledFunctions );
		} elseif (
			class_exists( 'ExtVariables' ) &&
			!version_compare( ExtVariables::VERSION, '2.3', '>=' )
		) {
			wfLogWarning(
				'You are using a version of the Variables extension below 2.3. ' .
				'Please use version 2.3+ to use features of the Loops extension requiring Variables.'
			);
		}

		/*
		 * store for loops count per parser object. This will solve several bugs related to
		 * 'ParserClearState' hook resetting the count early in combination with certain
		 * other extensions or special page inclusion. (since v0.4)
		 */
		$parser->mExtLoopsCounter = 0;

		self::initFunction( $parser, 'while' );
		self::initFunction( $parser, 'dowhile' );
		self::initFunction( $parser, 'loop' );
		self::initFunction( $parser, 'forargs' );
		self::initFunction( $parser, 'fornumargs' );
	}

	private static function initFunction( Parser $parser, $name ) {
		global $egLoopsEnabledFunctions;

		// don't register parser function if disabled by configuration:
		if ( !in_array( $name, $egLoopsEnabledFunctions ) ) {
			return;
		}

		$functionCallback = [ __CLASS__, 'pfObj_' . $name ];
		$parser->setFunctionHook( $name, $functionCallback, Parser::SFH_OBJECT_ARGS );
	}

	/**
	 * Parser functions
	 */
	public static function pfObj_while( Parser $parser, PPFrame $frame, array $args ) {
		return self::perform_while( $parser, $frame, $args, false );
	}

	public static function pfObj_dowhile( Parser $parser, PPFrame $frame, array $args ) {
		return self::perform_while( $parser, $frame, $args, true );
	}

	/**
	 * Generic function handling '#while' and '#dowhile' as one
	 */
	protected static function perform_while(
		Parser $parser,
		PPFrame $frame,
		array $args,
		$dowhile = false
	) {
		// #(do)while: | condition | code
		// unexpanded condition
		$rawCond = isset( $args[1] ) ? $args[1] : '';
		// unexpanded loop code
		$rawCode = isset( $args[2] ) ? $args[2] : '';

		if (
			$dowhile === false
			&& trim( $frame->expand( $rawCond ) ) === ''
		) {
			// while, but condition not fullfilled from the start
			return '';
		}

		$output = '';

		do {
			// limit check:
			if ( !self::incrCounter( $parser ) ) {
				return self::msgLoopsLimit( $output );
			}
			$output .= trim( $frame->expand( $rawCode ) );

		} while ( trim( $frame->expand( $rawCond ) ) );

		return $output;
	}

	public static function pfObj_loop( Parser $parser, PPFrame $frame, array $args ) {
		// #loop: var | start | count | code
		$varName  = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		$startVal = isset( $args[1] ) ? (int)trim( $frame->expand( $args[1] ) ) : 0;
		$loops    = isset( $args[2] ) ? (int)trim( $frame->expand( $args[2] ) ) : 0;
		// unexpanded loop code
		$rawCode = isset( $args[3] ) ? $args[3] : '';

		if ( $loops === 0 ) {
			// no loops to perform
			return '';
		}

		$output = '';
		$endVal = $startVal + $loops;
		$i = $startVal;

		while ( $i !== $endVal ) {
			// limit check:
			if ( !self::incrCounter( $parser ) ) {
				return self::msgLoopsLimit( $output );
			}

			// set current position as variable:
			self::setVariable( $parser, $varName, (string)$i );

			$output .= trim( $frame->expand( $rawCode ) );

			// in-/decrease loop count (count can be negative):
			( $i < $endVal ) ? $i++ : $i--;
		}
		return $output;
	}

	/**
	 * #forargs: filter | keyVarName | valVarName | code
	 */
	public static function pfObj_forargs( Parser $parser, PPFrame $frame, array $args ) {
		// The first arg is already expanded, but this is a good habit to have...
		$filter = array_shift( $args );
		$filter = $filter !== null ? trim( $frame->expand( $filter ) ) : '';

		// if prefix contains numbers only or isn't set, get all arguments, otherwise just non-numeric
		$tArgs = ( preg_match( '/^([1-9][0-9]*)?$/', $filter ) > 0 )
				? $frame->getArguments()
				: $frame->getNamedArguments();

		return self::perform_forargs( $parser, $frame, $args, $tArgs, $filter );
	}

	/**
	 * #fornumargs: keyVarName | valVarName | code
	 * or (since 0.4 for more consistency)
	 * #fornumargs: | keyVarName | valVarName | code
	 */
	public static function pfObj_fornumargs( Parser $parser, PPFrame $frame, array $args ) {
		/**
		 * get numeric arguments, don't use PPFrame::getNumberedArguments because it would
		 * return explicitely numbered arguments only.
		 */
		$tNumArgs = $frame->getArguments();
		foreach ( $tNumArgs as $argKey => $argVal ) {
			// allow all numeric, including negative values!
			if ( is_string( $argKey ) ) {
				unset( $tNumArgs[ $argKey ] );
			}
		}
		// sort from lowest to highest
		ksort( $tNumArgs );

		if ( count( $args ) > 3 ) {
			/**
			 * compatbility to pre 0.4 but consistency with other Loop functions.
			 * this way the first argument can be ommitted like '#fornumargs: |varKey |varVal |code'
			 */
			array_shift( $args );
		}

		return self::perform_forargs( $parser, $frame, $args, $tNumArgs, '' );
	}

	/**
	 * Generic function handling '#forargs' and '#fornumargs' as one
	 */
	protected static function perform_forargs(
			Parser $parser,
			PPFrame $frame,
			array $funcArgs,
			array $templateArgs,
			$prefix = ''
	) {
		// if not called within template instance:
		if ( !( $frame->isTemplate() ) ) {
			return '';
		}

		// name of the variable to store the argument name:
		$keyVar  = array_shift( $funcArgs );
		$keyVar  = $keyVar !== null ? trim( $frame->expand( $keyVar ) ) : '';
		// name of the variable to store the argument value:
		$valVar  = array_shift( $funcArgs );
		$valVar  = $valVar !== null ? trim( $frame->expand( $valVar ) ) : '';
		// unexpanded code:
		$rawCode = array_shift( $funcArgs );
		$rawCode = $rawCode !== null ? $rawCode : '';

		$output = '';

		// if prefix contains numbers only or isn't set, get all arguments, otherwise just non-numeric
		$tArgs = preg_match( '/^([1-9][0-9]*)?$/', $prefix ) > 0
				? $frame->getArguments() : $frame->getNamedArguments();

		foreach ( $templateArgs as $argName => $argVal ) {
			// if no filter or prefix in argument name:
			if ( $prefix !== '' && strpos( $argName, $prefix ) !== 0 ) {
				continue;
			}
			if ( $keyVar !== $valVar ) {
				// variable with the argument name without prefix as value:
				self::setVariable( $parser, $keyVar, substr( $argName, strlen( $prefix ) ) );
			}
			// variable with the arguments value:
			self::setVariable( $parser, $valVar, $argVal );

			// expand current run:
			$output .= trim( $frame->expand( $rawCode ) );
		}

		return $output;
	}

	/**
	 * Connects to 'Variables' extension and sets a variable.
	 * There shouldn't be any parser functions accessing this if variablesIsLoaded() is false.
	 *
	 * @param Parser $parser
	 * @param string $varName
	 * @param string $varValue
	 */
	private static function setVariable( Parser $parser, $varName, $varValue ) {
		ExtVariables::get( $parser )->setVarValue( $varName, $varValue );
	}

	/**
	 * Loops count
	 */

	/**
	 * Returns how many loops have been performed for a given Parser instance.
	 *
	 * @since 0.4
	 *
	 * @param Parser $parser
	 * @return int
	 */
	public static function getLoopsCount( Parser $parser ) {
		return $parser->mExtLoopsCounter;
	}

	/**
	 * Returns whether the maximum number of loops for the given Parser instance have
	 * been performed already.
	 *
	 * @since 0.4
	 *
	 * @param Parser $parser
	 * @return bool
	 */
	public static function maxLoopsPerformed( Parser $parser ) {
		global $egLoopsCounterLimit;
		return $egLoopsCounterLimit > -1 && $parser->mExtLoopsCounter >= $egLoopsCounterLimit;
	}

	/**
	 * If limit has not been exceeded already, this will increase the counter. If
	 * exceeded false will be returned, otherwise the new counter value
	 *
	 * @return false|int
	 */
	protected static function incrCounter( Parser $parser ) {
		if ( self::maxLoopsPerformed( $parser ) ) {
			return false;
		}
		return ++$parser->mExtLoopsCounter;
	}

	/**
	 * div wrapped error message stating maximum number of loops have been performed.
	 */
	protected static function msgLoopsLimit( $output = '' ) {
		if ( trim( $output ) !== '' ) {
			$output .= "\n";
		}
		$output .= '<div class="error">' .
			wfMessage( 'loops_max' )->inContentLanguage()->escaped() .
			'</div>';
		return $output;
	}

	// Hooks handling

	public static function onParserClearState( Parser &$parser ) {
		// reset loops counter since the parser process finished one page
		$parser->mExtLoopsCounter = 0;
	}

	public static function onParserLimitReportPrepare( $parser, $output ) {
		global $egLoopsCounterLimit;
		if ( $egLoopsCounterLimit > -1 ) {
			$output->setLimitReportData(
				'loops-limitreport-count-limited',
				[ self::getLoopsCount( $parser ), $egLoopsCounterLimit ]
			);
		} else {
			$output->setLimitReportData(
				'loops-limitreport-count-unlimited',
				[ self::getLoopsCount( $parser ) ]
			);
		}
	}

}
