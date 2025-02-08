<?php

namespace MediaWiki\Extension\DynamicPageList3\Maintenance;

$IP ??= getenv( 'MW_INSTALL_PATH' ) ?: dirname( __DIR__, 3 );
require_once "$IP/maintenance/Maintenance.php";

use Exception;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;

class CreateView extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Handle creating DPL3\'s dpl_clview VIEW.' );
		$this->addOption( 'recreate', 'Drop and recreate the view if it already exists', false, false );

		$this->requireExtension( 'DynamicPageList3' );
	}

	/**
	 * Get the unique update key for this logged update.
	 *
	 * @return string
	 */
	protected function getUpdateKey(): string {
		return 'dynamic-page-list-3-create-view';
	}

	/**
	 * Message to show that the update was done already and was just skipped
	 *
	 * @return string
	 */
	protected function updateSkippedMessage(): string {
		return 'VIEW already created.';
	}

	/**
	 * Handle creating DPL3's dpl_clview VIEW.
	 *
	 * @return bool
	 */
	protected function doDBUpdates(): bool {
		$dbw = $this->getDB( DB_PRIMARY );
		$recreate = $this->hasOption( 'recreate' );

		if ( $recreate || !$dbw->tableExists( 'dpl_clview', __METHOD__ ) ) {
			// Drop the view if --recreate option is set
			if ( $recreate ) {
				try {
					$dbw->query( "DROP VIEW IF EXISTS {$dbw->tablePrefix()}dpl_clview", __METHOD__ );
					$this->output( "Dropped existing view dpl_clview.\n" );
				} catch ( Exception $e ) {
					$this->output( "Failed to drop existing view: " . $e->getMessage() . "\n" );
					return false;
				}
			}

			// PostgreSQL doesn't have IFNULL, so use COALESCE instead
			$sqlNullMethod = ( $dbw->getType() === 'postgres' ? 'COALESCE' : 'IFNULL' );

			$query = "CREATE VIEW {$dbw->tablePrefix()}dpl_clview AS SELECT " .
				"$sqlNullMethod(cl_from, page_id) AS cl_from, " .
				"$sqlNullMethod(cl_to, '') AS cl_to, cl_sortkey " .
				"FROM {$dbw->tablePrefix()}page " .
				"LEFT OUTER JOIN {$dbw->tablePrefix()}categorylinks " .
				"ON {$dbw->tablePrefix()}page.page_id = cl_from;";

			// Create the view
			try {
				$dbw->query( $query, __METHOD__ );
				$this->output( "Created view dpl_clview.\n" );
			} catch ( Exception $e ) {
				$this->output( "Failed to create view: " . $e->getMessage() . "\n" );
				return false;
			}
		} else {
			$this->output( "VIEW already exists. Use --recreate to drop and recreate it.\n" );
		}

		return true;
	}
}

$maintClass = CreateView::class;
require_once RUN_MAINTENANCE_IF_MAIN;
