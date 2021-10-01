<?php

namespace DPL\Heading;

use DPL\Lister\Lister;
use DPL\Parameters;

class TieredHeading extends Heading {
	/**
	 * List(Section) Start
	 *
	 * @var string
	 */
	public $listStart = '<div%s>';

	/**
	 * List(Section) End
	 *
	 * @var string
	 */
	public $listEnd = '</div>';

	/**
	 * Item Start
	 *
	 * @var string
	 */
	public $itemStart = '<h%2$s%1$s>';

	/**
	 * Item End
	 *
	 * @var string
	 */
	public $itemEnd = '</h%2$s>';

	/**
	 * Tier Level
	 *
	 * @var string
	 */
	private $tierLevel = 'eader';

	/**
	 * @param Parameters $parameters
	 */
	public function __construct( Parameters $parameters ) {
		parent::__construct( $parameters );

		$this->tierLevel = substr( $parameters->getParameter( 'headingmode' ), 1 );
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

		$item .= $this->getItemEnd();
		$item .= $lister->formatList( $articles, $headingStart, $headingCount );

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
		return sprintf( $this->itemStart, $this->itemAttributes, $this->tierLevel );
	}

	/**
	 * Return $this->itemEnd with attributes replaced.
	 *
	 * @return string
	 */
	public function getItemEnd() {
		return sprintf( $this->itemEnd, $this->itemAttributes, $this->tierLevel );
	}
}
