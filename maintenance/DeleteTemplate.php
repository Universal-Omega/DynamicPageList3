<?php

namespace MediaWiki\Extension\DynamicPageList4\Maintenance;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\User\User;
use function wfMessage;
use const NS_TEMPLATE;

class DeleteTemplate extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Delete now-unused template that was previously used for content inclusion.' );
		$this->requireExtension( 'DynamicPageList4' );
	}

	protected function getUpdateKey(): string {
		return 'dynamic-page-list-4-delete-template';
	}

	protected function updateSkippedMessage(): string {
		return 'Template already deleted.';
	}

	protected function doDBUpdates(): bool {
		$services = $this->getServiceContainer();

		$titleFactory = $services->getTitleFactory();
		$wikiPageFactory = $services->getWikiPageFactory();
		$deletePageFactory = $services->getDeletePageFactory();

		$title = $titleFactory->newFromText( 'Extension DPL', NS_TEMPLATE );
		if ( $title === null || !$title->exists() ) {
			$this->output( "{$title->getPrefixedText()} does not exist; nothing to delete.\n" );
			return true;
		}

		$page = $wikiPageFactory->newFromTitle( $title );
		$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );

		$deletePage = $deletePageFactory->newDeletePage( $page, $user );
		$status = $deletePage
			->forceImmediate( true )
			->deleteUnsafe( wfMessage( 'dpl-template-deleted' )
					->inContentLanguage()->text()
			);

		if ( !$status->isOK() ) {
			$this->error( 'Deletion failed:' );
			$this->error( $status );
			return false;
		}

		$this->output( "Deletion completed.\n" );
		return true;
	}
}

// @codeCoverageIgnoreStart
return DeleteTemplate::class;
// @codeCoverageIgnoreEnd
