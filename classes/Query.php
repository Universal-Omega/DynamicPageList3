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

		//Addauthor can not be used with addlasteditor.
		if ($parameters->getParameter('addauthor') && $sSqlRevisionTable == '') {
			$sSqlRevisionTable = $tableNames['revision'] . ' AS rev, ';
			$sSqlCond_page_rev .= ' AND ' . $tableNames['page'] . '.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MIN(rev_aux_min.rev_timestamp) FROM ' . $tableNames['revision'] . ' AS rev_aux_min WHERE rev_aux_min.rev_page=rev.rev_page )';
		}

		if ($sSqlRevisionTable != '') {
			$sSqlRev_user = ', rev_user, rev_user_text, rev_comment';
		}

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

		if ($bAddCategories) {
			$sSqlCats            = ", GROUP_CONCAT(DISTINCT cl_gc.cl_to ORDER BY cl_gc.cl_to ASC SEPARATOR ' | ') AS cats";
			// Gives list of all categories linked from each article, if any.
			$sSqlClTableForGC    = $tableNames['categorylinks'] . ' AS cl_gc';
			// Categorylinks table used by the Group Concat (GC) function above
			$sSqlCond_page_cl_gc = 'page_id=cl_gc.cl_from';
			if ($sSqlGroupBy != '') {
				$sSqlGroupBy .= ', ';
			}
			$sSqlGroupBy .= $sSqlCl_to . $tableNames['page'] . '.page_id';
		}

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

		if ($bAddContribution) {
			$sSqlRCTable = $tableNames['recentchanges'] . ' AS rc, ';
			$sSqlSelPage .= ', SUM( ABS( rc.rc_new_len - rc.rc_old_len ) ) AS contribution, rc.rc_user_text AS contributor';
			$sSqlWhere .= ' AND page.page_id=rc.rc_cur_id';
			if ($sSqlGroupBy != '') {
				$sSqlGroupBy .= ', ';
			}
			$sSqlGroupBy .= 'rc.rc_cur_id';
		}

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

		$sSqlCl_timestamp = ", DATE_FORMAT(cl0.cl_timestamp, '%Y%m%d%H%i%s') AS cl_timestamp";

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

		//Addlastauthor can not be used with addeditor.
		if ($bAddLastEditor && $sSqlRevisionTable == '') {
			$sSqlRevisionTable = $tableNames['revision'] . ' AS rev, ';
			$sSqlCond_page_rev .= ' AND ' . $tableNames['page'] . '.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MAX(rev_aux_max.rev_timestamp) FROM ' . $tableNames['revision'] . ' AS rev_aux_max WHERE rev_aux_max.rev_page=rev.rev_page )';
		}

		if ($sSqlRevisionTable != '') {
			$sSqlRev_user = ', rev_user, rev_user_text, rev_comment';
		}

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

		$sSqlPage_counter = ", {$tableNames['page']}.page_counter AS page_counter";

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

		$sSqlPage_size = ", {$tableNames['page']}.page_len AS page_len";

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

		//@TODO: Need to check if this was added by the order methods or call this function to add it from there.
		if ($bAddPageTouchedDate && $sSqlPage_touched == '') {
			$sSqlPage_touched = ", {$tableNames['page']}.page_touched AS page_touched";
		}

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


		if ($sSqlRevisionTable != '') {
			$sSqlRev_user = ', rev_user, rev_user_text, rev_comment';
		}

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

		if ($sAllRevisionsBefore != '') {
			$sSqlCond_page_rev .= ' AND ' . $tableNames['page'] . '.page_id=rev.rev_page AND rev.rev_timestamp < ' . $sAllRevisionsBefore;
		}

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

		if ($sAllRevisionsSince != '') {
			$sSqlCond_page_rev .= ' AND ' . $tableNames['page'] . '.page_id=rev.rev_page AND rev.rev_timestamp >= ' . $sAllRevisionsSince;
		}

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

		if (isset($sArticleCategory) && $sArticleCategory !== null) {
			$sSqlWhere .= " AND {$tableNames['page']}.page_title IN (
				SELECT p2.page_title
				FROM {$tableNames['page']} p2
				INNER JOIN {$tableNames['categorylinks']} clstc ON (clstc.cl_from = p2.page_id AND clstc.cl_to = " . self::$DB->addQuotes($sArticleCategory) . " )
				WHERE p2.page_namespace = 0
				) ";
		}

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

		if (isset($aCatMinMax[0]) && $aCatMinMax[0] != '') {
			$sSqlCond_MaxCat .= ' AND ' . $aCatMinMax[0] . ' <= (SELECT count(*) FROM ' . $tableNames['categorylinks'] . ' WHERE ' . $tableNames['categorylinks'] . '.cl_from=page_id)';
		}
		if (isset($aCatMinMax[1]) && $aCatMinMax[1] != '') {
			$sSqlCond_MaxCat .= ' AND ' . $aCatMinMax[1] . ' >= (SELECT count(*) FROM ' . $tableNames['categorylinks'] . ' WHERE ' . $tableNames['categorylinks'] . '.cl_from=page_id)';
		}

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

		$iClTable = 0;
		for ($i = 0; $i < $iIncludeCatCount; $i++) {
			// If we want the Uncategorized
			$sSqlSelectFrom .= ' INNER JOIN ' . (in_array('', $aIncludeCategories[$i]) ? $tableNames['dpl_clview'] : $tableNames['categorylinks']) . ' AS cl' . $iClTable . ' ON ' . $tableNames['page'] . '.page_id=cl' . $iClTable . '.cl_from AND (cl' . $iClTable . '.cl_to' . $sCategoryComparisonMode . self::$DB->addQuotes(str_replace(' ', '_', $aIncludeCategories[$i][0]));
			for ($j = 1; $j < count($aIncludeCategories[$i]); $j++)
				$sSqlSelectFrom .= ' OR cl' . $iClTable . '.cl_to' . $sCategoryComparisonMode . self::$DB->addQuotes(str_replace(' ', '_', $aIncludeCategories[$i][$j]));
			$sSqlSelectFrom .= ') ';
			$iClTable++;
		}

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

		if ($parameters->getParameter('createdby')) {
		    $sSqlCreationRevisionTable = $tableNames['revision'] . ' AS creation_rev, ';
		    $sSqlCond_page_rev .= ' AND ' . self::$DB->addQuotes($parameters->getParameter('createdby')) . ' = creation_rev.rev_user_text' . ' AND creation_rev.rev_page = page_id' . ' AND creation_rev.rev_parent_id = 0';
		}

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

		if ($sFirstRevisionSince != '') {
			$sSqlCond_page_rev .= ' AND ' . $tableNames['page'] . '.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MIN(rev_aux_snc.rev_timestamp) FROM ' . $tableNames['revision'] . ' AS rev_aux_snc WHERE rev_aux_snc.rev_page=rev.rev_page AND rev_aux_snc.rev_timestamp >= ' . $sFirstRevisionSince . ')';
		}

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

		if (count($aImageContainer) > 0) {
			$sSqlPageLinksTable .= $tableNames['imagelinks'] . ' AS ic, ';
			if ($acceptOpenReferences) {
				$sSqlCond_page_pl .= ' AND (';
			} else {
				$sSqlCond_page_pl .= ' AND ' . $tableNames['page'] . '.page_namespace=\'6\' AND ' . $tableNames['page'] . '.page_title=ic.il_to AND (';
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

		if (count($aImageUsed) > 0) {
			$sSqlPageLinksTable .= $tableNames['imagelinks'] . ' AS il, ';
			$sSqlCond_page_pl .= ' AND ' . $tableNames['page'] . '.page_id=il.il_from AND (';
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

	    $sSqlCond_page_rev .= ' AND ' . self::$DB->addQuotes($parameters->getParameter('lastmodifiedby')) . ' = (SELECT rev_user_text FROM ' . $tableNames['revision'] . ' WHERE ' . $tableNames['revision'] . '.rev_page=page_id ORDER BY ' . $tableNames['revision'] . '.rev_timestamp DESC LIMIT 1)';

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

		if ($sLastRevisionBefore != '') {
			$sSqlCond_page_rev .= ' AND ' . $tableNames['page'] . '.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MAX(rev_aux_bef.rev_timestamp) FROM ' . $tableNames['revision'] . ' AS rev_aux_bef WHERE rev_aux_bef.rev_page=rev.rev_page AND rev_aux_bef.rev_timestamp < ' . $sLastRevisionBefore . ')';
		}

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

		$sSqlCond_page_pl .= ' AND ' . $tableNames['page'] . '.page_id NOT IN (SELECT ' . $tableNames['pagelinks'] . '.pl_from FROM ' . $tableNames['pagelinks'] . ' WHERE (';
		$n = 0;
		foreach ($aNotLinksTo as $links) {
			foreach ($links as $link) {
				if ($n > 0) {
					$sSqlCond_page_pl .= ' OR ';
				}
				$sSqlCond_page_pl .= '(' . $tableNames['pagelinks'] . '.pl_namespace=' . intval($link->getNamespace());
				if (strpos($link->getDbKey(), '%') >= 0) {
					$operator = ' LIKE ';
				} else {
					$operator = '=';
				}
				if ($bIgnoreCase) {
					$sSqlCond_page_pl .= ' AND LOWER(CAST(' . $tableNames['pagelinks'] . '.pl_title AS char))' . $operator . 'LOWER(' . self::$DB->addQuotes($link->getDbKey()) . '))';
				} else {
					$sSqlCond_page_pl .= ' AND		 ' . $tableNames['pagelinks'] . '.pl_title' . $operator . self::$DB->addQuotes($link->getDbKey()) . ')';
				}
				$n++;
			}
		}
		$sSqlCond_page_pl .= ') )';

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

		if (count($aLinksTo) > 0) {
			$sSqlPageLinksTable .= $tableNames['pagelinks'] . ' AS pl, ';
			$sSqlCond_page_pl .= ' AND ' . $tableNames['page'] . '.page_id=pl.pl_from AND ';
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
				$sSqlCond_page_pl .= ' AND EXISTS(select pl_from FROM ' . $tableNames['pagelinks'] . ' WHERE (' . $tableNames['pagelinks'] . '.pl_from=page_id AND (';
				foreach ($linkGroup as $link) {
					if (++$m > 1) {
						$sSqlCond_page_pl .= ' OR ';
					}
					$sSqlCond_page_pl .= '(' . $tableNames['pagelinks'] . '.pl_namespace=' . intval($link->getNamespace());
					if (strpos($link->getDbKey(), '%') >= 0) {
						$operator = ' LIKE ';
					} else {
						$operator = '=';
					}
					if ($bIgnoreCase) {
						$sSqlCond_page_pl .= ' AND LOWER(CAST(' . $tableNames['pagelinks'] . '.pl_title AS char))' . $operator . 'LOWER(' . self::$DB->addQuotes($link->getDbKey()) . ')';
					} else {
						$sSqlCond_page_pl .= ' AND ' . $tableNames['pagelinks'] . '.pl_title' . $operator . self::$DB->addQuotes($link->getDbKey());
					}
					$sSqlCond_page_pl .= ')';
				}
				$sSqlCond_page_pl .= ')))';
			}
		}

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

		if (count($aLinksToExternal) > 0) {
			$sSqlExternalLinksTable .= $tableNames['externallinks'] . ' AS el, ';
			$sSqlCond_page_el .= ' AND ' . $tableNames['page'] . '.page_id=el.el_from AND (';
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
				$sSqlCond_page_el .= ' AND EXISTS(SELECT el_from FROM ' . $tableNames['externallinks'] . ' WHERE (' . $tableNames['externallinks'] . '.el_from=page_id AND (';
				foreach ($linkGroup as $link) {
					if (++$m > 1) {
						$sSqlCond_page_el .= ' OR ';
					}
					$sSqlCond_page_el .= '(' . $tableNames['externallinks'] . '.el_to LIKE ' . self::$DB->addQuotes($link) . ')';
				}
				$sSqlCond_page_el .= ')))';
			}
		}

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

		$sSqlWhere .= " AND ((SELECT count(rev_aux3.rev_page) FROM {$tableNames['revision']} AS rev_aux3 WHERE rev_aux3.rev_page=page.page_id) <= $iMaxRevisions)";

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

		if (isset($sMinorEdits) && $sMinorEdits == 'exclude') {
			$sSqlWhere .= ' AND rev_minor_edit=0';
		}

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

		$sSqlWhere .= " AND ((SELECT count(rev_aux2.rev_page) FROM {$tableNames['revision']} AS rev_aux2 WHERE rev_aux2.rev_page=page.page_id) >= $iMinRevisions)";

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

	    $sSqlChangeRevisionTable = $tableNames['revision'] . ' AS change_rev, ';
	    $sSqlCond_page_rev .= ' AND ' . self::$DB->addQuotes($parameters->getParameter('modifiedby')) . ' = change_rev.rev_user_text' . ' AND change_rev.rev_page = page_id';

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

		if (!empty($aNamespaces)) {
			if ($acceptOpenReferences) {
				$sSqlWhere .= ' AND ' . $tableNames['pagelinks'] . '.pl_namespace IN (' . self::$DB->makeList($aNamespaces) . ')';
			} else {
				$sSqlWhere .= ' AND ' . $tableNames['page'] . '.page_namespace IN (' . self::$DB->makeList($aNamespaces) . ')';
			}
		}

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

		//@TODO: The table incremental variable needs to be on the object.
		for ($i = 0; $i < $iExcludeCatCount; $i++) {
			$sSqlSelectFrom .= ' LEFT OUTER JOIN ' . $tableNames['categorylinks'] . ' AS cl' . $iClTable . ' ON ' . $tableNames['page'] . '.page_id=cl' . $iClTable . '.cl_from' . ' AND cl' . $iClTable . '.cl_to' . $sNotCategoryComparisonMode . self::$DB->addQuotes(str_replace(' ', '_', $aExcludeCategories[$i]));
			$sSqlWhere .= ' AND cl' . $iClTable . '.cl_to IS NULL';
			$iClTable++;
		}

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

	    $sSqlNoCreationRevisionTable = $tableNames['revision'] . ' AS no_creation_rev, ';
	    $sSqlCond_page_rev .= ' AND ' . self::$DB->addQuotes($parameters->getParameter('notcreatedby')) . ' != no_creation_rev.rev_user_text' . ' AND no_creation_rev.rev_page = page_id' . ' AND no_creation_rev.rev_parent_id = 0';

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

	    $sSqlCond_page_rev .= ' AND ' . self::$DB->addQuotes($parameters->getParameter('notlastmodifiedby')) . ' != (SELECT rev_user_text FROM ' . $tableNames['revision'] . ' WHERE ' . $tableNames['revision'] . '.rev_page=page_id ORDER BY ' . $tableNames['revision'] . '.rev_timestamp DESC LIMIT 1)';

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
				$sSqlCond_page_pl .= ' AND CONCAT(page_namespace,page_title) NOT IN (SELECT CONCAT(' . $tableNames['pagelinks'] . '.pl_namespace,' . $tableNames['pagelinks'] . '.pl_title) from ' . $tableNames['pagelinks'] . ' WHERE (';
				$n = 0;
				foreach ($aNotLinksFrom as $links) {
					foreach ($links as $link) {
						if ($n > 0) {
							$sSqlCond_page_pl .= ' OR ';
						}
						$sSqlCond_page_pl .= $tableNames['pagelinks'] . '.pl_from=' . $link->getArticleID() . ' ';
						$n++;
					}
				}
				$sSqlCond_page_pl .= '))';
			}
		}

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

	    $sSqlCond_page_rev .= ' AND NOT EXISTS (SELECT 1 FROM ' . $tableNames['revision'] . ' WHERE ' . $tableNames['revision'] . '.rev_page=page_id AND ' . $tableNames['revision'] . '.rev_user_text = ' . self::$DB->addQuotes($parameters->getParameter('notmodifiedby')) . ' LIMIT 1)';

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

		if (!empty($aExcludeNamespaces)) {
			if ($acceptOpenReferences) {
				$sSqlWhere .= ' AND ' . $tableNames['pagelinks'] . '.pl_namespace NOT IN (' . self::$DB->makeList($aExcludeNamespaces) . ')';
			} else {
				$sSqlWhere .= ' AND ' . $tableNames['page'] . '.page_namespace NOT IN (' . self::$DB->makeList($aExcludeNamespaces) . ')';
			}
		}

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
						$sSqlWhere .= 'LOWER(CAST(' . $tableNames['page'] . '.page_title AS char))' . $sNotTitleMatchMode . 'LOWER(' . self::$DB->addQuotes($link) . ')';
					} else {
						$sSqlWhere .= $tableNames['page'] . '.page_title' . $sNotTitleMatchMode . self::$DB->addQuotes($link);
					}
				}
				$n++;
			}
			$sSqlWhere .= ')';
		}

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

		if (count($aNotUses) > 0) {
			$sSqlCond_page_pl .= ' AND ' . $tableNames['page'] . '.page_id NOT IN (SELECT ' . $tableNames['templatelinks'] . '.tl_from FROM ' . $tableNames['templatelinks'] . ' WHERE (';
			$n = 0;
			foreach ($aNotUses as $link) {
				if ($n > 0) {
					$sSqlCond_page_pl .= ' OR ';
				}
				$sSqlCond_page_pl .= '(' . $tableNames['templatelinks'] . '.tl_namespace=' . intval($link->getNamespace());
				if ($bIgnoreCase) {
					$sSqlCond_page_pl .= ' AND LOWER(CAST(' . $tableNames['templatelinks'] . '.tl_title AS char))=LOWER(' . self::$DB->addQuotes($link->getDbKey()) . '))';
				} else {
					$sSqlCond_page_pl .= ' AND ' . $tableNames['templatelinks'] . '.tl_title=' . self::$DB->addQuotes($link->getDbKey()) . ')';
				}
				$n++;
			}
			$sSqlCond_page_pl .= ') )';
		}

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

		if (!$acceptOpenReferences) {
			switch ($sRedirects) {
				case 'only':
					$sSqlWhere .= ' AND ' . $tableNames['page'] . '.page_is_redirect=1';
					break;
				case 'exclude':
					$sSqlWhere .= ' AND ' . $tableNames['page'] . '.page_is_redirect=0';
					break;
			}
		}

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

		if ($sTitleIs != '') {
			if ($bIgnoreCase) {
				$sSqlWhere .= ' AND LOWER(CAST(' . $tableNames['page'] . '.page_title AS char)) = LOWER(' . self::$DB->addQuotes($sTitleIs) . ')';
			} else {
				$sSqlWhere .= ' AND ' . $tableNames['page'] . '.page_title = ' . self::$DB->addQuotes($sTitleIs);
			}
		}

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

		if ($sTitleGE != '') {
			$sSqlWhere .= ' AND (';
			if (substr($sTitleGE, 0, 2) == '=_') {
				if ($acceptOpenReferences) {
					$sSqlWhere .= 'pl_title >=' . self::$DB->addQuotes(substr($sTitleGE, 2));
				} else {
					$sSqlWhere .= $tableNames['page'] . '.page_title >=' . self::$DB->addQuotes(substr($sTitleGE, 2));
				}
			} else {
				if ($acceptOpenReferences) {
					$sSqlWhere .= 'pl_title >' . self::$DB->addQuotes($sTitleGE);
				} else {
					$sSqlWhere .= $tableNames['page'] . '.page_title >' . self::$DB->addQuotes($sTitleGE);
				}
			}
			$sSqlWhere .= ')';
		}

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

		if ($sTitleLE != '') {
			$sSqlWhere .= ' AND (';
			if (substr($sTitleLE, 0, 2) == '=_') {
				if ($acceptOpenReferences) {
					$sSqlWhere .= 'pl_title <=' . self::$DB->addQuotes(substr($sTitleLE, 2));
				} else {
					$sSqlWhere .= $tableNames['page'] . '.page_title <=' . self::$DB->addQuotes(substr($sTitleLE, 2));
				}
			} else {
				if ($acceptOpenReferences) {
					$sSqlWhere .= 'pl_title <' . self::$DB->addQuotes($sTitleLE);
				} else {
					$sSqlWhere .= $tableNames['page'] . '.page_title <' . self::$DB->addQuotes($sTitleLE);
				}
			}
			$sSqlWhere .= ')';
		}

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
						$sSqlWhere .= 'LOWER(CAST(' . $tableNames['page'] . '.page_title AS char))' . $sTitleMatchMode . strtolower(self::$DB->addQuotes($link));
					} else {
						$sSqlWhere .= $tableNames['page'] . '.page_title' . $sTitleMatchMode . self::$DB->addQuotes($link);
					}
				}
				$n++;
			}
			$sSqlWhere .= ')';
		}

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
				$sSqlPageLinksTable .= $tableNames['templatelinks'] . ' AS tpl, ' . $tableNames['page'] . 'AS tplsrc, ';
				$sSqlCond_page_tpl .= ' AND ' . $tableNames['page'] . '.page_title = tpl.tl_title  AND tplsrc.page_id=tpl.tl_from AND (';
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

		if (count($aUses) > 0) {
			$sSqlPageLinksTable .= ' ' . $tableNames['templatelinks'] . ' as tl, ';
			$sSqlCond_page_pl .= ' AND ' . $tableNames['page'] . '.page_id=tl.tl_from  AND (';
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

		return $query;
	}
}
?>