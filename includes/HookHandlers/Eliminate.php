<?php

namespace MediaWiki\Extension\DynamicPageList4\HookHandlers;

use MediaWiki\Extension\DynamicPageList4\Utils;
use MediaWiki\Hook\ParserAfterTidyHook;
use ReflectionProperty;

class Eliminate implements ParserAfterTidyHook {

	/**
	 * End eliminate
	 *
	 * @inheritDoc
	 * @param string &$text @phan-unused-param
	 */
	public function onParserAfterTidy( $parser, &$text ) {
		if ( !Utils::$createdLinks ) {
			return;
		}

		$output = $parser->getOutput();

		if ( isset( Utils::$createdLinks[0] ) ) {
			$links = $output->getLinks();
			foreach ( $links as $nsp => $_ ) {
				if ( !isset( Utils::$createdLinks[0][$nsp] ) ) {
					continue;
				}

				$links[$nsp] = array_diff_assoc(
					$links[$nsp],
					Utils::$createdLinks[0][$nsp]
				);

				if ( $links[$nsp] === [] ) {
					unset( $links[$nsp] );
				}
			}

			$this->setParserOutputProperty( $output, 'mLinks', $links );
		}

		if ( isset( Utils::$createdLinks[1] ) ) {
			$templates = $output->getTemplates();
			foreach ( $templates as $nsp => $_ ) {
				if ( !isset( Utils::$createdLinks[1][$nsp] ) ) {
					continue;
				}

				$templates[$nsp] = array_diff_assoc(
					$templates[$nsp],
					Utils::$createdLinks[1][$nsp]
				);

				if ( $templates[$nsp] === [] ) {
					unset( $templates[$nsp] );
				}
			}

			$this->setParserOutputProperty( $output, 'mTemplates', $templates );
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

			$output->setCategories(
				array_diff_assoc( $categories, Utils::$createdLinks[2] )
			);
		}

		if ( isset( Utils::$createdLinks[3] ) ) {
			$images = $output->getImages();
			$images = array_diff_assoc( $images, Utils::$createdLinks[3] );
			$this->setParserOutputProperty( $output, 'mImages', $images );
		}
	}

	private function setParserOutputProperty( object $object, string $property, mixed $value ): void {
		$refProp = new ReflectionProperty( $object, $property );
		$refProp->setAccessible( true );
		$refProp->setValue( $object, $value );
	}
}
