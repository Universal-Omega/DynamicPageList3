<?php
/**
 * DynamicPageList3
 * DPL OrderedList Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/

namespace DPL\List;

class OrderedList extends UnorderedList {
	/**
	 * Listing style for this class.
	 *
	 * @var		constant
	 */
	public $style = LIST_ORDERED;

	/**
	 * List(Section) Start
	 *
	 * @var		string
	 */
	public $listStart = '<ol>';

	/**
	 * List(Section) End
	 *
	 * @var		string
	 */
	public $listEnd = '</ol>';
}
?>