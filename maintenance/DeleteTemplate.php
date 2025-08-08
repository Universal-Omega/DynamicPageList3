<?php

namespace MediaWiki\Extension\DynamicPageList4\Maintenance;

use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\User;

class DeleteTemplate extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Delete now-unused template that was previously used for content inclusion.' );
		$this->requireExtension( 'DynamicPageList4' );
	}

	public function execute(): void {
		$services = $this->getServiceContainer();

		$titleFactory = $services->getTitleFactory();
		$wikiPageFactory = $services->getWikiPageFactory();
		$deletePageFactory = $services->getDeletePageFactory();

		$title = $titleFactory->newFromText( 'Extension DPL', NS_TEMPLATE );
		if ( $title === null || !$title->exists() ) {
			$this->output( "Template:Extension DPL does not exist; nothing to delete.\n" );
			return;
		}

		$page = $wikiPageFactory->newFromTitle( $title );
		$user = User::newSystemUser( 'DynamicPageList4 extension', [ 'steal' => true ] );

		$deletePage = $deletePageFactory->newDeletePage( $page, $user );
		$status = $deletePage->deleteUnsafe(
			'Removing obsolete content-inclusion template (no longer required).'
		);

		if ( !$status->isOK() ) {
			$this->fatalError( 'Deletion failed.' );
		}

		$this->output( "Deletion completed.\n" );
	}
}

// @codeCoverageIgnoreStart
return DeleteTemplate::class;
// @codeCoverageIgnoreEnd
