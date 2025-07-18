<?php

namespace MediaWiki\Extension\DynamicPageList4\Heading;

use MediaWiki\Extension\DynamicPageList4\Lister\Lister;

class DefinitionHeading extends Heading {

	private const HEAD_START_TAG = '<dt>';
	private const HEAD_END_TAG = '</dt>';

	protected string $listStart = '<dl%s>';
	protected string $listEnd = '</dl>';
	protected string $itemStart = '<dd%s>';
	protected string $itemEnd = '</dd>';

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
		$item = self::HEAD_START_TAG . $headingLink;
		if ( $this->showHeadingCount ) {
			$item .= $this->articleCountMessage( $headingCount );
		}

		$item .= self::HEAD_END_TAG;
		$item .= $this->getItemStart();
		$item .= $lister->formatList( $articles, $headingStart, $headingCount );
		$item .= $this->getItemEnd();

		return $item;
	}
}
