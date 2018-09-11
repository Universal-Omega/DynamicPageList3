<?php
/**
 * DynamicPageList3
 * DPL DefinitionHeading Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/

namespace DPL\Heading;

use DPL\Lister\Lister;

class DefinitionHeading extends Heading {
	/**
	 * Heading List Start
	 * Use %s for attribute placement.  Example: <div%s>
	 *
	 * @var		string
	 */
	public $headListStart = '<dt>';

	/**
	 * Heading List End
	 *
	 * @var		string
	 */
	public $headListEnd = '</dt>';

	/**
	 * Heading List Start
	 * Use %s for attribute placement.  Example: <div%s>
	 *
	 * @var		string
	 */
	public $headItemStart = '';

	/**
	 * Heading List End
	 *
	 * @var		string
	 */
	public $headItemEnd = '';

	/**
	 * List(Section) Start
	 *
	 * @var		string
	 */
	public $listStart = '<dl%s>';

	/**
	 * List(Section) End
	 *
	 * @var		string
	 */
	public $listEnd = '</dl>';

	/**
	 * Item Start
	 *
	 * @var		string
	 */
	public $itemStart = '<dd%s>';

	/**
	 * Item End
	 *
	 * @var		string
	 */
	public $itemEnd = '</dd>';

	/**
	 * Format a heading group.
	 *
	 * @access	public
	 * @param	integer	Article start index for this heading.
	 * @param	integer	Article count for this heading.
	 * @param	string	Heading link/text display.
	 * @param	array	List of \DPL\Article.
	 * @param	object	List of \DPL\Lister\Lister
	 * @return	string	Heading HTML
	 */
	public function formatItem($headingStart, $headingCount, $headingLink, $articles, Lister $lister) {
		$item = '';

		$item .= $this->headListStart . $headingLink;
		if ($this->showHeadingCount) {
			$item .= $this->articleCountMessage($headingCount);
		}
		$item .= $this->headListEnd;
		$item .= $this->getItemStart() . $lister->formatList($articles, $headingStart, $headingCount) . $this->getItemEnd();

		return $item;
	}
}
