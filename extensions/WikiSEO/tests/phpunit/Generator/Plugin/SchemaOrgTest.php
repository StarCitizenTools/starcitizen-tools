<?php

namespace Octfx\WikiSEO\Tests\Generator\Plugin;

use Octfx\WikiSEO\Generator\Plugins\SchemaOrg;
use Octfx\WikiSEO\Tests\Generator\GeneratorTest;

class SchemaOrgTest extends GeneratorTest {
	/**
	 * @covers \Octfx\WikiSEO\Generator\Plugins\SchemaOrg::init
	 * @covers \Octfx\WikiSEO\Generator\Plugins\SchemaOrg::addMetadata
	 */
	public function testAddMetadata() {
		$metadata = [
			'description' => 'Example Description',
			'type'        => 'website',
		];

		$out = $this->newInstance();

		$generator = new SchemaOrg();
		$generator->init( $metadata, $out );
		$generator->addMetadata();

		$this->assertArrayHasKey( 'jsonld-metadata', $out->getHeadItemsArray() );

		$this->assertContains( '@type', $out->getHeadItemsArray()['jsonld-metadata'] );
	}
}