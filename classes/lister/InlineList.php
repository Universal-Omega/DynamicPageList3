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
		// default for inlinetext (if not in mode=userformat)
		if (($listmode != 'userformat') && ($inlinetext == '')) {
			$inlinetext = '&#160;-&#160;';
		}
		$this->name = $listmode;
		$_listattr  = ($listattr == '') ? '' : ' '.\Sanitizer::fixTagAttributes($listattr, 'ul');
		$_itemattr  = ($itemattr == '') ? '' : ' '.\Sanitizer::fixTagAttributes($itemattr, 'li');

		$this->sectionSeparators = $sectionSeparators;
		$this->multiSectionSeparators = $multiSectionSeparators;
		$this->dominantSectionCount = $dominantSection - 1; // 0 based index

		switch ($listmode) {
			case 'inline':
				if (stristr($inlinetext, '<br/>')) { //one item per line (pseudo-inline)
					$this->sListStart = '<div'.$_listattr.'>';
					$this->sListEnd   = '</div>';
				}
				$this->sItemStart = '<span'.$_itemattr.'>';
				$this->sItemEnd   = '</span>';
				$this->sInline    = $inlinetext;
				break;
			case 'gallery':
				$this->sListStart = "<gallery>\n";
				$this->sListEnd   = "\n</gallery>";

				$this->sItemStart = '';
				$this->sItemEnd   = '||';
				$this->sInline    = "\n";
				break;
			case 'ordered':
				if ($iOffset == 0) {
					$this->sListStart = '<ol start=1 '.$_listattr.'>';
				} else {
					$this->sListStart = '<ol start='.($iOffset + 1).' '.$_listattr.'>';
				}
				$this->sListEnd   = '</ol>';
				$this->sItemStart = '<li'.$_itemattr.'>';
				$this->sItemEnd   = '</li>';
				break;
			case 'unordered':
				$this->sListStart = '<ul'.$_listattr.'>';
				$this->sListEnd   = '</ul>';
				$this->sItemStart = '<li'.$_itemattr.'>';
				$this->sItemEnd   = '</li>';
				break;
			case 'definition':
				$this->sListStart    = '<dl'.$_listattr.'>';
				$this->sListEnd      = '</dl>';
				// item html attributes on dt element or dd element ?
				$this->sHeadingStart = '<dt>';
				$this->sHeadingEnd   = '</dt><dd>';
				$this->sItemEnd      = '</dd>';
				break;
			case 'H2':
			case 'H3':
			case 'H4':
				$this->sListStart    = '<div'.$_listattr.'>';
				$this->sListEnd      = '</div>';
				$this->sHeadingStart = '<'.$listmode.'>';
				$this->sHeadingEnd   = '</'.$listmode.'>';
				break;
			case 'userformat':
				list($this->sListStart, $this->sItemStart, $this->sItemEnd, $this->sListEnd) = array_pad($listseparators, 4, null);
				$this->sInline = $inlinetext;
				break;
		}
	}
}
?>