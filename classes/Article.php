<?php
/**
 * DynamicPageList
 * DPL Article Class
 *
 * @author		IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license		GPL
 * @package		DynamicPageList
 *
**/
namespace DPL;

class Article {
	public $mTitle = ''; 		// title
	public $mNamespace = -1;	// namespace (number)
	public $mID = 0;			// page_id
	public $mSelTitle = '';    // selected title of initial page
	public $mSelNamespace = -1;// selected namespace (number) of initial page
	public $mImageSelTitle = ''; // selected title of image
	public $mLink = ''; 		// html link to page
	public $mExternalLink = '';// external link on the page
	public $mStartChar = ''; 	// page title first char
	public $mParentHLink = ''; // heading (link to the associated page) that page belongs to in the list (default '' means no heading)
	public $mCategoryLinks = array(); // category links in the page
	public $mCategoryTexts = array(); // category names (without link) in the page
	public $mCounter = ''; 	// Number of times this page has been viewed
	public $mSize = ''; 		// Article length in bytes of wiki text
	public $mDate = ''; 		// timestamp depending on the user's request (can be first/last edit, page_touched, ...)
	public $myDate = ''; 		// the same, based on user format definition
	public $mRevision = '';    // the revision number if specified
	public $mUserLink = ''; 	// link to editor (first/last, depending on user's request) 's page or contributions if not registered
	public $mUser = ''; 		// name of editor (first/last, depending on user's request) or contributions if not registered
	public $mComment = ''; 	// revision comment / edit summary
	public $mContribution= ''; // number of bytes changed
	public $mContrib= '';      // short string indicating the size of a contribution
	public $mContributor= '';  // user who made the changes

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @param	string	Title
	 * @param	integer	Namespace
	 * @return	void
	 */
	public function __construct($title, $namespace) {
		$this->mTitle     = $title;
		$this->mNamespace = $namespace;
	}

	/**
	 * Initialize a new instance from a database row.
	 *
	 * @access	public
	 * @param	array	Database Row
	 * @param	object	\DPL\Parameters Object
	 * @param	object	Mediawiki Title Object
	 * @param	integer	Page Namespace ID
	 * @return	object	\DPL\Article Object
	 */
	static public function newFromRow($row, Parameters $parameters, \Title $title, $pageNamespace) {
		global $wgLang, $wgContLang;

		$article = new Article($title, $pageNamespace);

		$titleText = $title->getText();
		if ($parameters->getParameter('shownamespace') === true) {
			$titleText = $title->getPrefixedText();
		}
		if ($parameters->getParameter('replaceintitle') !== null) {
			$titleText = preg_replace($parameters->getParameter('replaceintitle')[0], $parameters->getParameter('replaceintitle')[1], $titleText);
		}

		//Chop off title if longer than the 'titlemaxlen' parameter.
		if ($parameters->getParameter('titlemaxlen') !== null && strlen($titleText) > $parameters->getParameter('titlemaxlen')) {
			$titleText = substr($titleText, 0, $parameters->getParameter('titlemaxlen')).'...';
		}
		if ($parameters->getParameter('showcurid') === true && isset($row['page_id'])) {
			$articleLink = '[{{fullurl:'.$title->getText().'|curid='.$row['page_id'].'}} '.htmlspecialchars($titleText).']';
		} elseif (!$parameters->getParameter('escapelinks') || ($pageNamespace != NS_CATEGORY && $pageNamespace != NS_FILE)) {
			//Links to categories or images need an additional ":"
			$articleLink = '[['.$title->getPrefixedText().'|'.$wgContLang->convert($titleText).']]';
		} else {
			$articleLink = '[{{fullurl:'.$title->getText().'}} '.htmlspecialchars($titleText).']';
		}

		$article->mLink = $articleLink;

		//get first char used for category-style output
		if (isset($row['sortkey'])) {
			$article->mStartChar = $wgContLang->convert($wgContLang->firstChar($row['sortkey']));
		}
		if (isset($row['sortkey'])) {
			$article->mStartChar = $wgContLang->convert($wgContLang->firstChar($row['sortkey']));
		} else {
			$article->mStartChar = $wgContLang->convert($wgContLang->firstChar($pageTitle));
		}

		$article->mID = intval($row['page_id']);

		//External link
		if (isset($row['el_to'])) {
			$article->mExternalLink = $row['el_to'];
		}

		//SHOW PAGE_COUNTER
		if (isset($row['page_counter'])) {
			$article->mCounter = $row['page_counter'];
		}

		//SHOW PAGE_SIZE
		if (isset($row['page_len'])) {
			$article->mSize = $row['page_len'];
		}
		//STORE initially selected PAGE
		if (count($parameters->getParameter('linksto')) || count($parameters->getParameter('linksfrom'))) {
			if (!isset($row['sel_title'])) {
				$article->mSelTitle     = 'unknown page';
				$article->mSelNamespace = 0;
			} else {
				$article->mSelTitle     = $row['sel_title'];
				$article->mSelNamespace = $row['sel_ns'];
			}
		}

		//STORE selected image
		if (count($parameters->getParameter('imageused')) > 0) {
			if (!isset($row['image_sel_title'])) {
				$article->mImageSelTitle = 'unknown image';
			} else {
				$article->mImageSelTitle = $row['image_sel_title'];
			}
		}

		if ($parameters->getParameter('goal') != 'categories') {
			//REVISION SPECIFIED
			if ($parameters->getParameter('lastrevisionbefore') || $parameters->getParameter('allrevisionsbefore') || $parameters->getParameter('firstrevisionsince') || $parameters->getParameter('allrevisionssince')) {
				$article->mRevision = $row['rev_id'];
				$article->mUser     = $row['rev_user_text'];
				$article->mDate     = $row['rev_timestamp'];
			}

			//SHOW "PAGE_TOUCHED" DATE, "FIRSTCATEGORYDATE" OR (FIRST/LAST) EDIT DATE
			if ($parameters->getParameter('addpagetoucheddate')) {
				$article->mDate = $row['page_touched'];
			} elseif ($parameters->getParameter('addfirstcategorydate')) {
				$article->mDate = $row['cl_timestamp'];
			} elseif ($parameters->getParameter('addeditdate') && isset($row['rev_timestamp'])) {
				$article->mDate = $row['rev_timestamp'];
			} elseif ($parameters->getParameter('addeditdate') && isset($row['page_touched'])) {
				$article->mDate = $row['page_touched'];
			}

			//Time zone adjustment
			if ($article->mDate) {
				$article->mDate = $wgLang->userAdjust($article->mDate);
			}

			if ($article->mDate && $parameters->getParameter('userdateformat')) {
				//Apply the userdateformat
				$article->myDate = gmdate($parameters->getParameter('userdateformat'), wfTimeStamp(TS_UNIX, $article->mDate));
			}
			// CONTRIBUTION, CONTRIBUTOR
			if ($parameters->getParameter('addcontribution')) {
				$article->mContribution = $row['contribution'];
				$article->mContributor  = $row['contributor'];
				$article->mContrib      = substr('*****************', 0, round(log($row['contribution'])));
			}


			//USER/AUTHOR(S)
			// because we are going to do a recursive parse at the end of the output phase
			// we have to generate wiki syntax for linking to a user´s homepage
			if ($parameters->getParameter('adduser') || $parameters->getParameter('addauthor') || $parameters->getParameter('addlasteditor') || $parameters->getParameter('lastrevisionbefore') || $parameters->getParameter('allrevisionsbefore') || $parameters->getParameter('firstrevisionsince') || $parameters->getParameter('allrevisionssince')) {
				$article->mUserLink = '[[User:'.$row['rev_user_text'].'|'.$row['rev_user_text'].']]';
				$article->mUser     = $row['rev_user_text'];
				$article->mComment  = $row['rev_comment'];
			}

			//CATEGORY LINKS FROM CURRENT PAGE
			if ($parameters->getParameter('addcategories') && ($row['cats'])) {
				$artCatNames = explode(' | ', $row['cats']);
				foreach ($artCatNames as $artCatName) {
					$article->mCategoryLinks[] = '[[:Category:'.$artCatName.'|'.str_replace('_', ' ', $artCatName).']]';
					$article->mCategoryTexts[] = str_replace('_', ' ', $artCatName);
				}
			}
			// PARENT HEADING (category of the page, editor (user) of the page, etc. Depends on ordermethod param)
			if ($parameters->getParameter('headingmode') != 'none') {
				switch ($parameters->getParameter('ordermethod')[0]) {
					case 'category':
						//Count one more page in this heading
						$headings[$row['cl_to']] = isset($headings[$row['cl_to']]) ? $headings[$row['cl_to']] + 1 : 1;
						if ($row['cl_to'] == '') {
							//uncategorized page (used if ordermethod=category,...)
							$article->mParentHLink = '[[:Special:Uncategorizedpages|'.wfMsg('uncategorizedpages').']]';
						} else {
							$article->mParentHLink = '[[:Category:'.$row['cl_to'].'|'.str_replace('_', ' ', $row['cl_to']).']]';
						}
						break;
					case 'user':
						$headings[$row['rev_user_text']] = isset($headings[$row['rev_user_text']]) ? $headings[$row['rev_user_text']] + 1 : 1;
						if ($row['rev_user'] == 0) { //anonymous user
							$article->mParentHLink = '[[User:'.$row['rev_user_text'].'|'.$row['rev_user_text'].']]';

						} else {
							$article->mParentHLink = '[[User:'.$row['rev_user_text'].'|'.$row['rev_user_text'].']]';
						}
						break;
				}
			}
		}

		return $article;
	}
}
