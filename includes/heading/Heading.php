<?php

namespace DPL\Heading;

use DPL\Article;
use DPL\Lister\Lister;
use DPL\Parameters;
use Sanitizer;

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
	 * @param string $attributes
	 */
	public function setListAttributes( $attributes ) {
		$this->listAttributes = Sanitizer::fixTagAttributes( $attributes, 'ul' );
	}

	/**
	 * Set extra item attributes.
	 *
	 * @param string $attributes
	 */
	public function setItemAttributes( $attributes ) {
		$this->itemAttributes = Sanitizer::fixTagAttributes( $attributes, 'li' );
	}

	/**
	 * Set if the article count per heading should be shown.
	 *
	 * @param bool $show
	 */
	public function setShowHeadingCount( $show = false ) {
		$this->showHeadingCount = boolval( $show );
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
	 *
	 * @param array $articles
	 * @param Lister $lister
	 * @return string
	 */
	public function format( $articles, Lister $lister ) {
		$columns = $this->getParameters()->getParameter( 'columns' );
		$rows = $this->getParameters()->getParameter( 'rows' );
		$rowSize = $this->getParameters()->getParameter( 'rowsize' );
		$rowColFormat = $this->getParameters()->getParameter( 'rowcolformat' );

		$start = 0;
		$count = 0;

		$headings = Article::getHeadings();
		$output = '';

		if ( !empty( $headings ) ) {
			if ( $columns != 1 || $rows != 1 ) {
				$hspace = 2;

				// repeat outer tags for each of the specified columns / rows in the output
				// we assume that a heading roughly takes the space of two articles
				$count = count( $articles ) + $hspace * count( $headings );

				if ( $columns != 1 ) {
					$iGroup = $columns;
				} else {
					$iGroup = $rows;
				}

				$nsize = floor( $count / $iGroup );
				$rest = $count - ( floor( $nsize ) * floor( $iGroup ) );

				if ( $rest > 0 ) {
					$nsize += 1;
				}

				$output .= "{|" . $rowColFormat . "\n|\n";

				if ( $nsize < $hspace + 1 ) {
					$nsize = $hspace + 1;
				}

				$output .= $this->getListStart();
				$nstart = 0;
				$greml = $nsize;
				$g = 0;
				$offset = 0;
				foreach ( $headings as $headingCount ) {
					$headingStart = $nstart - $offset;
					$headingLink = $articles[$headingStart]->mParentHLink;
					$output .= $this->getItemStart() . $headingLink . $this->getItemEnd();

					if ( $this->showHeadingCount ) {
						$output .= $this->articleCountMessage( $headingCount );
					}

					$offset += $hspace;
					$nstart += $hspace;
					$portion = $headingCount;
					$greml -= $hspace;
					$listOutput = '';

					do {
						$greml -= $portion;

						if ( $greml > 0 ) {
							$output .= $lister->formatList( $articles, $nstart - $offset, $portion );
							$nstart += $portion;
							$portion = 0;
							break;
						} else {
							$output .= $lister->formatList( $articles, $nstart - $offset, $portion + $greml );
							$nstart += ( $portion + $greml );
							$portion = ( -$greml );

							if ( $columns != 1 ) {
								$output .= "\n|valign=top|\n";
							} else {
								$output .= "\n|-\n|\n";
							}

							++$g;

							if ( $nstart + $nsize > $count ) {
								$nsize = $count - $nstart;
							}

							$greml = $nsize;

							if ( $greml <= 0 ) {
								break;
							}
						}
					} while ( $portion > 0 );

					$output .= $this->getItemEnd();
				}

				$output .= $this->listEnd;
				$output .= "\n|}\n";
			} else {
				$output .= $this->getListStart();
				$headingStart = 0;

				foreach ( $headings as $headingCount ) {
					$headingLink = $articles[$headingStart]->mParentHLink;
					$output .= $this->formatItem( $headingStart, $headingCount, $headingLink, $articles, $lister );
					$headingStart += $headingCount;
				}

				$output .= $this->listEnd;
			}
		} elseif ( $columns != 1 || $rows != 1 ) {
			// repeat outer tags for each of the specified columns / rows in the output
			$nstart = 0;
			$count = count( $articles );

			if ( $columns != 1 ) {
				$iGroup = $columns;
			} else {
				$iGroup = $rows;
			}

			$nsize = floor( $count / $iGroup );
			$rest = $count - ( floor( $nsize ) * floor( $iGroup ) );

			if ( $rest > 0 ) {
				$nsize += 1;
			}

			$output .= "{|" . $rowColFormat . "\n|\n";

			for ( $g = 0; $g < $iGroup; $g++ ) {
				$output .= $lister->formatList( $articles, $nstart, (int)$nsize );

				if ( $columns != 1 ) {
					$output .= "\n|valign=top|\n";
				} else {
					$output .= "\n|-\n|\n";
				}

				$nstart += $nsize;

				if ( $nstart + $nsize > $count ) {
					$nsize = $count - $nstart;
				}
			}

			$output .= "\n|}\n";
		} elseif ( $rowSize > 0 ) {
			// repeat row header after n lines of output
			$nstart = 0;
			$nsize = $rowSize;
			$count = count( $articles );
			$output .= '{|' . $rowColFormat . "\n|\n";

			do {
				if ( $nstart + $nsize > $count ) {
					$nsize = $count - $nstart;
				}

				$output .= $lister->formatList( $articles, $nstart, (int)$nsize );
				$output .= "\n|-\n|\n";
				$nstart += $nsize;
				if ( $nstart >= $count ) {
					break;
				}
			} while ( true );

			$output .= "\n|}\n";
		} else {
			// Even though the headingmode is not none there were no headings, but still results. Output them anyway.
			$output .= $lister->formatList( $articles, 0, count( $articles ) );
		}

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
