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
	 * Format the list of articles.
	 *
	 * @access	public
	 * @param	array	List of \DPL\Article
	 * @param	integer	Start position of the array to process.
	 * @param	integer	Total objects from the array to process.
	 * @return	string	Formatted list.
	 */
	public function formatList($articles, $start, $count) {
		$list = parent::formatList($articles, $start, $count);

		// if requested we sort the table by the contents of a given column
		if ($this->getTableSortColumn() !== null) {
			$sortColumn	= $this->getTableSortColumn();
			$rows		= explode("\n|-", $list);
			$rowsKey	= [];
			foreach ($rows as $index => $row) {
				if (strlen($row) > 0) {
					if ((($word = explode("\n|", $row, $sortColumn + 2)) !== false) && (count($word) > $sortColumn)) {
						$rowsKey[$index] = $word[$sortColumn];
					} else {
						$rowsKey[$index] = $row;
					}
				}
			}
			if ($sortColumn < 0) {
				arsort($rowsKey);
			} else {
				asort($rowsKey);
			}
			$list = "";
			foreach ($rowsKey as $index => $val) {
				$list .= "\n|-".$rows[$index];
			}
		}
	}

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