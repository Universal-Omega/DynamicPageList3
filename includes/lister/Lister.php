<?php
/**
 * DynamicPageList3
 * DPL List Class
 *
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 */

namespace DPL\Lister;

use DPL\Article;
use DPL\LST;
use DPL\UpdateArticle;
use MediaWiki\MediaWikiServices;

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
	 * @var constant
	 */
	public $style = null;

	/**
	 * Heading List Start
	 * Use %s for attribute placement.  Example: <div%s>
	 *
	 * @var string
	 */
	public $headListStart = '';

	/**
	 * Heading List End
	 *
	 * @var string
	 */
	public $headListEnd = '';

	/**
	 * Heading List Start
	 * Use %s for attribute placement.  Example: <div%s>
	 *
	 * @var string
	 */
	public $headItemStart = '';

	/**
	 * Heading List End
	 *
	 * @var string
	 */
	public $headItemEnd = '';

	/**
	 * List(Section) Start
	 * Use %s for attribute placement.  Example: <div%s>
	 *
	 * @var string
	 */
	public $listStart = '';

	/**
	 * List(Section) End
	 *
	 * @var string
	 */
	public $listEnd = '';

	/**
	 * Item Start
	 * Use %s for attribute placement.  Example: <div%s>
	 *
	 * @var string
	 */
	public $itemStart = '';

	/**
	 * Item End
	 *
	 * @var string
	 */
	public $itemEnd = '';

	/**
	 * Extra head list HTML attributes.
	 *
	 * @var array
	 */
	public $headListAttributes = '';

	/**
	 * Extra head item HTML attributes.
	 *
	 * @var array
	 */
	public $headItemAttributes = '';

	/**
	 * Extra list HTML attributes.
	 *
	 * @var array
	 */
	public $listAttributes = '';

	/**
	 * Extra item HTML attributes.
	 *
	 * @var array
	 */
	public $itemAttributes = '';

	/**
	 * Count tipping point to mark a section as dominant.
	 *
	 * @var int
	 */
	protected $dominantSectionCount = -1;

	/**
	 * Template Suffix
	 *
	 * @var string
	 */
	protected $templateSuffix = '';

	/**
	 * Trim included wiki text.
	 *
	 * @var bool
	 */
	protected $trimIncluded = false;

	/**
	 * Trim included wiki text.
	 *
	 * @var bool
	 */
	protected $escapeLinks = true;

	/**
	 * Index of the table column to sort by.
	 *
	 * @var int
	 */
	protected $tableSortColumn = null;

	/**
	 * Maximum title length.
	 *
	 * @var int
	 */
	protected $titleMaxLength = null;

	/**
	 * Section separators that separate transcluded pages/sections of wiki text.
	 *
	 * @var array
	 */
	protected $sectionSeparators = [];

	/**
	 * Section separators that separate transcluded pages/sections that refer to the same chapter or tempalte of wiki text.
	 *
	 * @var array
	 */
	protected $multiSectionSeparators = [];

	/**
	 * Include page text in output.
	 *
	 * @var bool
	 */
	protected $includePageText = false;

	/**
	 * Maximum length before truncated included wiki text.
	 *
	 * @var int
	 */
	protected $includePageMaxLength = null;

	/**
	 * Array of plain text matches for page transclusion. (include)
	 *
	 * @var array
	 */
	protected $pageTextMatch = null;

	/**
	 * Array of regex text matches for page transclusion. (includematch)
	 *
	 * @var array
	 */
	protected $pageTextMatchRegex = null;

	/**
	 * Array of not regex text matches for page transclusion. (includenotmatch)
	 *
	 * @var array
	 */
	protected $pageTextMatchNotRegex = null;

	/**
	 * Parsed wiki text into HTML before running include/includematch/includenotmatch.
	 *
	 * @var bool
	 */
	protected $includePageParsed = false;

	/**
	 * Total result count after parsing, transcluding, and such.
	 *
	 * @var int
	 */
	public $rowCount = 0;

	/**
	 * \DPL\Parameters
	 *
	 * @var object
	 */
	protected $parameters = null;

	/**
	 * Parser
	 *
	 * @var object
	 */
	protected $parser = null;

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @param	object	\DPL\Parameters
	 * @param	object	MediaWiki \Parser
	 * @return	void
	 */
	public function __construct( \DPL\Parameters $parameters, \Parser $parser ) {
		$this->setHeadListAttributes( $parameters->getParameter( 'hlistattr' ) );
		$this->setHeadItemAttributes( $parameters->getParameter( 'hitemattr' ) );
		$this->setListAttributes( $parameters->getParameter( 'listattr' ) );
		$this->setItemAttributes( $parameters->getParameter( 'itemattr' ) );
		$this->setDominantSectionCount( $parameters->getParameter( 'dominantsection' ) );
		$this->setTemplateSuffix( $parameters->getParameter( 'defaulttemplatesuffix' ) );
		$this->setTrimIncluded( $parameters->getParameter( 'includetrim' ) );
		$this->setTableSortColumn( $parameters->getParameter( 'tablesortcol' ) );
		$this->setTitleMaxLength( $parameters->getParameter( 'titlemaxlen' ) );
		$this->setEscapeLinks( $parameters->getParameter( 'escapelinks' ) );
		$this->setSectionSeparators( $parameters->getParameter( 'secseparators' ) );
		$this->setMultiSectionSeparators( $parameters->getParameter( 'multisecseparators' ) );
		$this->setIncludePageText( $parameters->getParameter( 'incpage' ) );
		$this->setIncludePageMaxLength( $parameters->getParameter( 'includemaxlen' ) );
		$this->setPageTextMatch( (array)$parameters->getParameter( 'seclabels' ) );
		$this->setPageTextMatchRegex( (array)$parameters->getParameter( 'seclabelsmatch' ) );
		$this->setPageTextMatchNotRegex( (array)$parameters->getParameter( 'seclabelsnotmatch' ) );
		$this->setIncludePageParsed( $parameters->getParameter( 'incparsed' ) );
		$this->parameters = $parameters;
		$this->parser = clone $parser;
	}

	/**
	 * Get a new List subclass based on user selection.
	 *
	 * @access	public
	 * @param	string	List style.
	 * @param	object	\DPL\Parameters
	 * @param	object	MediaWiki \Parser
	 * @return	object	Lister subclass.
	 */
	public static function newFromStyle( $style, \DPL\Parameters $parameters, \Parser $parser ) {
		$style = strtolower( $style );
		switch ( $style ) {
			case 'category':
				$class = 'CategoryList';
				break;
			case 'definition':
				$class = 'DefinitionList';
				break;
			case 'gallery':
				$class = 'GalleryList';
				break;
			case 'inline':
				$class = 'InlineList';
				break;
			case 'ordered':
				$class = 'OrderedList';
				break;
			case 'subpage':
				$class = 'SubPageList';
				break;
			default:
			case 'unordered':
				$class = 'UnorderedList';
				break;
			case 'userformat':
				$class = 'UserFormatList';
				break;
		}
		$class = '\DPL\Lister\\' . $class;

		return new $class( $parameters, $parser );
	}

	/**
	 * Get the \DPL\Parameters object this object was constructed with.
	 *
	 * @access	public
	 * @return	object	\DPL\Parameters
	 */
	public function getParameters() {
		return $this->parameters;
	}

	/**
	 * Set extra list attributes for header wraps.
	 *
	 * @access	public
	 * @param	string	Tag soup attributes, example: this="that" thing="no"
	 * @return	void
	 */
	public function setHeadListAttributes( $attributes ) {
		$this->headListAttributes = \Sanitizer::fixTagAttributes( $attributes, 'ul' );
	}

	/**
	 * Set extra item attributes for header items.
	 *
	 * @access	public
	 * @param	string	Tag soup attributes, example: this="that" thing="no"
	 * @return	void
	 */
	public function setHeadItemAttributes( $attributes ) {
		$this->headItemAttributes = \Sanitizer::fixTagAttributes( $attributes, 'li' );
	}

	/**
	 * Set extra list attributes.
	 *
	 * @access	public
	 * @param	string	Tag soup attributes, example: this="that" thing="no"
	 * @return	void
	 */
	public function setListAttributes( $attributes ) {
		$this->listAttributes = \Sanitizer::fixTagAttributes( $attributes, 'ul' );
	}

	/**
	 * Set extra item attributes.
	 *
	 * @access	public
	 * @param	string	Tag soup attributes, example: this="that" thing="no"
	 * @return	void
	 */
	public function setItemAttributes( $attributes ) {
		$this->itemAttributes = \Sanitizer::fixTagAttributes( $attributes, 'li' );
	}

	/**
	 * Set the count of items to trigger a section as dominant.
	 *
	 * @access	public
	 * @param	integer	Count
	 * @return	void
	 */
	public function setDominantSectionCount( $count = -1 ) {
		$this->dominantSectionCount = intval( $count );
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
	public function setTemplateSuffix( $suffix = '.default' ) {
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
	public function setTrimIncluded( $trim = false ) {
		$this->trimIncluded = boolval( $trim );
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
	 * @todo The naming of this parameter is weird and I am not sure what it does.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Escape
	 * @return	void
	 */
	public function setEscapeLinks( $escape = true ) {
		$this->escapeLinks = boolval( $escape );
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
	public function setTableSortColumn( $index = null ) {
		$this->tableSortColumn = $index === null ? null : intval( $index );
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
	public function setTitleMaxLength( $length = null ) {
		$this->titleMaxLength = $length === null ? null : intval( $length );
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
	public function setSectionSeparators( ?array $separators ) {
		$this->sectionSeparators = (array)$separators ?? [];
	}

	/**
	 * Set the separators that separate related sections of matched page text.
	 *
	 * @access	public
	 * @param	array	[Optional] Array of section separators.
	 * @return	void
	 */
	public function setMultiSectionSeparators( ?array $separators ) {
		$this->multiSectionSeparators = (array)$separators ?? [];
	}

	/**
	 * Set if wiki text should be included in output.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Parse
	 * @return	void
	 */
	public function setIncludePageText( $include = false ) {
		$this->includePageText = boolval( $include );
	}

	/**
	 * Set the maximum included page text length before truncating.
	 *
	 * @access	public
	 * @param	mixed	[Optional] Integer length or null to disable.
	 * @return	void
	 */
	public function setIncludePageMaxLength( $length = null ) {
		$this->includePageMaxLength = $length === null ? null : intval( $length );
	}

	/**
	 * Set the plain string text matching for page transclusion.
	 *
	 * @access	public
	 * @param	array	[Optional] Array of plain string matches.
	 * @return	void
	 */
	public function setPageTextMatch( array $pageTextMatch = [] ) {
		$this->pageTextMatch = (array)$pageTextMatch;
	}

	/**
	 * Set the regex text matching for page transclusion.
	 *
	 * @access	public
	 * @param	array	[Optional] Array of regexes.
	 * @return	void
	 */
	public function setPageTextMatchRegex( array $pageTextMatchRegex = [] ) {
		$this->pageTextMatchRegex = (array)$pageTextMatchRegex;
	}

	/**
	 * Set the not regex text matching for page transclusion.
	 *
	 * @access	public
	 * @param	array	[Optional] Array of regexes.
	 * @return	void
	 */
	public function setPageTextMatchNotRegex( array $pageTextMatchNotRegex = [] ) {
		$this->pageTextMatchNotRegex = (array)$pageTextMatchNotRegex;
	}

	/**
	 * Set if included wiki text should be parsed before being matched against.
	 *
	 * @access	public
	 * @param	boolean	[Optional] Parse
	 * @return	void
	 */
	public function setIncludePageParsed( $parse = false ) {
		$this->includePageParsed = boolval( $parse );
	}

	/**
	 * Shortcut to format all articles into a single formatted list.
	 *
	 * @access	public
	 * @param	array	List of \DPL\Article
	 * @return	string	Formatted list.
	 */
	public function format( $articles ) {
		return $this->formatList( $articles, 0, count( $articles ) );
	}

	/**
	 * Format a list of articles into a singular list.
	 *
	 * @access	public
	 * @param	array	List of \DPL\Article
	 * @param	integer	Start position of the array to process.
	 * @param	integer	Total objects from the array to process.
	 * @return	string	Formatted list.
	 */
	public function formatList( $articles, $start, $count ) {
		$filteredCount = 0;
		$items = [];
		for ( $i = $start; $i < $start + $count; $i++ ) {
			$article = $articles[$i];
			if ( empty( $article ) || empty( $article->mTitle ) ) {
				continue;
			}

			$pageText = null;
			if ( $this->includePageText ) {
				$pageText = $this->transcludePage( $article, $filteredCount );
			} else {
				$filteredCount++;
			}

			$this->rowCount = $filteredCount;

			$items[] = $this->formatItem( $article, $pageText );
		}

		$this->rowCount = $filteredCount;

		return $this->getListStart() . $this->implodeItems( $items ) . $this->listEnd;
	}

	/**
	 * Format a single item.
	 *
	 * @access	public
	 * @param	object	DPL\Article
	 * @param	string	[Optional] Page text to include.
	 * @return	string	Item HTML
	 */
	public function formatItem( Article $article, $pageText = null ) {
		global $wgLang;

		$item = '';

		// DPL Article, not MediaWiki.
		$date = $article->getDate();
		if ( $date !== null ) {
			$item .= $date . ' ';
			if ( $article->mRevision !== null ) {
				$item .= '[{{fullurl:' . $article->mTitle . '|oldid=' . $article->mRevision . '}} ' . htmlspecialchars( $article->mTitle ) . ']';
			} else {
				$item .= $article->mLink;
			}
		} else {
			// output the link to the article
			$item .= $article->mLink;
		}

		if ( $article->mSize != null ) {
			$byte = 'B';
			$pageLength = $wgLang->formatNum( $article->mSize );
			$item .= " [{$pageLength} {$byte}]";
		}

		if ( $article->mCounter !== null ) {
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();

			$item .= ' ' . $contLang->getDirMark() . '(' . wfMessage( 'hitcounters-nviews', $wgLang->formatNum( $article->mCounter ) )->escaped() . ')';
		}

		if ( $article->mUserLink !== null ) {
			$item .= ' . . [[User:' . $article->mUser . '|' . $article->mUser . ']]';
			if ( $article->mComment != '' ) {
				$item .= ' { ' . $article->mComment . ' }';
			}
		}

		if ( $article->mContributor !== null ) {
			$item .= ' . . [[User:' . $article->mContributor . '|' . $article->mContributor . " $article->mContrib]]";
		}

		if ( !empty( $article->mCategoryLinks ) ) {
			$item .= ' . . <small>' . wfMessage( 'categories' ) . ': ' . implode( ' | ', $article->mCategoryLinks ) . '</small>';
		}

		if ( $this->getParameters()->getParameter( 'addexternallink' ) && $article->mExternalLink !== null ) {
			$item .= ' → ' . $article->mExternalLink;
		}

		if ( $pageText !== null ) {
			//Include parsed/processed wiki markup content after each item before the closing tag.
			$item .= $pageText;
		}

		$item = $this->getItemStart() . $item . $this->getItemEnd();

		$item = $this->replaceTagParameters( $item, $article );

		return $item;
	}

	/**
	 * Return $this->headListStart with attributes replaced.
	 *
	 * @access	public
	 * @return	string	Head List Start
	 */
	public function getHeadListStart() {
		return sprintf( $this->headListStart, $this->headListAttributes );
	}

	/**
	 * Return $this->headItemStart with attributes replaced.
	 *
	 * @access	public
	 * @return	string	Head Item Start
	 */
	public function getHeadItemStart() {
		return sprintf( $this->headItemStart, $this->headItemAttributes );
	}

	/**
	 * Return $this->headItemStart with attributes replaced.
	 *
	 * @access	public
	 * @return	string	Head Item End
	 */
	public function getHeadItemEnd() {
		return $this->headItemEnd;
	}

	/**
	 * Return $this->listStart with attributes replaced.
	 *
	 * @access	public
	 * @return	string	List Start
	 */
	public function getListStart() {
		return sprintf( $this->listStart, $this->listAttributes );
	}

	/**
	 * Return $this->itemStart with attributes replaced.
	 *
	 * @access	public
	 * @return	string	Item Start
	 */
	public function getItemStart() {
		return sprintf( $this->itemStart, $this->itemAttributes );
	}

	/**
	 * Return $this->itemEnd with attributes replaced.
	 *
	 * @access	public
	 * @return	string	Item End
	 */
	public function getItemEnd() {
		return $this->itemEnd;
	}

	/**
	 * Join together items after being processed by formatItem().
	 *
	 * @protected
	 * @param	array	Items as formatted by formatItem().
	 * @return	string	Imploded items.
	 */
	protected function implodeItems( $items ) {
		return implode( '', $items );
	}

	/**
	 * Replace user tag parameters.
	 *
	 * @protected
	 * @param	string	Text to perform replacements on.
	 * @param	object	\DPL\Article
	 * @return	string	Text with replacements performed.
	 */
	protected function replaceTagParameters( $tag, Article $article ) {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();

		$namespaces = $contLang->getNamespaces();

		if ( strpos( $tag, '%' ) === false ) {
			return $tag;
		}

		$imageUrl = $this->parseImageUrlWithPath( $article );

		$pagename = $article->mTitle->getPrefixedText();
		if ( $this->getEscapeLinks() && ( $article->mNamespace == NS_CATEGORY || $article->mNamespace == NS_FILE ) ) {
			// links to categories or images need an additional ":"
			$pagename = ':' . $pagename;
		}

		$tag = str_replace( '%PAGE%', $pagename, $tag );
		$tag = str_replace( '%PAGEID%', $article->mID, $tag );
		$tag = str_replace( '%NAMESPACE%', $namespaces[$article->mNamespace], $tag );
		$tag = str_replace( '%IMAGE%', $imageUrl, $tag );
		$tag = str_replace( '%EXTERNALLINK%', $article->mExternalLink, $tag );
		$tag = str_replace( '%EDITSUMMARY%', $article->mComment, $tag );

		$title = $article->mTitle->getText();
		$replaceInTitle = $this->getParameters()->getParameter( 'replaceintitle' );
		if ( is_array( $replaceInTitle ) && count( $replaceInTitle ) === 2 ) {
			$title = preg_replace( $replaceInTitle[0], $replaceInTitle[1], $title );
		}
		$titleMaxLength = $this->getTitleMaxLength();
		if ( $titleMaxLength !== null && ( strlen( $title ) > $titleMaxLength ) ) {
			$title = substr( $title, 0, $titleMaxLength ) . '...';
		}
		$tag = str_replace( '%TITLE%', $title, $tag );

		$tag = str_replace( '%COUNT%', $article->mCounter, $tag );
		$tag = str_replace( '%COUNTFS%', floor( log( $article->mCounter ) * 0.7 ), $tag );
		$tag = str_replace( '%COUNTFS2%', floor( sqrt( log( $article->mCounter ) ) ), $tag );
		$tag = str_replace( '%SIZE%', $article->mSize, $tag );
		$tag = str_replace( '%SIZEFS%', floor( sqrt( log( $article->mSize ) ) * 2.5 - 5 ), $tag );
		$tag = str_replace( '%DATE%', $article->getDate(), $tag );
		$tag = str_replace( '%REVISION%', $article->mRevision, $tag );
		$tag = str_replace( '%CONTRIBUTION%', $article->mContribution, $tag );
		$tag = str_replace( '%CONTRIB%', $article->mContrib, $tag );
		$tag = str_replace( '%CONTRIBUTOR%', $article->mContributor, $tag );
		$tag = str_replace( '%USER%', $article->mUser, $tag );

		if ( $article->mSelTitle != null ) {
			if ( $article->mSelNamespace == 0 ) {
				$tag = str_replace( '%PAGESEL%', str_replace( '_', ' ', $article->mSelTitle ), $tag );
			} else {
				$tag = str_replace( '%PAGESEL%', $namespaces[$article->mSelNamespace] . ':' . str_replace( '_', ' ', $article->mSelTitle ), $tag );
			}
		}
		$tag = str_replace( '%IMAGESEL%', str_replace( '_', ' ', $article->mImageSelTitle ), $tag );

		$tag = $this->replaceTagCategory( $tag, $article );

		return $tag;
	}

	/**
	 * Replace user tag parameters for categories.
	 *
	 * @protected
	 * @param	string	Text to perform replacements on.
	 * @param	object	\DPL\Article
	 * @return	string	Text with replacements performed.
	 */
	protected function replaceTagCategory( $tag, Article $article ) {
		if ( !empty( $article->mCategoryLinks ) ) {
			$tag = str_replace( '%CATLIST%', implode( ', ', $article->mCategoryLinks ), $tag );
			$tag = str_replace( '%CATBULLETS%', '* ' . implode( "\n* ", $article->mCategoryLinks ), $tag );
			$tag = str_replace( '%CATNAMES%', implode( ', ', $article->mCategoryTexts ), $tag );
		} else {
			$tag = str_replace( '%CATLIST%', '', $tag );
			$tag = str_replace( '%CATBULLETS%', '', $tag );
			$tag = str_replace( '%CATNAMES%', '', $tag );
		}

		return $tag;
	}

	/**
	 * Replace the %NR%(current article sequence number) in text.
	 *
	 * @protected
	 * @param	string	Text to perform replacements on.
	 * @param	integer	The current article sequence number (starting from 1).
	 * @return	string	Text with replacements performed.
	 */
	protected function replaceTagCount( $tag, $nr ) {
		return str_replace( '%NR%', $nr, $tag );
	}

	//

	/**
	 * Format one single item of an entry in the output list (i.e. one occurence of one item from the include parameter).
	 * @todo I am not exactly sure how this function differs from replaceTagParameters().  It has something to do with table row formatting.  --Alexia
	 *
	 * @private
	 * @param	array	String pieces to perform replacements on.
	 * @param	mixed	Index of the table row position.
	 * @param	object	\DPL\Article
	 * @return	void
	 */
	private function replaceTagTableRow( &$pieces, $s, Article $article ) {
		$tableFormat = $this->getParameters()->getParameter( 'tablerow' );
		$firstCall = true;
		foreach ( $pieces as $key => $val ) {
			if ( isset( $tableFormat[$s] ) ) {
				if ( $s == 0 || $firstCall ) {
					$pieces[$key] = str_replace( '%%', $val, $tableFormat[$s] );
				} else {
					$n = strpos( $tableFormat[$s], '|' );
					if ( $n === false || !( strpos( substr( $tableFormat[$s], 0, $n ), '{' ) === false ) || !( strpos( substr( $tableFormat[$s], 0, $n ), '[' ) === false ) ) {
						$pieces[$key] = str_replace( '%%', $val, $tableFormat[$s] );
					} else {
						$pieces[$key] = str_replace( '%%', $val, substr( $tableFormat[$s], $n + 1 ) );
					}
				}
				$pieces[$key] = str_replace( '%IMAGE%', $this->parseImageUrlWithPath( $val ), $pieces[$key] );
				$pieces[$key] = str_replace( '%PAGE%', $article->mTitle->getPrefixedText(), $pieces[$key] );

				$pieces[$key] = $this->replaceTagCategory( $pieces[$key], $article );
			}
			$firstCall = false;
		}
	}

	/**
	 * Format one single template argument of one occurence of one item from the include parameter.  This is called via a backlink from LST::includeTemplate().
	 * @todo Again, another poorly documented function with vague functionality.  --Alexia
	 *
	 * @access	public
	 * @param	string	Argument to parse and replace.
	 * @param	mixed	Index of the table row position.
	 * @param	mixed	Other part of the index of the table row position?
	 * @param	boolean	Is this the first time this function was called in this context?
	 * @param	integer	Maximum text length allowed.
	 * @param	object	\DPL\Article
	 * @return	strig	Formatted text.
	 */
	public function formatTemplateArg( $arg, $s, $argNr, $firstCall, $maxLength, Article $article ) {
		$tableFormat = $this->getParameters()->getParameter( 'tablerow' );
		// we could try to format fields differently within the first call of a template
		// currently we do not make such a difference

		// if the result starts with a '-' we add a leading space; thus we avoid a misinterpretation of |- as
		// a start of a new row (wiki table syntax)
		if ( array_key_exists( "$s.$argNr", $tableFormat ) ) {
			$n = -1;
			if ( $s >= 1 && $argNr == 0 && !$firstCall ) {
				$n = strpos( $tableFormat["$s.$argNr"], '|' );
				if ( $n === false || !( strpos( substr( $tableFormat["$s.$argNr"], 0, $n ), '{' ) === false ) || !( strpos( substr( $tableFormat["$s.$argNr"], 0, $n ), '[' ) === false ) ) {
					$n = -1;
				}
			}
			$result = str_replace( '%%', $arg, substr( $tableFormat["$s.$argNr"], $n + 1 ) );
			$result = str_replace( '%PAGE%', $article->mTitle->getPrefixedText(), $result );
			$result = str_replace( '%IMAGE%', $this->parseImageUrlWithPath( $arg ), $result ); //@TODO: This just blindly passes the argument through hoping it is an image.  --Alexia
			$result = $this->cutAt( $maxLength, $result );
			if ( strlen( $result ) > 0 && $result[0] == '-' ) {
				return ' ' . $result;
			} else {
				return $result;
			}
		}
		$result = $this->cutAt( $maxLength, $arg );
		if ( strlen( $result ) > 0 && $result[0] == '-' ) {
			return ' ' . $result;
		} else {
			return $result;
		}
	}

	/**
	 * Truncate a portion of wikitext so that ..
	 * ... it is not larger that $lim characters
	 * ... it is balanced in terms of braces, brackets and tags
	 * ... can be used as content of a wikitable field without spoiling the whole surrounding wikitext structure
	 *
	 * @private
	 * @param  $lim     limit of character count for the result
	 * @param  $text    the wikitext to be truncated
	 * @return the truncated text; note that in some cases it may be slightly longer than the given limit
	 *         if the text is alread shorter than the limit or if the limit is negative, the text
	 *         will be returned without any checks for balance of tags
	 */
	private function cutAt( $lim, $text ) {
		if ( $lim < 0 ) {
			return $text;
		}
		return LST::limitTranscludedText( $text, $lim );
	}

	/**
	 * Prepends an image name with its hash path.
	 *
	 * @protected
	 * @param 	mixed	\DPL\Article or string image name of the image (may start with Image: or File:).
	 * @return	string	Image URL
	 */
	protected function parseImageUrlWithPath( $article ) {
		$imageUrl = '';
		if ( $article instanceof \DPL\Article ) {
			if ( $article->mNamespace == NS_FILE ) {
				// calculate URL for existing images
				// $img = Image::newFromName($article->mTitle->getText());
				$img = wfFindFile( \Title::makeTitle( NS_FILE, $article->mTitle->getText() ) );
				if ( $img && $img->exists() ) {
					$imageUrl = $img->getURL();
				} else {
					$fileTitle = \Title::makeTitleSafe( NS_FILE, $article->mTitle->getDBKey() );
					$imageUrl = \RepoGroup::singleton()->getLocalRepo()->newFile( $fileTitle )->getPath();
				}
			}
		} else {
			$title = \Title::newfromText( 'File:' . $article );
			if ( $title !== null ) {
				$fileTitle   = \Title::makeTitleSafe( 6, $title->getDBKey() );
				$imageUrl = \RepoGroup::singleton()->getLocalRepo()->newFile( $fileTitle )->getPath();
			}
		}

		//@TODO: Check this preg_replace.  Probably only works for stock file repositories.  --Alexia
		$imageUrl = preg_replace( '~^.*images/(.*)~', '\1', $imageUrl );

		return $imageUrl;
	}

	/**
	 * Transclude a page contents.
	 *
	 * @access	public
	 * @param	object	\DPL\Article
	 * @param	integer	Filtered Article Count
	 * @return	string	Page Text
	 */
	public function transcludePage( Article $article, &$filteredCount ) {
		$matchFailed = false;
		if ( empty( $this->pageTextMatch ) || $this->pageTextMatch[0] == '*' ) { // include whole article
			$title = $article->mTitle->getPrefixedText();
			if ( $this->getStyle() == self::LIST_USERFORMAT ) {
				$pageText = '';
			} else {
				$pageText = '<br/>';
			}
			$text = $this->parser->fetchTemplate( \Title::newFromText( $title ) );
			if ( ( count( $this->pageTextMatchRegex ) <= 0 || $this->pageTextMatchRegex[0] == '' || !preg_match( $this->pageTextMatchRegex[0], $text ) == false ) && ( count( $this->pageTextMatchNotRegex ) <= 0 || $this->pageTextMatchNotRegex[0] == '' || preg_match( $this->pageTextMatchNotRegex[0], $text ) == false ) ) {
				if ( $this->includePageMaxLength > 0 && ( strlen( $text ) > $this->includePageMaxLength ) ) {
					$text = LST::limitTranscludedText( $text, $this->includePageMaxLength, ' [[' . $title . '|..→]]' );
				}
				$filteredCount = $filteredCount + 1;

				// update article if include=* and updaterules are given
				$updateRules = $this->getParameters()->getParameter( 'updaterules' );
				$deleteRules = $this->getParameters()->getParameter( 'deleterules' );
				if ( !empty( $updateRules ) ) {
					$ruleOutput = UpdateArticle::updateArticleByRule( $title, $text, $updateRules );
					// append update message to output
					$pageText .= $ruleOutput;
				} elseif ( !empty( $deleteRules ) ) {
					$ruleOutput = UpdateArticle::deleteArticleByRule( $title, $text, $deleteRules );
					// append delete message to output
					$pageText .= $ruleOutput;
				} else {
					// append full text to output
					if ( is_array( $this->sectionSeparators ) && array_key_exists( '0', $this->sectionSeparators ) ) {
						$pageText .= $this->replaceTagCount( $this->sectionSeparators[0], $filteredCount );
						$pieces = [
							0 => $text
						];
						$this->replaceTagTableRow( $pieces, 0, $article );
						$pageText .= $pieces[0];
					} else {
						$pageText .= $text;
					}
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

			foreach ( $this->pageTextMatch as $s => $sSecLabel ) {
				$sSecLabel = trim( $sSecLabel );
				if ( $sSecLabel == '' ) {
					break;
				}
				// if sections are identified by number we have a % at the beginning
				if ( $sSecLabel[0] == '%' ) {
					$sSecLabel = '#' . $sSecLabel;
				}

				$maxLength = -1;
				if ( $sSecLabel == '-' ) {
					// '-' is used as a dummy parameter which will produce no output
					// if maxlen was 0 we suppress all output; note that for matching we used the full text
					$secPieces = [
						''
					];
					$this->replaceTagTableRow( $secPieces, $s, $article );
				} elseif ( $sSecLabel[0] != '{' ) {
					$limpos      = strpos( $sSecLabel, '[' );
					$cutLink     = 'default';
					$skipPattern = [];
					if ( $limpos > 0 && $sSecLabel[strlen( $sSecLabel ) - 1] == ']' ) {
						// regular expressions which define a skip pattern may precede the text
						$fmtSec = explode( '~', substr( $sSecLabel, $limpos + 1, strlen( $sSecLabel ) - $limpos - 2 ) );
						$sSecLabel = substr( $sSecLabel, 0, $limpos );
						$cutInfo = explode( " ", $fmtSec[count( $fmtSec ) - 1], 2 );
						$maxLength = intval( $cutInfo[0] );
						if ( array_key_exists( '1', $cutInfo ) ) {
							$cutLink = $cutInfo[1];
						}
						foreach ( $fmtSec as $skipKey => $skipPat ) {
							if ( $skipKey == count( $fmtSec ) - 1 ) {
								continue;
							}
							$skipPattern[] = $skipPat;
						}
					}
					if ( $maxLength < 0 ) {
						$maxLength = -1; // without valid limit include whole section
					}
				}

				// find out if the user specified an includematch / includenotmatch condition
				if ( is_array( $this->pageTextMatchRegex ) && count( $this->pageTextMatchRegex ) > $s && !empty( $this->pageTextMatchRegex[$s] ) ) {
					$mustMatch = $this->pageTextMatchRegex[$s];
				} else {
					$mustMatch = '';
				}
				if ( is_array( $this->pageTextMatchNotRegex ) && count( $this->pageTextMatchNotRegex ) > $s && !empty( $this->pageTextMatchNotRegex[$s] ) ) {
					$mustNotMatch = $this->pageTextMatchNotRegex[$s];
				} else {
					$mustNotMatch = '';
				}

				// if chapters are selected by number, text or regexp we get the heading from LST::includeHeading
				$sectionHeading[0] = '';
				if ( $sSecLabel == '-' ) {
					$secPiece[$s] = $secPieces[0];
				} elseif ( $sSecLabel[0] == '#' || $sSecLabel[0] == '@' ) {
					$sectionHeading[0] = substr( $sSecLabel, 1 );
					// Uses LST::includeHeading() from LabeledSectionTransclusion extension to include headings from the page
					$secPieces = LST::includeHeading( $this->parser, $article->mTitle->getPrefixedText(), substr( $sSecLabel, 1 ), '', $sectionHeading, false, $maxLength, $cutLink, $this->getTrimIncluded(), $skipPattern );
					if ( $mustMatch != '' || $mustNotMatch != '' ) {
						$secPiecesTmp = $secPieces;
						$offset       = 0;
						foreach ( $secPiecesTmp as $nr => $onePiece ) {
							if ( ( $mustMatch != '' && preg_match( $mustMatch, $onePiece ) == false ) || ( $mustNotMatch != '' && preg_match( $mustNotMatch, $onePiece ) != false ) ) {
								array_splice( $secPieces, $nr - $offset, 1 );
								$offset++;
							}
						}
					}
					// if maxlen was 0 we suppress all output; note that for matching we used the full text
					if ( $maxLength == 0 ) {
						$secPieces = [
							''
						];
					}

					$this->replaceTagTableRow( $secPieces, $s, $article );
					if ( !array_key_exists( 0, $secPieces ) ) {
						// avoid matching against a non-existing array element
						// and skip the article if there was a match condition
						if ( $mustMatch != '' || $mustNotMatch != '' ) {
							$matchFailed = true;
						}
						break;
					}
					$secPiece[$s] = $secPieces[0];
					for ( $sp = 1; $sp < count( $secPieces ); $sp++ ) {
						if ( isset( $this->multiSectionSeparators[$s] ) ) {
							$secPiece[$s] .= str_replace( '%SECTION%', $sectionHeading[$sp], $this->replaceTagCount( $this->multiSectionSeparators[$s], $filteredCount ) );
						}
						$secPiece[$s] .= $secPieces[$sp];
					}
					if ( $this->getDominantSectionCount() >= 0 && $s == $this->getDominantSectionCount() && count( $secPieces ) > 1 ) {
						$dominantPieces = $secPieces;
					}
					if ( ( $mustMatch != '' || $mustNotMatch != '' ) && count( $secPieces ) <= 0 ) {
						$matchFailed = true; // NOTHING MATCHED
						break;
					}

				} elseif ( $sSecLabel[0] == '{' ) {
					// Uses LST::includeTemplate() from LabeledSectionTransclusion extension to include templates from the page
					// primary syntax {template}suffix
					$template1 = trim( substr( $sSecLabel, 1, strpos( $sSecLabel, '}' ) - 1 ) );
					$template2 = trim( str_replace( '}', '', substr( $sSecLabel, 1 ) ) );
					// alternate syntax: {template|surrogate}
					if ( $template2 == $template1 && strpos( $template1, '|' ) > 0 ) {
						$template1 = preg_replace( '/\|.*/', '', $template1 );
						$template2 = preg_replace( '/^.+\|/', '', $template2 );
					}
					//Why the hell was defaultTemplateSuffix be passed all over the place for just fucking here?  --Alexia
					$secPieces    = LST::includeTemplate( $this->parser, $this, $s, $article, $template1, $template2, $template2 . $this->getTemplateSuffix(), $mustMatch, $mustNotMatch, $this->includePageParsed, implode( ', ', $article->mCategoryLinks ) );
					$secPiece[$s] = implode( isset( $this->multiSectionSeparators[$s] ) ? $this->replaceTagCount( $this->multiSectionSeparators[$s], $filteredCount ) : '', $secPieces );
					if ( $this->getDominantSectionCount() >= 0 && $s == $this->getDominantSectionCount() && count( $secPieces ) > 1 ) {
						$dominantPieces = $secPieces;
					}
					if ( ( $mustMatch != '' || $mustNotMatch != '' ) && count( $secPieces ) <= 1 && $secPieces[0] == '' ) {
						$matchFailed = true; // NOTHING MATCHED
						break;
					}
				} else {
					// Uses LST::includeSection() from LabeledSectionTransclusion extension to include labeled sections from the page
					$secPieces    = LST::includeSection( $this->parser, $article->mTitle->getPrefixedText(), $sSecLabel, '', false, $this->getTrimIncluded(), $skipPattern );
					$secPiece[$s] = implode( isset( $this->multiSectionSeparators[$s] ) ? $this->replaceTagCount( $this->multiSectionSeparators[$s], $filteredCount ) : '', $secPieces );
					if ( $this->getDominantSectionCount() >= 0 && $s == $this->getDominantSectionCount() && count( $secPieces ) > 1 ) {
						$dominantPieces = $secPieces;
					}
					if ( ( $mustMatch != '' && preg_match( $mustMatch, $secPiece[$s] ) == false ) || ( $mustNotMatch != '' && preg_match( $mustNotMatch, $secPiece[$s] ) != false ) ) {
						$matchFailed = true;
						break;
					}
				}

				// separator tags
				if ( is_array( $this->sectionSeparators ) && count( $this->sectionSeparators ) == 1 ) {
					// If there is only one separator tag use it always
					$septag[$s * 2] = str_replace( '%SECTION%', $sectionHeading[0], $this->replaceTagCount( $this->sectionSeparators[0], $filteredCount ) );
				} elseif ( isset( $this->sectionSeparators[$s * 2] ) ) {
					$septag[$s * 2] = str_replace( '%SECTION%', $sectionHeading[0], $this->replaceTagCount( $this->sectionSeparators[$s * 2], $filteredCount ) );
				} else {
					$septag[$s * 2] = '';
				}
				if ( isset( $this->sectionSeparators[$s * 2 + 1] ) ) {
					$septag[$s * 2 + 1] = str_replace( '%SECTION%', $sectionHeading[0], $this->replaceTagCount( $this->sectionSeparators[$s * 2 + 1], $filteredCount ) );
				} else {
					$septag[$s * 2 + 1] = '';
				}
			}

			// if there was a match condition on included contents which failed we skip the whole page
			if ( $matchFailed ) {
				return '';
			}
			$filteredCount = $filteredCount + 1;

			// assemble parts with separators
			$pageText = '';
			if ( $dominantPieces != false ) {
				foreach ( $dominantPieces as $dominantPiece ) {
					foreach ( $secPiece as $s => $piece ) {
						if ( $s == $this->getDominantSectionCount() ) {
							$pageText .= $this->joinSectionTagPieces( $dominantPiece, $septag[$s * 2], $septag[$s * 2 + 1] );
						} else {
							$pageText .= $this->joinSectionTagPieces( $piece, $septag[$s * 2], $septag[$s * 2 + 1] );
						}
					}
				}
			} else {
				foreach ( $secPiece as $s => $piece ) {
					$pageText .= $this->joinSectionTagPieces( $piece, $septag[$s * 2], $septag[$s * 2 + 1] );
				}
			}
		}
		return $pageText;
	}

	/**
	 * Wrap seciton pieces with start and end tags.
	 *
	 * @protected
	 * @param	string	Piece to be wrapped.
	 * @param	string	Text to prepend.
	 * @param	string	Text to append.
	 * @return	string	Wrapped text.
	 */
	protected function joinSectionTagPieces( $piece, $start, $end ) {
		return $start . $piece . $end;
	}

	/**
	 * Get the count of listed items after formatting, transcluding, and such.
	 *
	 * @access	public
	 * @return	integer	Row Count
	 */
	public function getRowCount() {
		return $this->rowCount;
	}
}
