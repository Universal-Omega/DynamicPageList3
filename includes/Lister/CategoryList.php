<?php

namespace MediaWiki\Extension\DynamicPageList4\Lister;

use MediaWiki\Category\CategoryViewer;
use MediaWiki\Extension\DynamicPageList4\Article;

class CategoryList extends Lister {

	protected int $style = parent::LIST_CATEGORY;

	public function formatList( array $articles, int $start, int $count ): string {
		$articleLinks = [];
		$articleStartChars = [];

		$limit = $start + $count;
		for ( $i = $start; $i < $limit; $i++ ) {
			$articleLinks[] = $articles[$i]->mLink;
			$articleStartChars[] = $articles[$i]->mStartChar;
		}

		$this->rowCount = count( $articleLinks );
		if ( $this->rowCount === 0 ) {
			return '';
		}

		$prefix = '__NOTOC____NOEDITSECTION__';
		return $this->rowCount > $this->config->get( 'categoryStyleListCutoff' )
			? $prefix . CategoryViewer::columnList( $articleLinks, $articleStartChars )
			: $prefix . CategoryViewer::shortList( $articleLinks, $articleStartChars );
	}

	/**
	 * @param Article $article @phan-unused-param
	 * @param ?string $pageText @phan-unused-param
	 */
	protected function formatItem( Article $article, ?string $pageText ): string {
		return '';
	}
}
