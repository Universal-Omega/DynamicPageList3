<?php

namespace DPL\Lister;

class UnorderedList extends Lister {
	/**
	 * Listing style for this class.
	 *
	 * @var int
	 */
	public $style = parent::LIST_UNORDERED;

	/**
	 * List(Section) Start
	 *
	 * @var string
	 */
	public $listStart = '<ul%s>';

	/**
	 * List(Section) End
	 *
	 * @var string
	 */
	public $listEnd = '</ul>';

	/**
	 * Item Start
	 *
	 * @var string
	 */
	public $itemStart = '<li%s>';

	/**
	 * Item End
	 *
	 * @var string
	 */
	public $itemEnd = '</li>';
}
