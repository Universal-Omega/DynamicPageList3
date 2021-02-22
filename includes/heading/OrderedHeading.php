<?php
/**
 * DynamicPageList3
 * DPL OrderedHeading Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 */

namespace DPL\Heading;

class OrderedHeading extends UnorderedHeading {
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
}
