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
	 * Group By Clauses
	 *
	 * @var		array
	 */
	private $this->groupBy = [];

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
	 * Add a group by clause to the output.
	 *
	 * @access	public
	 * @param	string	Where clause
	 * @return	boolean Success
	 */
	public function addGroupBy($groupBy) {
		if (empty($groupBy)) {
			throw new MWException(__METHOD__.': An empty group by clause was passed.');
		}
		$this->groupBy[] = $groupBy;
		return true;
	}

	/**
	 * Return SQL for 'addauthor' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addauthor($option) {
		//Addauthor can not be used with addlasteditor.
		if (!$this->parametersProcessed['addlasteditor']) {
			$this->addTable('revision', 'rev');
			$this->addWhere($this->tableNames['page'].'.page_id = rev.rev_page AND rev.rev_timestamp = (SELECT MIN(rev_aux_min.rev_timestamp) FROM '.$this->tableNames['revision'].' AS rev_aux_min WHERE rev_aux_min.rev_page = rev.rev_page)');
			$this->addSelect(['rev_user', 'rev_user_text', 'rev_comment']);
		}
	}

	/**
	 * Return SQL for 'addcategories' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addcategories($option) {
		if ($bAddCategories) {
			$this->addSelect("GROUP_CONCAT(DISTINCT cl_gc.cl_to ORDER BY cl_gc.cl_to ASC SEPARATOR ' | ') AS cats");
			$this->addTable('categorylinks', 'cl_gc');
			//@TODO: Figure out how to get this LEFT OUTER JOIN thingy to work.
			$sSqlCond_page_cl_gc = 'page_id=cl_gc.cl_from';
			$this->addGroupBy($this->tableNames['page'].'.page_id');
		}
	}

	/**
	 * Return SQL for 'addcontribution' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addcontribution($option) {
		$this->addTable('recentchanges', 'rc');
		$this->addSelect('SUM( ABS( rc.rc_new_len - rc.rc_old_len ) ) AS contribution, rc.rc_user_text AS contributor');
		$this->addWhere("page.page_id=rc.rc_cur_id");
		$this->addGroupBy('rc.rc_cur_id');
	}

	/**
	 * Return SQL for 'addeditdate' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addeditdate($option) {	}

	/**
	 * Return SQL for 'addexternallink' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addexternallink($option) {	}

	/**
	 * Return SQL for 'addfirstcategorydate' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addfirstcategorydate($option) {
		$this->addSelect("DATE_FORMAT(cl0.cl_timestamp, '%Y%m%d%H%i%s') AS cl_timestamp");
	}

	/**
	 * Return SQL for 'addlasteditor' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addlasteditor($option) {
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
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addpagecounter($option) {
		$sSqlPage_counter = ", {$this->tableNames['page']}.page_counter AS page_counter";
	}

	/**
	 * Return SQL for 'addpagesize' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addpagesize($option) {
		$sSqlPage_size = ", {$this->tableNames['page']}.page_len AS page_len";
	}

	/**
	 * Return SQL for 'addpagetoucheddate' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addpagetoucheddate($option) {
		//@TODO: Need to check if this was added by the order methods or call this function to add it from there.
		if ($bAddPageTouchedDate && $sSqlPage_touched == '') {
			$sSqlPage_touched = ", {$this->tableNames['page']}.page_touched AS page_touched";
		}
	}

	/**
	 * Return SQL for 'adduser' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _adduser($option) {

		if ($sSqlRevisionTable != '') {
			$sSqlRev_user = ', rev_user, rev_user_text, rev_comment';
		}
	}

	/**
	 * Return SQL for 'allowcachedresults' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _allowcachedresults($option) {	}

	/**
	 * Return SQL for 'allrevisionsbefore' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _allrevisionsbefore($option) {
		if ($sAllRevisionsBefore != '') {
			$sSqlCond_page_rev .= ' AND '.$this->tableNames['page'].'.page_id=rev.rev_page AND rev.rev_timestamp < '.$sAllRevisionsBefore;
		}
	}

	/**
	 * Return SQL for 'allrevisionssince' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _allrevisionssince($option) {
		if ($sAllRevisionsSince != '') {
			$sSqlCond_page_rev .= ' AND '.$this->tableNames['page'].'.page_id=rev.rev_page AND rev.rev_timestamp >= '.$sAllRevisionsSince;
		}
	}

	/**
	 * Return SQL for 'articlecategory' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _articlecategory($option) {
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
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _categoriesminmax($option) {
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
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _category($option) {
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
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _categorymatch($option) {	}

	/**
	 * Return SQL for 'categoryregexp' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _categoryregexp($option) {	}

	/**
	 * Return SQL for 'columns' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _columns($option) {	}

	/**
	 * Return SQL for 'count' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _count($option) {	}

	/**
	 * Return SQL for 'createdby' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _createdby($option) {
		if ($parameters->getParameter('createdby')) {
		    $sSqlCreationRevisionTable = $this->tableNames['revision'].' AS creation_rev, ';
		    $sSqlCond_page_rev .= ' AND '.self::$DB->addQuotes($parameters->getParameter('createdby')).' = creation_rev.rev_user_text'.' AND creation_rev.rev_page = page_id'.' AND creation_rev.rev_parent_id = 0';
		}
	}

	/**
	 * Return SQL for 'debug' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _debug($option) {	}

	/**
	 * Return SQL for 'deleterules' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _deleterules($option) {	}

	/**
	 * Return SQL for 'distinct' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _distinct($option) {	}

	/**
	 * Return SQL for 'dominantsection' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _dominantsection($option) {	}

	/**
	 * Return SQL for 'dplcache' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _dplcache($option) {	}

	/**
	 * Return SQL for 'dplcacheperiod' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _dplcacheperiod($option) {	}

	/**
	 * Return SQL for 'eliminate' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _eliminate($option) {	}

	/**
	 * Return SQL for 'escapelinks' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _escapelinks($option) {	}

	/**
	 * Return SQL for 'execandexit' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _execandexit($option) {	}

	/**
	 * Return SQL for 'firstrevisionsince' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _firstrevisionsince($option) {
		if ($sFirstRevisionSince != '') {
			$sSqlCond_page_rev .= ' AND '.$this->tableNames['page'].'.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MIN(rev_aux_snc.rev_timestamp) FROM '.$this->tableNames['revision'].' AS rev_aux_snc WHERE rev_aux_snc.rev_page=rev.rev_page AND rev_aux_snc.rev_timestamp >= '.$sFirstRevisionSince.')';
		}
	}

	/**
	 * Return SQL for 'fixcategory' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _fixcategory($option) {	}

	/**
	 * Return SQL for 'format' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _format($option) {	}

	/**
	 * Return SQL for 'goal' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _goal($option) {	}

	/**
	 * Return SQL for 'headingcount' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _headingcount($option) {	}

	/**
	 * Return SQL for 'headingmode' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _headingmode($option) {	}

	/**
	 * Return SQL for 'hiddencategories' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _hiddencategories($option) {	}

	/**
	 * Return SQL for 'hitemattr' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _hitemattr($option) {	}

	/**
	 * Return SQL for 'hlistattr' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _hlistattr($option) {	}

	/**
	 * Return SQL for 'ignorecase' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _ignorecase($option) {	}

	/**
	 * Return SQL for 'imagecontainer' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _imagecontainer($option) {
		$this->addTable('imagelinks', 'ic');
		if ($this->parameters->getParameter('openreferences')) {
			$where .= '(';
		} else {
			$where .= $this->tableNames['page'].'.page_namespace=\'6\' AND '.$this->tableNames['page'].'.page_title=ic.il_to AND (';
		}
		$i = 0;
		foreach ($option as $link) {
			if ($i > 0) {
				$where .= ' OR ';
			}
			if ($bIgnoreCase) {
				$where .= "LOWER(CAST(ic.il_from AS char)=LOWER(".self::$DB->addQuotes($link->getArticleID()).')';
			} else {
				$where .= "ic.il_from=".self::$DB->addQuotes($link->getArticleID());
			}
			$i++;
		}
		$where .= ')';
		$this->addWhere($where);
	}

	/**
	 * Return SQL for 'imageused' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _imageused($option) {
		$this->addTable('imagelinks', 'il');
		$this->addSelect(['image_sel_title' => 'il`.`il_to']);
		$where .= $this->tableNames['page'].'.page_id=il.il_from AND (';
		$i = 0;
		foreach ($aImageUsed as $link) {
			if ($i > 0) {
				$where .= ' OR ';
			}
			if ($bIgnoreCase) {
				$where .= "LOWER(CAST(il.il_to AS char))=LOWER(".self::$DB->addQuotes($link->getDbKey()).')';
			} else {
				$where .= "il.il_to=".self::$DB->addQuotes($link->getDbKey());
			}
			$i++;
		}
		$where .= ')';
		$this->addWhere($where);
	}

	/**
	 * Return SQL for 'include' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _include($option) {	}

	/**
	 * Return SQL for 'includematch' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _includematch($option) {	}

	/**
	 * Return SQL for 'includematchparsed' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _includematchparsed($option) {	}

	/**
	 * Return SQL for 'includemaxlength' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _includemaxlength($option) {	}

	/**
	 * Return SQL for 'includenotmatch' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _includenotmatch($option) {	}

	/**
	 * Return SQL for 'includenotmatchparsed' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _includenotmatchparsed($option) {	}

	/**
	 * Return SQL for 'includepage' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _includepage($option) {	}

	/**
	 * Return SQL for 'includesubpages' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _includesubpages($option) {	}

	/**
	 * Return SQL for 'includetrim' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _includetrim($option) {	}

	/**
	 * Return SQL for 'inlinetext' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _inlinetext($option) {	}

	/**
	 * Return SQL for 'itemattr' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _itemattr($option) {	}

	/**
	 * Return SQL for 'lastmodifiedby' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _lastmodifiedby($option) {
	    $sSqlCond_page_rev .= ' AND '.self::$DB->addQuotes($parameters->getParameter('lastmodifiedby')).' = (SELECT rev_user_text FROM '.$this->tableNames['revision'].' WHERE '.$this->tableNames['revision'].'.rev_page=page_id ORDER BY '.$this->tableNames['revision'].'.rev_timestamp DESC LIMIT 1)';
	}

	/**
	 * Return SQL for 'lastrevisionbefore' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _lastrevisionbefore($option) {
		if ($sLastRevisionBefore != '') {
			$sSqlCond_page_rev .= ' AND '.$this->tableNames['page'].'.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MAX(rev_aux_bef.rev_timestamp) FROM '.$this->tableNames['revision'].' AS rev_aux_bef WHERE rev_aux_bef.rev_page=rev.rev_page AND rev_aux_bef.rev_timestamp < '.$sLastRevisionBefore.')';
		}
	}

	/**
	 * Return SQL for 'linksfrom' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _linksfrom($option) {
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
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _linksto($option) {
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
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _linkstoexternal($option) {
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
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _listattr($option) {	}

	/**
	 * Return SQL for 'listseparators' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _listseparators($option) {	}

	/**
	 * Return SQL for 'maxrevisions' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _maxrevisions($option) {
		$this->addWhere("((SELECT count(rev_aux3.rev_page) FROM {$this->tableNames['revision']} AS rev_aux3 WHERE rev_aux3.rev_page=page.page_id) <= $iMaxRevisions)");
	}

	/**
	 * Return SQL for 'minoredits' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _minoredits($option) {
		if (isset($sMinorEdits) && $sMinorEdits == 'exclude') {
			$this->addWhere("rev_minor_edit=0");
		}
	}

	/**
	 * Return SQL for 'minrevisions' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _minrevisions($option) {
		$this->addWhere("((SELECT count(rev_aux2.rev_page) FROM {$this->tableNames['revision']} AS rev_aux2 WHERE rev_aux2.rev_page=page.page_id) >= $iMinRevisions)");
	}

	/**
	 * Return SQL for 'mode' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _mode($option) {	}

	/**
	 * Return SQL for 'modifiedby' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _modifiedby($option) {
	    $sSqlChangeRevisionTable = $this->tableNames['revision'].' AS change_rev, ';
	    $sSqlCond_page_rev .= ' AND '.self::$DB->addQuotes($parameters->getParameter('modifiedby')).' = change_rev.rev_user_text'.' AND change_rev.rev_page = page_id';
	}

	/**
	 * Return SQL for 'multisecseparators' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _multisecseparators($option) {	}

	/**
	 * Return SQL for 'namespace' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _namespace($option) {
		if (!empty($aNamespaces)) {
			if ($this->parameters->getParameter('openreferences')) {
				$this->addWhere("{$this->tableNames['pagelinks']}.pl_namespace IN (".self::$DB->makeList($aNamespaces).")");
			} else {
				$this->addWhere("{$this->tableNames['page']}.page_namespace IN (".self::$DB->makeList($aNamespaces).")");
			}
		}
	}

	/**
	 * Return SQL for 'noresultsfooter' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _noresultsfooter($option) {	}

	/**
	 * Return SQL for 'noresultsheader' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _noresultsheader($option) {	}

	/**
	 * Return SQL for 'notcategory' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notcategory($option) {
		//@TODO: The table incremental variable needs to be on the object.
		for ($i = 0; $i < $iExcludeCatCount; $i++) {
			$sSqlSelectFrom .= ' LEFT OUTER JOIN '.$this->tableNames['categorylinks'].' AS cl'.$iClTable.' ON '.$this->tableNames['page'].'.page_id=cl'.$iClTable.'.cl_from'.' AND cl'.$iClTable.'.cl_to'.$sNotCategoryComparisonMode.self::$DB->addQuotes(str_replace(' ', '_', $aExcludeCategories[$i]));
			$this->addWhere("cl{$iClTable}.cl_to IS NULL");
			$iClTable++;
		}
	}

	/**
	 * Return SQL for 'notcategorymatch' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notcategorymatch($option) {	}

	/**
	 * Return SQL for 'notcategoryregexp' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notcategoryregexp($option) {	}

	/**
	 * Return SQL for 'notcreatedby' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notcreatedby($option) {
	    $sSqlNoCreationRevisionTable = $this->tableNames['revision'].' AS no_creation_rev, ';
	    $sSqlCond_page_rev .= ' AND '.self::$DB->addQuotes($parameters->getParameter('notcreatedby')).' != no_creation_rev.rev_user_text'.' AND no_creation_rev.rev_page = page_id'.' AND no_creation_rev.rev_parent_id = 0';
	}

	/**
	 * Return SQL for 'notlastmodifiedby' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notlastmodifiedby($option) {
	    $sSqlCond_page_rev .= ' AND '.self::$DB->addQuotes($parameters->getParameter('notlastmodifiedby')).' != (SELECT rev_user_text FROM '.$this->tableNames['revision'].' WHERE '.$this->tableNames['revision'].'.rev_page=page_id ORDER BY '.$this->tableNames['revision'].'.rev_timestamp DESC LIMIT 1)';
	}

	/**
	 * Return SQL for 'notlinksfrom' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notlinksfrom($option) {
		if (count($aNotLinksFrom) > 0) {
			if ($this->parameters->getParameter('openreferences')) {
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
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notlinksto($option) {	}

	/**
	 * Return SQL for 'notmodifiedby' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notmodifiedby($option) {
	    $sSqlCond_page_rev .= 'NOT EXISTS (SELECT 1 FROM '.$this->tableNames['revision'].' WHERE '.$this->tableNames['revision'].'.rev_page=page_id AND '.$this->tableNames['revision'].'.rev_user_text = '.self::$DB->addQuotes($parameters->getParameter('notmodifiedby')).' LIMIT 1)';
	}

	/**
	 * Return SQL for 'notnamespace' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notnamespace($option) {
		if (!empty($aExcludeNamespaces)) {
			if ($this->parameters->getParameter('openreferences')) {
				$this->addWhere($this->tableNames['pagelinks'].".pl_namespace NOT IN (".self::$DB->makeList($aExcludeNamespaces).")");
			} else {
				$this->addWhere($this->tableNames['page'].".page_namespace NOT IN (".self::$DB->makeList($aExcludeNamespaces).")");
			}
		}
	}

	/**
	 * Return SQL for 'nottitlematch' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _nottitlematch($option) {
		$where .= '(';
		$i = 0;
		foreach ($aNotTitleMatch as $link) {
			if ($i > 0) {
				$where .= ' OR ';
			}
			if ($this->parameters->getParameter('openreferences')) {
				if ($bIgnoreCase) {
					$where .= 'LOWER(CAST(pl_title AS char))'.$sNotTitleMatchMode.'LOWER('.self::$DB->addQuotes($link).')';
				} else {
					$where .= 'pl_title'.$sNotTitleMatchMode.self::$DB->addQuotes($link);
				}
			} else {
				if ($bIgnoreCase) {
					$where .= 'LOWER(CAST('.$this->tableNames['page'].'.page_title AS char))'.$sNotTitleMatchMode.'LOWER('.self::$DB->addQuotes($link).')';
				} else {
					$where .= $this->tableNames['page'].'.page_title'.$sNotTitleMatchMode.self::$DB->addQuotes($link);
				}
			}
			$i++;
		}
		$where .= ')';
		$this->addWhere($where);
	}

	/**
	 * Return SQL for 'nottitleregexp' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _nottitleregexp($option) {	}

	/**
	 * Return SQL for 'notuses' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notuses($option) {
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
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _offset($option) {	}

	/**
	 * Return SQL for 'oneresultfooter' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _oneresultfooter($option) {	}

	/**
	 * Return SQL for 'oneresultheader' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _oneresultheader($option) {	}

	/**
	 * Return SQL for 'openreferences' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _openreferences($option) {	}

	/**
	 * Return SQL for 'order' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _order($option) {	}

	/**
	 * Return SQL for 'ordercollation' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _ordercollation($option) {	}

	/**
	 * Return SQL for 'ordermethod' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _ordermethod($option) {	}

	/**
	 * Return SQL for 'qualitypages' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _qualitypages($option) {	}

	/**
	 * Return SQL for 'randomcount' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _randomcount($option) {	}

	/**
	 * Return SQL for 'redirects' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _redirects($option) {
		if (!$this->parameters->getParameter('openreferences')) {
			switch ($sRedirects) {
				case 'only':
					$this->addWhere($this->tableNames['page'].".page_is_redirect=1");
					break;
				case 'exclude':
					$this->addWhere($this->tableNames['page'].".page_is_redirect=0");
					break;
			}
		}
	}

	/**
	 * Return SQL for 'replaceintitle' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _replaceintitle($option) {	}

	/**
	 * Return SQL for 'reset' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _reset($option) {	}

	/**
	 * Return SQL for 'resultsfooter' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _resultsfooter($option) {	}

	/**
	 * Return SQL for 'resultsheader' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _resultsheader($option) {	}

	/**
	 * Return SQL for 'rowcolformat' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _rowcolformat($option) {	}

	/**
	 * Return SQL for 'rows' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _rows($option) {	}

	/**
	 * Return SQL for 'rowsize' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _rowsize($option) {	}

	/**
	 * Return SQL for 'scroll' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _scroll($option) {	}

	/**
	 * Return SQL for 'secseparators' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _secseparators($option) {	}

	/**
	 * Return SQL for 'showcurid' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _showcurid($option) {	}

	/**
	 * Return SQL for 'shownamespace' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _shownamespace($option) {	}

	/**
	 * Return SQL for 'skipthispage' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _skipthispage($option) {	}

	/**
	 * Return SQL for 'stablepages' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _stablepages($option) {	}

	/**
	 * Return SQL for 'suppresserrors' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _suppresserrors($option) {	}

	/**
	 * Return SQL for 'table' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _table($option) {	}

	/**
	 * Return SQL for 'tablerow' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _tablerow($option) {	}

	/**
	 * Return SQL for 'tablesortcol' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _tablesortcol($option) {	}

	/**
	 * Return SQL for 'title' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _title($option) {
		if ($sTitleIs != '') {
			if ($bIgnoreCase) {
				$this->addWhere("LOWER(CAST('.$this->tableNames['page'].'.page_title AS char)) = LOWER(".self::$DB->addQuotes($sTitleIs).")");
			} else {
				$this->addWhere($this->tableNames['page'].'.page_title = '.self::$DB->addQuotes($sTitleIs));
			}
		}
	}

	/**
	 * Return SQL for 'titlegt' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _titlegt($option) {
		if (substr($sTitleGE, 0, 2) == '=_') {
			if ($this->parameters->getParameter('openreferences')) {
				$where .= 'pl_title >='.self::$DB->addQuotes(substr($sTitleGE, 2));
			} else {
				$where .= $this->tableNames['page'].'.page_title >='.self::$DB->addQuotes(substr($sTitleGE, 2));
			}
		} else {
			if ($this->parameters->getParameter('openreferences')) {
				$where .= 'pl_title >'.self::$DB->addQuotes($sTitleGE);
			} else {
				$where .= $this->tableNames['page'].'.page_title >'.self::$DB->addQuotes($sTitleGE);
			}
		}
		$where .= ')';
		$this->addWhere($where);
	}

	/**
	 * Return SQL for 'titlelt' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _titlelt($option) {
		if (substr($sTitleLE, 0, 2) == '=_') {
			if ($this->parameters->getParameter('openreferences')) {
				$where .= 'pl_title <='.self::$DB->addQuotes(substr($sTitleLE, 2));
			} else {
				$where .= $this->tableNames['page'].'.page_title <='.self::$DB->addQuotes(substr($sTitleLE, 2));
			}
		} else {
			if ($this->parameters->getParameter('openreferences')) {
				$where .= 'pl_title <'.self::$DB->addQuotes($sTitleLE);
			} else {
				$where .= $this->tableNames['page'].'.page_title <'.self::$DB->addQuotes($sTitleLE);
			}
		}
		$where .= ')';
		$this->addWhere($where);
	}

	/**
	 * Return SQL for 'titlematch' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _titlematch($option) {
		$where = '(';
		$i = 0;
		foreach ($aTitleMatch as $link) {
			if ($i > 0) {
				$where .= ' OR ';
			}
			if ($this->parameters->getParameter('openreferences')) {
				if ($bIgnoreCase) {
					$where .= 'LOWER(CAST(pl_title AS char))'.$sTitleMatchMode.strtolower(self::$DB->addQuotes($link));
				} else {
					$where .= 'pl_title'.$sTitleMatchMode.self::$DB->addQuotes($link);
				}
			} else {
				if ($bIgnoreCase) {
					$where .= 'LOWER(CAST('.$this->tableNames['page'].'.page_title AS char))'.$sTitleMatchMode.strtolower(self::$DB->addQuotes($link));
				} else {
					$where .= $this->tableNames['page'].'.page_title'.$sTitleMatchMode.self::$DB->addQuotes($link);
				}
			}
			$i++;
		}
		$where .= ')';
		$this->addWhere($where);
	}

	/**
	 * Return SQL for 'titlemaxlength' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _titlemaxlength($option) {	}

	/**
	 * Return SQL for 'titleregexp' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _titleregexp($option) {	}

	/**
	 * Return SQL for 'updaterules' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _updaterules($option) {	}

	/**
	 * Return SQL for 'usedby' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _usedby($option) {
		if (count($aUsedBy) > 0) {
			if ($this->parameters->getParameter('openreferences')) {
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
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _userdateformat($option) {
	}

	/**
	 * Return SQL for 'uses' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _uses($option) {
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