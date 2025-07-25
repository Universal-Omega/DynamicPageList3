<?php

namespace MediaWiki\Extension\DynamicPageList4\Lister;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DynamicPageList4\Article;
use MediaWiki\Extension\DynamicPageList4\Config;
use MediaWiki\Extension\DynamicPageList4\LST;
use MediaWiki\Extension\DynamicPageList4\Parameters;
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
	protected int $dominantSectionCount;
	protected string $templateSuffix;

	/** Trim included wiki text. */
	protected bool $trimIncluded;
	protected bool $escapeLinks;

	/** Index of the table column to sort by. */
	protected int $tableSortColumn;
	protected string $tableSortMethod;

	protected int $titleMaxLength;

	/** Section separators that separate transcluded pages/sections of wiki text. */
	protected array $sectionSeparators;

	/**
	 * Section separators that separate transcluded pages/sections that refer to
	 * the same chapter or tempalte of wiki text.
	 */
	protected array $multiSectionSeparators;

	/** Include page text in output. */
	protected bool $includePageText;

	/** Maximum length before truncated included wiki text. */
	protected int $includePageMaxLength;

	/** Array of plain text matches for page transclusion. (include) */
	protected array $pageTextMatch;

	/** Array of regex text matches for page transclusion. (includematch) */
	protected array $pageTextMatchRegex;

	/** Array of not regex text matches for page transclusion. (includenotmatch) */
	protected array $pageTextMatchNotRegex;

	/** Parsed wiki text into HTML before running include/includematch/includenotmatch. */
	protected bool $includePageParsed;

	/** Total result count after parsing, transcluding, and such. */
	protected int $rowCount = 0;

	protected function __construct(
		protected readonly Parameters $parameters,
		private readonly Parser $parser
	) {
		$this->setHeadListAttributes( $parameters->getParameter( 'hlistattr' ) ?? '' );
		$this->setHeadItemAttributes( $parameters->getParameter( 'hitemattr' ) ?? '' );
		$this->setListAttributes( $parameters->getParameter( 'listattr' ) ?? '' );
		$this->setItemAttributes( $parameters->getParameter( 'itemattr' ) ?? '' );

		$this->dominantSectionCount = $parameters->getParameter( 'dominantsection' );
		$this->escapeLinks = $parameters->getParameter( 'escapelinks' );
		$this->includePageMaxLength = $parameters->getParameter( 'includemaxlen' ) ?? 0;
		$this->includePageParsed = $parameters->getParameter( 'incparsed' ) ?? false;
		$this->includePageText = $parameters->getParameter( 'incpage' ) ?? false;
		$this->multiSectionSeparators = $parameters->getParameter( 'multisecseparators' ) ?? [];
		$this->pageTextMatch = $parameters->getParameter( 'seclabels' ) ?? [];
		$this->pageTextMatchNotRegex = $parameters->getParameter( 'seclabelsnotmatch' ) ?? [];
		$this->pageTextMatchRegex = $parameters->getParameter( 'seclabelsmatch' ) ?? [];
		$this->sectionSeparators = $parameters->getParameter( 'secseparators' ) ?? [];
		$this->tableSortColumn = $parameters->getParameter( 'tablesortcol' ) ?? 0;
		$this->tableSortMethod = $parameters->getParameter( 'tablesortmethod' );
		$this->templateSuffix = $parameters->getParameter( 'defaulttemplatesuffix' );
		$this->titleMaxLength = $parameters->getParameter( 'titlemaxlen' ) ?? 0;
		$this->trimIncluded = $parameters->getParameter( 'includetrim' ) ?? false;
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
			'unordered' => UnorderedList::class,
			default => UnorderedList::class,
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
	public function formatList( array $articles, int $start, int $count ): string {
		$items = [];
		$filteredCount = 0;

		$limit = $start + $count;
		for ( $i = $start; $i < $limit; $i++ ) {
			$article = $articles[$i] ?? null;
			if ( !$article?->mTitle ) {
				continue;
			}

			$pageText = $this->includePageText
				? $this->transcludePage( $article, $filteredCount )
				: null;

			if ( !$this->includePageText ) {
				$filteredCount++;
			}

			$items[] = $this->formatItem( $article, $pageText );
		}

		$this->rowCount = $filteredCount;
		return $this->getListStart() . $this->implodeItems( $items ) . $this->listEnd;
	}

	/**
	 * Format a single item.
	 */
	protected function formatItem( Article $article, ?string $pageText ): string {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();

		$item = '';
		$date = $article->getDate();
		if ( $date ) {
			$item .= $date . ' ';
			if ( $article->mRevision > 0 ) {
				$titleText = $article->mTitle->getPrefixedText();
				$title = htmlspecialchars( $titleText );
				$item .= "[{{fullurl:$titleText|oldid={$article->mRevision}}} $title]";
			} else {
				$item .= $article->mLink;
			}
		} else {
			$item .= $article->mLink;
		}

		if ( $article->mSize > 0 ) {
			$item .= wfMessage( 'brackets' )
				->sizeParams( $article->mSize )
				->escaped();
		}

		if ( $article->mCounter > 0 ) {
			$views = wfMessage( 'hitcounters-pop-page-line' )
				->numParams( $article->mCounter )
				->text();

			$item .= ' ' . Html::element( 'bdi',
				[ 'dir' => $contLang->getDir() ],
				wfMessage( 'parentheses', $views )->text()
			);
		}

		if ( $article->mUserLink ) {
			$item .= " . . [[User:{$article->mUser}|{$article->mUser}]]";
			if ( $article->mComment !== '' ) {
				$item .= ' { ' . $article->mComment . ' }';
			}
		}

		if ( $article->mContributor ) {
			$item .= ' . . [[User:' . $article->mContributor . '|' .
				$article->mContributor . ' ' . $article->mContrib . ']]';
		}

		if ( $article->mCategoryLinks ) {
			$lang = RequestContext::getMain()->getLanguage();
			$item .= ' . . ' . Html::element( 'small', [],
				wfMessage( 'categories' )->text() . wfMessage( 'colon-separator' )->text() .
				$lang->pipeList( $article->mCategoryLinks )
			);
		}

		if (
			$this->parameters->getParameter( 'addexternallink' )
			&& $article->mExternalLink
		) {
			$item .= " {$contLang->getArrow()} {$article->mExternalLink}";
		}

		if ( $pageText !== null ) {
			$item .= $pageText;
		}

		$item = $this->getItemStart() . $item . $this->getItemEnd();
		return $this->replaceTagParameters( $item, $article );
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
		if ( !str_contains( $tag, '%' ) ) {
			return $tag;
		}

		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		$namespaces = $contLang->getNamespaces();
		$imageUrl = $this->parseImageUrlWithPath( $article );

		$pageName = $article->mTitle->getPrefixedText();
		if ( $this->escapeLinks && $article->mTitle->inNamespaces( NS_CATEGORY, NS_FILE ) ) {
			$pageName = ':' . $pageName;
		}

		$title = $article->mTitle->getText();
		$replaceInTitle = $this->parameters->getParameter( 'replaceintitle' ) ?? [];

		if ( count( $replaceInTitle ) === 2 ) {
			$title = preg_replace( $replaceInTitle[0], $replaceInTitle[1], $title );
		}

		if ( $this->titleMaxLength > 0 && strlen( $title ) > $this->titleMaxLength ) {
			$title = substr( $title, 0, $this->titleMaxLength ) . wfMessage( 'ellipsis' )->escaped();
		}

		$tag = strtr( $tag, [
			'%PAGE%' => $pagename,
			'%PAGEID%' => (string)$article->mID,
			'%NAMESPACE%' => $namespaces[$article->mNamespace],
			'%IMAGE%' => $imageUrl,
			'%EXTERNALLINK%' => $article->mExternalLink,
			'%EDITSUMMARY%' => $article->mComment,
			'%TITLE%' => $title,
			'%DISPLAYTITLE%' => $article->mDisplayTitle ?: $title,
			'%COUNT%' => (string)$article->mCounter,
			'%COUNTFS%' => (string)floor( log( $article->mCounter ) * 0.7 ),
			'%COUNTFS2%' => (string)floor( sqrt( log( $article->mCounter ) ) ),
			'%SIZE%' => (string)$article->mSize,
			'%SIZEFS%' => (string)floor( sqrt( log( $article->mSize ) ) * 2.5 - 5 ),
			'%DATE%' => $article->getDate(),
			'%REVISION%' => (string)$article->mRevision,
			'%CONTRIBUTION%' => (string)$article->mContribution,
			'%CONTRIB%' => $article->mContrib,
			'%CONTRIBUTOR%' => $article->mContributor,
			'%USER%' => $article->mUser,
		] );

		if ( $article->mSelTitle ) {
			$pageSel = $article->mSelNamespace === NS_MAIN
				? str_replace( '_', ' ', $article->mSelTitle )
				: $namespaces[$article->mSelNamespace] . ':' . str_replace( '_', ' ', $article->mSelTitle );

			$tag = str_replace( '%PAGESEL%', $pageSel, $tag );
		}

		$tag = str_replace( '%IMAGESEL%', str_replace( '_', ' ', $article->mImageSelTitle ), $tag );
		return $this->replaceTagCategory( $tag, $article );
	}

	/**
	 * Replace user tag parameters for categories.
	 */
	private function replaceTagCategory( string $tag, Article $article ): string {
		$hasCats = $article->mCategoryLinks !== [];
		return strtr( $tag, [
			'%CATLIST%' => $hasCats ? implode( ', ', $article->mCategoryLinks ) : '',
			'%CATBULLETS%' => $hasCats ? '* ' . implode( "\n* ", $article->mCategoryLinks ) : '',
			'%CATNAMES%' => $hasCats ? implode( ', ', $article->mCategoryTexts ) : '',
		] );
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
			if ( !isset( $tableFormat[$s] ) ) {
				$firstCall = false;
				continue;
			}

			$format = $tableFormat[$s];
			$pipePos = strpos( $format, '|' );
			$beforePipe = $pipePos !== false ? substr( $format, 0, $pipePos ) : null;

			$isFirst = $s === 0 || $firstCall;
			$hasBracketSyntax = str_contains( $beforePipe ?? '', '{' ) || str_contains( $beforePipe ?? '', '[' );

			if ( $isFirst || $pipePos === false || $hasBracketSyntax ) {
				$row = str_replace( '%%', $val, $format );
			} else {
				$row = str_replace( '%%', $val, substr( $format, $pipePos + 1 ) );
			}

			$row = str_replace(
				[ '%IMAGE%', '%PAGE%' ],
				[ $this->parseImageUrlWithPath( $val ), $article->mTitle->getPrefixedText() ],
				$row
			);

			$row = $this->replaceTagCategory( $row, $article );
			$pieces[$key] = $row;

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

		$key = "$s.$argNr";
		if ( !isset( $tableFormat[$key] ) ) {
			$result = $this->cutAt( $maxLength, $arg );
			return $result !== '' && $result[0] === '-' ? ' ' . $result : $result;
		}

		$format = $tableFormat[$key];
		$n = -1;

		if ( $s >= 1 && $argNr === 0 && !$firstCall ) {
			$pipePos = strpos( $format, '|' );
			$beforePipe = $pipePos !== false ? substr( $format, 0, $pipePos ) : null;
			if (
				$pipePos !== false &&
				!str_contains( $beforePipe ?? '', '{' ) &&
				!str_contains( $beforePipe ?? '', '[' )
			) {
				$n = $pipePos;
			}
		}

		$result = str_replace(
			[ '%%', '%PAGE%', '%IMAGE%' ],
			[ $arg, $article->mTitle->getPrefixedText(), $this->parseImageUrlWithPath( $arg ) ],
			substr( $format, $n + 1 )
		);

		$result = $this->cutAt( $maxLength, $result );
		return $result !== '' && $result[0] === '-' ? ' ' . $result : $result;
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

				// append full text to output
				if ( array_key_exists( '0', $this->sectionSeparators ) ) {
					$pageText .= $this->replaceTagCount( $this->sectionSeparators[0], $filteredCount );
					$pieces = [ 0 => $text ];

					$this->replaceTagTableRow( $pieces, 0, $article );
					$pageText .= $pieces[0];
				} else {
					$pageText .= $text;
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
					count( $this->pageTextMatchRegex ) > $s &&
					!empty( $this->pageTextMatchRegex[$s] )
				) {
					$mustMatch = $this->pageTextMatchRegex[$s];
				} else {
					$mustMatch = '';
				}

				if (
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
						$this->trimIncluded,
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
						$template2 . $this->templateSuffix,
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
						$sSecLabel, false, $this->trimIncluded,
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
				if ( count( $this->sectionSeparators ) == 1 ) {
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
