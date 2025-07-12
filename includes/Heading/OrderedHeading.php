<?php

namespace MediaWiki\Extension\DynamicPageList4\Heading;

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
