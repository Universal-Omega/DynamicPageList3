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
	 * Mediawiki DB Object
	 *
	 * @var		object
	 */
	private $DB;

	/**
	 * Array of prefixed and escaped table names.
	 *
	 * @var		array
	 */
	private $this->tableNames = [];

	/**
	 * Parameters that have already been processed.
	 *
	 * @var		array
	 */
	private $parametersProcessed = [];

	/**
	 * Prefixed and escaped table names.
	 *
	 * @var		array
	 */
	private $this->tables = [];

	/**
	 * Where Clauses
	 *
	 * @var		array
	 */
	private $this->where = [];

	/**
	 * Select Fields
	 *
	 * @var		array
	 */
	private $this->select = [];

	/**
	 * Main Constructor
	 *
	 * @access	public
	 * @param	object	Parameters
	 * @return	void
	 */
	public function __construct(Parameters $parameters) {
		$this->parameters = $parameters;

		$this->tableNames = self::getTableNames();

		$this->DB = wfGetDB(DB_SLAVE);
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
			//Some parameters do not modifiy the query so we check if the function to modify the query exists first.
			if (method_exists($this, $function)) {
				$query = $this->$function($option);
			}
			$this->parametersProcessed[$parameter] = true;
		}
	}

	/**
	 * Return prefixed and quoted tables that are needed.
	 *
	 * @access	private
	 * @return	array	Prepared table names.
	 */
	static private function getTableNames() {
		$tables = [
			'categorylinks',
			'dpl_clview',
			'externallinks',
			'flaggedpages',
			'imagelinks',
			'page',
			'pagelinks',
			'recentchanges',
			'revision',
			'templatelinks'
		];
		foreach ($tables as $table) {
			$tableNames[$table] = $this->DB->tableName($table);
		}
		return $tableNames;
	}

	/**
	 * Add a table to the output.
	 *
	 * @access	public
	 * @param	string	Raw Table Name - Will be ran through tableName().
	 * @param	string	Table Alias
	 * @return	boolean	Success - Added, false if the table alias already exists.
	 */
	public function addTable($table, $alias) {
		if (empty($table)) {
			throw new MWException(__METHOD__.': An empty table name was passed.');
		}
		if (empty($alias) || is_numeric($alias)) {
			throw new MWException(__METHOD__.': An empty or numeric table alias was passed.');
		}
		if (!array_key_exists($alias, $this->tables)) {
			$this->tables[$alias] = $this->DB->tableName($table);
			return false;
		} else {
			return false;
		}
	}

	/**
	 * Add a where clause to the output.
	 * Where clauses get imploded together with AND at the end.  Any custom where clauses should be preformed before placed into here.
	 *
	 * @access	public
	 * @param	string	Where clause
	 * @return	boolean Success
	 */
	public function addWhere($where) {
		if (empty($where)) {
			throw new MWException(__METHOD__.': An empty where clause was passed.');
		}
		$this->where[] = $where;
		return true;
	}

	/**
	 * Add a field to select.
	 *
	 * @access	public
	 * @param	array	Array of fields with the array key being the field alias.  Leave the array key as a numeric index to not specify an alias.
	 * @return	boolean Success
	 */
	public function addSelect($fields) {
		if (!is_array($fields)) {
			throw new MWException(__METHOD__.': A non-array was passed.');
		}
		foreach ($fields as $alias => $field) {
			//String alias and does not exist already.
			if (!is_numeric($alias) && !array_key_exists($alias, $this->select)) {
				$this->select[$alias] = $field;
			}
			if (array_key_exists($alias, $this->select) && $this->select[$alias] != $field) {
				//In case of a code bug that is overwriting an existing field alias throw an exception.
				throw new MWException(__METHOD__.": Attempted to overwrite existing field alias `{$this->select[$alias]}` AS `{$alias}` with `{$field}` AS `{$alias}`.");
			}

			if (is_numeric($alias) && !in_array($field, $this->select)) {
				$this->select[] = $field;
			}
		}
		return true;
	}

	/**
	 * Return SQL for 'addauthor' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _addauthor($option) {
		//Addauthor can not be used with addlasteditor.
		if (!$this->parametersProcessed['addlasteditor']) {
			$this->addTable('revision', 'rev');
			$this->addWhere($this->tableNames['page'].'.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MIN(rev_aux_min.rev_timestamp) FROM '.$this->tableNames['revision'].' AS rev_aux_min WHERE rev_aux_min.rev_page=rev.rev_page )');
			$this->addSelect(['rev_user', 'rev_user_text', 'rev_comment']);
		}
		return $query;
	}

	/**
	 * Return SQL for 'addcategories' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _addcategories($option) {
		if ($bAddCategories) {
			$sSqlCats            = ", GROUP_CONCAT(DISTINCT cl_gc.cl_to ORDER BY cl_gc.cl_to ASC SEPARATOR ' | ') AS cats";
			// Gives list of all categories linked from each article, if any.
			$sSqlClTableForGC    = $this->tableNames['categorylinks'].' AS cl_gc';
			// Categorylinks table used by the Group Concat (GC) function above
			$sSqlCond_page_cl_gc = 'page_id=cl_gc.cl_from';
			if ($sSqlGroupBy != '') {
				$sSqlGroupBy .= ', ';
			}
			$sSqlGroupBy .= $sSqlCl_to.$this->tableNames['page'].'.page_id';
		}
	}

	/**
	 * Return SQL for 'addcontribution' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _addcontribution($option) {
		if ($bAddContribution) {
			$sSqlRCTable = $this->tableNames['recentchanges'].' AS rc, ';
			$sSqlSelPage .= ', SUM( ABS( rc.rc_new_len - rc.rc_old_len ) ) AS contribution, rc.rc_user_text AS contributor';
			$sSqlWhere .= ' AND page.page_id=rc.rc_cur_id';
			if ($sSqlGroupBy != '') {
				$sSqlGroupBy .= ', ';
			}
			$sSqlGroupBy .= 'rc.rc_cur_id';
		}
	}

	/**
	 * Return SQL for 'addeditdate' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _addeditdate($option) {	}

	/**
	 * Return SQL for 'addexternallink' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _addexternallink($option) {	}

	/**
	 * Return SQL for 'addfirstcategorydate' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _addfirstcategorydate($option) {
		$sSqlCl_timestamp = ", DATE_FORMAT(cl0.cl_timestamp, '%Y%m%d%H%i%s') AS cl_timestamp";
	}

	/**
	 * Return SQL for 'addlasteditor' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _addlasteditor($option) {
		//Addlastauthor can not be used with addeditor.
		if ($bAddLastEditor && $sSqlRevisionTable == '') {
			$sSqlRevisionTable = $this->tableNames['revision'].' AS rev, ';
			$sSqlCond_page_rev .= ' AND '.$this->tableNames['page'].'.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MAX(rev_aux_max.rev_timestamp) FROM '.$this->tableNames['revision'].' AS rev_aux_max WHERE rev_aux_max.rev_page=rev.rev_page )';
		}

		if ($sSqlRevisionTable != '') {
			$sSqlRev_user = ', rev_user, rev_user_text, rev_comment';
		}
	}

	/**
	 * Return SQL for 'addpagecounter' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _addpagecounter($option) {
		$sSqlPage_counter = ", {$this->tableNames['page']}.page_counter AS page_counter";
	}

	/**
	 * Return SQL for 'addpagesize' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _addpagesize($option) {
		$sSqlPage_size = ", {$this->tableNames['page']}.page_len AS page_len";
	}

	/**
	 * Return SQL for 'addpagetoucheddate' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _addpagetoucheddate($option) {
		//@TODO: Need to check if this was added by the order methods or call this function to add it from there.
		if ($bAddPageTouchedDate && $sSqlPage_touched == '') {
			$sSqlPage_touched = ", {$this->tableNames['page']}.page_touched AS page_touched";
		}
	}

	/**
	 * Return SQL for 'adduser' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _adduser($option) {

		if ($sSqlRevisionTable != '') {
			$sSqlRev_user = ', rev_user, rev_user_text, rev_comment';
		}
	}

	/**
	 * Return SQL for 'allowcachedresults' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _allowcachedresults($option) {	}

	/**
	 * Return SQL for 'allrevisionsbefore' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _allrevisionsbefore($option) {
		if ($sAllRevisionsBefore != '') {
			$sSqlCond_page_rev .= ' AND '.$this->tableNames['page'].'.page_id=rev.rev_page AND rev.rev_timestamp < '.$sAllRevisionsBefore;
		}
	}

	/**
	 * Return SQL for 'allrevisionssince' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _allrevisionssince($option) {
		if ($sAllRevisionsSince != '') {
			$sSqlCond_page_rev .= ' AND '.$this->tableNames['page'].'.page_id=rev.rev_page AND rev.rev_timestamp >= '.$sAllRevisionsSince;
		}
	}

	/**
	 * Return SQL for 'articlecategory' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _articlecategory($option) {
		if (isset($sArticleCategory) && $sArticleCategory !== null) {
			$sSqlWhere .= " AND {$this->tableNames['page']}.page_title IN (
				SELECT p2.page_title
				FROM {$this->tableNames['page']} p2
				INNER JOIN {$this->tableNames['categorylinks']} clstc ON (clstc.cl_from = p2.page_id AND clstc.cl_to = ".self::$DB->addQuotes($sArticleCategory)." )
				WHERE p2.page_namespace = 0
				) ";
		}
	}

	/**
	 * Return SQL for 'categoriesminmax' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _categoriesminmax($option) {
		if (isset($aCatMinMax[0]) && $aCatMinMax[0] != '') {
			$sSqlCond_MaxCat .= ' AND '.$aCatMinMax[0].' <= (SELECT count(*) FROM '.$this->tableNames['categorylinks'].' WHERE '.$this->tableNames['categorylinks'].'.cl_from=page_id)';
		}
		if (isset($aCatMinMax[1]) && $aCatMinMax[1] != '') {
			$sSqlCond_MaxCat .= ' AND '.$aCatMinMax[1].' >= (SELECT count(*) FROM '.$this->tableNames['categorylinks'].' WHERE '.$this->tableNames['categorylinks'].'.cl_from=page_id)';
		}
	}

	/**
	 * Return SQL for 'category' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _category($option) {
		$iClTable = 0;
		for ($i = 0; $i < $iIncludeCatCount; $i++) {
			// If we want the Uncategorized
			$sSqlSelectFrom .= ' INNER JOIN '.(in_array('', $aIncludeCategories[$i]) ? $this->tableNames['dpl_clview'] : $this->tableNames['categorylinks']).' AS cl'.$iClTable.' ON '.$this->tableNames['page'].'.page_id=cl'.$iClTable.'.cl_from AND (cl'.$iClTable.'.cl_to'.$sCategoryComparisonMode.self::$DB->addQuotes(str_replace(' ', '_', $aIncludeCategories[$i][0]));
			for ($j = 1; $j < count($aIncludeCategories[$i]); $j++)
				$sSqlSelectFrom .= ' OR cl'.$iClTable.'.cl_to'.$sCategoryComparisonMode.self::$DB->addQuotes(str_replace(' ', '_', $aIncludeCategories[$i][$j]));
			$sSqlSelectFrom .= ') ';
			$iClTable++;
		}
	}

	/**
	 * Return SQL for 'categorymatch' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _categorymatch($option) {	}

	/**
	 * Return SQL for 'categoryregexp' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _categoryregexp($option) {	}

	/**
	 * Return SQL for 'columns' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _columns($option) {	}

	/**
	 * Return SQL for 'count' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _count($option) {	}

	/**
	 * Return SQL for 'createdby' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _createdby($option) {
		if ($parameters->getParameter('createdby')) {
		    $sSqlCreationRevisionTable = $this->tableNames['revision'].' AS creation_rev, ';
		    $sSqlCond_page_rev .= ' AND '.self::$DB->addQuotes($parameters->getParameter('createdby')).' = creation_rev.rev_user_text'.' AND creation_rev.rev_page = page_id'.' AND creation_rev.rev_parent_id = 0';
		}
	}

	/**
	 * Return SQL for 'debug' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _debug($option) {	}

	/**
	 * Return SQL for 'deleterules' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _deleterules($option) {	}

	/**
	 * Return SQL for 'distinct' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _distinct($option) {	}

	/**
	 * Return SQL for 'dominantsection' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _dominantsection($option) {	}

	/**
	 * Return SQL for 'dplcache' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _dplcache($option) {	}

	/**
	 * Return SQL for 'dplcacheperiod' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _dplcacheperiod($option) {	}

	/**
	 * Return SQL for 'eliminate' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _eliminate($option) {	}

	/**
	 * Return SQL for 'escapelinks' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _escapelinks($option) {	}

	/**
	 * Return SQL for 'execandexit' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _execandexit($option) {	}

	/**
	 * Return SQL for 'firstrevisionsince' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _firstrevisionsince($option) {
		if ($sFirstRevisionSince != '') {
			$sSqlCond_page_rev .= ' AND '.$this->tableNames['page'].'.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MIN(rev_aux_snc.rev_timestamp) FROM '.$this->tableNames['revision'].' AS rev_aux_snc WHERE rev_aux_snc.rev_page=rev.rev_page AND rev_aux_snc.rev_timestamp >= '.$sFirstRevisionSince.')';
		}
	}

	/**
	 * Return SQL for 'fixcategory' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _fixcategory($option) {	}

	/**
	 * Return SQL for 'format' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _format($option) {	}

	/**
	 * Return SQL for 'goal' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _goal($option) {	}

	/**
	 * Return SQL for 'headingcount' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _headingcount($option) {	}

	/**
	 * Return SQL for 'headingmode' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _headingmode($option) {	}

	/**
	 * Return SQL for 'hiddencategories' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _hiddencategories($option) {	}

	/**
	 * Return SQL for 'hitemattr' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _hitemattr($option) {	}

	/**
	 * Return SQL for 'hlistattr' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _hlistattr($option) {	}

	/**
	 * Return SQL for 'ignorecase' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _ignorecase($option) {	}

	/**
	 * Return SQL for 'imagecontainer' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _imagecontainer($option) {
		if (count($aImageContainer) > 0) {
			$sSqlPageLinksTable .= $this->tableNames['imagelinks'].' AS ic, ';
			if ($acceptOpenReferences) {
				$sSqlCond_page_pl .= ' AND (';
			} else {
				$sSqlCond_page_pl .= ' AND '.$this->tableNames['page'].'.page_namespace=\'6\' AND '.$this->tableNames['page'].'.page_title=ic.il_to AND (';
			}
			$n = 0;
			foreach ($aImageContainer as $link) {
				if ($n > 0) {
					$sSqlCond_page_pl .= ' OR ';
				}
				if ($bIgnoreCase) {
					$sSqlCond_page_pl .= "LOWER(CAST(ic.il_from AS char)=LOWER(".self::$DB->addQuotes($link->getArticleID()).')';
				} else {
					$sSqlCond_page_pl .= "ic.il_from=".self::$DB->addQuotes($link->getArticleID());
				}
				$n++;
			}
			$sSqlCond_page_pl .= ')';
		}
	}

	/**
	 * Return SQL for 'imageused' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _imageused($option) {
		if (count($aImageUsed) > 0) {
			$sSqlPageLinksTable .= $this->tableNames['imagelinks'].' AS il, ';
			$sSqlCond_page_pl .= ' AND '.$this->tableNames['page'].'.page_id=il.il_from AND (';
			$sSqlSelPage = ', il.il_to AS image_sel_title';
			$n           = 0;
			foreach ($aImageUsed as $link) {
				if ($n > 0) {
					$sSqlCond_page_pl .= ' OR ';
				}
				if ($bIgnoreCase) {
					$sSqlCond_page_pl .= "LOWER(CAST(il.il_to AS char))=LOWER(".self::$DB->addQuotes($link->getDbKey()).')';
				} else {
					$sSqlCond_page_pl .= "il.il_to=".self::$DB->addQuotes($link->getDbKey());
				}
				$n++;
			}
			$sSqlCond_page_pl .= ')';
		}
	}

	/**
	 * Return SQL for 'include' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _include($option) {	}

	/**
	 * Return SQL for 'includematch' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _includematch($option) {	}

	/**
	 * Return SQL for 'includematchparsed' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _includematchparsed($option) {	}

	/**
	 * Return SQL for 'includemaxlength' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _includemaxlength($option) {	}

	/**
	 * Return SQL for 'includenotmatch' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _includenotmatch($option) {	}

	/**
	 * Return SQL for 'includenotmatchparsed' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _includenotmatchparsed($option) {	}

	/**
	 * Return SQL for 'includepage' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _includepage($option) {	}

	/**
	 * Return SQL for 'includesubpages' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _includesubpages($option) {	}

	/**
	 * Return SQL for 'includetrim' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _includetrim($option) {	}

	/**
	 * Return SQL for 'inlinetext' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _inlinetext($option) {	}

	/**
	 * Return SQL for 'itemattr' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _itemattr($option) {	}

	/**
	 * Return SQL for 'lastmodifiedby' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _lastmodifiedby($option) {
	    $sSqlCond_page_rev .= ' AND '.self::$DB->addQuotes($parameters->getParameter('lastmodifiedby')).' = (SELECT rev_user_text FROM '.$this->tableNames['revision'].' WHERE '.$this->tableNames['revision'].'.rev_page=page_id ORDER BY '.$this->tableNames['revision'].'.rev_timestamp DESC LIMIT 1)';
	}

	/**
	 * Return SQL for 'lastrevisionbefore' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _lastrevisionbefore($option) {
		if ($sLastRevisionBefore != '') {
			$sSqlCond_page_rev .= ' AND '.$this->tableNames['page'].'.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MAX(rev_aux_bef.rev_timestamp) FROM '.$this->tableNames['revision'].' AS rev_aux_bef WHERE rev_aux_bef.rev_page=rev.rev_page AND rev_aux_bef.rev_timestamp < '.$sLastRevisionBefore.')';
		}
	}

	/**
	 * Return SQL for 'linksfrom' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _linksfrom($option) {
		$sSqlCond_page_pl .= ' AND '.$this->tableNames['page'].'.page_id NOT IN (SELECT '.$this->tableNames['pagelinks'].'.pl_from FROM '.$this->tableNames['pagelinks'].' WHERE (';
		$n = 0;
		foreach ($aNotLinksTo as $links) {
			foreach ($links as $link) {
				if ($n > 0) {
					$sSqlCond_page_pl .= ' OR ';
				}
				$sSqlCond_page_pl .= '('.$this->tableNames['pagelinks'].'.pl_namespace='.intval($link->getNamespace());
				if (strpos($link->getDbKey(), '%') >= 0) {
					$operator = ' LIKE ';
				} else {
					$operator = '=';
				}
				if ($bIgnoreCase) {
					$sSqlCond_page_pl .= ' AND LOWER(CAST('.$this->tableNames['pagelinks'].'.pl_title AS char))'.$operator.'LOWER('.self::$DB->addQuotes($link->getDbKey()).'))';
				} else {
					$sSqlCond_page_pl .= ' AND		 '.$this->tableNames['pagelinks'].'.pl_title'.$operator.self::$DB->addQuotes($link->getDbKey()).')';
				}
				$n++;
			}
		}
		$sSqlCond_page_pl .= ') )';
	}

	/**
	 * Return SQL for 'linksto' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _linksto($option) {
		if (count($aLinksTo) > 0) {
			$sSqlPageLinksTable .= $this->tableNames['pagelinks'].' AS pl, ';
			$sSqlCond_page_pl .= ' AND '.$this->tableNames['page'].'.page_id=pl.pl_from AND ';
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
					$sSqlCond_page_pl .= '(pl.pl_namespace='.intval($link->getNamespace());
					if (strpos($link->getDbKey(), '%') >= 0) {
						$operator = ' LIKE ';
					} else {
						$operator = '=';
					}
					if ($bIgnoreCase) {
						$sSqlCond_page_pl .= ' AND LOWER(CAST(pl.pl_title AS char))'.$operator.'LOWER('.self::$DB->addQuotes($link->getDbKey()).'))';
					} else {
						$sSqlCond_page_pl .= ' AND pl.pl_title'.$operator.self::$DB->addQuotes($link->getDbKey()).')';
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
				$sSqlCond_page_pl .= ' AND EXISTS(select pl_from FROM '.$this->tableNames['pagelinks'].' WHERE ('.$this->tableNames['pagelinks'].'.pl_from=page_id AND (';
				foreach ($linkGroup as $link) {
					if (++$m > 1) {
						$sSqlCond_page_pl .= ' OR ';
					}
					$sSqlCond_page_pl .= '('.$this->tableNames['pagelinks'].'.pl_namespace='.intval($link->getNamespace());
					if (strpos($link->getDbKey(), '%') >= 0) {
						$operator = ' LIKE ';
					} else {
						$operator = '=';
					}
					if ($bIgnoreCase) {
						$sSqlCond_page_pl .= ' AND LOWER(CAST('.$this->tableNames['pagelinks'].'.pl_title AS char))'.$operator.'LOWER('.self::$DB->addQuotes($link->getDbKey()).')';
					} else {
						$sSqlCond_page_pl .= ' AND '.$this->tableNames['pagelinks'].'.pl_title'.$operator.self::$DB->addQuotes($link->getDbKey());
					}
					$sSqlCond_page_pl .= ')';
				}
				$sSqlCond_page_pl .= ')))';
			}
		}
	}

	/**
	 * Return SQL for 'linkstoexternal' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _linkstoexternal($option) {
		if (count($aLinksToExternal) > 0) {
			$sSqlExternalLinksTable .= $this->tableNames['externallinks'].' AS el, ';
			$sSqlCond_page_el .= ' AND '.$this->tableNames['page'].'.page_id=el.el_from AND (';
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
					$sSqlCond_page_el .= '(el.el_to LIKE '.self::$DB->addQuotes($link).')';
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
				$sSqlCond_page_el .= ' AND EXISTS(SELECT el_from FROM '.$this->tableNames['externallinks'].' WHERE ('.$this->tableNames['externallinks'].'.el_from=page_id AND (';
				foreach ($linkGroup as $link) {
					if (++$m > 1) {
						$sSqlCond_page_el .= ' OR ';
					}
					$sSqlCond_page_el .= '('.$this->tableNames['externallinks'].'.el_to LIKE '.self::$DB->addQuotes($link).')';
				}
				$sSqlCond_page_el .= ')))';
			}
		}
	}

	/**
	 * Return SQL for 'listattr' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _listattr($option) {	}

	/**
	 * Return SQL for 'listseparators' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _listseparators($option) {	}

	/**
	 * Return SQL for 'maxrevisions' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _maxrevisions($option) {
		$sSqlWhere .= " AND ((SELECT count(rev_aux3.rev_page) FROM {$this->tableNames['revision']} AS rev_aux3 WHERE rev_aux3.rev_page=page.page_id) <= $iMaxRevisions)";
	}

	/**
	 * Return SQL for 'minoredits' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _minoredits($option) {
		if (isset($sMinorEdits) && $sMinorEdits == 'exclude') {
			$sSqlWhere .= ' AND rev_minor_edit=0';
		}
	}

	/**
	 * Return SQL for 'minrevisions' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _minrevisions($option) {
		$sSqlWhere .= " AND ((SELECT count(rev_aux2.rev_page) FROM {$this->tableNames['revision']} AS rev_aux2 WHERE rev_aux2.rev_page=page.page_id) >= $iMinRevisions)";
	}

	/**
	 * Return SQL for 'mode' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _mode($option) {	}

	/**
	 * Return SQL for 'modifiedby' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _modifiedby($option) {
	    $sSqlChangeRevisionTable = $this->tableNames['revision'].' AS change_rev, ';
	    $sSqlCond_page_rev .= ' AND '.self::$DB->addQuotes($parameters->getParameter('modifiedby')).' = change_rev.rev_user_text'.' AND change_rev.rev_page = page_id';
	}

	/**
	 * Return SQL for 'multisecseparators' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _multisecseparators($option) {	}

	/**
	 * Return SQL for 'namespace' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _namespace($option) {
		if (!empty($aNamespaces)) {
			if ($acceptOpenReferences) {
				$sSqlWhere .= ' AND '.$this->tableNames['pagelinks'].'.pl_namespace IN ('.self::$DB->makeList($aNamespaces).')';
			} else {
				$sSqlWhere .= ' AND '.$this->tableNames['page'].'.page_namespace IN ('.self::$DB->makeList($aNamespaces).')';
			}
		}
	}

	/**
	 * Return SQL for 'noresultsfooter' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _noresultsfooter($option) {	}

	/**
	 * Return SQL for 'noresultsheader' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _noresultsheader($option) {	}

	/**
	 * Return SQL for 'notcategory' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _notcategory($option) {
		//@TODO: The table incremental variable needs to be on the object.
		for ($i = 0; $i < $iExcludeCatCount; $i++) {
			$sSqlSelectFrom .= ' LEFT OUTER JOIN '.$this->tableNames['categorylinks'].' AS cl'.$iClTable.' ON '.$this->tableNames['page'].'.page_id=cl'.$iClTable.'.cl_from'.' AND cl'.$iClTable.'.cl_to'.$sNotCategoryComparisonMode.self::$DB->addQuotes(str_replace(' ', '_', $aExcludeCategories[$i]));
			$sSqlWhere .= ' AND cl'.$iClTable.'.cl_to IS NULL';
			$iClTable++;
		}
	}

	/**
	 * Return SQL for 'notcategorymatch' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _notcategorymatch($option) {	}

	/**
	 * Return SQL for 'notcategoryregexp' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _notcategoryregexp($option) {	}

	/**
	 * Return SQL for 'notcreatedby' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _notcreatedby($option) {
	    $sSqlNoCreationRevisionTable = $this->tableNames['revision'].' AS no_creation_rev, ';
	    $sSqlCond_page_rev .= ' AND '.self::$DB->addQuotes($parameters->getParameter('notcreatedby')).' != no_creation_rev.rev_user_text'.' AND no_creation_rev.rev_page = page_id'.' AND no_creation_rev.rev_parent_id = 0';
	}

	/**
	 * Return SQL for 'notlastmodifiedby' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _notlastmodifiedby($option) {
	    $sSqlCond_page_rev .= ' AND '.self::$DB->addQuotes($parameters->getParameter('notlastmodifiedby')).' != (SELECT rev_user_text FROM '.$this->tableNames['revision'].' WHERE '.$this->tableNames['revision'].'.rev_page=page_id ORDER BY '.$this->tableNames['revision'].'.rev_timestamp DESC LIMIT 1)';
	}

	/**
	 * Return SQL for 'notlinksfrom' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _notlinksfrom($option) {
		if (count($aNotLinksFrom) > 0) {
			if ($acceptOpenReferences) {
				$sSqlCond_page_pl .= ' AND (';
				$n = 0;
				foreach ($aNotLinksFrom as $links) {
					foreach ($links as $link) {
						if ($n > 0) {
							$sSqlCond_page_pl .= ' AND ';
						}
						$sSqlCond_page_pl .= 'pl_from <> '.$link->getArticleID().' ';
						$n++;
					}
				}
				$sSqlCond_page_pl .= ')';
			} else {
				$sSqlCond_page_pl .= ' AND CONCAT(page_namespace,page_title) NOT IN (SELECT CONCAT('.$this->tableNames['pagelinks'].'.pl_namespace,'.$this->tableNames['pagelinks'].'.pl_title) from '.$this->tableNames['pagelinks'].' WHERE (';
				$n = 0;
				foreach ($aNotLinksFrom as $links) {
					foreach ($links as $link) {
						if ($n > 0) {
							$sSqlCond_page_pl .= ' OR ';
						}
						$sSqlCond_page_pl .= $this->tableNames['pagelinks'].'.pl_from='.$link->getArticleID().' ';
						$n++;
					}
				}
				$sSqlCond_page_pl .= '))';
			}
		}
	}

	/**
	 * Return SQL for 'notlinksto' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _notlinksto($option) {	}

	/**
	 * Return SQL for 'notmodifiedby' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _notmodifiedby($option) {
	    $sSqlCond_page_rev .= ' AND NOT EXISTS (SELECT 1 FROM '.$this->tableNames['revision'].' WHERE '.$this->tableNames['revision'].'.rev_page=page_id AND '.$this->tableNames['revision'].'.rev_user_text = '.self::$DB->addQuotes($parameters->getParameter('notmodifiedby')).' LIMIT 1)';
	}

	/**
	 * Return SQL for 'notnamespace' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _notnamespace($option) {
		if (!empty($aExcludeNamespaces)) {
			if ($acceptOpenReferences) {
				$sSqlWhere .= ' AND '.$this->tableNames['pagelinks'].'.pl_namespace NOT IN ('.self::$DB->makeList($aExcludeNamespaces).')';
			} else {
				$sSqlWhere .= ' AND '.$this->tableNames['page'].'.page_namespace NOT IN ('.self::$DB->makeList($aExcludeNamespaces).')';
			}
		}
	}

	/**
	 * Return SQL for 'nottitlematch' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _nottitlematch($option) {
		if (count($aNotTitleMatch) > 0) {
			$sSqlWhere .= ' AND NOT (';
			$n = 0;
			foreach ($aNotTitleMatch as $link) {
				if ($n > 0) {
					$sSqlWhere .= ' OR ';
				}
				if ($acceptOpenReferences) {
					if ($bIgnoreCase) {
						$sSqlWhere .= 'LOWER(CAST(pl_title AS char))'.$sNotTitleMatchMode.'LOWER('.self::$DB->addQuotes($link).')';
					} else {
						$sSqlWhere .= 'pl_title'.$sNotTitleMatchMode.self::$DB->addQuotes($link);
					}
				} else {
					if ($bIgnoreCase) {
						$sSqlWhere .= 'LOWER(CAST('.$this->tableNames['page'].'.page_title AS char))'.$sNotTitleMatchMode.'LOWER('.self::$DB->addQuotes($link).')';
					} else {
						$sSqlWhere .= $this->tableNames['page'].'.page_title'.$sNotTitleMatchMode.self::$DB->addQuotes($link);
					}
				}
				$n++;
			}
			$sSqlWhere .= ')';
		}
	}

	/**
	 * Return SQL for 'nottitleregexp' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _nottitleregexp($option) {	}

	/**
	 * Return SQL for 'notuses' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _notuses($option) {
		if (count($aNotUses) > 0) {
			$sSqlCond_page_pl .= ' AND '.$this->tableNames['page'].'.page_id NOT IN (SELECT '.$this->tableNames['templatelinks'].'.tl_from FROM '.$this->tableNames['templatelinks'].' WHERE (';
			$n = 0;
			foreach ($aNotUses as $link) {
				if ($n > 0) {
					$sSqlCond_page_pl .= ' OR ';
				}
				$sSqlCond_page_pl .= '('.$this->tableNames['templatelinks'].'.tl_namespace='.intval($link->getNamespace());
				if ($bIgnoreCase) {
					$sSqlCond_page_pl .= ' AND LOWER(CAST('.$this->tableNames['templatelinks'].'.tl_title AS char))=LOWER('.self::$DB->addQuotes($link->getDbKey()).'))';
				} else {
					$sSqlCond_page_pl .= ' AND '.$this->tableNames['templatelinks'].'.tl_title='.self::$DB->addQuotes($link->getDbKey()).')';
				}
				$n++;
			}
			$sSqlCond_page_pl .= ') )';
		}
	}

	/**
	 * Return SQL for 'offset' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _offset($option) {	}

	/**
	 * Return SQL for 'oneresultfooter' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _oneresultfooter($option) {	}

	/**
	 * Return SQL for 'oneresultheader' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _oneresultheader($option) {	}

	/**
	 * Return SQL for 'openreferences' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _openreferences($option) {	}

	/**
	 * Return SQL for 'order' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _order($option) {	}

	/**
	 * Return SQL for 'ordercollation' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _ordercollation($option) {	}

	/**
	 * Return SQL for 'ordermethod' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _ordermethod($option) {	}

	/**
	 * Return SQL for 'qualitypages' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _qualitypages($option) {	}

	/**
	 * Return SQL for 'randomcount' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _randomcount($option) {	}

	/**
	 * Return SQL for 'redirects' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _redirects($option) {
		if (!$acceptOpenReferences) {
			switch ($sRedirects) {
				case 'only':
					$sSqlWhere .= ' AND '.$this->tableNames['page'].'.page_is_redirect=1';
					break;
				case 'exclude':
					$sSqlWhere .= ' AND '.$this->tableNames['page'].'.page_is_redirect=0';
					break;
			}
		}
	}

	/**
	 * Return SQL for 'replaceintitle' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _replaceintitle($option) {	}

	/**
	 * Return SQL for 'reset' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _reset($option) {	}

	/**
	 * Return SQL for 'resultsfooter' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _resultsfooter($option) {	}

	/**
	 * Return SQL for 'resultsheader' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _resultsheader($option) {	}

	/**
	 * Return SQL for 'rowcolformat' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _rowcolformat($option) {	}

	/**
	 * Return SQL for 'rows' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _rows($option) {	}

	/**
	 * Return SQL for 'rowsize' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _rowsize($option) {	}

	/**
	 * Return SQL for 'scroll' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _scroll($option) {	}

	/**
	 * Return SQL for 'secseparators' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _secseparators($option) {	}

	/**
	 * Return SQL for 'showcurid' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _showcurid($option) {	}

	/**
	 * Return SQL for 'shownamespace' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _shownamespace($option) {	}

	/**
	 * Return SQL for 'skipthispage' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _skipthispage($option) {	}

	/**
	 * Return SQL for 'stablepages' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _stablepages($option) {	}

	/**
	 * Return SQL for 'suppresserrors' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _suppresserrors($option) {	}

	/**
	 * Return SQL for 'table' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _table($option) {	}

	/**
	 * Return SQL for 'tablerow' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _tablerow($option) {	}

	/**
	 * Return SQL for 'tablesortcol' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _tablesortcol($option) {	}

	/**
	 * Return SQL for 'title' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _title($option) {
		if ($sTitleIs != '') {
			if ($bIgnoreCase) {
				$sSqlWhere .= ' AND LOWER(CAST('.$this->tableNames['page'].'.page_title AS char)) = LOWER('.self::$DB->addQuotes($sTitleIs).')';
			} else {
				$sSqlWhere .= ' AND '.$this->tableNames['page'].'.page_title = '.self::$DB->addQuotes($sTitleIs);
			}
		}
	}

	/**
	 * Return SQL for 'titlegt' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _titlegt($option) {
		if ($sTitleGE != '') {
			$sSqlWhere .= ' AND (';
			if (substr($sTitleGE, 0, 2) == '=_') {
				if ($acceptOpenReferences) {
					$sSqlWhere .= 'pl_title >='.self::$DB->addQuotes(substr($sTitleGE, 2));
				} else {
					$sSqlWhere .= $this->tableNames['page'].'.page_title >='.self::$DB->addQuotes(substr($sTitleGE, 2));
				}
			} else {
				if ($acceptOpenReferences) {
					$sSqlWhere .= 'pl_title >'.self::$DB->addQuotes($sTitleGE);
				} else {
					$sSqlWhere .= $this->tableNames['page'].'.page_title >'.self::$DB->addQuotes($sTitleGE);
				}
			}
			$sSqlWhere .= ')';
		}
	}

	/**
	 * Return SQL for 'titlelt' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _titlelt($option) {
		if ($sTitleLE != '') {
			$sSqlWhere .= ' AND (';
			if (substr($sTitleLE, 0, 2) == '=_') {
				if ($acceptOpenReferences) {
					$sSqlWhere .= 'pl_title <='.self::$DB->addQuotes(substr($sTitleLE, 2));
				} else {
					$sSqlWhere .= $this->tableNames['page'].'.page_title <='.self::$DB->addQuotes(substr($sTitleLE, 2));
				}
			} else {
				if ($acceptOpenReferences) {
					$sSqlWhere .= 'pl_title <'.self::$DB->addQuotes($sTitleLE);
				} else {
					$sSqlWhere .= $this->tableNames['page'].'.page_title <'.self::$DB->addQuotes($sTitleLE);
				}
			}
			$sSqlWhere .= ')';
		}
	}

	/**
	 * Return SQL for 'titlematch' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _titlematch($option) {
		if (count($aTitleMatch) > 0) {
			$sSqlWhere .= ' AND (';
			$n = 0;
			foreach ($aTitleMatch as $link) {
				if ($n > 0) {
					$sSqlWhere .= ' OR ';
				}
				if ($acceptOpenReferences) {
					if ($bIgnoreCase) {
						$sSqlWhere .= 'LOWER(CAST(pl_title AS char))'.$sTitleMatchMode.strtolower(self::$DB->addQuotes($link));
					} else {
						$sSqlWhere .= 'pl_title'.$sTitleMatchMode.self::$DB->addQuotes($link);
					}
				} else {
					if ($bIgnoreCase) {
						$sSqlWhere .= 'LOWER(CAST('.$this->tableNames['page'].'.page_title AS char))'.$sTitleMatchMode.strtolower(self::$DB->addQuotes($link));
					} else {
						$sSqlWhere .= $this->tableNames['page'].'.page_title'.$sTitleMatchMode.self::$DB->addQuotes($link);
					}
				}
				$n++;
			}
			$sSqlWhere .= ')';
		}
	}

	/**
	 * Return SQL for 'titlemaxlength' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _titlemaxlength($option) {	}

	/**
	 * Return SQL for 'titleregexp' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _titleregexp($option) {	}

	/**
	 * Return SQL for 'updaterules' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _updaterules($option) {	}

	/**
	 * Return SQL for 'usedby' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _usedby($option) {
		if (count($aUsedBy) > 0) {
			if ($acceptOpenReferences) {
				$sSqlCond_page_tpl .= ' AND (';
				$n = 0;
				foreach ($aUsedBy as $link) {
					if ($n > 0) {
						$sSqlCond_page_tpl .= ' OR ';
					}
					$sSqlCond_page_tpl .= '(tpl_from='.$link->getArticleID().')';
					$n++;
				}
				$sSqlCond_page_tpl .= ')';
			} else {
				$sSqlPageLinksTable .= $this->tableNames['templatelinks'].' AS tpl, '.$this->tableNames['page'].'AS tplsrc, ';
				$sSqlCond_page_tpl .= ' AND '.$this->tableNames['page'].'.page_title = tpl.tl_title  AND tplsrc.page_id=tpl.tl_from AND (';
				$sSqlSelPage = ', tplsrc.page_title AS tpl_sel_title, tplsrc.page_namespace AS tpl_sel_ns';
				$n           = 0;
				foreach ($aUsedBy as $link) {
					if ($n > 0) {
						$sSqlCond_page_tpl .= ' OR ';
					}
					$sSqlCond_page_tpl .= '(tpl.tl_from='.$link->getArticleID().')';
					$n++;
				}
				$sSqlCond_page_tpl .= ')';
			}
		}
	}

	/**
	 * Return SQL for 'userdateformat' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _userdateformat($option) {
	}

	/**
	 * Return SQL for 'uses' parameter.
	 *
	 * @access	public
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	public function _uses($option) {
		if (count($aUses) > 0) {
			$sSqlPageLinksTable .= ' '.$this->tableNames['templatelinks'].' as tl, ';
			$sSqlCond_page_pl .= ' AND '.$this->tableNames['page'].'.page_id=tl.tl_from  AND (';
			$n = 0;
			foreach ($aUses as $link) {
				if ($n > 0) {
					$sSqlCond_page_pl .= ' OR ';
				}
				$sSqlCond_page_pl .= '(tl.tl_namespace='.intval($link->getNamespace());
				if ($bIgnoreCase) {
					$sSqlCond_page_pl .= " AND LOWER(CAST(tl.tl_title AS char))=LOWER(".self::$DB->addQuotes($link->getDbKey()).'))';
				} else {
					$sSqlCond_page_pl .= " AND		 tl.tl_title=".self::$DB->addQuotes($link->getDbKey()).')';
				}
				$n++;
			}
			$sSqlCond_page_pl .= ')';
		}
	}
}
?>