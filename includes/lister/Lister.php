<?php

namespace DPL\Lister;

use DPL\Article;
use DPL\LST;
use DPL\Parameters;
use DPL\UpdateArticle;
use MediaWiki\MediaWikiServices;
use Parser;
use Sanitizer;
use Title;

class Lister {
	public const LIST_DEFINITION = 1;

	public const LIST_GALLERY = 2;

	public const LIST_HEADING = 3;

	public const LIST_INLINE = 4;

	public const LIST_ORDERED = 5;

	public const LIST_UNORDERED = 6;

	public const LIST_CATEGORY = 7;

	public const LIST_USERFORMAT = 8;

	/**
	 * Listing style for this class.
	 *
	 * @var int|null
	 */
	public $style = null;

	/**
	 * Heading List Start
	 * Use %s for attribute placement. Example: <div%s>
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
	 * Use %s for attribute placement. Example: <div%s>
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
	 * Use %s for attribute placement. Example: <div%s>
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
	 * Use %s for attribute placement. Example: <div%s>
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
	 * @var string
	 */
	public $headListAttributes = '';

	/**
	 * Extra head item HTML attributes.
	 *
	 * @var string
	 */
	public $headItemAttributes = '';

	/**
	 * Extra list HTML attributes.
	 *
	 * @var string
	 */
	public $listAttributes = '';

	/**
	 * Extra item HTML attributes.
	 *
	 * @var string
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
	 * @var int|null
	 */
	protected $tableSortColumn = null;

	/**
	 * @var string|null
	 */
	protected $tableSortMethod = null;

	/**
	 * Maximum title length.
	 *
	 * @var int|null
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
	 * @var int|null
	 */
	protected $includePageMaxLength = null;

	/**
	 * Array of plain text matches for page transclusion. (include)
	 *
	 * @var array
	 */
	protected $pageTextMatch;

	/**
	 * Array of regex text matches for page transclusion. (includematch)
	 *
	 * @var array
	 */
	protected $pageTextMatchRegex;

	/**
	 * Array of not regex text matches for page transclusion. (includenotmatch)
	 *
	 * @var array
	 */
	protected $pageTextMatchNotRegex;

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
	 * Parameters
	 *
	 * @var Parameters
	 */
	protected $parameters;

	/**
	 * Parser
	 *
	 * @var Parser
	 */
	protected $parser;

	/**
	 * @param Parameters $parameters
	 * @param Parser $parser
	 */
	public function __construct( Parameters $parameters, Parser $parser ) {
		$this->setHeadListAttributes( $parameters->getParameter( 'hlistattr' ) );
		$this->setHeadItemAttributes( $parameters->getParameter( 'hitemattr' ) );
		$this->setListAttributes( $parameters->getParameter( 'listattr' ) );
		$this->setItemAttributes( $parameters->getParameter( 'itemattr' ) );
		$this->setDominantSectionCount( $parameters->getParameter( 'dominantsection' ) );
		$this->setTemplateSuffix( $parameters->getParameter( 'defaulttemplatesuffix' ) );
		$this->setTrimIncluded( $parameters->getParameter( 'includetrim' ) );
		$this->setTableSortColumn( $parameters->getParameter( 'tablesortcol' ) );
		$this->setTableSortMethod( $parameters->getParameter( 'tablesortmethod' ) );
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
	 * @param string $style
	 * @param Parameters $parameters
	 * @param Parser $parser
	 * @return mixed
	 */
	public static function newFromStyle( $style, Parameters $parameters, Parser $parser ) {
		$style = strtolower( $style );

		switch ( $style ) {
			case 'category':
				$class = CategoryList::class;
				break;
			case 'definition':
				$class = DefinitionList::class;
				break;
			case 'gallery':
				$class = GalleryList::class;
				break;
			case 'inline':
				$class = InlineList::class;
				break;
			case 'ordered':
				$class = OrderedList::class;
				break;
			case 'subpage':
				$class = SubPageList::class;
				break;
			default:
			case 'unordered':
				$class = UnorderedList::class;
				break;
			case 'userformat':
				$class = UserFormatList::class;
				break;
		}

		return new $class( $parameters, $parser );
	}

	/**
	 * Get the Parameters object this object was constructed with.
	 *
	 * @return Parameters
	 */
	public function getParameters() {
		return $this->parameters;
	}

	/**
	 * Set extra list attributes for header wraps.
	 *
	 * @param string $attributes
	 */
	public function setHeadListAttributes( $attributes ) {
		$this->headListAttributes = Sanitizer::fixTagAttributes( $attributes, 'ul' );
	}

	/**
	 * Set extra item attributes for header items.
	 *
	 * @param string $attributes
	 */
	public function setHeadItemAttributes( $attributes ) {
		$this->headItemAttributes = Sanitizer::fixTagAttributes( $attributes, 'li' );
	}

	/**
	 * Set extra list attributes.
	 *
	 * @param string $attributes
	 */
	public function setListAttributes( $attributes ) {
		$this->listAttributes = Sanitizer::fixTagAttributes( $attributes, 'ul' );
	}

	/**
	 * Set extra item attributes.
	 *
	 * @param string $attributes
	 */
	public function setItemAttributes( $attributes ) {
		$this->itemAttributes = Sanitizer::fixTagAttributes( $attributes, 'li' );
	}

	/**
	 * Set the count of items to trigger a section as dominant.
	 *
	 * @param int $count
	 */
	public function setDominantSectionCount( $count = -1 ) {
		$this->dominantSectionCount = intval( $count );
	}

	/**
	 * Get the count of items to trigger a section as dominant.
	 *
	 * @return int
	 */
	public function getDominantSectionCount() {
		return $this->dominantSectionCount;
	}

	/**
	 * Return the list style.
	 *
	 * @return int
	 */
	public function getStyle() {
		return $this->style;
	}

	/**
	 * Set the template suffix for whatever the hell uses it.
	 *
	 * @param string $suffix
	 */
	public function setTemplateSuffix( $suffix = '.default' ) {
		$this->templateSuffix = $suffix;
	}

	/**
	 * Get the template suffix for whatever the hell uses it.
	 *
	 * @return string
	 */
	public function getTemplateSuffix() {
		return $this->templateSuffix;
	}

	/**
	 * Set if included wiki text should be trimmed.
	 *
	 * @param bool $trim
	 */
	public function setTrimIncluded( $trim = false ) {
		$this->trimIncluded = boolval( $trim );
	}

	/**
	 * Get if included wiki text should be trimmed.
	 *
	 * @return bool
	 */
	public function getTrimIncluded() {
		return $this->trimIncluded;
	}

	/**
	 * Set if links should be escaped?
	 * @todo The naming of this parameter is weird and I am not sure what it does.
	 *
	 * @param bool $escape
	 */
	public function setEscapeLinks( $escape = true ) {
		$this->escapeLinks = boolval( $escape );
	}

	/**
	 * Get if links should be escaped.
	 *
	 * @return bool
	 */
	public function getEscapeLinks() {
		return $this->escapeLinks;
	}

	/**
	 * Set the index of the table column to sort by.
	 *
	 * @param int|null $index
	 */
	public function setTableSortColumn( $index = null ) {
		$this->tableSortColumn = $index === null ? null : intval( $index );
	}

	/**
	 * Get the index of the table column to sort by.
	 *
	 * @return int|null
	 */
	public function getTableSortColumn() {
		return $this->tableSortColumn;
	}

	/**
	 * Set the algorithm for table sorting
	 *
	 * @param string|null $method
	 */
	public function setTableSortMethod( $method = null ) {
		$this->tableSortMethod = $method === null ? 'standard' : $method;
	}

	/**
	 * Get the algorithm for table sorting
	 *
	 * @return string
	 */
	public function getTableSortMethod() {
		return $this->tableSortMethod;
	}

	/**
	 * Set the maximum title length for display.
	 *
	 * @param int|null $length
	 */
	public function setTitleMaxLength( $length = null ) {
		$this->titleMaxLength = $length === null ? null : intval( $length );
	}

	/**
	 * Get the maximum title length for display.
	 *
	 * @return int|null
	 */
	public function getTitleMaxLength() {
		return $this->titleMaxLength;
	}

	/**
	 * Set the separators that separate sections of matched page text.
	 *
	 * @param ?array $separators
	 */
	public function setSectionSeparators( ?array $separators ) {
		$this->sectionSeparators = $separators ?? [];
	}

	/**
	 * Set the separators that separate related sections of matched page text.
	 *
	 * @param ?array $separators
	 */
	public function setMultiSectionSeparators( ?array $separators ) {
		$this->multiSectionSeparators = $separators ?? [];
	}

	/**
	 * Set if wiki text should be included in output.
	 *
	 * @param bool $include
	 */
	public function setIncludePageText( $include = false ) {
		$this->includePageText = boolval( $include );
	}

	/**
	 * Set the maximum included page text length before truncating.
	 *
	 * @param int|null $length
	 */
	public function setIncludePageMaxLength( $length = null ) {
		$this->includePageMaxLength = $length === null ? null : intval( $length );
	}

	/**
	 * Set the plain string text matching for page transclusion.
	 *
	 * @param array	$pageTextMatch
	 */
	public function setPageTextMatch( array $pageTextMatch = [] ) {
		$this->pageTextMatch = $pageTextMatch;
	}

	/**
	 * Set the regex text matching for page transclusion.
	 *
	 * @param array	$pageTextMatchRegex
	 */
	public function setPageTextMatchRegex( array $pageTextMatchRegex = [] ) {
		$this->pageTextMatchRegex = $pageTextMatchRegex;
	}

	/**
	 * Set the not regex text matching for page transclusion.
	 *
	 * @param array	$pageTextMatchNotRegex
	 */
	public function setPageTextMatchNotRegex( array $pageTextMatchNotRegex = [] ) {
		$this->pageTextMatchNotRegex = $pageTextMatchNotRegex;
	}

	/**
	 * Set if included wiki text should be parsed before being matched against.
	 *
	 * @param bool $parse
	 */
	public function setIncludePageParsed( $parse = false ) {
		$this->includePageParsed = boolval( $parse );
	}

	/**
	 * Shortcut to format all articles into a single formatted list.
	 *
	 * @param array $articles
	 * @return string
	 */
	public function format( $articles ) {
		return $this->formatList( $articles, 0, count( $articles ) );
	}

	/**
	 * Format a list of articles into a singular list.
	 *
	 * @param array $articles
	 * @param int $start
	 * @param int $count
	 * @return string
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
	 * @param Article $article
	 * @param string|null $pageText
	 * @return string
	 */
	public function formatItem( Article $article, $pageText = null ) {
		global $wgLang;

		$item = '';

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
			// Include parsed/processed wiki markup content after each item before the closing tag.
			$item .= $pageText;
		}

		$item = $this->getItemStart() . $item . $this->getItemEnd();

		$item = $this->replaceTagParameters( $item, $article );

		return $item;
	}

	/**
	 * Return $this->headListStart with attributes replaced.
	 *
	 * @return string
	 */
	public function getHeadListStart() {
		return sprintf( $this->headListStart, $this->headListAttributes );
	}

	/**
	 * Return $this->headItemStart with attributes replaced.
	 *
	 * @return string
	 */
	public function getHeadItemStart() {
		return sprintf( $this->headItemStart, $this->headItemAttributes );
	}

	/**
	 * Return $this->headItemStart with attributes replaced.
	 *
	 * @return string
	 */
	public function getHeadItemEnd() {
		return $this->headItemEnd;
	}

	/**
	 * Return $this->listStart with attributes replaced.
	 *
	 * @return string
	 */
	public function getListStart() {
		return sprintf( $this->listStart, $this->listAttributes );
	}

	/**
	 * Return $this->itemStart with attributes replaced.
	 *
	 * @return string
	 */
	public function getItemStart() {
		return sprintf( $this->itemStart, $this->itemAttributes );
	}

	/**
	 * Return $this->itemEnd with attributes replaced.
	 *
	 * @return string
	 */
	public function getItemEnd() {
		return $this->itemEnd;
	}

	/**
	 * Join together items after being processed by formatItem().
	 *
	 * @param array $items
	 * @return string
	 */
	protected function implodeItems( $items ) {
		return implode( '', $items );
	}

	/**
	 * Replace user tag parameters.
	 *
	 * @param string $tag
	 * @param Article $article
	 * @return string
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
		$tag = str_replace( '%PAGEID%', (string)$article->mID, $tag );
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

		$tag = str_replace( '%COUNT%', (string)$article->mCounter, $tag );
		$tag = str_replace( '%COUNTFS%', (string)( floor( log( $article->mCounter ) * 0.7 ) ), $tag );
		$tag = str_replace( '%COUNTFS2%', (string)( floor( sqrt( log( $article->mCounter ) ) ) ), $tag );
		$tag = str_replace( '%SIZE%', (string)$article->mSize, $tag );
		$tag = str_replace( '%SIZEFS%', (string)( floor( sqrt( log( $article->mSize ) ) * 2.5 - 5 ) ), $tag );
		$tag = str_replace( '%DATE%', $article->getDate(), $tag );
		$tag = str_replace( '%REVISION%', (string)$article->mRevision, $tag );
		$tag = str_replace( '%CONTRIBUTION%', (string)$article->mContribution, $tag );
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
	 * @param string $tag
	 * @param Article $article
	 * @return string
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
	 * @param string $tag
	 * @param int $nr
	 * @return string
	 */
	protected function replaceTagCount( $tag, $nr ) {
		return str_replace( '%NR%', (string)$nr, $tag );
	}

	/**
	 * Format one single item of an entry in the output list (i.e. one occurence of one item from the include parameter).
	 *
	 * @param array &$pieces
	 * @param mixed $s Index of the table row position.
	 * @param Article $article
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
	 * Format one single template argument of one occurence of one item from the include parameter. This is called via a backlink from LST::includeTemplate().
	 *
	 * @param string $arg
	 * @param mixed	$s Index of the table row position.
	 * @param mixed $argNr Other part of the index of the table row position?
	 * @param bool $firstCall
	 * @param int $maxLength
	 * @param Article $article
	 * @return string
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

			// @TODO: This just blindly passes the argument through hoping it is an image.
			$result = str_replace( '%IMAGE%', $this->parseImageUrlWithPath( $arg ), $result );
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
	 * @param int $lim
	 * @param string $text
	 *
	 * @return string the truncated text; note that in some cases it may be slightly longer than the given limit
	 * if the text is alread shorter than the limit or if the limit is negative, the text
	 * will be returned without any checks for balance of tags
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
	 * @param Article|string $article
	 * @return string
	 */
	protected function parseImageUrlWithPath( $article ) {
		$repoGroup = MediaWikiServices::getInstance()->getRepoGroup();

		$imageUrl = '';
		if ( $article instanceof Article ) {
			if ( $article->mNamespace == NS_FILE ) {
				// calculate URL for existing images
				// $img = Image::newFromName( $article->mTitle->getText() );

				$img = $repoGroup->findFile( Title::makeTitle( NS_FILE, $article->mTitle->getText() ) );
				if ( $img && $img->exists() ) {
					$imageUrl = $img->getURL();
				} else {
					$fileTitle = Title::makeTitleSafe( NS_FILE, $article->mTitle->getDBKey() );
					$imageUrl = $repoGroup->getLocalRepo()->newFile( $fileTitle )->getPath();
				}
			}
		} else {
			$title = Title::newfromText( 'File:' . $article );

			if ( $title !== null ) {
				$fileTitle = Title::makeTitleSafe( 6, $title->getDBKey() );

				$imageUrl = $repoGroup->getLocalRepo()->newFile( $fileTitle )->getPath();
			}
		}

		// @TODO: Check this preg_replace. Probably only works for stock file repositories.
		$imageUrl = preg_replace( '~^.*images/(.*)~', '\1', $imageUrl );

		return $imageUrl;
	}

	/**
	 * Transclude a page contents.
	 *
	 * @param Article $article
	 * @param int &$filteredCount
	 * @return string
	 */
	public function transcludePage( Article $article, &$filteredCount ) {
		$matchFailed = false;
		$septag = [];

		if ( empty( $this->pageTextMatch ) || $this->pageTextMatch[0] == '*' ) { // include whole article
			$title = $article->mTitle->getPrefixedText();

			if ( $this->getStyle() == self::LIST_USERFORMAT ) {
				$pageText = '';
			} else {
				$pageText = '<br/>';
			}

			$text = $this->parser->fetchTemplateAndTitle( Title::newFromText( $title ) )[0];
			if ( ( count( $this->pageTextMatchRegex ) <= 0 || $this->pageTextMatchRegex[0] == '' || !( !preg_match( $this->pageTextMatchRegex[0], $text ) ) ) && ( count( $this->pageTextMatchNotRegex ) <= 0 || $this->pageTextMatchNotRegex[0] == '' || preg_match( $this->pageTextMatchNotRegex[0], $text ) == false ) ) {
				if ( $this->includePageMaxLength > 0 && ( strlen( $text ) > $this->includePageMaxLength ) ) {
					$text = LST::limitTranscludedText( $text, $this->includePageMaxLength, ' [[' . $title . '|..→]]' );
				}

				$filteredCount++;

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
			$secPiece = [];
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
					$limpos = strpos( $sSecLabel, '[' );
					$cutLink = 'default';
					$skipPattern = [];

					if ( $limpos > 0 && $sSecLabel[strlen( $sSecLabel ) - 1] == ']' ) {
						// regular expressions which define a skip pattern may precede the text
						$fmtSec = explode( '~', substr( $sSecLabel, $limpos + 1, strlen( $sSecLabel ) - $limpos - 2 ) );
						$sSecLabel = substr( $sSecLabel, 0, $limpos );
						$cutInfo = explode( ' ', $fmtSec[count( $fmtSec ) - 1], 2 );
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
						// without valid limit include whole section
						$maxLength = -1;
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
				$sectionHeading = [];
				$sectionHeading[0] = '';

				if ( $sSecLabel == '-' ) {
					$secPiece[$s] = $secPieces[0];
				} elseif ( $sSecLabel[0] == '#' || $sSecLabel[0] == '@' ) {
					$sectionHeading[0] = substr( $sSecLabel, 1 );

					// Uses LST::includeHeading() from LabeledSectionTransclusion extension to include headings from the page
					$secPieces = LST::includeHeading( $this->parser, $article->mTitle->getPrefixedText(), substr( $sSecLabel, 1 ), '', $sectionHeading, false, $maxLength, $cutLink ?? 'default', $this->getTrimIncluded(), $skipPattern ?? [] );

					if ( $mustMatch != '' || $mustNotMatch != '' ) {
						$secPiecesTmp = $secPieces;
						$offset = 0;

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
							$secPiece[$s] .= str_replace( '%SECTION%', $sectionHeading[$sp] ?? '', $this->replaceTagCount( $this->multiSectionSeparators[$s], $filteredCount ) );
						}

						$secPiece[$s] .= $secPieces[$sp];
					}

					if ( $this->getDominantSectionCount() >= 0 && $s == $this->getDominantSectionCount() && count( $secPieces ) > 1 ) {
						$dominantPieces = $secPieces;
					}

					if ( ( $mustMatch != '' || $mustNotMatch != '' ) && count( $secPieces ) <= 0 ) {
						$matchFailed = true;
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

					// Why was defaultTemplateSuffix passed all over the place for just here?
					$secPieces = LST::includeTemplate( $this->parser, $this, $s, $article, $template1, $template2, $template2 . $this->getTemplateSuffix(), $mustMatch, $mustNotMatch, $this->includePageParsed, implode( ', ', $article->mCategoryLinks ) );
					$secPiece[$s] = implode( isset( $this->multiSectionSeparators[$s] ) ? $this->replaceTagCount( $this->multiSectionSeparators[$s], $filteredCount ) : '', $secPieces );

					if ( $this->getDominantSectionCount() >= 0 && $s == $this->getDominantSectionCount() && count( $secPieces ) > 1 ) {
						$dominantPieces = $secPieces;
					}

					if ( ( $mustMatch != '' || $mustNotMatch != '' ) && count( $secPieces ) <= 1 && $secPieces[0] == '' ) {
						$matchFailed = true;
						break;
					}
				} else {
					// Uses LST::includeSection() from LabeledSectionTransclusion extension to include labeled sections from the page
					$secPieces = LST::includeSection( $this->parser, $article->mTitle->getPrefixedText(), $sSecLabel, '', false, $this->getTrimIncluded(), $skipPattern ?? [] );
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

			$filteredCount++;

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
	 * @param string $piece
	 * @param string $start
	 * @param string $end
	 * @return string
	 */
	protected function joinSectionTagPieces( $piece, $start, $end ) {
		return $start . $piece . $end;
	}

	/**
	 * Get the count of listed items after formatting, transcluding, and such.
	 *
	 * @return int
	 */
	public function getRowCount() {
		return $this->rowCount;
	}
}
