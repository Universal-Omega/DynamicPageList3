<?php

namespace MediaWiki\Extension\DynamicPageList4\HookHandlers;

use MediaWiki\Extension\DynamicPageList4\Maintenance\CreateView;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class Installer implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore Tested by updating or installing MediaWiki.
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addPostDatabaseUpdateMaintenance( CreateView::class );
	}
}
