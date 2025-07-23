<?php

namespace MediaWiki\Extension\DynamicPageList4\Heading;

class UnorderedHeading extends Heading {

	protected string $listStart = '<ul%s>';
	protected string $listEnd = '</ul>';

	protected string $itemStart = '<li%s>';
	protected string $itemEnd = '</li>';
}
