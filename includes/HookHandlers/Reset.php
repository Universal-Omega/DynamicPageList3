<?php

namespace MediaWiki\Extension\DynamicPageList4\HookHandlers;

use MediaWiki\Extension\DynamicPageList4\Utils;
use MediaWiki\Hook\ParserAfterTidyHook;

class Reset implements ParserAfterTidyHook {

	/**
	 * End reset
	 * Reset everything; some categories may have been fixed, however via fixcategory=
	 *
	 * @inheritDoc
	 * @param string &$text @phan-unused-param
	 */
	public function onParserAfterTidy( $parser, &$text ) {
		if ( Utils::$createdLinks['resetdone'] ) {
			return;
		}

		Utils::$createdLinks['resetdone'] = true;
		$output = $parser->getOutput();

		foreach ( $output->getCategoryNames() as $key ) {
			if ( !isset( Utils::$fixedCategories[$key] ) ) {
				continue;
			}

			Utils::$fixedCategories[$key] = $output->getCategorySortKey( $key );
		}

		if ( Utils::$createdLinks['resetLinks'] ) {
			$output->mLinks = [];
		}

		if ( Utils::$createdLinks['resetCategories'] ) {
			$output->setCategories( Utils::$fixedCategories );
		}

		if ( Utils::$createdLinks['resetTemplates'] ) {
			$output->mTemplates = [];
		}

		if ( Utils::$createdLinks['resetImages'] ) {
			$output->mImages = [];
		}

		Utils::$fixedCategories = [];
	}
}
