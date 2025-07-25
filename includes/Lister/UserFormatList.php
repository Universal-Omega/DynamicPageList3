<?php

namespace MediaWiki\Extension\DynamicPageList4\Lister;

use MediaWiki\Extension\DynamicPageList4\Article;
use MediaWiki\Extension\DynamicPageList4\Parameters;
use MediaWiki\Parser\Parser;

class UserFormatList extends Lister {

	protected int $style = parent::LIST_USERFORMAT;
	private string $textSeparator = '';

	protected function __construct( Parameters $parameters, Parser $parser ) {
		parent::__construct( $parameters, $parser );

		$this->textSeparator = $parameters->getParameter( 'inlinetext' );
		$separators = $parameters->getParameter( 'listseparators' );

		[
			$this->listStart,
			$this->itemStart,
			$this->itemEnd,
			$this->listEnd,
		] = array_replace( [ null, null, null, null ], $separators );
	}

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

			$items[] = $this->formatItem( $article, $pageText );
		}

		$this->rowCount = $filteredCount;

		$sortColumn = $this->tableSortColumn;
		if ( $sortColumn !== 0 ) {
			$rowsKey = [];
			foreach ( $items as $index => $item ) {
				$trimmed = trim( $item );
				if ( str_starts_with( $trimmed, '|-' ) ) {
					$parts = explode( '|-', $trimmed, 2 );
					$trimmed = $parts[1] ?? $parts[0];
				}

				if ( $trimmed === '' ) {
					continue;
				}

				$cells = explode( "\n|", $trimmed );
				if ( isset( $cells[0] ) && $cells[0] === '' ) {
					array_shift( $cells );
				}

				$cell = $cells[abs( $sortColumn ) - 1] ?? null;
				if ( $cell !== null ) {
					$value = trim( $cell );
					if ( str_contains( $value, '|' ) ) {
						$value = trim( explode( '|', $value, 2 )[1] ?? $value );
					}
					$rowsKey[$index] = $value;
				}
			}

			$this->sort( $rowsKey, $sortColumn );
			$items = array_map(
				fn ( int $index ): string => $items[$index],
				array_keys( $rowsKey )
			);
		}

		return $this->listStart . $this->implodeItems( $items ) . $this->listEnd;
	}

	/**
	 * Sort the data of a table column in place. Preserves array keys.
	 */
	private function sort( array &$rowsKey, int $sortColumn ): void {
		$reverse = $sortColumn < 0;
		$method = $this->tableSortMethod;

		if ( $reverse ) {
			match ( $method ) {
				'natural' => uasort( $rowsKey,
					static fn ( string $a, string $b ): int => strnatcmp( $b, $a )
				),
				default => arsort( $rowsKey ),
			};
			return;
		}

		match ( $method ) {
			'natural' => natsort( $rowsKey ),
			default => asort( $rowsKey ),
		};
	}

	protected function formatItem( Article $article, ?string $pageText ): string {
		// Include parsed/processed wiki markup content after each item before the closing tag.
		$content = $pageText ?? '';

		$item = $this->getItemStart() . $content . $this->getItemEnd();
		return $this->replaceTagParameters( $item, $article );
	}

	protected function getItemStart(): string {
		return $this->replaceTagCount( $this->itemStart, $this->getRowCount() );
	}

	protected function getItemEnd(): string {
		return $this->replaceTagCount( $this->itemEnd, $this->getRowCount() );
	}

	protected function implodeItems( array $items ): string {
		return implode( $this->textSeparator, $items );
	}
}
