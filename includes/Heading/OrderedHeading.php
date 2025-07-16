<?php

namespace MediaWiki\Extension\DynamicPageList4\Heading;

class OrderedHeading extends UnorderedHeading {

	protected string $listStart = '<ol%s>';

	protected string $listEnd = '</ol>';
}
