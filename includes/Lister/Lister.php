<?php

namespace MediaWiki\Extension\DynamicPageList4\Lister;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DynamicPageList4\Article;
use MediaWiki\Extension\DynamicPageList4\Config;
use MediaWiki\Extension\DynamicPageList4\LST;
use MediaWiki\Extension\DynamicPageList4\Parameters;
use MediaWiki\Extension\DynamicPageList4\UpdateArticle;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Title\Title;
use PageImages\PageImages;

class Lister {

	protected const LIST_DEFINITION = 1;

	protected const LIST_GALLERY = 2;

	protected const LIST_HEADING = 3;

	protected const LIST_INLINE = 4;

	protected const LIST_ORDERED = 5;

	protected const LIST_UNORDERED = 6;

	protected const LIST_CATEGORY = 7;

	protected const LIST_USERFORMAT = 8;

	protected readonly Config $config;

	protected int $style = self::LIST_UNORDERED;

	/**
	 * Heading List Start
	 * Use %s for attribute placement. Example: <div%s>
	 */
	protected string $headListStart = '';
	protected string $headListEnd = '';

	/**
	 * Heading List Start
	 * Use %s for attribute placement. Example: <div%s>
	 */
	protected string $headItemStart = '';
	protected string $headItemEnd = '';

	/**
	 * List(Section) Start
	 * Use %s for attribute placement. Example: <div%s>
	 */
	protected string $listStart = '';
	protected string $listEnd = '';

	/**
	 * Item Start
	 * Use %s for attribute placement. Example: <div%s>
	 */
	protected string $itemStart = '';
	protected string $itemEnd = '';

	/** Extra head list HTML attributes. */
	protected string $headListAttributes = '';

	/** Extra head item HTML attributes. */
	protected string $headItemAttributes = '';

	/** Extra list HTML attributes. */
	protected string $listAttributes = '';

	/** Extra item HTML attributes. */
	protected string $itemAttributes = '';

	/** Count tipping point to mark a section as dominant. */
	protected int $dominantSectionCount = -1;
	protected string $templateSuffix = '';

	/** Trim included wiki text. */
	protected bool $trimIncluded = false;
	protected bool $escapeLinks = true;

	/** Index of the table column to sort by. */
	protected ?int $tableSortColumn = null;
	protected string $tableSortMethod;

	protected ?int $titleMaxLength = null;

	/** Section separators that separate transcluded pages/sections of wiki text. */
	protected array $sectionSeparators = [];

	/**
	 * Section separators that separate transcluded pages/sections that refer to
	 * the same chapter or tempalte of wiki text.
	 */
	protected array $multiSectionSeparators = [];

	/** Include page text in output. */
	protected bool $includePageText = false;

	/** Maximum length before truncated included wiki text. */
	protected ?int $includePageMaxLength = null;

	/** Array of plain text matches for page transclusion. (include) */
	protected array $pageTextMatch;

	/** Array of regex text matches for page transclusion. (includematch) */
	protected array $pageTextMatchRegex;

	/** Array of not regex text matches for page transclusion. (includenotmatch) */
	protected array $pageTextMatchNotRegex;

	/** Parsed wiki text into HTML before running include/includematch/includenotmatch. */
	protected bool $includePageParsed = false;

	/** Total result count after parsing, transcluding, and such. */
	protected int $rowCount = 0;

	protected function __construct(
		protected readonly Parameters $parameters,
		protected readonly Parser $parser
	) {
		$this->setHeadListAttributes( $parameters->getParameter( 'hlistattr' ) ?? '' );
		$this->setHeadItemAttributes( $parameters->getParameter( 'hitemattr' ) ?? '' );
		$this->setListAttributes( $parameters->getParameter( 'listattr' ) ?? '' );
		$this->setItemAttributes( $parameters->getParameter( 'itemattr' ) ?? '' );

		$this->dominantSectionCount = $parameters->getParameter( 'dominantsection' );
		$this->escapeLinks = $parameters->getParameter( 'escapelinks' );
		$this->includePageMaxLength = $parameters->getParameter( 'includemaxlen' );
		$this->includePageParsed = $parameters->getParameter( 'incparsed' );
		$this->includePageText = $parameters->getParameter( 'incpage' );
		$this->multiSectionSeparators = $parameters->getParameter( 'multisecseparators' ) ?? [];
		$this->pageTextMatch = $parameters->getParameter( 'seclabels' ) ?? [];
		$this->pageTextMatchNotRegex = $parameters->getParameter( 'seclabelsnotmatch' ) ?? [];
		$this->pageTextMatchRegex = $parameters->getParameter( 'seclabelsmatch' ) ?? [];
		$this->sectionSeparators = $parameters->getParameter( 'secseparators' ) ?? [];
		$this->tableSortColumn = $parameters->getParameter( 'tablesortcol' );
		$this->tableSortMethod = $parameters->getParameter( 'tablesortmethod' );
		$this->templateSuffix = $parameters->getParameter( 'defaulttemplatesuffix' );
		$this->titleMaxLength = $parameters->getParameter( 'titlemaxlen' );
		$this->trimIncluded = $parameters->getParameter( 'includetrim' );
		$this->config = Config::getInstance();
	}

	public static function newFromStyle(
		string $style,
		Parameters $parameters,
		Parser $parser
	): self {
		$style = strtolower( $style );
		$class = match ( $style ) {
			'category' => CategoryList::class,
			'definition' => DefinitionList::class,
			'gallery' => GalleryList::class,
			'inline' => InlineList::class,
			'ordered' => OrderedList::class,
			'subpage' => SubPageList::class,
			'userformat' => UserFormatList::class,
			'unordered', default => UnorderedList::class,
		};

		return new $class( $parameters, $parser );
	}

	private function setHeadListAttributes( string $attributes ): void {
		$this->headListAttributes = Sanitizer::fixTagAttributes( $attributes, 'ul' );
	}

	private function setHeadItemAttributes( string $attributes ): void {
		$this->headItemAttributes = Sanitizer::fixTagAttributes( $attributes, 'li' );
	}

	private function setListAttributes( string $attributes ): void {
		$this->listAttributes = Sanitizer::fixTagAttributes( $attributes, 'ul' );
	}

	private function setItemAttributes( string $attributes ): void {
		$this->itemAttributes = Sanitizer::fixTagAttributes( $attributes, 'li' );
	}

	/**
	 * Shortcut to format all articles into a single formatted list.
	 */
	public function format( array $articles ): string {
		return $this->formatList( $articles, 0, count( $articles ) );
	}

	/**
	 * Format a list of articles into a singular list.
	 */
	public function formatList(
		array $articles,
		int $start,
		int $count
	): string {
		$filteredCount = 0;
		$items = [];

		for ( $i = $start; $i < $start + $count; $i++ ) {
			$article = $articles[$i];

			if ( !$article || empty( $article->mTitle ) ) {
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
	 */
	protected function formatItem( Article $article, ?string $pageText ): string {
		$lang = RequestContext::getMain()->getLanguage();

		$item = '';

		$date = $article->getDate();
		if ( $date ) {
			$item .= $date . ' ';

			if ( $article->mRevision > 0 ) {
				$item .= '[{{fullurl:' . $article->mTitle . '|oldid=' .
					$article->mRevision . '}} ' . htmlspecialchars( $article->mTitle ) . ']';
			} else {
				$item .= $article->mLink;
			}
		} else {
			// output the link to the article
			$item .= $article->mLink;
		}

		if ( $article->mSize > 0 ) {
			$byte = 'B';
			$pageLength = $lang->formatNum( $article->mSize );
			$item .= " [{$pageLength} {$byte}]";
		}

		if ( $article->mCounter > 0 ) {
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();
			$item .= ' ' . Html::rawElement( 'bdi',
				[ 'dir' => $contLang->getDir() ],
				'(' . wfMessage( 'hitcounters-nviews' )->numParams( $article->mCounter )->escaped() . ')'
			);
		}

		if ( $article->mUserLink ) {
			$item .= ' . . [[User:' . $article->mUser . '|' . $article->mUser . ']]';

			if ( $article->mComment != '' ) {
				$item .= ' { ' . $article->mComment . ' }';
			}
		}

		if ( $article->mContributor ) {
			$item .= ' . . [[User:' . $article->mContributor . '|' . $article->mContributor . " $article->mContrib]]";
		}

		if ( $article->mCategoryLinks ) {
			$item .= ' . . <small>' . wfMessage( 'categories' ) . ': ' .
				implode( ' | ', $article->mCategoryLinks ) . '</small>';
		}

		if ( $this->parameters->getParameter( 'addexternallink' ) && $article->mExternalLink ) {
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
	 */
	protected function getHeadListStart(): string {
		return sprintf( $this->headListStart, $this->headListAttributes );
	}

	/**
	 * Return $this->headItemStart with attributes replaced.
	 */
	protected function getHeadItemStart(): string {
		return sprintf( $this->headItemStart, $this->headItemAttributes );
	}

	/**
	 * Return $this->headItemStart with attributes replaced.
	 */
	protected function getHeadItemEnd(): string {
		return $this->headItemEnd;
	}

	/**
	 * Return $this->listStart with attributes replaced.
	 */
	protected function getListStart(): string {
		return sprintf( $this->listStart, $this->listAttributes );
	}

	/**
	 * Return $this->itemStart with attributes replaced.
	 */
	protected function getItemStart(): string {
		return sprintf( $this->itemStart, $this->itemAttributes );
	}

	/**
	 * Return $this->itemEnd with attributes replaced.
	 */
	protected function getItemEnd(): string {
		return $this->itemEnd;
	}

	/**
	 * Join together items after being processed by formatItem().
	 */
	protected function implodeItems( array $items ): string {
		return implode( '', $items );
	}

	/**
	 * Replace user tag parameters.
	 */
	protected function replaceTagParameters( string $tag, Article $article ): string {
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
		$replaceInTitle = $this->parameters->getParameter( 'replaceintitle' );

		if ( is_array( $replaceInTitle ) && count( $replaceInTitle ) === 2 ) {
			$title = preg_replace( $replaceInTitle[0], $replaceInTitle[1], $title );
		}

		$titleMaxLength = $this->getTitleMaxLength();
		if ( $titleMaxLength !== null && ( strlen( $title ) > $titleMaxLength ) ) {
			$title = substr( $title, 0, $titleMaxLength ) . '...';
		}

		$tag = str_replace( '%TITLE%', $title, $tag );
		$tag = str_replace( '%DISPLAYTITLE%', $article->mDisplayTitle ?: $title, $tag );

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

		if ( $article->mSelTitle ) {
			if ( $article->mSelNamespace == 0 ) {
				$tag = str_replace( '%PAGESEL%', str_replace( '_', ' ', $article->mSelTitle ), $tag );
			} else {
				$tag = str_replace( '%PAGESEL%', $namespaces[$article->mSelNamespace] . ':' .
					str_replace( '_', ' ', $article->mSelTitle ), $tag
				);
			}
		}

		$tag = str_replace( '%IMAGESEL%', str_replace( '_', ' ', $article->mImageSelTitle ), $tag );

		$tag = $this->replaceTagCategory( $tag, $article );
		return $tag;
	}

	/**
	 * Replace user tag parameters for categories.
	 */
	private function replaceTagCategory( string $tag, Article $article ): string {
		if ( $article->mCategoryLinks ) {
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
	 */
	protected function replaceTagCount( string $tag, int $nr ): string {
		return str_replace( '%NR%', (string)$nr, $tag );
	}

	/**
	 * Format one single item of an entry in the output list
	 * i.e. one occurence of one item from the include parameter
	 */
	private function replaceTagTableRow( array &$pieces, int $s, Article $article ): void {
		$tableFormat = $this->parameters->getParameter( 'tablerow' );
		$firstCall = true;

		foreach ( $pieces as $key => $val ) {
			if ( isset( $tableFormat[$s] ) ) {
				if ( $s == 0 || $firstCall ) {
					$pieces[$key] = str_replace( '%%', $val, $tableFormat[$s] );
				} else {
					$n = strpos( $tableFormat[$s], '|' );

					if (
						$n === false ||
						!( strpos( substr( $tableFormat[$s], 0, $n ), '{' ) === false ) ||
						!( strpos( substr( $tableFormat[$s], 0, $n ), '[' ) === false )
					) {
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
	 * Format one single template argument of one occurence of one item from the include parameter.
	 * This is called via a backlink from LST::includeTemplate().
	 */
	public function formatTemplateArg(
		string $arg,
		int $s,
		int $argNr,
		bool $firstCall,
		int $maxLength,
		Article $article
	): string {
		$tableFormat = $this->parameters->getParameter( 'tablerow' );

		// we could try to format fields differently within the first call of a template
		// currently we do not make such a difference

		// if the result starts with a '-' we add a leading space; thus we avoid a misinterpretation of |- as
		// a start of a new row (wiki table syntax)
		if ( array_key_exists( "$s.$argNr", $tableFormat ) ) {
			$n = -1;

			if ( $s >= 1 && $argNr == 0 && !$firstCall ) {
				$n = strpos( $tableFormat["$s.$argNr"], '|' );
				if (
					$n === false ||
					!( strpos( substr( $tableFormat["$s.$argNr"], 0, $n ), '{' ) === false ) ||
					!( strpos( substr( $tableFormat["$s.$argNr"], 0, $n ), '[' ) === false )
				) {
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
	 * @return string the truncated text; note that in some cases it may be slightly longer than the given limit
	 * if the text is alread shorter than the limit or if the limit is negative, the text
	 * will be returned without any checks for balance of tags
	 */
	private function cutAt( int $lim, string $text ): string {
		if ( $lim < 0 ) {
			return $text;
		}

		return LST::limitTranscludedText( $text, $lim );
	}

	/**
	 * Prepends an image name with its hash path.
	 */
	private function parseImageUrlWithPath( Article|string $article ): string {
		$repoGroup = MediaWikiServices::getInstance()->getRepoGroup();
		if ( $article instanceof Article ) {
			if ( $article->mNamespace === NS_FILE ) {
				// Calculate the URL for existing files.
				$file = $repoGroup->findFile( $article->mTitle );
				return $file && $file->exists()
					? $file->getRel()
					: $repoGroup->getLocalRepo()->newFile( $article->mTitle )->getRel();
			}

			if ( ExtensionRegistry::getInstance()->isLoaded( 'PageImages' ) ) {
				// Get the PageImage URL.
				$pageImage = PageImages::getPageImage( $article->mTitle );
				if ( !$pageImage || !$pageImage->exists() ) {
					return '';
				}

				return $pageImage->getRel();
			}

			return '';
		}

		$title = Title::newFromText( $article, NS_FILE );
		if ( !$title ) {
			return '';
		}

		return $repoGroup->getLocalRepo()->newFile( $title )->getRel();
	}

	/**
	 * Transclude a page contents.
	 */
	protected function transcludePage( Article $article, int &$filteredCount ): string {
		$matchFailed = false;
		$septag = [];

		// include whole article
		if ( !$this->pageTextMatch || $this->pageTextMatch[0] == '*' ) {
			$title = $article->mTitle->getPrefixedText();

			if ( $this->style === self::LIST_USERFORMAT ) {
				$pageText = '';
			} else {
				$pageText = Html::element( 'br' );
			}

			$text = $this->parser->fetchTemplateAndTitle( Title::newFromText( $title ) )[0];
			if (
				(
					count( $this->pageTextMatchRegex ) <= 0 ||
					$this->pageTextMatchRegex[0] == '' ||
					!( !preg_match( $this->pageTextMatchRegex[0], $text ) )
				) &&
				(
					count( $this->pageTextMatchNotRegex ) <= 0 ||
					$this->pageTextMatchNotRegex[0] == '' ||
					preg_match( $this->pageTextMatchNotRegex[0], $text ) == false
				)
			) {
				if ( $this->includePageMaxLength > 0 && ( strlen( $text ) > $this->includePageMaxLength ) ) {
					$text = LST::limitTranscludedText( $text, $this->includePageMaxLength, ' [[' . $title . '|..→]]' );
				}

				$filteredCount++;

				// update article if include=* and updaterules are given
				$updateRules = $this->parameters->getParameter( 'updaterules' );
				$deleteRules = $this->parameters->getParameter( 'deleterules' );

				if ( $updateRules ) {
					$ruleOutput = UpdateArticle::updateArticleByRule( $title, $text, $updateRules );

					// append update message to output
					$pageText .= $ruleOutput;
				} elseif ( $deleteRules ) {
					$ruleOutput = UpdateArticle::deleteArticleByRule( $title, $text, $deleteRules );

					// append delete message to output
					$pageText .= $ruleOutput;
				} else {
					// append full text to output
					if ( is_array( $this->sectionSeparators ) && array_key_exists( '0', $this->sectionSeparators ) ) {
						$pageText .= $this->replaceTagCount( $this->sectionSeparators[0], $filteredCount );
						$pieces = [ 0 => $text ];

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
						$maxLength = (int)$cutInfo[0];

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
				if (
					is_array( $this->pageTextMatchRegex ) &&
					count( $this->pageTextMatchRegex ) > $s &&
					!empty( $this->pageTextMatchRegex[$s] )
				) {
					$mustMatch = $this->pageTextMatchRegex[$s];
				} else {
					$mustMatch = '';
				}

				if (
					is_array( $this->pageTextMatchNotRegex ) &&
					count( $this->pageTextMatchNotRegex ) > $s &&
					!empty( $this->pageTextMatchNotRegex[$s] )
				) {
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

					// Uses LST::includeHeading() from LabeledSectionTransclusion extension to
					// include headings from the page
					$secPieces = LST::includeHeading(
						$this->parser,
						$article->mTitle->getPrefixedText(),
						substr( $sSecLabel, 1 ),
						'',
						$sectionHeading,
						false,
						$maxLength,
						$cutLink ?? 'default',
						$this->getTrimIncluded(),
						$skipPattern ?? []
					);

					if ( $mustMatch != '' || $mustNotMatch != '' ) {
						$secPiecesTmp = $secPieces;
						$offset = 0;

						foreach ( $secPiecesTmp as $nr => $onePiece ) {
							if ( ( $mustMatch != '' && preg_match( $mustMatch, $onePiece ) == false ) ||
								( $mustNotMatch != '' && preg_match( $mustNotMatch, $onePiece ) != false )
							) {
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
							$secPiece[$s] .= str_replace(
								'%SECTION%', $sectionHeading[$sp] ?? '',
								$this->replaceTagCount(
									$this->multiSectionSeparators[$s],
									$filteredCount
								)
							);
						}

						$secPiece[$s] .= $secPieces[$sp];
					}

					if (
						$this->dominantSectionCount >= 0 &&
						$s == $this->dominantSectionCount &&
						count( $secPieces ) > 1
					) {
						$dominantPieces = $secPieces;
					}

					if ( ( $mustMatch != '' || $mustNotMatch != '' ) && count( $secPieces ) <= 0 ) {
						$matchFailed = true;
						break;
					}

				} elseif ( $sSecLabel[0] == '{' ) {
					// Uses LST::includeTemplate() from LabeledSectionTransclusion extension to
					// include templates from the page primary syntax {template}suffix
					$template1 = trim( substr( $sSecLabel, 1, strpos( $sSecLabel, '}' ) - 1 ) );
					$template2 = trim( str_replace( '}', '', substr( $sSecLabel, 1 ) ) );

					// alternate syntax: {template|surrogate}
					if ( $template2 == $template1 && strpos( $template1, '|' ) > 0 ) {
						$template1 = preg_replace( '/\|.*/', '', $template1 );
						$template2 = preg_replace( '/^.+\|/', '', $template2 );
					}

					// Why was defaultTemplateSuffix passed all over the place for just here?
					$secPieces = LST::includeTemplate(
						$this->parser,
						$this,
						$s,
						$article,
						$template1,
						$template2,
						$template2 . $this->getTemplateSuffix(),
						$mustMatch,
						$mustNotMatch,
						$this->includePageParsed,
						implode( ', ', $article->mCategoryLinks )
					);

					$secPiece[$s] = implode(
						isset( $this->multiSectionSeparators[$s] ) ?
						$this->replaceTagCount(
							$this->multiSectionSeparators[$s],
							$filteredCount
						) : '',
						$secPieces
					);

					if (
						$this->dominantSectionCount >= 0 &&
						$s == $this->dominantSectionCount &&
						count( $secPieces ) > 1
					) {
						$dominantPieces = $secPieces;
					}

					if (
						( $mustMatch != '' || $mustNotMatch != '' ) &&
						count( $secPieces ) <= 1 && $secPieces[0] == ''
					) {
						$matchFailed = true;
						break;
					}
				} else {
					// Uses LST::includeSection() from LabeledSectionTransclusion extension to
					// include labeled sections from the page
					$secPieces = LST::includeSection(
						$this->parser, $article->mTitle->getPrefixedText(),
						$sSecLabel, false, $this->getTrimIncluded(),
						$skipPattern ?? []
					);
					$secPiece[$s] = implode(
						$this->replaceTagCount(
							$this->multiSectionSeparators[$s] ?? '', $filteredCount
						), $secPieces
					);

					if (
						$this->dominantSectionCount >= 0 &&
						$s == $this->dominantSectionCount &&
						count( $secPieces ) > 1
					) {
						$dominantPieces = $secPieces;
					}

					if ( (
						$mustMatch != '' &&
						preg_match( $mustMatch, $secPiece[$s] ) == false
					) || (
						$mustNotMatch != '' &&
						preg_match( $mustNotMatch, $secPiece[$s] ) != false
					) ) {
						$matchFailed = true;
						break;
					}
				}

				// separator tags
				if ( is_array( $this->sectionSeparators ) && count( $this->sectionSeparators ) == 1 ) {
					// If there is only one separator tag use it always
					$septag[$s * 2] = str_replace(
						'%SECTION%', $sectionHeading[0], $this->replaceTagCount(
							$this->sectionSeparators[0], $filteredCount
						)
					);
				} elseif ( isset( $this->sectionSeparators[$s * 2] ) ) {
					$septag[$s * 2] = str_replace(
						'%SECTION%', $sectionHeading[0], $this->replaceTagCount(
							$this->sectionSeparators[$s * 2], $filteredCount
						)
					);
				} else {
					$septag[$s * 2] = '';
				}

				if ( isset( $this->sectionSeparators[$s * 2 + 1] ) ) {
					$septag[$s * 2 + 1] = str_replace(
						'%SECTION%', $sectionHeading[0], $this->replaceTagCount(
							$this->sectionSeparators[$s * 2 + 1], $filteredCount
						)
					);
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
						if ( $s == $this->dominantSectionCount ) {
							$pageText .= $this->joinSectionTagPieces(
								$dominantPiece, $septag[$s * 2], $septag[$s * 2 + 1]
							);
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
	 */
	private function joinSectionTagPieces( string $piece, string $start, string $end ): string {
		return $start . $piece . $end;
	}

	/**
	 * Get the count of listed items after formatting, transcluding, and such.
	 */
	public function getRowCount(): int {
		return $this->rowCount;
	}
}
