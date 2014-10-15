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

class Query {
	/**
	 * Parameters Object
	 *
	 * @var		object
	 */
	private $parameters;

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @param	object	Parameters
	 * @return	void
	 */
	public function __construct(Parameters $parameters) {
		$this->parameters = $parameters;
	}

	/**
	 * Start a query build.
	 *
	 * @access	public
	 * @return	void
	 */
	public function build() {
		$parameters = $this->parameters->getAllParameters();
		foreach ($parameters as $parameter => $option) {
			$function = "_".$parameter;
			$query = $this->$function($option);
		}
	}

	/**
	 * Return SQL for 'addauthor' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _addauthor($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'addcategories' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _addcategories($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'addcontribution' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _addcontribution($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'addeditdate' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _addeditdate($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'addexternallink' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _addexternallink($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'addfirstcategorydate' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _addfirstcategorydate($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'addlasteditor' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _addlasteditor($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'addpagecounter' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _addpagecounter($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'addpagesize' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _addpagesize($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'addpagetoucheddate' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _addpagetoucheddate($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'adduser' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _adduser($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'allowcachedresults' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _allowcachedresults($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'allrevisionsbefore' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _allrevisionsbefore($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'allrevisionssince' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _allrevisionssince($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'articlecategory' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _articlecategory($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'categoriesminmax' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _categoriesminmax($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'category' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _category($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'categorymatch' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _categorymatch($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'categoryregexp' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _categoryregexp($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'columns' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _columns($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'count' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _count($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'createdby' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _createdby($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'debug' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _debug($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'deleterules' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _deleterules($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'distinct' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _distinct($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'dominantsection' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _dominantsection($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'dplcache' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _dplcache($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'dplcacheperiod' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _dplcacheperiod($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'eliminate' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _eliminate($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'escapelinks' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _escapelinks($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'execandexit' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _execandexit($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'firstrevisionsince' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _firstrevisionsince($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'fixcategory' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _fixcategory($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'format' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _format($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'goal' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _goal($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'headingcount' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _headingcount($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'headingmode' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _headingmode($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'hiddencategories' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _hiddencategories($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'hitemattr' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _hitemattr($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'hlistattr' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _hlistattr($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'ignorecase' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _ignorecase($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'imagecontainer' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _imagecontainer($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'imageused' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _imageused($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'include' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _include($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'includematch' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _includematch($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'includematchparsed' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _includematchparsed($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'includemaxlength' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _includemaxlength($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'includenotmatch' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _includenotmatch($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'includenotmatchparsed' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _includenotmatchparsed($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'includepage' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _includepage($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'includesubpages' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _includesubpages($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'includetrim' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _includetrim($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'inlinetext' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _inlinetext($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'itemattr' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _itemattr($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'lastmodifiedby' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _lastmodifiedby($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'lastrevisionbefore' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _lastrevisionbefore($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'linksfrom' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _linksfrom($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'linksto' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _linksto($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'linkstoexternal' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _linkstoexternal($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'listattr' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _listattr($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'listseparators' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _listseparators($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'maxrevisions' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _maxrevisions($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'minoredits' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _minoredits($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'minrevisions' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _minrevisions($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'mode' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _mode($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'modifiedby' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _modifiedby($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'multisecseparators' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _multisecseparators($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'namespace' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _namespace($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'noresultsfooter' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _noresultsfooter($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'noresultsheader' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _noresultsheader($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'notcategory' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _notcategory($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'notcategorymatch' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _notcategorymatch($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'notcategoryregexp' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _notcategoryregexp($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'notcreatedby' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _notcreatedby($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'notlastmodifiedby' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _notlastmodifiedby($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'notlinksfrom' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _notlinksfrom($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'notlinksto' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _notlinksto($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'notmodifiedby' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _notmodifiedby($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'notnamespace' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _notnamespace($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'nottitlematch' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _nottitlematch($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'nottitleregexp' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _nottitleregexp($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'notuses' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _notuses($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'offset' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _offset($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'oneresultfooter' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _oneresultfooter($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'oneresultheader' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _oneresultheader($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'openreferences' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _openreferences($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'order' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _order($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'ordercollation' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _ordercollation($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'ordermethod' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _ordermethod($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'qualitypages' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _qualitypages($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'randomcount' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _randomcount($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'redirects' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _redirects($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'replaceintitle' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _replaceintitle($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'reset' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _reset($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'resultsfooter' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _resultsfooter($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'resultsheader' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _resultsheader($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'rowcolformat' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _rowcolformat($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'rows' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _rows($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'rowsize' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _rowsize($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'scroll' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _scroll($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'secseparators' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _secseparators($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'showcurid' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _showcurid($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'shownamespace' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _shownamespace($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'skipthispage' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _skipthispage($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'stablepages' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _stablepages($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'suppresserrors' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _suppresserrors($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'table' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _table($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'tablerow' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _tablerow($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'tablesortcol' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _tablesortcol($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'title' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _title($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'titlegt' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _titlegt($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'titlelt' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _titlelt($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'titlematch' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _titlematch($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'titlemaxlength' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _titlemaxlength($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'titleregexp' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _titleregexp($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'updaterules' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _updaterules($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'usedby' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _usedby($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'userdateformat' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _userdateformat($option) {
		$query = [];

		return $query;
	}

	/**
	 * Return SQL for 'uses' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	mixed	Array of values to mix into the query or false on error.
	 */
	public function _uses($option) {
		$query = [];

		return $query;
	}
}
?>