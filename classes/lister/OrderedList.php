<?php
/**
 * DynamicPageList3
 * DPL OrderedList Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/

namespace DPL\Lister;

class OrderedList extends UnorderedList {
	/**
	 * Listing style for this class.
	 *
	 * @var		constant
	 */
	public $style = parent::LIST_ORDERED;

	/**
	 * List(Section) Start
	 *
	 * @var		string
	 */
	public $listStart = '<ol%s>';

	/**
	 * List(Section) End
	 *
	 * @var		string
	 */
	public $listEnd = '</ol>';

	/**
	 * Offset Count
	 *
	 * @var		integer
	 */
	private $offsetCount = 0;

	/**
	 * Format the list of articles.
	 *
	 * @access	public
	 * @param	array	List of \DPL\Article
	 * @param	integer	Start position of the array to process.
	 * @param	integer	Total objects from the array to process.
	 * @return	string	Formatted list.
	 */
	public function formatList($articles, $start, $count) {
		$this->offsetCount = $count;
		return parent::formatList($articles, $start, $count);
	}

	/**
	 * Return $this->listStart with attributes replaced.
	 *
	 * @access	public
	 * @return	string	List Start
	 */
	public function getListStart() {
		// increase start value of ordered lists at multi-column output
		//The offset that comes from the URL parameter is zero based, but has to be +1'ed for display.
		$offset = $this->getParameters()->getParameter('offset') + 1;

		if ($offset != 0) {
			//@TODO: So this adds the total count of articles to the offset.  I have not found a case where this does not mess up the displayed count.  I am commenting this out for now.
			//$offset += $this->offsetCount;
		}

		return sprintf($this->listStart, $this->listAttributes . ' start="' . $offset . '"');
	}
}
