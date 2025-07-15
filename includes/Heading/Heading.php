<?php

namespace MediaWiki\Extension\DynamicPageList4\Heading;

use MediaWiki\Extension\DynamicPageList4\Article;
use MediaWiki\Extension\DynamicPageList4\Lister\Lister;
use MediaWiki\Extension\DynamicPageList4\Parameters;
use MediaWiki\Parser\Sanitizer;

class Heading {
	/**
	 * Listing style for this class.
	 *
	 * @var int|null
	 */
	public $style = null;

	/**
	 * List(Section) Start
	 * Use %s for attribute placement. Example: <div%s>
	 *
	 * @var string
	 */
	public $listStart = '';

	/**
	 * List(Section) End
	 *
	 * @var string
	 */
	public $listEnd = '';

	/**
	 * Item Start
	 * Use %s for attribute placement. Example: <div%s>
	 *
	 * @var string
	 */
	public $itemStart = '';

	/**
	 * Item End
	 *
	 * @var string
	 */
	public $itemEnd = '';

	/**
	 * Extra list HTML attributes.
	 *
	 * @var string
	 */
	public $listAttributes = '';

	/**
	 * Extra item HTML attributes.
	 *
	 * @var string
	 */
	public $itemAttributes = '';

	/**
	 * If the article count per heading should be shown.
	 *
	 * @var bool
	 */
	protected $showHeadingCount = false;

	/**
	 * Parameters
	 *
	 * @var Parameters
	 */
	protected $parameters;

	/**
	 * @param Parameters $parameters
	 */
	public function __construct( Parameters $parameters ) {
		$this->setListAttributes( $parameters->getParameter( 'hlistattr' ) );
		$this->setItemAttributes( $parameters->getParameter( 'hitemattr' ) );
		$this->setShowHeadingCount( $parameters->getParameter( 'headingcount' ) );
		$this->parameters = $parameters;
	}

	/**
	 * Get a new List subclass based on user selection.
	 *
	 * @param string $style
	 * @param Parameters $parameters
	 * @return mixed
	 */
	public static function newFromStyle( $style, Parameters $parameters ) {
		$style = strtolower( $style );

		switch ( $style ) {
			case 'definition':
				$class = DefinitionHeading::class;
				break;
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
			case 'header':
				$class = TieredHeading::class;
				break;
			case 'ordered':
				$class = OrderedHeading::class;
				break;
			case 'unordered':
				$class = UnorderedHeading::class;
				break;
			default:
				return null;
		}

		return new $class( $parameters );
	}

	/**
	 * Get the Parameters object this object was constructed with.
	 *
	 * @return Parameters
	 */
	public function getParameters() {
		return $this->parameters;
	}

	/**
	 * Set extra list attributes.
	 *
	 * @param ?string $attributes
	 */
	public function setListAttributes( $attributes ) {
		$this->listAttributes = Sanitizer::fixTagAttributes( $attributes ?: '', 'ul' );
	}

	/**
	 * Set extra item attributes.
	 *
	 * @param ?string $attributes
	 */
	public function setItemAttributes( $attributes ) {
		$this->itemAttributes = Sanitizer::fixTagAttributes( $attributes ?: '', 'li' );
	}

	/**
	 * Set if the article count per heading should be shown.
	 *
	 * @param bool $show
	 */
	public function setShowHeadingCount( $show = false ) {
		$this->showHeadingCount = (bool)$show;
	}

	/**
	 * Return the list style.
	 *
	 * @return int
	 */
	public function getStyle() {
		return $this->style;
	}

	/**
	 * Format a list of articles into all lists with headings as needed.
	 */
	public function format( array $articles, Lister $lister ): string {
		$parameters = $this->getParameters();
		$columns = (int)( $parameters->getParameter( 'columns' ) ?? 1 );
		$rows = (int)( $parameters->getParameter( 'rows' ) ?? 1 );
		$rowSize = (int)( $parameters->getParameter( 'rowsize' ) ?? 0 );
		$rowColFormat = $parameters->getParameter( 'rowcolformat' ) ?? '';

		$headings = Article::getHeadings();
		$output = '';

		if ( $headings ) {
			if ( $columns !== 1 || $rows !== 1 ) {
				$output .= $this->formatWithColumnsAndRows( $articles, $lister, $headings, $columns, $rows, $rowColFormat );
			} else {
				$output .= $this->formatWithHeadingsOnly( $articles, $lister, $headings );
			}
		} elseif ( $columns !== 1 || $rows !== 1 ) {
			$output .= $this->formatWithoutHeadingsWithColumns( $articles, $lister, $columns, $rows, $rowColFormat );
		} elseif ( $rowSize > 0 ) {
			$output .= $this->formatWithRowSize( $articles, $lister, $rowSize, $rowColFormat );
		} else {
			$output .= $lister->formatList( $articles, 0, count( $articles ) );
		}

		return $output;
	}

	private function formatWithColumnsAndRows( array $articles, Lister $lister, array $headings, int $columns, int $rows, string $rowColFormat ): string {
		$hspace = 2;
		$count = count( $articles ) + $hspace * count( $headings );
		$iGroup = $columns !== 1 ? $columns : $rows;
		$nsize = (int)ceil( $count / max( $iGroup, 1 ) );

		$output = "{|{$rowColFormat}\n|\n" . $this->getListStart();
		$nstart = 0;
		$offset = 0;

		foreach ( $headings as $headingCount ) {
			$headingStart = $nstart - $offset;
			$headingLink = $articles[$headingStart]->mParentHLink ?? '';
			$output .= $this->getItemStart() . $headingLink;

			if ( $this->showHeadingCount ) {
				$output .= $this->articleCountMessage( $headingCount );
			}

			$output .= $this->getItemEnd();
			$nstart += $hspace;
			$offset += $hspace;
			$remaining = $headingCount;

			while ( $remaining > 0 ) {
				$chunk = min( $remaining, $nsize - $hspace );
				$output .= $lister->formatList( $articles, $nstart - $offset, $chunk );
				$nstart += $chunk;
				$remaining -= $chunk;

				if ( $remaining > 0 ) {
					$output .= $columns !== 1 ? "\n|valign=top|\n" : "\n|-\n|\n";
				}
			}
		}

		$output .= $this->listEnd . "\n|}\n";
		return $output;
	}

	private function formatWithHeadingsOnly( array $articles, Lister $lister, array $headings ): string {
		$output = $this->getListStart();
		$headingStart = 0;

		foreach ( $headings as $headingCount ) {
			$headingLink = $articles[$headingStart]->mParentHLink ?? '';
			$output .= $this->formatItem( $headingStart, $headingCount, $headingLink, $articles, $lister );
			$headingStart += $headingCount;
		}

		$output .= $this->listEnd;
		return $output;
	}

	private function formatWithoutHeadingsWithColumns( array $articles, Lister $lister, int $columns, int $rows, string $rowColFormat ): string {
		$count = count( $articles );
		$iGroup = $columns !== 1 ? $columns : $rows;
		$nsize = (int)ceil( $count / max( $iGroup, 1 ) );

		$output = "{|{$rowColFormat}\n|-\n";

		$nstart = 0;

		for ( $g = 0; $g < $iGroup && $nstart < $count; $g++ ) {
			$chunk = min( $nsize, $count - $nstart );

			for ( $i = 0; $i < $chunk; $i++ ) {
				$output .= '| ' . $lister->formatList( [ $articles[ $nstart + $i ] ], 0, 1 ) . "\n";
			}

			$nstart += $chunk;

			if ( $g < $iGroup - 1 ) {
				$output .= "|-\n";
			}
		}

		$output .= "|}\n";

		return $output;
	}

	private function formatWithRowSize( array $articles, Lister $lister, int $rowSize, string $rowColFormat ): string {
		$count = count( $articles );
		$nstart = 0;
		$output = "{|{$rowColFormat}\n|\n";

		while ( $nstart < $count ) {
			$chunk = min( $rowSize, $count - $nstart );
			$output .= $lister->formatList( $articles, $nstart, $chunk );
			$output .= "\n|-\n|\n";
			$nstart += $chunk;
		}

		$output .= "\n|}\n";
		return $output;
	}

	/**
	 * Format a heading group.
	 *
	 * @param int $headingStart
	 * @param int $headingCount
	 * @param string $headingLink
	 * @param array $articles
	 * @param Lister $lister
	 * @return string
	 */
	public function formatItem( $headingStart, $headingCount, $headingLink, $articles, Lister $lister ) {
		$item = '';

		$item .= $this->getItemStart() . $headingLink;

		if ( $this->showHeadingCount ) {
			$item .= $this->articleCountMessage( $headingCount );
		}

		$item .= $lister->formatList( $articles, $headingStart, $headingCount );
		$item .= $this->getItemEnd();

		return $item;
	}

	/**
	 * Return $this->listStart with attributes replaced.
	 *
	 * @return string
	 */
	public function getListStart() {
		return sprintf( $this->listStart, $this->listAttributes );
	}

	/**
	 * Return $this->itemStart with attributes replaced.
	 *
	 * @return string
	 */
	public function getItemStart() {
		return sprintf( $this->itemStart, $this->itemAttributes );
	}

	/**
	 * Return $this->itemEnd with attributes replaced.
	 *
	 * @return string
	 */
	public function getItemEnd() {
		return $this->itemEnd;
	}

	/**
	 * Get the article count message appropriate for this list.
	 *
	 * @param int $count
	 * @return string
	 */
	protected function articleCountMessage( $count ) {
		$orderMethods = $this->getParameters()->getParameter( 'ordermethods' );

		if ( isset( $orderMethods[0] ) && $orderMethods[0] === 'category' ) {
			$message = 'categoryarticlecount';
		} else {
			$message = 'dpl_articlecount';
		}

		return '<p>' . wfMessage( $message, $count )->escaped() . '</p>';
	}
}
