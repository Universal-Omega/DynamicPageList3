<?php
/**
 * DynamicPageList3
 * DPL List Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/
namespace DPL;

class List {
	const LIST_DEFINITION;
	const LIST_GALLERY;
	const LIST_HEADING;
	const LIST_INLINE;
	const LIST_ORDERED;
	const LIST_UNORDERED;

	/**
	 * Listing style for this class.
	 *
	 * @var		constant
	 */
	public $style = null;

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
	 * Extra list HTML attributes.
	 *
	 * @var		array
	 */
	public $listAttributes = '';

	/**
	 * Extra item HTML attributes.
	 *
	 * @var		array
	 */
	public $itemAttributes = '';

	/**
	 * Section Separators
	 *
	 * @var		array
	 */
	public $sectionSeparators = [];

	/**
	 * Multi-Section Separators
	 *
	 * @var		array
	 */
	public $multiSectionSeparators = [];

	/**
	 * Count tipping point to mark a section as dominant.
	 *
	 * @var		integer
	 */
	public $dominantSectionCount = -1;

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {

	}

	/**
	 * Get a new List subclass based on user selection.
	 *
	 * @access	public
	 * @param	string	List style.
	 * @return	object	List subclass.
	 */
	static public function newFromStyle($style) {
		$style = strtolower($style);
		switch ($style) {
			case 'definition':
				$class = 'DefinitionList';
				break;
			case 'gallery':
				$class = 'GalleryList';
				break;
			case 'h1':
			case 'h2':
			case 'h3':
			case 'h4':
			case 'h5':
			case 'h6':
			case 'heading':
				$class = 'HeadingList';
				break;
			case 'inline':
				$class = 'InlineList';
				break;
			case 'ordered':
				$class = 'OrderedList';
				break;
			default:
			case 'unordered':
				$class = 'UnorderedList';
				break;
			case 'userformat':
				$class = 'UserFormatList';
				break;
		}

		return new $class;
	}

	/**
	 * Set extra list attributes.
	 *
	 * @access	public
	 * @param	string	Tag soup attributes, example: this="that" thing="no"
	 * @return	void
	 */
	public function setListAttributes($attributes) {
		$this->listAttributes = \Sanitizer::fixTagAttributes($listattr, 'ul');
	}

	/**
	 * Set extra item attributes.
	 *
	 * @access	public
	 * @param	string	Tag soup attributes, example: this="that" thing="no"
	 * @return	void
	 */
	public function setItemAttributes($attributes) {
		$this->itemAttributes = \Sanitizer::fixTagAttributes($listattr, 'li');
	}

	/**
	 * Set the count of items to trigger a section as dominant.
	 *
	 * @access	public
	 * @param	integer	Count
	 * @return	void
	 */
	public function setDominantSectionCount($count = -1) {
		$this->dominantSectionCount = intval($count);
	}
}
?>