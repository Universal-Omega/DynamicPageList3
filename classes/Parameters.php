<?php
/**
 * DynamicPageList
 * DPL Variables Class
 *
 * @author		IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license		GPL
 * @package		DynamicPageList
 *
 **/
namespace DPL;

class Parameters {
	/**
	 * Parameter Richness
	 * The level of parameters that is accesible for the user.
	 *
	 * @var		integer
	 */
	static private $parameterRichness = 0;

	/**
	 * List of all the valid parameters that can be used per level of functional richness.
	 *
	 * @var		array
	 */
	static private $parametersForRichnessLevel = [
		0 => [
			'addfirstcategorydate',
			'category',
			'count',
			'hiddencategories',
			'mode',
			'namespace',
			'notcategory',
			'order',
			'ordermethod',
			'qualitypages',
			'redirects',
			'showcurid',
			'shownamespace',
			'stablepages',
			'suppresserrors'
		],
		1 => [
			'allowcachedresults',
			'execandexit',
			'columns',
			'debug',
			'distinct',
			'escapelinks',
			'format',
			'inlinetext',
			'listseparators',
			'notnamespace',
			'offset',
			'oneresultfooter',
			'oneresultheader',
			'ordercollation',
			'noresultsfooter',
			'noresultsheader',
			'randomcount',
			'replaceintitle',
			'resultsfooter',
			'resultsheader',
			'rowcolformat',
			'rows',
			'rowsize',
			'scroll',
			'title',
			'title<',
			'title>',
			'titlemaxlength',
			'userdateformat'
		],
		2 => [
			'addauthor',
			'addcategories',
			'addcontribution',
			'addeditdate',
			'addexternallink',
			'addlasteditor',
			'addpagecounter',
			'addpagesize',
			'addpagetoucheddate',
			'adduser',
			'categoriesminmax',
			'createdby',
			'dominantsection',
			'dplcache',
			'dplcacheperiod',
			'eliminate',
			'fixcategory',
			'headingcount',
			'headingmode',
			'hitemattr',
			'hlistattr',
			'ignorecase',
			'imagecontainer',
			'imageused',
			'include',
			'includematch',
			'includematchparsed',
			'includemaxlength',
			'includenotmatch',
			'includenotmatchparsed',
			'includepage',
			'includesubpages',
			'includetrim',
			'itemattr',
			'lastmodifiedby',
			'linksfrom',
			'linksto',
			'linkstoexternal',
			'listattr',
			'minoredits',
			'modifiedby',
			'multisecseparators',
			'notcreatedby',
			'notlastmodifiedby',
			'notlinksfrom',
			'notlinksto',
			'notmodifiedby',
			'notuses',
			'reset',
			'secseparators',
			'skipthispage',
			'table',
			'tablerow',
			'tablesortcol',
			'titlematch',
			'usedby',
			'uses'
		],
		3 => [
			'allrevisionsbefore',
			'allrevisionssince',
			'articlecategory',
			'categorymatch',
			'categoryregexp',
			'firstrevisionsince',
			'lastrevisionbefore',
			'maxrevisions',
			'minrevisions',
			'notcategorymatch',
			'notcategoryregexp',
			'nottitlematch',
			'nottitleregexp',
			'openreferences',
			'titleregexp'
		],
		4 => [
			'deleterules',
			'goal',
			'updaterules'
		]
	];

	/**
	 * Sets the current parameter richness.
	 *
	 * @access	public
	 * @param	integer	Integer level.
	 * @return	void
	 */
    static public function setRichness($level) {
		self::$parameterRichness = intval($level);
	}

	/**
	 * Returns the current parameter richness.
	 *
	 * @access	public
	 * @return	integer
	 */
	static public function getRichness() {
		return self::$parameterRichness;
	}

	/**
	 * Tests if the function is valid for the current functional richness level.
	 *
	 * @access	public
	 * @param	string	Function to test.
	 * @return	boolean	Valid for this functional richness level.
	 */
	static public function testRichness($function) {
		$valid = false;
		for ($i = 0; $i <= self::getRichness(); $i++) {
			if (in_array($function, self::$parametersForRichnessLevel[$i])) {
				$valid = true;
				break;
			}
		}
		return $valid;
	}

	/**
	 * Returns all parameters for the current richness level or limited to the optional maximum richness.
	 *
	 * @access	public
	 * @param	
	 * @return	array	The functional richness parameters list.
	 */
	static public function getParametersForRichness($level = null) {
		if ($level === null) {
			$level = self::getRichness();
		}

		$parameters = [];
		for ($i = 0; $i <= $level; $i++) {
			$parameters = array_merge($parameters, self::$parametersForRichnessLevel[0]);
		}
		return $parameters;
	}

	/**
	 * Clean and test '$1' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function categoryParameter($option) {
		// Init array of categories to include
		$aCategories = array();
		$bHeading    = false;
		$bNotHeading = false;
		if ($option != '' && $option[0] == '+') { // categories are headings
			$bHeading = true;
			$option[0]  = '';
		}
		if ($option != '' && $option[0] == '-') { // categories are NOT headings
			$bNotHeading = true;
			$option[0]     = '';
		}
		$op   = 'OR';
		// we expand html entities because they contain an '& 'which would be interpreted as an AND condition
		$option = html_entity_decode($option, ENT_QUOTES);
		if (strpos($option, '&') !== false) {
			$aParams = explode('&', $option);
			$op      = 'AND';
		} else {
			$aParams = explode('|', $option);
		}
		foreach ($aParams as $sParam) {
			$sParam = trim($sParam);
			if ($sParam == '_none_') {
				$aParams[$sParam] = '';
				$bIncludeUncat    = true;
				$aCategories[]    = '';
			} elseif ($sParam != '') {
				if ($sParam[0] == '*' && strlen($sParam) >= 2) {
					if ($sParam[1] == '*') {
						$sParamList = explode('|', self::getSubcategories(substr($sParam, 2), $sPageTable, 2));
					} else {
						$sParamList = explode('|', self::getSubcategories(substr($sParam, 1), $sPageTable, 1));
					}
					foreach ($sParamList as $sPar) {
						$title = \Title::newFromText($sPar);
						if (!is_null($title)) {
							$aCategories[] = $title->getDbKey();
						}
					}
				} else {
					$title = \Title::newFromText($sParam);
					if (!is_null($title)) {
						$aCategories[] = $title->getDbKey();
					}
				}
			}
		}
		if (!empty($aCategories)) {
			if ($op == 'OR') {
				$aIncludeCategories[] = $aCategories;
			} else {
				foreach ($aCategories as $aParams) {
					$sParam               = array();
					$sParam[]             = $aParams;
					$aIncludeCategories[] = $sParam;
				}
			}
			if ($bHeading) {
				$aCatHeadings = array_unique($aCatHeadings + $aCategories);
			}
			if ($bNotHeading) {
				$aCatNotHeadings = array_unique($aCatNotHeadings + $aCategories);
			}
			$bConflictsWithOpenReferences = true;
		}
		return $options;
	}

	/**
	 * Clean and test 'hiddencategories' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function hiddencategoriesParameter($option) {
		if (in_array($option, Options::$options['hiddencategories'])) {
			$sHiddenCategories = $option;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'notcategory' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notcategoryParameter($option) {
		$title = \Title::newFromText($option);
		if (!is_null($title)) {
			$aExcludeCategories[]         = $title->getDbKey();
			$bConflictsWithOpenReferences = true;
		}
		return $options;
	}

	/**
	 * Clean and test 'namespace' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function namespaceParameter($option) {
		$aParams = explode('|', $option);
		foreach ($aParams as $sParam) {
			$sParam = trim($sParam);
			$sNs    = $sParam;
			if (in_array($sNs, Options::$options['namespace'])) {
				$aNamespaces[]           = $wgContLang->getNsIndex($sNs);
				$bSelectionCriteriaFound = true;
			} else if (array_key_exists($sNs, array_keys(Options::$options['namespace']))) {
				$aNamespaces[]           = $sNs;
				$bSelectionCriteriaFound = true;
			} else {
				return false;
			}
		}
		return $options;
	}

	/**
	 * Clean and test 'redirects' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function redirectsParameter($option) {
		if (in_array($option, Options::$options['redirects'])) {
			$sRedirects                   = $option;
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'stablepages' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function stablepagesParameter($option) {
		if (in_array($option, Options::$options['stablepages'])) {
			$sStable                      = $option;
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'qualitypages' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function qualitypagesParameter($option) {
		if (in_array($option, Options::$options['qualitypages'])) {
			$sQuality                     = $option;
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'count' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function countParameter($option) {
		// setting by URL overwrites other settings, hence we ignore the command
		if ($sCount == '') {
			$sCount = trim($option);
		}
		return $options;
	}

	/**
	 * Clean and test 'addfirstcategorydate' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function addfirstcategorydateParameter($option) {
		if (in_array($option, Options::$options['addfirstcategorydate'])) {
			$bAddFirstCategoryDate        = self::filterBoolean($option);
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'ordermethod' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function ordermethodParameter($option) {
		$methods   = explode(',', $option);
		$breakaway = false;
		foreach ($methods as $method) {
			if (!in_array($method, Options::$options['ordermethod'])) {
				return false;
				$breakaway = true;
			}
		}
		if (!$breakaway) {
			$aOrderMethods = $methods;
			if ($methods[0] != 'none') {
				$bConflictsWithOpenReferences = true;
			}
		}
		return $options;
	}

	/**
	 * Clean and test 'order' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function orderParameter($option) {
		if (in_array($option, Options::$options['order'])) {
			$sOrder = $option;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'mode' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function modeParameter($option) {
		if (in_array($option, Options::$options['mode'])) {
			//'none' mode is implemented as a specific submode of 'inline' with <br/> as inline text
			if ($option == 'none') {
				$sPageListMode = 'inline';
				$sInlTxt       = '<br/>';
			} else if ($option == 'userformat') {
				// userformat resets inline text to empty string
				$sInlTxt       = '';
				$sPageListMode = $option;
			} else {
				$sPageListMode = $option;
			}
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'showcurid' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function showcuridParameter($option) {
		if (in_array($option, Options::$options['showcurid'])) {
			$bShowCurID = self::filterBoolean($option);
			if ($bShowCurID == true) {
				$bConflictsWithOpenReferences = true;
			}
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'shownamespace' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function shownamespaceParameter($option) {
		if (in_array($option, Options::$options['shownamespace'])) {
			$bShowNamespace = self::filterBoolean($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'suppresserrors' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function suppresserrorsParameter($option) {
		if (in_array($option, Options::$options['suppresserrors'])) {
			$bSuppressErrors = self::filterBoolean($option);
			if ($bSuppressErrors) {
				$sNoResultsHeader = ' ';
			}
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'execandexit' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function execandexitParameter($option) {
		// we offer a possibility to execute a DPL command without querying the database
		// this is useful if you want to catch the command line parameters DPL_arg1,... etc
		// in this case we prevent the parser cache from being disabled by later statements
		$sExecAndExit = $option;
		return $options;
	}

	/**
	 * Clean and test 'notnamespace' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notnamespaceParameter($option) {
		if (!in_array($option, Options::$options['notnamespace'])) {
			return $logger->msgWrongParam('notnamespace', $option);
		}
		$aExcludeNamespaces[]    = $wgContLang->getNsIndex($option);
		$bSelectionCriteriaFound = true;
		return $options;
	}

	/**
	 * Clean and test 'offset' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function offsetParameter($option) {
		//ensure that $iOffset is a number
		if (preg_match(Options::$options['offset']['pattern'], $option)) {
			$iOffset = ($option == '') ? 0 : intval($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'randomcount' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function randomcountParameter($option) {
		//ensure that $iRandomCount is a number;
		if (preg_match(Options::$options['randomcount']['pattern'], $option)) {
			$iRandomCount = ($option == '') ? null : intval($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'distinct' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function distinctParameter($option) {
		if (in_array($option, Options::$options['distinct'])) {
			if ($option == 'strict') {
				$sDistinctResultSet = 'strict';
			} else if (self::filterBoolean($option)) {
				$sDistinctResultSet = 'true';
			} else {
				$sDistinctResultSet = 'false';
			}
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'ordercollation' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function ordercollationParameter($option) {
		if ($option == 'bridge') {
			$bOrderSuitSymbols = true;
		} elseif ($option != '') {
			$sOrderCollation = "COLLATE ".self::$DB->strencode($option);
		}
		return $options;
	}

	/**
	 * Clean and test 'columns' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function columnsParameter($option) {
		//ensure that $iColumns is a number
		if (preg_match(Options::$options['columns']['pattern'], $option)) {
			$iColumns = ($option == '') ? 1 : intval($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'rows' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function rowsParameter($option) {
		//ensure that $iRows is a number
		if (preg_match(Options::$options['rows']['pattern'], $option)) {
			$iRows = ($option == '') ? 1 : intval($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'rowsize' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function rowsizeParameter($option) {
		//ensure that $iRowSize is a number
		if (preg_match(Options::$options['rowsize']['pattern'], $option)) {
			$iRowSize = ($option == '') ? 0 : intval($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'rowcolformat' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function rowcolformatParameter($option) {
		$sRowColFormat = self::stripHtmlTags($option);
		return $options;
	}

	/**
	 * Clean and test 'userdateformat' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function userdateformatParameter($option) {
		$sUserDateFormat = self::stripHtmlTags($option);
		return $options;
	}

	/**
	 * Clean and test 'escapelinks' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function escapelinksParameter($option) {
		if (in_array($option, Options::$options['escapelinks'])) {
			$bEscapeLinks = self::filterBoolean($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'inlinetext' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function inlinetextParameter($option) {
		$sInlTxt = self::stripHtmlTags($option);
		return $options;
	}

	/**
	 * Clean and test 'format' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function formatParameter($option) {
	case 'listseparators':
		// parsing of wikitext will happen at the end of the output phase
		// we replace '\n' in the input by linefeed because wiki syntax depends on linefeeds
		$option            = self::stripHtmlTags($option);
		$option            = str_replace('\n', "\n", $option);
		$option            = str_replace("¶", "\n", $option); // the paragraph delimiter is utf8-escaped
		$aListSeparators = explode(',', $option, 4);
		// mode=userformat will be automatically assumed
		$sPageListMode   = 'userformat';
		$sInlTxt         = '';
		return $options;
	}

	/**
	 * Clean and test 'title' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titleParameter($option) {
		// we replace blanks by underscores to meet the internal representation
		// of page names in the database
		$title = \Title::newFromText($option);
		if ($title) {
			$sNamespace                   = $title->getNamespace();
			$sTitleIs                     = str_replace(' ', '_', $title->getText());
			$aNamespaces[0]               = $sNamespace;
			$sPageListMode                = 'userformat';
			$aOrderMethods                = explode(',', '');
			$bSelectionCriteriaFound      = true;
			$bConflictsWithOpenReferences = true;
			$bAllowCachedResults          = true;
		}
		return $options;
	}

	/**
	 * Clean and test 'title<' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titleLTParameter($option) {
		// we replace blanks by underscores to meet the internal representation
		// of page names in the database
		$sTitleGE                = str_replace(' ', '_', $option);
		$bSelectionCriteriaFound = true;
		return $options;
	}

	/**
	 * Clean and test 'title<' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titleGTParameter($option) {
		// we replace blanks by underscores to meet the internal representation
		// of page names in the database
		$sTitleLE                = str_replace(' ', '_', $option);
		$bSelectionCriteriaFound = true;
		return $options;
	}

	/**
	 * Clean and test 'scroll' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function scrollParameter($option) {
		if (in_array($option, Options::$options['scroll'])) {
			$bScroll = self::filterBoolean($option);
			// if scrolling is active we adjust the values for certain other parameters
			// based on URL arguments
			if ($bScroll) {
				$sTitleGE = $wgRequest->getVal('DPL_fromTitle', '');
				if (strlen($sTitleGE) > 0) {
					$sTitleGE[0] = strtoupper($sTitleGE[0]);
				}
				// findTitle has priority over fromTitle
				$findTitle = $wgRequest->getVal('DPL_findTitle', '');
				if (strlen($findTitle) > 0) {
					$findTitle[0] = strtoupper($findTitle[0]);
				}
				if ($findTitle != '') {
					$sTitleGE = '=_' . $findTitle;
				}
				$sTitleLE = $wgRequest->getVal('DPL_toTitle', '');
				if (strlen($sTitleLE) > 0) {
					$sTitleLE[0] = strtoupper($sTitleLE[0]);
				}
				$sTitleGE     = str_replace(' ', '_', $sTitleGE);
				$sTitleLE     = str_replace(' ', '_', $sTitleLE);
				$scrollDir    = $wgRequest->getVal('DPL_scrollDir', '');
				// also set count limit from URL if not otherwise set
				$sCountScroll = $wgRequest->getVal('DPL_count', '');
			}
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'titlemaxlength' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titlemaxlengthParameter($option) {
		//processed like 'count' param
		if (preg_match(Options::$options['titlemaxlength']['pattern'], $option)) {
			$iTitleMaxLen = ($option == '') ? null : intval($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'replaceintitle' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function replaceintitleParameter($option) {
		// we offer a possibility to replace some part of the title
		$aReplaceInTitle = explode(',', $option, 2);
		if (isset($aReplaceInTitle[1])) {
			$aReplaceInTitle[1] = self::stripHtmlTags($aReplaceInTitle[1]);
		}
		return $options;
	}

	/**
	 * Clean and test 'resultsheader' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function resultsheaderParameter($option) {
		$sResultsHeader = self::stripHtmlTags($option);
		return $options;
	}

	/**
	 * Clean and test 'resultsfooter' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function resultsfooterParameter($option) {
		$sResultsFooter = self::stripHtmlTags($option);
		return $options;
	}

	/**
	 * Clean and test 'noresultsheader' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function noresultsheaderParameter($option) {
		$sNoResultsHeader = self::stripHtmlTags($option);
		return $options;
	}

	/**
	 * Clean and test 'noresultsfooter' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function noresultsfooterParameter($option) {
		$sNoResultsFooter = self::stripHtmlTags($option);
		return $options;
	}

	/**
	 * Clean and test 'oneresultheader' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function oneresultheaderParameter($option) {
		$sOneResultHeader = self::stripHtmlTags($option);
		return $options;
	}

	/**
	 * Clean and test 'oneresultfooter' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function oneresultfooterParameter($option) {
		$sOneResultFooter = self::stripHtmlTags($option);
		return $options;
	}

	/**
	 * Clean and test 'debug' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function debugParameter($option) {
		if (in_array($option, Options::$options['debug'])) {
			if ($key > 1) {
				$output .= $logger->escapeMsg(\DynamicPageListHooks::WARN_DEBUGPARAMNOTFIRST, $option);
			}
			$logger->iDebugLevel = intval($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'linksto' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function linkstoParameter($option) {
		$problems = self::getPageNameList('linksto', $option, $aLinksTo, $bSelectionCriteriaFound, $logger, true);
		if ($problems != '') {
			return $problems;
		}
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'notlinksto' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notlinkstoParameter($option) {
		$problems = self::getPageNameList('notlinksto', $option, $aNotLinksTo, $bSelectionCriteriaFound, $logger, true);
		if ($problems != '') {
			return $problems;
		}
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'linksfrom' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function linksfromParameter($option) {
		$problems = self::getPageNameList('linksfrom', $option, $aLinksFrom, $bSelectionCriteriaFound, $logger, true);
		if ($problems != '') {
			return $problems;
		}
		// $bConflictsWithOpenReferences=true;
		return $options;
	}

	/**
	 * Clean and test 'notlinksfrom' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notlinksfromParameter($option) {
		$problems = self::getPageNameList('notlinksfrom', $option, $aNotLinksFrom, $bSelectionCriteriaFound, $logger, true);
		if ($problems != '') {
			return $problems;
		}
		return $options;
	}

	/**
	 * Clean and test 'linkstoexternal' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function linkstoexternalParameter($option) {
		$problems = self::getPageNameList('linkstoexternal', $option, $aLinksToExternal, $bSelectionCriteriaFound, $logger, false);
		if ($problems != '') {
			return $problems;
		}
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'imageused' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function imageusedParameter($option) {
		$pages = explode('|', trim($option));
		$n     = 0;
		foreach ($pages as $page) {
			if (trim($page) == '') {
				continue;
			}
			if (!($theTitle = \Title::newFromText(trim($page)))) {
				return $logger->msgWrongParam('imageused', $option);
			}
			$aImageUsed[$n++]        = $theTitle;
			$bSelectionCriteriaFound = true;
		}
		if (!$bSelectionCriteriaFound) {
			return $logger->msgWrongParam('imageused', $option);
		}
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'imagecontainer' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function imagecontainerParameter($option) {
		$pages = explode('|', trim($option));
		$n     = 0;
		foreach ($pages as $page) {
			if (trim($page) == '') {
				continue;
			}
			if (!($theTitle = \Title::newFromText(trim($page)))) {
				return $logger->msgWrongParam('imagecontainer', $option);
			}
			$aImageContainer[$n++]   = $theTitle;
			$bSelectionCriteriaFound = true;
		}
		if (!$bSelectionCriteriaFound) {
			return $logger->msgWrongParam('imagecontainer', $option);
		}
		return $options;
	}

	/**
	 * Clean and test 'uses' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function usesParameter($option) {
		$pages = explode('|', $option);
		$n     = 0;
		foreach ($pages as $page) {
			if (trim($page) == '') {
				continue;
			}
			if (!($theTitle = \Title::newFromText(trim($page)))) {
				return $logger->msgWrongParam('uses', $option);
			}
			$aUses[$n++]             = $theTitle;
			$bSelectionCriteriaFound = true;
		}
		if (!$bSelectionCriteriaFound) {
			return $logger->msgWrongParam('uses', $option);
		}
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'notuses' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notusesParameter($option) {
		$pages = explode('|', $option);
		$n     = 0;
		foreach ($pages as $page) {
			if (trim($page) == '') {
				continue;
			}
			if (!($theTitle = \Title::newFromText(trim($page)))) {
				return $logger->msgWrongParam('notuses', $option);
			}
			$aNotUses[$n++]          = $theTitle;
			$bSelectionCriteriaFound = true;
		}
		if (!$bSelectionCriteriaFound) {
			return $logger->msgWrongParam('notuses', $option);
		}
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'usedby' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function usedbyParameter($option) {
		$pages = explode('|', $option);
		$n     = 0;
		foreach ($pages as $page) {
			if (trim($page) == '') {
				continue;
			}
			if (!($theTitle = \Title::newFromText(trim($page)))) {
				return $logger->msgWrongParam('usedby', $option);
			}
			$aUsedBy[$n++]           = $theTitle;
			$bSelectionCriteriaFound = true;
		}
		if (!$bSelectionCriteriaFound) {
			return $logger->msgWrongParam('usedby', $option);
		}
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'createdby' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function createdbyParameter($option) {
		$sCreatedBy = $option;
		if ($sCreatedBy != '') {
			$bSelectionCriteriaFound = true;
		}
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'notcreatedby' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notcreatedbyParameter($option) {
		$sNotCreatedBy = $option;
		if ($sNotCreatedBy != '') {
			$bSelectionCriteriaFound = true;
		}
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'modifiedby' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function modifiedbyParameter($option) {
		$sModifiedBy = $option;
		if ($sModifiedBy != '') {
			$bSelectionCriteriaFound = true;
		}
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'notmodifiedby' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notmodifiedbyParameter($option) {
		$sNotModifiedBy = $option;
		if ($sNotModifiedBy != '') {
			$bSelectionCriteriaFound = true;
		}
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'lastmodifiedby' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function lastmodifiedbyParameter($option) {
		$sLastModifiedBy = $option;
		if ($sLastModifiedBy != '') {
			$bSelectionCriteriaFound = true;
		}
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'notlastmodifiedby' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notlastmodifiedbyParameter($option) {
		$sNotLastModifiedBy = $option;
		if ($sNotLastModifiedBy != '') {
			$bSelectionCriteriaFound = true;
		}
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'titlematch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titlematchParameter($option) {
		// we replace blanks by underscores to meet the internal representation
		// of page names in the database
		$aTitleMatch             = explode('|', str_replace(' ', '\_', $option));
		$bSelectionCriteriaFound = true;
		return $options;
	}

	/**
	 * Clean and test 'minoredits' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function minoreditsParameter($option) {
		if (in_array($option, Options::$options['minoredits'])) {
			$sMinorEdits                  = $option;
			$bConflictsWithOpenReferences = true;
		} else { //wrong param val, using default
			$sMinorEdits = Options::$options['minoredits']['default'];
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'includesubpages' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includesubpagesParameter($option) {
		if (in_array($option, Options::$options['includesubpages'])) {
			$bIncludeSubpages = self::filterBoolean($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'ignorecase' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function ignorecaseParameter($option) {
		if (in_array($option, Options::$options['ignorecase'])) {
			$bIgnoreCase = self::filterBoolean($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'categoriesminmax' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function categoriesminmaxParameter($option) {
		if (preg_match(Options::$options['categoriesminmax']['pattern'], $option)) {
			$aCatMinMax = ($option == '') ? null : explode(',', $option);
		} else { // wrong value
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'skipthispage' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function skipthispageParameter($option) {
		if (in_array($option, Options::$options['skipthispage'])) {
			$bSkipThisPage = self::filterBoolean($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'addcategories' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function addcategoriesParameter($option) {
		if (in_array($option, Options::$options['addcategories'])) {
			$bAddCategories               = self::filterBoolean($option);
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'addeditdate' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function addeditdateParameter($option) {
		if (in_array($option, Options::$options['addeditdate'])) {
			$bAddEditDate                 = self::filterBoolean($option);
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'addexternallink' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function addexternallinkParameter($option) {
		if (in_array($option, Options::$options['addexternallink'])) {
			$bAddExternalLink             = self::filterBoolean($option);
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'addpagecounter' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function addpagecounterParameter($option) {
		if (in_array($option, Options::$options['addpagecounter'])) {
			$bAddPageCounter              = self::filterBoolean($option);
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'addpagesize' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function addpagesizeParameter($option) {
		if (in_array($option, Options::$options['addpagesize'])) {
			$bAddPageSize                 = self::filterBoolean($option);
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'addpagetoucheddate' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function addpagetoucheddateParameter($option) {
		if (in_array($option, Options::$options['addpagetoucheddate'])) {
			$bAddPageTouchedDate          = self::filterBoolean($option);
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'include' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includeParameter($option) {
	case 'includepage':
		$bIncPage = $option !== '';
		if ($bIncPage) {
			$aSecLabels = explode(',', $option);
		}
		return $options;
	}

	/**
	 * Clean and test 'includematchparsed' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includematchparsedParameter($option) {
		$bIncParsed = true;
		return $options;
	}

	/**
	 * Clean and test 'includematch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includematchParameter($option) {
		$aSecLabelsMatch = explode(',', $option);
		return $options;
	}

	/**
	 * Clean and test 'includenotmatchparsed' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includenotmatchparsedParameter($option) {
		$bIncParsed = true;
		return $options;
	}

	/**
	 * Clean and test 'includenotmatch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includenotmatchParameter($option) {
		$aSecLabelsNotMatch = explode(',', $option);
		return $options;
	}

	/**
	 * Clean and test 'includetrim' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includetrimParameter($option) {
		if (in_array($option, Options::$options['includetrim'])) {
			$bIncludeTrim = self::filterBoolean($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'adduser' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function adduserParameter($option) {
		if (in_array($option, Options::$options['adduser'])) {
			$bAddUser                     = self::filterBoolean($option);
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'addauthor' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function addauthorParameter($option) {
		if (in_array($option, Options::$options['addauthor'])) {
			$bAddAuthor                   = self::filterBoolean($option);
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'addcontribution' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function addcontributionParameter($option) {
		if (in_array($option, Options::$options['addcontribution'])) {
			$bAddContribution             = self::filterBoolean($option);
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'addlasteditor' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function addlasteditorParameter($option) {
		if (in_array($option, Options::$options['addlasteditor'])) {
			$bAddLastEditor               = self::filterBoolean($option);
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'headingmode' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function headingmodeParameter($option) {
		if (in_array($option, Options::$options['headingmode'])) {
			$sHListMode                   = $option;
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'headingcount' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function headingcountParameter($option) {
		if (in_array($option, Options::$options['headingcount'])) {
			$bHeadingCount                = self::filterBoolean($option);
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'secseparators' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function secseparatorsParameter($option) {
		// we replace '\n' by newline to support wiki syntax within the section separators
		$option           = str_replace('\n', "\n", $option);
		$option           = str_replace("¶", "\n", $option); // the paragraph delimiter is utf8-escaped
		$aSecSeparators = explode(',', $option);
		return $options;
	}

	/**
	 * Clean and test 'multisecseparators' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function multisecseparatorsParameter($option) {
		// we replace '\n' by newline to support wiki syntax within the section separators
		$option                = str_replace('\n', "\n", $option);
		$option                = str_replace("¶", "\n", $option); // the paragraph delimiter is utf8-escaped
		$aMultiSecSeparators = explode(',', $option);
		return $options;
	}

	/**
	 * Clean and test 'table' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function tableParameter($option) {
		$option   = str_replace('\n', "\n", $option);
		$sTable = str_replace("¶", "\n", $option); // the paragraph delimiter is utf8-escaped
		return $options;
	}

	/**
	 * Clean and test 'tablerow' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function tablerowParameter($option) {
		$option = str_replace('\n', "\n", $option);
		$option = str_replace("¶", "\n", $option); // the paragraph delimiter is utf8-escaped
		if (trim($option) == '') {
			$aTableRow = array();
		} else {
			$aTableRow = explode(',', $option);
		}
		return $options;
	}

	/**
	 * Clean and test 'tablesortcol' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function tablesortcolParameter($option) {
		if (preg_match(Options::$options['tablesortcol']['pattern'], $option)) {
			$iTableSortCol = ($option == '') ? 0 : intval($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'dominantsection' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function dominantsectionParameter($option) {
		if (preg_match(Options::$options['dominantsection']['pattern'], $option)) {
			$iDominantSection = ($option == '') ? null : intval($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'includemaxlength' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includemaxlengthParameter($option) {
		//processed like 'count' param
		if (preg_match(Options::$options['includemaxlength']['pattern'], $option)) {
			$iIncludeMaxLen = ($option == '') ? null : intval($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'listattr' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function listattrParameter($option) {
		$sListHtmlAttr = $option;
		return $options;
	}

	/**
	 * Clean and test 'itemattr' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function itemattrParameter($option) {
		$sItemHtmlAttr = $option;
		return $options;
	}

	/**
	 * Clean and test 'hlistattr' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function hlistattrParameter($option) {
		$sHListHtmlAttr = $option;
		return $options;
	}

	/**
	 * Clean and test 'hitemattr' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function hitemattrParameter($option) {
		$sHItemHtmlAttr = $option;
		return $options;
	}

	/**
	 * Clean and test 'allowcachedresults' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function allowcachedresultsParameter($option) {
		// if execAndExit was previously set (i.e. if it is not empty) we will ignore all cache settings
		// which are placed AFTER the execandexit statement
		// thus we make sure that the cache will only become invalid if the query is really executed
		if ($sExecAndExit == '') {
			if (in_array($option, Options::$options['allowcachedresults'])) {
				$bAllowCachedResults = self::filterBoolean($option);
				if ($option == 'yes+warn') {
					$bAllowCachedResults = true;
					$bWarnCachedResults  = true;
				}
			} else {
				return false;
			}
		}
		return $options;
	}

	/**
	 * Clean and test 'dplcache' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function dplcacheParameter($option) {
		if ($option != '') {
			$DPLCache     = $parser->mTitle->getArticleID() . '_' . str_replace("/", "_", $option) . '.dplc';
			$DPLCachePath = $parser->mTitle->getArticleID() % 10;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'dplcacheperiod' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function dplcacheperiodParameter($option) {
		if (preg_match(Options::$options['dplcacheperiod']['pattern'], $option)) {
			$iDPLCachePeriod = ($option == '') ? Options::$options['dplcacheperiod']['default'] : intval($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'fixcategory' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function fixcategoryParameter($option) {
		\DynamicPageListHooks::fixCategory($option);
		return $options;
	}

	/**
	 * Clean and test 'reset' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function resetParameter($option) {
		foreach (preg_split('/[;,]/', $option) as $arg) {
			$arg = trim($arg);
			if ($arg == '') {
				continue;
			}
			if (!in_array($arg, Options::$options['reset'])) {
				return false;
			} elseif ($arg == 'links') {
				$bReset[0] = true;
			} elseif ($arg == 'templates') {
				$bReset[1] = true;
			} elseif ($arg == 'categories') {
				$bReset[2] = true;
			} elseif ($arg == 'images') {
				$bReset[3] = true;
			} elseif ($arg == 'all') {
				$bReset[0] = true;
				$bReset[1] = true;
				$bReset[2] = true;
				$bReset[3] = true;
			}
		}
		return $options;
	}

	/**
	 * Clean and test 'eliminate' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function eliminateParameter($option) {
		foreach (preg_split('/[;,]/', $option) as $arg) {
			$arg = trim($arg);
			if ($arg == '') {
				continue;
			}
			if (!in_array($arg, Options::$options['eliminate'])) {
				return false;
			} elseif ($arg == 'links') {
				$bReset[4] = true;
			} elseif ($arg == 'templates') {
				$bReset[5] = true;
			} elseif ($arg == 'categories') {
				$bReset[6] = true;
			} elseif ($arg == 'images') {
				$bReset[7] = true;
			} elseif ($arg == 'all') {
				$bReset[4] = true;
				$bReset[5] = true;
				$bReset[6] = true;
				$bReset[7] = true;
			} elseif ($arg == 'none') {
				$bReset[4] = false;
				$bReset[5] = false;
				$bReset[6] = false;
				$bReset[7] = false;
			}
		}
		return $options;
	}

	/**
	 * Clean and test 'categoryregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function categoryregexpParameter($option) {
		$sCategoryComparisonMode      = ' REGEXP ';
		$aIncludeCategories[]         = array(
			$option
		);
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'categorymatch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function categorymatchParameter($option) {
		$sCategoryComparisonMode      = ' LIKE ';
		$aIncludeCategories[]         = explode('|', $option);
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'notcategoryregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notcategoryregexpParameter($option) {
		$sNotCategoryComparisonMode   = ' REGEXP ';
		$aExcludeCategories[]         = $option;
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'notcategorymatch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notcategorymatchParameter($option) {
		$sNotCategoryComparisonMode   = ' LIKE ';
		$aExcludeCategories[]         = $option;
		$bConflictsWithOpenReferences = true;
		return $options;
	}

	/**
	 * Clean and test 'titleregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titleregexpParameter($option) {
		$sTitleMatchMode         = ' REGEXP ';
		$aTitleMatch             = array(
			$option
		);
		$bSelectionCriteriaFound = true;
		return $options;
	}

	/**
	 * Clean and test 'nottitleregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function nottitleregexpParameter($option) {
		$sNotTitleMatchMode      = ' REGEXP ';
		$aNotTitleMatch          = array(
			$option
		);
		$bSelectionCriteriaFound = true;
		return $options;
	}

	/**
	 * Clean and test 'nottitlematch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function nottitlematchParameter($option) {
		// we replace blanks by underscores to meet the internal representation
		// of page names in the database
		$aNotTitleMatch          = explode('|', str_replace(' ', '_', $option));
		$bSelectionCriteriaFound = true;
		return $options;
	}

	/**
	 * Clean and test 'lastrevisionbefore' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function lastrevisionbeforeParameter($option) {
	case 'allrevisionsbefore':
	case 'firstrevisionsince':
	case 'allrevisionssince':
		if (preg_match(Options::$options[$parameter]['pattern'], $option)) {
			$date = str_pad(preg_replace('/[^0-9]/', '', $option), 14, '0');
			$date = $wgLang->userAdjust($date);
			if (($parameter) == 'lastrevisionbefore') {
				$sLastRevisionBefore = $date;
			}
			if (($parameter) == 'allrevisionsbefore') {
				$sAllRevisionsBefore = $date;
			}
			if (($parameter) == 'firstrevisionsince') {
				$sFirstRevisionSince = $date;
			}
			if (($parameter) == 'allrevisionssince') {
				$sAllRevisionsSince = $date;
			}
			$bConflictsWithOpenReferences = true;
		} else {
			$output .= $logger->msgWrongParam($parameter, $option);
		}
		return $options;
	}

	/**
	 * Clean and test 'minrevisions' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function minrevisionsParameter($option) {
		//ensure that $iMinRevisions is a number
		if (preg_match(Options::$options['minrevisions']['pattern'], $option)) {
			$iMinRevisions = ($option == '') ? null : intval($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'maxrevisions' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function maxrevisionsParameter($option) {
		//ensure that $iMaxRevisions is a number
		if (preg_match(Options::$options['maxrevisions']['pattern'], $option)) {
			$iMaxRevisions = ($option == '') ? null : intval($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'openreferences' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function openreferencesParameter($option) {
		if (in_array($option, Options::$options['openreferences'])) {
			$acceptOpenReferences = self::filterBoolean($option);
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'articlecategory' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function articlecategoryParameter($option) {
		$sArticleCategory = str_replace(' ', '_', $option);
		return $options;
	}

	/**
	 * Clean and test 'goal' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function goalParameter($option) {
		if (in_array($option, Options::$options['goal'])) {
			$sGoal                        = $option;
			$bConflictsWithOpenReferences = true;
		} else {
			return false;
		}
		return $options;
	}

	/**
	 * Clean and test 'updaterules' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function updaterulesParameter($option) {
		$sUpdateRules = $option;
		return $options;
	}

	/**
	 * Clean and test 'deleterules' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function deleterulesParameter($option) {
		$sDeleteRules = $option;
		return $options;
	}
}
?>