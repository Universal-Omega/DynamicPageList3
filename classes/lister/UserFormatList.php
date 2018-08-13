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
	 * Inline item text separator.
	 *
	 * @var		string
	 */
	protected $textSeparator = '';

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @param	object	\DPL\Parameters
	 * @return	void
	 */
	public function __construct(\DPL\Parameters $parameters) {
		parent::__construct($parameters);
		$this->textSeparator = $parameters->getParameter('inlinetext');
		$listSeparators = $parameters->getParameter('listseparators');
		if (isset($listSeparators[0])) {
			$this->listStart = $listSeparators[0];
		}
		if (isset($listSeparators[1])) {
			$this->itemStart = $listSeparators[1];
		}
		if (isset($listSeparators[2])) {
			$this->itemEnd = $listSeparators[2];
		}
		if (isset($listSeparators[3])) {
			$this->listEnd = $listSeparators[3];
		}
	}

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

		return $list;
	}

	/**
	 * Format a single item.
	 *
	 * @access	public
	 * @param	object	DPL\Article
	 * @param	string	[Optional] Page text to include.
	 * @return	string	Item HTML
	 */
	public function formatItem($article, $pageText = null) {
		$item = '';

		if ($pageText !== null) {
			//Include parsed/processed wiki markup content after each item before the closing tag.
			$item .= $pageText;
		}

		$item = $this->getItemStart().$item.$this->getItemEnd();

		$item = $this->replaceTagParameters($item, $article);

		return $item;
	}

	/**
	 * Return $this->itemStart with attributes replaced.
	 *
	 * @access	public
	 * @return	string	Item Start
	 */
	public function getItemStart() {
		return $this->replaceTagCount($this->itemStart, $this->getRowCount());
	}

	/**
	 * Return $this->itemEnd with attributes replaced.
	 *
	 * @access	public
	 * @return	string	Item End
	 */
	public function getItemEnd() {
		return $this->replaceTagCount($this->itemEnd, $this->getRowCount());
	}

	/**
	 * Join together items after being processed by formatItem().
	 *
	 * @access	public
	 * @param	array	Items as formatted by formatItem().
	 * @return	string	Imploded items.
	 */
	protected function implodeItems($items) {
		return implode($this->textSeparator, $items);
	}
}
?>