<?php

namespace MediaWiki\Extension\DynamicPageList4\Lister;

use function sprintf;

class OrderedList extends UnorderedList {

	protected int $style = parent::LIST_ORDERED;

	protected string $listStart = '<ol%s>';
	protected string $listEnd = '</ol>';

	private int $offsetCount = 0;

	public function formatList( array $articles, int $start, int $count ): string {
		$this->offsetCount = $count;
		return parent::formatList( $articles, $start, $count );
	}

	protected function getListStart(): string {
		// increase start value of ordered lists at multi-column output
		// The offset that comes from the URL parameter is zero based, but has to be +1'ed for display.
		$offset = $this->parameters->getParameter( 'offset' ) + 1;

		if ( $offset !== 0 ) {
			// @TODO: So this adds the total count of articles to the offset.
			// I have not found a case where this does not mess up the displayed count.
			// I am commenting this out for now.
			// $offset += $this->offsetCount;
		}

		return sprintf( $this->listStart, $this->listAttributes . ' start="' . $offset . '"' );
	}
}
