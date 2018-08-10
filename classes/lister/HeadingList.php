<?php
/**
 * DynamicPageList3
 * DPL HeadingList Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/

namespace DPL\Lister;

class HeadingList extends Lister {
	/**
	 * Listing style for this class.
	 *
	 * @var		constant
	 */
	public $style = parent::LIST_HEADING;

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
	public $listStart = '';

	/**
	 * List(Section) End
	 *
	 * @var		string
	 */
	public $listEnd = '';

	/**
	 * Item Start
	 *
	 * @var		string
	 */
	public $itemStart = '';

	/**
	 * Item End
	 *
	 * @var		string
	 */
	public $itemEnd = '';

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @param	string	List Mode
	 * @param	string	Section Separators
	 * @param	string	Multi-Section Separators
	 * @param	string	Inline Text
	 * @param	string	[Optional] List Attributes
	 * @param	string	[Optional] Item Attributes
	 * @param	string	List Separators
	 * @param	integer	Offset
	 * @param	integer	Dominant Section
	 * @return	void
	 */
	public function __construct($listmode, $sectionSeparators, $multiSectionSeparators, $inlinetext, $listattr = '', $itemattr = '', $listseparators, $iOffset, $dominantSection) {
			case 'H2':
			case 'H3':
			case 'H4':
				$this->sListStart    = '<div'.$_listattr.'>';
				$this->sListEnd      = '</div>';
				$this->sHeadingStart = '<'.$listmode.'>';
				$this->sHeadingEnd   = '</'.$listmode.'>';
				break;
	}
}
?>