<?php
/**
 * DynamicPageList3
 * DPL InlineList Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/

namespace DPL\Lister;

class InlineList extends Lister {
	/**
	 * Listing style for this class.
	 *
	 * @var		constant
	 */
	public $style = parent::LIST_INLINE;

	/**
	 * Heading Start
	 *
	 * @var		string
	 */
	public $headingStart = '';

	/**
	 * Heading End
	 *
	 * @var		string
	 */
	public $headingEnd = '';

	/**
	 * List(Section) Start
	 *
	 * @var		string
	 */
	public $listStart = '<div%s>';

	/**
	 * List(Section) End
	 *
	 * @var		string
	 */
	public $listEnd = '</div>';

	/**
	 * Item Start
	 *
	 * @var		string
	 */
	public $itemStart = '<span%s>';

	/**
	 * Item End
	 *
	 * @var		string
	 */
	public $itemEnd = '</span>';

	/**
	 * Inline item text separator.
	 *
	 * @var		string
	 */
	protected $textSeparator = '';

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @param	object	\DPL\Parameters
	 * @param	object	MediaWiki \Parser
	 * @return	void
	 */
	public function __construct(\DPL\Parameters $parameters, \Parser $parser) {
		parent::__construct($parameters, $parser);
		$this->textSeparator = $parameters->getParameter('inlinetext');
	}

	/**
	 * Join together items after being processed by formatItem().
	 *
	 * @access	public
	 * @param	array	Items as formatted by formatItem().
	 * @return	string	Imploded items.
	 */
	protected function implodeItems($items) {
		return implode($this->textSeparator, $items);
	}
}
