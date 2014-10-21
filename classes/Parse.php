<?php
/**
 * DynamicPageList
 * DPL Parse Class
 *
 * @author		IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license		GPL
 * @package		DynamicPageList
 *
 **/
namespace DPL;

class Parse {
	/**
	 * Mediawiki Database Object
	 *
	 * @var		object
	 */
	private $DB = null;

	/**
	 * \DPL\Parameters Object
	 *
	 * @var		object
	 */
	private $parameters = null;

	/**
	 * \DPL\Logger Object
	 *
	 * @var		object
	 */
	private $logger = null;

	/**
	 * Array of prequoted table names.
	 *
	 * @var		object
	 */
	private $tableNames = null;

	/**
	 * Array of possible URL arguments.
	 *
	 * @var		array
	 */
	private $urlArguments = [
		'DPL_offset',
		'DPL_count',
		'DPL_fromTitle',
		'DPL_findTitle',
		'DPL_toTitle'
	];

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		global $wgRequest, $wgLang, $wgContLang;

		$this->DB			= wfGetDB(DB_SLAVE);
		$this->parameters	= new Parameters();
		$this->logger		= new Logger($this->parameters->getData('debug')['default']);
		$this->tableNames	= Query::getTableNames();
		$this->wgRequest	= $wgRequest;
		$this->wgLang		= $wgLang;
		$this->wgContLang	= $wgContLang;

		$this->getUrlArgs();
	}

	/**
	 * The real callback function for converting the input text to wiki text output
	 *
	 * @access	public
	 * @param	string	Raw User Input
	 * @param	object	Parser object.
	 * @param	array	Reset Booleans(@TODO: Redo this documentation after fixing reset parameter.)
	 * @param	boolean	[Optional] Call as a parser tag
	 * @return	string	Wiki/HTML Output
	 */
	public function parse($input, \Parser $parser, &$bReset, $isParserTag = true) {
		wfProfileIn(__METHOD__);

		//Check that we are not in an infinite transclusion loop
		if (isset($parser->mTemplatePath[$parser->mTitle->getPrefixedText()])) {
			$this->logger->addMessage(\DynamicPageListHooks::WARN_TRANSCLUSIONLOOP, $parser->mTitle->getPrefixedText());
			return $this->getFullOutput();
		}

		//Check if DPL shall only be executed from protected pages.
		if (Config::getSetting('runFromProtectedPagesOnly') === true && !$parser->mTitle->isProtected('edit')) {
			//Ideally we would like to allow using a DPL query if the query istelf is coded on a template page which is protected. Then there would be no need for the article to be protected.  However, how can one find out from which wiki source an extension has been invoked???
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_NOTPROTECTED, $parser->mTitle->getPrefixedText());
			return $this->getFullOutput();
		}

		/************************************/
		/* Check for URL Arguments in Input */
		/************************************/
		if (strpos($input, '{%DPL_') >= 0) {
			for ($i = 1; $i <= 5; $i++) {
				$this->urlArguments[] = 'DPL_arg'.$i;
			}
		}
		$input = $this->resolveUrlArguments($input, $this->urlArguments);

		$offset = $this->wgRequest->getInt('DPL_offset', $this->parameters->getData('offset')['default']);

		$originalInput = $input;

		$bDPLRefresh = ($this->wgRequest->getVal('DPL_refresh', '') == 'yes');

		//Options
		$DPLCache        = '';
		$DPLCachePath    = '';

		/***************************************/
		/* User Input preparation and parsing. */
		/***************************************/
		$cleanParameters = $this->prepareUserInput($input);
		$cleanParameters = $this->parameters->sortByPriority($cleanParameters);
		$this->parameters->setParameter('includeuncat', false); // to check if pseudo-category of Uncategorized pages is included

		foreach ($cleanParameters as $parameter => $option) {
			//Parameter functions return true or false.  The full parameter data will be passed into the Query object later.
			if ($this->parameters->$parameter($option) === false) {
				//Do not build this into the output just yet.  It will be collected at the end.
				$this->logger->addMessage(\DynamicPageListHooks::WARN_WRONGPARAM, $parameter, $option);
			}
		}

		/*************************/
		/* Execute and Exit Only */
		/*************************/
		if ($this->parameters->getParameter('execandexit')) {
			//@TODO: Fix up this parameter's arguments in ParameterData and how it handles the response.
			//The keyword "geturlargs" is used to return the Url arguments and do nothing else.
			if ($sExecAndExit == 'geturlargs') {
				return '';
			}
			//In all other cases we return the value of the argument (which may contain parser function calls)
			return $sExecAndExit;
		}

		/*******************/
		/* Are we caching? */
		/*******************/
		if (!$this->parameters->getParameter('allowcachedresults')) {
			$parser->disableCache();
		}
		if ($DPLCache != '') {
			$cache = wfGetCache(Config::getSetting('cacheType'));

			// when the page containing the DPL statement is changed we must recreate the cache as the DPL statement may have changed
			// otherwise we accept thecache if it is not too old
			if (!$bDPLRefresh && file_exists($cacheFile)) {
				// find out if cache is acceptable or too old
				$diff = time() - filemtime($cacheFile);
				if ($diff <= $iDPLCachePeriod) {
					$cachedOutput    = file_get_contents($cacheFile);
					$cachedOutputPos = strpos($cachedOutput, "+++\n");
					// when submitting a page we check if the DPL statement has changed
					if ($this->wgRequest->getVal('action', 'view') != 'submit' || ($originalInput == substr($cachedOutput, 0, $cachedOutputPos))) {
						$cacheTimeStamp = self::prettyTimeStamp(date('YmdHis', filemtime($cacheFile)));
						$cachePeriod    = self::durationTime($iDPLCachePeriod);
						$diffTime       = self::durationTime($diff);
						$output .= substr($cachedOutput, $cachedOutputPos + 4);
						if ($this->logger->iDebugLevel >= 2) {
							$output .= "{{Extension DPL cache|mode=get|page={{FULLPAGENAME}}|cache=$DPLCache|date=$cacheTimeStamp|now=" . date('H:i:s') . "|age=$diffTime|period=$cachePeriod|offset=$offset}}";
						}
						// ignore further parameters, stop processing, return cache content
						return $output;
					}
				}
			}
		}

		//Construct internal keys for TableRow according to the structure of "include".  This will be needed in the output phase.
		if ($this->parameters->getParameter('seclabels') !== null) {
			$this->parameters->setParameter('tablerow', $this->updateTableRowKeys($this->parameters->getParameter('tablerow'), $this->parameters->getParameter('seclabels')));
		}

		if ($isParserTag === false) {
			// in tag mode 'eliminate' is the same as 'reset' for tpl,cat,img
			if ($bReset[5]) {
				$bReset[1] = true;
				$bReset[5] = false;
			}
			if ($bReset[6]) {
				$bReset[2] = true;
				$bReset[6] = false;
			}
			if ($bReset[7]) {
				$bReset[3] = true;
				$bReset[7] = false;
			}
		} else {
			if ($bReset[1]) {
				\DynamicPageListHooks::$createdLinks['resetTemplates'] = true;
			}
			if ($bReset[2]) {
				\DynamicPageListHooks::$createdLinks['resetCategories'] = true;
			}
			if ($bReset[3]) {
				\DynamicPageListHooks::$createdLinks['resetImages'] = true;
			}
		}
		if (($isParserTag === false && $bReset[0]) || $isParserTag === true) {
			if ($bReset[0]) {
				\DynamicPageListHooks::$createdLinks['resetLinks'] = true;
			}
			// register a hook to reset links which were produced during parsing DPL output
			global $wgHooks;
			if (!in_array('DynamicPageListHooks::endReset', $wgHooks['ParserAfterTidy'])) {
				$wgHooks['ParserAfterTidy'][] = 'DynamicPageListHooks::endReset';
			}
		}

		/**************************/
		/* Check Errors and Query */
		/**************************/
		$errors = $this->doQueryErrorChecks();
		if ($errors === false) {
			//WHAT HAS HAPPENED OH NOOOOOOOOOOOOO.
			return $this->getFullOutput();
		}

		$calcRows = false;
		if (!Config::getSetting('allowUnlimitedResults') && $this->parameters->getParameter('goal') != 'categories' && strpos($this->parameters->getParameter('resultsheader').$this->parameters->getParameter('noresultsheader').$this->parameters->getParameter('resultsfooter'), '%TOTALPAGES%') !== false) {
			$calcRows = true;
		}

		// JOIN ...
		if ($sSqlClHeadTable != '' || $sSqlClTableForGC != '') {
			$b2tables = ($sSqlClHeadTable != '') && ($sSqlClTableForGC != '');
			$sSqlSelectFrom .= ' LEFT OUTER JOIN '.$sSqlClHeadTable.($b2tables ? ', ' : '').$sSqlClTableForGC.' ON ('.$sSqlCond_page_cl_head.($b2tables ? ' AND ' : '').$sSqlCond_page_cl_gc.')';
		}

		try {
			$this->query = new Query($this->parameters);
			$result = $this->query->buildAndSelect($calcRows);
		} catch (MWException $e) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_SQLBUILDERROR, $e->getMessage());
			return $this->getFullOutput();
		}

		/*********************/
		/* Handle No Results */
		/*********************/
		$this->addOutput('{{Extension DPL}}');

		if ($this->DB->numRows($result) <= 0) {
			$replacementVariables = [];
			$replacementVariables['%TOTALPAGES%'] = 0;
			$replacementVariables['%PAGES%'] = 0;
			if ($this->parameters->getParameter('noresultsheader') !== null) {
				$noResultsHeader = $this->parameters->getParameter('noresultsheader');
				$this->setHeader($this->replaceVariables($this->parameters->getParameter('noresultsheader'), $replacementVariables));
			}
			if ($this->parameters->getParameter('noresultsfooter') !== null) {
				$this->setHeader($this->replaceVariables($this->parameters->getParameter('noresultsfooter'), $replacementVariables));
			}
			$this->DB->freeResult($result);
			return $this->getFullOutput();
		}

		$articles = $this->processQueryResults($result, $parser);

		$foundRows = null;
		if ($calcRows) {
			$foundRows = $this->query->getFoundRows();
		}

		// backward scrolling: if the user specified titleLE we reverse the output order
		if ($this->parameters->getParameter('titlelt') && !$this->parameters->getParameter('titlegt') && $this->parameters->getParameter('order') == 'descending') {
			$articles = array_reverse($articles);
		}

		// special sort for card suits (Bridge)
		if ($this->parameters->getParameter('ordersuitsymbols')) {
			$articles = self::cardSuitSort($articles);
		}


		/*******************/
		/* Generate Output */
		/*******************/
		$listMode = new ListMode(
			$this->parameters->getParameter('pagelistmode'),
			$this->parameters->getParameter('secseparators'),
			$this->parameters->getParameter('multisecseparators'),
			$this->parameters->getParameter('inltxt'),
			$this->parameters->getParameter('listhtmlattr'),
			$this->parameters->getParameter('itemhtmlattr'),
			$this->parameters->getParameter('listseparators'),
			$this->parameters->getParameter('offset'),
			$this->parameters->getParameter('dominantsection')
		);

		$hListMode = new ListMode(
			$this->parameters->getParameter('hlistmode'),
			$this->parameters->getParameter('secseparators'),
			$this->parameters->getParameter('multisecseparators'),
			'',
			$this->parameters->getParameter('hlisthtmlattr'),
			$this->parameters->getParameter('hitemhtmlattr'),
			$this->parameters->getParameter('listseparators'),
			$this->parameters->getParameter('offset'),
			$this->parameters->getParameter('dominantsection')
		);

		$dpl = new DynamicPageList(
			$headings,
			$this->parameters->getParameter('headingcount'),
			$this->parameters->getParameter('columns'),
			$this->parameters->getParameter('rows'),
			$this->parameters->getParameter('rowsize'),
			$this->parameters->getParameter('rowcolformat'),
			$articles,
			$this->parameters->getParameter('ordermethods')[0],
			$hListMode,
			$listMode,
			$this->parameters->getParameter('escapelinks'),
			$this->parameters->getParameter('addexternallink'),
			$this->parameters->getParameter('incpage'),
			$this->parameters->getParameter('includemaxlen'),
			$this->parameters->getParameter('seclabels'),
			$this->parameters->getParameter('seclabelsmatch'),
			$this->parameters->getParameter('seclabelsnotmatch'),
			$this->parameters->getParameter('incparsed'),
			$parser,
			$logger,
			$this->parameters->getParameter('replaceintitle'),
			$this->parameters->getParameter('titlemaxlen'),
			$defaultTemplateSuffix,
			$this->parameters->getParameter('tablerow'),
			$this->parameters->getParameter('includetrim'),
			$this->parameters->getParameter('tablesortcol'),
			$this->parameters->getParameter('updaterules'),
			$this->parameters->getParameter('deleterules')
		);

		if ($foundRows === null) {
			$foundRows = intval($dpl->getRowCount());
		}
		$this->addOutput($dpl->getText());

		/*******************************/
		/* Start Headers/Footers       */
		/*******************************/
		$this->setHeader($this->parameters->getParameter('resultsheader'));
		$this->setFooter($this->parameters->getParameter('resultsfooter'));
		if ($this->parameters->getParameter('warncachedresults')) {
			//@TODO: Better way to add the cache warning?
			$this->setHeader('{{DPL Cache Warning}}'.$this->getHeader());
		}
		if ($this->logger->iDebugLevel == 5) {
			$this->logger->iDebugLevel = 2;
			$this->setHeader('<pre><nowiki>'.$this->getHeader());
			$this->setFooter($this->setFooter().'</nowiki></pre>');
		}

		$replacementVariables = [];
		$replacementVariables['%TOTALPAGES%'] = $foundRows;
		$replacementVariables['%VERSION%'] = DPL_VERSION;
		$header = $this->parameters->getParameter('resultsheader');
		$footer = $this->parameters->getParameter('resultsfooter');
		if ($foundRows === 1) {
			$replacementVariables['%PAGES%'] = 1;
			//Only override header and footers if specified.
			if ($this->parameters->getParameter('oneresultheader') !== null) {
				$footer = $this->parameters->getParameter('oneresultheader');
			}
			if ($this->parameters->getParameter('oneresultfooter') !== null) {
				$footer = $this->parameters->getParameter('oneresultfooter');
			}
		} elseif ($foundRows === 0) {
			$replacementVariables['%PAGES%'] = $dpl->getRowCount();
			//Only override header and footers if specified.
			if ($this->parameters->getParameter('noresultsheader') !== null) {
				$footer = $this->parameters->getParameter('noresultsheader');
			}
			if ($this->parameters->getParameter('noresultsfooter') !== null) {
				$footer = $this->parameters->getParameter('noresultsfooter');
			}
		}

		// replace %DPLTIME% by execution time and timestamp in header and footer
		$nowTimeStamp   = date('Y/m/d H:i:s');
		$dplElapsedTime = sprintf('%.3f sec.', microtime(true) - $dplStartTime);
		$replacementVariables['%DPLTIME%'] = "{$dplElapsedTime} ({$nowTimeStamp})";

		// replace %LASTTITLE% / %LASTNAMESPACE% by the last title found in header and footer
		if (($n = count($articles)) > 0) {
			$firstNamespaceFound = str_replace(' ', '_', $articles[0]->mTitle->getNamespace());
			$firstTitleFound     = str_replace(' ', '_', $articles[0]->mTitle->getText());
			$lastNamespaceFound  = str_replace(' ', '_', $articles[$n - 1]->mTitle->getNamespace());
			$lastTitleFound      = str_replace(' ', '_', $articles[$n - 1]->mTitle->getText());
		}
		$replacementVariables['%FIRSTNAMESPACE%'] = $firstNamespaceFound;
		$replacementVariables['%FIRSTTITLE%'] = $firstTitleFound;
		$replacementVariables['%LASTNAMESPACE%'] = $lastNamespaceFound;
		$replacementVariables['%LASTTITLE%'] = $lastTitleFound;
		$replacementVariables['%SCROLLDIR%'] = $scrollDir;

		$this->setHeader($this->replaceVariables($header, $replacementVariables));
		$this->setFooter($this->replaceVariables($footer, $replacementVariables));

		self::defineScrollVariables($firstNamespaceFound, $firstTitleFound, $lastNamespaceFound, $lastTitleFound, $scrollDir, $this->parameters->getParameter('count'), $replacementVariables['%DPLTIME%'], $foundRows, $dpl->getRowCount());

		// save generated wiki text to dplcache page if desired

		if ($DPLCache != '') {
			if (!is_writeable($cacheFile)) {
				wfMkdirParents(dirname($cacheFile));
			} else if (($this->parameters->getParameter('dplrefresh') || $this->wgRequest->getVal('action', 'view') == 'submit') && strpos($DPLCache, '/') > 0 && strpos($DPLCache, '..') === false) {
				// if the cache file contains a path and the user requested a refesh (or saved the file) we delete all brothers
				wfRecursiveRemoveDir(dirname($cacheFile));
				wfMkdirParents(dirname($cacheFile));
			}
			$cacheTimeStamp = self::prettyTimeStamp(date('YmdHis'));
			$cFile          = fopen($cacheFile, 'w');
			fwrite($cFile, $originalInput);
			fwrite($cFile, "+++\n");
			fwrite($cFile, $output);
			fclose($cFile);
			$dplElapsedTime = time() - $dplStartTime;
			if ($this->logger->iDebugLevel >= 2) {
				$output .= "{{Extension DPL cache|mode=update|page={{FULLPAGENAME}}|cache=$DPLCache|date=$cacheTimeStamp|age=0|now=" . date('H:i:s') . "|dpltime=$dplElapsedTime|offset=$offset}}";
			}
			$parser->disableCache();
		}

		// The following requires an extra parser step which may consume some time
		// we parse the DPL output and save all references found in that output in a global list
		// in a final user exit after the whole document processing we eliminate all these links
		// we use a local parser to avoid interference with the main parser

		if ($bReset[4] || $bReset[5] || $bReset[6] || $bReset[7]) {
			global $wgHooks;
			//Register a hook to reset links which were produced during parsing DPL output
			if (!in_array('DynamicPageListHooks::endEliminate', $wgHooks['ParserAfterTidy'])) {
				$wgHooks['ParserAfterTidy'][] = 'DynamicPageListHooks::endEliminate';
			}

			//Use a new parser to handle rendering.
			$localParser = new \Parser();
			$parserOutput = $localParser->parse($output, $parser->mTitle, $parser->mOptions);
		}
		if ($bReset[4]) { // LINKS
			// we trigger the mediawiki parser to find links, images, categories etc. which are contained in the DPL output
			// this allows us to remove these links from the link list later
			// If the article containing the DPL statement itself uses one of these links they will be thrown away!
			\DynamicPageListHooks::$createdLinks[0] = array();
			foreach ($parserOutput->getLinks() as $nsp => $link) {
				\DynamicPageListHooks::$createdLinks[0][$nsp] = $link;
			}
		}
		if ($bReset[5]) { // TEMPLATES
			\DynamicPageListHooks::$createdLinks[1] = array();
			foreach ($parserOutput->getTemplates() as $nsp => $tpl) {
				\DynamicPageListHooks::$createdLinks[1][$nsp] = $tpl;
			}
		}
		if ($bReset[6]) { // CATEGORIES
			\DynamicPageListHooks::$createdLinks[2] = $parserOutput->mCategories;
		}
		if ($bReset[7]) { // IMAGES
			\DynamicPageListHooks::$createdLinks[3] = $parserOutput->mImages;
		}

		wfProfileOut(__METHOD__);

		return $this->getFullOutput();
	}

	/**
	 * Process Query Results
	 *
	 * @access	private
	 * @param	object	Mediawiki Result Object
	 * @param	object	Mediawiki Parser Object
	 * @return	array	Array of Article objects.
	 */
	private function processQueryResults($result, \Parser $parser) {
		/*******************************/
		/* Random Count Pick Generator */
		/*******************************/
		$randomCount = $this->parameters->getParameter('randomcount');
		if ($randomCount > 0) {
			$nResults = $this->DB->numRows($result);
			//mt_srand() seeding was removed due to PHP 5.2.1 and above no longer generating the same sequence for the same seed.
			//Constrain the total amount of random results to not be greater than the total results.
			if ($randomCount > $nResults) {
				$randomCount = $nResults;
			}

			//This is 50% to 150% faster than the old while (true) version that could keep rechecking the same random key over and over again.
			//Generate pick numbers for results.
			$pick = range(1, $nResults);
			//Shuffle the pick numbers.
			shuffle($pick);
			//Select pick numbers from the beginning to the maximum of $randomCount.
			$pick = array_slice($pick, 0, $randomCount);
		}

		$headings = []; //Maps heading to count (# of pages under each heading)
		$articles = [];

		/*******************************/
		/* Article Generation          */
		/*******************************/
		while ($row = $result->fetchRow()) {
			$i++;

			//In random mode skip articles which were not chosen.
			if ($randomCount > 0 && !in_array($i, $pick)) {
				continue;
			}

			if ($this->parameters->getParameter('goal') == 'categories') {
				$pageNamespace = 14; // CATEGORY
				$pageTitle     = $row['cl_to'];
			} else if ($this->parameters->getParameter('openreferences')) {
				if (count($this->parameters->getParameter('imagecontainer')) > 0) {
					$pageNamespace = NS_FILE;
					$pageTitle     = $row['il_to'];
				} else {
					// maybe non-existing title
					$pageNamespace = $row['pl_namespace'];
					$pageTitle     = $row['pl_title'];
				}
			} else {
				// existing PAGE TITLE
				$pageNamespace = $row['page_namespace'];
				$pageTitle     = $row['page_title'];
			}

			// if subpages are to be excluded: skip them
			if (!$this->parameters->getParameter('includesubpages') && (!(strpos($pageTitle, '/') === false))) {
				continue;
			}

			$title     = \Title::makeTitle($pageNamespace, $pageTitle);
			$thisTitle = $parser->getTitle();

			// block recursion: avoid to show the page which contains the DPL statement as part of the result
			if ($this->parameters->getParameter('skipthispage') && $thisTitle->equals($title)) {
				continue;
			}

			$dplArticle = new Article($title, $pageNamespace);
			//PAGE LINK
			$sTitleText = $title->getText();
			if ($this->parameters->getParameter('shownamespace')) {
				$sTitleText = $title->getPrefixedText();
			}
			if ($this->parameters->getParameter('replaceintitle')[0] != '') {
				$sTitleText = preg_replace($this->parameters->getParameter('replaceintitle')[0], $this->parameters->getParameter('replaceintitle')[1], $sTitleText);
			}

			//chop off title if "too long"
			if (is_numeric($this->parameters->getParameter('titlemaxlen')) && strlen($sTitleText) > $this->parameters->getParameter('titlemaxlen')) {
				$sTitleText = substr($sTitleText, 0, $this->parameters->getParameter('titlemaxlen')) . '...';
			}
			if ($this->parameters->getParameter('showcurid') && isset($row['page_id'])) {
				$articleLink = '[{{fullurl:' . $title->getText() . '|curid=' . $row['page_id'] . '}} ' . htmlspecialchars($sTitleText) . ']';
			} else if (!$this->parameters->getParameter('escapelinks') || ($pageNamespace != NS_CATEGORY && $pageNamespace != NS_FILE)) {
				// links to categories or images need an additional ":"
				$articleLink = '[[' . $title->getPrefixedText() . '|' . $this->wgContLang->convert($sTitleText) . ']]';
			} else {
				$articleLink = '[{{fullurl:' . $title->getText() . '}} ' . htmlspecialchars($sTitleText) . ']';
			}

			$dplArticle->mLink = $articleLink;

			//get first char used for category-style output
			if (isset($row['sortkey'])) {
				$dplArticle->mStartChar = $this->wgContLang->convert($this->wgContLang->firstChar($row['sortkey']));
			}
			if (isset($row['sortkey'])) {
				$dplArticle->mStartChar = $this->wgContLang->convert($this->wgContLang->firstChar($row['sortkey']));
			} else {
				$dplArticle->mStartChar = $this->wgContLang->convert($this->wgContLang->firstChar($pageTitle));
			}

			// page_id
			if (isset($row['page_id'])) {
				$dplArticle->mID = $row['page_id'];
			} else {
				$dplArticle->mID = 0;
			}

			// external link
			if (isset($row['el_to'])) {
				$dplArticle->mExternalLink = $row['el_to'];
			}

			//SHOW PAGE_COUNTER
			if (isset($row['page_counter'])) {
				$dplArticle->mCounter = $row['page_counter'];
			}

			//SHOW PAGE_SIZE
			if (isset($row['page_len'])) {
				$dplArticle->mSize = $row['page_len'];
			}
			//STORE initially selected PAGE
			if (count($aLinksTo) > 0 || count($aLinksFrom) > 0) {
				if (!isset($row['sel_title'])) {
					$dplArticle->mSelTitle     = 'unknown page';
					$dplArticle->mSelNamespace = 0;
				} else {
					$dplArticle->mSelTitle     = $row['sel_title'];
					$dplArticle->mSelNamespace = $row['sel_ns'];
				}
			}

			//STORE selected image
			if (count($aImageUsed) > 0) {
				if (!isset($row['image_sel_title'])) {
					$dplArticle->mImageSelTitle = 'unknown image';
				} else {
					$dplArticle->mImageSelTitle = $row['image_sel_title'];
				}
			}

			if ($this->parameters->getParameter('goal') != 'categories') {
				//REVISION SPECIFIED
				if ($this->parameters->getParameter('lastrevisionbefore') || $this->parameters->getParameter('allrevisionsbefore') || $this->parameters->getParameter('firstrevisionsince') || $this->parameters->getParameter('allrevisionssince')) {
					$dplArticle->mRevision = $row['rev_id'];
					$dplArticle->mUser     = $row['rev_user_text'];
					$dplArticle->mDate     = $row['rev_timestamp'];
				}

				//SHOW "PAGE_TOUCHED" DATE, "FIRSTCATEGORYDATE" OR (FIRST/LAST) EDIT DATE
				if ($this->parameters->getParameter('addpagetoucheddate')) {
					$dplArticle->mDate = $row['page_touched'];
				} elseif ($this->parameters->getParameter('addfirstcategorydate')) {
					$dplArticle->mDate = $row['cl_timestamp'];
				} elseif ($this->parameters->getParameter('addeditdate') && isset($row['rev_timestamp'])) {
					$dplArticle->mDate = $row['rev_timestamp'];
				} elseif ($this->parameters->getParameter('addeditdate') && isset($row['page_touched'])) {
					$dplArticle->mDate = $row['page_touched'];
				}

				// time zone adjustment
				if ($dplArticle->mDate != '') {
					$dplArticle->mDate = $this->wgLang->userAdjust($dplArticle->mDate);
				}

				if ($dplArticle->mDate != '' && $this->parameters->getParameter('userdateformat') != '') {
					// we apply the userdateformat
					$dplArticle->myDate = gmdate($this->parameters->getParameter('userdateformat'), wfTimeStamp(TS_UNIX, $dplArticle->mDate));
				}
				// CONTRIBUTION, CONTRIBUTOR
				if ($this->parameters->getParameter('addcontribution')) {
					$dplArticle->mContribution = $row['contribution'];
					$dplArticle->mContributor  = $row['contributor'];
					$dplArticle->mContrib      = substr('*****************', 0, round(log($row['contribution'])));
				}


				//USER/AUTHOR(S)
				// because we are going to do a recursive parse at the end of the output phase
				// we have to generate wiki syntax for linking to a user´s homepage
				if ($this->parameters->getParameter('adduser') || $this->parameters->getParameter('addauthor') || $this->parameters->getParameter('addlasteditor') || $this->parameters->getParameter('lastrevisionbefore') || $this->parameters->getParameter('allrevisionsbefore') || $this->parameters->getParameter('firstrevisionsince') || $this->parameters->getParameter('allrevisionssince')) {
					$dplArticle->mUserLink = '[[User:' . $row['rev_user_text'] . '|' . $row['rev_user_text'] . ']]';
					$dplArticle->mUser     = $row['rev_user_text'];
					$dplArticle->mComment  = $row['rev_comment'];
				}

				//CATEGORY LINKS FROM CURRENT PAGE
				if ($this->parameters->getParameter('addcategories') && ($row['cats'] != '')) {
					$artCatNames = explode(' | ', $row['cats']);
					foreach ($artCatNames as $artCatName) {
						$dplArticle->mCategoryLinks[] = '[[:Category:' . $artCatName . '|' . str_replace('_', ' ', $artCatName) . ']]';
						$dplArticle->mCategoryTexts[] = str_replace('_', ' ', $artCatName);
					}
				}
				// PARENT HEADING (category of the page, editor (user) of the page, etc. Depends on ordermethod param)
				if ($this->parameters->getParameter('headingmode') != 'none') {
					switch ($this->parameters->getParameter('ordermethod')[0]) {
						case 'category':
							//count one more page in this heading
							$headings[$row['cl_to']] = isset($headings[$row['cl_to']]) ? $headings[$row['cl_to']] + 1 : 1;
							if ($row['cl_to'] == '') {
								//uncategorized page (used if ordermethod=category,...)
								$dplArticle->mParentHLink = '[[:Special:Uncategorizedpages|' . wfMsg('uncategorizedpages') . ']]';
							} else {
								$dplArticle->mParentHLink = '[[:Category:' . $row['cl_to'] . '|' . str_replace('_', ' ', $row['cl_to']) . ']]';
							}
							break;
						case 'user':
							$headings[$row['rev_user_text']] = isset($headings[$row['rev_user_text']]) ? $headings[$row['rev_user_text']] + 1 : 1;
							if ($row['rev_user'] == 0) { //anonymous user
								$dplArticle->mParentHLink = '[[User:' . $row['rev_user_text'] . '|' . $row['rev_user_text'] . ']]';

							} else {
								$dplArticle->mParentHLink = '[[User:' . $row['rev_user_text'] . '|' . $row['rev_user_text'] . ']]';
							}
							break;
					}
				}
			}

			$articles[] = $dplArticle;
		}
		$this->DB->freeResult($result);

		return $articles;
	}

	/**
	 * Do basic clean up and structuring of raw user input.
	 *
	 * @access	private
	 * @param	string	Raw User Input
	 * @return	array	Array of raw text parameter => option.
	 */
	private function prepareUserInput($input) {
		//We replace double angle brackets with single angle brackets to avoid premature tag expansion in the input.
		//The ¦ symbol is an alias for |.
		//The combination '²{' and '}²'will be translated to double curly braces; this allows postponed template execution which is crucial for DPL queries which call other DPL queries.
		$input = str_replace(['«', '»', '¦', '²{', '}²'], ['<', '>', '|', '{{', '}}'], $input);

		//Standard new lines into the standard \n and clean up any hanging new lines.
		$input = str_replace(["\r\n", "\r"], "\n", $input);
		$input = trim($input, "\n");
		$rawParameters = explode("\n", $input);

		foreach ($rawParameters as $key => $parameterOption) {
			if (strpos($parameterOption, '=') === false) {
				$this->logger->addMessage(\DynamicPageListHooks::WARN_UNKNOWNPARAM, $parameter." [missing '=']");
				continue;
			}

			list($parameter, $option) = explode('=', $parameterOption, 2);
			$parameter = trim($parameter);
			$option  = trim($option);

			if (strpos($parameter, '<') !== false || strpos($parameter, '>') !== false) {
				//Having the actual less than and greater than symbols is nasty for programatic look up.  The old parameter is still supported along with the new, but we just fix it here before calling it.
				$parameter = str_replace('<', 'lt', $parameter);
				$parameter = str_replace('>', 'gt', $parameter);
			}

			if (empty($parameter) || substr($parameter, 0, 1) == '#' || ($this->parameters->exists($parameter) && !$this->parameters->testRichness($parameter))) {
				continue;
			}

			if (!$this->parameters->exists($parameter)) {
				$this->logger->addMessage(\DynamicPageListHooks::WARN_UNKNOWNPARAM, $parameter);
				continue;
			}

			//Ignore parameter settings without argument (except namespace and category).
			if (empty($option)) {
				if ($parameter != 'namespace' && $parameter != 'notnamespace' && $parameter != 'category' && $this->parameters->exists($parameter)) {
					continue;
				}
			}
			$parameters[$parameter] = $option;
		}
		return $parameters;
	}

	/**
	 * Concatenate output
	 *
	 * @access	private
	 * @param	string	Output to add
	 * @return	void
	 */
	private function addOutput($output) {
		$this->output .= $output;
	}

	/**
	 * Return output including header and footer.
	 *
	 * @access	public
	 * @return	string	Output
	 */
	private function getFullOutput() {
		if (!$this->getHeader() && !$this->getFooter()) {
			$this->logger->addMessage(\DynamicPageListHooks::WARN_NORESULTS);
		}
		//@TODO: Add logger output messages here.
		return $this->header.$this->output.$this->footer;
	}

	/**
	 * Set the header text.
	 *
	 * @access	private
	 * @param	string	Header Text
	 * @return	void
	 */
	private function setHeader($header) {
		$this->header = $header;
	}

	/**
	 * Set the header text.
	 *
	 * @access	private
	 * @return	string	Header Text
	 */
	private function getHeader() {
		return $this->header;
	}

	/**
	 * Set the footer text.
	 *
	 * @access	private
	 * @param	string	Footer Text
	 * @return	void
	 */
	private function setFooter($footer) {
		$this->footer = $footer;
	}

	/**
	 * Set the footer text.
	 *
	 * @access	private
	 * @return	string	Footer Text
	 */
	private function getFooter() {
		return $this->footer;
	}

	/**
	 * Return text with custom new line characters replaced.
	 *
	 * @access	private
	 * @param	string	Text
	 * @return	string	New Lined Text
	 */
	private function replaceNewLines($text) {
		return str_replace(['\n', "¶"], "\n", $text);
	}

	/**
	 * Return text with variables replaced.
	 *
	 * @access	private
	 * @param	string	Text
	 * @param	array	Array of '%VARIABLE' => 'Replacement' replacements.
	 * @return	string	Replaced Text
	 */
	private function replaceVariables($text, $replacements) {
		$text = $this->replaceNewLines($text);
		foreach ($replacements as $variable => $replacement) {
			$text = str_replace($variable, $replacement, $text);
		}
		return $text;
	}

	/**
	 * Work through processed parameters and check for potential issues.
	 *
	 * @access	private
	 * @return	void
	 */
	private function doQueryErrorChecks() {
		//@TODO: Many things to fix in here still.
		/**************************/
		/* Parameter Error Checks */
		/**************************/

		$totalCategory = count($this->parameters->getParameter('category'), COUNT_RECURSIVE) - count($this->parameters->getParameter('category'));
		$totalNotCategory = count($this->parameters->getParameter('notcategory'), COUNT_RECURSIVE) - count($this->parameters->getParameter('notcategory'));
		$totalCategories = $totalCategory + $totalNotCategory;

		//Too many categories.
		if ($totalCategories > Config::getSetting('maxCategoryCount') && !Config::getSetting('allowUnlimitedCategories')) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_TOOMANYCATS, Config::getSetting('maxCategoryCount'));
			return false;
		}

		//Not enough categories.(Really?)
		if ($totalCategories < Config::getSetting('minCategoryCount')) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_TOOFEWCATS, Config::getSetting('minCategoryCount'));
			return false;
		}

		//Selection criteria needs to be found.  @TODO: Figure out why the original check skips this if categories are found.  Maybe goal=categories?  If so, fix the check to look at the goal parameter for confirmation.
		if (!$totalCategories == 0 && !$this->parameters->isSelectionCriteriaFound()) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_NOSELECTION);
			return false;
		}

		//ordermethod=sortkey requires ordermethod=category
		//Delayed to the construction of the SQL query, see near line 2211, gs
		//if (in_array('sortkey',$aOrderMethods) && ! in_array('category',$aOrderMethods)) $aOrderMethods[] = 'category';

		//Throw an error in no categories were selected when using category sorting modes or requesting category information.
		if (!$totalCategories == 0 && ($this->parameters->getParameter('ordermethod') == 'categoryadd' || $this->parameters->getParameter('addfirstcategorydate') === true)) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_CATDATEBUTNOINCLUDEDCATS);
			return false;
		}

		//No more than one type of date at a time!
		//@TODO: Can this be fixed to allow all three later after fixing the article class?
		if ((intval($this->parameters->getParameter('addpagetoucheddate')) + intval($this->parameters->getParameter('addfirstcategorydate')) + intval($this->parameters->getParameter('addeditdate'))) > 1) {
			return $this->logger->addMessage(\DynamicPageListHooks::FATAL_MORETHAN1TYPEOFDATE);
		}

		// the dominant section must be one of the sections mentioned in includepage
		if ($iDominantSection > 0 && count($aSecLabels) < $iDominantSection) {
			return $this->logger->addMessage(\DynamicPageListHooks::FATAL_DOMINANTSECTIONRANGE, count($aSecLabels));
		}

		// category-style output requested with not compatible order method
		if ($this->parameters->getParameter('pagelistmode') == 'category' && !array_intersect($this->parameters->getParameter('ordermethod'), array(
			'sortkey',
			'title',
			'titlewithoutnamespace'
		))) {
			return $this->logger->addMessage(\DynamicPageListHooks::FATAL_WRONGORDERMETHOD, 'mode=category', 'sortkey | title | titlewithoutnamespace');
		}

		// addpagetoucheddate=true with unappropriate order methods
		if ($this->parameters->getParameter('addpagetoucheddate') && !array_intersect($this->parameters->getParameter('ordermethod'), array(
			'pagetouched',
			'title'
		))) {
			return $this->logger->addMessage(\DynamicPageListHooks::FATAL_WRONGORDERMETHOD, 'addpagetoucheddate=true', 'pagetouched | title');
		}

		// addeditdate=true but not (ordermethod=...,firstedit or ordermethod=...,lastedit)
		//firstedit (resp. lastedit) -> add date of first (resp. last) revision
		if ($this->parameters->getParameter('addeditdate') && !array_intersect($this->parameters->getParameter('ordermethod'), ['firstedit', 'lastedit']) && ($this->parameters->getParameter('allrevisionsbefore') || $this->parameters->getParameter('allrevisionssince') || $this->parameters->getParameter('firstrevisionsince') || $this->parameters->getParameter('lastrevisionbefore'))) {
			return $this->logger->addMessage(\DynamicPageListHooks::FATAL_WRONGORDERMETHOD, 'addeditdate=true', 'firstedit | lastedit');
		}

		// adduser=true but not (ordermethod=...,firstedit or ordermethod=...,lastedit)
		/**
		 * @todo allow to add user for other order methods.
		 * The fact is a page may be edited by multiple users. Which user(s) should we show? all? the first or the last one?
		 * Ideally, we could use values such as 'all', 'first' or 'last' for the adduser parameter.
		 */
		if ($this->parameters->getParameter('adduser') && !array_intersect($this->parameters->getParameter('ordermethod'), ['firstedit', 'lastedit']) & ($sLastRevisionBefore . $sAllRevisionsBefore . $sFirstRevisionSince . $sAllRevisionsSince == '')) {
			return $this->logger->addMessage(\DynamicPageListHooks::FATAL_WRONGORDERMETHOD, 'adduser=true', 'firstedit | lastedit');
		}
		if ($this->parameters->getParameter('minoredits') && !array_intersect($this->parameters->getParameter('ordermethod'), ['firstedit', 'lastedit'])) {
			return $this->logger->addMessage(\DynamicPageListHooks::FATAL_WRONGORDERMETHOD, 'minoredits', 'firstedit | lastedit');
		}

		/**
		 * If we include the Uncategorized, we need the 'dpl_clview': VIEW of the categorylinks table where we have cl_to='' (empty string) for all uncategorized pages. This VIEW must have been created by the administrator of the mediawiki DB at installation. See the documentation.
		 */
		if ($this->parameters->getParameter('includeuncat')) {
			// If the view is not there, we can't perform logical operations on the Uncategorized.
			if (!$this->DB->tableExists('dpl_clview')) {
				$sSqlCreate_dpl_clview = 'CREATE VIEW ' . $this->tableNames['dpl_clview'] . " AS SELECT IFNULL(cl_from, page_id) AS cl_from, IFNULL(cl_to, '') AS cl_to, cl_sortkey FROM " . $this->tableNames['page'] . ' LEFT OUTER JOIN ' . $this->tableNames['categorylinks'] . ' ON ' . $this->tableNames['page'] . '.page_id=cl_from';
				$this->logger->addMessage(\DynamicPageListHooks::FATAL_NOCLVIEW, $this->tableNames['dpl_clview'], $sSqlCreate_dpl_clview);
				return $output;
			}
		}

		//add*** parameters have no effect with 'mode=category' (only namespace/title can be viewed in this mode)
		if ($sPageListMode == 'category' && ($this->parameters->getParameter('addcategories') || $this->parameters->getParameter('addeditdate') || $this->parameters->getParameter('addfirstcategorydate') || $this->parameters->getParameter('addpagetoucheddate') || $this->parameters->getParameter('incpage') || $this->parameters->getParameter('adduser') || $this->parameters->getParameter('addauthor') || $this->parameters->getParameter('addcontribution') || $this->parameters->getParameter('addlasteditor'))) {
			$this->logger->addMessage(\DynamicPageListHooks::WARN_CATOUTPUTBUTWRONGPARAMS);
		}

		//headingmode has effects with ordermethod on multiple components only
		if ($this->parameters->getParameter('headingmode') != 'none' && count($this->parameters->getParameter('ordermethod')) < 2) {
			$this->logger->addMessage(\DynamicPageListHooks::WARN_HEADINGBUTSIMPLEORDERMETHOD, $this->parameters->getParameter('headingmode'), 'none');
			$this->parameters->setParameter('hlistmode', 'none');
		}

		//The 'openreferences' parameter is incompatible with many other options.
		//@TODO: Fatal, but does not interrupt execution?
		if ($this->parameters->isOpenReferencesConflict()) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_OPENREFERENCES);
		}
		return true;
	}

	/**
	 * Create keys for TableRow which represent the structure of the "include=" arguments.
	 *
	 * @access	public
	 * @param	array	Array of 'tablerow' parameter data.
	 * @param	array	Array of 'include' parameter data.
	 * @return	array	Updated 'tablerow' parameter.
	 */
	private static function updateTableRowKeys($tableRow, $sectionLabels) {
		$_tableRow	= $tableRow;
		$tableRow	= [];
		$groupNr	= -1;
		$t			= -1;
		foreach ($sectionLabels as $label) {
			$t++;
			$groupNr++;
			$cols = explode('}:', $label);
			if (count($cols) <= 1) {
				if (array_key_exists($t, $_tableRow)) {
					$tableRow[$groupNr] = $_tableRow[$t];
				}
			} else {
				$n     = count(explode(':', $cols[1]));
				$colNr = -1;
				$t--;
				for ($i = 1; $i <= $n; $i++) {
					$colNr++;
					$t++;
					if (array_key_exists($t, $_tableRow)) {
						$tableRow[$groupNr.'.'.$colNr] = $_tableRow[$t];
					}
				}
			}
		}
		return $tableRow;
	}

	/**
	 * Resolve arguments in the input that would normally be in the URL.
	 *
	 * @access	public
	 * @param	string	Raw Uncleaned User Input
	 * @param	array	Array of URL arguments to resolve.  Non-arrays will be casted to an array.
	 * @return	string	Raw input with variables replaced
	 */
	private function resolveUrlArguments($input, $arguments) {
		$arguments = (array) $arguments;
		foreach ($arguments as $arg) {
			$dplArg = $this->wgRequest->getVal($arg, '');
			if ($dplArg == '') {
				$input = preg_replace('/\{%' . $arg . ':(.*)%\}/U', '\1', $input);
				$input = str_replace('{%' . $arg . '%}', '', $input);
			} else {
				$input = preg_replace('/\{%' . $arg . ':.*%\}/U  ', $dplArg, $input);
				$input = str_replace('{%' . $arg . '%}', $dplArg, $input);
			}
		}
		return $input;
	}

	/**
	 * This function uses the Variables extension to provide URL-arguments like &DPL_xyz=abc in the form of a variable which can be accessed as {{#var:xyz}} if Extension:Variables is installed.
	 *
	 * @access	public
	 * @return	void
	 */
	private function getUrlArgs() {
		global $wgExtVariables;
		//@TODO: Figure out why this function needs to set ALL request variables and not just those related to DPL.
		$args = $this->wgRequest->getValues();
		foreach ($args as $argName => $argValue) {
			Variables::setVar(array(
				'',
				'',
				$argName,
				$argValue
			));
		}
		if (!isset($wgExtVariables)) {
			return;
		}
		$args  = $this->wgRequest->getValues();
		$dummy = '';
		foreach ($args as $argName => $argValue) {
			$wgExtVariables->vardefine($dummy, $argName, $argValue);
		}
	}

	/**
	 * This function uses the Variables extension to provide navigation aids like DPL_firstTitle, DPL_lastTitle, DPL_findTitle.  These variables can be accessed as {{#var:DPL_firstTitle}} if Extension:Variables is installed.
	 *
	 * @access	public
	 * @return	void
	 */
	private static function defineScrollVariables($firstNamespace, $firstTitle, $lastNamespace, $lastTitle, $scrollDir, $dplCount, $dplElapsedTime, $totalPages, $pages) {
		//@TODO: $wgExtVariables is deprecated and removed.  Fix this function to use the static public functions off class ExtVariables.
		global $wgExtVariables;
		Variables::setVar(array(
			'',
			'',
			'DPL_firstNamespace',
			$firstNamespace
		));
		Variables::setVar(array(
			'',
			'',
			'DPL_firstTitle',
			$firstTitle
		));
		Variables::setVar(array(
			'',
			'',
			'DPL_lastNamespace',
			$lastNamespace
		));
		Variables::setVar(array(
			'',
			'',
			'DPL_lastTitle',
			$lastTitle
		));
		Variables::setVar(array(
			'',
			'',
			'DPL_scrollDir',
			$scrollDir
		));
		Variables::setVar(array(
			'',
			'',
			'DPL_time',
			$dplElapsedTime
		));
		Variables::setVar(array(
			'',
			'',
			'DPL_count',
			$dplCount
		));
		Variables::setVar(array(
			'',
			'',
			'DPL_totalPages',
			$totalPages
		));
		Variables::setVar(array(
			'',
			'',
			'DPL_pages',
			$pages
		));

		if (!isset($wgExtVariables)) {
			return;
		}
		$dummy = '';
		$wgExtVariables->vardefine($dummy, 'DPL_firstNamespace', $firstNamespace);
		$wgExtVariables->vardefine($dummy, 'DPL_firstTitle', $firstTitle);
		$wgExtVariables->vardefine($dummy, 'DPL_lastNamespace', $lastNamespace);
		$wgExtVariables->vardefine($dummy, 'DPL_lastTitle', $lastTitle);
		$wgExtVariables->vardefine($dummy, 'DPL_scrollDir', $scrollDir);
		$wgExtVariables->vardefine($dummy, 'DPL_time', $dplElapsedTime);
		$wgExtVariables->vardefine($dummy, 'DPL_count', $dplCount);
		$wgExtVariables->vardefine($dummy, 'DPL_totalPages', $totalPages);
		$wgExtVariables->vardefine($dummy, 'DPL_pages', $pages);
	}

	/**
	 * Sort an array of Article objects by the card suit symbol.
	 *
	 * @access	public
	 * @param	array	Article objects in an array.
	 * @return	array	Sorted objects
	 */
	private static function cardSuitSort($articles) {
		$skey = array();
		for ($a = 0; $a < count($articles); $a++) {
			$title  = preg_replace('/.*:/', '', $articles[$a]->mTitle);
			$token  = preg_split('/ - */', $title);
			$newkey = '';
			foreach ($token as $tok) {
				$initial = substr($tok, 0, 1);
				if ($initial >= '1' && $initial <= '7') {
					$newkey .= $initial;
					$suit = substr($tok, 1);
					if ($suit == '♣') {
						$newkey .= '1';
					} else if ($suit == '♦') {
						$newkey .= '2';
					} else if ($suit == '♥') {
						$newkey .= '3';
					} else if ($suit == '♠') {
						$newkey .= '4';
					} else if ($suit == 'sa' || $suit == 'SA' || $suit == 'nt' || $suit == 'NT') {
						$newkey .= '5 ';
					} else {
						$newkey .= $suit;
					}
				} else if ($initial == 'P' || $initial == 'p')
					$newkey .= '0 ';
				else if ($initial == 'X' || $initial == 'x')
					$newkey .= '8 ';
				else
					$newkey .= $tok;
			}
			$skey[$a] = "$newkey#$a";
		}
		for ($a = 0; $a < count($articles); $a++) {
			$cArticles[$a] = clone ($articles[$a]);
		}
		sort($skey);
		for ($a = 0; $a < count($cArticles); $a++) {
			$key          = intval(preg_replace('/.*#/', '', $skey[$a]));
			$articles[$a] = $cArticles[$key];
		}
		return $articles;
	}
}
?>