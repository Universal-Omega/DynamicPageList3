<?php

namespace MediaWiki\Extension\DynamicPageList4\Lister;

use MediaWiki\Extension\DynamicPageList4\Article;
use MediaWiki\Registration\ExtensionRegistry;
use PageImages\PageImages;

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

	/** @inheritDoc */
	public function formatItem( Article $article, $pageText = null ) {
		$item = $article->mTitle->getPrefixedText();

		// If PageImages is loaded and this is not a file, attempt to assemble a gallery of PageImages
		if ( $article->mNamespace !== NS_FILE && ExtensionRegistry::getInstance()->isLoaded( 'PageImages' ) ) {
			$pageImage = PageImages::getPageImage( $article->mTitle );
			if ( $pageImage && $pageImage->exists() ) {
				$this->listAttributes = 'mode=packed';
				var_dump( $pageImage->getName() );
				// Successfully got a page image, wrapping it.
				$item = $this->getItemStart() . $pageImage->getName() . $this->itemEnd .
					"[[$item]]{$this->itemEnd}link=$item";
			} else {
				// Failed to get a page image.
				$item = $this->getItemStart() . $item . $this->itemEnd . "[[$item]]";
			}

			return $this->replaceTagParameters( $item, $article );
		}

		if ( $pageText !== null ) {
			// Include parsed/processed wiki markup content
			// after each item before the closing tag.
			$item .= $pageText;
		}

		$item = $this->getItemStart() . $item . $this->itemEnd;
		return $this->replaceTagParameters( $item, $article );
	}
}
