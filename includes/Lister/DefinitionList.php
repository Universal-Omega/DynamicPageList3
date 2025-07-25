<?php

namespace MediaWiki\Extension\DynamicPageList4\Lister;

class DefinitionList extends Lister {

	protected int $style = parent::LIST_DEFINITION;

	protected string $headListStart = '<dt%s>';
	protected string $headListEnd = '</dt>';

	protected string $listStart = '<dl%s>';
	protected string $listEnd = '</dl>';

	protected string $itemStart = '<dd%s>';
	protected string $itemEnd = '</dd>';
}
