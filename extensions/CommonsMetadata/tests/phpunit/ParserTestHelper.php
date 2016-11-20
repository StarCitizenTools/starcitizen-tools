<?php

namespace CommonsMetadata;

class ParserTestHelper {
	/**
	 * Maps test names to filenames in the test subdirectory.
	 * This array only exists to have a place where the intentions of test files can be conveniently commented.
	 * Files have been saved from the Commons images of the same name via action=render.
	 * @var array name => filename
	 */
	public static $testHTMLFiles = array(
		// an image with no information template
		'noinfo' => 'File_Pentacle_3.svg',
		// a fairly simple page with a basic information template (with no language markup) and a single CC license
		'simple' => 'File_Sunrise_over_fishing_boats_in_Kerala.jpg',
		// language markup, but some of the description (a WLM reference number) is outside it
		'outside_lang' => 'File_Colonial_Williamsburg_(December,_2011)_-_Christmas_decorations_20.JPG',
		// English description only
		'singlelang' => 'File_Dala_Kyrka.JPG',
		// non-English description only
		'no_english' => 'File_Balkana,_januar_2012_(2).JPG',
		// en/fr/de description
		'multilang' => 'File_Sydney_Tower_Panorama.jpg',
		// complex non-ASCII characters
		'japanese' => 'File_SFC_.gif',
		// an image with multiple licenses (GFDL + 2xCC)
		'multilicense' => 'File_Pentacle_3.svg',
		// license template inside {{information}}
		'embedded_license' => 'File_Thury_Grave_Wiener_Zentralfriedhof.jpg',
		// coordinates
		'coord' => 'File_Sydney_Tower_Panorama.jpg',
		// complex HTML in the author field
		'creator_template' => 'File_Elizabeth_I_George_Gower.jpg',
		// an image with many languages
		'manylang' => 'File_Sikh_pilgrim_at_the_Golden_Temple_(Harmandir_Sahib)_in_Amritsar,_India.jpg',
		// an image with a relatively long description
		'big' => 'File_Askaris_im_Warschauer_Getto_-_1943.jpg',
		// information-like template with a title field
		'title' => 'File_Askaris_im_Warschauer_Getto_-_1943.jpg',
		// Book + Photograph templates
		'book' => 'File_Askaris_im_Warschauer_Getto_-_1943.jpg',
		// Book template alone
		'book2' => 'File_Meyers_b1_s0025.jpg',
		// new format for {{Information}} fields
		'infotpl_class' => 'File_Fourth_Doctor.jpg',
		// {{Artwork}} + {{Photograph}}
		'multiple_infotpl' => 'File_Bust_of_Wilhelmine_of_Bayreuth.jpg',
		// file marked for deletion
		'deletion' => 'File_Kerameikos_October_2012_15.JPG',
		// file with restrictions e.g. trademarked
		'restrict' => 'File_Logo_NIKE.svg',
	);

	/**
	 * @var \PHPUnit_Framework_TestCase
	 */
	protected $testCase;

	/**
	 * @param \PHPUnit_Framework_TestCase $testCase
	 */
	public function setTestCase( $testCase ) {
		$this->testCase = $testCase;
	}

	/**
	 * Loads a test file (usually the saved output of action=render for some image description page).
	 * @param string $name
	 * @throws \InvalidArgumentException
	 * @return string
	 */
	public function getTestHTML( $name ) {
		if ( !isset( self::$testHTMLFiles[$name] ) ) {
			throw new \InvalidArgumentException( 'no HTML test named ' . $name );
		}
		$filename = dirname( __DIR__ ) . '/html/' . self::$testHTMLFiles[$name] . '.html';

		if ( !file_exists( $filename ) ) {
			throw new \InvalidArgumentException( 'no HTML test file named ' . $filename );
		}
		$html = file_get_contents( $filename );
		return $html;
	}

	/**
	 * Loads an expected metadata test result.
	 * @param $name
	 * @throws \InvalidArgumentException
	 * @return array
	 */
	public function getMetadata( $name ) {
		if ( !isset( self::$testHTMLFiles[$name] ) ) {
			throw new \InvalidArgumentException( 'no HTML test named ' . $name );
		}
		$filename = dirname( __DIR__ ) . '/data/' . self::$testHTMLFiles[$name] . '.php';

		if ( !file_exists( $filename ) ) {
			throw new \InvalidArgumentException( 'no metadata file named ' . $filename );
		}
		$metadata = require( $filename );
		return $metadata;
	}

	/**
	 * @param string $description file page text
	 * @param string[] $categories list of category names, without namespace
	 * @return \LocalFile
	 */
	public function getLocalFile( $description, $categories ) {
		$file = $this->testCase->getMockBuilder( 'LocalFile' )
			->setMockClassName( 'LocalFileMock' )
			->disableOriginalConstructor()
			->getMock();
		$file->expects( $this->testCase->any() )
			->method( 'isLocal' )
			->will( $this->testCase->returnValue( true ) );
		$file->expects( $this->testCase->any() )
			->method( 'getDescriptionText' )
			->will( $this->testCase->returnValue( $description ) );
		$file->expects( $this->testCase->any() )
			->method( 'getDescriptionTouched' )
			->will( $this->testCase->returnValue( time() ) );
		$file->mockedCategories = $categories;
		return $file;
	}

	/**
	 * @param string $description file page text
	 * @return \ForeignAPIFile
	 */
	public function getForeignApiFile( $description ) {
		$file = $this->testCase->getMockBuilder( 'ForeignAPIFile' )
			->disableOriginalConstructor()
			->getMock();
		$file->expects( $this->testCase->any() )
			->method( 'isLocal' )
			->will( $this->testCase->returnValue( false ) );
		$file->expects( $this->testCase->any() )
			->method( 'getDescriptionText' )
			->will( $this->testCase->returnValue( $description ) );
		$file->expects( $this->testCase->any() )
			->method( 'getDescriptionTouched' )
			->will( $this->testCase->returnValue( time() ) );
		return $file;
	}

	/**
	 * @param string $description file page text
	 * @param string[] $categories list of category names, without namespace
	 * @return \ForeignDBFile
	 */
	public function getForeignDbFile( $description, $categories ) {
		$file = $this->testCase->getMockBuilder( 'ForeignDbFile' )
			->setMockClassName( 'ForeignDBFileMock' )
			->disableOriginalConstructor()
			->getMock();
		$file->expects( $this->testCase->any() )
			->method( 'isLocal' )
			->will( $this->testCase->returnValue( false ) );
		$file->expects( $this->testCase->any() )
			->method( 'getDescriptionText' )
			->will( $this->testCase->returnValue( $description ) );
		$file->expects( $this->testCase->any() )
			->method( 'getDescriptionTouched' )
			->will( $this->testCase->returnValue( false ) );
		$file->mockedCategories = $categories;
		return $file;
	}

	/**
	 * @param string $languageCode
	 * @return \IContextSource
	 */
	public function getContext( $languageCode ) {
		$language = \Language::factory( $languageCode );
		$context = $this->testCase->getMockBuilder( 'IContextSource' )
			->disableOriginalConstructor()
			->getMock();
		$context->expects( $this->testCase->any() )
			->method( 'getLanguage' )
			->will( $this->testCase->returnValue( $language ) );
		return $context;
	}
}
