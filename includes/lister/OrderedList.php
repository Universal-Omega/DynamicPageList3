<?php

namespace DPL\Lister;

class OrderedList extends UnorderedList {
	/**
	 * Listing style for this class.
	 *
	 * @var int
	 */
	public $style = parent::LIST_ORDERED;

	/**
	 * List(Section) Start
	 *
	 * @var string
	 */
	public $listStart = '<ol%s>';

	/**
	 * List(Section) End
	 *
	 * @var string
	 */
	public $listEnd = '</ol>';

	/**
	 * Offset Count
	 *
	 * @var int
	 */
	private $offsetCount = 0;

	/**
	 * Format the list of articles.
	 *
	 * @param array $articles
	 * @param int $start
	 * @param int $count
	 * @return string
	 */
	public function formatList( $articles, $start, $count ) {
		$this->offsetCount = $count;

		return parent::formatList( $articles, $start, $count );
	}

	/**
	 * Return $this->listStart with attributes replaced.
	 *
	 * @return string
	 */
	public function getListStart() {
		// increase start value of ordered lists at multi-column output
		// The offset that comes from the URL parameter is zero based, but has to be +1'ed for display.
		$offset = $this->getParameters()->getParameter( 'offset' ) + 1;

		if ( $offset != 0 ) {
			// @TODO: So this adds the total count of articles to the offset. I have not found a case where this does not mess up the displayed count. I am commenting this out for now.
			// $offset += $this->offsetCount;
		}

		return sprintf( $this->listStart, $this->listAttributes . ' start="' . $offset . '"' );
	}
}
