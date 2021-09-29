<?php

namespace DPL\Lister;

use DPL\Article;
use DPL\Parameters;
use Parser;

class UserFormatList extends Lister {
	/**
	 * Listing style for this class.
	 *
	 * @var int
	 */
	public $style = parent::LIST_USERFORMAT;

	/**
	 * Inline item text separator.
	 *
	 * @var string
	 */
	protected $textSeparator = '';

	/**
	 * @param Parameters $parameters
	 * @param Parser $parser
	 */
	public function __construct( Parameters $parameters, Parser $parser ) {
		parent::__construct( $parameters, $parser );

		$this->textSeparator = $parameters->getParameter( 'inlinetext' );
		$listSeparators = $parameters->getParameter( 'listseparators' );

		if ( isset( $listSeparators[0] ) ) {
			$this->listStart = $listSeparators[0];
		}

		if ( isset( $listSeparators[1] ) ) {
			$this->itemStart = $listSeparators[1];
		}

		if ( isset( $listSeparators[2] ) ) {
			$this->itemEnd = $listSeparators[2];
		}

		if ( isset( $listSeparators[3] ) ) {
			$this->listEnd = $listSeparators[3];
		}
	}

	/**
	 * Format the list of articles.
	 *
	 * @param array $articles
	 * @param int $start
	 * @param int $count
	 * @return string
	 */
	public function formatList( $articles, $start, $count ) {
		$filteredCount = 0;
		$items = [];

		for ( $i = $start; $i < $start + $count; $i++ ) {
			$article = $articles[$i];

			if ( empty( $article ) || empty( $article->mTitle ) ) {
				continue;
			}

			$pageText = null;
			if ( $this->includePageText ) {
				$pageText = $this->transcludePage( $article, $filteredCount );
			} else {
				$filteredCount++;
			}

			$this->rowCount = $filteredCount;

			$items[] = $this->formatItem( $article, $pageText );
		}

		$this->rowCount = $filteredCount;

		// if requested we sort the table by the contents of a given column
		$sortColumn = $this->getTableSortColumn();
		if ( $sortColumn != 0 ) {
			$rowsKey = [];

			foreach ( $items as $index => $item ) {
				$item = trim( $item );

				if ( strpos( $item, '|-' ) === 0 ) {
					$item = explode( '|-', $item, 2 );

					if ( count( $item ) == 2 ) {
						$item = $item[1];
					} else {
						$rowsKey[$index] = $item;
						continue;
					}
				}

				if ( strlen( $item ) > 0 ) {
					$word = explode( "\n|", $item );

					if ( isset( $word[0] ) && empty( $word[0] ) ) {
						array_shift( $word );
					}

					if ( isset( $word[abs( $sortColumn ) - 1] ) ) {
						$test = trim( $word[abs( $sortColumn ) - 1] );

						if ( strpos( $test, '|' ) > 0 ) {
							$test = trim( explode( '|', $test )[1] );
						}

						$rowsKey[$index] = $test;
					}
				}
			}

			$this->sort( $rowsKey, $sortColumn );
			$newItems = [];

			foreach ( $rowsKey as $index => $val ) {
				$newItems[] = $items[$index];
			}

			$items = $newItems;
		}

		return $this->listStart . $this->implodeItems( $items ) . $this->listEnd;
	}

	/**
	 * Sort the data of a table column in place. Preserves array keys.
	 *
	 * @param array	&$rowsKey
	 * @param int $sortColumn
	 */
	protected function sort( &$rowsKey, $sortColumn ) {
		$sortMethod = $this->getTableSortMethod();

		if ( $sortColumn < 0 ) {
			switch ( $sortMethod ) {
				case 'natural':
					// Reverse natsort()
					uasort( $rowsKey, static function ( $first, $second ) {
						return strnatcmp( $second, $first );
					} );
					break;
				case 'standard':
				default:
					arsort( $rowsKey );
					break;
			}
		} else {
			switch ( $sortMethod ) {
				case 'natural':
					natsort( $rowsKey );
					break;
				case 'standard':
				default:
					asort( $rowsKey );
					break;
			}
		}
	}

	/**
	 * Format a single item.
	 *
	 * @param Article $article
	 * @param string|null $pageText
	 * @return string
	 */
	public function formatItem( Article $article, $pageText = null ) {
		$item = '';

		if ( $pageText !== null ) {
			// Include parsed/processed wiki markup content after each item before the closing tag.
			$item .= $pageText;
		}

		$item = $this->getItemStart() . $item . $this->getItemEnd();

		$item = $this->replaceTagParameters( $item, $article );

		return $item;
	}

	/**
	 * Return $this->itemStart with attributes replaced.
	 *
	 * @return string
	 */
	public function getItemStart() {
		return $this->replaceTagCount( $this->itemStart, $this->getRowCount() );
	}

	/**
	 * Return $this->itemEnd with attributes replaced.
	 *
	 * @return string
	 */
	public function getItemEnd() {
		return $this->replaceTagCount( $this->itemEnd, $this->getRowCount() );
	}

	/**
	 * Join together items after being processed by formatItem().
	 *
	 * @param array $items
	 * @return string
	 */
	protected function implodeItems( $items ) {
		return implode( $this->textSeparator, $items );
	}
}
