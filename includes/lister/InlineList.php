<?php

namespace DPL\Lister;

use DPL\Parameters;
use Parser;

class InlineList extends Lister {
	/**
	 * Listing style for this class.
	 *
	 * @var int
	 */
	public $style = parent::LIST_INLINE;

	/**
	 * Heading Start
	 *
	 * @var string
	 */
	public $headingStart = '';

	/**
	 * Heading End
	 *
	 * @var string
	 */
	public $headingEnd = '';

	/**
	 * List(Section) Start
	 *
	 * @var string
	 */
	public $listStart = '<div%s>';

	/**
	 * List(Section) End
	 *
	 * @var string
	 */
	public $listEnd = '</div>';

	/**
	 * Item Start
	 *
	 * @var string
	 */
	public $itemStart = '<span%s>';

	/**
	 * Item End
	 *
	 * @var string
	 */
	public $itemEnd = '</span>';

	/**
	 * Inline item text separator.
	 *
	 * @var string
	 */
	protected $textSeparator = '';

	/**
	 * @param Parameters $parameters
	 * @param Parser $parser
	 */
	public function __construct( Parameters $parameters, Parser $parser ) {
		parent::__construct( $parameters, $parser );

		$this->textSeparator = $parameters->getParameter( 'inlinetext' );
	}

	/**
	 * Join together items after being processed by formatItem().
	 *
	 * @param array $items
	 * @return string
	 */
	protected function implodeItems( $items ) {
		return implode( $this->textSeparator, $items );
	}
}
