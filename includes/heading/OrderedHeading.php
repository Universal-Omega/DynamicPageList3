<?php

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
