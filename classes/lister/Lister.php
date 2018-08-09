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

use DPL\LST;

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
	 * Section separators that separate transcluded pages/sections of wiki text.
	 *
	 * @var		array
	 */
	protected $sectionSeparators = [];

	/**
	 * Section separators that separate transcluded pages/sections that refer to the same chapter or tempalte of wiki text.
	 *
	 * @var		array
	 */
	protected $multiSectionSeparators = [];

	/**
	 * Include page text in output.
	 *
	 * @var		boolean
	 */
	protected $includePageText = false;

	/**
	 * Maximum length before truncated included wiki text.
	 *
	 * @var		integer
	 */
	protected $includePageMaxLength = null;

	/**
	 * Array of plain text matches for page transclusion. (include)
	 *
	 * @var		array
	 */
	protected $pageTextMatch = null;

	/**
	 * Array of regex text matches for page transclusion. (includematch)
	 *
	 * @var		array
	 */
	protected $pageTextMatchRegex = null;

	/**
	 * Array of not regex text matches for page transclusion. (includenotmatch)
	 *
	 * @var		array
	 */
	protected $pageTextMatchNotRegex = null;

	/**
	 * Parsed wiki text into HTML before running include/includematch/includenotmatch.
	 *
	 * @var		boolean
	 */
	protected $includePageParsed = false;

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @param	object	\DPL\Parameters
	 * @return	void
	 */
	public function __construct(\DPL\Parameters $parameters) {
		$this->setListAttributes($parameters->getParameter('listattr'));
		$this->setItemAttributes($parameters->getParameter('itemattr'));
		$this->setDominantSectionCount($parameters->getParameter('dominantsection'));
		$this->setTemplateSuffix($parameters->getParameter('defaulttemplatesuffix'));
		$this->setTrimIncluded($parameters->getParameter('includetrim'));
		$this->setTableSortColumn($parameters->getParameter('tablesortcol'));
		$this->setTitleMaxLength($parameters->getParameter('titlemaxlen'));
		$this->setEscapeLinks($parameters->getParameter('escapelinks'));
		$this->setSectionSeparators($parameters->getParameter('secseparators'));
		$this->setMultiSectionSeparators($parameters->getParameter('multisecseparators'));
		$this->setIncludePageText($parameters->getParameter('incpage'));
		$this->setIncludePageMaxLength($parameters->getParameter('includemaxlen'));
		$this->setPageTextMatch((array)$parameters->getParameter('seclabels'));
		$this->setPageTextMatchRegex((array)$parameters->getParameter('seclabelsmatch'));
		$this->setPageTextMatchNotRegex((array)$parameters->getParameter('seclabelsnotmatch'));
		$this->setIncludePageParsed($parameters->getParameter('incparsed'));
	}

	/**
	 * Get a new List subclass based on user selection.
	 *
	 * @access	public
	 * @param	string	List style.
	 * @param	object	\DPL\Parameters
	 * @return	object	List subclass.
	 */
	static public function newFromStyle($style, \DPL\Parameters $parameters) {
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

		return new $class($parameters);
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
	 * Get the count of items to trigger a section as dominant.
	 *
	 * @access	public
	 * @return	integer	Count
	 */
	public function getDominantSectionCount() {
		return $this->dominantSectionCount;
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
	 * Set the separators that separate sections of matched page text.
	 *
	 * @access	public
	 * @param	array	[Optional] Array of section separators.
	 * @return	void
	 */
	public function setSectionSeparators(array $separators = []) {
		$this->sectionSeparators = (array)$separators;
	}

	/**
	 * Set the separators that separate related sections of matched page text.
	 *
	 * @access	public
	 * @param	array	[Optional] Array of section separators.
	 * @return	void
	 */
	public function setMultiSectionSeparators(array $separators = []) {
		$this->multiSectionSeparators = (array)$separators;
	}

	/**
	 * Set if wiki text should be included in output.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Parse
	 * @return	void
	 */
	public function setIncludePageText($include = false) {
		$this->includePageText = boolval($include);
	}

	/**
	 * Set the maximum included page text length before truncating.
	 *
	 * @access	public
	 * @param	mixed	[Optional] Integer length or null to disable.
	 * @return	void
	 */
	public function setIncludePageMaxLength($length = null) {
		$this->includePageMaxLength = $length === null ? null : intval($length);
	}

	/**
	 * Set the plain string text matching for page transclusion.
	 *
	 * @access	public
	 * @param	array	[Optional] Array of plain string matches.
	 * @return	void
	 */
	public function setPageTextMatch(array $pageTextMatch = []) {
		$this->pageTextMatch = (array)$pageTextMatch;
	}

	/**
	 * Set the regex text matching for page transclusion.
	 *
	 * @access	public
	 * @param	array	[Optional] Array of regexes.
	 * @return	void
	 */
	public function setPageTextMatchRegex(array $pageTextMatchRegex = []) {
		$this->pageTextMatchRegex = (array)$pageTextMatchRegex;
	}

	/**
	 * Set the not regex text matching for page transclusion.
	 *
	 * @access	public
	 * @param	array	[Optional] Array of regexes.
	 * @return	void
	 */
	public function setPageTextMatchNotRegex(array $pageTextMatchNotRegex = []) {
		$this->pageTextMatchNotRegex = (array)$pageTextMatchNotRegex;
	}

	/**
	 * Set if included wiki text should be parsed before being matched against.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Parse
	 * @return	void
	 */
	public function setIncludePageParsed($parse = false) {
		$this->includePageParsed = boolval($parse);
	}

	/**
	 * Function Documentation
	 *
	 * @access	public
	 * @param	array	List of \DPL\Article
	 * @param	integer	Start position of the array to process.
	 * @param	integer	Total objects from the array to process.
	 * @return	void
	 */
	public function formatList($articles, $start, $count) {
		$filteredCount = 0;
		$items = [];
		for ($i = $start; $i < $start + $count; $i++) {

			$article = $articles[$i];
			if (empty($article) || empty($article->mTitle)) {
				continue;
			}

			// Page transclusion: get contents and apply selection criteria based on that contents

			$pageText = null;
			if ($this->includePageText) {
				$pageText = $this->transcludePage($article, $filteredCount);
			} else {
				$filteredCount = $filteredCount + 1;
			}

			if ($i > $start) {
				$rBody .= $this->sInline; //If mode is not 'inline', sInline attribute is empty, so does nothing
			}

			$items[] = $this->formatItem($article, $pageText);
		}

		//@TODO: This start stuff might be UserFormat only.
		// increase start value of ordered lists at multi-column output
		$actualStart = $this->listStart;
		$start    = preg_replace('/.*start=([0-9]+).*/', '\1', $actualStart);
		$start    = intval($start);
		if ($start != 0) {
			$start += $count;
			$actualStart = preg_replace('/start=[0-9]+/', "start=$start", $actualStart);
		}

		return $actualStart.implode($items).$this->listEnd;
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
	 *
	 * @access	public
	 * @param	string	Text to perform replacements on.
	 * @param	object	\DPL\Article
	 * @param	integer	The current article sequence number (starting from 1).
	 * @return	string	Text with replacements performed.
	 */
	public function replaceTagParameters($tag, \DPL\Article $article, $nr) {
		global $wgLang;

		if (strpos($tag, '%') === false) {
			return $tag;
		}

		$imageUrl = '';
		if ($article->mNamespace == NS_FILE) {
			// calculate URL for existing images
			// $img = Image::newFromName($article->mTitle->getText());
			$img = wfFindFile(\Title::makeTitle(NS_FILE, $article->mTitle->getText()));
			//@TODO: Check this preg_replace.  Probably only works for stock file repositories.
			if ($img && $img->exists()) {
				$imageUrl = $img->getURL();
				$imageUrl = preg_replace('~^.*images/(.*)~', '\1', $imageUrl);
			} else {
				$iTitle   = \Title::makeTitleSafe(6, $article->mTitle->getDBKey());
				$imageUrl = preg_replace('~^.*images/(.*)~', '\1', \RepoGroup::singleton()->getLocalRepo()->newFile($iTitle)->getPath());
			}
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

	/**
	 * Transclude a page contents.
	 *
	 * @access	public
	 * @param	object	\DPL\Article
	 * @param	integer	Filtered Article Count
	 * @return	string	Page Text
	 */
	public function transcludePage(\DPL\Article $article, &$filteredCount) {
		$matchFailed = false;
		if (empty($this->pageTextMatch) || $this->pageTextMatch[0] == '*') { // include whole article
			$title = $article->mTitle->getPrefixedText();
			if ($this->getStyle() == self::LIST_USERFORMAT) {
				$pageText = '';
			} else {
				$pageText = '<br/>';
			}
			$text = $this->mParser->fetchTemplate(\Title::newFromText($title));
			if ((count($this->pageTextMatchRegex) <= 0 || $this->pageTextMatchRegex[0] == '' || !preg_match($this->pageTextMatchRegex[0], $text) == false) && (count($this->pageTextMatchNotRegex) <= 0 || $this->pageTextMatchNotRegex[0] == '' || preg_match($this->pageTextMatchNotRegex[0], $text) == false)) {
				if ($this->includePageMaxLength > 0 && (strlen($text) > $this->includePageMaxLength)) {
					$text = LST::limitTranscludedText($text, $this->includePageMaxLength, ' [['.$title.'|..→]]');
				}
				$filteredCount = $filteredCount + 1;

				// append full text to output
				if (is_array($this->sectionSeparators) && array_key_exists('0', $this->sectionSeparators)) {
					$pageText .= $this->replaceTagParameters($this->sectionSeparators[0], $article, $filteredCount);
					$pieces = [
						0 => $text
					];
					$this->formatSingleItems($pieces, 0, $article);
					$pageText .= $pieces[0];
				} else {
					$pageText .= $text;
				}
			} else {
				return '';
			}

		} else {
			// identify section pieces
			$secPiece       = [];
			$dominantPieces = false;
			// ONE section can be marked as "dominant"; if this section contains multiple entries
			// we will create a separate output row for each value of the dominant section
			// the values of all other columns will be repeated

			foreach ($this->pageTextMatch as $s => $sSecLabel) {
				$sSecLabel = trim($sSecLabel);
				if ($sSecLabel == '') {
					break;
				}
				// if sections are identified by number we have a % at the beginning
				if ($sSecLabel[0] == '%') {
					$sSecLabel = '#'.$sSecLabel;
				}

				$maxlen = -1;
				if ($sSecLabel == '-') {
					// '-' is used as a dummy parameter which will produce no output
					// if maxlen was 0 we suppress all output; note that for matching we used the full text
					$secPieces = [
						''
					];
					$this->formatSingleItems($secPieces, $s, $article);
				} elseif ($sSecLabel[0] != '{') {
					$limpos      = strpos($sSecLabel, '[');
					$cutLink     = 'default';
					$skipPattern = [];
					if ($limpos > 0 && $sSecLabel[strlen($sSecLabel) - 1] == ']') {
						// regular expressions which define a skip pattern may precede the text
						$fmtSec    = explode('~', substr($sSecLabel, $limpos + 1, strlen($sSecLabel) - $limpos - 2));
						$sSecLabel = substr($sSecLabel, 0, $limpos);
						$cutInfo   = explode(" ", $fmtSec[count($fmtSec) - 1], 2);
						$maxlen    = intval($cutInfo[0]);
						if (array_key_exists('1', $cutInfo)) {
							$cutLink = $cutInfo[1];
						}
						foreach ($fmtSec as $skipKey => $skipPat) {
							if ($skipKey == count($fmtSec) - 1) {
								continue;
							}
							$skipPattern[] = $skipPat;
						}
					}
					if ($maxlen < 0) {
						$maxlen = -1; // without valid limit include whole section
					}
				}

				// find out if the user specified an includematch / includenotmatch condition
				if (is_array($this->pageTextMatchRegex) && count($this->pageTextMatchRegex) > $s && $this->pageTextMatchRegex[$s] != '') {
					$mustMatch = $this->pageTextMatchRegex[$s];
				} else {
					$mustMatch = '';
				}
				if (is_array($this->pageTextMatchNotRegex) && count($this->pageTextMatchNotRegex) > $s && $this->pageTextMatchNotRegex[$s] != '') {
					$mustNotMatch = $this->pageTextMatchNotRegex[$s];
				} else {
					$mustNotMatch = '';
				}

				// if chapters are selected by number, text or regexp we get the heading from LST::includeHeading
				$sectionHeading[0] = '';
				if ($sSecLabel == '-') {
					$secPiece[$s] = $secPieces[0];
				} elseif ($sSecLabel[0] == '#' || $sSecLabel[0] == '@') {
					$sectionHeading[0] = substr($sSecLabel, 1);
					// Uses LST::includeHeading() from LabeledSectionTransclusion extension to include headings from the page
					$secPieces = LST::includeHeading($this->mParser, $article->mTitle->getPrefixedText(), substr($sSecLabel, 1), '', $sectionHeading, false, $maxlen, $cutLink, $this->getTrimIncluded(), $skipPattern);
					if ($mustMatch != '' || $mustNotMatch != '') {
						$secPiecesTmp = $secPieces;
						$offset       = 0;
						foreach ($secPiecesTmp as $nr => $onePiece) {
							if (($mustMatch != '' && preg_match($mustMatch, $onePiece) == false) || ($mustNotMatch != '' && preg_match($mustNotMatch, $onePiece) != false)) {
								array_splice($secPieces, $nr - $offset, 1);
								$offset++;
							}
						}
					}
					// if maxlen was 0 we suppress all output; note that for matching we used the full text
					if ($maxlen == 0) {
						$secPieces = [
							''
						];
					}

					$this->formatSingleItems($secPieces, $s, $article);
					if (!array_key_exists(0, $secPieces)) {
						// avoid matching against a non-existing array element
						// and skip the article if there was a match condition
						if ($mustMatch != '' || $mustNotMatch != '') {
							$matchFailed = true;
						}
						break;
					}
					$secPiece[$s] = $secPieces[0];
					for ($sp = 1; $sp < count($secPieces); $sp++) {
						if (isset($this->multiSectionSeparators[$s])) {
							$secPiece[$s] .= str_replace('%SECTION%', $sectionHeading[$sp], $this->replaceTagParameters($this->multiSectionSeparators[$s], $article, $filteredCount));
						}
						$secPiece[$s] .= $secPieces[$sp];
					}
					if ($this->getDominantSectionCount() >= 0 && $s == $this->getDominantSectionCount() && count($secPieces) > 1) {
						$dominantPieces = $secPieces;
					}
					if (($mustMatch != '' || $mustNotMatch != '') && count($secPieces) <= 0) {
						$matchFailed = true; // NOTHING MATCHED
						break;
					}

				} elseif ($sSecLabel[0] == '{') {
					// Uses LST::includeTemplate() from LabeledSectionTransclusion extension to include templates from the page
					// primary syntax {template}suffix
					$template1 = trim(substr($sSecLabel, 1, strpos($sSecLabel, '}') - 1));
					$template2 = trim(str_replace('}', '', substr($sSecLabel, 1)));
					// alternate syntax: {template|surrogate}
					if ($template2 == $template1 && strpos($template1, '|') > 0) {
						$template1 = preg_replace('/\|.*/', '', $template1);
						$template2 = preg_replace('/^.+\|/', '', $template2);
					}
					//Why the hell was defaultTemplateSuffix be passed all over the place for just fucking here?  --Alexia
					$secPieces    = LST::includeTemplate($this->mParser, $this, $s, $article, $template1, $template2, $template2.$this->getTemplateSuffix(), $mustMatch, $mustNotMatch, $this->includePageParsed, implode(', ', $article->mCategoryLinks));
					$secPiece[$s] = implode(isset($this->multiSectionSeparators[$s]) ? $this->replaceTagParameters($this->multiSectionSeparators[$s], $article, $filteredCount) : '', $secPieces);
					if ($this->getDominantSectionCount() >= 0 && $s == $this->getDominantSectionCount() && count($secPieces) > 1) {
						$dominantPieces = $secPieces;
					}
					if (($mustMatch != '' || $mustNotMatch != '') && count($secPieces) <= 1 && $secPieces[0] == '') {
						$matchFailed = true; // NOTHING MATCHED
						break;
					}
				} else {
					// Uses LST::includeSection() from LabeledSectionTransclusion extension to include labeled sections from the page
					$secPieces    = LST::includeSection($this->mParser, $article->mTitle->getPrefixedText(), $sSecLabel, '', false, $this->getTrimIncluded(), $skipPattern);
					$secPiece[$s] = implode(isset($this->multiSectionSeparators[$s]) ? $this->replaceTagParameters($this->multiSectionSeparators[$s], $article, $filteredCount) : '', $secPieces);
					if ($this->getDominantSectionCount() >= 0 && $s == $this->getDominantSectionCount() && count($secPieces) > 1) {
						$dominantPieces = $secPieces;
					}
					if (($mustMatch != '' && preg_match($mustMatch, $secPiece[$s]) == false) || ($mustNotMatch != '' && preg_match($mustNotMatch, $secPiece[$s]) != false)) {
						$matchFailed = true;
						break;
					}
				}

				// separator tags
				if (is_array($this->sectionSeparators) && count($this->sectionSeparators) == 1) {
					// If there is only one separator tag use it always
					$septag[$s * 2] = str_replace('%SECTION%', $sectionHeading[0], $this->replaceTagParameters($this->sectionSeparators[0], $article, $filteredCount));
				} elseif (isset($this->sectionSeparators[$s * 2])) {
					$septag[$s * 2] = str_replace('%SECTION%', $sectionHeading[0], $this->replaceTagParameters($this->sectionSeparators[$s * 2], $article, $filteredCount));
				} else {
					$septag[$s * 2] = '';
				}
				if (isset($this->sectionSeparators[$s * 2 + 1])) {
					$septag[$s * 2 + 1] = str_replace('%SECTION%', $sectionHeading[0], $this->replaceTagParameters($this->sectionSeparators[$s * 2 + 1], $article, $filteredCount));
				} else {
					$septag[$s * 2 + 1] = '';
				}

			}

			// if there was a match condition on included contents which failed we skip the whole page
			if ($matchFailed) {
				return '';
			}
			$filteredCount = $filteredCount + 1;

			// assemble parts with separators
			$pageText = '';
			if ($dominantPieces != false) {
				foreach ($dominantPieces as $dominantPiece) {
					foreach ($secPiece as $s => $piece) {
						if ($s == $this->getDominantSectionCount()) {
							$pageText .= $this->formatItem($dominantPiece, $septag[$s * 2], $septag[$s * 2 + 1]);
						} else {
							$pageText .= $this->formatItem($piece, $septag[$s * 2], $septag[$s * 2 + 1]);
						}
					}
				}
			} else {
				foreach ($secPiece as $s => $piece) {
					$pageText .= $this->formatItem($piece, $septag[$s * 2], $septag[$s * 2 + 1]);
				}
			}
		}
		return $pageText;
	}
}
?>