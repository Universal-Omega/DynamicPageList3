<?php

namespace DPL\Lister;

use CategoryViewer;
use DPL\Article;
use DPL\Config;

class CategoryList extends Lister {
	/**
	 * Listing style for this class.
	 *
	 * @var int
	 */
	public $style = parent::LIST_CATEGORY;

	/**
	 * Format the list of articles.
	 *
	 * @param array $articles
	 * @param int $start
	 * @param int $count
	 * @return string Formatted list.
	 */
	public function formatList( $articles, $start, $count ) {
		$articleLinks = [];
		$articleStartChars = [];

		$filteredCount = 0;

		for ( $i = $start; $i < $start + $count; $i++ ) {
			$articleLinks[] = $articles[$i]->mLink;
			$articleStartChars[] = $articles[$i]->mStartChar;

			$filteredCount++;
		}

		$this->rowCount = $filteredCount;

		if ( count( $articleLinks ) > Config::getSetting( 'categoryStyleListCutoff' ) ) {
			return "__NOTOC____NOEDITSECTION__" . CategoryViewer::columnList( $articleLinks, $articleStartChars );
		} elseif ( count( $articleLinks ) > 0 ) {
			// for short lists of articles in categories.
			return "__NOTOC____NOEDITSECTION__" . CategoryViewer::shortList( $articleLinks, $articleStartChars );
		}

		return '';
	}

	/**
	 * Format a single item.
	 *
	 * @param Article $article
	 * @param string|null $pageText
	 * @return string
	 */
	public function formatItem( Article $article, $pageText = null ) {
		return '';
	}
}
