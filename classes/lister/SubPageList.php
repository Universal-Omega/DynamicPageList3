<?php
/**
 * DynamicPageList3
 * DPL SubPageList Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/

namespace DPL\Lister;

class SubPageList extends UnorderedList {
	/**
	 * Listing style for this class.
	 *
	 * @var		constant
	 */
	public $style = parent::LIST_UNORDERED;

	/**
	 * List(Section) Start
	 *
	 * @var		string
	 */
	public $listStart = '<ul%s>';

	/**
	 * List(Section) End
	 *
	 * @var		string
	 */
	public $listEnd = '</ul>';

	/**
	 * Item Start
	 *
	 * @var		string
	 */
	public $itemStart = '<li%s>';

	/**
	 * Item End
	 *
	 * @var		string
	 */
	public $itemEnd = '</li>';

	/**
	 * Format a list of articles into a singular list.
	 *
	 * @access	public
	 * @param	array	List of \DPL\Article
	 * @param	integer	Start position of the array to process.
	 * @param	integer	Total objects from the array to process.
	 * @return	string	Formatted list.
	 */
	public function formatList($articles, $start, $count) {
		$filteredCount = 0;
		$items = [];
		for ($i = $start; $i < $start + $count; $i++) {
			$article = $articles[$i];
			if (empty($article) || empty($article->mTitle)) {
				continue;
			}

			$pageText = null;
			if ($this->includePageText) {
				$pageText = $this->transcludePage($article, $filteredCount);
			} else {
				$filteredCount++;
			}

			$this->rowCount = $filteredCount++;

			$parts = explode('/', $article->mTitle);
			$item = $this->formatItem($article, $pageText);
			$items = $this->nestItem($parts, $items, $item);
		}

		return $this->getListStart() . $this->implodeItems($items) . $this->listEnd;
	}

	/**
	 * Nest items down to the proper level.
	 *
	 * @access	private
	 * @param	array	Part levels to nest down to.
	 * @param	array	Items holder to nest the item into.
	 * @param	string	Formatted Item
	 * @return	array	Nest Items
	 */
	private function nestItem(&$parts, $items, $item) {
		$firstPart = reset($parts);
		if (count($parts) > 1) {
			array_shift($parts);
			if (!isset($items[$firstPart])) {
				$items[$firstPart] = [];
			}
			$items[$firstPart] = $this->nestItem($parts, $items[$firstPart], $item);
			return $items;
		}
		$items[$firstPart][] = $item;

		return $items;
	}

	/**
	 * Join together items after being processed by formatItem().
	 *
	 * @access	protected
	 * @param	array	Items as formatted by formatItem().
	 * @return	string	Imploded items.
	 */
	protected function implodeItems($items) {
		$list = '';
		foreach ($items as $key => $item) {
			if (is_string($item)) {
				$list .= $item;
				continue;
			}
			if (is_array($item)) {
				$list .= $this->getItemStart() . $key . $this->getListStart() . $this->implodeItems($item) . $this->listEnd . $this->getItemEnd();
			}
		}
		return $list;
	}
}
