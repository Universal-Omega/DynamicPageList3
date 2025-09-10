<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\DynamicPageList4;

use MediaWiki\Context\RequestContext;
use MediaWiki\ExternalLinks\LinkFilter;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWiki\User\ActorStore;
use stdClass;
use function count;
use function defined;
use function explode;
use function gmdate;
use function htmlspecialchars;
use function log;
use function mb_strlen;
use function mb_substr;
use function preg_replace;
use function round;
use function str_replace;
use function substr;
use function trim;
use function wfMessage;
use function wfTimestamp;
use const NS_CATEGORY;
use const NS_FILE;
use const NS_MAIN;
use const NS_VIDEO;
use const TS_UNIX;

class Article {

	private string $mDate = '';
	private static array $headings = [];

	public array $mCategoryLinks = [];
	public array $mCategoryTexts = [];

	public int $mID;
	public int $mCounter;
	public int $mSize;
	public int $mContribution = 0;
	public int $mRevision = 0;
	public int $mSelNamespace = -1;

	public string $mDisplayTitle;
	public string $mExternalLink;
	public string $mStartChar;
	public string $mLink;
	public string $mImageSelTitle = '';
	public string $mSelTitle = '';
	public string $mParentHLink = '';
	public string $mUserLink = '';
	public string $mUser = '';
	public string $mComment = '';
	public string $mContrib = '';
	public string $mContributor = '';
	public string $myDate = '';

	private function __construct(
		public readonly Title $mTitle,
		public readonly int $mNamespace
	) {
	}

	/**
	 * Initialize a new instance from a database row.
	 */
	public static function newFromRow(
		stdClass $row,
		Parameters $parameters,
		Title $title,
		int $pageNamespace,
		string $pageTitle
	): self {
		$services = MediaWikiServices::getInstance();
		$contentLanguage = $services->getContentLanguage();
		$userFactory = $services->getUserFactory();

		$article = new self( $title, $pageNamespace );

		$revActorName = ActorStore::UNKNOWN_USER_NAME;
		if ( (int)( $row->rev_actor ?? 0 ) !== 0 ) {
			$revUser = $userFactory->newFromActorId( (int)$row->rev_actor );
			$revUserDeleted = $row->rev_deleted & RevisionRecord::DELETED_USER;
			$revActorName = $revUser->isHidden() || $revUserDeleted ?
				wfMessage( 'rev-deleted-user' )->escaped() :
				$revUser->getName();
		}

		$article->mDisplayTitle = $row->displaytitle ?? '';

		$titleText = $parameters->getParameter( 'shownamespace' ) === true ?
			$title->getPrefixedText() : $title->getText();

		$replaceInTitle = $parameters->getParameter( 'replaceintitle' ) ?? [];
		if ( count( $replaceInTitle ) === 2 ) {
			$titleText = preg_replace(
				$replaceInTitle[0],
				$replaceInTitle[1],
				$titleText
			) ?? $titleText;
		}

		// Chop off title if longer than the 'titlemaxlength' parameter.
		$titleMaxLength = $parameters->getParameter( 'titlemaxlength' );
		if ( $titleMaxLength !== null && mb_strlen( $titleText ) > $titleMaxLength ) {
			$titleText = trim( mb_substr( $titleText, 0, $titleMaxLength ) ) .
				wfMessage( 'ellipsis' )->text();
		}

		$isVideoExtensionEnabled = ExtensionRegistry::getInstance()->isLoaded( 'Video' );
		$shouldEscape = $parameters->getParameter( 'escapelinks' ) &&
			(
				$pageNamespace === NS_CATEGORY ||
				$pageNamespace === NS_FILE ||
				( $isVideoExtensionEnabled && defined( 'NS_VIDEO' ) && $pageNamespace === NS_VIDEO )
			);

		if ( $parameters->getParameter( 'showcurid' ) === true && isset( $row->page_id ) ) {
			$articleLink = '[' . $title->getFullURL( [ 'curid' => $row->page_id ] ) . ' ' .
				htmlspecialchars( $titleText ) . ']';
		} else {
			$articleLink = '[[' . ( $shouldEscape ? ':' : '' ) .
				$title->getFullText() . '|' . htmlspecialchars( $titleText ) . ']]';
		}

		$article->mLink = $articleLink;

		// Get the first character used for category-style output.
		$languageConverter = $services->getLanguageConverterFactory()->getLanguageConverter();
		$sortKey = $row->sortkey ?? $pageTitle;
		$article->mStartChar = $languageConverter->convert( $contentLanguage->firstChar( $sortKey ) );

		$article->mID = (int)( $row->page_id ?? 0 );

		// External link
		$article->mExternalLink = LinkFilter::reverseIndexes( $row->el_to_domain_index ?? '' ) .
			( $row->el_to_path ?? '' );

		// SHOW PAGE_COUNTER
		$article->mCounter = (int)( $row->page_counter ?? 0 );

		// SHOW PAGE_SIZE
		$article->mSize = (int)( $row->page_len ?? 0 );

		// STORE initially selected PAGE
		if ( $parameters->getParameter( 'linksto' ) || $parameters->getParameter( 'linksfrom' ) ) {
			$article->mSelTitle = $row->sel_title ?? 'unknown page';
			$article->mSelNamespace = (int)( $row->sel_ns ?? NS_MAIN );
		}

		// STORE selected image
		if ( $parameters->getParameter( 'imageused' ) ) {
			$article->mImageSelTitle = $row->image_sel_title ?? 'unknown image';
		}

		if ( $parameters->getParameter( 'goal' ) !== 'categories' ) {
			// REVISION SPECIFIED
			if (
				$parameters->getParameter( 'lastrevisionbefore' ) ||
				$parameters->getParameter( 'allrevisionsbefore' ) ||
				$parameters->getParameter( 'firstrevisionsince' ) ||
				$parameters->getParameter( 'allrevisionssince' )
			) {
				$article->mRevision = (int)( $row->rev_id ?? 0 );
				$article->mUser = $revActorName;
				$article->mDate = $row->rev_timestamp ?? '';
				if ( $row->rev_comment_text ?? '' ) {
					$comment = $row->rev_comment_text;
					if ( $row->rev_deleted & RevisionRecord::DELETED_COMMENT ) {
						$comment = wfMessage( 'rev-deleted-comment' )->text();
					}

					// Wrap the edit summary in <nowiki> and escape it to:
					// 1) Prevent any wikitext parsing.
					// 2) Ensure any </nowiki> inside the summary doesn't prematurely close the tag.
					$article->mComment = Html::element( 'nowiki', [], $comment );
				}
			}

			// SHOW "PAGE_TOUCHED" DATE, "FIRSTCATEGORYDATE" OR (FIRST/LAST) EDIT DATE
			$article->mDate = match ( true ) {
				$parameters->getParameter( 'addpagetoucheddate' ) => $row->page_touched ?? '',
				$parameters->getParameter( 'addfirstcategorydate' ) => $row->cl_timestamp ?? '',
				$parameters->getParameter( 'addeditdate' ) => $row->rev_timestamp ?? $row->page_touched ?? '',
				default => $article->mDate,
			};

			// Time zone adjustment
			if ( $article->mDate ) {
				$lang = RequestContext::getMain()->getLanguage();
				$article->mDate = $lang->userAdjust( $article->mDate );
			}

			if ( $article->mDate && $parameters->getParameter( 'userdateformat' ) ) {
				// Apply the userdateformat
				$article->myDate = gmdate(
					$parameters->getParameter( 'userdateformat' ),
					(int)wfTimestamp( TS_UNIX, $article->mDate )
				);
			}

			// CONTRIBUTION, CONTRIBUTOR
			if ( $parameters->getParameter( 'addcontribution' ) ) {
				$article->mContribution = (int)( $row->contribution ?? 0 );
				$contribUser = $userFactory->newFromActorId( (int)( $row->contributor ?? 0 ) );
				$contribUserDeleted = $row->contrib_deleted & RevisionRecord::DELETED_USER;
				$article->mContributor = $contribUser->isHidden() || $contribUserDeleted ?
					wfMessage( 'rev-deleted-user' )->escaped() :
					$contribUser->getName();
				$article->mContrib = substr( '*****************', 0, (int)round( log( $article->mContribution ) ) );
			}

			// USER/AUTHOR(S)
			// Because we are going to do a recursive parse at the end of the output phase
			// we have to generate wiki syntax for linking to a user's homepage.
			if (
				$parameters->getParameter( 'adduser' ) ||
				$parameters->getParameter( 'addauthor' ) ||
				$parameters->getParameter( 'addlasteditor' )
			) {
				$article->mUserLink = "[[User:$revActorName|$revActorName]]";
				$article->mUser = $revActorName;
			}

			// CATEGORY LINKS FROM CURRENT PAGE
			if ( $parameters->getParameter( 'addcategories' ) && !empty( $row->cats ) ) {
				foreach ( explode( ' | ', $row->cats ) as $artCatName ) {
					$text = str_replace( '_', ' ', $artCatName );
					$link = "[[:Category:$artCatName|$text]]";
					$article->mCategoryLinks[] = $link;
					$article->mCategoryTexts[] = $text;
				}
			}

			// PARENT HEADING (category of the page, editor (user) of the page, etc. Depends on ordermethod param).
			if ( $parameters->getParameter( 'headingmode' ) !== 'none' ) {
				$ordermethod = $parameters->getParameter( 'ordermethod' )[0] ?? null;
				switch ( $ordermethod ) {
					case 'category':
						// Count one more page in this heading
						$clTo = $row->cl_to ?? '';
						self::$headings[$clTo] = ( self::$headings[$clTo] ?? 0 ) + 1;

						$text = str_replace( '_', ' ', $clTo );
						$message = wfMessage( 'uncategorizedpages' )->escaped();

						$article->mParentHLink = $clTo === '' ?
							// Uncategorized page (used if ordermethod=category,...)
							"[[:Special:Uncategorizedpages|$message]]" :
							"[[:Category:$clTo|$text]]";
						break;
					case 'user':
						if ( $revActorName !== ActorStore::UNKNOWN_USER_NAME ) {
							self::$headings[$revActorName] = ( self::$headings[$revActorName] ?? 0 ) + 1;
							$article->mParentHLink = "[[User:$revActorName|$revActorName]]";
						}
				}
			}
		}

		return $article;
	}

	/**
	 * Returns all heading information processed from all newly instantiated article objects.
	 */
	public static function getHeadings(): array {
		return self::$headings;
	}

	/**
	 * Reset the headings to their initial state.
	 * Ideally this Article class should not exist and be handled by the built in MediaWiki class.
	 */
	public static function resetHeadings(): void {
		self::$headings = [];
	}

	/**
	 * Get the formatted date for this article if available.
	 */
	public function getDate(): string {
		if ( $this->myDate !== '' ) {
			return $this->myDate;
		}

		if ( $this->mDate !== '' ) {
			$lang = RequestContext::getMain()->getLanguage();
			return $lang->timeanddate( $this->mDate, true );
		}

		return '';
	}
}
