<?php

namespace Babel\Tests;

use BabelStatic;
use PHPUnit_Framework_TestCase;

/**
 * @covers BabelStatic
 *
 * @group Babel
 *
 * @licence GNU GPL v2+
 * @author Thiemo Mättig
 */
class BabelStaticTest extends PHPUnit_Framework_TestCase {

	public function testOnParserFirstCallInit() {
		$parser = $this->getMockBuilder( 'Parser' )
			->disableOriginalConstructor()
			->getMock();
		$parser->expects( $this->once() )
			->method( 'setFunctionHook' )
			->with( 'babel', [ 'Babel', 'Render' ] )
			->will( $this->returnValue( true ) );

		BabelStatic::onParserFirstCallInit( $parser );
	}

}
