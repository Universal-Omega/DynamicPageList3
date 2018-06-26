<?php

namespace DPL;

use LoggedUpdateMaintenance;
use Title;
use WikiPage;
use ContentHandler;

/*
 * Creates the DPL template when updating.
 */
class CreateTemplateUpdateMaintenance extends LoggedUpdateMaintenance {

	protected function doDBUpdates() {
		//Make sure page "Template:Extension DPL" exists
		$title = Title::newFromText('Template:Extension DPL');

		if (!$title->exists()) {
			$page = WikiPage::factory($title);
			$pageContent = ContentHandler::makeContent("<noinclude>This page was automatically created. It serves as an anchor page for all '''[[Special:WhatLinksHere/Template:Extension_DPL|invocations]]''' of [http://mediawiki.org/wiki/Extension:DynamicPageList Extension:DynamicPageList (DPL)].</noinclude>", $title);
			$page->doEditContent(
				$pageContent,
				$title,
				EDIT_NEW | EDIT_FORCE_BOT
			);
		}
	}

	protected function getUpdateKey() {
		return 'dynamic-page-list-create-template';
	}

}
