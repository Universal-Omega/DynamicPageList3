<?php

namespace DPL\Lister;

use DPL\Article;

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

		if ( $pageText !== null ) {
			// Include parsed/processed wiki markup content after each item before the closing tag.
			$item .= $pageText;
		}

		$item = $this->getItemStart() . $item . $this->itemEnd;

		$item = $this->replaceTagParameters( $item, $article );

		return $item;
	}
}
