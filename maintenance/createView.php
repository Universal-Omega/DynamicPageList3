<?php

namespace MediaWiki\Extension\DynamicPageList3\Maintenance;

use LoggedUpdateMaintenance;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class CreateView extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Handle creating DPL3\'s dpl_clview VIEW.' );
	}

	/**
	 * Get the unique update key for this logged update.
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'dynamic-page-list-3-create-view';
	}

	/**
	 * Message to show that the update was done already and was just skipped
	 *
	 * @return string
	 */
	protected function updateSkippedMessage() {
		return 'VIEW already created.';
	}

	/**
	 * Handle creating DPL3's dpl_clview VIEW.
	 *
	 * @return bool
	 */
	protected function doDBUpdates() {
		$dbw = $this->getDB( DB_PRIMARY );
		if ( !$dbw->tableExists( 'dpl_clview' ) ) {
			// PostgreSQL doesn't have IFNULL, so use COALESCE instead
			$sqlNullMethod = ( $dbw->getType() === 'postgres' ? 'COALESCE' : 'IFNULL' );
			$dbw->query( "CREATE VIEW {$dbw->tablePrefix()}dpl_clview AS SELECT $sqlNullMethod(cl_from, page_id) AS cl_from, $sqlNullMethod(cl_to, '') AS cl_to, cl_sortkey FROM {$dbw->tablePrefix()}page LEFT OUTER JOIN {$dbw->tablePrefix()}categorylinks ON {$dbw->tablePrefix()}page.page_id=cl_from;" );
		}

		return true;
	}
}

$maintClass = CreateView::class;
require_once RUN_MAINTENANCE_IF_MAIN;
