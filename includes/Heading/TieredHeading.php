<?php

namespace MediaWiki\Extension\DynamicPageList4\Heading;

use MediaWiki\Extension\DynamicPageList4\Lister\Lister;
use MediaWiki\Extension\DynamicPageList4\Parameters;

class TieredHeading extends Heading {

	private string $tierLevel = 'eader';

	protected string $listStart = '<div%s>';
	protected string $listEnd = '</div>';

	protected string $itemStart = '<h%2$s%1$s>';
	protected string $itemEnd = '</h%2$s>';

	public function __construct( Parameters $parameters ) {
		parent::__construct( $parameters );
		$this->tierLevel = substr( $parameters->getParameter( 'headingmode' ), 1 );
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

		$item .= $this->getItemEnd();
		$item .= $lister->formatList( $articles, $headingStart, $headingCount );

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
		return sprintf( $this->itemStart, $this->itemAttributes, $this->tierLevel );
	}

	/**
	 * Return $this->itemEnd with attributes replaced.
	 */
	protected function getItemEnd(): string {
		return sprintf( $this->itemEnd, $this->itemAttributes, $this->tierLevel );
	}
}
