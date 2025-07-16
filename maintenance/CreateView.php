<?php

namespace MediaWiki\Extension\DynamicPageList4\Maintenance;

use Exception;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use Wikimedia\Rdbms\IMaintainableDatabase;

class CreateView extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Create or recreate the dpl_clview VIEW for DPL4.' );
		$this->addOption( 'recreate', 'Drop and recreate the view if it already exists', false, false );

		$this->requireExtension( 'DynamicPageList3' );
	}

	protected function getUpdateKey(): string {
		return 'dynamic-page-list-4-create-view';
	}

	protected function updateSkippedMessage(): string {
		return 'VIEW already created.';
	}

	protected function doDBUpdates(): bool {
		$dbw = $this->getDB( DB_PRIMARY );
		$recreate = $this->hasOption( 'recreate' );

		if ( $recreate || !$dbw->tableExists( 'dpl_clview', __METHOD__ ) ) {
			if ( $recreate ) {
				$this->dropView( $dbw );
			}

			return $this->createView( $dbw );
		}

		$this->output( "VIEW already exists. Use --recreate to force recreation.\n" );
		return true;
	}

	private function dropView( IMaintainableDatabase $dbw ): void {
		try {
			$dbw->query(
				"DROP VIEW IF EXISTS {$dbw->tableName( 'dpl_clview' )}",
				__METHOD__
			);
			$this->output( "Dropped existing view dpl_clview.\n" );
		} catch ( Exception $e ) {
			$errorMessage = $e->getMessage();
			$this->output( "Failed to drop existing view: $errorMessage\n" );
		}
	}

	private function createView( IMaintainableDatabase $dbw ): bool {
		$selectSQL = $dbw->newSelectQueryBuilder()
			->select( [
				'cl_to' => "COALESCE(cl.cl_to, '')",
				'cl_from' => 'COALESCE(cl.cl_from, page.page_id)',
				'cl_sortkey' => 'cl.cl_sortkey',
			] )
			->from( 'page', 'page' )
			->leftJoin( 'categorylinks', 'cl', 'page.page_id = cl.cl_from' )
			->caller( __METHOD__ )
			->getSQL();

		$viewName = $dbw->tableName( 'dpl_clview' );
		$createSQL = "CREATE VIEW $viewName AS $selectSQL";

		try {
			$dbw->query( $createSQL, __METHOD__ );
			$this->output( "Created view dpl_clview.\n" );
			return true;
		} catch ( Exception $e ) {
			$message = $e->getMessage();
			$this->output( "Failed to create view: $errorMessage\n" );
			return false;
		}
	}
}

// @codeCoverageIgnoreStart
return CreateView::class;
// @codeCoverageIgnoreEnd
