<?php

namespace CommonsMetadata;

use Language;
use Title;
use File;
use LocalFile;
use ForeignAPIFile;
use WikiFilePage;
use ScopedCallback;

/**
 * Class to handle metadata collection and formatting, and manage more specific data extraction classes.
 */
class DataCollector {

	/**
	 * Mapping of category names to assesment levels. Array keys are regexps which will be
	 * matched case-insensitively against category names; the first match is returned.
	 * @var array
	 */
	protected static $assessmentCategories = array(
		'poty' => '/^pictures of the year \(.*\)/',
		'potd' => '/^pictures of the day \(.*\)/',
		'featured' => '/^featured (pictures|sounds) on wikimedia commons/',
		'quality' => '/^quality images/',
		'valued' => '/^valued images/',
	);

	/**
	 * Language in which data should be collected. Can be null, which means collect all languages.
	 * @var Language
	 */
	protected $language;

	/**
	 * If true, ignore $language and collect metadata in all languages.
	 * @var bool
	 */
	protected $multiLang;

	/** @var TemplateParser */
	protected $templateParser;

	/** @var  LicenseParser */
	protected $licenseParser;

	/**
	 * @param Language $language
	 */
	public function setLanguage( $language ) {
		$this->language = $language;
	}

	/**
	 * @param boolean $multiLang
	 */
	public function setMultiLang( $multiLang ) {
		$this->multiLang = $multiLang;
	}

	/**
	 * @param TemplateParser $templateParser
	 */
	public function setTemplateParser( TemplateParser $templateParser ) {
		$this->templateParser = $templateParser;
	}

	/**
	 * @param LicenseParser $licenseParser
	 */
	public function setLicenseParser( LicenseParser $licenseParser ) {
		$this->licenseParser = $licenseParser;
	}

	/**
	 * Collects metadata from a file, and adds it to a metadata array. The array has the following format:
	 *
	 * '<metadata field name>' => array(
	 *     'value' => '<value>',
	 *     'source' => '<where did the data come from>',
	 * )
	 *
	 * For fields with multiple values and/or in multiple languages the format is more complex;
	 * see the documentation for the extmetadata API.
	 *
	 * @param array $previousMetadata metadata collected so far; new metadata will be added to this array
	 * @param File $file
	 */
	public function collect( array &$previousMetadata, File $file ) {
		$this->normalizeMetadataTimestamps( $previousMetadata );

		$descriptionText = $this->getDescriptionText( $file, $this->language );

		$categories = $this->getCategories( $file, $previousMetadata );
		$previousMetadata = array_merge( $previousMetadata, $this->getCategoryMetadata( $categories ) );

		$templateData = $this->templateParser->parsePage( $descriptionText );
		$previousMetadata = array_merge( $previousMetadata, $this->getTemplateMetadata( $templateData ) );
	}

	/**
	 * Checks for the presence of metadata needed for attributing the file (author, source, license)
	 * and returns a list of keys corresponding to problems.
	 * @param string $descriptionText HTML code of the file description
	 * @return array one or more of the following keys:
	 *  - no-license - failed to detect a license
	 *  - no-description - failed to detect any image description
	 *  - no-author - failed to detect author name or a custom attribution text
	 *  - no-source - failed to detect the source of the image or a custom attribution text
	 */
	public function verifyAttributionMetadata( $descriptionText ) {
		$templateData = $this->templateParser->parsePage( $descriptionText );
		$problems = $licenseData = $informationData = array();

		if ( isset( $templateData[TemplateParser::LICENSES_KEY] ) ) {
			$licenseData = $this->selectLicense( $templateData[TemplateParser::LICENSES_KEY] );
		}
		if ( isset( $templateData[TemplateParser::INFORMATION_FIELDS_KEY] ) ) {
			$informationData = $this->selectInformationTemplate( $templateData[TemplateParser::INFORMATION_FIELDS_KEY] );
		}

		if ( !isset( $licenseData['LicenseShortName'] ) || $licenseData['LicenseShortName'] === '' ) {
			$problems[] = 'no-license';
		}
		if ( !isset( $informationData['ImageDescription'] ) || $informationData['ImageDescription'] === '' ) {
			$problems[] = 'no-description';
		}
		if (
			( !isset( $informationData['Artist'] ) || $informationData['Artist'] === '' )
			&& ( !isset( $informationData['Attribution'] ) || $informationData['Attribution'] === '' )
		) {
			$problems[] = 'no-author';
		}
		if (
			( !isset( $informationData['Credit'] ) || $informationData['Credit'] === '' )
			&& ( !isset( $informationData['Attribution'] ) || $informationData['Attribution'] === '' )
		) {
			$problems[] = 'no-source';
		}

		return $problems;
	}

	/**
	 * @param array $categories
	 * @return array
	 */
	protected function getCategoryMetadata( array $categories ) {
		$assessments = $this->getAssessmentsAndRemoveFromCategories( $categories );
		$licenses = $this->getLicensesAndRemoveFromCategories( $categories );

		return array(
			'Categories' => array(
				'value' => implode( '|', $categories ),
				'source' => 'commons-categories',
			),
			'Assessments' => array(
				'value' => implode('|', $assessments),
				'source' => 'commons-categories',
			),
		);
	}

	/**
	 * @param array $templateData
	 * @return array
	 */
	protected function getTemplateMetadata( $templateData ) {
		// GetExtendedMetadata does not handle multivalued fields, we need to select one of everything
		$templateFields = array();

		if ( isset( $templateData[TemplateParser::COORDINATES_KEY] ) ) {
			$templateFields = array_merge( $templateFields, $this->selectFirst( $templateData[TemplateParser::COORDINATES_KEY] ) );
		}

		if ( isset( $templateData[TemplateParser::INFORMATION_FIELDS_KEY] ) ) {
			$templateFields = array_merge( $templateFields, $this->selectInformationTemplate( $templateData[TemplateParser::INFORMATION_FIELDS_KEY] ) );
		}

		if ( isset( $templateData[TemplateParser::LICENSES_KEY] ) ) {
			$templateFields = array_merge( $templateFields, $this->selectLicense( $templateData[TemplateParser::LICENSES_KEY] ) );
		}

		if ( isset( $templateData[TemplateParser::DELETION_KEY] ) ) {
			$templateFields = array_merge( $templateFields, $this->selectFirst( $templateData[TemplateParser::DELETION_KEY] ) );
		}

		if ( isset( $templateData[TemplateParser::RESTRICTIONS_KEY] ) ) {
			$templateFields = array_merge( $templateFields, $this->selectFirst( $templateData[TemplateParser::RESTRICTIONS_KEY] ) );
		}

		$metadata = array();
		foreach( $templateFields as $name => $value ) {
			$metadata[ $name ] = array(
				'value' => $value,
				'source' => 'commons-desc-page'
			);
		}

		// use short name to generate internal name used in i18n
		if ( isset( $templateFields['LicenseShortName'] ) ) {
			$licenseData = $this->licenseParser->parseLicenseString( $templateFields['LicenseShortName'] );
			if ( isset( $licenseData['name'] ) ) {
				$metadata['License'] = array(
					'value' => $licenseData['name'],
					'source' => 'commons-templates',
				);
			}
		}

		return $metadata;
	}

	/**
	 * Gets the text of the file's description page.
	 * @param File $file
	 * @param Language $language
	 * @return string
	 */
	protected function getDescriptionText( File $file, Language $language ) {
		# Note: If this is a local file, there is no caching here.
		# However, the results of this module have longer caching for local
		# files to help compensate. For foreign files, this method is cached
		# via parser cache, and possibly a second cache depending on
		# descriptionCacheExpiry (disabled on Wikimedia).

		if ( get_class( $file ) == 'LocalFile' || get_class( $file ) == 'LocalFileMock' ) {
			// LocalFile gets the text in a different way, and ends up with different output
			// (specifically, relative instead of absolute URLs). There is no proper way to
			// influence this process (see the end of Title::getLocalURL for details), so
			// we mess with one of the hooks.
			// The ScopedCallback object will unmess it once this method returns and the object is destructed.

			global $wgHooks;
			$makeAbsolute = function( Title $title, &$url, $query ) {
				global $wgServer, $wgRequest;
				if (
					substr( $url, 0, 1 ) === '/' && substr( $url, 1, 2 ) !== '/' // relative URL
					&& $wgRequest->getVal( 'action' ) != 'render' // for action=render $wgServer will be added in getLocalURL
				) {
					$url = $wgServer . $url;
				}
				return true;
			};
			$wgHooks['GetLocalURL::Internal']['CommonsMetadata::getDescriptionText'] = $makeAbsolute;

			$sc = new ScopedCallback( function() {
				global $wgHooks;
				unset( $wgHooks['GetLocalURL::Internal']['CommonsMetadata::getDescriptionText'] );
			} );
		}
		$text = $file->getDescriptionText( $language );
		return $text;
	}

	/**
	 * @param File $file
	 * @param array $data metadata passed to the onGetExtendedMetadata hook
	 * @return array list of category names in human-readable format
	 */
	protected function getCategories( File $file, array $data ) {
		$categories = array();

		if ( is_a( $file, 'LocalFileMock') || is_a( $file, 'ForeignDBFileMock') ) {
			// with all the hard-coded dependencies, mocking categoriy retrieval properly is
			// pretty much impossible
			return $file->mockedCategories;
		} elseif ( $file instanceof LocalFile ) {
			// for local or shared DB files (which are also LocalFile subclasses)
			// categories can be queried directly from the database

			$page = new WikiFilePage( $file->getOriginalTitle() );
			$page->setFile( $file );

			$categoryTitles = $page->getForeignCategories();

			foreach ( $categoryTitles as $title ) {
				$categories[] = $title->getText();
			}
		} elseif (
			$file instanceof ForeignAPIFile
			&& isset( $data['Categories'] )
		) {
			// getting categories for a ForeignAPIFile is not supported, but in case
			// CommonsMetadata is installed on the remote repository as well, its output
			// (including categories) is sent together with the extended file metadata,
			// when the file is loaded. onGetExtendedMetadata hooks get that metadata
			// when they are invoked.
			$categories = explode( '|', $data['Categories']['value'] );
		} else {
			// out of luck - file is probably from a ForeignAPIRepo with CommonsMetadata not installed there
			wfDebug( 'CommonsMetadata: cannot read category data' );
		}

		return $categories;
	}

	/**
	 * Matches category names to a category => license mapping, removes the matching categories
	 * and returns the corresponding licenses.
	 * @param array $categories a list of human-readable category names.
	 * @return array
	 */
	protected function getLicensesAndRemoveFromCategories( &$categories ) {
		$licenses = array();
		foreach ( $categories as $i => $category ) {
			$licenseData = $this->licenseParser->parseLicenseString( $category );
			if ( $licenseData ) {
				$licenses[] = $licenseData['name'];
				unset( $categories[$i] );
			}
		}
		$categories = array_merge( $categories ); // renumber to avoid holes in array
		return $licenses;
	}

	/**
	 * Matches category names to a category => assessment mapping, removes the matching categories
	 * and returns the corresponding assessments (valued image, picture of the day etc).
	 * @param array $categories a list of human-readable category names.
	 * @return array
	 */
	protected function getAssessmentsAndRemoveFromCategories( &$categories ) {
		$assessments = array();
		foreach ( $categories as $i => $category ) {

			foreach ( self::$assessmentCategories as $assessmentType => $regexp ) {
				if ( preg_match( $regexp . 'i', $category ) ) {
					$assessments[] = $assessmentType;
					unset( $categories[$i] );
				}
			}
		}
		$categories = array_merge( $categories ); // renumber to avoid holes in array
		return array_unique($assessments); // potd/poty can happen multiple times
	}

	/**
	 * Receives a list of metadata arrays and selects the first one to use.
	 * @param array $arrays an array of arrays of metdata fields in fieldname => value form
	 * @return array an array of metadata fields in fieldname => value form
	 */
	protected function selectFirst( $arrays ) {
		// multiple metadata values for the same fields on the same image would not make much sense, so use the first value
		return $arrays ? $arrays[0] : array();
	}

	/**
	 * Receives the list of information templates found by the template parser and selects which one to use.
	 * Also collects all the authors to make sure attribution requirements are honored.
	 * @param array $informationTemplates an array of information templates , each is an array of metdata fields in fieldname => value form
	 * @return array an array of metdata fields in fieldname => value form
	 */
	protected function selectInformationTemplate( array $informationTemplates ) {
		if ( !$informationTemplates ) {
			return array();
		}

		$authorCount = 0;
		foreach ( $informationTemplates as $template ) {
			if ( isset( $template['Artist'] ) ) {
				$authorCount++;
			}
		}

		if ( $authorCount > 1 ) {
			$informationTemplates[0]['AuthorCount'] = $authorCount;
		}
		return $informationTemplates[0];
	}

	/**
	 * Receives the list of licenses found by the template parser and selects which one to use.
	 * @param array $licenses an array of licenses, each is an array of metdata fields in fieldname => value form
	 * @return array an array of metdata fields in fieldname => value form
	 */
	protected function selectLicense( array $licenses ) {
		if ( !$licenses ) {
			return array();
		}

		$sortedLicenses = $this->licenseParser->sortDataByLicensePriority( $licenses, function ( $license ) {
			if ( !isset( $license['LicenseShortName'] ) ) {
				return null;
			}
			return $license['LicenseShortName'];
		} );

		// sortDataByLicensePriority puts things in right order but also rearranges the keys - we don't want that
		$sortedLicenses = array_values( $sortedLicenses );
		return $sortedLicenses ? $sortedLicenses[0] : array();
	}

	/**
	 * Normalizes the metadata to wfTimestamp()'s TS_DB format
	 * @param array $metadata
	 */
	protected function normalizeMetadataTimestamps( array &$metadata ) {
		$fieldsToNormalize = array( 'DateTime', 'DateTimeOriginal' );
		foreach( $fieldsToNormalize as $field ) {
			if ( isset( $metadata[$field] ) && isset( $metadata[$field]['value'] ) ) {
				$parsedTs = wfTimestamp( TS_DB, $metadata[$field]['value'] );
				if ( $parsedTs ) {
					$metadata[$field]['value'] = $parsedTs;
				}
			}
		}
	}
}
