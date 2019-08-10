<?php

namespace Octfx\WikiSEO\Tests;

use MediaWikiTestCase;
use Octfx\WikiSEO\Validator;

class ValidatorTest extends MediaWikiTestCase {
	/**
	 * @var Validator
	 */
	private $validator;

	protected function setUp() {
		parent::setUp();
		$this->validator = new Validator();
	}

	protected function tearDown() {
		unset( $this->validator );
		parent::tearDown();
	}

	/**
	 * @covers \Octfx\WikiSEO\Validator::validateParams
	 */
	public function testValidateParamsAllValid() {
		$params = [
			'title'       => '',
			'keywords'    => '',
			'description' => '',
			'locale'      => '',
		];

		$validatedArray = $this->validator->validateParams( $params );

		$this->assertCount( 4, $validatedArray );
		$this->assertArrayEquals( $params, $validatedArray );
	}

	/**
	 * @covers \Octfx\WikiSEO\Validator::validateParams
	 */
	public function testValidateParamsAllInvalid() {
		$params = [
			'no'      => '',
			'valid'   => '',
			'params'  => '',
			'in_here' => '',
		];

		$validatedArray = $this->validator->validateParams( $params );

		$this->assertCount( 0, $validatedArray );
		$this->assertEmpty( $validatedArray );
	}

	/**
	 * @covers \Octfx\WikiSEO\Validator::validateParams
	 */
	public function testMixedParams() {
		$valid = [
			'title'       => '',
			'keywords'    => '',
			'description' => '',
			'locale'      => '',
		];

		$invalid = [
			'no'      => '',
			'valid'   => '',
			'params'  => '',
			'in_here' => '',
		];

		$params = array_merge( $valid, $invalid );

		$validatedArray = $this->validator->validateParams( $params );

		$this->assertCount( 4, $validatedArray );
		$this->assertArrayEquals( $valid, $validatedArray );
	}
}