<?php

/**
 * Extract data from cldr XML.
 *
 * @author Niklas Laxström
 * @author Ryan Kaldari
 * @author Santhosh Thottingal
 * @author Sam Reed
 * @copyright Copyright © 2007-2015
 * @license GPL-2.0-or-later
 */
class CLDRParser {
	/**
	 * @param string $inputFile filename
	 * @param string $outputFile filename
	 */
	public function parse( $inputFile, $outputFile ) {
		// Open the input file for reading

		$contents = file_get_contents( $inputFile );
		$doc = new SimpleXMLElement( $contents );

		$data = [
			'languageNames' => [],
			'currencyNames' => [],
			'currencySymbols' => [],
			'countryNames' => [],
			'timeUnits' => [],
		];

		foreach ( $doc->xpath( '//languages/language' ) as $elem ) {
			if ( (string)$elem['alt'] !== '' ) {
				continue;
			}

			if ( (string)$elem['type'] === 'root' ) {
				continue;
			}

			$key = str_replace( '_', '-', strtolower( $elem['type'] ) );

			$data['languageNames'][$key] = (string)$elem;
		}

		foreach ( $doc->xpath( '//currencies/currency' ) as $elem ) {
			if ( (string)$elem->displayName[0] === '' ) {
				continue;
			}

			$data['currencyNames'][(string)$elem['type']] = (string)$elem->displayName[0];
			if ( (string)$elem->symbol[0] !== '' ) {
				$data['currencySymbols'][(string)$elem['type']] = (string)$elem->symbol[0];
			}
		}

		foreach ( $doc->xpath( '//territories/territory' ) as $elem ) {
			if ( (string)$elem['alt'] !== '' && (string)$elem['alt'] !== 'short' ) {
				continue;
			}

			if ( (string)$elem['type'] === 'ZZ' ||
				!preg_match( '/^[A-Z][A-Z]$/', $elem['type'] )
			) {
				continue;
			}

			$data['countryNames'][(string)$elem['type']] = (string)$elem;
		}
		foreach ( $doc->xpath( '//units/unitLength' ) as $unitLength ) {
			if ( (string)$unitLength['type'] !== 'long' ) {
				continue;
			}
			foreach ( $unitLength->unit as $elem ) {
				$type = (string)$elem['type'];
				$pos = strpos( $type, 'duration' );
				if ( $pos === false ) {
					continue;
				}
				$type = substr( $type, strlen( 'duration-' ) );
				foreach ( $elem->unitPattern as $pattern ) {
					$data['timeUnits'][$type . '-' . (string)$pattern['count']] = (string)$pattern;
				}
			}
		}
		foreach ( $doc->xpath( '//fields/field' ) as $field ) {
			$fieldType = (string)$field['type'];

			foreach ( $field->relativeTime as $relative ) {
				$type = (string)$relative['type'];
				foreach ( $relative->relativeTimePattern as $pattern ) {
					$data['timeUnits'][$fieldType . '-' . $type
					. '-' . (string)$pattern['count']] = (string)$pattern;
				}
			}
		}
		ksort( $data['timeUnits'] );

		$this->savephp( $data, $outputFile );
	}

	/**
	 * Parse method for the file structure found in common/supplemental/supplementalData.xml
	 * @param string $inputFile
	 * @param string $outputFile
	 */
	public function parse_supplemental( $inputFile, $outputFile ) {
		// Open the input file for reading

		$contents = file_get_contents( $inputFile );
		$doc = new SimpleXMLElement( $contents );

		$data = [
			'currencyFractions' => [],
			'localeCurrencies' => [],
		];

		// Pull currency attributes - digits, rounding, and cashRounding.
		// This will tell us how many decmal places make sense to use with any currency,
		// or if the currency is totally non-fractional
		foreach ( $doc->xpath( '//currencyData/fractions/info' ) as $elem ) {
			if ( (string)$elem['iso4217'] === '' ) {
				continue;
			}

			$attributes = [ 'digits', 'rounding', 'cashDigits', 'cashRounding' ];
			foreach ( $attributes as $att ) {
				if ( (string)$elem[$att] !== '' ) {
					$data['currencyFractions'][(string)$elem['iso4217']][$att] = (string)$elem[$att];
				}
			}
		}

		// Pull a map of regions to currencies in order of preference.
		foreach ( $doc->xpath( '//currencyData/region' ) as $elem ) {
			if ( (string)$elem['iso3166'] === '' ) {
				continue;
			}

			$region = (string)$elem['iso3166'];

			foreach ( $elem->currency as $currencynode ) {
				if ( (string)$currencynode['to'] === '' && (string)$currencynode['tender'] !== 'false' ) {
					$data['localeCurrencies'][$region][] = (string)$currencynode['iso4217'];
				}
			}
		}

		$this->savephp( $data, $outputFile );
	}

	/**
	 * Parse method for the currency section in the names files.
	 * This is separate from the regular parse function, because we need all of
	 * the currency locale information, even if mediawiki doesn't support the language.
	 * (For instance: en_AU uses '$' for AUD, not USD, but it's not a supported mediawiki locality)
	 * @param string $inputDir - the directory, in which we will parse everything.
	 * @param string $outputFile
	 */
	public function parse_currency_symbols( $inputDir, $outputFile ) {
		if ( !file_exists( $inputDir ) ) {
			return;
		}
		$files = scandir( $inputDir );

		$data = [
			'currencySymbols' => [],
		];

		// Foreach files!
		foreach ( $files as $inputFile ) {
			if ( strpos( $inputFile, '.xml' ) < 1 ) {
				continue;
			}

			$contents = file_get_contents( $inputDir . '/' . $inputFile );
			$doc = new SimpleXMLElement( $contents );

			foreach ( $doc->xpath( '//identity' ) as $elem ) {
				$language = (string)$elem->language['type'];
				if ( $language === '' ) {
					continue;
				}

				$territory = (string)$elem->territory['type'];
				if ( $territory === '' ) {
					$territory = 'DEFAULT';
				}
			}

			foreach ( $doc->xpath( '//currencies/currency' ) as $elem ) {
				if ( (string)$elem->symbol[0] !== '' ) {
					$data['currencySymbols'][(string)$elem['type']][$language][$territory] =
						(string)$elem->symbol[0];
				}
			}
		}

		// now massage the data somewhat. It's pretty blown up at this point.

		/**
		 * Part 1: Stop blowing up on defaults.
		 * Defaults apparently come in many forms. Listed below in order of scope
		 * (widest to narrowest)
		 * 1) The ISO code itself, in the absence of any other defaults
		 * 2) The 'root' language file definition
		 * 3) Language with no locality - locality will come in as 'DEFAULT'
		 *
		 * Intended behavior:
		 * From narrowest scope to widest, collapse the defaults
		 */
		foreach ( $data['currencySymbols'] as $currency => $language ) {
			// get the currency default symbol. This will either be defined in the
			// 'root' language file, or taken from the ISO code.
			$default = $currency;
			if ( array_key_exists( 'root', $language ) ) {
				$default = $language['root']['DEFAULT'];
			}

			foreach ( $language as $lang => $territories ) {
				// Collapse a language (no locality) array if it's just the default. One value will do fine.
				if ( is_array( $territories ) ) {
					if ( count( $territories ) === 1 && array_key_exists( 'DEFAULT', $territories ) ) {
						$data['currencySymbols'][$currency][$lang] = $territories['DEFAULT'];
						if ( $territories['DEFAULT'] === $default && $lang !== 'root' ) {
							unset( $data['currencySymbols'][$currency][$lang] );
						}
					} else {
						ksort( $data['currencySymbols'][$currency][$lang] );
					}
				}
			}

			ksort( $data['currencySymbols'][$currency] );
		}

		ksort( $data['currencySymbols'] );

		$this->savephp( $data, $outputFile );
	}

	/**
	 * savephp will build and return a string containing properly formatted php
	 * output of all the vars we've just parsed out of the xml.
	 * @param array $data The variable names and values we want defined in the php output
	 * @param string $location File location to write
	 */
	protected function savephp( $data, $location ) {
		$hasData = false;
		foreach ( $data as $v ) {
			if ( count( $v ) ) {
				$hasData = true;
				break;
			}
		}

		if ( !$hasData ) {
			return;
		}

		// Yes, I am aware I could have simply used var_export.
		// ...the spacing was ugly.
		$output = "<?php\n";
		foreach ( $data as $varname => $values ) {
			if ( !count( $values ) ) {
				// Don't output empty arrays
				continue;
			}
			$output .= "\n\$$varname = [\n";
			if ( $this->isAssoc( $values ) ) {
				foreach ( $values as $key => $value ) {
					if ( is_array( $value ) ) {
						$output .= $this->makePrettyArrayOuts( $key, $value, 1 );
					} else {
						$key = addcslashes( $key, "'" );
						$value = addcslashes( $value, "'" );
						if ( !is_numeric( $key ) ) {
							$key = "'$key'";
						}
						$output .= "\t$key => '$value',\n";
					}
				}
			} else {
				foreach ( $values as $value ) {
					if ( is_array( $value ) ) {
						$output .= $this->makePrettyArrayOuts( null, $value, 1 );
					} else {
						$value = addcslashes( $value, "'" );
						$output .= "\t'$value',\n";
					}
				}
			}
			$output .= "];\n";
		}

		file_put_contents( $location, $output );
	}

	/**
	 * It makes pretty array vals. Dur.
	 * @param string|null $key Use null to omit outputting the key
	 * @param array $value
	 * @param int $level
	 * @return string
	 */
	protected function makePrettyArrayOuts( $key, $value, $level = 1 ) {
		$subKeys = '';
		$isAssoc = $this->isAssoc( $value );
		$tabs = str_repeat( "\t", $level );

		foreach ( $value as $subkey => $subvalue ) {
			$subkey = $isAssoc ? $subkey : null;

			if ( is_array( $subvalue ) ) {
				$subKeys .= $this->makePrettyArrayOuts( $subkey, $subvalue, $level + 1 );
			} else {
				$subkey = $isAssoc ? $this->formatKey( $subkey ) : '';
				$subvalue = addcslashes( $subvalue, "'" );
				$subKeys .= "$tabs\t$subkey'$subvalue',\n";
			}
		}

		if ( $subKeys === '' ) {
			return '';
		}

		$key = $key !== null ? $this->formatKey( $key ) : '';
		return "$tabs$key" . "[\n$subKeys$tabs],\n";
	}

	/**
	 * It makes pretty array keys. Dur.
	 * @param string $key
	 * @return string
	 */
	protected function formatKey( $key ) {
		$key = addcslashes( $key, "'" );
		if ( !is_numeric( $key ) ) {
			$key = "'$key'";
		}

		return "$key => ";
	}

	/**
	 * Checks if array is associative or sequential.
	 *
	 * @param array $arr
	 * @return bool
	 */
	protected function isAssoc( array $arr ) {
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}
}
