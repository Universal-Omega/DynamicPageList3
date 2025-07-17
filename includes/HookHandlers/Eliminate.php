<?php

namespace MediaWiki\Extension\DynamicPageList4\HookHandlers;

use MediaWiki\Extension\DynamicPageList4\Utils;
use MediaWiki\Hook\ParserAfterTidyHook;

class Eliminate implements ParserAfterTidyHook {

	/**
	 * End eliminate
	 *
	 * @inheritDoc
	 * @param string &$text @phan-unused-param
	 */
	public function onParserAfterTidy( $parser, &$text ) {
		// Called during the final output phase; removes links created by DPL
		if ( !Utils::$createdLinks ) {
			return;
		}

		$output = $parser->getOutput();

		if ( isset( Utils::$createdLinks[0] ) ) {
			foreach ( $output->mLinks as $nsp => $_ ) {
				if ( !isset( Utils::$createdLinks[0][$nsp] ) ) {
					continue;
				}

				$output->mLinks[$nsp] = array_diff_assoc(
					$output->mLinks[$nsp],
					Utils::$createdLinks[0][$nsp]
				);

				if ( $output->mLinks[$nsp] === [] ) {
					unset( $output->mLinks[$nsp] );
				}
			}
		}

		if ( isset( Utils::$createdLinks[1] ) ) {
			foreach ( $output->mTemplates as $nsp => $_ ) {
				if ( !isset( Utils::$createdLinks[1][$nsp] ) ) {
					continue;
				}

				$output->mTemplates[$nsp] = array_diff_assoc(
					$output->mTemplates[$nsp],
					Utils::$createdLinks[1][$nsp]
				);

				if ( $output->mTemplates[$nsp] === [] ) {
					unset( $output->mTemplates[$nsp] );
				}
			}
		}

		if ( isset( Utils::$createdLinks[2] ) ) {
			$categories = array_combine(
				$output->getCategoryNames(),
				array_map(
					static fn ( string $name ): string =>
						$output->getCategorySortKey( $name ) ?? '',
					$output->getCategoryNames()
				)
			);

			$output->setCategories( array_diff( $categories, Utils::$createdLinks[2] ) );
		}

		if ( isset( Utils::$createdLinks[3] ) ) {
			$output->mImages = array_diff_assoc( $output->mImages, Utils::$createdLinks[3] );
		}
	}
}
