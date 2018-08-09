<?php
/**
 * DynamicPageList3
 * DPL UserFormatList Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/

namespace DPL\Lister;

class UserFormatList extends Lister {
	/**
	 * Listing style for this class.
	 *
	 * @var		constant
	 */
	public $style = parent::LIST_USERFORMAT;

	/**
	 * Format an item.
	 *
	 * @access	public
	 * @param	object	DPL\Article
	 * @param	string	[Optional] Page text to include.
	 * @return	string	Item HTML
	 */
	public function formatItem($article, $pageText = null) {
		$item = $lister->replaceTagParameters($lister->itemStart, $article, $this->filteredCount);

		$item .= parent::formatItem($article, $pageText);

		$item .= $lister->replaceTagParameters($lister->itemEnd, $article, $this->filteredCount);

		return $item;
	}
}
?>