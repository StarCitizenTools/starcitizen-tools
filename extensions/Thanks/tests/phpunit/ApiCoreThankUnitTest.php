<?php

/**
 * Unit tests for the Thanks API module
 *
 * @group Thanks
 * @group API
 *
 * @author Addshore
 */
class ApiCoreThankUnitTest extends MediaWikiTestCase {

	protected static $moduleName = 'thank';

	protected function getModule() {
		return new ApiCoreThank( new ApiMain(), self::$moduleName );
	}

	/**
	 * @dataProvider provideDieOnBadUser
	 * @covers ApiThank::dieOnBadUser
	 */
	public function testDieOnBadUser( $user, $expectedError ) {
		$module = $this->getModule();
		$method = new ReflectionMethod( $module, 'dieOnBadUser' );
		$method->setAccessible( true );

		if ( $expectedError ) {
			$this->setExpectedException( 'ApiUsageException', $expectedError );
		}

		$method->invoke( $module, $user );
		// perhaps the method should return true.. For now we must do this
		$this->assertTrue( true );
	}

	public function provideDieOnBadUser() {
		$testCases = [];

		$mockUser = $this->getMock( 'User' );
		$mockUser->expects( $this->once() )
			->method( 'isAnon' )
			->will( $this->returnValue( true ) );

		$testCases[ 'anon' ] = [ $mockUser, 'Anonymous users cannot send thanks' ];

		$mockUser = $this->getMock( 'User' );
		$mockUser->expects( $this->once() )
			->method( 'isAnon' )
			->will( $this->returnValue( false ) );
		$mockUser->expects( $this->once() )
			->method( 'pingLimiter' )
			->will( $this->returnValue( true ) );

		$testCases[ 'ping' ] = [
			$mockUser,
			"You've exceeded your rate limit. Please wait some time and try again"
		];

		$mockUser = $this->getMock( 'User' );
		$mockUser->expects( $this->once() )
			->method( 'isAnon' )
			->will( $this->returnValue( false ) );
		$mockUser->expects( $this->once() )
			->method( 'pingLimiter' )
			->will( $this->returnValue( false ) );
		$mockUser->expects( $this->once() )
			->method( 'isBlocked' )
			->will( $this->returnValue( true ) );
		$mockUser->expects( $this->once() )
			->method( 'getBlock' )
			->will( $this->returnValue( new Block( [
				'address' => 'Test user',
				'by' => 1,
				'byText' => 'UTSysop',
				'reason' => __METHOD__,
				'timestamp' => wfTimestamp( TS_MW ),
				'expiry' => 'infinity',
			] ) ) );

		$testCases[ 'blocked' ] = [ $mockUser, 'You have been blocked from editing' ];

		return $testCases;
	}

	// @todo test userAlreadySentThanksForRevision
	// @todo test getRevisionFromParams
	// @todo test getTitleFromRevision
	// @todo test getSourceFromParams
	// @todo test getUserIdFromRevision
	// @todo test markResultSuccess
	// @todo test sendThanks

}
