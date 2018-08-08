<?php
/**
 * DynamicPageList3
 * DPL GalleryList Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/

namespace DPL\Lister;

class GalleryList extends Lister {
	/**
	 * Listing style for this class.
	 *
	 * @var		constant
	 */
	public $style = parent::LIST_GALLERY;

	/**
	 * List(Section) Start
	 *
	 * @var		string
	 */
	public $listStart = '<gallery>';

	/**
	 * List(Section) End
	 *
	 * @var		string
	 */
	public $listEnd = '</gallery>';
	/**
	 * Item Start
	 *
	 * @var		string
	 */
	public $itemStart = "\n";

	/**
	 * Item End
	 *
	 * @var		string
	 */
	public $itemEnd = "||";

	/**
	 * Format an item.
	 *
	 * @access	public
	 * @param	object	DPL\Article
	 * @param	string	[Optional] Page text to 
	 * @return	string	Item HTML
	 */
	public function formatItem($article, $pageText = null) {
		$item = $this->itemStart;
		$item .= $article->mTitle;

		if ($pageText !== null) {
			var_dump("WAT");
			//Include parsed/processed wiki markup content after each item before the closing tag.
			$item .= $pageText;
		}

		$item .= $this->itemEnd;

		return $item;
	}
}
?>