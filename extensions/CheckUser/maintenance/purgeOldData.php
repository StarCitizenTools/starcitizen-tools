<?php
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class PurgeOldData extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Purge expired rows in CheckUser and RecentChanges";
		$this->setBatchSize( 200 );

		$this->requireExtension( 'CheckUser' );
	}

	public function execute() {
		global $wgCUDMaxAge, $wgRCMaxAge, $wgPutIPinRC;

		$this->output( "Purging data from cu_changes..." );
		$count = $this->prune( 'cu_changes', 'cuc_timestamp', $wgCUDMaxAge );
		$this->output( $count . " rows.\n" );

		if ( $wgPutIPinRC ) {
			$this->output( "Purging data from recentchanges..." );
			$count = $this->prune( 'recentchanges', 'rc_timestamp', $wgRCMaxAge );
			$this->output( $count . " rows.\n" );
		}

		$this->output( "Done.\n" );
	}

	protected function prune( $table, $ts_column, $maxAge ) {
		$dbw = wfGetDB( DB_MASTER );

		$expiredCond = "$ts_column < " . $dbw->addQuotes( $dbw->timestamp( time() - $maxAge ) );

		$count = 0;
		while ( true ) {
			// Get the first $this->mBatchSize (or less) items
			$res = $dbw->select( $table, $ts_column,
				$expiredCond,
				__METHOD__,
				[ 'ORDER BY' => "$ts_column ASC", 'LIMIT' => $this->mBatchSize ]
			);
			if ( !$res->numRows() ) {
				break; // all cleared
			}
			// Record the start and end timestamp for the set
			$blockStart = $dbw->addQuotes( $res->fetchRow()[$ts_column] );
			$res->seek( $res->numRows() - 1 );
			$blockEnd = $dbw->addQuotes( $res->fetchRow()[$ts_column] );
			$res->free();

			// Do the actual delete...
			$this->beginTransaction( $dbw, __METHOD__ );
			$dbw->delete( $table,
				[ "$ts_column BETWEEN $blockStart AND $blockEnd" ], __METHOD__ );
			$count += $dbw->affectedRows();
			$this->commitTransaction( $dbw, __METHOD__ );

			wfWaitForSlaves();
		}

		return $count;
	}
}

$maintClass = "PurgeOldData";
require_once RUN_MAINTENANCE_IF_MAIN;
