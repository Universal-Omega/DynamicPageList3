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

class Parameters extends ParametersData {
	/**
	 * Set parameter options.
	 *
	 * @var		array
	 */
	private $parameterOptions = [];

	/**
	 * Selection Criteria Found
	 *
	 * @var		boolean
	 */
	private $selectionCriteriaFound = false;

	/**
	 * Open References Conflict
	 *
	 * @var		boolean
	 */
	private $openReferencesConflict = false;

	/**
	 * Parameters that have already been processed.
	 *
	 * @var		array
	 */
	private $parametersProcessed = [];

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @return	void
	 */
	public function __construct() {
		parent::__construct();
		$this->setDefaults();
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
		$parameterData = $this->getData($parameter);

		//Check permission to use this parameter.
		if (array_key_exists('permission', $parameterData)) {
			global $wgUser;
			if (!$wgUser->isAllowed($parameterData['permission'])) {
				throw new PermissionsError($parameterData['permission']);
				return;
			}
		}

		//Subvert to the real function if it exists.  This keeps code elsewhere clean from needed to check if it exists first.
		$function = "_".$parameter;
		$this->parametersProcessed[$parameter] = true;
		if (method_exists($this, $function)) {
			return call_user_func_array($this->$function, $arguments);
		}
		$option = $arguments[0];
		$parameter = strtolower($parameter);

		//Assume by default that these simple parameter options should not failed, but if they do we will set $success to false below.
		$success = true;
		if ($parameterData !== false) {
			//If a parameter specifies options then enforce them.
			if (is_array($parameterData['values']) === true && !in_array($option, $parameterData['values'])) {
				$success = false;
			}

			//Strip <html> tag.
			if ($parameterData['strip_html'] === true) {
				$option = self::stripHtmlTags($option);
			}

			//Simple integer intval().
			if ($parameterData['integer'] === true) {
				if (!is_numeric($option)) {
					if ($parameterData['default'] !== null) {
						$option = intval($parameterData['default']);
					} else {
						$success = false;
					}
				} else {
					$option = intval($option);
				}
			}

			//Booleans
			if ($parameterData['boolean'] === true) {
				$option = $this->filterBoolean($option);
				if ($option === null) {
					$success = false;
				}
			}

			//Timestamps
			if ($parameterData['timestamp'] === true) {
				$option = wfTimestamp(TS_MW, $option);
				if ($option === false) {
					$success = false;
				}
			}

			//List of Pages
			if ($parameterData['page_name_list'] === true) {
				$option = $this->getPageNameList($option, (bool) $parameterData['page_name_must_exist']);
				if ($option === false) {
					$success = false;
				}
			}

			//Regex Pattern Matching
			if (array_key_exists('pattern', $parameterData)) {
				if (preg_match($parameterData['pattern'], $option, $matches)) {
					//Nuke the total pattern match off the beginning of the array.
					array_shift($matches);
					$option = $matches;
				} else {
					$success = false;
				}
			}

			//Database Key Formatting
			if ($parameterData['db_format'] === true) {
				$option = str_replace(' ', '_', $option);
			}

			//If none of the above checks marked this as a failure then set it.
			if ($success === true) {
				$this->setParameter($parameter, $option);

				//Set that criteria was found for a selection.
				if ($parameterData['set_criteria_found'] === true) {
					$this->setSelectionCriteriaFound(true);
				}

				//Set open references conflict possibility.
				if ($parameterData['open_ref_conflict'] === true) {
					$this->setOpenReferencesConflict(true);
				}
			}
		}
		return $success;
	}

	/**
	 * Set Selection Criteria Found
	 *
	 * @access	public
	 * @param	boolean	Is Found?
	 * @return	void
	 */
	private function setSelectionCriteriaFound($found = true) {
		if (!is_bool($conflict)) {
			throw new MWException(__METHOD__.': A non-boolean was passed.');
		}
		$this->selectionCriteriaFound = $found;
	}

	/**
	 * Get Selection Criteria Found
	 *
	 * @access	public
	 * @return	boolean	Is Conflict?
	 */
	public function isSelectionCriteriaFound() {
		return $this->selectionCriteriaFound;
	}

	/**
	 * Set Open References Conflict - See 'openreferences' parameter.
	 *
	 * @access	public
	 * @param	boolean	References Conflict?
	 * @return	void
	 */
	private function setOpenReferencesConflict($conflict = true) {
		if (!is_bool($conflict)) {
			throw new MWException(__METHOD__.': A non-boolean was passed.');
		}
		$this->openReferencesConflict = $conflict;
		$this->setParameter('openreferences', false);
	}

	/**
	 * Get Open References Conflict - See 'openreferences' parameter.
	 *
	 * @access	public
	 * @return	boolean	Is Conflict?
	 */
	public function isOpenReferencesConflict() {
		return $this->openReferencesConflict;
	}

	/**
	 * Set default parameters based on ParametersData.
	 *
	 * @access	private
	 * @return	void
	 */
	private function setDefaults() {
		$parameters = $this->getParametersForRichness();
		foreach ($parameters as $parameter) {
			if ($this->getData($parameter)['default'] !== null) {
				$this->setParameter($parameter, $this->getData($parameter)['default']);
			}
		}
	}

	/**
	 * Set a parameter's option.
	 *
	 * @access	public
	 * @param	string	Parameter to set
	 * @param	mixed	Option to set
	 * @return	void
	 */
	public function setParameter($parameter, $option) {
		$this->parameterOptions[$parameter] = $option;
	}

	/**
	 * Get a parameter's option.
	 *
	 * @access	public
	 * @param	string	Parameter to get
	 * @return	mixed	Option for specified parameter.
	 */
	public function getParameter($parameter) {
		return $this->parameterOptions[$parameter];
	}

	/**
	 * Get all parameters.
	 *
	 * @access	public
	 * @return	array	Parameter => Options
	 */
	public function getAllParameters() {
		return $this->parameterOptions;
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
	 * Get a list of valid page names.
	 *
	 * @access	private
	 * @param	string	Raw Text of Pages
	 * @param	boolean	[Optional] Each Title MUST Exist
	 * @return	mixed	List of page titles or false on error.
	 */
	private function getPageNameList($text, $mustExist = true) {
		$list = [];
		$pages = explode('|', trim($text));
		foreach ($pages as $page) {
			$page = trim($page);
			$page = rtrim($page, '\\'); //This was fixed from the original code, but I am not sure what its intended purpose was.
			if (empty($page)) {
				continue;
			}
			if ($mustExist === true) {
				$title = \Title::newFromText($page);
				if (!$title) {
					return false;
				}
				$list[] = $title;
			} else {
				$list[] = $page;
			}
		}

		return $list;
	}

	/**
	 * Clean and test 'category' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _category($option) {
		$option = trim($option);
		if (empty($option)) {
			return false;
		}
		// Init array of categories to include
		$categories = [];
		$heading    = false;
		$notHeading = false;
		if (substr($option, 0, 1) == '+') { // categories are headings
			$heading = true;
			$option = ltrim($option, '+');
		}
		if (substr($option, 0, 1) == '-') { // categories are NOT headings
			$notHeading = true;
			$option = ltrim($option, '-');
		}

		$operator = 'OR';
		//We expand html entities because they contain an '& 'which would be interpreted as an AND condition
		$option = html_entity_decode($option, ENT_QUOTES);
		if (strpos($option, '&') !== false) {
			$parameters = explode('&', $option);
			$operator = 'AND';
		} else {
			$parameters = explode('|', $option);
		}
		foreach ($parameters as $parameter) {
			$parameter = trim($parameter);
			if ($parameter == '_none_' || $parameter === '') {
				$parameters[$parameter] = '';
				$bIncludeUncat    = true;
				$categories[]    = '';
			} elseif (!empty($parameter)) {
				if (substr($parameter, 0, 1) == '*' && strlen($parameter) >= 2) {
					if (substr($parameter, 1, 2) == '*') {
						$subCategories = explode('|', self::getSubcategories(substr($parameter, 2), 2));
					} else {
						$subCategories = explode('|', self::getSubcategories(substr($parameter, 1), 1));
					}
					foreach ($subCategories as $subCategory) {
						$title = \Title::newFromText($subCategory);
						if (!is_null($title)) {
							$categories[] = $title->getDbKey();
						}
					}
				} else {
					$title = \Title::newFromText($parameter);
					if (!is_null($title)) {
						$categories[] = $title->getDbKey();
					}
				}
			}
		}
		if (!empty($categories)) {
			$data = $this->getParameter('includecategories');
			if (!is_array($data[$operator])) {
				$data[$operator] = [];
			}
			$data[$operator] = array_merge($data[$operator], $categories);
			$this->setParameter('includecategories', $data);
			if ($heading) {
				$this->setParameter('catheadings', array_unique($this->getParameter('catheadings') + $categories));
			}
			if ($notHeading) {
				$this->setParameter('catnotheadings', array_unique($this->getParameter('catnotheadings') + $categories));
			}
			$this->setOpenReferencesConflict(true);
			return true;
		}
		return false;
	}

	/**
	 * Clean and test 'categoryregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _categoryregexp($option) {
		$data = $this->getParameter('includecategories');
		if (!is_array($data['regexp'])) {
			$data['regexp'] = [];
		}
		$data['regexp'][] = $option;
		$this->setParameter('includecategories', $data);
		$this->setOpenReferencesConflict(true);
		return true;
	}

	/**
	 * Clean and test 'categorymatch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _categorymatch($option) {
		$data = $this->getParameter('includecategories');
		if (!is_array($data['like'])) {
			$data['like'] = [];
		}
		$newMatches = explode('|', $option);
		$data['like'] = array_merge($data['like'], $newMatches);
		$this->setParameter('includecategories', $data);
		$this->setOpenReferencesConflict(true);
		return true;
	}

	/**
	 * Clean and test 'notcategory' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _notcategory($option) {
		$title = \Title::newFromText($option);
		if (!is_null($title)) {
			$data = $this->getParameter('excludecategories');
			$data['='][] = $title->getDbKey();
			$this->setParameter('excludecategories', $data);
			$this->setOpenReferencesConflict(true);
			return true;
		}
		return false;
	}

	/**
	 * Clean and test 'notcategoryregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _notcategoryregexp($option) {
		$data = $this->getParameter('excludecategories');
		$data['regexp'][] = $option;
		$this->setParameter('excludecategories', $data);
		$this->setOpenReferencesConflict(true);
		return true;
	}

	/**
	 * Clean and test 'notcategorymatch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _notcategorymatch($option) {
		$data = $this->getParameter('excludecategories');
		$data['like'][] = $option;
		$this->setParameter('excludecategories', $data);
		$this->setOpenReferencesConflict(true);
		return true;
	}

	/**
	 * Clean and test 'count' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _count($option) {
		if (!Config::getSetting('allowUnlimitedResults') && $option <= Config::getSetting('maxResultCount') && $option > 0) {
			$this->setParameter('count', intval($option));
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
	public function _namespace($option) {
		global $wgContLang;
		$extraParams = explode('|', $option);
		foreach ($extraParams as $parameter) {
			$parameter = trim($parameter);
			$namespaceId = $wgContLang->getNsIndex($parameter);
			if ($namespaceId === false || (is_array(Config::getSetting('allowedNamespaces')) && !in_array($parameter, Config::getSetting('allowedNamespaces')))) {
				//Let the user know this namespace is not allowed or does not exist.
				return false;
			}
			$data = $this->getParameter('namespace');
			$data[] = $parameter;
			$data = array_unique($data);
			$this->setParameter('namespaces', $data);
			$this->setSelectionCriteriaFound(true);
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
	public function _notnamespace($option) {
		global $wgContLang;
		$extraParams = explode('|', $option);
		foreach ($extraParams as $parameter) {
			$parameter = trim($parameter);
			$namespaceId = $wgContLang->getNsIndex($parameter);
			//@TODO: I am not entirely sure why the namespace needs to be allowed to be NOT included, but this is how the original code worked.
			if ($namespaceId === false || (is_array(Config::getSetting('allowedNamespaces')) && !in_array($parameter, Config::getSetting('allowedNamespaces')))) {
				//Let the user know this namespace is not allowed or does not exist.
				return false;
			}
			$data = $this->getParameter('notnamespaces');
			$data[] = $parameter;
			$data = array_unique($data);
			$this->setParameter('notnamespaces', $data);
			$this->setSelectionCriteriaFound(true);
		}
		return true;
	}

	/**
	 * Clean and test 'ordermethods' parameter.(NOTE THE PLURAL 'S')
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _ordermethod($option) {
		$methods   = explode(',', $option);
		$success = true;
		foreach ($methods as $method) {
			if (!in_array($method, $this->getData('ordermethod')['values'])) {
				$success = false;
			}
		}
		if ($success === true) {
			$this->setParameter('ordermethods', $methods);
			if ($methods[0] != 'none') {
				$this->setOpenReferencesConflict(true);
			}
		}
		return true;
	}

	/**
	 * Clean and test 'mode' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _mode($option) {
		if (in_array($option, $this->getData('mode')['values'])) {
			//'none' mode is implemented as a specific submode of 'inline' with <br/> as inline text
			if ($option == 'none') {
				$this->setParameter('pagelistmode', 'inline');
				$this->setParameter('inltxt', '<br/>');
			} else if ($option == 'userformat') {
				// userformat resets inline text to empty string
				$this->setParameter('inltxt', '');
				$this->setParameter('pagelistmode', $option);
			} else {
				$this->setParameter('pagelistmode', $option);
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
	 * @return	boolean	Success
	 */
	public function _distinct($option) {
		$boolean = $this->filterBoolean($option);
		if ($option == 'strict') {
			$this->setParameter('distinctresultset', 'strict');
		} elseif ($boolean !== null) {
			$this->setParameter('distinctresultset', $boolean);
		} else {
			return false;
		}
	}

	/**
	 * Clean and test 'ordercollation' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _ordercollation($option) {
		if ($option == 'bridge') {
			$this->setParameter('ordersuitsymbols', true);
		} elseif (!empty($option)) {
			$this->setParameter('ordercollation', "COLLATE ".self::$DB->strencode($option));
		}
	}

	/**
	 * Short cut to formatParameter();
	 *
	 * @access	public
	 * @return	mixed
	 */
	public function _listseparators() {
		return call_user_func_array([$this, 'formatParameter'], func_get_args());
	}

	/**
	 * Clean and test 'format' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _format($option) {
		// parsing of wikitext will happen at the end of the output phase
		// we replace '\n' in the input by linefeed because wiki syntax depends on linefeeds
		$option            = self::stripHtmlTags($option);
		$option            = str_replace(['\n', "¶"], "\n", $option);
		$this->setParameter('listseparators', explode(',', $option, 4));
		// mode=userformat will be automatically assumed
		$this->setParameter('pagelistmode', 'userformat');
		$this->setParameter('inltxt', '');
	}

	/**
	 * Clean and test 'title' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _title($option) {
		$title = \Title::newFromText($option);
		if ($title) {
			$data = $this->getParameter('title');
			$data['='][] = str_replace(' ', '_', $title->getText());
			$this->setParameter('title', $data);
			$this->setParameter('namespaces', array_merge($this->getParameter('namespaces'), $title->getNamespace()));
			$this->setParameter('pagelistmode', 'userformat');
			$this->setParameter('ordermethods', []);
			$this->setParameter('selectioncriteriafound', true);
			$this->setParameter('allowcachedresults', true);
			$this->setOpenReferencesConflict(true);
		}
	}

	/**
	 * Clean and test 'titleregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _titleregexp($option) {
		$data = $this->getParameter('title');
		if (!is_array($data['regexp'])) {
			$data['regexp'] = [];
		}
		$newMatches = explode('|', str_replace(' ', '\_', $option));
		$data['regexp'] = array_merge($data['regexp'], $newMatches);
		$this->setParameter('title', $data);
		$this->setSelectionCriteriaFound(true);
		return true;
	}

	/**
	 * Clean and test 'titlematch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _titlematch($option) {
		$data = $this->getParameter('title');
		if (!is_array($data['like'])) {
			$data['like'] = [];
		}
		$newMatches = explode('|', str_replace(' ', '\_', $option));
		$data['like'] = array_merge($data['like'], $newMatches);
		$this->setParameter('title', $data);
		$this->setSelectionCriteriaFound(true);
		return true;
	}

	/**
	 * Clean and test 'nottitleregexp' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _nottitleregexp($option) {
		$data = $this->getParameter('nottitle');
		if (!is_array($data['regexp'])) {
			$data['regexp'] = [];
		}
		$newMatches = explode('|', str_replace(' ', '\_', $option));
		$data['regexp'] = array_merge($data['regexp'], $newMatches);
		$this->setParameter('nottitle', $data);
		$this->setSelectionCriteriaFound(true);
		return true;
	}

	/**
	 * Clean and test 'nottitlematch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _nottitlematch($option) {
		$data = $this->getParameter('nottitle');
		if (!is_array($data['like'])) {
			$data['like'] = [];
		}
		$newMatches = explode('|', str_replace(' ', '\_', $option));
		$data['like'] = array_merge($data['like'], $newMatches);
		$this->setParameter('nottitle', $data);
		$this->setSelectionCriteriaFound(true);
		return true;
	}

	/**
	 * Clean and test 'scroll' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _scroll($option) {
		$option = $this->filterBoolean($option);
		$this->setParameter('scroll', $option);
		//If scrolling is active we adjust the values for certain other parameters based on URL arguments
		if ($option === true) {
			global $wgRequest;

			//The 'findTitle' option has argument over the 'fromTitle' argument.
			$titlegt = $wgRequest->getVal('DPL_findTitle', '');
			if (!empty($titlegt)) {
				$titlegt = '=_'.ucfirst($titlegt);
			} else {
				$titlegt = $wgRequest->getVal('DPL_fromTitle', '');
				$titlegt = ucfirst($titlegt);
			}
			$this->setParameter('titlegt', str_replace(' ', '_', $titlegt));

			//Lets get the 'toTitle' argument.
			$titlelt = $wgRequest->getVal('DPL_toTitle', '');
			$titlelt = ucfirst($titlelt);
			$this->setParameter('titlelt', str_replace(' ', '_', $titlelt));

			//Make sure the 'scrollDir' arugment is captured.  This is mainly used for the Variables extension and in the header/footer replacements.
			$this->setParameter('scrolldir', $wgRequest->getVal('DPL_scrollDir', ''));

			//Also set count limit from URL if not otherwise set.
			$this->setParameter('scroll', $wgRequest->getVal('DPL_count', ''));
		}
		//We do not return false since they could have just left it out.  Who knows why they put the parameter in the list in the first place.
		return true;
	}

	/**
	 * Clean and test 'replaceintitle' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _replaceintitle($option) {
		//We offer a possibility to replace some part of the title
		$replaceInTitle = explode(',', $option, 2);
		if (isset($replaceInTitle[1])) {
			$replaceInTitle[1] = self::stripHtmlTags($replaceInTitle[1]);
		}

		$this->setParameter('replaceintitle', $replaceInTitle);

		return true;
	}

	/**
	 * Clean and test 'debug' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _debug($option) {
		if (in_array($option, $this->getData('debug')['values'])) {
			if ($key > 1) {
				$output .= $logger->escapeMsg(\DynamicPageListHooks::WARN_DEBUGPARAMNOTFIRST, $option);
			}
			$logger->iDebugLevel = intval($option);
		} else {
			return false;
		}

		return true;
	}

	/**
	 * Short cut to includeParameter();
	 *
	 * @access	public
	 * @return	mixed
	 */
	public function _includepage() {
		return call_user_func_array([$this, 'includeParameter'], func_get_args());
	}

	/**
	 * Clean and test 'include' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _include($option) {
		if (!empty($option)) {
			$this->setParameter('incpage', true);
			$this->setParameter('seclabels', explode(',', $option));
		} else {
			return false;
		}
		return true;
	}

	/**
	 * Clean and test 'includematch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _includematch($option) {
		$this->setParameter('seclabelsmatch', explode(',', $option));
		return true;
	}

	/**
	 * Clean and test 'includematchparsed' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _includematchparsed($option) {
		$this->setParameter('incparsed', true);
		$this->setParameter('seclabelsmatch', explode(',', $option));
		return true;
	}

	/**
	 * Clean and test 'includenotmatch' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _includenotmatch($option) {
		$this->setParameter('seclabelsnotmatch', explode(',', $option));
		return true;
	}

	/**
	 * Clean and test 'includenotmatchparsed' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _includenotmatchparsed($option) {
		$this->setParameter('incparsed', true);
		$this->setParameter('seclabelsnotmatch', explode(',', $option));
		return true;
	}

	/**
	 * Clean and test 'secseparators' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _secseparators($option) {
		//We replace '\n' by newline to support wiki syntax within the section separators
		$this->setParameter('secseparators', explode(',', str_replace(['\n', "¶"], "\n", $option)));
		return true;
	}

	/**
	 * Clean and test 'multisecseparators' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _multisecseparators($option) {
		//We replace '\n' by newline to support wiki syntax within the section separators
		$this->setParameter('multisecseparators', explode(',', str_replace(['\n', "¶"], "\n", $option)));
		return true;
	}

	/**
	 * Clean and test 'openreferences' parameter.
	 * This boolean is custom handled due to the open references conflict flag.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _openreferences($option) {
		$option = $this->filterBoolean($option);
		if ($option === null) {
			return false;
		}
		if (!$this->isOpenReferencesConflict()) {
			$this->setParameter('openreferences', $option);
		} else {
			$this->setParameter('openreferences', false);
		}
		return true;
	}

	/**
	 * Clean and test 'table' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _table($option) {
		$defaultTemplateSuffix = '';
		$sPageListMode         = 'userformat';
		$sInlTxt               = '';
		$withHLink             = "[[%PAGE%|%TITLE%]]\n|";

		foreach (explode(',', $option) as $tabnr => $tab) {
			if ($tabnr == 0) {
				if ($tab == '') {
					$tab = 'class=wikitable';
				}
				$listSeparators[0] = '{|' . $tab;
			} else {
				if ($tabnr == 1 && $tab == '-') {
					$withHLink = '';
					continue;
				}
				if ($tabnr == 1 && $tab == '') {
					$tab = wfMessage('article')->text();
				}
				$listSeparators[0] .= "\n!{$tab}";
			}
		}
		$listSeparators[1] = '';
		// the user may have specified the third parameter of 'format' to add meta attributes of articles to the table
		if (!array_key_exists(2, $listSeparators)) {
			$listSeparators[2] = '';
		}
		$listSeparators[3] = "\n|}";
		//Overwrite 'listseparators'.
		$this->parameters->setParameter('listseparators', $listSeparators);

		//@TODO: Fixed up things past here.
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

		$this->setParameter('table', str_replace(['\n', "¶"], "\n", $option));
		return true;
	}

	/**
	 * Clean and test 'tablerow' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _tablerow($option) {
		$option = str_replace(['\n', "¶"], "\n", trim($option));
		if (empty($option)) {
			$this->setParameter('tablerow', []);
		} else {
			$this->setParameter('tablerow', explode(',', $option));
		}
		return true;
	}

	/**
	 * Clean and test 'allowcachedresults' parameter.
	 * This function is necessary for the custom 'yes+warn' opton that sets 'warncachedresults'.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _allowcachedresults($option) {
		//If execAndExit was previously set (i.e. if it is not empty) we will ignore all cache settings which are placed AFTER the execandexit statement thus we make sure that the cache will only become invalid if the query is really executed.
		if (!$this->getParameter('execandexit')) {
			if ($option == 'yes+warn') {
				$this->setParameter('allowcachedresults', true);
				$this->setParameter('warncachedresults', true);
				return true;
			}
			$option = $this->filterBoolean($option);
			if ($option !== null) {
				$this->setParameter('allowcachedresults', $this->filterBoolean($option));
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
	 * @return	boolean	Success
	 */
	public function _dplcache($option) {
		if ($option != '') {
			$DPLCache     = $parser->mTitle->getArticleID() . '_' . str_replace("/", "_", $option) . '.dplc';
			$DPLCachePath = $parser->mTitle->getArticleID() % 10;
		} else {
			return false;
		}
		return true;
	}

	/**
	 * Clean and test 'fixcategory' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _fixcategory($option) {
		\DynamicPageListHooks::fixCategory($option);
		return true;
	}

	/**
	 * Clean and test 'reset' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _reset($option) {
		foreach (preg_split('/[;,]/', $option) as $arg) {
			$arg = trim($arg);
			if (empty($arg)) {
				continue;
			}
			if (!in_array($arg, $this->getData('reset')['values'])) {
				return false;
			} elseif ($arg == 'links') {
				$this->setOption['reset'][0] = true;
			} elseif ($arg == 'templates') {
				$this->setOption['reset'][1] = true;
			} elseif ($arg == 'categories') {
				$this->setOption['reset'][2] = true;
			} elseif ($arg == 'images') {
				$this->setOption['reset'][3] = true;
			} elseif ($arg == 'all') {
				$this->setOption['reset'][0] = true;
				$this->setOption['reset'][1] = true;
				$this->setOption['reset'][2] = true;
				$this->setOption['reset'][3] = true;
			}
		}
	}

	/**
	 * Clean and test 'eliminate' parameter.
	 *
	 * @access	public
	 * @param	string	Options passed to parameter.
	 * @return	boolean	Success
	 */
	public function _eliminate($option) {
		foreach (preg_split('/[;,]/', $option) as $arg) {
			$arg = trim($arg);
			if (empty($arg)) {
				continue;
			}
			if (!in_array($arg, $this->getData('eliminate')['values'])) {
				return false;
			} elseif ($arg == 'links') {
				$this->setOption['reset'][4] = true;
			} elseif ($arg == 'templates') {
				$this->setOption['reset'][5] = true;
			} elseif ($arg == 'categories') {
				$this->setOption['reset'][6] = true;
			} elseif ($arg == 'images') {
				$this->setOption['reset'][7] = true;
			} elseif ($arg == 'all') {
				$this->setOption['reset'][4] = true;
				$this->setOption['reset'][5] = true;
				$this->setOption['reset'][6] = true;
				$this->setOption['reset'][7] = true;
			} elseif ($arg == 'none') {
				$this->setOption['reset'][4] = false;
				$this->setOption['reset'][5] = false;
				$this->setOption['reset'][6] = false;
				$this->setOption['reset'][7] = false;
			}
		}
	}
}
?>