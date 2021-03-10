<?php

namespace Babel\Tests;

use BabelStatic;

/**
 * @covers BabelStatic
 *
 * @group Babel
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class BabelStaticTest extends \PHPUnit\Framework\TestCase {

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
