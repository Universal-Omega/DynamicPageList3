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
	private $tableNames = [];

	/**
	 * Parameters that have already been processed.
	 *
	 * @var		array
	 */
	private $parametersProcessed = [];

	/**
	 * Select Fields
	 *
	 * @var		array
	 */
	private $select = [];

	/**
	 * Prefixed and escaped table names.
	 *
	 * @var		array
	 */
	private $tables = [];

	/**
	 * Where Clauses
	 *
	 * @var		array
	 */
	private $where = [];

	/**
	 * Group By Clauses
	 *
	 * @var		array
	 */
	private $groupBy = [];

	/**
	 * Order By Clauses
	 *
	 * @var		array
	 */
	private $orderBy = [];

	/**
	 * Join Clauses
	 *
	 * @var		array
	 */
	private $join = [];

	/**
	 * Limit
	 *
	 * @var		integer
	 */
	private $limit = false;

	/**
	 * Offset
	 *
	 * @var		integer
	 */
	private $offset = false;

	/**
	 * Order By Direction
	 *
	 * @var		string
	 */
	private $direction = 'ASC';

	/**
	 * Distinct Results
	 *
	 * @var		boolean
	 */
	private $distinct = true;

	/**
	 * Character Set Collation
	 *
	 * @var		string
	 */
	private $collation = false;

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

		//Add things that are always part of the query.
		$this->addTable('page', 'page');
		$this->addSelect([$this->tableNames['page'].'.page_namespace']);
		$this->addSelect([$this->tableNames['page'].'.page_title']);
		$this->addSelect([$this->tableNames['page'].'.page_id']);

		$query['select'] = null;
		if (count($this->select)) {
			foreach ($this->select as $alias => $select) {
				if (!is_numeric($alias)) {
					$_select[] = "{$select} AS {$alias}";
				} else {
					$_select[] = "{$select}";
				}
			}
			$query['select'] = implode(', ', $_select);
		}

		$query['tables'] = null;
		if (count($this->tables)) {
			foreach ($this->tables as $alias => $table) {
				$_tables[] = "{$table} AS {$alias}";
			}
			$query['tables'] = implode(', ', $_tables);
		}

		$query['join'] = null;
		if (count($this->join)) {
			$query['join'] = implode(' ', $this->join);
		}

		$query['where'] = null;
		if (count($this->where)) {
			$query['where'] = implode(' AND ', $this->where);
		}
		var_dump($this->join);
		$limit = null;
		if ($this->offset === false && $this->limit > 0) {
			$limit = "LIMIT {$this->limit}";
		} elseif ($this->offset > 0 && $this->limit > 0) {
			$limit = "LIMIT {$this->offset}, {$this->limit}";
		} elseif ($this->offset > 0 && !$this->limit) {
			$limit = "LIMIT {$this->offset}, ".$this->parameters->getData('count')['default'];
		}


		/*if ($acceptOpenReferences) {
			// SELECT ... FROM
			if (count($aImageContainer) > 0) {
				$sSqlSelectFrom = "SELECT $sSqlCalcFoundRows $sSqlDistinct " . $sSqlCl_to . 'ic.il_to, ' . $sSqlSelPage . "ic.il_to AS sortkey" . ' FROM ' . $this->tableNames['imagelinks'] . ' AS ic';
			} else {
				$sSqlSelectFrom = "SELECT $sSqlCalcFoundRows $sSqlDistinct " . $sSqlCl_to . 'pl_namespace, pl_title' . $sSqlSelPage . $sSqlSortkey . ' FROM ' . $this->tableNames['pagelinks'];
			}
		} else {
			$sSqlSelectFrom = "SELECT $sSqlCalcFoundRows $sSqlDistinct " . $sSqlCl_to . $this->tableNames['page'] . '.page_namespace AS page_namespace,' . $this->tableNames['page'] . '.page_title AS page_title,' . $this->tableNames['page'] . '.page_id AS page_id' . $sSqlSelPage . $sSqlSortkey . $sSqlPage_counter . $sSqlPage_size . $sSqlPage_touched . $sSqlRev_user . $sSqlRev_timestamp . $sSqlRev_id . $sSqlCats . $sSqlCl_timestamp . ' FROM ' . $sSqlRevisionTable . $sSqlCreationRevisionTable . $sSqlNoCreationRevisionTable . $sSqlChangeRevisionTable . $sSqlRCTable . $sSqlPageLinksTable . $sSqlExternalLinksTable . $this->tableNames['page'];
		}*/
		$sql = "SELECT ".($this->distinct ? "DISTINCT " : null)."{$query['select']} FROM {$query['tables']} {$query['join']} WHERE {$query['where']}".(count($this->groupBy) ? " GROUP BY ".implode(', ', $this->groupBy) : null).(count($this->orderBy) ? " ORDER BY ".implode(', ', $this->orderBy)." ".$this->direction : null).($limit ? " ".$limit : null);

		return $sql;
	}

	/**
	 * Return prefixed and quoted tables that are needed.
	 *
	 * @access	public
	 * @return	array	Prepared table names.
	 */
	static public function getTableNames() {
		$DB = wfGetDB(DB_SLAVE);
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
			$tableNames[$table] = $DB->tableName($table);
		}
		return $tableNames;
	}

	/**
	 * Add a table to the output.
	 *
	 * @access	public
	 * @param	string	Raw Table Name - Will be ran through tableName().
	 * @param	string	Table Alias
	 * @return	boolean Success - Added, false if the table alias already exists.
	 */
	public function addTable($table, $alias) {
		if (empty($table)) {
			throw new \MWException(__METHOD__.': An empty table name was passed.');
		}
		if (empty($alias) || is_numeric($alias)) {
			throw new \MWException(__METHOD__.': An empty or numeric table alias was passed.');
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
	 * Where clauses get imploded together with AND at the end.	 Any custom where clauses should be preformed before placed into here.
	 *
	 * @access	public
	 * @param	string	Where clause
	 * @return	boolean Success
	 */
	public function addWhere($where) {
		if (empty($where)) {
			throw new \MWException(__METHOD__.': An empty where clause was passed.');
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
			throw new \MWException(__METHOD__.': A non-array was passed.');
		}
		foreach ($fields as $alias => $field) {
			if (!is_numeric($alias) && array_key_exists($alias, $this->select) && $this->select[$alias] != $field) {
				//In case of a code bug that is overwriting an existing field alias throw an exception.
				throw new \MWException(__METHOD__.": Attempted to overwrite existing field alias `{$this->select[$alias]}` AS `{$alias}` with `{$field}` AS `{$alias}`.");
			}
			//String alias and does not exist already.
			if (!is_numeric($alias) && !array_key_exists($alias, $this->select)) {
				$this->select[$alias] = $field;
			}

			if (is_numeric($alias) && !in_array($field, $this->select)) {
				$this->select[] = $field;
			}
		}
		return true;
	}

	/**
	 * Add a GROUP BY clause to the output.
	 *
	 * @access	public
	 * @param	string	Group By Clause
	 * @return	boolean Success
	 */
	public function addGroupBy($groupBy) {
		if (empty($groupBy)) {
			throw new \MWException(__METHOD__.': An empty group by clause was passed.');
		}
		$this->groupBy[] = $groupBy;
		return true;
	}

	/**
	 * Add a ORDER BY clause to the output.
	 *
	 * @access	public
	 * @param	string	Order By Clause
	 * @return	boolean Success
	 */
	public function addOrderBy($orderBy) {
		if (empty($orderBy)) {
			throw new \MWException(__METHOD__.': An empty order by clause was passed.');
		}
		$this->orderBy[] = $orderBy;
		return true;
	}

	/**
	 * Add a JOIN clause to the output.
	 *
	 * @access	public
	 * @param	string	Join clause
	 * @return	boolean Success
	 */
	public function addJoin($join) {
		if (empty($join)) {
			throw new \MWException(__METHOD__.': An join clause was passed.');
		}
		$this->join[] = $join;
		return true;
	}

	/**
	 * Set the limit.
	 *
	 * @access	public
	 * @param	mixed	Integer limit or false to unset.
	 * @return	boolean Success
	 */
	public function setLimit($limit) {
		if (is_numeric($limit)) {
			$this->limit = intval($limit);
		} else {
			$this->limit = false;
		}
		return true;
	}

	/**
	 * Set the offset.
	 *
	 * @access	public
	 * @param	mixed	Integer offset or false to unset.
	 * @return	boolean Success
	 */
	public function setOffset($offset) {
		if (is_numeric($offset)) {
			$this->offset = invtal($offset);
		} else {
			$this->offset = false;
		}
		return true;
	}

	/**
	 * Set the ORDER BY direction
	 *
	 * @access	public
	 * @param	string	SQL direction key word.
	 * @return	boolean Success
	 */
	public function setOrderDir($direction) {
		$this->direction = $direction;
		return true;
	}

	/**
	 * Set the character set collation.
	 *
	 * @access	public
	 * @param	string	Collation
	 * @return	void
	 */
	public function setCollation($collation) {
		$this->collation = $collation;
	}

	/**
	 * Recursively get and return an array of subcategories.
	 *
	 * @access	public
	 * @param	string	Category Name
	 * @param	integer	[Optional] Maximum Depth
	 * @return	array	Subcategories
	 */
	static private function getSubcategories($categoryName, $depth = 1) {
		if ($depth > 2) {
			//Hard constrain depth because lots of recursion is bad.
			$depth = 2;
		}
		$categories = [];
		$result = $this->DB->select(
			['page', 'categorylinks'],
			['page_title'],
			[
				'page_namespace'		=> intval(NS_CATEGORY),
				'categorylinks.cl_to'	=> str_replace(' ', '_', $categoryName)
			],
			__METHOD__,
			['DISTINCT'],
			[
				'categorylinks' => [
					'INNER JOIN', 'page.page_id = categorylinks.cl_from'
				]
			]
		);
		while ($row = $result->fetchRow()) {
			if ($depth > 1) {
				$categories[] = array_merge($categories, self::getSubcategories($row['page_title'], $depth - 1));
			} else {
				$categories[] = $row['page_title'];
			}
		}
		$categories = array_unique($categories);
		$this->DB->freeResult($result);
		return $categories;
	}

	/**
	 * Set SQL for 'addauthor' parameter.
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
	 * Set SQL for 'addcategories' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addcategories($option) {
		$this->addSelect(["GROUP_CONCAT(DISTINCT cl_gc.cl_to ORDER BY cl_gc.cl_to ASC SEPARATOR ' | ') AS cats"]);
		$this->addJoin('LEFT OUTER JOIN categorylinks AS cl_gc ON page_id = cl_gc.cl_from');
		$this->addGroupBy($this->tableNames['page'].'.page_id');
	}

	/**
	 * Set SQL for 'addcontribution' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addcontribution($option) {
		$this->addTable('recentchanges', 'rc');
		$this->addSelect(['SUM( ABS( rc.rc_new_len - rc.rc_old_len ) ) AS contribution, rc.rc_user_text AS contributor']);
		$this->addWhere("page.page_id=rc.rc_cur_id");
		$this->addGroupBy('rc.rc_cur_id');
	}

	/**
	 * Set SQL for 'addfirstcategorydate' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addfirstcategorydate($option) {
		$this->addSelect(["DATE_FORMAT(cl0.cl_timestamp, '%Y%m%d%H%i%s') AS cl_timestamp"]);
	}

	/**
	 * Set SQL for 'addlasteditor' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addlasteditor($option) {
		//Addlasteditor can not be used with addauthor.
		if (!$this->parametersProcessed['addauthor']) {
			$this->addTable('revision', 'rev');
			$this->addWhere($this->tableNames['page'].'.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MAX(rev_aux_max.rev_timestamp) FROM '.$this->tableNames['revision'].' AS rev_aux_max WHERE rev_aux_max.rev_page=rev.rev_page )');
			$this->addSelect(['rev_user', 'rev_user_text', 'rev_comment']);
		}
	}

	/**
	 * Set SQL for 'addpagecounter' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addpagecounter($option) {
		$this->addSelect(["page_counter" => "{$this->tableNames['page']}.page_counter"]);
	}

	/**
	 * Set SQL for 'addpagesize' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addpagesize($option) {
		$this->addSelect(["page_len" => "{$this->tableNames['page']}.page_len"]);
	}

	/**
	 * Set SQL for 'addpagetoucheddate' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _addpagetoucheddate($option) {
		//@TODO: Need to check if this was added by the order methods or call this function to add it from there.
		$this->addSelect(["page_touched" => "{$this->tableNames['page']}.page_touched"]);
	}

	/**
	 * Set SQL for 'adduser' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _adduser($option) {
		$this->addSelect(['rev_user', 'rev_user_text', 'rev_comment']);
	}

	/**
	 * Set SQL for 'allrevisionsbefore' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _allrevisionsbefore($option) {
		$this->addTable('revision', 'rev');
		$this->addSelect(['rev_id', 'rev_timestamp']);
		$this->addWhere($this->tableNames['page'].'.page_id=rev.rev_page AND rev.rev_timestamp < '.$this->DB->addQuotes($option));
	}

	/**
	 * Set SQL for 'allrevisionssince' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _allrevisionssince($option) {
		$this->addTable('revision', 'rev');
		$this->addSelect(['rev_id', 'rev_timestamp']);
		$this->addWhere($this->tableNames['page'].'.page_id=rev.rev_page AND rev.rev_timestamp >= '.$this->DB->addQuotes($option));
	}

	/**
	 * Set SQL for 'articlecategory' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _articlecategory($option) {
		$this->addWhere("{$this->tableNames['page']}.page_title IN (SELECT p2.page_title FROM {$this->tableNames['page']} p2 INNER JOIN {$this->tableNames['categorylinks']} clstc ON (clstc.cl_from = p2.page_id AND clstc.cl_to = ".$this->DB->addQuotes($option).") WHERE p2.page_namespace = 0)");
	}

	/**
	 * Set SQL for 'categoriesminmax' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _categoriesminmax($option) {
		if (is_numeric($option[0])) {
			$where .= $option[0].' <= (SELECT count(*) FROM '.$this->tableNames['categorylinks'].' WHERE '.$this->tableNames['categorylinks'].'.cl_from=page_id)';
		}
		if (is_numeric($option[1])) {
			$where .= ' AND '.$option[1].' >= (SELECT count(*) FROM '.$this->tableNames['categorylinks'].' WHERE '.$this->tableNames['categorylinks'].'.cl_from=page_id)';
		}
		$this->addWhere($where);
	}

	/**
	 * Set SQL for 'category' parameter.  This includes 'category', 'categorymatch', and 'categoryregexp'.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _category($option) {
		if (array_key_exists('AND', $option)) {
			$ands = $options['AND'];
			unset($options['AND']);
			$options = array_merge($options, $ands);
		}
		foreach ($option as $comparisonType => $categories) {
			$i++;
			$addJoin = "INNER JOIN ".(in_array('', $categories) ? $this->tableNames['dpl_clview'] : $this->tableNames['categorylinks'])." AS cl{$i} ON {$this->tableNames['page']}.page_id=cl{$i}.cl_from AND (";
			foreach ($categories as $category) {
				$ors[] = 'cl'.$i.'.cl_to '.(empty($comparisonType) || $comparisonType == 'OR' ? '=' : $comparisonType).' '.$this->DB->addQuotes(str_replace(' ', '_', $category));
			}
			$addJoin .= implode(' OR ', $ors);
			$addJoin .= ')';
			$this->addJoin($addJoin);
		}
	}

	/**
	 * Set SQL for 'notcategory' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notcategory($option) {
		foreach ($option as $comparisonType => $categories) {
			foreach ($categories as $category) {
				$i++;
				$this->addJoin("LEFT OUTER JOIN {$this->tableNames['categorylinks']} AS ecl{$i} ON {$this->tableNames['page']}.page_id=ecl{$i}.cl_from AND ecl{$i}.cl_to {$comparisonType}".$this->DB->addQuotes(str_replace(' ', '_', $category)));
				$this->addWhere("ecl{$i}.cl_to IS NULL");
			}
		}
	}

	/**
	 * Set SQL for 'createdby' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _createdby($option) {
		$this->addTable('revision', 'creation_rev');
		$this->addSelect(['rev_user', 'rev_user_text', 'rev_comment']);
		$this->addWhere($this->DB->addQuotes($option).' = creation_rev.rev_user_text'.' AND creation_rev.rev_page = page_id'.' AND creation_rev.rev_parent_id = 0');
	}

	/**
	 * Set SQL for 'distinct' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _distinct($option) {
		if ($option == 'strict' || $option === true) {
			//@TODO: Check to add $sSqlGroupBy = 'page_title';
			$this->distinct = true;
		} else {
			//@TODO: Check to remove $sSqlGroupBy = 'page_title';
			$this->distinct = false;
		}
	}

	/**
	 * Set SQL for 'firstrevisionsince' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _firstrevisionsince($option) {
		$this->addTable('revision', 'rev');
		$this->addSelect(['rev_id', 'rev_timestamp']);
		$this->addWhere($this->tableNames['page'].'.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MIN(rev_aux_snc.rev_timestamp) FROM '.$this->tableNames['revision'].' AS rev_aux_snc WHERE rev_aux_snc.rev_page=rev.rev_page AND rev_aux_snc.rev_timestamp >= '.$this->DB->addQuotes($option).')');
	}

	/**
	 * Set SQL for 'format' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _format($option) { }

	/**
	 * Set SQL for 'goal' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _goal($option) {
		//@TODO: This function.  Does some weird changes to the output.
		//@TODO: Goal turns off offset/limit when in category mode.
	}

	/**
	 * Set SQL for 'hiddencategories' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _hiddencategories($option) {
		//@TODO: Unfinished functionality!  Never implemented by original by original author.
	}

	/**
	 * Set SQL for 'imagecontainer' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _imagecontainer($option) {
		$this->addTable('imagelinks', 'ic');
		$this->addSelect(['sortkey' => 'il.il_to']);
		if ($this->parameters->getParameter('openreferences')) {
			$where .= '(';
		} else {
			$where .= "{$this->tableNames['page']}.page_namespace=".NS_FILE." AND {$this->tableNames['page']}.page_title=ic.il_to AND (";
		}
		$i = 0;
		foreach ($option as $link) {
			if ($i > 0) {
				$where .= ' OR ';
			}
			if ($this->parameters->getParameter('ignorecase')) {
				$where .= "LOWER(CAST(ic.il_from AS char)=LOWER(".$this->DB->addQuotes($link->getArticleID()).')';
			} else {
				$where .= "ic.il_from=".$this->DB->addQuotes($link->getArticleID());
			}
			$i++;
		}
		$where .= ')';
		$this->addWhere($where);
	}

	/**
	 * Set SQL for 'imageused' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _imageused($option) {
		$this->addTable('imagelinks', 'il');
		$this->addSelect(['image_sel_title' => 'il.il_to']);
		$where .= $this->tableNames['page'].'.page_id=il.il_from AND (';
		$i = 0;
		foreach ($option as $link) {
			if ($i > 0) {
				$where .= ' OR ';
			}
			if ($this->parameters->getParameter('ignorecase')) {
				$where .= "LOWER(CAST(il.il_to AS char))=LOWER(".$this->DB->addQuotes($link->getDbKey()).')';
			} else {
				$where .= "il.il_to=".$this->DB->addQuotes($link->getDbKey());
			}
			$i++;
		}
		$where .= ')';
		$this->addWhere($where);
	}

	/**
	 * Set SQL for 'lastmodifiedby' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _lastmodifiedby($option) {
	   $this->addWhere($this->DB->addQuotes($option).' = (SELECT rev_user_text FROM '.$this->tableNames['revision'].' WHERE '.$this->tableNames['revision'].'.rev_page=page_id ORDER BY '.$this->tableNames['revision'].'.rev_timestamp DESC LIMIT 1)');
	}

	/**
	 * Set SQL for 'lastrevisionbefore' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _lastrevisionbefore($option) {
		$this->addTable('revision', 'rev');
		$this->addSelect(['rev_id', 'rev_timestamp']);
		$this->addWhere($this->tableNames['page'].'.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MAX(rev_aux_bef.rev_timestamp) FROM '.$this->tableNames['revision'].' AS rev_aux_bef WHERE rev_aux_bef.rev_page=rev.rev_page AND rev_aux_bef.rev_timestamp < '.$this->DB->addQuotes($option).')');
	}

	/**
	 * Set SQL for 'linksfrom' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _linksfrom($option) {
		//@TODO: Fix up this function.
		if ($this->parameters->getParameter('openreferences')) {
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
			$sSqlCond_page_pl .= ' AND ' . $sPageTable . '.page_namespace = plf.pl_namespace AND ' . $sPageTable . '.page_title = plf.pl_title AND pagesrc.page_id=plf.pl_from AND (';
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

	/**
	 * Set SQL for 'linksto' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _linksto($option) {
		//@TODO: Fix up this function.
		if (count($aLinksTo) > 0) {
			$sSqlPageLinksTable .= $this->tableNames['pagelinks'].' AS pl, ';
			$sSqlCond_page_pl .= ' AND '.$this->tableNames['page'].'.page_id=pl.pl_from AND ';
			$sSqlSelPage = ', pl.pl_title AS sel_title, pl.pl_namespace AS sel_ns';
			$n			 = 0;
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
					if ($this->parameters->getParameter('ignorecase')) {
						$sSqlCond_page_pl .= ' AND LOWER(CAST(pl.pl_title AS char))'.$operator.'LOWER('.$this->DB->addQuotes($link->getDbKey()).'))';
					} else {
						$sSqlCond_page_pl .= ' AND pl.pl_title'.$operator.$this->DB->addQuotes($link->getDbKey()).')';
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
					if ($this->parameters->getParameter('ignorecase')) {
						$sSqlCond_page_pl .= ' AND LOWER(CAST('.$this->tableNames['pagelinks'].'.pl_title AS char))'.$operator.'LOWER('.$this->DB->addQuotes($link->getDbKey()).')';
					} else {
						$sSqlCond_page_pl .= ' AND '.$this->tableNames['pagelinks'].'.pl_title'.$operator.$this->DB->addQuotes($link->getDbKey());
					}
					$sSqlCond_page_pl .= ')';
				}
				$sSqlCond_page_pl .= ')))';
			}
		}
	}

	/**
	 * Set SQL for 'notlinksfrom' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notlinksfrom($option) {
		if ($this->parameters->getParameter('openreferences')) {
			$where .= '(';
			$n = 0;
			foreach ($option as $links) {
				foreach ($links as $link) {
					if ($n > 0) {
						$where .= ' AND ';
					}
					$where .= 'pl_from <> '.intval($link->getArticleID()).' ';
					$n++;
				}
			}
			$where .= ')';
		} else {
			$where .= 'CONCAT(page_namespace,page_title) NOT IN (SELECT CONCAT('.$this->tableNames['pagelinks'].'.pl_namespace,'.$this->tableNames['pagelinks'].'.pl_title) from '.$this->tableNames['pagelinks'].' WHERE (';
			$n = 0;
			foreach ($option as $links) {
				foreach ($links as $link) {
					if ($n > 0) {
						$where .= ' OR ';
					}
					$where .= $this->tableNames['pagelinks'].'.pl_from='.intval($link->getArticleID()).' ';
					$n++;
				}
			}
			$where .= '))';
		}
		$this->addWhere($where);
	}

	/**
	 * Set SQL for 'notlinksto' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notlinksto($option) {
		//@TODO: Fix up this function.
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
					$sSqlCond_page_pl .= ' AND LOWER(CAST(' . $sPageLinksTable . '.pl_title AS char))' . $operator . 'LOWER(' . $dbr->addQuotes($link->getDbKey()) . '))';
				} else {
					$sSqlCond_page_pl .= ' AND ' . $sPageLinksTable . '.pl_title' . $operator . $dbr->addQuotes($link->getDbKey()) . ')';
				}
				$n++;
			}
		}
		$sSqlCond_page_pl .= ') )';
	}

	/**
	 * Set SQL for 'linkstoexternal' parameter.
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
			$n			 = 0;
			foreach ($aLinksToExternal as $linkGroup) {
				if (++$n > 1) {
					break;
				}
				$m = 0;
				foreach ($linkGroup as $link) {
					if (++$m > 1) {
						$sSqlCond_page_el .= ' OR ';
					}
					$sSqlCond_page_el .= '(el.el_to LIKE '.$this->DB->addQuotes($link).')';
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
					$sSqlCond_page_el .= '('.$this->tableNames['externallinks'].'.el_to LIKE '.$this->DB->addQuotes($link).')';
				}
				$sSqlCond_page_el .= ')))';
			}
		}
	}

	/**
	 * Set SQL for 'maxrevisions' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _maxrevisions($option) {
		$this->addWhere("((SELECT count(rev_aux3.rev_page) FROM {$this->tableNames['revision']} AS rev_aux3 WHERE rev_aux3.rev_page=page.page_id) <= $iMaxRevisions)");
	}

	/**
	 * Set SQL for 'minoredits' parameter.
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
	 * Set SQL for 'minrevisions' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _minrevisions($option) {
		$this->addWhere("((SELECT count(rev_aux2.rev_page) FROM {$this->tableNames['revision']} AS rev_aux2 WHERE rev_aux2.rev_page=page.page_id) >= $iMinRevisions)");
	}

	/**
	 * Set SQL for 'modifiedby' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _modifiedby($option) {
		$this->addTable('revision', 'change_rev');
		$this->addWhere($this->DB->addQuotes($option).' = change_rev.rev_user_text AND change_rev.rev_page = page_id');
	}

	/**
	 * Set SQL for 'namespace' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _namespace($option) {
		if ($this->parameters->getParameter('openreferences')) {
			$this->addWhere("{$this->tableNames['pagelinks']}.pl_namespace IN (".$this->DB->makeList($option).")");
		} else {
			$this->addWhere("{$this->tableNames['page']}.page_namespace IN (".$this->DB->makeList($option).")");
		}
	}

	/**
	 * Set SQL for 'notcreatedby' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notcreatedby($option) {
		$this->addTable('revision', 'no_creation_rev');
		$this->addWhere($this->DB->addQuotes($option).' != no_creation_rev.rev_user_text AND no_creation_rev.rev_page = page_id AND no_creation_rev.rev_parent_id = 0');
	}

	/**
	 * Set SQL for 'notlastmodifiedby' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notlastmodifiedby($option) {
		$this->addWhere($this->DB->addQuotes($option).' != (SELECT rev_user_text FROM '.$this->tableNames['revision'].' WHERE '.$this->tableNames['revision'].'.rev_page=page_id ORDER BY '.$this->tableNames['revision'].'.rev_timestamp DESC LIMIT 1)');
	}

	/**
	 * Set SQL for 'notmodifiedby' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notmodifiedby($option) {
		$this->addWhere('NOT EXISTS (SELECT 1 FROM '.$this->tableNames['revision'].' WHERE '.$this->tableNames['revision'].'.rev_page=page_id AND '.$this->tableNames['revision'].'.rev_user_text = '.$this->DB->addQuotes($option).' LIMIT 1)');
	}

	/**
	 * Set SQL for 'notnamespace' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notnamespace($option) {
		if ($this->parameters->getParameter('openreferences')) {
			$this->addWhere($this->tableNames['pagelinks'].".pl_namespace NOT IN (".$this->DB->makeList($option).")");
		} else {
			$this->addWhere($this->tableNames['page'].".page_namespace NOT IN (".$this->DB->makeList($option).")");
		}
	}

	/**
	 * Set SQL for 'notuses' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _notuses($option) {
		//@TODO: Fix up this function.
		if (count($aNotUses) > 0) {
			$sSqlCond_page_pl .= ' AND '.$this->tableNames['page'].'.page_id NOT IN (SELECT '.$this->tableNames['templatelinks'].'.tl_from FROM '.$this->tableNames['templatelinks'].' WHERE (';
			$n = 0;
			foreach ($aNotUses as $link) {
				if ($n > 0) {
					$sSqlCond_page_pl .= ' OR ';
				}
				$sSqlCond_page_pl .= '('.$this->tableNames['templatelinks'].'.tl_namespace='.intval($link->getNamespace());
				if ($this->parameters->getParameter('ignorecase')) {
					$sSqlCond_page_pl .= ' AND LOWER(CAST('.$this->tableNames['templatelinks'].'.tl_title AS char))=LOWER('.$this->DB->addQuotes($link->getDbKey()).'))';
				} else {
					$sSqlCond_page_pl .= ' AND '.$this->tableNames['templatelinks'].'.tl_title='.$this->DB->addQuotes($link->getDbKey()).')';
				}
				$n++;
			}
			$sSqlCond_page_pl .= ') )';
		}
	}

	/**
	 * Set SQL for 'count' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _count($option) {
		$this->setLimit($option);
	}

	/**
	 * Set SQL for 'offset' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _offset($option) {
		$this->setOffset($option);
	}

	/**
	 * Set SQL for 'order' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _order($option) {
		if (is_array($this->parameters->getParameter('ordermethod')) && $this->parameters->getParameter('ordermethod')[0] !== 'none') {
			if ($option == 'descending') {
				$this->setOrderDir('DESC');
			} else {
				$this->setOrderDir('ASC');
			}
		}
	}

	/**
	 * Set SQL for 'ordercollation' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _ordercollation($option) {
		$option = mb_strtolower($option);

		$results = $this->DB->query('SHOW CHARACTER SETS');
		if (!$results) {
			return false;
		}

		while ($row = $results->fetchRow()) {
			if ($option == $row['Default collation']) {
				$this->setCollation($option);
				break;
			}
		}
	}

	/**
	 * Set SQL for 'ordermethod' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _ordermethod($option) {
		//@TODO: Fix up this function.
		var_dump($option);
		foreach ($option as $orderMethod) {
			switch ($orderMethod) {
				case 'category':
					$this->addOrderBy('cl_head.cl_to');
					$this->addSelect(['cl_head.cl_to']); //Gives category headings in the result.
					//@TODO: Deferred parameter processing for checks?
					$_clTable = ((in_array('', $aCatHeadings) || in_array('', $aCatNotHeadings)) ? $this->tableNames['dpl_clview'] : $this->tableNames['categorylinks']) . ' AS cl_head'; // use dpl_clview if Uncategorized in headings
					$this->addJoin("LEFT OUTER JOIN {$_clTable} ON page_id = cl_head.cl_from");
					if (!empty($aCatHeadings)) {
						$this->addWhere("cl_head.cl_to IN (".$this->DB->makeList($aCatHeadings).")");
					}
					if (!empty($aCatNotHeadings)) {
						$this->addWhere("NOT (cl_head.cl_to IN (".$this->DB->makeList($aCatNotHeadings)."))");
					}
					break;
				case 'categoryadd':
					$this->addOrderBy('cl0.cl_timestamp');
					break;
				case 'counter':
					$this->addOrderBy('page_counter');
					break;
				case 'firstedit':
					$this->addOrderBy('rev_timestamp');
					$this->addTable('revision', 'rev');
					$sSqlRev_timestamp = ', rev_timestamp';
					//@TODO: Duplicate check.
					$this->addWhere("{$this->tableNames['page']}.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MAX(rev_aux.rev_timestamp) FROM {$this->tableNames['revision']} AS rev_aux WHERE rev_aux.rev_page=rev.rev_page )");
					break;
				case 'lastedit':
					if (\DynamicPageListHooks::isLikeIntersection()) {
						$this->addOrderBy('page_touched');
						$this->addSelect(["{$this->tableNames['page']}.page_touched"]);
					} else {
						$this->addOrderBy('rev_timestamp');
						$this->addTable('revision', 'rev');
						$this->addSelect(['rev_timestamp']);
						//@TODO: Duplicate check.
						$this->addWhere("{$this->tableNames['page']}.page_id=rev.rev_page AND rev.rev_timestamp=( SELECT MAX(rev_aux.rev_timestamp) FROM {$this->tableNames['revision']} AS rev_aux WHERE rev_aux.rev_page=rev.rev_page )");
					}
					break;
				case 'pagesel':
					$this->addOrderBy('sortkey');
					$this->addSelect(['CONCAT(pl.pl_namespace,pl.pl_title) '.($this->collation !== false ? 'COLLATE '.$this->collation : null).' as sortkey']);
					break;
				case 'pagetouched':
					$this->addOrderBy('page_touched');
					$this->addSelect(["{$this->tableNames['page']}.page_touched"]);
					break;
				case 'size':
					$this->addOrderBy('page_len');
					break;
				case 'sortkey':
					//@TODO: Fix up this namespace thingy here with the one below.
					$aStrictNs = array_slice(\DynamicPageListHooks::$allowedNamespaces, 1, count(\DynamicPageListHooks::$allowedNamespaces), true);
					$sSqlNsIdToText = 'CASE pl_namespace';
					foreach ($aStrictNs as $iNs => $sNs) {
						$sSqlNsIdToText .= ' WHEN ' . intval($iNs) . " THEN " . $this->DB->addQuotes($sNs);
					}
					$sSqlNsIdToText .= ' END';
					// If cl_sortkey is null (uncategorized page), generate a sortkey in the usual way (full page name, underscores replaced with spaces).
					// UTF-8 created problems with non-utf-8 MySQL databases
					//see line 2011 (order method sortkey requires category
					if (count($this->parameters->getParameter('category')) + count($this->parameters->getParameter('notcategory')) > 0) {
						if (in_array('category', $this->parameters->getParameter('ordermethod'))) {
							$this->addSelect(["IFNULL(cl_head.cl_sortkey, REPLACE(CONCAT( IF(" . $this->tableNames['page'] . ".page_namespace=0, '', CONCAT(" . $sSqlNsIdToText . ", ':')), " . $this->tableNames['page'] . ".page_title), '_', ' ')) ".($this->collation !== false ? 'COLLATE '.$this->collation : null)." as sortkey"]);
						} else {
							$this->addSelect(["IFNULL(cl0.cl_sortkey, REPLACE(CONCAT( IF(" . $this->tableNames['page'] . ".page_namespace=0, '', CONCAT(" . $sSqlNsIdToText . ", ':')), " . $this->tableNames['page'] . ".page_title), '_', ' ')) ".($this->collation !== false ? 'COLLATE '.$this->collation : null)." as sortkey"]);
						}
					} else {
						$this->addSelect(["REPLACE(CONCAT( IF(" . $this->tableNames['page'] . ".page_namespace=0, '', CONCAT(" . $sSqlNsIdToText . ", ':')), " . $this->tableNames['page'] . ".page_title), '_', ' ') ".($this->collation !== false ? 'COLLATE '.$this->collation : null)." as sortkey"]);
					}
					break;
				case 'titlewithoutnamespace':
					if ($this->parameters->getParameter('openreferences')) {
						$this->addOrderBy("pl_title");
					} else {
						$this->addOrderBy("page_title");
					}
					$this->addSelect(["{$this->tableNames['page']}.page_title ".($this->collation !== false ? 'COLLATE '.$this->collation : null)." as sortkey"]);
					break;
				case 'title':
					$aStrictNs = array_slice(\DynamicPageListHooks::$allowedNamespaces, 1, count(\DynamicPageListHooks::$allowedNamespaces), true);
					$sSqlNsIdToText = 'CASE pl_namespace';
					foreach ($aStrictNs as $iNs => $sNs) {
						$sSqlNsIdToText .= ' WHEN ' . intval($iNs) . " THEN " . $this->DB->addQuotes($sNs);
					}
					$sSqlNsIdToText .= ' END';
					// map namespace index to name
					if ($this->parameters->getParameter('openreferences')) {
						$this->addSelect(["REPLACE(CONCAT( IF(pl_namespace=0, '', CONCAT(" . $sSqlNsIdToText . ", ':')), pl_title), '_', ' ') ".($this->collation !== false ? 'COLLATE '.$this->collation : null)." as sortkey"]);
					} else {
						// Generate sortkey like for category links. UTF-8 created problems with non-utf-8 MySQL databases
						$this->addWhere("REPLACE(CONCAT( IF(" . $this->tableNames['page'] . ".page_namespace=0, '', CONCAT(" . $sSqlNsIdToText . ", ':')), " . $this->tableNames['page'] . ".page_title), '_', ' ') ".($this->collation !== false ? 'COLLATE '.$this->collation : null)." as sortkey");
					}
					break;
				case 'user':
					$this->addOrderBy('rev_user_text');
					$this->addTable('revision', 'rev');
					$this->addSelect(['rev_user', 'rev_user_text', 'rev_comment']);
					break;
				case 'none':
					break;
			}
		}
	}

	/**
	 * Set SQL for 'redirects' parameter.
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
	 * Set SQL for 'stablepages' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _stablepages($option) {
		if (function_exists('efLoadFlaggedRevs')) {
			//Do not add this again if 'qualitypages' has already added it.
			if (!$this->parametersProcessed['qualitypages']) {
				$this->addJoin("LEFT JOIN {$this->tableNames['flaggedpages']} ON page_id = fp_page_id");
			}
			switch ($option) {
				case 'only':
					$this->addWhere('fp_stable IS NOT NULL');
					break;
				case 'exclude':
					$this->addWhere('fp_stable IS NULL');
					break;
			}
		}
	}

	/**
	 * Set SQL for 'qualitypages' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _qualitypages($option) {
		if (function_exists('efLoadFlaggedRevs')) {
			//Do not add this again if 'stablepages' has already added it.
			if (!$this->parametersProcessed['stablepages']) {
				$this->addJoin("LEFT JOIN {$this->tableNames['flaggedpages']} ON page_id = fp_page_id");
			}
			switch ($option) {
				case 'only':
					$this->addWhere('fp_quality >= 1');
					break;
				case 'exclude':
					$this->addWhere('fp_quality = 0');
					break;
			}
		}
	}

	/**
	 * Set SQL for 'title' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _title($option) {
		foreach ($option as $comparisonType => $titles) {
			foreach ($titles as $title) {
				if ($this->parameters->getParameter('openreferences')) {
					if ($this->parameters->getParameter('ignorecase')) {
						$_or = "LOWER(CAST(pl_title AS char)) {$comparisonType}".strtolower($this->DB->addQuotes($title));
					} else {
						$_or = "pl_title {$comparisonType} ".$this->DB->addQuotes($title);
					}
				} else {
					if ($this->parameters->getParameter('ignorecase')) {
						$_or = "LOWER(CAST({$this->tableNames['page']}.page_title AS char)) {$comparisonType}".strtolower($this->DB->addQuotes($title));
					} else {
						$_or = "{$this->tableNames['page']}.page_title {$comparisonType}".$this->DB->addQuotes($title);
					}
				}
				$ors[] = $_or;
			}
		}
		$where .= '('.implode(' OR ', $ors).')';
		$this->addWhere($where);
	}

	/**
	 * Set SQL for 'nottitle' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _nottitle($option) {
		foreach ($option as $comparisonType => $titles) {
			foreach ($titles as $title) {
				if ($this->parameters->getParameter('openreferences')) {
					if ($this->parameters->getParameter('ignorecase')) {
						$_or = "LOWER(CAST(pl_title AS char)) {$comparisonType}".strtolower($this->DB->addQuotes($title));
					} else {
						$_or = "pl_title {$comparisonType} ".$this->DB->addQuotes($title);
					}
				} else {
					if ($this->parameters->getParameter('ignorecase')) {
						$_or = "LOWER(CAST({$this->tableNames['page']}.page_title AS char)) {$comparisonType}".strtolower($this->DB->addQuotes($title));
					} else {
						$_or = "{$this->tableNames['page']}.page_title {$comparisonType}".$this->DB->addQuotes($title);
					}
				}
				$ors[] = $_or;
			}
		}
		$where .= 'NOT ('.implode(' OR ', $ors).')';
		$this->addWhere($where);
	}

	/**
	 * Set SQL for 'titlegt' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _titlegt($option) {
		$where = '(';
		if (substr($option, 0, 2) == '=_') {
			if ($this->parameters->getParameter('openreferences')) {
				$where .= 'pl_title >= '.$this->DB->addQuotes(substr($sTitleGE, 2));
			} else {
				$where .= $this->tableNames['page'].'.page_title >= '.$this->DB->addQuotes(substr($option, 2));
			}
		} else {
			if ($this->parameters->getParameter('openreferences')) {
				$where .= 'pl_title > '.$this->DB->addQuotes($option);
			} else {
				$where .= $this->tableNames['page'].'.page_title > '.$this->DB->addQuotes($option);
			}
		}
		$where .= ')';
		$this->addWhere($where);
	}

	/**
	 * Set SQL for 'titlelt' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _titlelt($option) {
		$where = '(';
		if (substr($option, 0, 2) == '=_') {
			if ($this->parameters->getParameter('openreferences')) {
				$where .= 'pl_title <= '.$this->DB->addQuotes(substr($option, 2));
			} else {
				$where .= $this->tableNames['page'].'.page_title <= '.$this->DB->addQuotes(substr($option, 2));
			}
		} else {
			if ($this->parameters->getParameter('openreferences')) {
				$where .= 'pl_title < '.$this->DB->addQuotes($option);
			} else {
				$where .= $this->tableNames['page'].'.page_title < '.$this->DB->addQuotes($option);
			}
		}
		$where .= ')';
		$this->addWhere($where);
	}

	/**
	 * Set SQL for 'usedby' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _usedby($option) {
		if ($this->parameters->getParameter('openreferences')) {
			foreach ($option as $link) {
				$ors[] = '(tpl_from='.intval($link->getArticleID()).')';
			}
			$where = '('.implode(' OR ', $ors).')';
		} else {
			$this->addTable('templatelinks', 'tpl');
			$this->addTable('page', 'tplsrc');
			$this->addSelect(['tpl_sel_title' => 'tplsrc.page_title', 'tpl_sel_ns' => 'tplsrc.page_namespace']);
			$where = $this->tableNames['page'].'.page_title = tpl.tl_title AND tplsrc.page_id=tpl.tl_from AND (';
			foreach ($option as $link) {
				$ors[] = '(tpl.tl_from='.intval($link->getArticleID()).')';
			}
			$where .= '('.implode(' OR ', $ors).')';
		}
		$this->addWhere($where);
	}

	/**
	 * Set SQL for 'uses' parameter.
	 *
	 * @access	private
	 * @param	mixed	Parameter Option
	 * @return	void
	 */
	private function _uses($option) {
		$this->addTable('templatelinks', 'tl');
		$where = $this->tableNames['page'].'.page_id=tl.tl_from AND (';
		foreach ($option as $link) {
			$_or = '(tl.tl_namespace='.intval($link->getNamespace());
			if ($this->parameters->getParameter('ignorecase')) {
				$_or .= " AND LOWER(CAST(tl.tl_title AS char))=LOWER(".$this->DB->addQuotes($link->getDbKey()).'))';
			} else {
				$_or .= " AND tl.tl_title=".$this->DB->addQuotes($link->getDbKey()).')';
			}
			$ors[] = $_or;
		}
		$where .= implode(' OR ', $ors).')';
		$this->addWhere($where);
	}
}
?>