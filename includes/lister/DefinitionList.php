<?php

namespace DPL\Lister;

class DefinitionList extends Lister {
	/**
	 * Listing style for this class.
	 *
	 * @var int
	 */
	public $style = parent::LIST_DEFINITION;

	/**
	 * Heading List Start
	 * Use %s for attribute placement.  Example: <div%s>
	 *
	 * @var string
	 */
	public $headListStart = '<dt%s>';

	/**
	 * Heading List End
	 *
	 * @var string
	 */
	public $headListEnd = '</dt>';

	/**
	 * Heading List Start
	 * Use %s for attribute placement.  Example: <div%s>
	 *
	 * @var string
	 */
	public $headItemStart = '';

	/**
	 * Heading List End
	 *
	 * @var string
	 */
	public $headItemEnd = '';

	/**
	 * List(Section) Start
	 *
	 * @var string
	 */
	public $listStart = '<dl%s>';

	/**
	 * List(Section) End
	 *
	 * @var string
	 */
	public $listEnd = '</dl>';

	/**
	 * Item Start
	 *
	 * @var string
	 */
	public $itemStart = '<dd%s>';

	/**
	 * Item End
	 *
	 * @var string
	 */
	public $itemEnd = '</dd>';
}
