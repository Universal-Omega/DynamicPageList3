<?php

namespace MediaWiki\Extension\DynamicPageList4\Lister;

class UnorderedList extends Lister {

	protected int $style = parent::LIST_UNORDERED;

	protected string $listStart = '<ul%s>';
	protected string $listEnd = '</ul>';

	protected string $itemStart = '<li%s>';
	protected string $itemEnd = '</li>';
}
