<?php

namespace MediaWiki\Extension\DynamicPageList4\Lister;

use function array_shift;
use function count;
use function is_array;
use function is_string;
use function explode;
use function reset;

class SubPageList extends UnorderedList {

	protected int $style = parent::LIST_UNORDERED;

	protected string $listStart = '<ul%s>';
	protected string $listEnd = '</ul>';

	protected string $itemStart = '<li%s>';
	protected string $itemEnd = '</li>';

	public function formatList( array $articles, int $start, int $count ): string {
		$items = [];
		$filteredCount = 0;

		$limit = $start + $count;
		for ( $i = $start; $i < $limit; $i++ ) {
			$article = $articles[$i] ?? null;
			if ( !$article?->mTitle ) {
				continue;
			}

			$pageText = $this->includePageText
				? $this->transcludePage( $article, $filteredCount )
				: null;

			if ( !$this->includePageText ) {
				$filteredCount++;
			}

			$parts = explode( '/', $article->mTitle->getPrefixedText() );
			$item = $this->formatItem( $article, $pageText );
			$items = $this->nestItem( $parts, $items, $item );
		}

		$this->rowCount = $filteredCount;
		return $this->getListStart() . $this->implodeItems( $items ) . $this->listEnd;
	}

	/**
	 * Nest items down to the proper level.
	 */
	private function nestItem( array &$parts, array $items, string $item ): array {
		$firstPart = reset( $parts );
		if ( count( $parts ) > 1 ) {
			array_shift( $parts );
			$items[$firstPart] ??= [];
			$items[$firstPart] = $this->nestItem( $parts, $items[$firstPart], $item );

			return $items;
		}

		$items[$firstPart][] = $item;
		return $items;
	}

	protected function implodeItems( array $items ): string {
		$list = '';
		foreach ( $items as $key => $item ) {
			if ( is_string( $item ) ) {
				$list .= $item;
				continue;
			}

			if ( is_array( $item ) ) {
				$list .= $this->getItemStart()
					. $key
					. $this->getListStart()
					. $this->implodeItems( $item )
					. $this->listEnd
					. $this->getItemEnd();
			}
		}

		return $list;
	}
}
