<?php

namespace MediaWiki\Extension\DynamicPageList4\Lister;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DynamicPageList4\Article;
use MediaWiki\Extension\DynamicPageList4\Config;
use MediaWiki\Extension\DynamicPageList4\Parameters;
use MediaWiki\Extension\DynamicPageList4\SectionTranscluder;
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

	/** Extra list HTML attributes. */
	protected string $listAttributes = '';

	/** Extra item HTML attributes. */
	private string $itemAttributes = '';

	/** Count tipping point to mark a section as dominant. */
	private readonly int $dominantSectionCount;
	private readonly string $templateSuffix;

	/** Trim included wiki text. */
	private readonly bool $trimIncluded;
	private readonly bool $escapeLinks;

	/** Index of the table column to sort by. */
	protected readonly int $tableSortColumn;
	protected readonly string $tableSortMethod;

	private readonly int $titleMaxLength;

	/** Section separators that separate transcluded pages/sections of wiki text. */
	private readonly array $sectionSeparators;

	/**
	 * Section separators that separate transcluded pages/sections that refer to
	 * the same chapter or tempalte of wiki text.
	 */
	private readonly array $multiSectionSeparators;

	/** Include page text in output. */
	protected readonly bool $includePageText;

	/** Maximum length before truncated included wiki text. */
	private readonly int $includePageMaxLength;

	/** Array of plain text matches for page transclusion. (include) */
	private readonly array $pageTextMatch;

	/** Array of regex text matches for page transclusion. (includematch) */
	private readonly array $pageTextMatchRegex;

	/** Array of not regex text matches for page transclusion. (includenotmatch) */
	private readonly array $pageTextMatchNotRegex;

	/** Parsed wiki text into HTML before running include/includematch/includenotmatch. */
	private readonly bool $includePageParsed;

	/** Total result count after parsing, transcluding, and such. */
	protected int $rowCount = 0;

	protected function __construct(
		protected readonly Parameters $parameters,
		private readonly Parser $parser
	) {
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

		return new $class( $parameters, clone $parser );
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
			$item .= ' ' . wfMessage( 'brackets' )
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
			$item .= ' . . ' . Html::rawElement( 'small', [],
				wfMessage( 'categories' )->escaped() . wfMessage( 'colon-separator' )->escaped() .
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
			'%PAGE%' => $pageName,
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
	 * This is called via a backlink from SectionTranscluder::includeTemplate().
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

		return SectionTranscluder::limitTranscludedText( $text, $lim );
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

		// Include whole article
		if ( $this->pageTextMatch === [] || $this->pageTextMatch[0] === '*' ) {
			$title = $article->mTitle->getPrefixedText();
			$pageText = ( $this->style === self::LIST_USERFORMAT )
				? ''
				: Html::element( 'br' );

			$text = $this->parser->fetchTemplateAndTitle( $article->mTitle )[0];

			$match = (
				( $this->pageTextMatchRegex === [] || $this->pageTextMatchRegex[0] === '' ||
				  preg_match( $this->pageTextMatchRegex[0], $text ) ) &&
				( $this->pageTextMatchNotRegex === [] || $this->pageTextMatchNotRegex[0] === '' ||
				  !preg_match( $this->pageTextMatchNotRegex[0], $text ) )
			);

			if ( !$match ) {
				return '';
			}

			if ( $this->includePageMaxLength > 0 && strlen( $text ) > $this->includePageMaxLength ) {
				$text = SectionTranscluder::limitTranscludedText(
					text: $text,
					limit: $this->includePageMaxLength,
					link: " [[$title|..→]]"
				);
			}

			$filteredCount++;
			$sectionSep = $this->sectionSeparators[0] ?? null;
			if ( $sectionSep !== null ) {
				$pageText .= $this->replaceTagCount( $sectionSep, $filteredCount );
				$pieces = [ $text ];
				$this->replaceTagTableRow( $pieces, 0, $article );
				$pageText .= $pieces[0];
			} else {
				$pageText .= $text;
			}

			return $pageText;
		}

		// Identify section pieces
		$secPiece = [];
		$dominantPieces = false;

		// ONE section can be marked as "dominant"; if this section contains multiple entries
		// we will create a separate output row for each value of the dominant section
		// the values of all other columns will be repeated.
		foreach ( $this->pageTextMatch as $s => $secLabel ) {
			$secLabel = trim( $secLabel );
			if ( $secLabel === '' ) {
				break;
			}

			// If sections are identified by number we have a % at the beginning.
			if ( str_starts_with( $secLabel, '%' ) ) {
				$secLabel = "#$secLabel";
			}

			$maxLength = -1;
			$cutLink = 'default';
			$skipPattern = [];

			if ( $secLabel === '-' ) {
				// '-' is used as a dummy parameter which will produce no output.
				// If maxlen was 0 we suppress all output; note that for matching we used the full text.
				$secPieces = [ '' ];
				$this->replaceTagTableRow( $secPieces, $s, $article );
				$secPiece[$s] = $secPieces[0];
				continue;
			}

			if ( !str_starts_with( $secLabel, '{' ) ) {
				$limpos = strpos( $secLabel, '[' );
				if ( $limpos > 0 && str_ends_with( $secLabel, ']' ) ) {
					$fmtSec = explode( '~',
						substr( $secLabel, $limpos + 1, strlen( $secLabel ) - $limpos - 2 )
					);
					$secLabel = substr( $secLabel, 0, $limpos );
					$cutInfo = explode( ' ', end( $fmtSec ), 2 );
					$maxLength = (int)$cutInfo[0];
					$cutLink = $cutInfo[1] ?? 'default';
					$skipPattern = array_slice( $fmtSec, 0, -1 );
				}
			}

			$mustMatch = $this->pageTextMatchRegex[$s] ?? '';
			$mustNotMatch = $this->pageTextMatchNotRegex[$s] ?? '';
			$sectionHeading = [ '' ];

			if ( str_starts_with( $secLabel, '#' ) || str_starts_with( $secLabel, '@' ) ) {
				$sectionHeading[0] = substr( $secLabel, 1 );
				$secPieces = SectionTranscluder::includeHeading(
					parser: $this->parser,
					page: $article->mTitle->getPrefixedText(),
					sec: substr( $secLabel, 1 ),
					to: '',
					sectionHeading: $sectionHeading,
					recursionCheck: false,
					maxLength: $maxLength,
					link: $cutLink,
					trim: $this->trimIncluded,
					skipPattern: $skipPattern
				);

				if ( $mustMatch !== '' || $mustNotMatch !== '' ) {
					$secPieces = array_values( array_filter( $secPieces, static fn ( string $piece ): bool =>
						( $mustMatch === '' || preg_match( $mustMatch, $piece ) ) &&
						( $mustNotMatch === '' || !preg_match( $mustNotMatch, $piece ) )
					) );
				}

				if ( $maxLength === 0 ) {
					$secPieces = [ '' ];
				}

				$this->replaceTagTableRow( $secPieces, $s, $article );
				if ( !isset( $secPieces[0] ) ) {
					if ( $mustMatch !== '' || $mustNotMatch !== '' ) {
						$matchFailed = true;
					}
					break;
				}

				$secPiece[$s] = $secPieces[0];
				$multiSep = $this->multiSectionSeparators[$s] ?? null;
				for ( $sp = 1, $len = count( $secPieces ); $sp < $len; $sp++ ) {
					if ( $multiSep !== null ) {
						$secPiece[$s] .= str_replace( '%SECTION%',
							// @phan-suppress-next-line PhanCoalescingAlwaysNullInLoop
							$sectionHeading[$sp] ?? '',
							$this->replaceTagCount( $multiSep, $filteredCount )
						);
					}

					$secPiece[$s] .= $secPieces[$sp];
				}
			} elseif ( str_starts_with( $secLabel, '{' ) ) {
				$template1 = trim( substr( $secLabel, 1, strpos( $secLabel, '}' ) - 1 ) );
				$template2 = trim( str_replace( '}', '', substr( $secLabel, 1 ) ) );
				if ( $template2 === $template1 && str_contains( $template1, '|' ) ) {
					$template1 = preg_replace( '/\|.*/', '', $template1 );
					$template2 = preg_replace( '/^.+\|/', '', $template2 );
				}

				$secPieces = SectionTranscluder::includeTemplate(
					parser: $this->parser,
					lister: $this,
					dplNr: $s,
					article: $article,
					template1: $template1,
					template2: $template2,
					defaultTemplate: $template2 . $this->templateSuffix,
					mustMatch: $mustMatch,
					mustNotMatch: $mustNotMatch,
					matchParsed: $this->includePageParsed,
					catlist: implode( ', ', $article->mCategoryLinks )
				);

				$multiSep = $this->multiSectionSeparators[$s] ?? null;
				$separator = $multiSep !== null
					? $this->replaceTagCount( $multiSep, $filteredCount )
					: '';

				$secPiece[$s] = implode( $separator, $secPieces );
			} else {
				$secPieces = SectionTranscluder::includeSection(
					parser: $this->parser,
					page: $article->mTitle->getPrefixedText(),
					sec: $secLabel,
					recursionCheck: false,
					trim: $this->trimIncluded,
					skipPattern: $skipPattern
				);

				$secPiece[$s] = implode( $this->replaceTagCount(
					$this->multiSectionSeparators[$s] ?? '',
					$filteredCount
				), $secPieces );
			}

			if ( $this->dominantSectionCount >= 0 && $s === $this->dominantSectionCount && count( $secPieces ) > 1 ) {
				$dominantPieces = $secPieces;
			}

			if (
				( $mustMatch !== '' && !preg_match( $mustMatch, $secPiece[$s] ) ) ||
				( $mustNotMatch !== '' && preg_match( $mustNotMatch, $secPiece[$s] ) )
			) {
				$matchFailed = true;
				break;
			}

			// Separator tags
			$sectionHeadingStr = $sectionHeading[0] ?? '';
			$left = $this->sectionSeparators[$s * 2] ?? '';
			$right = $this->sectionSeparators[$s * 2 + 1] ?? '';
			$septag[$s * 2] = str_replace( '%SECTION%',
				$sectionHeadingStr, $this->replaceTagCount( $left, $filteredCount )
			);

			$septag[$s * 2 + 1] = str_replace( '%SECTION%',
				$sectionHeadingStr, $this->replaceTagCount( $right, $filteredCount )
			);
		}

		if ( $matchFailed ) {
			return '';
		}

		$filteredCount++;
		$pageText = '';

		if ( $dominantPieces !== false ) {
			foreach ( $dominantPieces as $dominantPiece ) {
				foreach ( $secPiece as $s => $piece ) {
					$pageText .= $this->joinSectionTagPieces(
						$s === $this->dominantSectionCount ? $dominantPiece : $piece,
						$septag[$s * 2],
						$septag[$s * 2 + 1]
					);
				}
			}

			return $pageText;
		}

		foreach ( $secPiece as $s => $piece ) {
			$pageText .= $this->joinSectionTagPieces(
				$piece,
				$septag[$s * 2],
				$septag[$s * 2 + 1]
			);
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
