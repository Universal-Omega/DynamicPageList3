<?php

namespace MediaWiki\Extension\DynamicPageList3\Lister;

use ExtensionRegistry;
use MediaWiki\Extension\DynamicPageList3\Article;

class GalleryList extends Lister {
	/**
	 * Listing style for this class.
	 *
	 * @var int
	 */
	public $style = parent::LIST_GALLERY;

	/**
	 * List(Section) Start
	 *
	 * @var string
	 */
	public $listStart = '<gallery%s>';

	/**
	 * List(Section) End
	 *
	 * @var string
	 */
	public $listEnd = '</gallery>';

	/**
	 * Item Start
	 *
	 * @var string
	 */
	public $itemStart = "\n";

	/**
	 * Item End
	 *
	 * @var string
	 */
	public $itemEnd = '|';

	/**
	 * Format an item.
	 *
	 * @param Article $article
	 * @param string|null $pageText
	 * @return string
	 */
	public function formatItem( Article $article, $pageText = null ) {
		$item = $article->mTitle;

		// If PageImages is loaded and we are not in the file namespace, attempt to assemble a gallery of PageImages
		if ( $article->mNamespace !== NS_FILE && ExtensionRegistry::getInstance()->isLoaded( 'PageImages' ) ) {
			$pageImage = $this->getPageImage( $article->mID ) ?: false;

			if ( $pageImage ) {
				// Successfully got a page image, wrapping it
				$item = $this->getItemStart() . $pageImage . '| [[' . $item . ']]' . $this->itemEnd . 'link=' . $item;
			} else {
				// Failed to get a page image
				$item = $this->getItemStart() . $item . $this->itemEnd . '[[' . $item . ']]';
			}
		} else {
			if ( $pageText !== null ) {
				// Include parsed/processed wiki markup content after each item before the closing tag.
				$item .= $pageText;
			}

			$item = $this->getItemStart() . $item . $this->itemEnd;
		}

		$item = $this->replaceTagParameters( $item, $article );

		return $item;
	}
}
