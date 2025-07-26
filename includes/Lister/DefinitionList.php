<?php

namespace MediaWiki\Extension\DynamicPageList4\Lister;

class DefinitionList extends Lister {

	protected int $style = parent::LIST_DEFINITION;

	protected string $listStart = '<dl%s>';
	protected string $listEnd = '</dl>';

	protected string $itemStart = '<dd%s>';
	protected string $itemEnd = '</dd>';
}
