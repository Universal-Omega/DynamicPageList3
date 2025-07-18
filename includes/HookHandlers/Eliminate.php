<?php

namespace MediaWiki\Extension\DynamicPageList4\HookHandlers;

use MediaWiki\Extension\DynamicPageList4\Utils;
use MediaWiki\Hook\ParserAfterTidyHook;
use MediaWiki\Parser\ParserOutputLinkTypes;
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
			$links = [];
			foreach (
				$output->getLinkList( ParserOutputLinkTypes::LOCAL )
				as [ 'link' => $link, 'pageid' => $pageid ]
			) {
				$nsp = $link->getNamespace();
				$dbKey = $link->getDBkey();

				if ( isset( Utils::$createdLinks[0][$nsp][$dbKey] ) ) {
					continue;
				}

				$links[$nsp][$dbKey] = $pageid ?? 0;
			}
			var_dump( Utils::$createdLinks[0] );
			$this->setParserOutputProperty( $output, 'mLinks', $links );
		}

		if ( isset( Utils::$createdLinks[1] ) ) {
			$templates = [];
			foreach (
				$output->getLinkList( ParserOutputLinkTypes::TEMPLATE ) as
				[ 'link' => $link, 'pageid' => $pageid ]
			) {
				$nsp = $link->getNamespace();
				$dbKey = $link->getDBkey();

				if ( isset( Utils::$createdLinks[1][$nsp][$dbKey] ) ) {
					continue;
				}

				$templates[$nsp][$dbKey] = $pageid ?? 0;
			}
			$this->setParserOutputProperty( $output, 'mTemplates', $templates );
		}

		if ( isset( Utils::$createdLinks[2] ) ) {
			$categories = [];
			foreach ( $output->getCategoryNames() as $name ) {
				$categories[$name] = $output->getCategorySortKey( $name ) ?? '';
			}

			$output->setCategories(
				array_diff_assoc( $categories, Utils::$createdLinks[2] )
			);
		}

		if ( isset( Utils::$createdLinks[3] ) ) {
			$images = [];
			foreach (
				$output->getLinkList( ParserOutputLinkTypes::MEDIA )
				as [ 'link' => $link ]
			) {
				$dbKey = $link->getDBkey();

				if ( isset( Utils::$createdLinks[3][$dbKey] ) ) {
					continue;
				}

				$images[$dbKey] = 1;
			}
			$this->setParserOutputProperty( $output, 'mImages', $images );
		}
	}

	/**
	 * Set private/protected property on an object via reflection.
	 * This is a very messy hack but we have to since ParserOutput
	 * doesn't have any set* method for media or templates and
	 * the properties are private.
	 */
	private function setParserOutputProperty( object $object, string $property, mixed $value ): void {
		$refProp = new ReflectionProperty( $object, $property );
		$refProp->setAccessible( true );
		$refProp->setValue( $object, $value );
	}
}
