<?php

namespace MediaWiki\Extension\DynamicPageList4\Heading;

use MediaWiki\Extension\DynamicPageList4\Article;
use MediaWiki\Extension\DynamicPageList4\Lister\Lister;
use MediaWiki\Extension\DynamicPageList4\Parameters;
use MediaWiki\Html\Html;
use MediaWiki\Parser\Sanitizer;

class Heading {

	/**
	 * List(Section) Start
	 * Use %s for attribute placement. Example: <div%s>
	 */
	protected string $listStart = '';

	/** List(Section) End */
	protected string $listEnd = '';

	/**
	 * Item Start
	 * Use %s for attribute placement. Example: <div%s>
	 */
	protected string $itemStart = '';
	protected string $itemEnd = '';

	/** Extra list HTML attributes. */
	protected string $listAttributes = '';

	/** Extra item HTML attributes. */
	protected string $itemAttributes = '';

	/** If the article count per heading should be shown. */
	protected readonly bool $showHeadingCount;

	protected function __construct(
		private readonly Parameters $parameters
	) {
		$this->setListAttributes( $parameters->getParameter( 'hlistattr' ) ?? '' );
		$this->setItemAttributes( $parameters->getParameter( 'hitemattr' ) ?? '' );
		$this->showHeadingCount = $parameters->getParameter( 'headingcount' ) ?? false;
	}

	/**
	 * Get a new Heading subclass based on user selection.
	 */
	public static function newFromStyle( string $style, Parameters $parameters ): ?self {
		$style = strtolower( $style );
		$class = match ( $style ) {
			'definition' => DefinitionHeading::class,
			'ordered' => OrderedHeading::class,
			'unordered' => UnorderedHeading::class,
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header' => TieredHeading::class,
			default => null,
		};

		return $class ? new $class( $parameters ) : null;
	}

	private function setListAttributes( string $attributes ): void {
		$this->listAttributes = Sanitizer::fixTagAttributes( $attributes, 'ul' );
	}

	private function setItemAttributes( string $attributes ): void {
		$this->itemAttributes = Sanitizer::fixTagAttributes( $attributes, 'li' );
	}

	/**
	 * Format a list of articles into all lists with headings as needed.
	 */
	public function format( array $articles, Lister $lister ): string {
		$columns = $this->parameters->getParameter( 'columns' );
		$rows = $this->parameters->getParameter( 'rows' );
		$rowSize = $this->parameters->getParameter( 'rowsize' );
		$rowColFormat = $this->parameters->getParameter( 'rowcolformat' ) ?? '';

		$headings = Article::getHeadings();
		if ( $headings ) {
			if ( $columns !== 1 || $rows !== 1 ) {
				return $this->formatHeadingsMultiColumn(
					$articles, $lister, $columns, $rows,
					$headings, $rowColFormat
				);
			}

			return $this->formatHeadingsSingleColumn( $articles, $lister, $headings );
		}

		if ( $columns !== 1 || $rows !== 1 ) {
			return $this->formatMultiColumnWithoutHeadings( $articles, $lister, $columns, $rows, $rowColFormat );
		}

		if ( $rowSize > 0 ) {
			return $this->formatByRowSize( $articles, $lister, $rowSize, $rowColFormat );
		}

		return $lister->formatList( $articles, 0, count( $articles ) );
	}

	private function formatHeadingsMultiColumn(
		array $articles,
		Lister $lister,
		int $columns,
		int $rows,
		array $headings,
		string $rowColFormat
	): string {
		$hspace = 2;
		$count = count( $articles ) + $hspace * count( $headings );
		$iGroup = $columns !== 1 ? $columns : $rows;
		$nsize = max( (int)ceil( $count / $iGroup ), $hspace + 1 );

		$output = "{|$rowColFormat\n|\n";
		$output .= $this->getListStart();

		$nstart = 0;
		$greml = $nsize;
		$offset = 0;

		foreach ( $headings as $headingCount ) {
			$headingStart = $nstart - $offset;
			$headingLink = $articles[$headingStart]->mParentHLink ?? '';
			$output .= $this->getItemStart() . $headingLink . $this->getItemEnd();

			if ( $this->showHeadingCount ) {
				$output .= $this->articleCountMessage( $headingCount );
			}

			$offset += $hspace;
			$nstart += $hspace;
			$portion = $headingCount;
			$greml -= $hspace;

			$output .= $this->renderArticlesAcrossColumns(
				$articles, $lister, $columns, $count, $nstart,
				$offset, $portion, $nsize, $greml
			);

			$output .= $this->getItemEnd();
		}

		$output .= $this->getItemEnd();
		$output .= "\n|}\n";

		return $output;
	}

	private function renderArticlesAcrossColumns(
		array $articles,
		Lister $lister,
		int $columns,
		int $count,
		int &$nstart,
		int $offset,
		int $portion,
		int &$nsize,
		int $greml
	): string {
		$output = '';

		while ( $portion > 0 ) {
			$greml -= $portion;

			if ( $greml > 0 ) {
				$output .= $lister->formatList( $articles, $nstart - $offset, $portion );
				$nstart += $portion;
				break;
			}

			$output .= $lister->formatList( $articles, $nstart - $offset, $portion + $greml );
			$nstart += ( $portion + $greml );
			$portion = -$greml;

			$output .= $columns !== 1 ? "\n|valign=top|\n" : "\n|-\n|\n";

			if ( $nstart + $nsize > $count ) {
				$nsize = $count - $nstart;
			}

			$greml = $nsize;
			if ( $greml <= 0 ) {
				break;
			}
		}

		return $output;
	}

	private function formatHeadingsSingleColumn( array $articles, Lister $lister, array $headings ): string {
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

	private function formatMultiColumnWithoutHeadings(
		array $articles,
		Lister $lister,
		int $columns,
		int $rows,
		string $rowColFormat
	): string {
		$nstart = 0;
		$count = count( $articles );
		$iGroup = $columns !== 1 ? $columns : $rows;
		$nsize = (int)ceil( $count / $iGroup );

		$output = "{|$rowColFormat\n|\n";

		foreach ( range( 0, $iGroup - 1 ) as $_ ) {
			$output .= $lister->formatList( $articles, $nstart, $nsize );
			$output .= $columns !== 1 ? "\n|valign=top|\n" : "\n|-\n|\n";

			$nstart += $nsize;
			if ( $nstart + $nsize > $count ) {
				$nsize = $count - $nstart;
			}
		}

		$output .= "\n|}\n";
		return $output;
	}

	private function formatByRowSize(
		array $articles,
		Lister $lister,
		int $rowSize,
		string $rowColFormat
	): string {
		$nstart = 0;
		$count = count( $articles );

		$output = "{|$rowColFormat\n|\n";

		do {
			$nsize = min( $rowSize, $count - $nstart );
			$output .= $lister->formatList( $articles, $nstart, $nsize );
			$output .= "\n|-\n|\n";
			$nstart += $nsize;
		} while ( $nstart < $count );

		$output .= "\n|}\n";
		return $output;
	}

	/**
	 * Format a heading group.
	 */
	protected function formatItem(
		int $headingStart,
		int $headingCount,
		string $headingLink,
		array $articles,
		Lister $lister
	): string {
		$item = $this->getItemStart() . $headingLink;
		if ( $this->showHeadingCount ) {
			$item .= $this->articleCountMessage( $headingCount );
		}

		$item .= $lister->formatList( $articles, $headingStart, $headingCount );
		$item .= $this->getItemEnd();

		return $item;
	}

	/**
	 * Return $this->listStart with attributes replaced.
	 */
	protected function getListStart(): string {
		return sprintf( $this->listStart, $this->listAttributes );
	}

	/**
	 * Return $this->itemStart with attributes replaced.
	 */
	protected function getItemStart(): string {
		return sprintf( $this->itemStart, $this->itemAttributes );
	}

	/**
	 * Return $this->itemEnd with attributes replaced.
	 */
	protected function getItemEnd(): string {
		return $this->itemEnd;
	}

	/**
	 * Get the article count message appropriate for this list.
	 */
	protected function articleCountMessage( int $count ): string {
		$orderMethods = $this->parameters->getParameter( 'ordermethod' );
		$message = ( $orderMethods[0] ?? null ) === 'category'
			? 'category-article-count-limited'
			: 'dpl_articlecount';

		return Html::rawElement( 'p', [], wfMessage( $message )->numParams( $count )->parse() );
	}
}
