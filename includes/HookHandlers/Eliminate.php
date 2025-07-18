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
			$updatedLinks = [];
			foreach ( $output->getLinkList( ParserOutputLinkTypes::LOCAL ) as [ 'link' => $link, 'pageid' => $pageid ] ) {
				$nsp = $link->getNamespace();
				$dbKey = $link->getDBkey();

				if ( isset( Utils::$createdLinks[0][$nsp][$dbKey] ) ) {
					continue;
				}

				$updatedLinks[] = [ 'link' => $link, 'pageid' => $pageid ];
			}
			$this->setParserOutputProperty( $output, 'mLinks', $this->rebuildLinksArray( $updatedLinks ) );
		}

		if ( isset( Utils::$createdLinks[1] ) ) {
			$updatedTemplates = [];
			foreach ( $output->getLinkList( ParserOutputLinkTypes::TEMPLATE ) as [ 'link' => $link, 'pageid' => $pageid ] ) {
				$nsp = $link->getNamespace();
				$dbKey = $link->getDBkey();

				if ( isset( Utils::$createdLinks[1][$nsp][$dbKey] ) ) {
					continue;
				}

				$updatedTemplates[] = [ 'link' => $link, 'pageid' => $pageid ];
			}
			$this->setParserOutputProperty( $output, 'mTemplates', $this->rebuildLinksArray( $updatedTemplates ) );
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
			$output->setCategories( array_diff_assoc( $categories, Utils::$createdLinks[2] ) );
		}

		if ( isset( Utils::$createdLinks[3] ) ) {
			$images = [];
			foreach ( $output->getLinkList( ParserOutputLinkTypes::MEDIA ) as [ 'link' => $link ] ) {
				$dbKey = $link->getDBkey();

				if ( isset( Utils::$createdLinks[3][$dbKey] ) ) {
					continue;
				}

				$images[ $dbKey ] = 1;
			}
			$this->setParserOutputProperty( $output, 'mImages', $images );
		}
	}

	private function setParserOutputProperty( object $object, string $property, mixed $value ): void {
		$refProp = new ReflectionProperty( $object, $property );
		$refProp->setAccessible( true );
		$refProp->setValue( $object, $value );
	}

	private function rebuildLinksArray( array $linkList ): array {
		$rebuilt = [];
		foreach ( $linkList as [ 'link' => $link, 'pageid' => $pageid ] ) {
			$rebuilt[ $link->getNamespace() ][ $link->getDBkey() ] = $pageid ?? 0;
		}
		return $rebuilt;
	}
}
