<?php
/**
 * DynamicPageList3
 * DPL UnorderedList Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/

namespace DPL\List;

class UnorderedList {
	/**
	 * Listing style for this class.
	 *
	 * @var		constant
	 */
	public $style = LIST_UNORDERED;

	/**
	 * List(Section) Start
	 *
	 * @var		string
	 */
	public $listStart = '<ul>';

	/**
	 * List(Section) End
	 *
	 * @var		string
	 */
	public $listEnd = '</ul>';
	/**
	 * Item Start
	 *
	 * @var		string
	 */
	public $itemStart = '<li>';

	/**
	 * Item End
	 *
	 * @var		string
	 */
	public $itemEnd = '</li>';
}
?>