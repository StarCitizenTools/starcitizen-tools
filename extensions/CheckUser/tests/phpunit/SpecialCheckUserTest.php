<?php

/**
 * Test class for SpecialCheckUser class
 *
 * @group CheckUser
 * @group Database
 *
 * @covers SpecialCheckUser
 */
class SpecialCheckUserTest extends MediaWikiTestCase {

	function __construct( $name = null, array $data = [], $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->tablesUsed = array_merge(
			$this->tablesUsed,
			[
				'page',
				'revision',
				'ip_changes',
				'text',
				'archive',
				'recentchanges',
				'logging',
				'page_props',
				'cu_changes',
			]
		);
	}

	protected function setUp() {
		parent::setUp();

		$this->setMwGlobals( [
			'wgCheckUserCIDRLimit' => [
				'IPv4' => 16,
				'IPv6' => 19,
			]
		] );
	}

	/**
	 * @covers SpecialCheckUser::getIpConds
	 * @dataProvider provideGetIpConds
	 */
	public function testGetIpConds( $target, $expected ) {
		$dbr = wfGetDB( DB_REPLICA );

		$this->assertEquals(
			$expected,
			SpecialCheckUser::getIpConds( $dbr, $target )
		);
	}

	/**
	 * Test cases for SpecialCheckUser::getIpConds
	 * @return array
	 */
	public function provideGetIpConds() {
		return [
			[
				'212.35.31.121',
				[ 'cuc_ip_hex' => 'D4231F79' ],
			],
			[
				'212.35.31.121/32',
				[ 0 => 'cuc_ip_hex BETWEEN \'D4231F79\' AND \'D4231F79\'' ],
			],
			[
				'::e:f:2001',
				[ 'cuc_ip_hex' => 'v6-00000000000000000000000E000F2001' ],
			],
			[
				'::e:f:2001/96',
				[ 0 => 'cuc_ip_hex BETWEEN \'v6-00000000000000000000000E00000000\'' .
					' AND \'v6-00000000000000000000000EFFFFFFFF\'' ],
			],
			[ '0.17.184.5/15', false ],
			[ '2000::/16', false ],
		];
	}
}
