<?php

class EchoNotificationTest extends MediaWikiTestCase {

	public function testNewFromRow() {
		$row = $this->mockNotificationRow() + $this->mockEventRow();

		$notif = EchoNotification::newFromRow( (object)$row );
		$this->assertInstanceOf( 'EchoNotification', $notif );
		// getReadTimestamp() should return null
		$this->assertNull( $notif->getReadTimestamp() );
		$this->assertEquals(
			$notif->getTimestamp(),
			wfTimestamp( TS_MW, $row['notification_timestamp'] )
		);
		$this->assertInstanceOf( 'EchoEvent', $notif->getEvent() );
		$this->assertNull( $notif->getTargetPages() );

		// Provide a read timestamp
		$row['notification_read_timestamp'] = time() + 1000;
		$notif = EchoNotification::newFromRow( (object)$row );
		// getReadTimestamp() should return the timestamp in MW format
		$this->assertEquals(
			$notif->getReadTimestamp(),
			wfTimestamp( TS_MW, $row['notification_read_timestamp'] )
		);

		$notif = EchoNotification::newFromRow( (object)$row, array(
			EchoTargetPage::newFromRow( (object)$this->mockTargetPageRow() )
		) );
		$this->assertGreaterThan( 0, count( $notif->getTargetPages() ) );
		foreach ( $notif->getTargetPages() as $targetPage ) {
			$this->assertInstanceOf( 'EchoTargetPage', $targetPage );
		}
	}

	/**
	 * @expectedException MWException
	 */
	public function testNewFromRowWithException() {
		$row = $this->mockNotificationRow();
		// Provide an invalid event id
		$row['notification_event'] = -1;
		$noitf = EchoNotification::newFromRow( (object)$row );
	}

	/**
	 * Mock a notification row from database
	 */
	protected function mockNotificationRow() {
		return array(
			'notification_user' => 1,
			'notification_event' => 1,
			'notification_timestamp' => time(),
			'notification_read_timestamp' => '',
			'notification_bundle_base' => 1,
			'notification_bundle_hash' => 'testhash',
			'notification_bundle_display_hash' => 'testdisplayhash'
		);
	}

	/**
	 * Mock an event row from database
	 */
	protected function mockEventRow() {
		return array(
			'event_id' => 1,
			'event_type' => 'test_event',
			'event_variant' => '',
			'event_extra' => '',
			'event_page_id' => '',
			'event_agent_id' => '',
			'event_agent_ip' => ''
		);
	}

	/**
	 * Mock a target page row
	 */
	protected function mockTargetPageRow() {
		return array(
			'etp_user' => 1,
			'etp_page' => 2,
			'etp_event' => 1
		);
	}

}
