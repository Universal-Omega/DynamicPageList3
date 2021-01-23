<?php
/**
 * DynamicPageList3
 * CreateTemplateUpdateMaintenance
 *
 * @license GPL-2.0-or-later
 * @package DynamicPageList3
 *
 **/

namespace DPL\DB;

use LoggedUpdateMaintenance;
use Title;
use WikiPage;
use ContentHandler;


$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../../..';
}

require_once "$IP/maintenance/Maintenance.php";

/*
 * Creates the DPL template when updating.
 */
class CreateTemplateUpdateMaintenance extends LoggedUpdateMaintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Handle inserting DPL\'s necessary template for content inclusion.' );
	}

	/**
	 * Get the unique update key for this logged update.
	 *
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'dynamic-page-list-create-template';
	}

	/**
	 * Message to show that the update was done already and was just skipped
	 *
	 * @return string
	 */
	protected function updateSkippedMessage() {
		return 'Template already created.';
	}

	/**
	 * Handle inserting DPL's necessary template for content inclusion.
	 *
	 * @return bool|void
	 */
	protected function doDBUpdates() {
		// Make sure page "Template:Extension DPL" exists
		$title = Title::newFromText('Template:Extension DPL');

		if ( !$title->exists() ) {
			$page = WikiPage::factory( $title );
			$pageContent = ContentHandler::makeContent( "<noinclude>This page was automatically created.  It serves as an anchor page for all '''[[Special:WhatLinksHere/Template:Extension_DPL|invocations]]''' of [https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:DynamicPageList3 Extension:DynamicPageList3].</noinclude>", $title );
			$page->doEditContent(
				$pageContent,
				$title,
				EDIT_NEW | EDIT_FORCE_BOT
			);
		}

		return true;
	}
}

$maintClass = CreateTemplateUpdateMaintenance::class;
require_once RUN_MAINTENANCE_IF_MAIN;
