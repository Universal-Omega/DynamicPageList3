<?php
/**
 * DynamicPageList3
 * DPL GalleryList Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/

namespace DPL\List;

class GalleryList {
	/**
	 * Listing style for this class.
	 *
	 * @var		constant
	 */
	public $style = LIST_GALLERY;

	/**
	 * List(Section) Start
	 *
	 * @var		string
	 */
	public $listStart = '<gallery>';

	/**
	 * List(Section) End
	 *
	 * @var		string
	 */
	public $listEnd = '</gallery>';
	/**
	 * Item Start
	 *
	 * @var		string
	 */
	public $itemStart = "\n";

	/**
	 * Item End
	 *
	 * @var		string
	 */
	public $itemEnd = "||";
}
?>