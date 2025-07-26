<?php

namespace MediaWiki\Extension\DynamicPageList4\Lister;

class InlineList extends Lister {

	protected int $style = parent::LIST_INLINE;

	protected string $listStart = '<div%s>';
	protected string $listEnd = '</div>';

	protected string $itemStart = '<span%s>';
	protected string $itemEnd = '</span>';

	protected function implodeItems( array $items ): string {
		$textSeparator = $this->parameters->getParameter( 'inlinetext' );
		return implode( $textSeparator, $items );
	}
}
