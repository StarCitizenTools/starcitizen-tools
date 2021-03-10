<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Populate the cu_changes table needed for CheckUser queries with
 * data from recent changes.
 * This is automatically run during first installation within update.php
 * but --force parameter should be set if you want to manually run thereafter.
 */
class PopulateCheckUserTable extends LoggedUpdateMaintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Populate `cu_changes` table with entries from recentchanges' );
		$this->addOption( 'cutoff', 'Cut-off time for rc_timestamp' );
		$this->setBatchSize( 100 );

		$this->requireExtension( 'CheckUser' );
	}

	protected function getUpdateKey() {
		return __CLASS__;
	}

	protected function doDBUpdates() {
		$db = $this->getDB( DB_MASTER );

		// Check if the table is empty
		$rcRows = $db->selectField( 'recentchanges', 'COUNT(*)', false, __METHOD__ );
		if ( !$rcRows ) {
			$this->output( "recentchanges is empty; nothing to add.\n" );
			return true;
		}

		$cutoff = $this->getOption( 'cutoff' );
		if ( $cutoff ) {
			// Something leftover... clear old entries to minimize dupes
			$cutoff = wfTimestamp( TS_MW, $cutoff );
			$encCutoff = $db->addQuotes( $db->timestamp( $cutoff ) );
			$db->delete(
				'cu_changes',
				[ "cuc_timestamp < $encCutoff" ],
				__METHOD__
			);
			$cutoffCond = "AND rc_timestamp < $encCutoff";
		} else {
			$cutoffCond = "";
		}

		$start = $db->selectField( 'recentchanges', 'MIN(rc_id)', false, __METHOD__ );
		$end = $db->selectField( 'recentchanges', 'MAX(rc_id)', false, __METHOD__ );
		// Do remaining chunk
		$end += $this->mBatchSize - 1;
		$blockStart = $start;
		$blockEnd = $start + $this->mBatchSize - 1;

		$this->output(
			"Starting poulation of cu_changes with recentchanges rc_id from $start to $end\n"
		);

		$commentStore = CommentStore::newKey( 'rc_comment' );
		$commentQuery = $commentStore->getJoin();

		while ( $blockStart <= $end ) {
			$this->output( "...migrating rc_id from $blockStart to $blockEnd\n" );
			$cond = "rc_id BETWEEN $blockStart AND $blockEnd $cutoffCond";
			$res = $db->select(
				[ 'recentchanges' ] + $commentQuery['tables'],
				[
					'rc_timestamp',
					'rc_user',
					'rc_user_text',
					'rc_namespace',
					'rc_title',
					'rc_minor',
					'rc_cur_id',
					'rc_this_oldid',
					'rc_last_oldid',
					'rc_type',
					'rc_ip',
				] + $commentQuery['fields'],
				$cond,
				__METHOD__,
				[],
				$commentQuery['joins']
			);
			$batch = [];
			foreach ( $res as $row ) {
				$batch[] = [
					'cuc_timestamp' => $row->rc_timestamp,
					'cuc_user' => $row->rc_user,
					'cuc_user_text' => $row->rc_user_text,
					'cuc_namespace' => $row->rc_namespace,
					'cuc_title' => $row->rc_title,
					'cuc_comment' => $commentStore->getComment( $row )->text,
					'cuc_minor' => $row->rc_minor,
					'cuc_page_id' => $row->rc_cur_id,
					'cuc_this_oldid' => $row->rc_this_oldid,
					'cuc_last_oldid' => $row->rc_last_oldid,
					'cuc_type' => $row->rc_type,
					'cuc_ip' => $row->rc_ip,
					'cuc_ip_hex' => IP::toHex( $row->rc_ip ),
				];
			}
			if ( count( $batch ) ) {
				$db->insert( 'cu_changes', $batch, __METHOD__ );
			}
			$blockStart += $this->mBatchSize - 1;
			$blockEnd += $this->mBatchSize - 1;
			wfWaitForSlaves( 5 );
		}

		$this->output( "...cu_changes table has been populated.\n" );
		return true;
	}
}

$maintClass = 'PopulateCheckUserTable';
require_once RUN_MAINTENANCE_IF_MAIN;
