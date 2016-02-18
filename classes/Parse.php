<?php
/**
 * DynamicPageList3
 * DPL Parse Class
 *
 * @author		IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license		GPL
 * @package		DynamicPageList3
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
	 * Mediawiki Parser Object
	 *
	 * @var		object
	 */
	private $parser = null;

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
	 * @var		array
	 */
	private $tableNames = [];

	/**
	 * Cache Key for this tag parse.
	 *
	 * @var		string
	 */
	private $cacheKey = null;

	/**
	 * Header Output
	 *
	 * @var		string
	 */
	private $header = '';

	/**
	 * Footer Output
	 *
	 * @var		string
	 */
	private $footer = '';

	/**
	 * Body Output
	 *
	 * @var		string
	 */
	private $output = '';

	/**
	 * DynamicPageList Object Holder
	 *
	 * @var		object
	 */
	private $dpl = null;

	/**
	 * Replacement Variables
	 *
	 * @var		array
	 */
	private $replacementVariables = [];

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
		global $wgRequest;

		$this->DB			= wfGetDB(DB_SLAVE);
		$this->parameters	= new Parameters();
		$this->logger		= new Logger($this->parameters->getData('debug')['default']);
		$this->tableNames	= Query::getTableNames();
		$this->wgRequest	= $wgRequest;
	}

	/**
	 * The real callback function for converting the input text to wiki text output
	 *
	 * @access	public
	 * @param	string	Raw User Input
	 * @param	object	Mediawiki Parser object.
	 * @param	array	End Reset Booleans
	 * @param	array	End Eliminate Booleans
	 * @param	boolean	[Optional] Called as a parser tag
	 * @return	string	Wiki/HTML Output
	 */
	public function parse($input, \Parser $parser, &$reset, &$eliminate, $isParserTag = false) {
		wfProfileIn(__METHOD__);
		$dplStartTime = microtime(true);
		$this->parser = $parser;

		//Reset headings when being ran more than once in the same page load.
		Article::resetHeadings();

		//Check that we are not in an infinite transclusion loop
		if (isset($this->parser->mTemplatePath[$this->parser->mTitle->getPrefixedText()])) {
			$this->logger->addMessage(\DynamicPageListHooks::WARN_TRANSCLUSIONLOOP, $this->parser->mTitle->getPrefixedText());
			return $this->getFullOutput();
		}

		//Check if DPL shall only be executed from protected pages.
		if (Config::getSetting('runFromProtectedPagesOnly') === true && !$this->parser->mTitle->isProtected('edit')) {
			//Ideally we would like to allow using a DPL query if the query istelf is coded on a template page which is protected. Then there would be no need for the article to be protected.  However, how can one find out from which wiki source an extension has been invoked???
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_NOTPROTECTED, $this->parser->mTitle->getPrefixedText());
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
		$this->getUrlArgs($this->parser);

		$this->parameters->setParameter('offset', $this->wgRequest->getInt('DPL_offset', $this->parameters->getData('offset')['default']));
		$offset = $this->parameters->getParameter('offset');

		/***************************************/
		/* User Input preparation and parsing. */
		/***************************************/
		$cleanParameters = $this->prepareUserInput($input);
		if (!is_array($cleanParameters)) {
			//Short circuit for dumb things.
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_NOSELECTION);
			return $this->getFullOutput();
		}
		$cleanParameters = $this->parameters->sortByPriority($cleanParameters);
		$this->parameters->setParameter('includeuncat', false); // to check if pseudo-category of Uncategorized pages is included

		foreach ($cleanParameters as $parameter => $option) {
			foreach ($option as $_option) {
				//Parameter functions return true or false.  The full parameter data will be passed into the Query object later.
				if ($this->parameters->$parameter($_option) === false) {
					//Do not build this into the output just yet.  It will be collected at the end.
					$this->logger->addMessage(\DynamicPageListHooks::WARN_WRONGPARAM, $parameter, $_option);
				}
			}
		}

		/*************************/
		/* Execute and Exit Only */
		/*************************/
		if ($this->parameters->getParameter('execandexit') !== null) {
			//The keyword "geturlargs" is used to return the Url arguments and do nothing else.
			if ($this->parameters->getParameter('execandexit') == 'geturlargs') {
				return;
			}
			//In all other cases we return the value of the argument which may contain parser function calls.
			return $this->parameters->getParameter('execandexit');
		}

		//Construct internal keys for TableRow according to the structure of "include".  This will be needed in the output phase.
		if ($this->parameters->getParameter('seclabels') !== null) {
			$this->parameters->setParameter('tablerow', $this->updateTableRowKeys($this->parameters->getParameter('tablerow'), $this->parameters->getParameter('seclabels')));
		}

		/****************/
		/* Check Errors */
		/****************/
		$errors = $this->doQueryErrorChecks();
		if ($errors === false) {
			//WHAT HAS HAPPENED OH NOOOOOOOOOOOOO.
			return $this->getFullOutput();
		}

		$calcRows = false;
		if (!Config::getSetting('allowUnlimitedResults') && $this->parameters->getParameter('goal') != 'categories' && strpos($this->parameters->getParameter('resultsheader').$this->parameters->getParameter('noresultsheader').$this->parameters->getParameter('resultsfooter'), '%TOTALPAGES%') !== false) {
			$calcRows = true;
		}

		/*********/
		/* Query */
		/*********/
		try {
			$this->query = new Query($this->parameters);
			$result = $this->query->buildAndSelect($calcRows);
		} catch (MWException $e) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_SQLBUILDERROR, $e->getMessage());
			return $this->getFullOutput();
		}

		$numRows = $this->DB->numRows($result);
		$articles = $this->processQueryResults($result);

		$this->addOutput('{{Extension DPL}}');

		//Preset these to defaults.
		$this->setVariable('TOTALPAGES', 0);
		$this->setVariable('PAGES', 0);
		$this->setVariable('VERSION', DPL_VERSION);

		/*********************/
		/* Handle No Results */
		/*********************/
		if ($numRows <= 0 || empty($articles)) {
			//Shortcut out since there is no processing to do.
			$this->DB->freeResult($result);
			return $this->getFullOutput(0, false);
		}

		$foundRows = null;
		if ($calcRows) {
			$foundRows = $this->query->getFoundRows();
		}

		//Backward scrolling: If the user specified only titlelt with descending reverse the output order.
		if ($this->parameters->getParameter('titlelt') && !$this->parameters->getParameter('titlegt') && $this->parameters->getParameter('order') == 'descending') {
			$articles = array_reverse($articles);
		}

		//Special sort for card suits (Bridge)
		if ($this->parameters->getParameter('ordersuitsymbols')) {
			$articles = $this->cardSuitSort($articles);
		}

		/*******************/
		/* Generate Output */
		/*******************/
		$listMode = new ListMode(
			$this->parameters->getParameter('mode'),
			$this->parameters->getParameter('secseparators'),
			$this->parameters->getParameter('multisecseparators'),
			$this->parameters->getParameter('inlinetext'),
			$this->parameters->getParameter('listattr'),
			$this->parameters->getParameter('itemattr'),
			$this->parameters->getParameter('listseparators'),
			$offset,
			$this->parameters->getParameter('dominantsection')
		);

		$hListMode = new ListMode(
			$this->parameters->getParameter('headingmode'),
			$this->parameters->getParameter('secseparators'),
			$this->parameters->getParameter('multisecseparators'),
			'',
			$this->parameters->getParameter('hlistattr'),
			$this->parameters->getParameter('hitemattr'),
			$this->parameters->getParameter('listseparators'),
			$offset,
			$this->parameters->getParameter('dominantsection')
		);

		$this->dpl = new DynamicPageList(
			Article::getHeadings(),
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
			$this->parser,
			$this->parameters->getParameter('replaceintitle'),
			$this->parameters->getParameter('titlemaxlen'),
			$this->parameters->getParameter('defaulttemplatesuffix'),
			$this->parameters->getParameter('tablerow'),
			$this->parameters->getParameter('includetrim'),
			$this->parameters->getParameter('tablesortcol'),
			$this->parameters->getParameter('updaterules'),
			$this->parameters->getParameter('deleterules')
		);

		if ($foundRows === null) {
			$foundRows = $this->dpl->getRowCount();
		}
		$this->addOutput($this->dpl->getText());

		/*******************************/
		/* Replacement Variables       */
		/*******************************/
		$this->setVariable('TOTALPAGES', $foundRows); //Guaranteed to be an accurate count if SQL_CALC_FOUND_ROWS was used.  Otherwise only accurate if results are less than the SQL LIMIT.
		$this->setVariable('PAGES', $this->dpl->getRowCount()); //This could be different than TOTALPAGES.  PAGES represents the total results within the constraints of SQL LIMIT.

		//Replace %DPLTIME% by execution time and timestamp in header and footer
		$nowTimeStamp   = date('Y/m/d H:i:s');
		$dplElapsedTime = sprintf('%.3f sec.', microtime(true) - $dplStartTime);
		$dplTime = "{$dplElapsedTime} ({$nowTimeStamp})";
		$this->setVariable('DPLTIME', $dplTime);

		//Replace %LASTTITLE% / %LASTNAMESPACE% by the last title found in header and footer
		if (($n = count($articles)) > 0) {
			$firstNamespaceFound = str_replace(' ', '_', $articles[0]->mTitle->getNamespace());
			$firstTitleFound     = str_replace(' ', '_', $articles[0]->mTitle->getText());
			$lastNamespaceFound  = str_replace(' ', '_', $articles[$n - 1]->mTitle->getNamespace());
			$lastTitleFound      = str_replace(' ', '_', $articles[$n - 1]->mTitle->getText());
		}
		$this->setVariable('FIRSTNAMESPACE', $firstNamespaceFound);
		$this->setVariable('FIRSTTITLE', $firstTitleFound);
		$this->setVariable('LASTNAMESPACE', $lastNamespaceFound);
		$this->setVariable('LASTTITLE', $lastTitleFound);
		$this->setVariable('SCROLLDIR', $this->parameters->getParameter('scrolldir'));

		/*******************************/
		/* Scroll Variables            */
		/*******************************/
		$scrollVariables = [
			'DPL_firstNamespace'	=> $firstNamespaceFound,
			'DPL_firstTitle'		=> $firstTitleFound,
			'DPL_lastNamespace'		=> $lastNamespaceFound,
			'DPL_lastTitle'			=> $lastTitleFound,
			'DPL_scrollDir'			=> $this->parameters->getParameter('scrolldir'),
			'DPL_time'				=> $dplTime,
			'DPL_count'				=> $this->parameters->getParameter('count'),
			'DPL_totalPages'		=> $foundRows,
			'DPL_pages'				=> $this->dpl->getRowCount()
		];
		$this->defineScrollVariables($scrollVariables);

		if ($this->parameters->getParameter('allowcachedresults')) {
			$this->parser->getOutput()->updateCacheExpiry($this->parameters->getParameter('cacheperiod') ? $this->parameters->getParameter('cacheperiod') : 3600);
		} else {
			$this->parser->disableCache();
		}

		$finalOutput = $this->getFullOutput($foundRows, false);

		$this->triggerEndResets($finalOutput, $reset, $eliminate, $isParserTag);

		wfProfileOut(__METHOD__);

		return $finalOutput;
	}

	/**
	 * Process Query Results
	 *
	 * @access	private
	 * @param	object	Mediawiki Result Object
	 * @return	array	Array of Article objects.
	 */
	private function processQueryResults($result) {
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

		$articles = [];

		/**********************/
		/* Article Processing */
		/**********************/
		$i = 0;
		while ($row = $result->fetchRow()) {
			$i++;

			//In random mode skip articles which were not chosen.
			if ($randomCount > 0 && !in_array($i, $pick)) {
				continue;
			}

			if ($this->parameters->getParameter('goal') == 'categories') {
				$pageNamespace = NS_CATEGORY;
				$pageTitle     = $row['cl_to'];
			} elseif ($this->parameters->getParameter('openreferences')) {
				if (count($this->parameters->getParameter('imagecontainer')) > 0) {
					$pageNamespace = NS_FILE;
					$pageTitle     = $row['il_to'];
				} else {
					//Maybe non-existing title
					$pageNamespace = $row['pl_namespace'];
					$pageTitle     = $row['pl_title'];
				}
			} else {
				//Existing PAGE TITLE
				$pageNamespace = $row['page_namespace'];
				$pageTitle     = $row['page_title'];
			}

			// if subpages are to be excluded: skip them
			if (!$this->parameters->getParameter('includesubpages') && strpos($pageTitle, '/') !== false) {
				continue;
			}

			$title     = \Title::makeTitle($pageNamespace, $pageTitle);
			$thisTitle = $this->parser->getTitle();

			//Block recursion from happening by seeing if this result row is the page the DPL query was ran from.
			if ($this->parameters->getParameter('skipthispage') && $thisTitle->equals($title)) {
				continue;
			}

			$articles[] = Article::newFromRow($row, $this->parameters, $title, $pageNamespace, $pageTitle);
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

		$parameters = false;
		foreach ($rawParameters as $parameterOption) {
			if (empty($parameterOption)) {
				//Softly ignore blank lines.
				continue;
			}

			if (strpos($parameterOption, '=') === false) {
				$this->logger->addMessage(\DynamicPageListHooks::WARN_PARAMNOOPTION, $parameterOption);
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
				$this->logger->addMessage(\DynamicPageListHooks::WARN_UNKNOWNPARAM, $parameter, implode(', ', $this->parameters->getParametersForRichness()));
				continue;
			}

			//Ignore parameter settings without argument (except namespace and category).
			if (!strlen($option)) {
				if ($parameter != 'namespace' && $parameter != 'notnamespace' && $parameter != 'category' && $this->parameters->exists($parameter)) {
					continue;
				}
			}
			$parameters[$parameter][] = $option;
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
	 * Set the output text.
	 *
	 * @access	private
	 * @return	string	Output Text
	 */
	private function getOutput() {
		//@TODO: 2015-08-28 Consider calling $this->replaceVariables() here.  Might cause issues with text returned in the results.
		return $this->output;
	}

	/**
	 * Return output optionally including header and footer.
	 *
	 * @access	private
	 * @param	boolean	[Optional] Total results.
	 * @param	boolean	[Optional] Skip adding the header and footer.
	 * @return	string	Output
	 */
	private function getFullOutput($totalResults = false, $skipHeaderFooter = true) {
		if (!$skipHeaderFooter) {
			$header = '';
			$footer = '';
			//Only override header and footers if specified.
			$_headerType = $this->getHeaderFooterType('header', $totalResults);
			if ($_headerType !== false) {
				$header = $this->parameters->getParameter($_headerType);
			}
			$_footerType = $this->getHeaderFooterType('footer', $totalResults);
			if ($_footerType !== false) {
				$footer = $this->parameters->getParameter($_footerType);
			}

			$this->setHeader($header);
			$this->setFooter($footer);
		}

		if (!$totalResults && !strlen($this->getHeader()) && !strlen($this->getFooter())) {
			$this->logger->addMessage(\DynamicPageListHooks::WARN_NORESULTS);
		}
		$messages = $this->logger->getMessages(false);

		return (count($messages) ? implode("<br/>\n", $messages) : null).$this->getHeader().$this->getOutput().$this->getFooter();
	}

	/**
	 * Set the header text.
	 *
	 * @access	private
	 * @param	string	Header Text
	 * @return	void
	 */
	private function setHeader($header) {
		if (\DynamicPageListHooks::getDebugLevel() == 5) {
			$header = '<pre><nowiki>'.$header;
		}
		$this->header = $this->replaceVariables($header);
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
		if (\DynamicPageListHooks::getDebugLevel() == 5) {
			$footer .= '</nowiki></pre>';
		}
		$this->footer = $this->replaceVariables($footer);
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
	 * Determine the header/footer type to use based on what output format parameters were chosen and the number of results.
	 *
	 * @access	private
	 * @param	string	Page position to check: 'header' or 'footer'.
	 * @param	integer	Count of pages.
	 * @return	mixed	Type to use: 'results', 'oneresult', or 'noresults'.  False if invalid or none should be used.
	 */
	private function getHeaderFooterType($position, $count) {
		$count = intval($count);
		if ($position != 'header' && $position != 'footer') {
			return false;
		}

		if ($this->parameters->getParameter('results'.$position) !== null && ($count >= 2 || ($this->parameters->getParameter('oneresult'.$position) === null && $count >= 1))) {
			$_type = 'results'.$position;
		} elseif ($count === 1 && $this->parameters->getParameter('oneresult'.$position) !== null) {
			$_type = 'oneresult'.$position;
		} elseif ($count === 0 && $this->parameters->getParameter('noresults'.$position) !== null) {
			$_type = 'noresults'.$position;
		} else {
			$_type = false;
		}
		return $_type;
	}

	/**
	 * Set a variable to be replaced with the provided text later at the end of the output.
	 *
	 * @access	private
	 * @param	string	Variable name, will be transformed to uppercase and have leading and trailing percent signs added.
	 * @param	string	Text to replace the variable with.
	 * @return	void
	 */
	private function setVariable($variable, $replacement) {
		$variable = "%".mb_strtoupper($variable, "UTF-8")."%";
		$this->replacementVariables[$variable] = $replacement;
	}

	/**
	 * Return text with variables replaced.
	 *
	 * @access	private
	 * @param	string	Text to perform replacements on.
	 * @return	string	Replaced Text
	 */
	private function replaceVariables($text) {
		$text = self::replaceNewLines($text);
		foreach ($this->replacementVariables as $variable => $replacement) {
			$text = str_replace($variable, $replacement, $text);
		}
		return $text;
	}

	/**
	 * Return text with custom new line characters replaced.
	 *
	 * @access	private
	 * @param	string	Text
	 * @return	string	New Lined Text
	 */
	static public function replaceNewLines($text) {
		return str_replace(['\n', "¶"], "\n", $text);
	}

	/**
	 * Work through processed parameters and check for potential issues.
	 *
	 * @access	private
	 * @return	void
	 */
	private function doQueryErrorChecks() {
		/**************************/
		/* Parameter Error Checks */
		/**************************/

		$totalCategories = 0;
		if (is_array($this->parameters->getParameter('category'))) {
			foreach ($this->parameters->getParameter('category') as $comparisonType => $operatorTypes) {
				foreach ($operatorTypes as $operatorType => $categoryGroups) {
					foreach ($categoryGroups as $categories) {
						$totalCategories += count($categories);
					}
				}
			}
		}
		if (is_array($this->parameters->getParameter('notcategory'))) {
			foreach ($this->parameters->getParameter('notcategory') as $comparisonType => $operatorTypes) {
				foreach ($operatorTypes as $operatorType => $categories) {
					$totalCategories += count($categories);
				}
			}
		}

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

		//Selection criteria needs to be found.
		if (!$totalCategories && !$this->parameters->isSelectionCriteriaFound()) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_NOSELECTION);
			return false;
		}

		//ordermethod=sortkey requires ordermethod=category
		//Delayed to the construction of the SQL query, see near line 2211, gs
		//if (in_array('sortkey',$aOrderMethods) && ! in_array('category',$aOrderMethods)) $aOrderMethods[] = 'category';

		$orderMethods = (array) $this->parameters->getParameter('ordermethod');
		//Throw an error in no categories were selected when using category sorting modes or requesting category information.
		if ($totalCategories == 0 && (in_array('categoryadd', $orderMethods) || $this->parameters->getParameter('addfirstcategorydate') === true)) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_CATDATEBUTNOINCLUDEDCATS);
			return false;
		}

		//No more than one type of date at a time!
		//@TODO: Can this be fixed to allow all three later after fixing the article class?
		if ((intval($this->parameters->getParameter('addpagetoucheddate')) + intval($this->parameters->getParameter('addfirstcategorydate')) + intval($this->parameters->getParameter('addeditdate'))) > 1) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_MORETHAN1TYPEOFDATE);
			return false;
		}

		// the dominant section must be one of the sections mentioned in includepage
		if ($this->parameters->getParameter('dominantsection') > 0 && count($this->parameters->getParameter('seclabels')) < $this->parameters->getParameter('dominantsection')) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_DOMINANTSECTIONRANGE, count($this->parameters->getParameter('seclabels')));
			return false;
		}

		// category-style output requested with not compatible order method
		if ($this->parameters->getParameter('mode') == 'category' && !array_intersect($orderMethods, ['sortkey', 'title', 'titlewithoutnamespace'])) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_WRONGORDERMETHOD, 'mode=category', 'sortkey | title | titlewithoutnamespace');
			return false;
		}

		// addpagetoucheddate=true with unappropriate order methods
		if ($this->parameters->getParameter('addpagetoucheddate') && !array_intersect($orderMethods, ['pagetouched', 'title'])) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_WRONGORDERMETHOD, 'addpagetoucheddate=true', 'pagetouched | title');
			return false;
		}

		// addeditdate=true but not (ordermethod=...,firstedit or ordermethod=...,lastedit)
		//firstedit (resp. lastedit) -> add date of first (resp. last) revision
		if ($this->parameters->getParameter('addeditdate') && !array_intersect($orderMethods, ['firstedit', 'lastedit']) && ($this->parameters->getParameter('allrevisionsbefore') || $this->parameters->getParameter('allrevisionssince') || $this->parameters->getParameter('firstrevisionsince') || $this->parameters->getParameter('lastrevisionbefore'))) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_WRONGORDERMETHOD, 'addeditdate=true', 'firstedit | lastedit');
			return false;
		}

		// adduser=true but not (ordermethod=...,firstedit or ordermethod=...,lastedit)
		/**
		 * @todo allow to add user for other order methods.
		 * The fact is a page may be edited by multiple users. Which user(s) should we show? all? the first or the last one?
		 * Ideally, we could use values such as 'all', 'first' or 'last' for the adduser parameter.
		 */
		if ($this->parameters->getParameter('adduser') && !array_intersect($orderMethods, ['firstedit', 'lastedit']) && !$this->parameters->getParameter('allrevisionsbefore') && !$this->parameters->getParameter('allrevisionssince') && !$this->parameters->getParameter('firstrevisionsince') && !$this->parameters->getParameter('lastrevisionbefore')) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_WRONGORDERMETHOD, 'adduser=true', 'firstedit | lastedit');
			return false;
		}
		if ($this->parameters->getParameter('minoredits') && !array_intersect($orderMethods, ['firstedit', 'lastedit'])) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_WRONGORDERMETHOD, 'minoredits', 'firstedit | lastedit');
			return false;
		}

		/**
		 * If including the Uncategorized, we need the 'dpl_clview': VIEW of the categorylinks table where we have cl_to='' (empty string) for all uncategorized pages. This VIEW must have been created by the administrator of the mediawiki DB at installation. See the documentation.
		 */
		if ($this->parameters->getParameter('includeuncat')) {
			//If the view is not there, we can't perform logical operations on the Uncategorized.
			if (!$this->DB->tableExists('dpl_clview')) {
				$sql = 'CREATE VIEW '.$this->tableNames['dpl_clview']." AS SELECT IFNULL(cl_from, page_id) AS cl_from, IFNULL(cl_to, '') AS cl_to, cl_sortkey FROM ".$this->tableNames['page'].' LEFT OUTER JOIN '.$this->tableNames['categorylinks'].' ON '.$this->tableNames['page'].'.page_id=cl_from';
				$this->logger->addMessage(\DynamicPageListHooks::FATAL_NOCLVIEW, $this->tableNames['dpl_clview'], $sql);
				return false;
			}
		}

		//add*** parameters have no effect with 'mode=category' (only namespace/title can be viewed in this mode)
		if ($this->parameters->getParameter('mode') == 'category' && ($this->parameters->getParameter('addcategories') || $this->parameters->getParameter('addeditdate') || $this->parameters->getParameter('addfirstcategorydate') || $this->parameters->getParameter('addpagetoucheddate') || $this->parameters->getParameter('incpage') || $this->parameters->getParameter('adduser') || $this->parameters->getParameter('addauthor') || $this->parameters->getParameter('addcontribution') || $this->parameters->getParameter('addlasteditor'))) {
			$this->logger->addMessage(\DynamicPageListHooks::WARN_CATOUTPUTBUTWRONGPARAMS);
		}

		//headingmode has effects with ordermethod on multiple components only
		if ($this->parameters->getParameter('headingmode') != 'none' && count($orderMethods) < 2) {
			$this->logger->addMessage(\DynamicPageListHooks::WARN_HEADINGBUTSIMPLEORDERMETHOD, $this->parameters->getParameter('headingmode'), 'none');
			$this->parameters->setParameter('headingmode', 'none');
		}

		//The 'openreferences' parameter is incompatible with many other options.
		if ($this->parameters->isOpenReferencesConflict() && $this->parameters->getParameter('openreferences') === true) {
			$this->logger->addMessage(\DynamicPageListHooks::FATAL_OPENREFERENCES);
			return false;
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
		$_tableRow	= (array) $tableRow;
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
		$args = $this->wgRequest->getValues();
		foreach ($args as $argName => $argValue) {
			if (strpos($argName, 'DPL_') === false) {
				continue;
			}
			Variables::setVar(['', '', $argName, $argValue]);
			if (defined('ExtVariables::VERSION')) {
				\ExtVariables::get($this->parser)->setVarValue($argName, $argValue);
			}
		}
	}

	/**
	 * This function uses the Variables extension to provide navigation aids such as DPL_firstTitle, DPL_lastTitle, or DPL_findTitle.  These variables can be accessed as {{#var:DPL_firstTitle}} if Extension:Variables is installed.
	 *
	 * @access	public
	 * @param	array	Array of scroll variables with the key as the variable name and the value as the value.  Non-arrays will be casted to arrays.
	 * @return	void
	 */
	private function defineScrollVariables($scrollVariables) {
		$scrollVariables = (array) $scrollVariables;

		foreach ($scrollVariables as $variable => $value) {
			Variables::setVar(['', '', $variable, $value]);
			if (defined('ExtVariables::VERSION')) {
				\ExtVariables::get($this->parser)->setVarValue($variable, $value);
			}
		}
	}

	/**
	 * Trigger Resets and Eliminates that run at the end of parsing.
	 *
	 * @access	private
	 * @param	string	Full output including header, footer, and any warnings.
	 * @param	array	End Reset Booleans
	 * @param	array	End Eliminate Booleans
	 * @param	boolean	Call as a parser tag
	 * @return	void
	 */
	private function triggerEndResets($output, &$reset, &$eliminate, $isParserTag) {
		global $wgHooks;

		$localParser = new \Parser();
		$parserOutput = $localParser->parse($output, $this->parser->mTitle, $this->parser->mOptions);

		if (!is_array($reset)) {
			$reset = [];
		}
		$reset = array_merge($reset, $this->parameters->getParameter('reset'));

		if (!is_array($eliminate)) {
			$eliminate = [];
		}
		$eliminate = array_merge($eliminate, $this->parameters->getParameter('eliminate'));
		if ($isParserTag === true) {
			//In tag mode 'eliminate' is the same as 'reset' for templates, categories, and images.
			if (isset($eliminate['templates']) && $eliminate['templates']) {
				$reset['templates'] = true;
				$eliminate['templates'] = false;
			}
			if (isset($eliminate['categories']) && $eliminate['categories']) {
				$reset['categories'] = true;
				$eliminate['categories'] = false;
			}
			if (isset($eliminate['images']) && $eliminate['images']) {
				$reset['images'] = true;
				$eliminate['images'] = false;
			}
		} else {
			if (isset($reset['templates']) && $reset['templates']) {
				\DynamicPageListHooks::$createdLinks['resetTemplates'] = true;
			}
			if (isset($reset['categories']) && $reset['categories']) {
				\DynamicPageListHooks::$createdLinks['resetCategories'] = true;
			}
			if (isset($reset['images']) && $reset['images']) {
				\DynamicPageListHooks::$createdLinks['resetImages'] = true;
			}
		}
		if (($isParserTag === true && isset($reset['links'])) || $isParserTag === false) {
			if (isset($reset['links'])) {
				\DynamicPageListHooks::$createdLinks['resetLinks'] = true;
			}
			//Register a hook to reset links which were produced during parsing DPL output.
			if (!isset($wgHooks['ParserAfterTidy']) || !is_array($wgHooks['ParserAfterTidy']) || !in_array('DynamicPageListHooks::endReset', $wgHooks['ParserAfterTidy'])) {
				$wgHooks['ParserAfterTidy'][] = 'DynamicPageListHooks::endReset';
			}
		}

		if (array_sum($eliminate)) {
			//Register a hook to reset links which were produced during parsing DPL output
			if (!isset($wgHooks['ParserAfterTidy']) || !is_array($wgHooks['ParserAfterTidy']) || !in_array('DynamicPageListHooks::endEliminate', $wgHooks['ParserAfterTidy'])) {
				$wgHooks['ParserAfterTidy'][] = 'DynamicPageListHooks::endEliminate';
			}

			if (isset($eliminate['links']) && $eliminate['links']) {
				//Trigger the mediawiki parser to find links, images, categories etc. which are contained in the DPL output.  This allows us to remove these links from the link list later.  If the article containing the DPL statement itself uses one of these links they will be thrown away!
				\DynamicPageListHooks::$createdLinks[0] = array();
				foreach ($parserOutput->getLinks() as $nsp => $link) {
					\DynamicPageListHooks::$createdLinks[0][$nsp] = $link;
				}
			}
			if (isset($eliminate['templates']) && $eliminate['templates']) {
				\DynamicPageListHooks::$createdLinks[1] = array();
				foreach ($parserOutput->getTemplates() as $nsp => $tpl) {
					\DynamicPageListHooks::$createdLinks[1][$nsp] = $tpl;
				}
			}
			if (isset($eliminate['categories']) && $eliminate['categories']) {
				\DynamicPageListHooks::$createdLinks[2] = $parserOutput->mCategories;
			}
			if (isset($eliminate['images']) && $eliminate['images']) {
				\DynamicPageListHooks::$createdLinks[3] = $parserOutput->mImages;
			}
		}
	}

	/**
	 * Sort an array of Article objects by the card suit symbol.
	 *
	 * @access	private
	 * @param	array	Article objects in an array.
	 * @return	array	Sorted objects
	 */
	private function cardSuitSort($articles) {
		$sortKeys = [];
		foreach ($articles as $key => $article) {
			$title  = preg_replace('/.*:/', '', $article->mTitle);
			$tokens  = preg_split('/ - */', $title);
			$newKey = '';
			foreach ($tokens as $token) {
				$initial = substr($token, 0, 1);
				if ($initial >= '1' && $initial <= '7') {
					$newKey .= $initial;
					$suit = substr($token, 1);
					if ($suit == '♣') {
						$newKey .= '1';
					} elseif ($suit == '♦') {
						$newKey .= '2';
					} elseif ($suit == '♥') {
						$newKey .= '3';
					} elseif ($suit == '♠') {
						$newKey .= '4';
					} elseif (strtolower($suit) == 'sa' || strtolower($suit) == 'nt') {
						$newKey .= '5 ';
					} else {
						$newKey .= $suit;
					}
				} elseif (strtolower($initial) == 'p') {
					$newKey .= '0 ';
				} elseif (strtolower($initial) == 'x') {
					$newKey .= '8 ';
				} else {
					$newKey .= $token;
				}
			}
			$sortKeys[$key] = $newKey;
		}
		asort($sortKeys);
		foreach ($sortKeys as $oldKey => $newKey) {
			$sortedArticles[] = $articles[$oldKey];
		}
		return $sortedArticles;
	}
}
?>