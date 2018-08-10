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
	public $listStart = '<div>';

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
	public $itemStart = '<span>';

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
	public $textSeparator = '';

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @param	object	\DPL\Parameters
	 * @return	void
	 */
	public function __construct(\DPL\Parameters $parameters) {
		parent::__construct($parameters);
		$this->textSeparator = $parameters->getParameter('inlinetext');
	}

	/**
	 * Format an item.
	 *
	 * @access	public
	 * @param	object	DPL\Article
	 * @param	string	[Optional] Page text to include.
	 * @return	string	Item HTML
	 */
	public function formatItem($article, $pageText = null) {
		$item = $lister->replaceTagParameters($lister->itemStart, $article, $this->filteredCount);

		$item .= parent::formatItem($article, $pageText);

		$item .= $lister->replaceTagParameters($lister->itemEnd, $article, $this->filteredCount);

		return $item;
	}
}
?>