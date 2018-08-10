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
}
?>