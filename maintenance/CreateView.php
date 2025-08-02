<?php

namespace MediaWiki\Extension\DynamicPageList4\Maintenance;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use Wikimedia\Rdbms\DBQueryError;
use Wikimedia\Rdbms\IMaintainableDatabase;
use const DB_PRIMARY;

class CreateView extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Create or recreate the necessary VIEW for DPL4.' );
		$this->addOption( 'recreate', 'Drop and recreate the VIEW if it already exists.' );

		$this->requireExtension( 'DynamicPageList4' );
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
			$viewName = $dbw->tableName( 'dpl_clview' );
			$dbw->query( "DROP VIEW IF EXISTS $viewName;", __METHOD__ );
			$this->output( "Dropped existing VIEW $viewName.\n" );
		} catch ( DBQueryError $e ) {
			$this->output( "Failed to drop existing VIEW: {$e->getMessage()}\n" );
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

		try {
			$viewName = $dbw->tableName( 'dpl_clview' );
			$dbw->query( "CREATE VIEW $viewName AS $selectSQL;", __METHOD__ );
			$this->output( "Created VIEW $viewName.\n" );
			return true;
		} catch ( DBQueryError $e ) {
			$this->output( "Failed to create VIEW: {$e->getMessage()}\n" );
			return false;
		}
	}
}

// @codeCoverageIgnoreStart
return CreateView::class;
// @codeCoverageIgnoreEnd
