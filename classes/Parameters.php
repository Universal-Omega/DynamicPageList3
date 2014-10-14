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
	 * List of all the valid parameters that can be used per level of functional richness.
	 *
	 * @var		array
	 */
	private $setOptions = [];

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
	 * Filter a standard boolean like value into an actual boolean.
	 *
	 * @access	private
	 * @param	mixed	Integer or string to evaluated through filter_var().
	 * @return	boolean
	 */
	private function filterBoolean($boolean) {
		return filter_var($boolean, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
	}

	/**
	 * Strip <html> tags.
	 *
	 * @access	private
	 * @param	string	Dirty Text
	 * @return	string	Clean Text
	 */
	private function stripHtmlTags($text) {
		$text = preg_replace("#<.*?html.*?>#is", "", $text);

		return $text;
	}

	/**
	 * Handle simple parameter functions.
	 *
	 * @access	public
	 * @param	string	Function(Parameter) Called
	 * @param	string	Function Arguments
	 * @return	boolean	Successful
	 */
	public function __call($parameter, $arguments) {
		//Subvert to the real function if it exists.  This keeps code elsewhere clean from needed to check if it exists first.
		if (method_exists($this, $parameter.'Parameter')) {
			return call_user_func_array($this->$parameter.'Parameter', $arguments);
		}
		$option = $arguments[0];
		$parameter = strtolower($parameter);

		//Assume by default that these simple parameter options should not failed, but if they do we will set $success to false below.
		$success = true;
		if (array_key_exists($parameter, Options::$options)) {
			$this->setOption[$parameter] = $option;
			$paramData = Options::$options[$parameter];

			//If a parameter specifies options then enforce them.
			if (is_array($paramData['values']) === true && in_array($option, $paramData['values'])) {
				$this->setOption[$parameter] = $option;
			} else {
				$success = false;
			}

			//Set that criteria was found for a selection.
			if ($paramData['set_criteria_found'] === true && !empty($option)) {
				$this->setSelectionCriteriaFound(true);
			}

			//Set open references conflict possibility.
			if ($paramData['open_ref_conflict'] === true) {
				$this->setOpenReferencesConflict(true);
			}

			//Strip <html> tag.
			if ($paramData['strip_html'] === true) {
				$this->setOption[$parameter] = self::stripHtmlTags($option);
			}

			//Simple integer intval().
			if ($paramData['integer'] === true) {
				if (!is_numeric($option)) {
					if ($paramData['default'] !== null) {
						$this->setOption[$parameter] = intval($paramData['default']);
					} else {
						$success = false;
					}
				} else {
					$this->setOption[$parameter] = intval($option);
				}
			}

			//Handle Booleans
			if ($paramData['boolean'] === true) {
				$option = $this->filterBoolean($option);
				if ($option !== null) {
					$this->setOption[$parameter] = $option;
				} else {
					$success = false;
				}
			}
		}
		return $success;
	}

	/**
	 * Clean and test '$1' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function categoryParameter($option, Options $options) {
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
			$this->setOpenReferencesConflict(true);
		}
	}

	/**
	 * Clean and test 'notcategory' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function notcategoryParameter($option, Options $options) {
		$title = \Title::newFromText($option);
		if (!is_null($title)) {
			$this->setOption['excludecategories'][] = $title->getDbKey();
			$this->setOpenReferencesConflict(true);
			return true;
		}
		return false;
	}

	/**
	 * Clean and test 'namespace' parameter.
	 *
	 * @access	public
	 * @param	string	Option passed to parameter.
	 * @return	boolean	Success
	 */
	public function namespaceParameter($option, Options $options) {
		$extraParams = explode('|', $option);
		foreach ($extraParams as $parameter) {
			$parameter = trim($parameter);
			if (in_array($parameter, $options->getOptions('namespace')['values'])) {
				$this->setOption['namespaces'][] = $wgContLang->getNsIndex($parameter);
				$this->setSelectionCriteriaFound(true);
			} elseif (array_key_exists($parameter, array_keys($options->getOptions('namespace')['values']))) {
				$this->setOption['namespaces'][] = $parameter;
				$this->setSelectionCriteriaFound(true);
			} else {
				return false;
			}
		}
		return true;
	}

	/**
	 * Clean and test 'notnamespace' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function notnamespaceParameter($option, Options $options) {
		if (!in_array($option, $options->getOptions('notnamespace')['values'])) {
			return false;
		}
		$this->setOption['excludenamespaces'][] = $wgContLang->getNsIndex($option);
		$this->setSelectionCriteriaFound(true);
		return true;
	}

	/**
	 * Clean and test 'ordermethod' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function ordermethodParameter($option, Options $options) {
		$methods   = explode(',', $option);
		$success = true;
		foreach ($methods as $method) {
			if (!in_array($method, $options->getOptions('ordermethod')['values'])) {
				$success = false;
			}
		}
		if ($success === true) {
			$this->setOption['ordermethods'] = $methods;
			if ($methods[0] != 'none') {
				$this->setOpenReferencesConflict(true);
			}
		}
		return false;
	}

	/**
	 * Clean and test 'mode' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function modeParameter($option, Options $options) {
		if (in_array($option, $options->getOptions('mode')['values'])) {
			//'none' mode is implemented as a specific submode of 'inline' with <br/> as inline text
			if ($option == 'none') {
				$this->setOption['pagelistmode'] = 'inline';
				$this->setOption['inltxt']       = '<br/>';
			} else if ($option == 'userformat') {
				// userformat resets inline text to empty string
				$this->setOption['inltxt']       = '';
				$this->setOption['pagelistmode'] = $option;
			} else {
				$this->setOption['pagelistmode'] = $option;
			}
		} else {
			return false;
		}
	}

	/**
	 * Clean and test 'distinct' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function distinctParameter($option, Options $options) {
		if (in_array($option, $options->getOptions('distinct')['values'])) {
			if ($option == 'strict') {
				$this->setOption['distinctresultset'] = 'strict';
			} elseif (self::filterBoolean($option) === true) {
				$this->setOption['distinctresultset'] = true;
			} else {
				$this->setOption['distinctresultset'] = false;
			}
		} else {
			return false;
		}
	}

	/**
	 * Clean and test 'ordercollation' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function ordercollationParameter($option, Options $options) {
		if ($option == 'bridge') {
			$this->setOption['ordersuitsymbols'] = true;
		} elseif (!empty($option)) {
			$this->setOption['ordercollation'] = "COLLATE ".self::$DB->strencode($option);
		}
	}

	/**
	 * Short cut to formatParameter();
	 *
	 * @access	public
	 * @return	mixed
	 */
	public function listseparatorsParameter() {
		return call_user_func_array([$this, 'formatParameter'], func_get_args());
	}

	/**
	 * Clean and test 'format' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function formatParameter($option, Options $options) {
		// parsing of wikitext will happen at the end of the output phase
		// we replace '\n' in the input by linefeed because wiki syntax depends on linefeeds
		$option            = self::stripHtmlTags($option);
		$option            = str_replace(['\n', "¶"], "\n", $option);
		$this->setOption['listseparators'] = explode(',', $option, 4);
		// mode=userformat will be automatically assumed
		$this->setOption['pagelistmode']   = 'userformat';
		$this->setOption['inltxt']         = '';
	}

	/**
	 * Clean and test 'title' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titleParameter($option, Options $options) {
		// we replace blanks by underscores to meet the internal representation
		// of page names in the database
		$title = \Title::newFromText($option);
		if ($title) {
			$this->setOption['namespace']                   = $title->getNamespace();
			$this->setOption['titleis']                     = str_replace(' ', '_', $title->getText());
			$this->setOption['namespaces'][0]               = $sNamespace;
			$this->setOption['pagelistmode']                = 'userformat';
			$this->setOption['ordermethods']                = explode(',', '');
			$this->setOption['selectioncriteriafound']      = true;
			$this->setOpenReferencesConflict(true);
			$this->setOption['allowcachedresults']          = true;
		}
	}

	/**
	 * Clean and test 'title<' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titleLTParameter($option, Options $options) {
		// we replace blanks by underscores to meet the internal representation
		// of page names in the database
		$this->setOption['titlege']                = str_replace(' ', '_', $option);
		$this->setSelectionCriteriaFound(true);
	}

	/**
	 * Clean and test 'title<' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titleGTParameter($option, Options $options) {
		// we replace blanks by underscores to meet the internal representation
		// of page names in the database
		$this->setOption['titlele']                = str_replace(' ', '_', $option);
		$this->setSelectionCriteriaFound(true);
	}

	/**
	 * Clean and test 'scroll' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function scrollParameter($option, Options $options) {
		if (in_array($option, $options->getOptions('scroll')['values'])) {
			$this->setOption['scroll'] = self::filterBoolean($option);
			// if scrolling is active we adjust the values for certain other parameters
			// based on URL arguments
			if ($this->setOption['scroll'] === true) {
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
					$this->setOption['titlege'] = '=_' . $findTitle;
				}
				$sTitleLE = $wgRequest->getVal('DPL_toTitle', '');
				if (strlen($sTitleLE) > 0) {
					$sTitleLE[0] = strtoupper($sTitleLE[0]);
				}
				$this->setOption['titlege']     = str_replace(' ', '_', $sTitleGE);
				$this->setOption['titlele']     = str_replace(' ', '_', $sTitleLE);
				$this->setOption['crolldir']    = $wgRequest->getVal('DPL_scrollDir', '');
				// also set count limit from URL if not otherwise set
				$this->setOption['countscroll'] = $wgRequest->getVal('DPL_count', '');
			}
		} else {
			return false;
		}
	}

	/**
	 * Clean and test 'replaceintitle' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function replaceintitleParameter($option, Options $options) {
		// we offer a possibility to replace some part of the title
		$aReplaceInTitle = explode(',', $option, 2);
		if (isset($aReplaceInTitle[1])) {
			$this->setOption['replaceintitle'][1] = self::stripHtmlTags($aReplaceInTitle[1]);
		}

	/**
	 * Clean and test 'debug' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function debugParameter($option, Options $options) {
		if (in_array($option, $options->getOptions('debug')['values'])) {
			if ($key > 1) {
				$output .= $logger->escapeMsg(\DynamicPageListHooks::WARN_DEBUGPARAMNOTFIRST, $option);
			}
			$logger->iDebugLevel = intval($option);
		} else {
			return false;
		}
	}

	/**
	 * Clean and test 'linksto' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function linkstoParameter($option, Options $options) {
		$problems = self::getPageNameList('linksto', $option, $aLinksTo, $bSelectionCriteriaFound, $logger, true);
		if ($problems != '') {
			return $problems;
		}
		$this->setOpenReferencesConflict(true);
	}

	/**
	 * Clean and test 'notlinksto' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notlinkstoParameter($option, Options $options) {
		$problems = self::getPageNameList('notlinksto', $option, $aNotLinksTo, $bSelectionCriteriaFound, $logger, true);
		if ($problems != '') {
			return $problems;
		}
		$this->setOpenReferencesConflict(true);
	}

	/**
	 * Clean and test 'linksfrom' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function linksfromParameter($option, Options $options) {
		$problems = self::getPageNameList('linksfrom', $option, $aLinksFrom, $bSelectionCriteriaFound, $logger, true);
		if ($problems != '') {
			return $problems;
		}
		// $this->setOption['conflictswithopenreferences']=true;
	}

	/**
	 * Clean and test 'notlinksfrom' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notlinksfromParameter($option, Options $options) {
		$problems = self::getPageNameList('notlinksfrom', $option, $aNotLinksFrom, $bSelectionCriteriaFound, $logger, true);
		if ($problems != '') {
			return $problems;
		}
	}

	/**
	 * Clean and test 'linkstoexternal' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function linkstoexternalParameter($option, Options $options) {
		$problems = self::getPageNameList('linkstoexternal', $option, $aLinksToExternal, $bSelectionCriteriaFound, $logger, false);
		if ($problems != '') {
			return $problems;
		}
		$this->setOpenReferencesConflict(true);
	}

	/**
	 * Clean and test 'imageused' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function imageusedParameter($option, Options $options) {
		$pages = explode('|', trim($option));
		$n     = 0;
		foreach ($pages as $page) {
			if (trim($page) == '') {
				continue;
			}
			if (!($theTitle = \Title::newFromText(trim($page)))) {
				return $logger->msgWrongParam('imageused', $option);
			}
			$this->setOption['imageused'][$n++]        = $theTitle;
			$this->setSelectionCriteriaFound(true);
		}
		if (!$bSelectionCriteriaFound) {
			return $logger->msgWrongParam('imageused', $option);
		}
		$this->setOpenReferencesConflict(true);
	}

	/**
	 * Clean and test 'imagecontainer' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function imagecontainerParameter($option, Options $options) {
		$pages = explode('|', trim($option));
		$n     = 0;
		foreach ($pages as $page) {
			if (trim($page) == '') {
				continue;
			}
			if (!($theTitle = \Title::newFromText(trim($page)))) {
				return $logger->msgWrongParam('imagecontainer', $option);
			}
			$this->setOption['imagecontainer'][$n++]   = $theTitle;
			$this->setSelectionCriteriaFound(true);
		}
		if (!$bSelectionCriteriaFound) {
			return $logger->msgWrongParam('imagecontainer', $option);
		}
	}

	/**
	 * Clean and test 'uses' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function usesParameter($option, Options $options) {
		$pages = explode('|', $option);
		$n     = 0;
		foreach ($pages as $page) {
			if (trim($page) == '') {
				continue;
			}
			if (!($theTitle = \Title::newFromText(trim($page)))) {
				return $logger->msgWrongParam('uses', $option);
			}
			$this->setOption['uses'][$n++]             = $theTitle;
			$this->setSelectionCriteriaFound(true);
		}
		if (!$bSelectionCriteriaFound) {
			return $logger->msgWrongParam('uses', $option);
		}
		$this->setOpenReferencesConflict(true);
	}

	/**
	 * Clean and test 'notuses' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notusesParameter($option, Options $options) {
		$pages = explode('|', $option);
		$n     = 0;
		foreach ($pages as $page) {
			if (trim($page) == '') {
				continue;
			}
			if (!($theTitle = \Title::newFromText(trim($page)))) {
				return $logger->msgWrongParam('notuses', $option);
			}
			$this->setOption['notuses'][$n++]          = $theTitle;
			$this->setSelectionCriteriaFound(true);
		}
		if (!$bSelectionCriteriaFound) {
			return $logger->msgWrongParam('notuses', $option);
		}
		$this->setOpenReferencesConflict(true);
	}

	/**
	 * Clean and test 'usedby' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function usedbyParameter($option, Options $options) {
		$pages = explode('|', $option);
		$n     = 0;
		foreach ($pages as $page) {
			if (trim($page) == '') {
				continue;
			}
			if (!($theTitle = \Title::newFromText(trim($page)))) {
				return $logger->msgWrongParam('usedby', $option);
			}
			$this->setOption['usedby'][$n++]           = $theTitle;
			$this->setSelectionCriteriaFound(true);
		}
		if (!$bSelectionCriteriaFound) {
			return $logger->msgWrongParam('usedby', $option);
		}
		$this->setOpenReferencesConflict(true);
	}

	/**
	 * Clean and test 'titlematch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titlematchParameter($option, Options $options) {
		// we replace blanks by underscores to meet the internal representation
		// of page names in the database
		$aTitleMatch             = explode('|', str_replace(' ', '\_', $option));
		$this->setSelectionCriteriaFound(true);
	}

	/**
	 * Clean and test 'minoredits' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function minoreditsParameter($option, Options $options) {
		if (in_array($option, $options->getOptions('minoredits')['values'])) {
			$sMinorEdits                  = $option;
			$this->setOpenReferencesConflict(true);
		} else { //wrong param val, using default
			$sMinorEdits = $options->getOptions('minoredits')['default'];
			return false;
		}
	}

	/**
	 * Clean and test 'categoriesminmax' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function categoriesminmaxParameter($option, Options $options) {
		if (preg_match($options->getOptions('categoriesminmax')['pattern'], $option)) {
			$aCatMinMax = ($option == '') ? null : explode(',', $option);
		} else { // wrong value
			return false;
		}
	}

	/**
	 * Clean and test 'include' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includeParameter($option, Options $options) {
	case 'includepage':
		$bIncPage = $option !== '';
		if ($bIncPage) {
			$aSecLabels = explode(',', $option);
		}
	}

	/**
	 * Clean and test 'includematchparsed' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includematchparsedParameter($option, Options $options) {
		$bIncParsed = true;
	}

	/**
	 * Clean and test 'includematch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includematchParameter($option, Options $options) {
		$aSecLabelsMatch = explode(',', $option);
	}

	/**
	 * Clean and test 'includenotmatchparsed' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includenotmatchparsedParameter($option, Options $options) {
		$bIncParsed = true;
	}

	/**
	 * Clean and test 'includenotmatch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function includenotmatchParameter($option, Options $options) {
		$aSecLabelsNotMatch = explode(',', $option);
	}

	/**
	 * Clean and test 'secseparators' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function secseparatorsParameter($option, Options $options) {
		// we replace '\n' by newline to support wiki syntax within the section separators
		$option           = str_replace('\n', "\n", $option);
		$option           = str_replace("¶", "\n", $option); // the paragraph delimiter is utf8-escaped
		$aSecSeparators = explode(',', $option);
	}

	/**
	 * Clean and test 'multisecseparators' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function multisecseparatorsParameter($option, Options $options) {
		// we replace '\n' by newline to support wiki syntax within the section separators
		$option                = str_replace('\n', "\n", $option);
		$option                = str_replace("¶", "\n", $option); // the paragraph delimiter is utf8-escaped
		$aMultiSecSeparators = explode(',', $option);
	}

	/**
	 * Clean and test 'table' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function tableParameter($option, Options $options) {
		$option   = str_replace('\n', "\n", $option);
		$sTable = str_replace("¶", "\n", $option); // the paragraph delimiter is utf8-escaped
	}

	/**
	 * Clean and test 'tablerow' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function tablerowParameter($option, Options $options) {
		$option = str_replace('\n', "\n", $option);
		$option = str_replace("¶", "\n", $option); // the paragraph delimiter is utf8-escaped
		if (trim($option) == '') {
			$aTableRow = array();
		} else {
			$aTableRow = explode(',', $option);
		}
	}

	/**
	 * Clean and test 'allowcachedresults' parameter.
	 * This function is necessary for the custom 'yes+warn' opton that sets 'warncachedresults'.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function allowcachedresultsParameter($option, Options $options) {
		//If execAndExit was previously set (i.e. if it is not empty) we will ignore all cache settings which are placed AFTER the execandexit statement thus we make sure that the cache will only become invalid if the query is really executed.
		if (!$this->setOptions['execandexit']) {
			if ($option == 'yes+warn') {
				$this->setOptions['allowcachedresults'] = true;
				$this->setOptions['warncachedresults'] = true;
				return true;
			}
			$option = self::filterBoolean($option);
			if ($option !== null) {
				$this->setOptions['allowcachedresults'] = self::filterBoolean($option);
			} else {
				return false;
			}
		}
		return true;
	}

	/**
	 * Clean and test 'dplcache' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function dplcacheParameter($option, Options $options) {
		if ($option != '') {
			$DPLCache     = $parser->mTitle->getArticleID() . '_' . str_replace("/", "_", $option) . '.dplc';
			$DPLCachePath = $parser->mTitle->getArticleID() % 10;
		} else {
			return false;
		}
	}

	/**
	 * Clean and test 'fixcategory' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function fixcategoryParameter($option, Options $options) {
		\DynamicPageListHooks::fixCategory($option);
	}

	/**
	 * Clean and test 'reset' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function resetParameter($option, Options $options) {
		foreach (preg_split('/[;,]/', $option) as $arg) {
			$arg = trim($arg);
			if ($arg == '') {
				continue;
			}
			if (!in_array($arg, $options->getOptions('reset')['values'])) {
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
	}

	/**
	 * Clean and test 'eliminate' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function eliminateParameter($option, Options $options) {
		foreach (preg_split('/[;,]/', $option) as $arg) {
			$arg = trim($arg);
			if ($arg == '') {
				continue;
			}
			if (!in_array($arg, $options->getOptions('eliminate')['values'])) {
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
	}

	/**
	 * Clean and test 'categoryregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function categoryregexpParameter($option, Options $options) {
		$sCategoryComparisonMode      = ' REGEXP ';
		$aIncludeCategories[]         = array(
			$option
		);
		$this->setOpenReferencesConflict(true);
	}

	/**
	 * Clean and test 'categorymatch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function categorymatchParameter($option, Options $options) {
		$sCategoryComparisonMode      = ' LIKE ';
		$aIncludeCategories[]         = explode('|', $option);
		$this->setOpenReferencesConflict(true);
	}

	/**
	 * Clean and test 'notcategoryregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notcategoryregexpParameter($option, Options $options) {
		$sNotCategoryComparisonMode   = ' REGEXP ';
		$aExcludeCategories[]         = $option;
		$this->setOpenReferencesConflict(true);
	}

	/**
	 * Clean and test 'notcategorymatch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function notcategorymatchParameter($option, Options $options) {
		$sNotCategoryComparisonMode   = ' LIKE ';
		$aExcludeCategories[]         = $option;
		$this->setOpenReferencesConflict(true);
	}

	/**
	 * Clean and test 'titleregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function titleregexpParameter($option, Options $options) {
		$sTitleMatchMode         = ' REGEXP ';
		$aTitleMatch             = array(
			$option
		);
		$this->setSelectionCriteriaFound(true);
	}

	/**
	 * Clean and test 'nottitleregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function nottitleregexpParameter($option, Options $options) {
		$sNotTitleMatchMode      = ' REGEXP ';
		$aNotTitleMatch          = array(
			$option
		);
		$this->setSelectionCriteriaFound(true);
	}

	/**
	 * Clean and test 'nottitlematch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function nottitlematchParameter($option, Options $options) {
		// we replace blanks by underscores to meet the internal representation
		// of page names in the database
		$aNotTitleMatch          = explode('|', str_replace(' ', '_', $option));
		$this->setSelectionCriteriaFound(true);
	}

	/**
	 * Clean and test 'lastrevisionbefore' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function lastrevisionbeforeParameter($option, Options $options) {
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
			$this->setOpenReferencesConflict(true);
		} else {
			$output .= $logger->msgWrongParam($parameter, $option);
		}
	}

	/**
	 * Clean and test 'articlecategory' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function articlecategoryParameter($option, Options $options) {
		$sArticleCategory = str_replace(' ', '_', $option);
	}

	/**
	 * Clean and test 'goal' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	mixed	Array of options to enact on or false on error.
	 */
	public function goalParameter($option, Options $options) {
		if (in_array($option, $options->getOptions('goal')['values'])) {
			$sGoal                        = $option;
			$this->setOpenReferencesConflict(true);
		} else {
			return false;
		}
	}
}
?>