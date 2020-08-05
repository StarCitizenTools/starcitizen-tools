<?php
/**
 * Class for handling the parser functions for External Data.
 */

class EDParserFunctions {

	/**
	 * A helper function, called by doGetWebData().
	 */
	public static function setGlobalValuesArray( array $external_values, array $filters, array $mappings ) {
		global $edgValues;

		foreach ( $filters as $filter_var => $filter_value ) {
			// Find the entry of $external_values that matches
			// the filter variable; if none exists, just ignore
			// the filter.
			if ( array_key_exists( $filter_var, $external_values ) ) {
				if ( is_array( $external_values[$filter_var] ) ) {
					$column_values = $external_values[$filter_var];
					foreach ( $column_values as $i => $single_value ) {
						// if a value doesn't match
						// the filter value, remove
						// the value from this row for
						// all columns
						if ( trim( $single_value ) != trim( $filter_value ) ) {
							foreach ( $external_values as $external_var => $external_value ) {
								unset( $external_values[$external_var][$i] );
							}
						}
					}
				} else {
					// if we have only one row of values,
					// and the filter doesn't match, just
					// keep the results array blank and
					// return
					if ( $external_values[$filter_var] != $filter_value ) {
						return;
					}
				}
			}
		}
		// for each external variable name specified in the function
		// call, get its value or values (if any exist), and attach it
		// or them to the local variable name
		foreach ( $mappings as $local_var => $external_var ) {
			if ( array_key_exists( $external_var, $external_values ) ) {
				if ( is_array( $external_values[$external_var] ) ) {
					// array_values() restores regular
					// 1, 2, 3 indexes to array, after unset()
					// in filtering may have removed some
					$edgValues[$local_var] = array_values( $external_values[$external_var] );
				} else {
					$edgValues[$local_var][] = $external_values[$external_var];
				}
			}
		}
	}

	/**
	 * Common code for doGetWebData and doGetFileData.
	 */
	private static function prepareTextProcessing( Parser &$parser, array $params ) {
		global $edgCurPageName, $edgValues, $edgCacheExpireTime;

		// If we're handling multiple pages, reset $edgValues
		// when we move from one page to another.
		$cur_page_name = $parser->getTitle()->getText();
		if ( !isset( $edgCurPageName ) || $edgCurPageName !== $cur_page_name ) {
			$edgValues = [];
			$edgCurPageName = $cur_page_name;
		}

		$args = EDUtils::parseParams( $params ); // parse params into name-value pairs

		// Preliminary format:
		$format = array_key_exists( 'format', $args ) ? strtolower( $args['format'] ) : '';

		// Final format:
		// XPath:
		if ( array_key_exists( 'use xpath', $args ) && ( $format === 'xml' || $format === 'html' ) ) {
			$format .= ' with xpath';
		}

		if ( $format === 'html' && !class_exists( 'Symfony\Component\CssSelector\CssSelectorConverter' ) ) {
			// Addressing DOM nodes with CSS/jQuery-like selectors requires symfony/css-selector.
			return EDUtils::formatErrorMessage( wfMessage( 'externaldata-no-css-selector', 'symfony/css-selector', 'HTML', 'use xpath' )->parse() );
		}

		// JSONPath:
		if ( array_key_exists( 'use jsonpath', $args ) && ( $format === 'json' ) ) {
			$format .= ' with jsonpath';
		}

		// CSV:
		if ( $format === 'csv' || $format === 'csv with header' ) {
			if ( array_key_exists( 'delimiter', $args ) ) {
				$delimiter = $args['delimiter'];
				// Allow for tab delimiters, using \t.
				$delimiter = str_replace( '\t', "\t", $delimiter );
				// Hopefully this solution isn't "too clever".
				$format = [ $format, $delimiter ];
			}
		}

		// Regular expression for text format:
		$regex = $format === 'text' && array_key_exists( 'regex', $args )
			? html_entity_decode( $args['regex'] )
			: null;

		if ( array_key_exists( 'data', $args ) ) {
			// Parse the 'data' arg into mappings.
			$lc_values = !( $format === 'xml with xpath' || $format === 'html with xpath' || $format === 'text' || $format === 'json with jsonpath' );
			$mappings = EDUtils::paramToArray( $args['data'], false, $lc_values );
		} else {
			return EDUtils::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'data' )->parse() );
		}

		$cacheExpireTime = array_key_exists( 'cache seconds', $args ) ? $args['cache seconds'] : $edgCacheExpireTime;

		$prefixLength = array_key_exists( 'json offset', $args ) ? $args['json offset'] : 0;

		$filters = array_key_exists( 'filters', $args ) ? EDUtils::paramToArray( $args['filters'], true, false ) : [];

		return [ $args, $format, $regex, $mappings, $cacheExpireTime, $prefixLength, $filters ];
	}

	/**
	 * Render the #get_web_data parser function.
	 */
	static function doGetWebData( Parser &$parser ) {
		$params = func_get_args();
		array_shift( $params ); // we already know the $parser ...
		$parsed = self::prepareTextProcessing( $parser, $params );

		if ( !is_array( $parsed ) ) {
			// Parsing parameters ended in error.
			return $parsed;
		}

		// self::prepareTextProcessing () hasn't returned an error:
		list( $args, $format, $regex, $mappings, $cacheExpireTime, $prefixLength, $filters ) = $parsed;

		// Parameters specific to {{#get_web_data:}}
		if ( array_key_exists( 'url', $args ) ) {
			$url = $args['url'];
		} else {
			return EDUtils::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'url' )->parse() );
		}
		$url = str_replace( ' ', '%20', $url ); // do some minor URL-encoding
		// if the URL isn't allowed (based on a whitelist), exit
		if ( !EDUtils::isURLAllowed( $url ) ) {
			return EDUtils::formatErrorMessage( "URL is not allowed" );
		}

		$postData = array_key_exists( 'post data', $args ) ? $args['post data'] : '';

		$external_values = EDUtils::getDataFromURL( $url, $format, $mappings, $postData, $cacheExpireTime, $prefixLength, $regex );

		if ( is_string( $external_values ) ) {
			// It's an error message - display it on the screen.
			return EDUtils::formatErrorMessage( $external_values );
		}
		if ( count( $external_values ) === 0 ) {
			return;
		}

		self::setGlobalValuesArray( $external_values, $filters, $mappings );
	}

	/**
	 * Render the #get_file_data parser function.
	 */
	static function doGetFileData( Parser &$parser ) {
		$params = func_get_args();
		array_shift( $params ); // we already know the $parser ...
		$parsed = self::prepareTextProcessing( $parser, $params );

		if ( !is_array( $parsed ) ) {
			// Parsing parameters ended in error.
			return $parsed;
		}

		// self::prepareTextProcessing () hasn't returned an error:
		list( $args, $format, $regex, $mappings, $cacheExpireTime, $prefixLength, $filters ) = $parsed;

		// Parameters specific to {{#get_file_data:}}
		if ( array_key_exists( 'file', $args ) ) {
			$file = $args['file'];
		} elseif ( array_key_exists( 'directory', $args ) ) {
			$directory = $args['directory'];
			if ( array_key_exists( 'file name', $args ) ) {
				$fileName = $args['file name'];
			} else {
				return EDUtils::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'file name' )->parse() );
			}
		} else {
			return EDUtils::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'file|directory' )->parse() );
		}

		if ( isset( $file ) ) {
			$external_values = EDUtils::getDataFromFile( $file, $format, $mappings, $regex );
		} else {
			$external_values = EDUtils::getDataFromDirectory( $directory, $fileName, $format, $mappings, $regex );
		}

		if ( is_string( $external_values ) ) {
			// It's an error message - display it on the screen.
			return EDUtils::formatErrorMessage( $external_values );
		}
		if ( count( $external_values ) === 0 ) {
			return;
		}

		self::setGlobalValuesArray( $external_values, $filters, $mappings );
	}

	/**
	 * Render the #get_soap_data parser function.
	 */
	static function doGetSOAPData( Parser &$parser ) {
		global $edgCurPageName, $edgValues;

		// If we're handling multiple pages, reset $edgValues
		// when we move from one page to another.
		$cur_page_name = $parser->getTitle()->getText();
		if ( !isset( $edgCurPageName ) || $edgCurPageName != $cur_page_name ) {
			$edgValues = [];
			$edgCurPageName = $cur_page_name;
		}

		$params = func_get_args();
		array_shift( $params ); // we already know the $parser ...
		$args = EDUtils::parseParams( $params ); // parse params into name-value pairs
		if ( array_key_exists( 'url', $args ) ) {
			$url = $args['url'];
		} else {
			return EDUtils::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'url' )->parse() );
		}
		$url = str_replace( ' ', '%20', $url ); // do some minor URL-encoding
		// If the URL isn't allowed (based on a whitelist), exit.
		if ( !EDUtils::isURLAllowed( $url ) ) {
			return EDUtils::formatErrorMessage( "URL is not allowed" );
		}

		if ( array_key_exists( 'request', $args ) ) {
			$requestName = $args['request'];
		} else {
			return EDUtils::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'request' )->parse() );
		}

		if ( array_key_exists( 'requestData', $args ) ) {
			$requestData = EDUtils::paramToArray( $args['requestData'] );
		} else {
			return EDUtils::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'requestData' )->parse() );
		}

		if ( array_key_exists( 'response', $args ) ) {
			$responseName = $args['response'];
		} else {
			return EDUtils::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'response' )->parse() );
		}

		if ( array_key_exists( 'data', $args ) ) {
			$mappings = EDUtils::paramToArray( $args['data'] ); // parse the data arg into mappings
		} else {
			return EDUtils::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'data' )->parse() );
		}

		$external_values = EDUtils::getSOAPData( $url, $requestName, $requestData, $responseName, $mappings );
		if ( is_string( $external_values ) ) {
			// It's an error message - display it on the screen.
			return EDUtils::formatErrorMessage( $external_values );
		}

		self::setGlobalValuesArray( $external_values, [], $mappings );
	}

	/**
	 * Render the #get_ldap_data parser function.
	 */
	static function doGetLDAPData( Parser &$parser ) {
		global $edgCurPageName, $edgValues;

		// If we're handling multiple pages, reset $edgValues
		// when we move from one page to another.
		$cur_page_name = $parser->getTitle()->getText();
		if ( !isset( $edgCurPageName ) || $edgCurPageName != $cur_page_name ) {
			$edgValues = [];
			$edgCurPageName = $cur_page_name;
		}

		$params = func_get_args();
		array_shift( $params ); // we already know the $parser ...
		$args = EDUtils::parseParams( $params ); // parse params into name-value pairs
		if ( array_key_exists( 'data', $args ) ) {
			$mappings = EDUtils::paramToArray( $args['data'] ); // parse the data arg into mappings
		} else {
			return EDUtils::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'data' )->parse() );
		}

		if ( !array_key_exists( 'filter', $args ) ) {
			return EDUtils::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'filter' )->parse() );
		} elseif ( !array_key_exists( 'domain', $args ) ) {
			return EDUtils::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'domain' )->parse() );
		} else {
			$external_values = EDUtils::getLDAPData( $args['filter'], $args['domain'], array_values( $mappings ) );
		}

		// Build $edgValues
		foreach ( $external_values as $i => $row ) {
			if ( !is_array( $row ) ) {
				continue;
			}
			foreach ( $mappings as $local_var => $external_var ) {
				if ( array_key_exists( $external_var, $row ) ) {
					$edgValues[$local_var][] = $row[$external_var][0];
				} else {
					$edgValues[$local_var][] = '';
				}
			}
		}
	}

	/**
	 * Render the #get_db_data parser function.
	 */
	static function doGetDBData( Parser &$parser ) {
		global $edgCurPageName, $edgValues;

		// If we're handling multiple pages, reset $edgValues
		// when we move from one page to another.
		$cur_page_name = $parser->getTitle()->getText();
		if ( !isset( $edgCurPageName ) || $edgCurPageName != $cur_page_name ) {
			$edgValues = [];
			$edgCurPageName = $cur_page_name;
		}

		$params = func_get_args();
		array_shift( $params ); // we already know the $parser ...
		$args = EDUtils::parseParams( $params ); // parse params into name-value pairs
		$data = ( array_key_exists( 'data', $args ) ) ? $args['data'] : null;
		if ( array_key_exists( 'db', $args ) ) {
			$dbID = $args['db'];
		} elseif ( array_key_exists( 'server', $args ) ) {
			// For backwards-compatibility - 'db' parameter was
			// added in External Data version 1.3.
			$dbID = $args['server'];
		} else {
			return EDUtils::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'db' )->parse() );
		}
		if ( array_key_exists( 'from', $args ) ) {
			$from = $args['from'];
		} else {
			return EDUtils::formatErrorMessage( wfMessage( 'externaldata-no-param-specified', 'from' )->parse() );
		}
		$conds = ( array_key_exists( 'where', $args ) ) ? $args['where'] : null;
		$limit = ( array_key_exists( 'limit', $args ) ) ? $args['limit'] : null;
		$orderBy = ( array_key_exists( 'order by', $args ) ) ? $args['order by'] : null;
		$groupBy = ( array_key_exists( 'group by', $args ) ) ? $args['group by'] : null;
		$sqlOptions = [ 'LIMIT' => $limit, 'ORDER BY' => $orderBy, 'GROUP BY' => $groupBy ];
		$joinOn = ( array_key_exists( 'join on', $args ) ) ? $args['join on'] : null;
		$otherParams = [];
		if ( array_key_exists( 'aggregate', $args ) ) {
			$otherParams['aggregate'] = $args['aggregate'];
		} elseif ( array_key_exists( 'find query', $args ) ) {
			$otherParams['find query'] = $args['find query'];
		}
		$mappings = EDUtils::paramToArray( $data ); // parse the data arg into mappings

		$external_values = EDUtils::getDBData( $dbID, $from, array_values( $mappings ), $conds, $sqlOptions, $joinOn, $otherParams );

		// Handle error cases.
		if ( !is_array( $external_values ) ) {
			return EDUtils::formatErrorMessage( $external_values );
		}

		// Build $edgValues.
		foreach ( $mappings as $local_var => $external_var ) {
			if ( array_key_exists( $external_var, $external_values ) ) {
				foreach ( $external_values[$external_var] as $value ) {
					$edgValues[$local_var][] = $value;
				}
			}
		}
	}

	/**
	 * Get the specified index of the array for the specified local
	 * variable retrieved by one of the #get... parser functions.
	 */
	static function getIndexedValue( $var, $i ) {
		global $edgValues;
		if ( array_key_exists( $var, $edgValues ) && array_key_exists( $i, $edgValues[$var] ) ) {
			return $edgValues[$var][$i];
		} else {
			return '';
		}
	}

	/**
	 * Render the #external_value parser function.
	 */
	static function doExternalValue( Parser &$parser, $local_var = '' ) {
		global $edgValues, $edgExternalValueVerbose;
		if ( !array_key_exists( $local_var, $edgValues ) ) {
			return $edgExternalValueVerbose ? EDUtils::formatErrorMessage( "Error: no local variable \"$local_var\" was set." ) : '';
		} elseif ( is_array( $edgValues[$local_var] ) ) {
			return $edgValues[$local_var][0];
		} else {
			return $edgValues[$local_var];
		}
	}

	/**
	 * Render the #for_external_table parser function.
	 */
	static function doForExternalTable( Parser &$parser, $expression = '' ) {
		global $edgValues;

		// Get the variables used in this expression, get the number
		// of values for each, and loop through.
		$matches = [];
		preg_match_all( '/{{{([^}]*)}}}/', $expression, $matches );
		$variables = $matches[1];
		$num_loops = 0;

		$commands = [ "urlencode", "htmlencode" ];
		// Used for a regexp check.
		$commandsStr = implode( '|', $commands );

		foreach ( $variables as $variable ) {
			// If it ends with one of the pre-defined "commands",
			// ignore the command to get the actual variable name.
			foreach ( $commands as $command ) {
				$variable = str_replace( $command, '', $variable );
			}
			$variable = str_replace( '.urlencode', '', $variable );
			if ( array_key_exists( $variable, $edgValues ) ) {
				$num_loops = max( $num_loops, count( $edgValues[$variable] ) );
			}
		}

		$text = "";
		for ( $i = 0; $i < $num_loops; $i++ ) {
			$cur_expression = $expression;
			foreach ( $variables as $variable ) {
				// If it ends with one of the pre-defined "commands",
				// ignore the command to get the actual variable name.
				$matches = [];
				preg_match( "/([^.]*)\.?($commandsStr)?$/", $variable, $matches );

				$real_var = $matches[1];
				if ( count( $matches ) == 3 ) {
					$command = $matches[2];
				} else {
					$command = null;
				}

				switch ( $command ) {
					case "htmlencode":
						$value = htmlentities( self::getIndexedValue( $real_var, $i ), ENT_COMPAT | ENT_HTML401 | ENT_SUBSTITUTE, null, false );
						break;
					case "urlencode":
						$value = urlencode( self::getIndexedValue( $real_var, $i ) );
						break;
					default:
						$value = self::getIndexedValue( $real_var, $i );
				}

				$cur_expression = str_replace( '{{{' . $variable . '}}}', $value, $cur_expression );
			}
			$text .= $cur_expression;
		}
		return $text;
	}

	/**
	 * Render the #display_external_table parser function.
	 *
	 * @author Dan Bolser
	 */
	static function doDisplayExternalTable( Parser &$parser ) {
		global $edgValues;

		$params = func_get_args();
		array_shift( $params ); // we already know the $parser ...
		$args = EDUtils::parseParams( $params ); // parse params into name-value pairs

		if ( array_key_exists( 'template', $args ) ) {
			$template = $args['template'];
		} else {
			return EDUtils::formatErrorMessage( "No template specified" );
		}

		if ( array_key_exists( 'data', $args ) ) {
			// parse the 'data' arg into mappings
			$mappings = EDUtils::paramToArray( $args['data'], false, false );
		} else {
			// or just use keys from edgValues
			foreach ( $edgValues as $local_variable => $values ) {
				$mappings[$local_variable] = $local_variable;
			}
		}

		// The string placed in the wikitext between template calls -
		// default is a newline.
		if ( array_key_exists( 'delimiter', $args ) ) {
			$delimiter = str_replace( '\n', "\n", $args['delimiter'] );
		} else {
			$delimiter = "\n";
		}

		$num_loops = 0; // May differ when multiple '#get_'s are used in one page
		foreach ( $mappings as $template_param => $local_variable ) {
			if ( !array_key_exists( $local_variable, $edgValues ) ) {
				// Don't throw an error message - the source may just
				// not publish this variable.
				continue;
			}
			$num_loops = max( $num_loops, count( $edgValues[$local_variable] ) );
		}

		if ( array_key_exists( 'intro template', $args ) && $num_loops > 0 ) {
			$text = '{{' . $args['intro template'] . '}}';
		} else {
			$text = "";
		}
		for ( $i = 0; $i < $num_loops; $i++ ) {
			if ( $i > 0 ) {
				$text .= $delimiter;
			}
			$text .= '{{' . $template;
			foreach ( $mappings as $template_param => $local_variable ) {
				$value = self::getIndexedValue( $local_variable, $i );
				$text .= "|$template_param=$value";
			}
			$text .= "}}";
		}
		if ( array_key_exists( 'outro template', $args ) && $num_loops > 0 ) {
			$text .= '{{' . $args['outro template'] . '}}';
		}

		// This actually 'calls' the template that we built above
		return [ $text, 'noparse' => false ];
	}

	/**
	 * Based on Semantic Internal Objects'
	 * SIOSubobjectHandler::doSetInternal().
	 */
	public static function callSubobject( Parser $parser, $params ) {
		// This is a hack, since SMW's SMWSubobject::render() call is
		// not meant to be called outside of SMW. However, this seemed
		// like the better solution than copying over all of that
		// method's code. Ideally, a true public function can be
		// added to SMW, that handles a subobject creation, that this
		// code can then call.

		$subobjectArgs = [ &$parser ];
		// Blank first argument, so that subobject ID will be
		// an automatically-generated random number.
		$subobjectArgs[1] = '';
		// "main" property, pointing back to the page.
		$mainPageName = $parser->getTitle()->getText();
		$mainPageNamespace = $parser->getTitle()->getNsText();
		if ( $mainPageNamespace != '' ) {
			$mainPageName = $mainPageNamespace . ':' . $mainPageName;
		}
		$subobjectArgs[2] = $params[0] . '=' . $mainPageName;

		foreach ( $params as $i => $value ) {
			if ( $i === 0 ) {
				continue;
			}
			$subobjectArgs[] = $value;
		}

		// SMW 1.9+
		$instance = \SMW\ParserFunctionFactory::newFromParser( $parser )->getSubobjectParser();
		return $instance->parse( new SMW\ParserParameterFormatter( $subobjectArgs ) );
	}

	/**
	 * Render the #store_external_table parser function.
	 */
	static function doStoreExternalTable( Parser &$parser ) {
		global $edgValues;

		// Quick exit if Semantic MediaWiki is not installed.
		if ( !class_exists( '\SMW\ParserFunctionFactory' ) ) {
			return '<div class="error">Error: Semantic MediaWiki must be installed in order to call #store_external_table.</div>';
		}

		$params = func_get_args();
		array_shift( $params ); // we already know the $parser...

		// Get the variables used in this expression, get the number
		// of values for each, and loop through.
		$expression = implode( '|', $params );
		$matches = [];
		preg_match_all( '/{{{([^}]*)}}}/', $expression, $matches );
		$variables = $matches[1];
		$num_loops = 0;
		foreach ( $variables as $variable ) {
			// ignore the presence of '.urlencode' - it's a command,
			// not part of the actual variable name
			$variable = str_replace( '.urlencode', '', $variable );
			if ( array_key_exists( $variable, $edgValues ) ) {
				$num_loops = max( $num_loops, count( $edgValues[$variable] ) );
			}
		}
		$text = "";
		for ( $i = 0; $i < $num_loops; $i++ ) {
			// re-get $params
			$params = func_get_args();
			array_shift( $params );
			foreach ( $params as $j => $param ) {
				foreach ( $variables as $variable ) {
					// If variable name ends with a ".urlencode",
					// that's a command - URL-encode the value of
					// the actual variable.
					if ( strrpos( $variable, '.urlencode' ) === strlen( $variable ) - strlen( '.urlencode' ) ) {
						$real_var = str_replace( '.urlencode', '', $variable );
						$value = urlencode( self::getIndexedValue( $real_var, $i ) );
					} else {
						$value = self::getIndexedValue( $variable, $i );
					}
					$params[$j] = str_replace( '{{{' . $variable . '}}}', $value, $params[$j] );
				}
			}

			self::callSubobject( $parser, $params );
		}
		return null;
	}

	/**
	 * Render the #clear_external_data parser function.
	 */
	static function doClearExternalData( Parser &$parser ) {
		global $edgValues;
		$edgValues = [];
	}
}
