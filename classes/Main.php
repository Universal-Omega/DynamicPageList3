<?php
/**
 * DynamicPageList
 * Parameters
 *
 * @author		IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license		GPL
 * @package		DynamicPageList
 *
 **/
namespace DPL;

class Main {
	/**
	 * Mediawiki Database Object
	 *
	 * @var		object
	 */
	static private $DB = null;

	/**
	 * The real callback function for converting the input text to wiki text output
	 *
	 * @access	public
	 * @return	????
	 */
	public static function dynamicPageList($input, $params, $parser, &$bReset, $calledInMode) {
		global $wgUser, $wgLang, $wgContLang, $wgRequest;
		global $wgNonincludableNamespaces;

		// Output
		$output = '';

		//Make sure database is setup.
		self::$DB = wfGetDB(DB_SLAVE);

		//logger (display of debug messages)
		$logger = new Logger();

		//check that we are not in an infinite transclusion loop
		if (isset($parser->mTemplatePath[$parser->mTitle->getPrefixedText()])) {
			return $logger->escapeMsg(\DynamicPageListHooks::WARN_TRANSCLUSIONLOOP, $parser->mTitle->getPrefixedText());
		}

		/**
		 * Initialization
		 */
		$parameters = new Parameters();

		$dplStartTime = microtime(true);

		// Local parser created. See http://www.mediawiki.org/wiki/Extensions_FAQ#How_do_I_render_wikitext_in_my_extension.3F
		$localParser = new \Parser();
		$pOptions    = $parser->mOptions;

		// check if DPL shall only be executed from protected pages
		if (array_key_exists('RunFromProtectedPagesOnly', Options::$options) && Options::$options['RunFromProtectedPagesOnly'] == true && !($parser->mTitle->isProtected('edit'))) {

			// Ideally we would like to allow using a DPL query if the query istelf is coded on a template page
			// which is protected. Then there would be no need for the article to be protected.
			// BUT: How can one find out from which wiki source an extension has been invoked???

			return (Options::$options['RunFromProtectedPagesOnly']);
		}

		$sPageTable          = self::$DB->tableName('page');
		$sCategorylinksTable = self::$DB->tableName('categorylinks');

		// Extension variables
		// Allowed namespaces for DPL: all namespaces except the first 2: Media (-2) and Special (-1), because we cannot use the DB for these to generate dynamic page lists.
		if (!is_array(\DynamicPageListHooks::$allowedNamespaces)) { // Initialization
			$aNs                                   = $wgContLang->getNamespaces();
			\DynamicPageListHooks::$allowedNamespaces = array_slice($aNs, 2, count($aNs), true);
			if (!is_array(Options::$options['namespace'])) {
				Options::$options['namespace'] = \DynamicPageListHooks::$allowedNamespaces;
			} else {
				// Make sure user namespace options are allowed.
				Options::$options['namespace'] = array_intersect(Options::$options['namespace'], \DynamicPageListHooks::$allowedNamespaces);
			}
			if (!isset(Options::$options['namespace']['default'])) {
				Options::$options['namespace']['default'] = null;
			}
			if (!is_array(Options::$options['notnamespace'])) {
				Options::$options['notnamespace'] = \DynamicPageListHooks::$allowedNamespaces;
			} else {
				Options::$options['notnamespace'] = array_intersect(Options::$options['notnamespace'], \DynamicPageListHooks::$allowedNamespaces);
			}
			if (!isset(Options::$options['notnamespace']['default'])) {
				Options::$options['notnamespace']['default'] = null;
			}
		}

		// check parameters which can be set via the URL

		self::getUrlArgs();

		if (strpos($input, '{%DPL_') >= 0) {
			for ($i = 1; $i <= 5; $i++) {
				$input = self::resolveUrlArg($input, 'DPL_arg' . $i);
			}
		}

		$_sOffset = $wgRequest->getVal('DPL_offset', Options::$options['offset']['default']);
		$iOffset  = ($_sOffset == '') ? 0 : intval($_sOffset);

		// commandline parameters like %DPL_offset% are replaced
		$input = self::resolveUrlArg($input, 'DPL_offset');
		$input = self::resolveUrlArg($input, 'DPL_count');
		$input = self::resolveUrlArg($input, 'DPL_fromTitle');
		$input = self::resolveUrlArg($input, 'DPL_findTitle');
		$input = self::resolveUrlArg($input, 'DPL_toTitle');

		$originalInput = $input;

		$bDPLRefresh = ($wgRequest->getVal('DPL_refresh', '') == 'yes');

		//Options
		$DPLCache        = '';
		$DPLCachePath    = '';

		//Array for LINK / TEMPLATE / CATGEORY / IMAGE by RESET / ELIMINATE
		if (Options::$options['eliminate'] == 'all') {
			$bReset = array(
				false,
				false,
				false,
				false,
				true,
				true,
				true,
				true
			);
		} else {
			$bReset = array(
				false,
				false,
				false,
				false,
				false,
				false,
				false,
				false
			);
		}

		// ###### PARSE PARAMETERS ######

		// we replace double angle brackets by < > ; thus we avoid premature tag expansion in the input
		$input = str_replace('»', '>', $input);
		$input = str_replace('«', '<', $input);

		// use the ¦ as a general alias for |
		$input = str_replace('¦', '|', $input); // the symbol is utf8-escaped

		// the combination '²{' and '}²'will be translated to double curly braces; this allows postponed template execution
		// which is crucial for DPL queries which call other DPL queries
		$input = str_replace('²{', '{{', $input);
		$input = str_replace('}²', '}}', $input);

		$input         = str_replace(["\r\n", "\r"], "\n", $input);
		$input         = trim($input, "\n");
		$aParams       = explode("\n", $input);
		$bIncludeUncat = false; // to check if pseudo-category of Uncategorized pages is included

		// version 0.9:
		// we do not parse parameters recursively when reading them in.
		// we rather leave them unchanged, produce the complete output and then finally
		// parse the result recursively. This allows to build complex structures in the output
		// which are only understood by the parser if seen as a whole

		foreach ($aParams as $key => $parameterOption) {
			list($parameter, $option) = explode('=', $parameterOption, 2);
			$parameter = trim($parameter);
			$option  = trim($option);
			if (count($parameter) < 2) {
				if (trim($aParam[0]) != '') {
					$output .= $logger->escapeMsg(\DynamicPageListHooks::WARN_UNKNOWNPARAM, $aParam[0] . " [missing '=']", implode(', ', ParametersData::getParametersForRichness()));
					continue;
				}
			}

			if (empty($parameter) || $parameter[0] == '#' || !ParametersData::testRichness($parameter)) {
				continue;
			}

			// ignore parameter settings without argument (except namespace and category)
			if ($option == '') {
				if ($parameter != 'namespace' && $parameter != 'notnamespace' && $parameter != 'category' && array_key_exists($parameter, Options::$options)) {
					continue;
				}
			}

			switch ($parameter) {
				$function = str_replace(['<', '>'], ['LT', 'GT'], $parameter);
				//Parameter functions generally return their processed options, but we will grab them all at the end instead.
				if ($parameterHandler->$function($option) === false) {
					//Do not build this into the output just yet.  It will be collected at the end.
					$logger->msgWrongParam($parameter, $option);
				}
			}
		}

		// set COUNT

		if ($sCount == '') {
			$sCount = $sCountScroll;
		}
		if ($sCount == '') {
			$iCount = -1;
		} else {
			if (preg_match(Options::$options['count']['pattern'], $sCount)) {
				$iCount = intval($sCount);
			} else {
				// wrong value
				$output .= $logger->msgWrongParam('count', "$sCount : not a number!");
				$iCount = 1;
			}
		}
		if (!\DynamicPageListHooks::$allowUnlimitedResults && ($iCount < 0 || $iCount > \DynamicPageListHooks::$maxResultCount)) {
			// justify limits;
			$iCount = \DynamicPageListHooks::$maxResultCount;
		}


		// disable parser cache if caching is not allowed (which is default for DPL but not for <DynamicPageList>)
		if (!$bAllowCachedResults) {
			$parser->disableCache();
		}
		// place cache warning in resultsheader
		if ($bWarnCachedResults) {
			$sResultsHeader = '{{DPL Cache Warning}}' . $sResultsHeader;
		}



		if ($sExecAndExit != '') {
			// the keyword "geturlargs" is used to return the Url arguments and do nothing else.
			if ($sExecAndExit == 'geturlargs') {
				return '';
			}
			// in all other cases we return the value of the argument (which may contain parser function calls)
			return $sExecAndExit;
		}



		// if Caching is desired AND if the cache is up to date: get result from Cache and exit

		global $wgUploadDirectory, $wgRequest;
		if ($DPLCache != '') {
			$cacheFile = "$wgUploadDirectory/dplcache/$DPLCachePath/$DPLCache";
			// when the page containing the DPL statement is changed we must recreate the cache as the DPL statement may have changed
			// when the page containing the DPL statement is changed we must recreate the cache as the DPL statement may have changed
			// otherwise we accept thecache if it is not too old
			if (!$bDPLRefresh && file_exists($cacheFile)) {
				// find out if cache is acceptable or too old
				$diff = time() - filemtime($cacheFile);
				if ($diff <= $iDPLCachePeriod) {
					$cachedOutput    = file_get_contents($cacheFile);
					$cachedOutputPos = strpos($cachedOutput, "+++\n");
					// when submitting a page we check if the DPL statement has changed
					if ($wgRequest->getVal('action', 'view') != 'submit' || ($originalInput == substr($cachedOutput, 0, $cachedOutputPos))) {
						$cacheTimeStamp = self::prettyTimeStamp(date('YmdHis', filemtime($cacheFile)));
						$cachePeriod    = self::durationTime($iDPLCachePeriod);
						$diffTime       = self::durationTime($diff);
						$output .= substr($cachedOutput, $cachedOutputPos + 4);
						if ($logger->iDebugLevel >= 2) {
							$output .= "{{Extension DPL cache|mode=get|page={{FULLPAGENAME}}|cache=$DPLCache|date=$cacheTimeStamp|now=" . date('H:i:s') . "|age=$diffTime|period=$cachePeriod|offset=$iOffset}}";
						}
						// ignore further parameters, stop processing, return cache content
						return $output;
					}
				}
			}
		}

		// debug level 5 puts nowiki tags around the output
		if ($logger->iDebugLevel == 5) {
			$logger->iDebugLevel = 2;
			$sResultsHeader      = '<pre><nowiki>' . $sResultsHeader;
			$sResultsFooter .= '</nowiki></pre>';
		}

		// construct internal keys for TableRow according to the structure of "include"
		// this will be needed in the output phase
		self::updateTableRowKeys($aTableRow, $aSecLabels);
		// foreach ($aTableRow as $key => $val) $output .= "TableRow($key)=$val;<br/>";

		$iIncludeCatCount      = count($aIncludeCategories);
		$iTotalIncludeCatCount = count($aIncludeCategories, COUNT_RECURSIVE) - $iIncludeCatCount;
		$iExcludeCatCount      = count($aExcludeCategories);
		$iTotalCatCount        = $iTotalIncludeCatCount + $iExcludeCatCount;

		if ($calledInMode == 'tag') {
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
		if (($calledInMode == 'tag' && $bReset[0]) || $calledInMode == 'func') {
			if ($bReset[0]) {
				\DynamicPageListHooks::$createdLinks['resetLinks'] = true;
			}
			// register a hook to reset links which were produced during parsing DPL output
			global $wgHooks;
			if (!in_array('DynamicPageListHooks::endReset', $wgHooks['ParserAfterTidy'])) {
				$wgHooks['ParserAfterTidy'][] = 'DynamicPageListHooks::endReset';
			}
		}


		// ###### CHECKS ON PARAMETERS ######

		// too many categories!
		if (($iTotalCatCount > \DynamicPageListHooks::$maxCategoryCount) && (!\DynamicPageListHooks::$allowUnlimitedCategories)) {
			return $output . $logger->escapeMsg(\DynamicPageListHooks::FATAL_TOOMANYCATS, \DynamicPageListHooks::$maxCategoryCount);
		}

		// too few categories!
		if ($iTotalCatCount < \DynamicPageListHooks::$minCategoryCount) {
			return $output . $logger->escapeMsg(\DynamicPageListHooks::FATAL_TOOFEWCATS, \DynamicPageListHooks::$minCategoryCount);
		}

		// no selection criteria! Warn only if no debug level is set
		if ($iTotalCatCount == 0 && $bSelectionCriteriaFound == false) {
			if ($logger->iDebugLevel <= 1) {
				return $output;
			}
			return $output . $logger->escapeMsg(\DynamicPageListHooks::FATAL_NOSELECTION);
		}

		// ordermethod=sortkey requires ordermethod=category
		// delayed to the construction of the SQL query, see near line 2211, gs
		//if (in_array('sortkey',$aOrderMethods) && ! in_array('category',$aOrderMethods)) $aOrderMethods[] = 'category';

		// no included categories but ordermethod=categoryadd or addfirstcategorydate=true!
		if ($iTotalIncludeCatCount == 0 && ($aOrderMethods[0] == 'categoryadd' || $bAddFirstCategoryDate == true)) {
			return $output . $logger->escapeMsg(\DynamicPageListHooks::FATAL_CATDATEBUTNOINCLUDEDCATS);
		}

		// more than one included category but ordermethod=categoryadd or addfirstcategorydate=true!
		// we ALLOW this parameter combination, risking ambiguous results
		//if ($iTotalIncludeCatCount > 1 && ($aOrderMethods[0] == 'categoryadd' || $bAddFirstCategoryDate == true) )
		//	return $output . $logger->escapeMsg(\DynamicPageListHooks::FATAL_CATDATEBUTMORETHAN1CAT);

		// no more than one type of date at a time!
		if ($bAddPageTouchedDate + $bAddFirstCategoryDate + $bAddEditDate > 1) {
			return $output . $logger->escapeMsg(\DynamicPageListHooks::FATAL_MORETHAN1TYPEOFDATE);
		}

		// the dominant section must be one of the sections mentioned in includepage
		if ($iDominantSection > 0 && count($aSecLabels) < $iDominantSection) {
			return $output . $logger->escapeMsg(\DynamicPageListHooks::FATAL_DOMINANTSECTIONRANGE, count($aSecLabels));
		}

		// category-style output requested with not compatible order method
		if ($sPageListMode == 'category' && !array_intersect($aOrderMethods, array(
			'sortkey',
			'title',
			'titlewithoutnamespace'
		))) {
			return $output . $logger->escapeMsg(\DynamicPageListHooks::FATAL_WRONGORDERMETHOD, 'mode=category', 'sortkey | title | titlewithoutnamespace');
		}

		// addpagetoucheddate=true with unappropriate order methods
		if ($bAddPageTouchedDate && !array_intersect($aOrderMethods, array(
			'pagetouched',
			'title'
		))) {
			return $output . $logger->escapeMsg(\DynamicPageListHooks::FATAL_WRONGORDERMETHOD, 'addpagetoucheddate=true', 'pagetouched | title');
		}

		// addeditdate=true but not (ordermethod=...,firstedit or ordermethod=...,lastedit)
		//firstedit (resp. lastedit) -> add date of first (resp. last) revision
		if ($bAddEditDate && !array_intersect($aOrderMethods, array(
			'firstedit',
			'lastedit'
		)) & ($sLastRevisionBefore . $sAllRevisionsBefore . $sFirstRevisionSince . $sAllRevisionsSince == '')) {
			return $output . $logger->escapeMsg(\DynamicPageListHooks::FATAL_WRONGORDERMETHOD, 'addeditdate=true', 'firstedit | lastedit');
		}

		// adduser=true but not (ordermethod=...,firstedit or ordermethod=...,lastedit)
		/**
		 * @todo allow to add user for other order methods.
		 * The fact is a page may be edited by multiple users. Which user(s) should we show? all? the first or the last one?
		 * Ideally, we could use values such as 'all', 'first' or 'last' for the adduser parameter.
		 */
		if ($bAddUser && !array_intersect($aOrderMethods, array(
			'firstedit',
			'lastedit'
		)) & ($sLastRevisionBefore . $sAllRevisionsBefore . $sFirstRevisionSince . $sAllRevisionsSince == '')) {
			return $output . $logger->escapeMsg(\DynamicPageListHooks::FATAL_WRONGORDERMETHOD, 'adduser=true', 'firstedit | lastedit');
		}
		if (isset($sMinorEdits) && !array_intersect($aOrderMethods, array(
			'firstedit',
			'lastedit'
		))) {
			return $output . $logger->escapeMsg(\DynamicPageListHooks::FATAL_WRONGORDERMETHOD, 'minoredits', 'firstedit | lastedit');
		}

		/**
		 * If we include the Uncategorized, we need the 'dpl_clview': VIEW of the categorylinks table where we have cl_to='' (empty string) for all uncategorized pages. This VIEW must have been created by the administrator of the mediawiki DB at installation. See the documentation.
		 */
		$sDplClView = '';
		if ($bIncludeUncat) {
			$sDplClView = self::$DB->tableName('dpl_clview');
			// If the view is not there, we can't perform logical operations on the Uncategorized.
			if (!self::$DB->tableExists('dpl_clview')) {
				$sSqlCreate_dpl_clview = 'CREATE VIEW ' . $sDplClView . " AS SELECT IFNULL(cl_from, page_id) AS cl_from, IFNULL(cl_to, '') AS cl_to, cl_sortkey FROM " . $sPageTable . ' LEFT OUTER JOIN ' . $sCategorylinksTable . ' ON ' . $sPageTable . '.page_id=cl_from';
				$output .= $logger->escapeMsg(\DynamicPageListHooks::FATAL_NOCLVIEW, $sDplClView, $sSqlCreate_dpl_clview);
				return $output;
			}
		}

		//add*** parameters have no effect with 'mode=category' (only namespace/title can be viewed in this mode)
		if ($sPageListMode == 'category' && ($bAddCategories || $bAddEditDate || $bAddFirstCategoryDate || $bAddPageTouchedDate || $bIncPage || $bAddUser || $bAddAuthor || $bAddContribution || $bAddLastEditor)) {
			$output .= $logger->escapeMsg(\DynamicPageListHooks::WARN_CATOUTPUTBUTWRONGPARAMS);
		}

		//headingmode has effects with ordermethod on multiple components only
		if ($sHListMode != 'none' && count($aOrderMethods) < 2) {
			$output .= $logger->escapeMsg(\DynamicPageListHooks::WARN_HEADINGBUTSIMPLEORDERMETHOD, $sHListMode, 'none');
			$sHListMode = 'none';
		}

		// openreferences is incompatible with many other options
		if ($acceptOpenReferences && $bConflictsWithOpenReferences) {
			$output .= $logger->escapeMsg(\DynamicPageListHooks::FATAL_OPENREFERENCES);
			$acceptOpenReferences = false;
		}


		// if 'table' parameter is set: derive values for listseparators, secseparators and multisecseparators
		$defaultTemplateSuffix = '.default';
		if ($sTable != '') {
			$defaultTemplateSuffix = '';
			$sPageListMode         = 'userformat';
			$sInlTxt               = '';
			$withHLink             = "[[%PAGE%|%TITLE%]]\n|";
			foreach (explode(',', $sTable) as $tabnr => $tab) {
				if ($tabnr == 0) {
					if ($tab == '') {
						$tab = 'class=wikitable';
					}
					$aListSeparators[0] = '{|' . $tab;
				} else {
					if ($tabnr == 1 && $tab == '-') {
						$withHLink = '';
						continue;
					}
					if ($tabnr == 1 && $tab == '') {
						$tab = 'Article';
					}
					$aListSeparators[0] .= "\n!$tab";
				}
			}
			$aListSeparators[1] = '';
			// the user may have specified the third parameter of 'format' to add meta attributes of articles to the table
			if (!array_key_exists(2, $aListSeparators)) {
				$aListSeparators[2] = '';
			}
			$aListSeparators[3] = "\n|}";

			for ($i = 0; $i < count($aSecLabels); $i++) {
				if ($i == 0) {
					$aSecSeparators[0]      = "\n|-\n|" . $withHLink; //."\n";
					$aSecSeparators[1]      = '';
					$aMultiSecSeparators[0] = "\n|-\n|" . $withHLink; // ."\n";
				} else {
					$aSecSeparators[2 * $i]     = "\n|"; // ."\n";
					$aSecSeparators[2 * $i + 1] = '';
					if (is_array($aSecLabels[$i]) && $aSecLabels[$i][0] == '#') {
						$aMultiSecSeparators[$i] = "\n----\n";
					}
					if ($aSecLabels[$i][0] == '#') {
						$aMultiSecSeparators[$i] = "\n----\n";
					} else {
						$aMultiSecSeparators[$i] = "<br/>\n";
					}
				}
			}
		}

		// backward scrolling: if the user specified titleLE and wants ascending order we reverse the SQL sort order
		if ($sTitleLE != '' && $sTitleGE == '') {
			if ($sOrder == 'ascending') {
				$sOrder = 'descending';
			}
		}

		$output .= '{{Extension DPL}}';



		// ###### BUILD SQL QUERY ######
		$sSqlPage_counter  = '';
		$sSqlPage_size     = '';
		$sSqlPage_touched  = '';
		$sSqlCalcFoundRows = '';
		if (!\DynamicPageListHooks::$allowUnlimitedResults && $sGoal != 'categories' && strpos($sResultsHeader . $sResultsFooter . $sNoResultsHeader, '%TOTALPAGES%') !== false) {
			$sSqlCalcFoundRows = 'SQL_CALC_FOUND_ROWS';
		}
		if ($sDistinctResultSet === false) {
			$sSqlDistinct = '';
		} else {
			$sSqlDistinct = 'DISTINCT';
		}
		$sSqlGroupBy = '';
		if ($sDistinctResultSet == 'strict' && (count($aLinksTo) + count($aNotLinksTo) + count($aLinksFrom) + count($aNotLinksFrom) + count($aLinksToExternal) + count($aImageUsed)) > 0) {
			$sSqlGroupBy = 'page_title';
		}
		$sSqlSortkey            = '';
		$sSqlCl_to              = '';
		$sSqlCats               = '';
		$sSqlCl_timestamp       = '';
		$sSqlClHeadTable        = '';
		$sSqlCond_page_cl_head  = '';
		$sSqlClTableForGC       = '';
		$sSqlCond_page_cl_gc    = '';
		$sSqlRCTable            = ''; // recent changes
		$sRCTable               = self::$DB->tableName('recentchanges');
		$sRevisionTable         = self::$DB->tableName('revision');
		$sSqlRevisionTable      = '';
		$sSqlRev_timestamp      = '';
		$sSqlRev_id             = '';
		$sSqlRev_user           = '';
		$sSqlCond_page_rev      = '';
		$sPageLinksTable        = self::$DB->tableName('pagelinks');
		$sExternalLinksTable    = self::$DB->tableName('externallinks');
		$sImageLinksTable       = self::$DB->tableName('imagelinks');
		$sTemplateLinksTable    = self::$DB->tableName('templatelinks');
		$sSqlPageLinksTable     = '';
		$sSqlExternalLinksTable = '';
		$sSqlCreationRevisionTable = '';
		$sSqlNoCreationRevisionTable = '';
		$sSqlChangeRevisionTable = '';
		$sSqlCond_page_pl       = '';
		$sSqlCond_page_el       = '';
		$sSqlCond_page_tpl      = '';
		$sSqlCond_MaxCat        = '';
		$sSqlWhere              = ' WHERE 1=1 ';
		$sSqlSelPage            = ''; // initial page for selection

		// normally we create a result of normal pages, but when goal=categories is set, we create a list of categories
		// as this conflicts with some options we need to avoid producing incoorect SQl code
		$bGoalIsPages = true;
		if ($sGoal == 'categories') {
			$aOrderMethods = explode(',', '');
			$bGoalIsPages  = false;
		}

		foreach ($aOrderMethods as $sOrderMethod) {
			switch ($sOrderMethod) {
				case 'category':
					$sSqlCl_to             = "cl_head.cl_to, "; // Gives category headings in the result
					$sSqlClHeadTable       = ((in_array('', $aCatHeadings) || in_array('', $aCatNotHeadings)) ? $sDplClView : $sCategorylinksTable) . ' AS cl_head'; // use dpl_clview if Uncategorized in headings
					$sSqlCond_page_cl_head = 'page_id=cl_head.cl_from';
					if (!empty($aCatHeadings)) {
						$sSqlWhere .= " AND cl_head.cl_to IN (" . self::$DB->makeList($aCatHeadings) . ")";
					}
					if (!empty($aCatNotHeadings)) {
						$sSqlWhere .= " AND NOT (cl_head.cl_to IN (" . self::$DB->makeList($aCatNotHeadings) . "))";
					}
					break;
				case 'firstedit':
					$sSqlRevisionTable = $sRevisionTable . ' AS rev, ';
					$sSqlRev_timestamp = ', rev_timestamp';
					// deleted because of conflict with revsion-parameters
					$sSqlCond_page_rev = ' AND ' . $sPageTable . '.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MIN(rev_aux.rev_timestamp) FROM ' . $sRevisionTable . ' AS rev_aux WHERE rev_aux.rev_page=rev.rev_page )';
					break;
				case 'pagetouched':
					$sSqlPage_touched = ", $sPageTable.page_touched as page_touched";
					break;
				case 'lastedit':
					if (\DynamicPageListHooks::isLikeIntersection()) {
						$sSqlPage_touched = ", $sPageTable.page_touched as page_touched";
					} else {
						$sSqlRevisionTable = $sRevisionTable . ' AS rev, ';
						$sSqlRev_timestamp = ', rev_timestamp';
						// deleted because of conflict with revision-parameters
						$sSqlCond_page_rev = ' AND ' . $sPageTable . '.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MAX(rev_aux.rev_timestamp) FROM ' . $sRevisionTable . ' AS rev_aux WHERE rev_aux.rev_page=rev.rev_page )';
					}
					break;
				case 'sortkey':
					// We need the namespaces with strictly positive indices (DPL allowed namespaces, except the first one: Main).
					$aStrictNs      = array_slice(\DynamicPageListHooks::$allowedNamespaces, 1, count(\DynamicPageListHooks::$allowedNamespaces), true);
					// map ns index to name
					$sSqlNsIdToText = 'CASE ' . $sPageTable . '.page_namespace';
					foreach ($aStrictNs as $iNs => $sNs)
						$sSqlNsIdToText .= ' WHEN ' . intval($iNs) . " THEN " . self::$DB->addQuotes($sNs);
					$sSqlNsIdToText .= ' END';
					// If cl_sortkey is null (uncategorized page), generate a sortkey in the usual way (full page name, underscores replaced with spaces).
					// UTF-8 created problems with non-utf-8 MySQL databases
					//see line 2011 (order method sortkey requires category
					if (count($aIncludeCategories) + count($aExcludeCategories) > 0) {
						if (in_array('category', $aOrderMethods) && (count($aIncludeCategories) + count($aExcludeCategories) > 0)) {
							$sSqlSortkey = ", IFNULL(cl_head.cl_sortkey, REPLACE(CONCAT( IF(" . $sPageTable . ".page_namespace=0, '', CONCAT(" . $sSqlNsIdToText . ", ':')), " . $sPageTable . ".page_title), '_', ' ')) " . $sOrderCollation . " as sortkey";
						} else {
							$sSqlSortkey = ", IFNULL(cl0.cl_sortkey, REPLACE(CONCAT( IF(" . $sPageTable . ".page_namespace=0, '', CONCAT(" . $sSqlNsIdToText . ", ':')), " . $sPageTable . ".page_title), '_', ' ')) " . $sOrderCollation . " as sortkey";
						}
					} else {
						$sSqlSortkey = ", REPLACE(CONCAT( IF(" . $sPageTable . ".page_namespace=0, '', CONCAT(" . $sSqlNsIdToText . ", ':')), " . $sPageTable . ".page_title), '_', ' ') " . $sOrderCollation . " as sortkey";
					}
					break;
				case 'pagesel':
					$sSqlSortkey = ', CONCAT(pl.pl_namespace,pl.pl_title) ' . $sOrderCollation . ' as sortkey';
					break;
				case 'titlewithoutnamespace':
					$sSqlSortkey = ", $sPageTable.page_title " . $sOrderCollation . " as sortkey";
					break;
				case 'title':
					$aStrictNs = array_slice(\DynamicPageListHooks::$allowedNamespaces, 1, count(\DynamicPageListHooks::$allowedNamespaces), true);
					// map namespace index to name
					if ($acceptOpenReferences) {
						$sSqlNsIdToText = 'CASE pl_namespace';
						foreach ($aStrictNs as $iNs => $sNs)
							$sSqlNsIdToText .= ' WHEN ' . intval($iNs) . " THEN " . self::$DB->addQuotes($sNs);
						$sSqlNsIdToText .= ' END';
						$sSqlSortkey = ", REPLACE(CONCAT( IF(pl_namespace=0, '', CONCAT(" . $sSqlNsIdToText . ", ':')), pl_title), '_', ' ') " . $sOrderCollation . " as sortkey";
					} else {
						$sSqlNsIdToText = 'CASE ' . $sPageTable . '.page_namespace';
						foreach ($aStrictNs as $iNs => $sNs)
							$sSqlNsIdToText .= ' WHEN ' . intval($iNs) . " THEN " . self::$DB->addQuotes($sNs);
						$sSqlNsIdToText .= ' END';
						// Generate sortkey like for category links. UTF-8 created problems with non-utf-8 MySQL databases
						$sSqlSortkey = ", REPLACE(CONCAT( IF(" . $sPageTable . ".page_namespace=0, '', CONCAT(" . $sSqlNsIdToText . ", ':')), " . $sPageTable . ".page_title), '_', ' ') " . $sOrderCollation . " as sortkey";
					}
					break;
				case 'user':
					$sSqlRevisionTable = $sRevisionTable . ', ';
					$sSqlRev_user      = ', rev_user, rev_user_text, rev_comment';
					break;
				case 'none':
					break;
			}
		}

		// linksto

		if (count($aLinksTo) > 0) {
			$sSqlPageLinksTable .= $sPageLinksTable . ' AS pl, ';
			$sSqlCond_page_pl .= ' AND ' . $sPageTable . '.page_id=pl.pl_from AND ';
			$sSqlSelPage = ', pl.pl_title AS sel_title, pl.pl_namespace AS sel_ns';
			$n           = 0;
			foreach ($aLinksTo as $linkGroup) {
				if (++$n > 1) {
					break;
				}
				$sSqlCond_page_pl .= '( ';
				$m = 0;
				foreach ($linkGroup as $link) {
					if (++$m > 1) {
						$sSqlCond_page_pl .= ' OR ';
					}
					$sSqlCond_page_pl .= '(pl.pl_namespace=' . intval($link->getNamespace());
					if (strpos($link->getDbKey(), '%') >= 0) {
						$operator = ' LIKE ';
					} else {
						$operator = '=';
					}
					if ($bIgnoreCase) {
						$sSqlCond_page_pl .= ' AND LOWER(CAST(pl.pl_title AS char))' . $operator . 'LOWER(' . self::$DB->addQuotes($link->getDbKey()) . '))';
					} else {
						$sSqlCond_page_pl .= ' AND pl.pl_title' . $operator . self::$DB->addQuotes($link->getDbKey()) . ')';
					}
				}
				$sSqlCond_page_pl .= ')';
			}
		}
		if (count($aLinksTo) > 1) {
			$n = 0;
			foreach ($aLinksTo as $linkGroup) {
				if (++$n == 1) {
					continue;
				}
				$m = 0;
				$sSqlCond_page_pl .= ' AND EXISTS(select pl_from FROM ' . $sPageLinksTable . ' WHERE (' . $sPageLinksTable . '.pl_from=page_id AND (';
				foreach ($linkGroup as $link) {
					if (++$m > 1) {
						$sSqlCond_page_pl .= ' OR ';
					}
					$sSqlCond_page_pl .= '(' . $sPageLinksTable . '.pl_namespace=' . intval($link->getNamespace());
					if (strpos($link->getDbKey(), '%') >= 0) {
						$operator = ' LIKE ';
					} else {
						$operator = '=';
					}
					if ($bIgnoreCase) {
						$sSqlCond_page_pl .= ' AND LOWER(CAST(' . $sPageLinksTable . '.pl_title AS char))' . $operator . 'LOWER(' . self::$DB->addQuotes($link->getDbKey()) . ')';
					} else {
						$sSqlCond_page_pl .= ' AND ' . $sPageLinksTable . '.pl_title' . $operator . self::$DB->addQuotes($link->getDbKey());
					}
					$sSqlCond_page_pl .= ')';
				}
				$sSqlCond_page_pl .= ')))';
			}
		}

		// notlinksto
		if (count($aNotLinksTo) > 0) {
			$sSqlCond_page_pl .= ' AND ' . $sPageTable . '.page_id NOT IN (SELECT ' . $sPageLinksTable . '.pl_from FROM ' . $sPageLinksTable . ' WHERE (';
			$n = 0;
			foreach ($aNotLinksTo as $links) {
				foreach ($links as $link) {
					if ($n > 0) {
						$sSqlCond_page_pl .= ' OR ';
					}
					$sSqlCond_page_pl .= '(' . $sPageLinksTable . '.pl_namespace=' . intval($link->getNamespace());
					if (strpos($link->getDbKey(), '%') >= 0) {
						$operator = ' LIKE ';
					} else {
						$operator = '=';
					}
					if ($bIgnoreCase) {
						$sSqlCond_page_pl .= ' AND LOWER(CAST(' . $sPageLinksTable . '.pl_title AS char))' . $operator . 'LOWER(' . self::$DB->addQuotes($link->getDbKey()) . '))';
					} else {
						$sSqlCond_page_pl .= ' AND		 ' . $sPageLinksTable . '.pl_title' . $operator . self::$DB->addQuotes($link->getDbKey()) . ')';
					}
					$n++;
				}
			}
			$sSqlCond_page_pl .= ') )';
		}

		// linksfrom
		if (count($aLinksFrom) > 0) {
			if ($acceptOpenReferences) {
				$sSqlCond_page_pl .= ' AND (';
				$n = 0;
				foreach ($aLinksFrom as $links) {
					foreach ($links as $link) {
						if ($n > 0) {
							$sSqlCond_page_pl .= ' OR ';
						}
						$sSqlCond_page_pl .= '(pl_from=' . $link->getArticleID() . ')';
						$n++;
					}
				}
				$sSqlCond_page_pl .= ')';
			} else {
				$sSqlPageLinksTable .= $sPageLinksTable . ' AS plf, ' . $sPageTable . 'AS pagesrc, ';
				$sSqlCond_page_pl .= ' AND ' . $sPageTable . '.page_namespace = plf.pl_namespace AND ' . $sPageTable . '.page_title = plf.pl_title	AND pagesrc.page_id=plf.pl_from AND (';
				$sSqlSelPage = ', pagesrc.page_title AS sel_title, pagesrc.page_namespace AS sel_ns';
				$n           = 0;
				foreach ($aLinksFrom as $links) {
					foreach ($links as $link) {
						if ($n > 0) {
							$sSqlCond_page_pl .= ' OR ';
						}
						$sSqlCond_page_pl .= '(plf.pl_from=' . $link->getArticleID() . ')';
						$n++;
					}
				}
				$sSqlCond_page_pl .= ')';
			}
		}

		// notlinksfrom
		if (count($aNotLinksFrom) > 0) {
			if ($acceptOpenReferences) {
				$sSqlCond_page_pl .= ' AND (';
				$n = 0;
				foreach ($aNotLinksFrom as $links) {
					foreach ($links as $link) {
						if ($n > 0) {
							$sSqlCond_page_pl .= ' AND ';
						}
						$sSqlCond_page_pl .= 'pl_from <> ' . $link->getArticleID() . ' ';
						$n++;
					}
				}
				$sSqlCond_page_pl .= ')';
			} else {
				$sSqlCond_page_pl .= ' AND CONCAT(page_namespace,page_title) NOT IN (SELECT CONCAT(' . $sPageLinksTable . '.pl_namespace,' . $sPageLinksTable . '.pl_title) from ' . $sPageLinksTable . ' WHERE (';
				$n = 0;
				foreach ($aNotLinksFrom as $links) {
					foreach ($links as $link) {
						if ($n > 0) {
							$sSqlCond_page_pl .= ' OR ';
						}
						$sSqlCond_page_pl .= $sPageLinksTable . '.pl_from=' . $link->getArticleID() . ' ';
						$n++;
					}
				}
				$sSqlCond_page_pl .= '))';
			}
		}

		// linkstoexternal
		if (count($aLinksToExternal) > 0) {
			$sSqlExternalLinksTable .= $sExternalLinksTable . ' AS el, ';
			$sSqlCond_page_el .= ' AND ' . $sPageTable . '.page_id=el.el_from AND (';
			$sSqlSelPage = ', el.el_to as el_to';
			$n           = 0;
			foreach ($aLinksToExternal as $linkGroup) {
				if (++$n > 1) {
					break;
				}
				$m = 0;
				foreach ($linkGroup as $link) {
					if (++$m > 1) {
						$sSqlCond_page_el .= ' OR ';
					}
					$sSqlCond_page_el .= '(el.el_to LIKE ' . self::$DB->addQuotes($link) . ')';
				}
			}
			$sSqlCond_page_el .= ')';
		}
		if (count($aLinksToExternal) > 1) {
			$n = 0;
			foreach ($aLinksToExternal as $linkGroup) {
				if (++$n == 1) {
					continue;
				}
				$m = 0;
				$sSqlCond_page_el .= ' AND EXISTS(SELECT el_from FROM ' . $sExternalLinksTable . ' WHERE (' . $sExternalLinksTable . '.el_from=page_id AND (';
				foreach ($linkGroup as $link) {
					if (++$m > 1) {
						$sSqlCond_page_el .= ' OR ';
					}
					$sSqlCond_page_el .= '(' . $sExternalLinksTable . '.el_to LIKE ' . self::$DB->addQuotes($link) . ')';
				}
				$sSqlCond_page_el .= ')))';
			}
		}

		// imageused
		if (count($aImageUsed) > 0) {
			$sSqlPageLinksTable .= $sImageLinksTable . ' AS il, ';
			$sSqlCond_page_pl .= ' AND ' . $sPageTable . '.page_id=il.il_from AND (';
			$sSqlSelPage = ', il.il_to AS image_sel_title';
			$n           = 0;
			foreach ($aImageUsed as $link) {
				if ($n > 0) {
					$sSqlCond_page_pl .= ' OR ';
				}
				if ($bIgnoreCase) {
					$sSqlCond_page_pl .= "LOWER(CAST(il.il_to AS char))=LOWER(" . self::$DB->addQuotes($link->getDbKey()) . ')';
				} else {
					$sSqlCond_page_pl .= "il.il_to=" . self::$DB->addQuotes($link->getDbKey());
				}
				$n++;
			}
			$sSqlCond_page_pl .= ')';
		}

		// imagecontainer
		if (count($aImageContainer) > 0) {
			$sSqlPageLinksTable .= $sImageLinksTable . ' AS ic, ';
			if ($acceptOpenReferences) {
				$sSqlCond_page_pl .= ' AND (';
			} else {
				$sSqlCond_page_pl .= ' AND ' . $sPageTable . '.page_namespace=\'6\' AND ' . $sPageTable . '.page_title=ic.il_to AND (';
			}
			$n = 0;
			foreach ($aImageContainer as $link) {
				if ($n > 0) {
					$sSqlCond_page_pl .= ' OR ';
				}
				if ($bIgnoreCase) {
					$sSqlCond_page_pl .= "LOWER(CAST(ic.il_from AS char)=LOWER(" . self::$DB->addQuotes($link->getArticleID()) . ')';
				} else {
					$sSqlCond_page_pl .= "ic.il_from=" . self::$DB->addQuotes($link->getArticleID());
				}
				$n++;
			}
			$sSqlCond_page_pl .= ')';
		}

		// uses
		if (count($aUses) > 0) {
			$sSqlPageLinksTable .= ' ' . $sTemplateLinksTable . ' as tl, ';
			$sSqlCond_page_pl .= ' AND ' . $sPageTable . '.page_id=tl.tl_from  AND (';
			$n = 0;
			foreach ($aUses as $link) {
				if ($n > 0) {
					$sSqlCond_page_pl .= ' OR ';
				}
				$sSqlCond_page_pl .= '(tl.tl_namespace=' . intval($link->getNamespace());
				if ($bIgnoreCase) {
					$sSqlCond_page_pl .= " AND LOWER(CAST(tl.tl_title AS char))=LOWER(" . self::$DB->addQuotes($link->getDbKey()) . '))';
				} else {
					$sSqlCond_page_pl .= " AND		 tl.tl_title=" . self::$DB->addQuotes($link->getDbKey()) . ')';
				}
				$n++;
			}
			$sSqlCond_page_pl .= ')';
		}

		// notuses
		if (count($aNotUses) > 0) {
			$sSqlCond_page_pl .= ' AND ' . $sPageTable . '.page_id NOT IN (SELECT ' . $sTemplateLinksTable . '.tl_from FROM ' . $sTemplateLinksTable . ' WHERE (';
			$n = 0;
			foreach ($aNotUses as $link) {
				if ($n > 0) {
					$sSqlCond_page_pl .= ' OR ';
				}
				$sSqlCond_page_pl .= '(' . $sTemplateLinksTable . '.tl_namespace=' . intval($link->getNamespace());
				if ($bIgnoreCase) {
					$sSqlCond_page_pl .= ' AND LOWER(CAST(' . $sTemplateLinksTable . '.tl_title AS char))=LOWER(' . self::$DB->addQuotes($link->getDbKey()) . '))';
				} else {
					$sSqlCond_page_pl .= ' AND ' . $sTemplateLinksTable . '.tl_title=' . self::$DB->addQuotes($link->getDbKey()) . ')';
				}
				$n++;
			}
			$sSqlCond_page_pl .= ') )';
		}

		// usedby
		if (count($aUsedBy) > 0) {
			if ($acceptOpenReferences) {
				$sSqlCond_page_tpl .= ' AND (';
				$n = 0;
				foreach ($aUsedBy as $link) {
					if ($n > 0) {
						$sSqlCond_page_tpl .= ' OR ';
					}
					$sSqlCond_page_tpl .= '(tpl_from=' . $link->getArticleID() . ')';
					$n++;
				}
				$sSqlCond_page_tpl .= ')';
			} else {
				$sSqlPageLinksTable .= $sTemplateLinksTable . ' AS tpl, ' . $sPageTable . 'AS tplsrc, ';
				$sSqlCond_page_tpl .= ' AND ' . $sPageTable . '.page_title = tpl.tl_title  AND tplsrc.page_id=tpl.tl_from AND (';
				$sSqlSelPage = ', tplsrc.page_title AS tpl_sel_title, tplsrc.page_namespace AS tpl_sel_ns';
				$n           = 0;
				foreach ($aUsedBy as $link) {
					if ($n > 0) {
						$sSqlCond_page_tpl .= ' OR ';
					}
					$sSqlCond_page_tpl .= '(tpl.tl_from=' . $link->getArticleID() . ')';
					$n++;
				}
				$sSqlCond_page_tpl .= ')';
			}
		}


		// recent changes  =============================

		if ($bAddContribution) {
			$sSqlRCTable = $sRCTable . ' AS rc, ';
			$sSqlSelPage .= ', SUM( ABS( rc.rc_new_len - rc.rc_old_len ) ) AS contribution, rc.rc_user_text AS contributor';
			$sSqlWhere .= ' AND page.page_id=rc.rc_cur_id';
			if ($sSqlGroupBy != '') {
				$sSqlGroupBy .= ', ';
			}
			$sSqlGroupBy .= 'rc.rc_cur_id';
		}

		// Revisions ==================================
		if ($sCreatedBy != "") {
		    $sSqlCreationRevisionTable = $sRevisionTable . ' AS creation_rev, ';
		    $sSqlCond_page_rev .= ' AND ' . self::$DB->addQuotes($sCreatedBy) . ' = creation_rev.rev_user_text' . ' AND creation_rev.rev_page = page_id' . ' AND creation_rev.rev_parent_id = 0';
		}
		if ($sNotCreatedBy != "") {
		    $sSqlNoCreationRevisionTable = $sRevisionTable . ' AS no_creation_rev, ';
		    $sSqlCond_page_rev .= ' AND ' . self::$DB->addQuotes($sNotCreatedBy) . ' != no_creation_rev.rev_user_text' . ' AND no_creation_rev.rev_page = page_id' . ' AND no_creation_rev.rev_parent_id = 0';
		}
		if ($sModifiedBy != "") {
		    $sSqlChangeRevisionTable = $sRevisionTable . ' AS change_rev, ';
		    $sSqlCond_page_rev .= ' AND ' . self::$DB->addQuotes($sModifiedBy) . ' = change_rev.rev_user_text' . ' AND change_rev.rev_page = page_id';
		}
		if ($sNotModifiedBy != "") {
		    $sSqlCond_page_rev .= ' AND NOT EXISTS (SELECT 1 FROM ' . $sRevisionTable . ' WHERE ' . $sRevisionTable . '.rev_page=page_id AND ' . $sRevisionTable . '.rev_user_text = ' . self::$DB->addQuotes($sNotModifiedBy) . ' LIMIT 1)';
		}
		if ($sLastModifiedBy != "") {
		    $sSqlCond_page_rev .= ' AND ' . self::$DB->addQuotes($sLastModifiedBy) . ' = (SELECT rev_user_text FROM ' . $sRevisionTable . ' WHERE ' . $sRevisionTable . '.rev_page=page_id ORDER BY ' . $sRevisionTable . '.rev_timestamp DESC LIMIT 1)';
		}
		if ($sNotLastModifiedBy != "") {
		    $sSqlCond_page_rev .= ' AND ' . self::$DB->addQuotes($sNotLastModifiedBy) . ' != (SELECT rev_user_text FROM ' . $sRevisionTable . ' WHERE ' . $sRevisionTable . '.rev_page=page_id ORDER BY ' . $sRevisionTable . '.rev_timestamp DESC LIMIT 1)';
		}

		if ($bAddAuthor && $sSqlRevisionTable == '') {
			$sSqlRevisionTable = $sRevisionTable . ' AS rev, ';
			$sSqlCond_page_rev .= ' AND ' . $sPageTable . '.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MIN(rev_aux_min.rev_timestamp) FROM ' . $sRevisionTable . ' AS rev_aux_min WHERE rev_aux_min.rev_page=rev.rev_page )';
		}
		if ($bAddLastEditor && $sSqlRevisionTable == '') {
			$sSqlRevisionTable = $sRevisionTable . ' AS rev, ';
			$sSqlCond_page_rev .= ' AND ' . $sPageTable . '.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MAX(rev_aux_max.rev_timestamp) FROM ' . $sRevisionTable . ' AS rev_aux_max WHERE rev_aux_max.rev_page=rev.rev_page )';
		}

		if ($sLastRevisionBefore . $sAllRevisionsBefore . $sFirstRevisionSince . $sAllRevisionsSince != '') {
			$sSqlRevisionTable = $sRevisionTable . ' AS rev, ';
			$sSqlRev_timestamp = ', rev_timestamp';
			$sSqlRev_id        = ', rev_id';
			if ($sLastRevisionBefore != '') {
				$sSqlCond_page_rev .= ' AND ' . $sPageTable . '.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MAX(rev_aux_bef.rev_timestamp) FROM ' . $sRevisionTable . ' AS rev_aux_bef WHERE rev_aux_bef.rev_page=rev.rev_page AND rev_aux_bef.rev_timestamp < ' . $sLastRevisionBefore . ')';
			}
			if ($sAllRevisionsBefore != '') {
				$sSqlCond_page_rev .= ' AND ' . $sPageTable . '.page_id=rev.rev_page AND rev.rev_timestamp < ' . $sAllRevisionsBefore;
			}
			if ($sFirstRevisionSince != '') {
				$sSqlCond_page_rev .= ' AND ' . $sPageTable . '.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MIN(rev_aux_snc.rev_timestamp) FROM ' . $sRevisionTable . ' AS rev_aux_snc WHERE rev_aux_snc.rev_page=rev.rev_page AND rev_aux_snc.rev_timestamp >= ' . $sFirstRevisionSince . ')';
			}
			if ($sAllRevisionsSince != '') {
				$sSqlCond_page_rev .= ' AND ' . $sPageTable . '.page_id=rev.rev_page AND rev.rev_timestamp >= ' . $sAllRevisionsSince;
			}
		}

		if (isset($aCatMinMax[0]) && $aCatMinMax[0] != '') {
			$sSqlCond_MaxCat .= ' AND ' . $aCatMinMax[0] . ' <= (SELECT count(*) FROM ' . $sCategorylinksTable . ' WHERE ' . $sCategorylinksTable . '.cl_from=page_id)';
		}
		if (isset($aCatMinMax[1]) && $aCatMinMax[1] != '') {
			$sSqlCond_MaxCat .= ' AND ' . $aCatMinMax[1] . ' >= (SELECT count(*) FROM ' . $sCategorylinksTable . ' WHERE ' . $sCategorylinksTable . '.cl_from=page_id)';
		}

		if ($bAddFirstCategoryDate) {
			//format cl_timestamp field (type timestamp) to string in same format AS rev_timestamp field
			//to make it compatible with $wgLang->date() function used in function DPLOutputListStyle() to show "firstcategorydate"
			$sSqlCl_timestamp = ", DATE_FORMAT(cl0.cl_timestamp, '%Y%m%d%H%i%s') AS cl_timestamp";
		}
		if ($bAddPageCounter) {
			$sSqlPage_counter = ", $sPageTable.page_counter AS page_counter";
		}
		if ($bAddPageSize) {
			$sSqlPage_size = ", $sPageTable.page_len AS page_len";
		}
		if ($bAddPageTouchedDate && $sSqlPage_touched == '') {
			$sSqlPage_touched = ", $sPageTable.page_touched AS page_touched";
		}
		if ($bAddUser || $bAddAuthor || $bAddLastEditor || $sSqlRevisionTable != '') {
			$sSqlRev_user = ', rev_user, rev_user_text, rev_comment';
		}
		if ($bAddCategories) {
			$sSqlCats            = ", GROUP_CONCAT(DISTINCT cl_gc.cl_to ORDER BY cl_gc.cl_to ASC SEPARATOR ' | ') AS cats";
			// Gives list of all categories linked from each article, if any.
			$sSqlClTableForGC    = $sCategorylinksTable . ' AS cl_gc';
			// Categorylinks table used by the Group Concat (GC) function above
			$sSqlCond_page_cl_gc = 'page_id=cl_gc.cl_from';
			if ($sSqlGroupBy != '') {
				$sSqlGroupBy .= ', ';
			}
			$sSqlGroupBy .= $sSqlCl_to . $sPageTable . '.page_id';
		}

		// SELECT ... FROM
		if ($acceptOpenReferences) {
			// SELECT ... FROM
			if (count($aImageContainer) > 0) {
				$sSqlSelectFrom = "SELECT $sSqlCalcFoundRows $sSqlDistinct " . $sSqlCl_to . 'ic.il_to, ' . $sSqlSelPage . "ic.il_to AS sortkey" . ' FROM ' . $sImageLinksTable . ' AS ic';
			} else {
				$sSqlSelectFrom = "SELECT $sSqlCalcFoundRows $sSqlDistinct " . $sSqlCl_to . 'pl_namespace, pl_title' . $sSqlSelPage . $sSqlSortkey . ' FROM ' . $sPageLinksTable;
			}
		} else {
			$sSqlSelectFrom = "SELECT $sSqlCalcFoundRows $sSqlDistinct " . $sSqlCl_to . $sPageTable . '.page_namespace AS page_namespace,' . $sPageTable . '.page_title AS page_title,' . $sPageTable . '.page_id AS page_id' . $sSqlSelPage . $sSqlSortkey . $sSqlPage_counter . $sSqlPage_size . $sSqlPage_touched . $sSqlRev_user . $sSqlRev_timestamp . $sSqlRev_id . $sSqlCats . $sSqlCl_timestamp . ' FROM ' . $sSqlRevisionTable . $sSqlCreationRevisionTable . $sSqlNoCreationRevisionTable . $sSqlChangeRevisionTable . $sSqlRCTable . $sSqlPageLinksTable . $sSqlExternalLinksTable . $sPageTable;
		}

		// JOIN ...
		if ($sSqlClHeadTable != '' || $sSqlClTableForGC != '') {
			$b2tables = ($sSqlClHeadTable != '') && ($sSqlClTableForGC != '');
			$sSqlSelectFrom .= ' LEFT OUTER JOIN ' . $sSqlClHeadTable . ($b2tables ? ', ' : '') . $sSqlClTableForGC . ' ON (' . $sSqlCond_page_cl_head . ($b2tables ? ' AND ' : '') . $sSqlCond_page_cl_gc . ')';
		}

		// Include categories...
		$iClTable = 0;
		for ($i = 0; $i < $iIncludeCatCount; $i++) {
			// If we want the Uncategorized
			$sSqlSelectFrom .= ' INNER JOIN ' . (in_array('', $aIncludeCategories[$i]) ? $sDplClView : $sCategorylinksTable) . ' AS cl' . $iClTable . ' ON ' . $sPageTable . '.page_id=cl' . $iClTable . '.cl_from AND (cl' . $iClTable . '.cl_to' . $sCategoryComparisonMode . self::$DB->addQuotes(str_replace(' ', '_', $aIncludeCategories[$i][0]));
			for ($j = 1; $j < count($aIncludeCategories[$i]); $j++)
				$sSqlSelectFrom .= ' OR cl' . $iClTable . '.cl_to' . $sCategoryComparisonMode . self::$DB->addQuotes(str_replace(' ', '_', $aIncludeCategories[$i][$j]));
			$sSqlSelectFrom .= ') ';
			$iClTable++;
		}

		// Exclude categories...
		for ($i = 0; $i < $iExcludeCatCount; $i++) {
			$sSqlSelectFrom .= ' LEFT OUTER JOIN ' . $sCategorylinksTable . ' AS cl' . $iClTable . ' ON ' . $sPageTable . '.page_id=cl' . $iClTable . '.cl_from' . ' AND cl' . $iClTable . '.cl_to' . $sNotCategoryComparisonMode . self::$DB->addQuotes(str_replace(' ', '_', $aExcludeCategories[$i]));
			$sSqlWhere .= ' AND cl' . $iClTable . '.cl_to IS NULL';
			$iClTable++;
		}

		// WHERE... (actually finish the WHERE clause we may have started if we excluded categories - see above)
		// Namespace IS ...
		if (!empty($aNamespaces)) {
			if ($acceptOpenReferences) {
				$sSqlWhere .= ' AND ' . $sPageLinksTable . '.pl_namespace IN (' . self::$DB->makeList($aNamespaces) . ')';
			} else {
				$sSqlWhere .= ' AND ' . $sPageTable . '.page_namespace IN (' . self::$DB->makeList($aNamespaces) . ')';
			}
		}
		// Namespace IS NOT ...
		if (!empty($aExcludeNamespaces)) {
			if ($acceptOpenReferences) {
				$sSqlWhere .= ' AND ' . $sPageLinksTable . '.pl_namespace NOT IN (' . self::$DB->makeList($aExcludeNamespaces) . ')';
			} else {
				$sSqlWhere .= ' AND ' . $sPageTable . '.page_namespace NOT IN (' . self::$DB->makeList($aExcludeNamespaces) . ')';
			}
		}

		// TitleIs
		if ($sTitleIs != '') {
			if ($bIgnoreCase) {
				$sSqlWhere .= ' AND LOWER(CAST(' . $sPageTable . '.page_title AS char)) = LOWER(' . self::$DB->addQuotes($sTitleIs) . ')';
			} else {
				$sSqlWhere .= ' AND ' . $sPageTable . '.page_title = ' . self::$DB->addQuotes($sTitleIs);
			}
		}

		// TitleGE ...
		if ($sTitleGE != '') {
			$sSqlWhere .= ' AND (';
			if (substr($sTitleGE, 0, 2) == '=_') {
				if ($acceptOpenReferences) {
					$sSqlWhere .= 'pl_title >=' . self::$DB->addQuotes(substr($sTitleGE, 2));
				} else {
					$sSqlWhere .= $sPageTable . '.page_title >=' . self::$DB->addQuotes(substr($sTitleGE, 2));
				}
			} else {
				if ($acceptOpenReferences) {
					$sSqlWhere .= 'pl_title >' . self::$DB->addQuotes($sTitleGE);
				} else {
					$sSqlWhere .= $sPageTable . '.page_title >' . self::$DB->addQuotes($sTitleGE);
				}
			}
			$sSqlWhere .= ')';
		}

		// TitleLE ...
		if ($sTitleLE != '') {
			$sSqlWhere .= ' AND (';
			if (substr($sTitleLE, 0, 2) == '=_') {
				if ($acceptOpenReferences) {
					$sSqlWhere .= 'pl_title <=' . self::$DB->addQuotes(substr($sTitleLE, 2));
				} else {
					$sSqlWhere .= $sPageTable . '.page_title <=' . self::$DB->addQuotes(substr($sTitleLE, 2));
				}
			} else {
				if ($acceptOpenReferences) {
					$sSqlWhere .= 'pl_title <' . self::$DB->addQuotes($sTitleLE);
				} else {
					$sSqlWhere .= $sPageTable . '.page_title <' . self::$DB->addQuotes($sTitleLE);
				}
			}
			$sSqlWhere .= ')';
		}

		// TitleMatch ...
		if (count($aTitleMatch) > 0) {
			$sSqlWhere .= ' AND (';
			$n = 0;
			foreach ($aTitleMatch as $link) {
				if ($n > 0) {
					$sSqlWhere .= ' OR ';
				}
				if ($acceptOpenReferences) {
					if ($bIgnoreCase) {
						$sSqlWhere .= 'LOWER(CAST(pl_title AS char))' . $sTitleMatchMode . strtolower(self::$DB->addQuotes($link));
					} else {
						$sSqlWhere .= 'pl_title' . $sTitleMatchMode . self::$DB->addQuotes($link);
					}
				} else {
					if ($bIgnoreCase) {
						$sSqlWhere .= 'LOWER(CAST(' . $sPageTable . '.page_title AS char))' . $sTitleMatchMode . strtolower(self::$DB->addQuotes($link));
					} else {
						$sSqlWhere .= $sPageTable . '.page_title' . $sTitleMatchMode . self::$DB->addQuotes($link);
					}
				}
				$n++;
			}
			$sSqlWhere .= ')';
		}

		// NotTitleMatch ...
		if (count($aNotTitleMatch) > 0) {
			$sSqlWhere .= ' AND NOT (';
			$n = 0;
			foreach ($aNotTitleMatch as $link) {
				if ($n > 0) {
					$sSqlWhere .= ' OR ';
				}
				if ($acceptOpenReferences) {
					if ($bIgnoreCase) {
						$sSqlWhere .= 'LOWER(CAST(pl_title AS char))' . $sNotTitleMatchMode . 'LOWER(' . self::$DB->addQuotes($link) . ')';
					} else {
						$sSqlWhere .= 'pl_title' . $sNotTitleMatchMode . self::$DB->addQuotes($link);
					}
				} else {
					if ($bIgnoreCase) {
						$sSqlWhere .= 'LOWER(CAST(' . $sPageTable . '.page_title AS char))' . $sNotTitleMatchMode . 'LOWER(' . self::$DB->addQuotes($link) . ')';
					} else {
						$sSqlWhere .= $sPageTable . '.page_title' . $sNotTitleMatchMode . self::$DB->addQuotes($link);
					}
				}
				$n++;
			}
			$sSqlWhere .= ')';
		}

		// rev_minor_edit IS
		if (isset($sMinorEdits) && $sMinorEdits == 'exclude') {
			$sSqlWhere .= ' AND rev_minor_edit=0';
		}
		// page_is_redirect IS ...
		if (!$acceptOpenReferences) {
			switch ($sRedirects) {
				case 'only':
					$sSqlWhere .= ' AND ' . $sPageTable . '.page_is_redirect=1';
					break;
				case 'exclude':
					$sSqlWhere .= ' AND ' . $sPageTable . '.page_is_redirect=0';
					break;
			}
		}

		// page_id=rev_page (if revision table required)
		$sSqlWhere .= $sSqlCond_page_rev;

		if ($iMinRevisions != null) {
			$sSqlWhere .= " and ((SELECT count(rev_aux2.rev_page) FROM revision AS rev_aux2 WHERE rev_aux2.rev_page=page.page_id) >= $iMinRevisions)";
		}
		if ($iMaxRevisions != null) {
			$sSqlWhere .= " and ((SELECT count(rev_aux3.rev_page) FROM revision AS rev_aux3 WHERE rev_aux3.rev_page=page.page_id) <= $iMaxRevisions)";
		}

		// count(all categories) <= max no of categories
		$sSqlWhere .= $sSqlCond_MaxCat;

		// check against forbidden namespaces
		if (is_array($wgNonincludableNamespaces) && array_count_values($wgNonincludableNamespaces) > 0 && implode(',', $wgNonincludableNamespaces) != '') {
			$sSqlWhere .= ' AND ' . $sPageTable . '.page_namespace NOT IN (' . implode(',', $wgNonincludableNamespaces) . ')';
		}

		// page_id=pl.pl_from (if pagelinks table required)
		$sSqlWhere .= $sSqlCond_page_pl;

		// page_id=el.el_from (if external links table required)
		$sSqlWhere .= $sSqlCond_page_el;

		// page_id=tpl.tl_from (if templatelinks table required)
		$sSqlWhere .= $sSqlCond_page_tpl;

		if (isset($sArticleCategory) && $sArticleCategory !== null) {
			$sSqlWhere .= " AND $sPageTable.page_title IN (
				SELECT p2.page_title
				FROM $sPageTable p2
				INNER JOIN $sCategorylinksTable clstc ON (clstc.cl_from = p2.page_id AND clstc.cl_to = " . self::$DB->addQuotes($sArticleCategory) . " )
				WHERE p2.page_namespace = 0
				) ";
		}

		if (function_exists('efLoadFlaggedRevs')) {
			$filterSet = array(
				'only',
				'exclude'
			);
			# Either involves the same JOIN here...
			if (in_array($sStable, $filterSet) || in_array($sQuality, $filterSet)) {
				$flaggedpages = self::$DB->tableName('flaggedpages');
				$sSqlSelectFrom .= " LEFT JOIN $flaggedpages ON page_id = fp_page_id";
			}
			switch ($sStable) {
				case 'only':
					$sSqlWhere .= ' AND fp_stable IS NOT NULL ';
					break;
				case 'exclude':
					$sSqlWhere .= ' AND fp_stable IS NULL ';
					break;
			}
			switch ($sQuality) {
				case 'only':
					$sSqlWhere .= ' AND fp_quality >= 1';
					break;
				case 'exclude':
					$sSqlWhere .= ' AND fp_quality = 0';
					break;
			}
		}

		// GROUP BY ...
		if ($sSqlGroupBy != '') {
			$sSqlWhere .= ' GROUP BY ' . $sSqlGroupBy . ' ';
		}

		// ORDER BY ...
		if ($aOrderMethods[0] != '' && $aOrderMethods[0] != 'none') {
			$sSqlWhere .= ' ORDER BY ';
			foreach ($aOrderMethods as $i => $sOrderMethod) {

				if ($i > 0) {
					$sSqlWhere .= ', ';
				}

				switch ($sOrderMethod) {
					case 'category':
						$sSqlWhere .= 'cl_head.cl_to';
						break;
					case 'categoryadd':
						$sSqlWhere .= 'cl0.cl_timestamp';
						break;
					case 'counter':
						$sSqlWhere .= 'page_counter';
						break;
					case 'size':
						$sSqlWhere .= 'page_len';
						break;
					case 'firstedit':
						$sSqlWhere .= 'rev_timestamp';
						break;
					case 'lastedit':
						// extension:intersection used to sort by page_touched although the field is called 'lastedit'
						if (\DynamicPageListHooks::isLikeIntersection()) {
							$sSqlWhere .= 'page_touched';
						} else {
							$sSqlWhere .= 'rev_timestamp';
						}
						break;
					case 'pagetouched':
						$sSqlWhere .= 'page_touched';
						break;
					case 'sortkey':
					case 'title':
					case 'pagesel':
						$sSqlWhere .= 'sortkey';
						break;
					case 'titlewithoutnamespace':
						if ($acceptOpenReferences) {
							$sSqlWhere .= "pl_title";
						} else {
							$sSqlWhere .= "page_title";
						}
						break;
					case 'user':
						// rev_user_text can discriminate anonymous users (e.g. based on IP), rev_user cannot (=' 0' for all)
						$sSqlWhere .= 'rev_user_text';
						break;
					default:
				}
			}
			if ($sOrder == 'descending') {
				$sSqlWhere .= ' DESC';
			} else {
				$sSqlWhere .= ' ASC';
			}
		}

		if ($sAllRevisionsSince != '' || $sAllRevisionsBefore != '') {
			if ($aOrderMethods[0] == '' || $aOrderMethods[0] == 'none') {
				$sSqlWhere .= ' ORDER BY ';
			} else {
				$sSqlWhere .= ', ';
			}
			$sSqlWhere .= 'rev_id DESC';
		}

		// LIMIT ....
		// we must switch off LIMITS when going for categories as output goal (due to mysql limitations)
		if ((!\DynamicPageListHooks::$allowUnlimitedResults || $iCount >= 0) && $sGoal != 'categories') {
			$sSqlWhere .= " LIMIT $iOffset, ";
			if ($iCount < 0) {
				$iCount = intval(Options::$options['count']['default']);
			}
			$sSqlWhere .= $iCount;
		}

		// when we go for a list of categories as result we transform the output of the normal query into a subquery
		// of a selection on the categorylinks

		if ($sGoal == 'categories') {
			$sSqlSelectFrom = 'SELECT DISTINCT cl3.cl_to FROM ' . $sCategorylinksTable . ' AS cl3 WHERE cl3.cl_from IN ( ' . preg_replace('/SELECT +DISTINCT +.* FROM /', 'SELECT DISTINCT ' . $sPageTable . '.page_id FROM ', $sSqlSelectFrom);
			if ($sOrder == 'descending') {
				$sSqlWhere .= ' ) ORDER BY cl3.cl_to DESC';
			} else {
				$sSqlWhere .= ' ) ORDER BY cl3.cl_to ASC';
			}
		}


		// ###### DUMP SQL QUERY ######
		if ($logger->iDebugLevel >= 3) {
			//DEBUG: output SQL query
			$output .= "DPL debug -- Query=<br />\n<tt>" . $sSqlSelectFrom . $sSqlWhere . "</tt>\n\n";
		}

		// Do NOT proces the SQL command if debug==6; this is useful if the SQL statement contains bad code
		if ($logger->iDebugLevel == 6) {
			return $output;
		}


		// ###### PROCESS SQL QUERY ######
		$queryError = false;
		try {
			$res = self::$DB->query($sSqlSelectFrom . $sSqlWhere);
		}
		catch (Exception $e) {
			$queryError = true;
		}
		if ($queryError == true || $res === false) {
			$result = "The DPL extension (version " . DPL_VERSION . ") produced a SQL statement which lead to a Database error.<br/>\n
The reason may be an internal error of DPL or an error which you made, especially when using DPL options like 'categoryregexp' or 'titleregexp'.  Usage of non-greedy *? matching patterns are not supported.<br/>\n
Error message was:<br />\n<tt>" . self::$DB->lastError() . "</tt>\n\n";
			return $result;
		}

		if (self::$DB->numRows($res) <= 0) {
			$header = str_replace('%TOTALPAGES%', '0', str_replace('%PAGES%', '0', $sNoResultsHeader));
			if ($sNoResultsHeader != '') {
				$output .= str_replace('\n', "\n", str_replace("¶", "\n", $header));
			}
			$footer = str_replace('%TOTALPAGES%', '0', str_replace('%PAGES%', '0', $sNoResultsFooter));
			if ($sNoResultsFooter != '') {
				$output .= str_replace('\n', "\n", str_replace("¶", "\n", $footer));
			}
			if ($sNoResultsHeader == '' && $sNoResultsFooter == '') {
				$output .= $logger->escapeMsg(\DynamicPageListHooks::WARN_NORESULTS);
			}
			self::$DB->freeResult($res);
			return $output;
		}

		// generate title for Special:Contributions (used if adduser=true)
		$sSpecContribs = '[[:Special:Contributions|Contributions]]';

		$aHeadings = array(); // maps heading to count (# of pages under each heading)
		$aArticles = array();

		if (isset($iRandomCount) && $iRandomCount > 0) {
			$nResults = self::$DB->numRows($res);
			//mt_srand() seeding was removed due to PHP 5.2.1 and above no longer generating the same sequence for the same seed.
			if ($iRandomCount > $nResults) {
				$iRandomCount = $nResults;
			}

			//This is 50% to 150% faster than the old while (true) version that could keep rechecking the same random key over and over again.
			$pick = range(1, $nResults);
			shuffle($pick);
			$pick = array_slice($pick, 0, $iRandomCount);
		}

		$iArticle            = 0;
		$firstNamespaceFound = '';
		$firstTitleFound     = '';
		$lastNamespaceFound  = '';
		$lastTitleFound      = '';

		foreach ($res as $row) {
			$iArticle++;

			// in random mode skip articles which were not chosen
			if (isset($iRandomCount) && $iRandomCount > 0 && !in_array($iArticle, $pick)) {
				continue;
			}

			if ($sGoal == 'categories') {
				$pageNamespace = 14; // CATEGORY
				$pageTitle     = $row->cl_to;
			} else if ($acceptOpenReferences) {
				if (count($aImageContainer) > 0) {
					$pageNamespace = 6;
					$pageTitle     = $row->il_to;
				} else {
					// maybe non-existing title
					$pageNamespace = $row->pl_namespace;
					$pageTitle     = $row->pl_title;
				}
			} else {
				// existing PAGE TITLE
				$pageNamespace = $row->page_namespace;
				$pageTitle     = $row->page_title;
			}

			// if subpages are to be excluded: skip them
			if (!$bIncludeSubpages && (!(strpos($pageTitle, '/') === false))) {
				continue;
			}

			$title     = \Title::makeTitle($pageNamespace, $pageTitle);
			$thisTitle = $parser->getTitle();

			// block recursion: avoid to show the page which contains the DPL statement as part of the result
			if ($bSkipThisPage && $thisTitle->equals($title)) {
				// $output.= 'BLOCKED '.$thisTitle->getText().' DUE TO RECURSION'."\n";
				continue;
			}

			$dplArticle = new Article($title, $pageNamespace);
			//PAGE LINK
			$sTitleText = $title->getText();
			if ($bShowNamespace) {
				$sTitleText = $title->getPrefixedText();
			}
			if ($aReplaceInTitle[0] != '') {
				$sTitleText = preg_replace($aReplaceInTitle[0], $aReplaceInTitle[1], $sTitleText);
			}

			//chop off title if "too long"
			if (isset($iTitleMaxLen) && (strlen($sTitleText) > $iTitleMaxLen)) {
				$sTitleText = substr($sTitleText, 0, $iTitleMaxLen) . '...';
			}
			if ($bShowCurID && isset($row->page_id)) {
				$articleLink = '[{{fullurl:' . $title->getText() . '|curid=' . $row->page_id . '}} ' . htmlspecialchars($sTitleText) . ']';
			} else if (!$bEscapeLinks || ($pageNamespace != 14 && $pageNamespace != 6)) {
				// links to categories or images need an additional ":"
				$articleLink = '[[' . $title->getPrefixedText() . '|' . $wgContLang->convert($sTitleText) . ']]';
			} else {
				$articleLink = '[{{fullurl:' . $title->getText() . '}} ' . htmlspecialchars($sTitleText) . ']';
			}

			$dplArticle->mLink = $articleLink;

			//get first char used for category-style output
			if (isset($row->sortkey)) {
				$dplArticle->mStartChar = $wgContLang->convert($wgContLang->firstChar($row->sortkey));
			}
			if (isset($row->sortkey)) {
				$dplArticle->mStartChar = $wgContLang->convert($wgContLang->firstChar($row->sortkey));
			} else {
				$dplArticle->mStartChar = $wgContLang->convert($wgContLang->firstChar($pageTitle));
			}

			// page_id
			if (isset($row->page_id)) {
				$dplArticle->mID = $row->page_id;
			} else {
				$dplArticle->mID = 0;
			}

			// external link
			if (isset($row->el_to)) {
				$dplArticle->mExternalLink = $row->el_to;
			}

			//SHOW PAGE_COUNTER
			if (isset($row->page_counter)) {
				$dplArticle->mCounter = $row->page_counter;
			}

			//SHOW PAGE_SIZE
			if (isset($row->page_len)) {
				$dplArticle->mSize = $row->page_len;
			}
			//STORE initially selected PAGE
			if (count($aLinksTo) > 0 || count($aLinksFrom) > 0) {
				if (!isset($row->sel_title)) {
					$dplArticle->mSelTitle     = 'unknown page';
					$dplArticle->mSelNamespace = 0;
				} else {
					$dplArticle->mSelTitle     = $row->sel_title;
					$dplArticle->mSelNamespace = $row->sel_ns;
				}
			}

			//STORE selected image
			if (count($aImageUsed) > 0) {
				if (!isset($row->image_sel_title)) {
					$dplArticle->mImageSelTitle = 'unknown image';
				} else {
					$dplArticle->mImageSelTitle = $row->image_sel_title;
				}
			}

			if ($bGoalIsPages) {
				//REVISION SPECIFIED
				if ($sLastRevisionBefore . $sAllRevisionsBefore . $sFirstRevisionSince . $sAllRevisionsSince != '') {
					$dplArticle->mRevision = $row->rev_id;
					$dplArticle->mUser     = $row->rev_user_text;
					$dplArticle->mDate     = $row->rev_timestamp;
				}

				//SHOW "PAGE_TOUCHED" DATE, "FIRSTCATEGORYDATE" OR (FIRST/LAST) EDIT DATE
				if ($bAddPageTouchedDate) {
					$dplArticle->mDate = $row->page_touched;
				} elseif ($bAddFirstCategoryDate) {
					$dplArticle->mDate = $row->cl_timestamp;
				} elseif ($bAddEditDate && isset($row->rev_timestamp)) {
					$dplArticle->mDate = $row->rev_timestamp;
				} elseif ($bAddEditDate && isset($row->page_touched)) {
					$dplArticle->mDate = $row->page_touched;
				}

				// time zone adjustment
				if ($dplArticle->mDate != '') {
					$dplArticle->mDate = $wgLang->userAdjust($dplArticle->mDate);
				}

				if ($dplArticle->mDate != '' && $sUserDateFormat != '') {
					// we apply the userdateformat
					$dplArticle->myDate = gmdate($sUserDateFormat, wfTimeStamp(TS_UNIX, $dplArticle->mDate));
				}
				// CONTRIBUTION, CONTRIBUTOR
				if ($bAddContribution) {
					$dplArticle->mContribution = $row->contribution;
					$dplArticle->mContributor  = $row->contributor;
					$dplArticle->mContrib      = substr('*****************', 0, round(log($row->contribution)));
				}


				//USER/AUTHOR(S)
				// because we are going to do a recursive parse at the end of the output phase
				// we have to generate wiki syntax for linking to a user´s homepage
				if ($bAddUser || $bAddAuthor || $bAddLastEditor || $sLastRevisionBefore . $sAllRevisionsBefore . $sFirstRevisionSince . $sAllRevisionsSince != '') {
					$dplArticle->mUserLink = '[[User:' . $row->rev_user_text . '|' . $row->rev_user_text . ']]';
					$dplArticle->mUser     = $row->rev_user_text;
					$dplArticle->mComment  = $row->rev_comment;
				}

				//CATEGORY LINKS FROM CURRENT PAGE
				if ($bAddCategories && $bGoalIsPages && ($row->cats != '')) {
					$artCatNames = explode(' | ', $row->cats);
					foreach ($artCatNames as $artCatName) {
						$dplArticle->mCategoryLinks[] = '[[:Category:' . $artCatName . '|' . str_replace('_', ' ', $artCatName) . ']]';
						$dplArticle->mCategoryTexts[] = str_replace('_', ' ', $artCatName);
					}
				}
				// PARENT HEADING (category of the page, editor (user) of the page, etc. Depends on ordermethod param)
				if ($sHListMode != 'none') {
					switch ($aOrderMethods[0]) {
						case 'category':
							//count one more page in this heading
							$aHeadings[$row->cl_to] = isset($aHeadings[$row->cl_to]) ? $aHeadings[$row->cl_to] + 1 : 1;
							if ($row->cl_to == '') {
								//uncategorized page (used if ordermethod=category,...)
								$dplArticle->mParentHLink = '[[:Special:Uncategorizedpages|' . wfMsg('uncategorizedpages') . ']]';
							} else {
								$dplArticle->mParentHLink = '[[:Category:' . $row->cl_to . '|' . str_replace('_', ' ', $row->cl_to) . ']]';
							}
							break;
						case 'user':
							$aHeadings[$row->rev_user_text] = isset($aHeadings[$row->rev_user_text]) ? $aHeadings[$row->rev_user_text] + 1 : 1;
							if ($row->rev_user == 0) { //anonymous user
								$dplArticle->mParentHLink = '[[User:' . $row->rev_user_text . '|' . $row->rev_user_text . ']]';

							} else {
								$dplArticle->mParentHLink = '[[User:' . $row->rev_user_text . '|' . $row->rev_user_text . ']]';
							}
							break;
					}
				}
			}

			$aArticles[] = $dplArticle;
		}
		self::$DB->freeResult($res);
		$rowcount = -1;
		if ($sSqlCalcFoundRows != '') {
			$res      = self::$DB->query('SELECT FOUND_ROWS() AS rowcount');
			$row      = self::$DB->fetchObject($res);
			$rowcount = $row->rowcount;
			self::$DB->freeResult($res);
		}

		// backward scrolling: if the user specified titleLE we reverse the output order
		if ($sTitleLE != '' && $sTitleGE == '' && $sOrder == 'descending') {
			$aArticles = array_reverse($aArticles);
		}

		// special sort for card suits (Bridge)
		if ($bOrderSuitSymbols) {
			self::cardSuitSort($aArticles);
		}


		// ###### SHOW OUTPUT ######

		$listMode = new ListMode($sPageListMode, $aSecSeparators, $aMultiSecSeparators, $sInlTxt, $sListHtmlAttr, $sItemHtmlAttr, $aListSeparators, $iOffset, $iDominantSection);

		$hListMode = new ListMode($sHListMode, $aSecSeparators, $aMultiSecSeparators, '', $sHListHtmlAttr, $sHItemHtmlAttr, $aListSeparators, $iOffset, $iDominantSection);

		$dpl = new DynamicPageList($aHeadings, $bHeadingCount, $iColumns, $iRows, $iRowSize, $sRowColFormat, $aArticles, $aOrderMethods[0], $hListMode, $listMode, $bEscapeLinks, $bAddExternalLink, $bIncPage, $iIncludeMaxLen, $aSecLabels, $aSecLabelsMatch, $aSecLabelsNotMatch, $bIncParsed, $parser, $logger, $aReplaceInTitle, $iTitleMaxLen, $defaultTemplateSuffix, $aTableRow, $bIncludeTrim, $iTableSortCol, $sUpdateRules, $sDeleteRules);

		if ($rowcount == -1) {
			$rowcount = $dpl->getRowCount();
		}
		$dplResult = $dpl->getText();
		$header    = '';
		if ($sOneResultHeader != '' && $rowcount == 1) {
			$header = str_replace('%TOTALPAGES%', $rowcount, str_replace('%PAGES%', 1, $sOneResultHeader));
		} else if ($rowcount == 0) {
			$header = str_replace('%TOTALPAGES%', $rowcount, str_replace('%PAGES%', $dpl->getRowCount(), $sNoResultsHeader));
			if ($sNoResultsHeader != '') {
				$output .= str_replace('\n', "\n", str_replace("¶", "\n", $header));
			}
			$footer = str_replace('%TOTALPAGES%', $rowcount, str_replace('%PAGES%', $dpl->getRowCount(), $sNoResultsFooter));
			if ($sNoResultsFooter != '') {
				$output .= str_replace('\n', "\n", str_replace("¶", "\n", $footer));
			}
			if ($sNoResultsHeader == '' && $sNoResultsFooter == '') {
				$output .= $logger->escapeMsg(\DynamicPageListHooks::WARN_NORESULTS);
			}
		} else {
			if ($sResultsHeader != '') {
				$header = str_replace('%TOTALPAGES%', $rowcount, str_replace('%PAGES%', $dpl->getRowCount(), $sResultsHeader));
			}
		}
		$header = str_replace('\n', "\n", str_replace("¶", "\n", $header));
		$header = str_replace('%VERSION%', DPL_VERSION, $header);
		$footer = '';
		if ($sOneResultFooter != '' && $rowcount == 1) {
			$footer = str_replace('%PAGES%', 1, $sOneResultFooter);
		} else {
			if ($sResultsFooter != '') {
				$footer = str_replace('%TOTALPAGES%', $rowcount, str_replace('%PAGES%', $dpl->getRowCount(), $sResultsFooter));
			}
		}
		$footer = str_replace('\n', "\n", str_replace("¶", "\n", $footer));
		$footer = str_replace('%VERSION%', DPL_VERSION, $footer);

		// replace %DPLTIME% by execution time and timestamp in header and footer
		$nowTimeStamp   = self::prettyTimeStamp(date('YmdHis'));
		$dplElapsedTime = sprintf('%.3f sec.', microtime(true) - $dplStartTime);
		$header         = str_replace('%DPLTIME%', "$dplElapsedTime ($nowTimeStamp)", $header);
		$footer         = str_replace('%DPLTIME%', "$dplElapsedTime ($nowTimeStamp)", $footer);

		// replace %LASTTITLE% / %LASTNAMESPACE% by the last title found in header and footer
		if (($n = count($aArticles)) > 0) {
			$firstNamespaceFound = str_replace(' ', '_', $aArticles[0]->mTitle->getNamespace());
			$firstTitleFound     = str_replace(' ', '_', $aArticles[0]->mTitle->getText());
			$lastNamespaceFound  = str_replace(' ', '_', $aArticles[$n - 1]->mTitle->getNamespace());
			$lastTitleFound      = str_replace(' ', '_', $aArticles[$n - 1]->mTitle->getText());
		}
		$header = str_replace('%FIRSTNAMESPACE%', $firstNamespaceFound, $header);
		$footer = str_replace('%FIRSTNAMESPACE%', $firstNamespaceFound, $footer);
		$header = str_replace('%FIRSTTITLE%', $firstTitleFound, $header);
		$footer = str_replace('%FIRSTTITLE%', $firstTitleFound, $footer);
		$header = str_replace('%LASTNAMESPACE%', $lastNamespaceFound, $header);
		$footer = str_replace('%LASTNAMESPACE%', $lastNamespaceFound, $footer);
		$header = str_replace('%LASTTITLE%', $lastTitleFound, $header);
		$footer = str_replace('%LASTTITLE%', $lastTitleFound, $footer);
		$header = str_replace('%SCROLLDIR%', $scrollDir, $header);
		$footer = str_replace('%SCROLLDIR%', $scrollDir, $footer);

		$output .= $header . $dplResult . $footer;

		self::defineScrollVariables($firstNamespaceFound, $firstTitleFound, $lastNamespaceFound, $lastTitleFound, $scrollDir, $iCount, "$dplElapsedTime ($nowTimeStamp)", $rowcount, $dpl->getRowCount());

		// save generated wiki text to dplcache page if desired

		if ($DPLCache != '') {
			if (!is_writeable($cacheFile)) {
				wfMkdirParents(dirname($cacheFile));
			} else if (($bDPLRefresh || $wgRequest->getVal('action', 'view') == 'submit') && strpos($DPLCache, '/') > 0 && strpos($DPLCache, '..') === false) {
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
			if ($logger->iDebugLevel >= 2) {
				$output .= "{{Extension DPL cache|mode=update|page={{FULLPAGENAME}}|cache=$DPLCache|date=$cacheTimeStamp|age=0|now=" . date('H:i:s') . "|dpltime=$dplElapsedTime|offset=$iOffset}}";
			}
			$parser->disableCache();
		}

		// The following requires an extra parser step which may consume some time
		// we parse the DPL output and save all references found in that output in a global list
		// in a final user exit after the whole document processing we eliminate all these links
		// we use a local parser to avoid interference with the main parser

		if ($bReset[4] || $bReset[5] || $bReset[6] || $bReset[7]) {
			// register a hook to reset links which were produced during parsing DPL output
			global $wgHooks;
			if (!in_array('DynamicPageListHooks::endEliminate', $wgHooks['ParserAfterTidy'])) {
				$wgHooks['ParserAfterTidy'][] = 'DynamicPageListHooks::endEliminate';
			}
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

		return $output;
	}


	// auxiliary functions ===============================================================================

	// create keys for TableRow which represent the structure of the "include=" arguments
	private static function updateTableRowKeys(&$aTableRow, $aSecLabels) {
		$tableRow  = $aTableRow;
		$aTableRow = array();
		$groupNr   = -1;
		$t         = -1;
		foreach ($aSecLabels as $label) {
			$t++;
			$groupNr++;
			$cols = explode('}:', $label);
			if (count($cols) <= 1) {
				if (array_key_exists($t, $tableRow)) {
					$aTableRow[$groupNr] = $tableRow[$t];
				}
			} else {
				$n     = count(explode(':', $cols[1]));
				$colNr = -1;
				$t--;
				for ($i = 1; $i <= $n; $i++) {
					$colNr++;
					$t++;
					if (array_key_exists($t, $tableRow)) {
						$aTableRow[$groupNr . '.' . $colNr] = $tableRow[$t];
					}
				}
			}
		}
	}

	private static function getSubcategories($cat, $sPageTable, $depth) {
		if (self::$DB === null) {
			self::$DB = wfGetDB(DB_SLAVE);
		}
		$cats = $cat;
		$res  = self::$DB->query("SELECT DISTINCT page_title FROM " . self::$DB->tableName('page') . " INNER JOIN " . self::$DB->tableName('categorylinks') . " AS cl0 ON " . $sPageTable . ".page_id = cl0.cl_from AND cl0.cl_to='" . str_replace(' ', '_', $cat) . "'" . " WHERE page_namespace='14'");
		foreach ($res as $row) {
			if ($depth > 1) {
				$cats .= '|' . self::getSubcategories($row->page_title, $sPageTable, $depth - 1);
			} else {
				$cats .= '|' . $row->page_title;
			}
		}
		self::$DB->freeResult($res);
		return $cats;
	}

	private static function prettyTimeStamp($t) {
		return substr($t, 0, 4) . '/' . substr($t, 4, 2) . '/' . substr($t, 6, 2) . '  ' . substr($t, 8, 2) . ':' . substr($t, 10, 2) . ':' . substr($t, 12, 2);
	}

	private static function durationTime($t) {
		if ($t < 60) {
			return "00:00:" . str_pad($t, 2, "0", STR_PAD_LEFT);
		}
		if ($t < 3600) {
			return "00:" . str_pad(floor($t / 60), 2, "0", STR_PAD_LEFT) . ':' . str_pad(floor(fmod($t, 60)), 2, "0", STR_PAD_LEFT);
		}
		if ($t < 86400) {
			return str_pad(floor($t / 3600), 2, "0", STR_PAD_LEFT) . ':' . str_pad(floor(fmod(floor($t / 60), 60)), 2, "0", STR_PAD_LEFT) . ':' . str_pad(fmod($t, 60), 2, "0", STR_PAD_LEFT);
		}
		if ($t < 2 * 86400) {
			return "1 day";
		}
		return floor($t / 86400) . ' days';
	}

	private static function resolveUrlArg($input, $arg) {
		global $wgRequest;
		$dplArg = $wgRequest->getVal($arg, '');
		if ($dplArg == '') {
			$input = preg_replace('/\{%' . $arg . ':(.*)%\}/U', '\1', $input);
			return str_replace('{%' . $arg . '%}', '', $input);
		} else {
			$input = preg_replace('/\{%' . $arg . ':.*%\}/U  ', $dplArg, $input);
			return str_replace('{%' . $arg . '%}', $dplArg, $input);
		}
	}

	// this function uses the Variables extension to provide URL-arguments like &DPL_xyz=abc
	// in the form of a variable which can be accessed as {{#var:xyz}} if ExtensionVariables is installed
	private static function getUrlArgs() {
		global $wgRequest, $wgExtVariables;
		$args = $wgRequest->getValues();
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
		$args  = $wgRequest->getValues();
		$dummy = '';
		foreach ($args as $argName => $argValue) {
			$wgExtVariables->vardefine($dummy, $argName, $argValue);
		}
	}

	// this function uses the Variables extension to provide navigation aids like DPL_firstTitle, DPL_lastTitle, DPL_findTitle
	// these variables can be accessed as {{#var:DPL_firstTitle}} etc. if ExtensionVariables is installed
	private static function defineScrollVariables($firstNamespace, $firstTitle, $lastNamespace, $lastTitle, $scrollDir, $dplCount, $dplElapsedTime, $totalPages, $pages) {
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

	private static function cardSuitSort(&$articles) {
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
	}
}
?>