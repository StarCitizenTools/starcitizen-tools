<?php

namespace Flow\Tests\Import;
use Title;
use Flow\Import\ArchiveNameHelper;


/**
 * @group Flow
 */
class ArchiveNameHelperTest extends \MediaWikiTestCase {

	public function decideArchiveTitleProvider() {
		return array(
			array(
				'Selects the first pattern if n=1 does exist',
				// expect
				'Talk:Flow/Archive 1',
				// source title
				Title::newFromText( 'Talk:Flow' ),
				// formats
				array( '%s/Archive %d', '%s/Archive%d' ),
				// existing titles
				array(),
			),

			array(
				'Selects n=2 when n=1 exists',
				// expect
				'Talk:Flow/Archive 2',
				// source title
				Title::newFromText( 'Talk:Flow' ),
				// formats
				array( '%s/Archive %d' ),
				// existing titles
				array( 'Talk:Flow/Archive 1' ),
			),

			array(
				'Selects the second pattern if n=1 exists',
				// expect
				'Talk:Flow/Archive2',
				// source title
				Title::newFromText( 'Talk:Flow' ),
				// formats
				array( '%s/Archive %d', '%s/Archive%d' ),
				// existing titles
				array( 'Talk:Flow/Archive1' ),
			),
		);
	}
	/**
	 * @dataProvider decideArchiveTitleProvider
	 */
	public function testDecideArchiveTitle( $message, $expect, Title $source, array $formats, array $exists ) {
		// flip so we can use isset
		$existsByKey = array_flip( $exists );

		$titleRepo = $this->getMock( 'Flow\Repository\TitleRepository' );
		$titleRepo->expects( $this->any() )
			->method( 'exists' )
			->will( $this->returnCallback( function( Title $title ) use ( $existsByKey ) {
				return isset( $existsByKey[$title->getPrefixedText()] );
			} ) );

		$archiveNameHelper = new ArchiveNameHelper();
		$result = $archiveNameHelper->decideArchiveTitle( $source, $formats, $titleRepo );
		$this->assertEquals( $expect, $result, $message );
	}

	public function findLatestArchiveTitleProvider() {
		return array(
			array(
				'Returns false if no archive exist',
				// expect
				false,
				// source title
				Title::newFromText( 'Talk:Flow' ),
				// formats
				array( '%s/Archive %d', '%s/Archive%d' ),
				// existing titles
				array(),
			),

			array(
				'Selects n=2 when n=2 exists',
				// expect
				'Talk:Flow/Archive 2',
				// source title
				Title::newFromText( 'Talk:Flow' ),
				// formats
				array( '%s/Archive %d' ),
				// existing titles
				array( 'Talk:Flow/Archive 1', 'Talk:Flow/Archive 2' ),
			),

		);
	}
	/**
	 * @dataProvider findLatestArchiveTitleProvider
	 */
	public function testFindLatestArchiveTitle( $message, $expect, Title $source, array $formats, array $exists ) {
		// flip so we can use isset
		$existsByKey = array_flip( $exists );

		$titleRepo = $this->getMock( 'Flow\Repository\TitleRepository' );
		$titleRepo->expects( $this->any() )
			->method( 'exists' )
			->will( $this->returnCallback( function( Title $title ) use ( $existsByKey ) {
				return isset( $existsByKey[$title->getPrefixedText()] );
			} ) );

		$archiveNameHelper = new ArchiveNameHelper();
		$result = $archiveNameHelper->findLatestArchiveTitle( $source, $formats, $titleRepo );
		$this->assertEquals( $expect, $result, $message );
	}

}
