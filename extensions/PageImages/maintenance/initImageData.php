<?php

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once( "$IP/maintenance/Maintenance.php" );

/**
 * @license WTFPL 2.0
 * @author Max Semenik
 */
class InitImageData extends Maintenance {
	const BATCH_SIZE = 100;

	public function __construct() {
		parent::__construct();
		$this->mDescription = 'Initializes PageImages data';
		$this->addOption( 'namespaces', 'Comma-separated list of namespace(s) to refresh', false, true );
		$this->addOption( 'earlier-than', 'Run only on pages earlier than this timestamp', false, true );
	}

	public function execute() {
		global $wgPageImagesNamespaces;

		$id = 0;

		do {
			$tables = array( 'page', 'imagelinks' );
			$conds = array(
				'page_id > ' . (int) $id,
				'il_from IS NOT NULL',
				'page_is_redirect' => 0,
			);
			$fields = array( 'page_id' );
			$joinConds = array( 'imagelinks' => array(
				'LEFT JOIN', 'page_id = il_from',
			) );

			$dbr = wfGetDB( DB_SLAVE );
			if ( $this->hasOption( 'namespaces' ) ) {
				$ns = explode( ',', $this->getOption( 'namespaces' ) );
				$conds['page_namespace'] = $ns;
			} else {
				$conds['page_namespace'] = $wgPageImagesNamespaces;
			}
			if ( $this->hasOption( 'earlier-than' ) ) {
				$conds[] = 'page_touched < '
					. $dbr->addQuotes( $this->getOption( 'earlier-than' ) );
			}
			$res = $dbr->select( $tables, $fields, $conds, __METHOD__,
				array( 'LIMIT' => self::BATCH_SIZE, 'ORDER_BY' => 'page_id', 'GROUP BY' => 'page_id' ),
				$joinConds
			);
			foreach ( $res as $row ) {
				$id = $row->page_id;
				RefreshLinks::fixLinksFromArticle( $id );
				wfWaitForSlaves();
			}
			$this->output( "$id\n" );
		} while ( $res->numRows() );
		$this->output( "done\n" );
	}
}

$maintClass = 'InitImageData';
require_once( DO_MAINTENANCE );
