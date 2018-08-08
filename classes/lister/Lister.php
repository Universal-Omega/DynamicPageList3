<?php
/**
 * DynamicPageList3
 * DPL List Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/
namespace DPL\Lister;

class Lister {
	const LIST_DEFINITION = 1;
	const LIST_GALLERY = 2;
	const LIST_HEADING = 3;
	const LIST_INLINE = 4;
	const LIST_ORDERED = 5;
	const LIST_UNORDERED = 6;
	const LIST_CATEGORY = 7;
	const LIST_USERFORMAT = 8;

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
	protected $dominantSectionCount = -1;

	/**
	 * Template Suffix
	 *
	 * @var		string
	 */
	protected $templateSuffix = '';

	/**
	 * Trim included wiki text.
	 *
	 * @var		boolean
	 */
	protected $trimIncluded = false;

	/**
	 * Trim included wiki text.
	 *
	 * @var		boolean
	 */
	protected $escapeLinks = true;

	/**
	 * Index of the table column to sort by.
	 *
	 * @var		integer
	 */
	protected $tableSortColumn = null;

	/**
	 * Maximum title length.
	 *
	 * @var		integer
	 */
	protected $titleMaxLength = null;

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
		$class = '\DPL\Lister\\'.$class;

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
		$this->listAttributes = \Sanitizer::fixTagAttributes($attributes, 'ul');
	}

	/**
	 * Set extra item attributes.
	 *
	 * @access	public
	 * @param	string	Tag soup attributes, example: this="that" thing="no"
	 * @return	void
	 */
	public function setItemAttributes($attributes) {
		$this->itemAttributes = \Sanitizer::fixTagAttributes($attributes, 'li');
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

	/**
	 * Return the list style.
	 *
	 * @access	public
	 * @return	integer	List style constant.
	 */
	public function getStyle() {
		return $this->style;
	}

	/**
	 * Set the template suffix for whatever the hell uses it.
	 *
	 * @access	public
	 * @param	string	Template Suffix
	 * @return	void
	 */
	public function setTemplateSuffix($suffix = '.default') {
		$this->templateSuffix = $suffix;
	}

	/**
	 * Get the template suffix for whatever the hell uses it.
	 *
	 * @access	public
	 * @return	string
	 */
	public function getTemplateSuffix() {
		return $this->templateSuffix;
	}

	/**
	 * Set if included wiki text should be trimmed.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Trim
	 * @return	void
	 */
	public function setTrimIncluded($trim = false) {
		$this->trimIncluded = boolval($trim);
	}

	/**
	 * Get if included wiki text should be trimmed.
	 *
	 * @access	public
	 * @return	boolean	Trim
	 */
	public function getTrimIncluded() {
		return $this->trimIncluded;
	}

	/**
	 * Set if links should be escaped?
	 * @TODO: The naming of this parameter is weird and I am not sure what it does.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Escape
	 * @return	void
	 */
	public function setEscapeLinks($escape = true) {
		$this->escapeLinks = boolval($escape);
	}

	/**
	 * Get if links should be escaped.
	 *
	 * @access	public
	 * @return	boolean	Escape
	 */
	public function getEscapeLinks() {
		return $this->escapeLinks;
	}

	/**
	 * Set the index of the table column to sort by.
	 *
	 * @access	public
	 * @param	mixed	[Optional] Integer index or null to disable.
	 * @return	void
	 */
	public function setTableSortColumn($index = null) {
		$this->tableSortColumn = $index === null ? null : intval($index);
	}

	/**
	 * Get the index of the table column to sort by.
	 *
	 * @access	public
	 * @return	mixed	Integer index or null to disable.
	 */
	public function getTableSortColumn() {
		return $this->tableSortColumn;
	}

	/**
	 * Set the maximum title length for display.
	 *
	 * @access	public
	 * @param	mixed	[Optional] Integer length or null to disable.
	 * @return	void
	 */
	public function setTitleMaxLength($length = null) {
		$this->titleMaxLength = $length === null ? null : intval($length);
	}

	/**
	 * Get the maximum title length for display.
	 *
	 * @access	public
	 * @return	mixed	Integer length or null to disable.
	 */
	public function getTitleMaxLength() {
		return $this->titleMaxLength;
	}

	/**
	 * Format an item.
	 *
	 * @access	public
	 * @param	object	DPL\Article
	 * @param	string	[Optional] Page text to 
	 * @return	string	Item HTML
	 */
	public function formatItem($article, $pageText = null) {
		global $wgLang;

		$item = $this->itemStart;
		//DPL Article, not MediaWiki.
		$date = $article->getDate();
		if ($date !== null) {
			$item .= $date.' ';
			if ($article->mRevision !== null) {
				$item .= '[{{fullurl:'.$article->mTitle.'|oldid='.$article->mRevision.'}} '.htmlspecialchars($article->mTitle).']';
			} else {
				$item .= $article->mLink;
			}
		} else {
			// output the link to the article
			$item .= $article->mLink;
		}

		if ($article->mSize != null) {
			$byte = 'B';
			$pageLength = $wgLang->formatNum($article->mSize);
			$item .= " [{$pageLength} {$byte}]";
		}

		if ($article->mCounter !== null) {
			// Adapted from SpecialPopularPages::formatResult()
			// $nv = $this->msgExt( 'nviews', array( 'parsemag', 'escape'), $wgLang->formatNum( $article->mCounter ) );
			$nv = $this->msgExt('hitcounters-nviews', [
				'escape'
			], $wgLang->formatNum($article->mCounter));
			$item .= ' '.$wgContLang->getDirMark().'('.$nv.')';
		}

		if ($article->mUserLink !== null) {
			$item .= ' . . [[User:'.$article->mUser.'|'.$article->mUser.']]';
			if ($article->mComment != '') {
				$item .= ' { '.$article->mComment.' }';
			}
		}

		if ($article->mContributor !== null) {
			$item .= ' . . [[User:'.$article->mContributor.'|'.$article->mContributor." $article->mContrib]]";
		}

		if (!empty($article->mCategoryLinks)) {
			$item .= ' . . <small>'.wfMessage('categories').': '.implode(' | ', $article->mCategoryLinks).'</small>';
		}

		//@TODO: I removed "$this->mAddExternalLink && " for testing.  Need to get this from the addexternallink parameter somehow.
		if ($article->mExternalLink !== null) {
			$item .= ' → '.$article->mExternalLink;
		}

		if ($pageText !== null) {
			//Include parsed/processed wiki markup content after each item before the closing tag.
			$item .= $pageText;
		}

		$item .= $this->itemEnd;

		return $item;
	}

	/**
	 * Replace user tag parameters.
	 * @TODO: Temporarily? static during rewrite.  Unsure if this will stay static.
	 *
	 * @access	public
	 * @param	string	Text to perform replacements on.
	 * @return	string	Text with replacements performed.
	 */
	public function replaceTagParameters($tag, $article, $imageUrl, $nr) {
		global $wgLang;

		if (strpos($tag, '%') === false) {
			return $tag;
		}

		if ($this->getEscapeLinks() && ($article->mNamespace == NS_CATEGORY || $article->mNamespace == NS_FILE)) {
			// links to categories or images need an additional ":"
			$pagename = ':'.$pagename;
		}

		$tag = str_replace('%PAGE%', $pagename, $tag);
		$tag = str_replace('%PAGEID%', $article->mID, $tag);
		$tag = str_replace('%NAMESPACE%', $this->nameSpaces[$article->mNamespace], $tag);
		$tag = str_replace('%IMAGE%', $imageUrl, $tag);
		$tag = str_replace('%EXTERNALLINK%', $article->mExternalLink, $tag);
		$tag = str_replace('%EDITSUMMARY%', $article->mComment, $tag);

		$title = $article->mTitle->getText();
		if (strpos($title, '%TITLE%') >= 0) {
			if ($this->mReplaceInTitle[0] != '') {
				$title = preg_replace($this->mReplaceInTitle[0], $this->mReplaceInTitle[1], $title);
			}
			$titleMaxLength = $this->getTitleMaxLength();
			if ($titleMaxLength !== null && (strlen($title) > $titleMaxLength)) {
				$title = substr($title, 0, $titleMaxLength).'...';
			}
			$tag = str_replace('%TITLE%', $title, $tag);
		}

		$tag = str_replace('%NR%', $nr, $tag);
		$tag = str_replace('%COUNT%', $article->mCounter, $tag);
		$tag = str_replace('%COUNTFS%', floor(log($article->mCounter) * 0.7), $tag);
		$tag = str_replace('%COUNTFS2%', floor(sqrt(log($article->mCounter))), $tag);
		$tag = str_replace('%SIZE%', $article->mSize, $tag);
		$tag = str_replace('%SIZEFS%', floor(sqrt(log($article->mSize)) * 2.5 - 5), $tag);
		$tag = str_replace('%DATE%', $article->getDate(), $tag);
		$tag = str_replace('%REVISION%', $article->mRevision, $tag);
		$tag = str_replace('%CONTRIBUTION%', $article->mContribution, $tag);
		$tag = str_replace('%CONTRIB%', $article->mContrib, $tag);
		$tag = str_replace('%CONTRIBUTOR%', $article->mContributor, $tag);
		$tag = str_replace('%USER%', $article->mUser, $tag);

		if ($article->mSelTitle != null) {
			if ($article->mSelNamespace == 0) {
				$tag = str_replace('%PAGESEL%', str_replace('_', ' ', $article->mSelTitle), $tag);
			} else {
				$tag = str_replace('%PAGESEL%', $this->nameSpaces[$article->mSelNamespace].':'.str_replace('_', ' ', $article->mSelTitle), $tag);
			}
		}
		$tag = str_replace('%IMAGESEL%', str_replace('_', ' ', $article->mImageSelTitle), $tag);

		if (!empty($article->mCategoryLinks)) {
			$tag = str_replace('%CATLIST%', implode(', ', $article->mCategoryLinks), $tag);
			$tag = str_replace('%CATBULLETS%', '* '.implode("\n* ", $article->mCategoryLinks), $tag);
			$tag = str_replace('%CATNAMES%', implode(', ', $article->mCategoryTexts), $tag);
		} else {
			$tag = str_replace('%CATLIST%', '', $tag);
			$tag = str_replace('%CATBULLETS%', '', $tag);
			$tag = str_replace('%CATNAMES%', '', $tag);
		}

		return $tag;
	}
}
?>