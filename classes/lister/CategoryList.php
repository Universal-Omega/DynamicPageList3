<?php
/**
 * DynamicPageList3
 * DPL CategoryList Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/

namespace DPL\Lister;

use DPL\Article;
use DPL\Config;

class CategoryList extends Lister {
	/**
	 * Listing style for this class.
	 *
	 * @var		constant
	 */
	public $style = parent::LIST_CATEGORY;

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
		for ($i = $start; $i < $start + $count; $i++) {
			$articleLinks[] = $articles[$i]->mLink;
			$articleStartChars[] = $articles[$i]->mStartChar;
			$filteredCount = $filteredCount + 1;
		}

		$this->rowCount = $filteredCount;

		if (count($articleLinks) > Config::getSetting('categoryStyleListCutoff')) {
			return "__NOTOC____NOEDITSECTION__" . \CategoryViewer::columnList($articleLinks, $articleStartChars);
		} elseif (count($articleLinks) > 0) {
			// for short lists of articles in categories.
			return "__NOTOC____NOEDITSECTION__" . \CategoryViewer::shortList($articleLinks, $articleStartChars);
		}
		return '';
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
		return '';
	}
}
