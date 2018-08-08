<?php
/**
 * DynamicPageList3
 * DPL DefinitionList Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/

namespace DPL\List;

class DefinitionList {
	/**
	 * Listing style for this class.
	 *
	 * @var		constant
	 */
	public $style = LIST_DEFINITION;

	/**
	 * Heading Start
	 *
	 * @var		string
	 */
	public $headingStart = '<dt>';

	/**
	 * Heading End
	 *
	 * @var		string
	 */
	public $headingEnd = '</dt>';

	/**
	 * List(Section) Start
	 *
	 * @var		string
	 */
	public $listStart = '<dl>';

	/**
	 * List(Section) End
	 *
	 * @var		string
	 */
	public $listEnd = '</dl>';
	/**
	 * Item Start
	 *
	 * @var		string
	 */
	public $itemStart = '<dd>';

	/**
	 * Item End
	 *
	 * @var		string
	 */
	public $itemEnd = '</dd>';
}
?>