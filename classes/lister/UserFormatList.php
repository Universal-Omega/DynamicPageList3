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

use DPL\Article;

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
	 * @param	object	MediaWiki \Parser
	 * @return	void
	 */
	public function __construct(\DPL\Parameters $parameters, \Parser $parser) {
		parent::__construct($parameters, $parser);
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

			$this->rowCount = $filteredCount;

			$items[] = $this->formatItem($article, $pageText);
		}

		$this->rowCount = $filteredCount;

		// if requested we sort the table by the contents of a given column
		$sortColumn	= $this->getTableSortColumn();
		if ($sortColumn != 0) {
			$rowsKey	= [];
			foreach ($items as $index => $item) {
				$item = trim($item);
				if (strpos($item, '|-') === 0) {
					$item = explode('|-', $item, 2);
					if (count($item) == 2) {
						$item = $item[1];
					} else {
						$rowsKey[$index] = $item;
						continue;
					}
				}
				if (strlen($item) > 0) {
					$word = explode("\n|", $item);
					if (isset($word[0]) && empty($word[0])) {
						array_shift($word);
					}
					if (isset($word[abs($sortColumn) - 1])) {
						$test = trim($word[abs($sortColumn) - 1]);
						if (strpos($test, '|') > 0) {
							$test = trim(explode('|', $test)[1]);
						}
						$rowsKey[$index] = $test;
					}
				}
			}
			if ($sortColumn < 0) {
				arsort($rowsKey);
			} else {
				asort($rowsKey);
			}
			$newItems = [];
			foreach ($rowsKey as $index => $val) {
				$newItems[] = $items[$index];
			}
			$items = $newItems;
		}

		return $this->listStart . $this->implodeItems($items) . $this->listEnd;
	}

	/**
	 * Format a single item.
	 *
	 * @access	public
	 * @param	object	DPL\Article
	 * @param	string	[Optional] Page text to include.
	 * @return	string	Item HTML
	 */
	public function formatItem(Article $article, $pageText = null) {
		$item = '';

		if ($pageText !== null) {
			//Include parsed/processed wiki markup content after each item before the closing tag.
			$item .= $pageText;
		}

		$item = $this->getItemStart() . $item . $this->getItemEnd();

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
