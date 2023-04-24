<?php

namespace MediaWiki\Extension\DynamicPageList3;

use MediaWiki\MediaWikiServices;
use MediaWiki\User\ActorStore;
use RequestContext;
use stdClass;
use Title;

class Article {
	/**
	 * Title
	 *
	 * @var Title
	 */
	public $mTitle;

	/**
	 * Namespace ID
	 *
	 * @var int
	 */
	public $mNamespace = -1;

	/**
	 * Page ID
	 *
	 * @var int
	 */
	public $mID = 0;

	/**
	 * Selected title of initial page.
	 *
	 * @var string
	 */
	public $mSelTitle = '';

	/**
	 * Selected namespace ID of initial page.
	 *
	 * @var int
	 */
	public $mSelNamespace = -1;

	/**
	 * Selected title of image.
	 *
	 * @var string
	 */
	public $mImageSelTitle = '';

	/**
	 * HTML link to page.
	 *
	 * @var string
	 */
	public $mLink = '';

	/**
	 * External link on the page.
	 *
	 * @var string
	 */
	public $mExternalLink = '';

	/**
	 * First character of the page title.
	 *
	 * @var string
	 */
	public $mStartChar = '';

	/**
	 * Heading (link to the associated page) that page belongs to in the list (default '' means no heading)
	 *
	 * @var string
	 */
	public $mParentHLink = '';

	/**
	 * Category links on the page.
	 *
	 * @var array
	 */
	public $mCategoryLinks = [];

	/**
	 * Category names (without link) in the page.
	 *
	 * @var array
	 */
	public $mCategoryTexts = [];

	/**
	 * Number of times this page has been viewed.
	 *
	 * @var int
	 */
	public $mCounter = 0;

	/**
	 * Article length in bytes of wiki text
	 *
	 * @var int
	 */
	public $mSize = 0;

	/**
	 * Timestamp depending on the user's request (can be first/last edit, page_touched, ...)
	 *
	 * @var string|int
	 */
	public $mDate = '';

	/**
	 * Timestamp depending on the user's request, based on user format definition.
	 *
	 * @var string
	 */
	public $myDate = '';

	/**
	 * Revision ID
	 *
	 * @var int
	 */
	public $mRevision = 0;

	/**
	 * Link to editor (first/last, depending on user's request) 's page or contributions if not registered.
	 *
	 * @var string
	 */
	public $mUserLink = '';

	/**
	 * Name of editor (first/last, depending on user's request) or contributions if not registered.
	 *
	 * @var string
	 */
	public $mUser = '';

	/**
	 * Edit Summary(Revision Comment)
	 *
	 * @var string
	 */
	public $mComment = '';

	/**
	 * Number of bytes changed.
	 *
	 * @var int
	 */
	public $mContribution = 0;

	/**
	 * Short string indicating the size of a contribution.
	 *
	 * @var string
	 */
	public $mContrib = '';

	/**
	 * User text of who made the changes.
	 *
	 * @var string
	 */
	public $mContributor = '';

	/**
	 * Article Headings - Maps heading to count (# of pages under each heading).
	 *
	 * @var array
	 */
	private static $headings = [];

	/**
	 * @param Title $title
	 * @param int $namespace
	 */
	public function __construct( Title $title, $namespace ) {
		$this->mTitle = $title;
		$this->mNamespace = $namespace;
	}

	/**
	 * Initialize a new instance from a database row.
	 *
	 * @param stdClass $row
	 * @param Parameters $parameters
	 * @param Title $title
	 * @param int $pageNamespace
	 * @param string $pageTitle
	 * @return self
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
		if ( isset( $row->rev_actor ) && $row->rev_actor !== '0' ) {
			$revActorName = $userFactory->newFromActorId( $row->rev_actor )->getName();
		}

		$titleText = $title->getText();
		if ( $parameters->getParameter( 'shownamespace' ) === true ) {
			$titleText = $title->getPrefixedText();
		}

		$replaceInTitle = $parameters->getParameter( 'replaceintitle' );
		if ( is_array( $replaceInTitle ) && count( $replaceInTitle ) === 2 ) {
			$titleText = preg_replace( $replaceInTitle[0], $replaceInTitle[1], $titleText );
		}

		// Chop off title if longer than the 'titlemaxlen' parameter.
		if ( $parameters->getParameter( 'titlemaxlen' ) !== null && strlen( $titleText ) > $parameters->getParameter( 'titlemaxlen' ) ) {
			$titleText = substr( $titleText, 0, $parameters->getParameter( 'titlemaxlen' ) ) . '...';
		}

		if ( $parameters->getParameter( 'showcurid' ) === true && isset( $row->page_id ) ) {
			$articleLink = '[' . $title->getLinkURL( [ 'curid' => $row->page_id ] ) . ' ' . htmlspecialchars( $titleText ) . ']';
		} else {
			$articleLink = '[[' . ( $parameters->getParameter( 'escapelinks' ) && ( $pageNamespace == NS_CATEGORY || $pageNamespace == NS_FILE ) ? ':' : '' ) . $title->getFullText() . '|' . htmlspecialchars( $titleText ) . ']]';
		}

		$article->mLink = $articleLink;

		$languageConverter = $services->getLanguageConverterFactory()->getLanguageConverter();

		// get first char used for category-style output
		if ( isset( $row->sortkey ) ) {
			$article->mStartChar = $languageConverter->convert( $contentLanguage->firstChar( $row->sortkey ) );
		} else {
			$article->mStartChar = $languageConverter->convert( $contentLanguage->firstChar( $pageTitle ) );
		}

		$article->mID = intval( $row->page_id );

		// External link
		if ( isset( $row->el_to ) ) {
			$article->mExternalLink = $row->el_to;
		}

		// SHOW PAGE_COUNTER
		if ( isset( $row->page_counter ) ) {
			$article->mCounter = intval( $row->page_counter );
		}

		// SHOW PAGE_SIZE
		if ( isset( $row->page_len ) ) {
			$article->mSize = intval( $row->page_len );
		}

		// STORE initially selected PAGE
		if ( is_array( $parameters->getParameter( 'linksto' ) ) && ( count( $parameters->getParameter( 'linksto' ) ) || count( $parameters->getParameter( 'linksfrom' ) ) ) ) {
			if ( !isset( $row->sel_title ) ) {
				$article->mSelTitle = 'unknown page';
				$article->mSelNamespace = 0;
			} else {
				$article->mSelTitle = $row->sel_title;
				$article->mSelNamespace = $row->sel_ns;
			}
		}

		// STORE selected image
		if ( is_array( $parameters->getParameter( 'imageused' ) ) && count( $parameters->getParameter( 'imageused' ) ) > 0 ) {
			if ( !isset( $row->image_sel_title ) ) {
				$article->mImageSelTitle = 'unknown image';
			} else {
				$article->mImageSelTitle = $row->image_sel_title;
			}
		}

		if ( $parameters->getParameter( 'goal' ) != 'categories' ) {
			// REVISION SPECIFIED
			if ( $parameters->getParameter( 'lastrevisionbefore' ) || $parameters->getParameter( 'allrevisionsbefore' ) || $parameters->getParameter( 'firstrevisionsince' ) || $parameters->getParameter( 'allrevisionssince' ) ) {
				$article->mRevision = $row->rev_id;
				$article->mUser = $revActorName;
				$article->mDate = $row->rev_timestamp;

				// $article->mComment = $row->rev_comment;
			}

			// SHOW "PAGE_TOUCHED" DATE, "FIRSTCATEGORYDATE" OR (FIRST/LAST) EDIT DATE
			if ( $parameters->getParameter( 'addpagetoucheddate' ) ) {
				$article->mDate = $row->page_touched;
			} elseif ( $parameters->getParameter( 'addfirstcategorydate' ) ) {
				$article->mDate = $row->cl_timestamp;
			} elseif ( $parameters->getParameter( 'addeditdate' ) && isset( $row->rev_timestamp ) ) {
				$article->mDate = $row->rev_timestamp;
			} elseif ( $parameters->getParameter( 'addeditdate' ) && isset( $row->page_touched ) ) {
				$article->mDate = $row->page_touched;
			}

			// Time zone adjustment
			if ( $article->mDate ) {
				$lang = RequestContext::getMain()->getLanguage();

				$article->mDate = $lang->userAdjust( $article->mDate );
			}

			if ( $article->mDate && $parameters->getParameter( 'userdateformat' ) ) {
				// Apply the userdateformat
				$article->myDate = gmdate( $parameters->getParameter( 'userdateformat' ), (int)wfTimestamp( TS_UNIX, $article->mDate ) );
			}

			// CONTRIBUTION, CONTRIBUTOR
			if ( $parameters->getParameter( 'addcontribution' ) ) {
				$article->mContribution = $row->contribution;

				$article->mContributor = $userFactory->newFromActorId( $row->contributor )->getName();

				$article->mContrib = substr( '*****************', 0, (int)round( log( $row->contribution ) ) );
			}

			// USER/AUTHOR(S)
			// because we are going to do a recursive parse at the end of the output phase
			// we have to generate wiki syntax for linking to a user´s homepage
			if ( $parameters->getParameter( 'adduser' ) || $parameters->getParameter( 'addauthor' ) || $parameters->getParameter( 'addlasteditor' ) ) {
				$article->mUserLink = '[[User:' . $revActorName . '|' . $revActorName . ']]';
				$article->mUser = $revActorName;
			}

			// CATEGORY LINKS FROM CURRENT PAGE
			if ( $parameters->getParameter( 'addcategories' ) && ( $row->cats ) ) {
				$artCatNames = explode( ' | ', $row->cats );
				foreach ( $artCatNames as $artCatName ) {
					$article->mCategoryLinks[] = '[[:Category:' . $artCatName . '|' . str_replace( '_', ' ', $artCatName ) . ']]';
					$article->mCategoryTexts[] = str_replace( '_', ' ', $artCatName );
				}
			}

			// PARENT HEADING (category of the page, editor (user) of the page, etc. Depends on ordermethod param)
			if ( $parameters->getParameter( 'headingmode' ) != 'none' ) {
				switch ( $parameters->getParameter( 'ordermethod' )[0] ) {
					case 'category':
						// Count one more page in this heading
						self::$headings[$row->cl_to] = ( isset( self::$headings[$row->cl_to] ) ? self::$headings[$row->cl_to] + 1 : 1 );
						if ( $row->cl_to == '' ) {
							// uncategorized page (used if ordermethod=category,...)
							$article->mParentHLink = '[[:Special:Uncategorizedpages|' . wfMessage( 'uncategorizedpages' ) . ']]';
						} else {
							$article->mParentHLink = '[[:Category:' . $row->cl_to . '|' . str_replace( '_', ' ', $row->cl_to ) . ']]';
						}

						break;
					case 'user':
						if ( $revActorName && $revActorName !== ActorStore::UNKNOWN_USER_NAME ) {
							self::$headings[$revActorName] = ( isset( self::$headings[$revActorName] ) ? self::$headings[$revActorName] + 1 : 1 );

							$article->mParentHLink = '[[User:' . $revActorName . '|' . $revActorName . ']]';
						}

						break;
				}
			}
		}

		return $article;
	}

	/**
	 * Returns all heading information processed from all newly instantiated article objects.
	 *
	 * @return array
	 */
	public static function getHeadings() {
		return self::$headings;
	}

	/**
	 * Reset the headings to their initial state.
	 * Ideally this Article class should not exist and be handled by the built in MediaWiki class.
	 */
	public static function resetHeadings() {
		self::$headings = [];
	}

	/**
	 * Get the formatted date for this article if available.
	 *
	 * @return string formatted string or empty string if none set.
	 */
	public function getDate() {
		if ( $this->myDate ) {
			return $this->myDate;
		} elseif ( $this->mDate ) {
			$lang = RequestContext::getMain()->getLanguage();

			return $lang->timeanddate( $this->mDate, true );
		}

		return '';
	}
}
