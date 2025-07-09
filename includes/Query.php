<?php

namespace MediaWiki\Extension\DynamicPageList3;

use DateInterval;
use DateTime;
use Exception;
use InvalidArgumentException;
use LogicException;
use MediaWiki\MediaWikiServices;
use MediaWiki\PoolCounter\PoolCounterWorkViaCallback;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserFactory;
use MediaWiki\WikiMap\WikiMap;
use UnexpectedValueException;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\IDatabase;

class Query {

	use ExternalDomainPatternParser;

	/**
	 * Parameters Object
	 *
	 * @var Parameters
	 */
	private $parameters;

	/**
	 * Mediawiki DB Object
	 *
	 * @var IDatabase
	 */
	private $dbr;

	/**
	 * Parameters that have already been processed.
	 *
	 * @var array
	 */
	private $parametersProcessed = [];

	/**
	 * Select Fields
	 *
	 * @var array
	 */
	private $select = [];

	/**
	 * The generated SQL Query.
	 *
	 * @var string
	 */
	private $sqlQuery = '';

	/**
	 * Selected Fields - An array to look up keys against for speed optimization.
	 *
	 * @var array
	 */
	private $selectedFields = [];

	/**
	 * Prefixed and escaped table names.
	 *
	 * @var array
	 */
	private $tables = [];

	/**
	 * Where Clauses
	 *
	 * @var array
	 */
	private $where = [];

	/**
	 * Group By Clauses
	 *
	 * @var array
	 */
	private $groupBy = [];

	/**
	 * Order By Clauses
	 *
	 * @var array
	 */
	private $orderBy = [];

	/**
	 * Join Clauses
	 *
	 * @var array
	 */
	private $join = [];

	/**
	 * Limit
	 *
	 * @var int|bool
	 */
	private $limit = false;

	/**
	 * Offset
	 *
	 * @var int|bool
	 */
	private $offset = false;

	/**
	 * Order By Direction
	 *
	 * @var string
	 */
	private $direction = 'ASC';

	/**
	 * Distinct Results
	 *
	 * @var bool
	 */
	private $distinct = true;

	/**
	 * Character Set Collation
	 *
	 * @var string|bool
	 */
	private $collation = false;

	/**
	 * Was the revision auxiliary table select added for firstedit and lastedit?
	 *
	 * @var bool
	 */
	private $revisionAuxWhereAdded = false;

	/**
	 * UserFactory object
	 *
	 * @var UserFactory
	 */
	private $userFactory;

	/**
	 * @param Parameters $parameters
	 */
	public function __construct( Parameters $parameters ) {
		$this->parameters = $parameters;

		$this->dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA, 'dpl' );

		$this->userFactory = MediaWikiServices::getInstance()->getUserFactory();
	}

	/**
	 * Start a query build. Returns found rows.
	 *
	 * @param bool $calcRows
	 * @param string $profilingContext Used to see the origin of a query in the profiling
	 * @return array|bool
	 */
	public function buildAndSelect( bool $calcRows = false, $profilingContext = '' ) {
		global $wgNonincludableNamespaces, $wgDebugDumpSql;

		$options = [];

		$parameters = $this->parameters->getAllParameters();
		foreach ( $parameters as $parameter => $option ) {
			$function = '_' . $parameter;
			// Some parameters do not modify the query so we check if the function to modify the query exists first.
			$success = true;
			if ( method_exists( $this, $function ) ) {
				$success = $this->$function( $option );
			}

			if ( $success === false ) {
				throw new LogicException(
					__METHOD__ . ": SQL Build Error returned from {$function} for " .
					serialize( $option ) . '.'
				);
			}

			$this->parametersProcessed[$parameter] = true;
		}

		if ( !$this->parameters->getParameter( 'openreferences' ) ) {
			// Add things that are always part of the query.
			$this->addTable( 'page', $this->dbr->tableName( 'page', 'raw' ) );
			$this->addSelect(
				[
					'page_namespace' => $this->dbr->tableName( 'page' ) . '.page_namespace',
					'page_id' => $this->dbr->tableName( 'page' ) . '.page_id',
					'page_title' => $this->dbr->tableName( 'page' ) . '.page_title',
				]
			);
		}

		// Always add nonincludeable namespaces.
		if ( is_array( $wgNonincludableNamespaces ) && count( $wgNonincludableNamespaces ) ) {
			$this->addNotWhere(
				[
					$this->dbr->tableName( 'page' ) . '.page_namespace' => $wgNonincludableNamespaces,
				]
			);
		}

		if ( $this->offset !== false ) {
			$options['OFFSET'] = $this->offset;
		}

		if ( $this->limit !== false ) {
			$options['LIMIT'] = $this->limit;
		} elseif ( $this->offset !== false ) {
			$options['LIMIT'] = $this->parameters->getParameter( 'count' );
		}

		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			if ( count( $this->parameters->getParameter( 'imagecontainer' ) ?? [] ) > 0 ) {
				$this->addSelect(
					[
						'il_to',
					]
				);

				$this->addTable( 'imagelinks', 'ic' );
			} else {
				if ( $this->parameters->getParameter( 'openreferences' ) === 'missing' ) {
					$this->addSelect(
						[
							'page_namespace' => $this->dbr->tableName( 'page' ) . '.page_namespace',
							'page_id' => $this->dbr->tableName( 'page' ) . '.page_id',
							'page_title' => $this->dbr->tableName( 'page' ) . '.page_title',
							'lt_namespace' => $this->dbr->tableName( 'linktarget' ) . '.lt_namespace',
							'lt_title' => $this->dbr->tableName( 'linktarget' ) . '.lt_title',
						]
					);

					$this->addWhere( [ $this->dbr->tableName( 'page' ) . '.page_namespace' => null ] );
				} else {
					$this->addSelect(
						[
							'page_id' => $this->dbr->tableName( 'page' ) . '.page_id',
							'lt_namespace' => $this->dbr->tableName( 'linktarget' ) . '.lt_namespace',
							'lt_title' => $this->dbr->tableName( 'linktarget' ) . '.lt_title',
						]
					);
				}

				$this->addWhere(
					"{$this->dbr->tableName( 'pagelinks' )}.pl_target_id = " .
					"{$this->dbr->tableName( 'linktarget' )}.lt_id"
				);

				$this->addJoin(
					$this->dbr->tableName( 'page', 'raw' ),
					[
						'LEFT JOIN',
						[
							"{$this->dbr->tableName( 'page' )}.page_namespace = " .
							"{$this->dbr->tableName( 'linktarget' )}.lt_namespace",
							"{$this->dbr->tableName( 'page' )}.page_title = " .
							"{$this->dbr->tableName( 'linktarget' )}.lt_title",
						],
					]
				);

				$this->addTables( [
					'page' => $this->dbr->tableName( 'page', 'raw' ),
					'pagelinks' => $this->dbr->tableName( 'pagelinks', 'raw' ),
					'linktarget' => $this->dbr->tableName( 'linktarget', 'raw' ),
				] );
			}
		} else {
			if ( count( $this->groupBy ) ) {
				$options['GROUP BY'] = $this->groupBy;
			}
			if ( count( $this->orderBy ) ) {
				$options['ORDER BY'] = $this->orderBy;
				foreach ( $options['ORDER BY'] as $key => $_ ) {
					$options['ORDER BY'][$key] .= ' ' . $this->direction;
				}
			}
		}
		if ( $this->parameters->getParameter( 'goal' ) == 'categories' ) {
			$categoriesGoal = true;
			$fields = [
				$this->dbr->tableName( 'page' ) . '.page_id'
			];

			$options[] = 'DISTINCT';
		} else {
			if ( $calcRows ) {
				$options[] = 'SQL_CALC_FOUND_ROWS';
			}

			if ( $this->distinct ) {
				$options[] = 'DISTINCT';
			}

			$categoriesGoal = false;
			$fields = $this->select;
		}

		try {
			if ( $categoriesGoal ) {
				$res = $this->dbr->select(
					$this->tables,
					$fields,
					$this->where,
					__METHOD__,
					$options,
					$this->join
				);

				$pageIds = [];

				foreach ( $res as $row ) {
					$pageIds[] = $row->page_id;
				}

				$query = $this->dbr->selectSQLText(
					[
						'clgoal' => 'categorylinks'
					],
					[
						'clgoal.cl_to'
					],
					[
						'clgoal.cl_from' => $pageIds
					],
					__METHOD__,
					[
						'ORDER BY' => 'clgoal.cl_to ' . $this->direction
					]
				);
			} else {
				$query = $this->dbr->selectSQLText(
					$this->tables,
					$fields,
					$this->where,
					__METHOD__,
					$options,
					$this->join
				);
			}

			if ( Hooks::getDebugLevel() >= 4 && $wgDebugDumpSql ) {
				$this->sqlQuery = $query;
			}
		} catch ( Exception $e ) {
			$errorMessage = $this->dbr->lastError();
			if ( $errorMessage == '' ) {
				$errorMessage = strval( $e );
			}
			throw new LogicException( __METHOD__ . ': ' . wfMessage(
				'dpl_query_error', Hooks::getVersion(), $errorMessage
			)->text() );
		}

		// Partially taken from intersection
		$queryCacheTime = Config::getSetting( 'queryCacheTime' );
		$maxQueryTime = Config::getSetting( 'maxQueryTime' );

		if ( $maxQueryTime ) {
			$options['MAX_EXECUTION_TIME'] = $maxQueryTime;
		}

		$qname = __METHOD__;
		if ( $profilingContext ) {
			$qname .= ' - ' . $profilingContext;
		}
		$tables = $this->tables;
		$where = $this->where;
		$join = $this->join;
		$dbr = $this->dbr;

		$doQuery = static function () use ( $qname, $dbr, $tables, $fields, $where, $options, $join, $calcRows ) {
			$res = $dbr->select( $tables, $fields, $where, $qname, $options, $join );
			$res = iterator_to_array( $res );

			if ( $calcRows ) {
				$res['count'] = $dbr->selectField( $tables, 'FOUND_ROWS()', '', $qname );
			}

			return $res;
		};

		$poolCounterKey = 'nowait:dpl3-query:' . WikiMap::getCurrentWikiId();
		$worker = new PoolCounterWorkViaCallback( 'DPL3', $poolCounterKey, [
			'doWork' => $doQuery,
		] );

		if ( $queryCacheTime <= 0 ) {
			return $worker->execute();
		}

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

		return $cache->getWithSetCallback(
			$cache->makeKey( 'DPL3Query', hash( 'sha256', $query ) ),
			$queryCacheTime,
			static function ( $oldVal, &$ttl, &$setOpts ) use ( $worker, $dbr ){
				$setOpts += Database::getCacheSetOptions( $dbr );
				$res = $worker->execute();
				if ( $res === false ) {
					// Do not cache errors.
					$ttl = WANObjectCache::TTL_UNCACHEABLE;
					// If we have oldVal, prefer it to error
					if ( is_array( $oldVal ) ) {
						return $oldVal;
					}
				}
				return $res;
			},
			[
				'lowTTL' => min( $cache::TTL_MINUTE, floor( $queryCacheTime * 0.75 ) ),
				'pcTTL' => min( $cache::TTL_PROC_LONG, $queryCacheTime )
			]
		);
	}

	/**
	 * Returns the generated SQL Query
	 *
	 * @return string
	 */
	public function getSqlQuery() {
		return $this->sqlQuery;
	}

	/**
	 * Add a table to the output.
	 *
	 * @param string $table
	 * @param string $alias
	 * @return bool
	 */
	public function addTable( $table, $alias ) {
		if ( !$table ) {
			throw new InvalidArgumentException( __METHOD__ . ': An empty table name was passed.' );
		}

		if ( !$alias || is_numeric( $alias ) ) {
			throw new InvalidArgumentException( __METHOD__ . ': An empty or numeric table alias was passed.' );
		}

		if ( !isset( $this->tables[$alias] ) ) {
			$this->tables[$alias] = $table;
			return true;
		}

		return false;
	}

	/**
	 * Add multiple tables to the output.
	 *
	 * @param array $tablesByAlias [ table => alias ]
	 */
	public function addTables( array $tablesByAlias ) {
		foreach ( $tablesByAlias as $table => $alias ) {
			$this->addTable( $table, $alias );
		}
	}

	/**
	 * Add a where clause to the output.
	 * Where clauses get imploded together with AND at the end. Any custom where clauses
	 * should be preformed before placed into here.
	 *
	 * @param array|string $where
	 * @return bool
	 */
	public function addWhere( $where ) {
		if ( !$where ) {
			throw new InvalidArgumentException( __METHOD__ . ': An empty where clause was passed.' );
		}

		if ( is_string( $where ) ) {
			$this->where[] = $where;
		} elseif ( is_array( $where ) ) {
			$this->where = array_merge( $this->where, $where );
		} else {
			throw new InvalidArgumentException( __METHOD__ . ': An invalid where clause was passed.' );
		}

		return true;
	}

	/**
	 * Add a where clause to the output that uses NOT IN or !=.
	 *
	 * @param array $where
	 * @return bool
	 */
	public function addNotWhere( $where ) {
		if ( !$where ) {
			throw new InvalidArgumentException( __METHOD__ . ': An empty not where clause was passed.' );
		}

		if ( is_array( $where ) ) {
			foreach ( $where as $field => $values ) {
				$this->where[] = $field . (
					count( $values ) > 1 ? ' NOT IN(' .
						$this->dbr->makeList( $values ) . ')' : ' != ' .
					$this->dbr->addQuotes( current( $values ) )
				);
			}
		} else {
			throw new InvalidArgumentException( __METHOD__ . ': An invalid NOT WHERE clause was passed.' );
		}

		return true;
	}

	/**
	 * Add a field to select.
	 * Will ignore duplicate values if the exact same alias and exact same field are passed.
	 *
	 * @param array $fields
	 * @return bool
	 */
	public function addSelect( $fields ) {
		if ( !is_array( $fields ) ) {
			throw new InvalidArgumentException( __METHOD__ . ': A non-array was passed.' );
		}

		foreach ( $fields as $alias => $field ) {
			if (
				!is_numeric( $alias ) &&
				array_key_exists( $alias, $this->select ) &&
				$this->select[$alias] != $field
			) {
				// In case of a code bug that is overwriting an existing field alias throw an exception.
				throw new UnexpectedValueException(
					__METHOD__ . ": Attempted to overwrite existing field alias " .
					"`{$this->select[$alias]}` AS `{$alias}` with `{$field}` AS `{$alias}`."
				);
			}

			// String alias and does not exist already.
			if ( !is_numeric( $alias ) && !array_key_exists( $alias, $this->select ) ) {
				$this->select[$alias] = $field;
			}

			// Speed up by not using in_array() or array_key_exists().
			// Toss the field names into their own array as keys => true
			// to exploit a speedy look up with isset().
			if ( is_numeric( $alias ) && !isset( $this->selectedFields[$field] ) ) {
				$this->select[] = $field;
				$this->selectedFields[$field] = true;
			}
		}

		return true;
	}

	/**
	 * Add a GROUP BY clause to the output.
	 *
	 * @param string $groupBy
	 * @return bool
	 */
	public function addGroupBy( $groupBy ) {
		if ( !$groupBy ) {
			throw new InvalidArgumentException( __METHOD__ . ': An empty GROUP BY clause was passed.' );
		}

		$this->groupBy[] = $groupBy;

		return true;
	}

	/**
	 * Add a ORDER BY clause to the output.
	 *
	 * @param string $orderBy
	 * @return bool
	 */
	public function addOrderBy( $orderBy ) {
		if ( !$orderBy ) {
			throw new InvalidArgumentException( __METHOD__ . ': An empty ORDER BY clause was passed.' );
		}

		$this->orderBy[] = $orderBy;

		return true;
	}

	/**
	 * Add a JOIN clause to the output.
	 *
	 * @param string $tableAlias
	 * @param array $joinConditions
	 * @return bool
	 */
	public function addJoin( $tableAlias, $joinConditions ) {
		if ( !$tableAlias || !$joinConditions ) {
			throw new InvalidArgumentException( __METHOD__ . ': An empty JOIN clause was passed.' );
		}

		if ( isset( $this->join[$tableAlias] ) ) {
			throw new UnexpectedValueException( __METHOD__ . ': Attempted to overwrite existing JOIN clause.' );
		}

		$this->join[$tableAlias] = $joinConditions;

		return true;
	}

	/**
	 * @param array $joins
	 */
	public function addJoins( array $joins ) {
		foreach ( $joins as $alias => $conds ) {
			$this->addJoin( $alias, $conds );
		}
	}

	/**
	 * Set the limit.
	 *
	 * @param mixed $limit
	 * @return bool
	 */
	public function setLimit( $limit ) {
		if ( is_numeric( $limit ) ) {
			$this->limit = (int)$limit;
		} else {
			$this->limit = false;
		}

		return true;
	}

	/**
	 * Set the offset.
	 *
	 * @param mixed $offset
	 * @return bool
	 */
	public function setOffset( $offset ) {
		if ( is_numeric( $offset ) ) {
			$this->offset = (int)$offset;
		} else {
			$this->offset = false;
		}

		return true;
	}

	/**
	 * Set the ORDER BY direction
	 *
	 * @param string $direction
	 * @return bool
	 */
	public function setOrderDir( $direction ) {
		$this->direction = $direction;

		return true;
	}

	/**
	 * Set the character set collation.
	 *
	 * @param string $collation
	 */
	public function setCollation( $collation ) {
		$this->collation = $collation;
	}

	/**
	 * Return SQL prefixed collation.
	 *
	 * @return string|null
	 */
	public function getCollateSQL() {
		return ( $this->collation !== false ? 'COLLATE ' . $this->collation : null );
	}

	/**
	 * Recursively get and return an array of subcategories.
	 *
	 * @param string $categoryName
	 * @param int $depth
	 * @return array
	 */
	public static function getSubcategories( $categoryName, $depth = 1 ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()->getConnection( DB_REPLICA, 'dpl' );

		if ( $depth > 2 ) {
			// Hard constrain depth because lots of recursion is bad.
			$depth = 2;
		}

		$categories = [];
		$res = $dbr->select(
			[ 'page', 'categorylinks' ],
			[ 'page_title' ],
			[
				'page_namespace' => NS_CATEGORY,
				'cl_to' => str_replace( ' ', '_', $categoryName )
			],
			__METHOD__,
			[ 'DISTINCT' ],
			[
				'categorylinks' => [
					'INNER JOIN',
					'page_id = cl_from'
				]
			]
		);

		foreach ( $res as $row ) {
			$categories[] = $row->page_title;
			if ( $depth > 1 ) {
				$categories = array_merge( $categories, self::getSubcategories( $row->page_title, $depth - 1 ) );
			}
		}

		$categories = array_unique( $categories );
		$res->free();

		return $categories;
	}

	/**
	 * Helper method to handle relative timestamps.
	 *
	 * @param mixed $inputDate
	 * @return int|string
	 */
	private function convertTimestamp( $inputDate ) {
		$timestamp = $inputDate;
		switch ( $inputDate ) {
			case 'today':
				$timestamp = date( 'YmdHis' );
				break;
			case 'last hour':
				$date = new DateTime();
				$date->sub( new DateInterval( 'P1H' ) );
				$timestamp = $date->format( 'YmdHis' );
				break;
			case 'last day':
				$date = new DateTime();
				$date->sub( new DateInterval( 'P1D' ) );
				$timestamp = $date->format( 'YmdHis' );
				break;
			case 'last week':
				$date = new DateTime();
				$date->sub( new DateInterval( 'P7D' ) );
				$timestamp = $date->format( 'YmdHis' );
				break;
			case 'last month':
				$date = new DateTime();
				$date->sub( new DateInterval( 'P1M' ) );
				$timestamp = $date->format( 'YmdHis' );
				break;
			case 'last year':
				$date = new DateTime();
				$date->sub( new DateInterval( 'P1Y' ) );
				$timestamp = $date->format( 'YmdHis' );
				break;
		}

		if ( is_numeric( $timestamp ) ) {
			return $this->dbr->addQuotes( $timestamp );
		}

		return 0;
	}

	/**
	 * Set SQL for 'addauthor' parameter.
	 *
	 * @param mixed $option @phan-unused-param
	 */
	private function _addauthor( $option ) {
		// Addauthor can not be used with addlasteditor.
		if ( !isset( $this->parametersProcessed['addlasteditor'] ) || !$this->parametersProcessed['addlasteditor'] ) {
			$this->addTable( 'revision', 'rev' );
			$this->addWhere( [
				$this->dbr->tableName( 'page' ) . '.page_id = rev.rev_page',
				'rev.rev_timestamp = (SELECT MIN(rev_aux_min.rev_timestamp) FROM ' .
					$this->dbr->tableName( 'revision' ) .
					' AS rev_aux_min WHERE rev_aux_min.rev_page = rev.rev_page)'
			] );

			$this->_adduser( null, 'rev' );
		}
	}

	/**
	 * Set SQL for 'addcategories' parameter.
	 *
	 * @param mixed $option @phan-unused-param
	 */
	private function _addcategories( $option ) {
		$this->addTable( 'categorylinks', 'cl_gc' );
		$this->addSelect(
			[
				'cats' => "GROUP_CONCAT(DISTINCT cl_gc.cl_to ORDER BY cl_gc.cl_to ASC SEPARATOR ' | ')"
			]
		);

		$this->addJoin(
			'cl_gc',
			[
				'LEFT OUTER JOIN',
				'page_id = cl_gc.cl_from'
			]
		);

		$this->addGroupBy( $this->dbr->tableName( 'page' ) . '.page_id' );
	}

	/**
	 * Set SQL for 'addcontribution' parameter.
	 *
	 * @param mixed $option @phan-unused-param
	 */
	private function _addcontribution( $option ) {
		$this->addTable( 'recentchanges', 'rc' );

		$this->addSelect(
			[
				'contribution' => 'SUM(ABS(rc.rc_new_len - rc.rc_old_len))',
				'contributor' => 'rc.rc_actor',
				'contrib_deleted' => 'rc.rc_deleted',
			]
		);

		$this->addWhere(
			[
				$this->dbr->tableName( 'page' ) . '.page_id = rc.rc_cur_id'
			]
		);

		$this->addGroupBy( 'rc.rc_cur_id' );
	}

	/**
	 * Set SQL for 'addeditdate' parameter.
	 *
	 * @param mixed $option @phan-unused-param
	 */
	private function _addeditdate( $option ) {
		$this->addTable( 'revision', 'rev' );
		$this->addSelect( [ 'rev.rev_timestamp' ] );

		$this->addWhere(
			[
				$this->dbr->tableName( 'page' ) . '.page_id = rev.rev_page',
			]
		);
	}

	/**
	 * Set SQL for 'addfirstcategorydate' parameter.
	 *
	 * @param mixed $option @phan-unused-param
	 */
	private function _addfirstcategorydate( $option ) {
		// @TODO: This should be programmatically determining which
		// categorylink table to use instead of assuming the first one.
		$this->addSelect(
			[
				'cl_timestamp' => "DATE_FORMAT(cl1.cl_timestamp, '%Y%m%d%H%i%s')"
			]
		);
	}

	/**
	 * Set SQL for 'addlasteditor' parameter.
	 *
	 * @param mixed $option @phan-unused-param
	 */
	private function _addlasteditor( $option ) {
		// Addlasteditor can not be used with addauthor.
		if ( !isset( $this->parametersProcessed['addauthor'] ) || !$this->parametersProcessed['addauthor'] ) {
			$this->addTable( 'revision', 'rev' );

			$this->addWhere( [
				$this->dbr->tableName( 'page' ) . '.page_id = rev.rev_page',
				'rev.rev_timestamp = (SELECT MAX(rev_aux_max.rev_timestamp) FROM ' .
					$this->dbr->tableName( 'revision' ) . ' AS rev_aux_max WHERE rev_aux_max.rev_page = rev.rev_page)'
			] );

			$this->_adduser( null, 'rev' );
		}
	}

	/**
	 * Set SQL for 'addpagecounter' parameter.
	 *
	 * @param mixed $option @phan-unused-param
	 */
	private function _addpagecounter( $option ) {
		if ( ExtensionRegistry::getInstance()->isLoaded( 'HitCounters' ) ) {
			$this->addTable( 'hit_counter', 'hit_counter' );
			$this->addSelect(
				[
					'page_counter' => 'hit_counter.page_counter'
				]
			);

			if ( !isset( $this->join['hit_counter'] ) ) {
				$this->addJoin(
					'hit_counter',
					[
						'LEFT JOIN',
						'hit_counter.page_id = ' . $this->dbr->tableName( 'page' ) . '.page_id'
					]
				);
			}
		}
	}

	/**
	 * Set SQL for 'addpagesize' parameter.
	 *
	 * @param mixed $option @phan-unused-param
	 */
	private function _addpagesize( $option ) {
		$this->addSelect(
			[
				'page_len' => "{$this->dbr->tableName( 'page' )}.page_len"
			]
		);
	}

	/**
	 * Set SQL for 'addpagetoucheddate' parameter.
	 *
	 * @param mixed $option @phan-unused-param
	 */
	private function _addpagetoucheddate( $option ) {
		$this->addSelect(
			[
				'page_touched' => "{$this->dbr->tableName( 'page' )}.page_touched"
			]
		);
	}

	/**
	 * Set SQL for 'adduser' parameter.
	 *
	 * @param mixed $option @phan-unused-param
	 * @param string $tableAlias
	 */
	private function _adduser( $option, $tableAlias = '' ) {
		if ( $tableAlias ) {
			$tableAlias .= '.';
		}

		$this->addSelect(
			[
				$tableAlias . 'rev_actor',
				$tableAlias . 'rev_deleted',
			]
		);
	}

	/**
	 * Set SQL for 'allrevisionsbefore' parameter.
	 *
	 * @param mixed $option
	 */
	private function _allrevisionsbefore( $option ) {
		$this->addTable( 'revision', 'rev' );
		$this->addSelect(
			[
				'rev.rev_id',
				'rev.rev_timestamp'
			]
		);

		$this->addOrderBy( 'rev.rev_id' );
		$this->setOrderDir( 'DESC' );

		$this->addWhere(
			[
				$this->dbr->tableName( 'page' ) . '.page_id = rev.rev_page',
				'rev.rev_timestamp < ' . $this->convertTimestamp( $option )
			]
		);
	}

	/**
	 * Set SQL for 'allrevisionssince' parameter.
	 *
	 * @param mixed $option
	 */
	private function _allrevisionssince( $option ) {
		$this->addTable( 'revision', 'rev' );
		$this->addSelect(
			[
				'rev.rev_id',
				'rev.rev_timestamp'
			]
		);

		$this->addOrderBy( 'rev.rev_id' );
		$this->setOrderDir( 'DESC' );

		$this->addWhere(
			[
				$this->dbr->tableName( 'page' ) . '.page_id = rev.rev_page',
				'rev.rev_timestamp >= ' . $this->convertTimestamp( $option )
			]
		);
	}

	/**
	 * Set SQL for 'articlecategory' parameter.
	 *
	 * @param mixed $option
	 */
	private function _articlecategory( $option ) {
		$this->addWhere(
			$this->dbr->tableName( 'page' ) . '.page_title IN (SELECT p2.page_title FROM ' .
			$this->dbr->tableName( 'page' ) . ' p2 INNER JOIN ' .
			$this->dbr->tableName( 'categorylinks' ) . ' clstc ON (clstc.cl_from = p2.page_id AND clstc.cl_to = ' .
			$this->dbr->addQuotes( $option ) . ') WHERE p2.page_namespace = 0)'
		);
	}

	/**
	 * Set SQL for 'categoriesminmax' parameter.
	 *
	 * @param mixed $option
	 */
	private function _categoriesminmax( $option ) {
		if ( is_numeric( $option[0] ) ) {
			$this->addWhere(
				(int)$option[0] . ' <= (SELECT count(*) FROM ' .
				$this->dbr->tableName( 'categorylinks' ) . ' WHERE ' .
				$this->dbr->tableName( 'categorylinks' ) . '.cl_from=page_id)'
			);
		}

		if ( isset( $option[1] ) && is_numeric( $option[1] ) ) {
			$this->addWhere(
				(int)$option[1] . ' >= (SELECT count(*) FROM ' .
				$this->dbr->tableName( 'categorylinks' ) . ' WHERE ' .
				$this->dbr->tableName( 'categorylinks' ) . '.cl_from=page_id)'
			);
		}
	}

	/**
	 * Set SQL for 'category' parameter. This includes 'category', 'categorymatch', and 'categoryregexp'.
	 *
	 * @param mixed $option
	 */
	private function _category( $option ) {
		$i = 0;

		foreach ( $option as $comparisonType => $operatorTypes ) {
			foreach ( $operatorTypes as $operatorType => $categoryGroups ) {
				foreach ( $categoryGroups as $categories ) {
					if ( !is_array( $categories ) ) {
						continue;
					}

					$tableName = ( in_array( '', $categories ) ? 'dpl_clview' : 'categorylinks' );
					if ( $operatorType == 'AND' ) {
						foreach ( $categories as $category ) {
							$i++;
							$tableAlias = "cl{$i}";
							$this->addTable( $tableName, $tableAlias );
							$this->addJoin(
								$tableAlias, [
									'INNER JOIN',
									"{$this->dbr->tableName( 'page' )}.page_id = {$tableAlias}.cl_from AND " .
										"$tableAlias.cl_to {$comparisonType} " .
										$this->dbr->addQuotes( str_replace( ' ', '_', $category ) )
								]
							);
						}
					} elseif ( $operatorType == 'OR' ) {
						$i++;
						$tableAlias = "cl{$i}";
						$this->addTable( $tableName, $tableAlias );

						$joinOn = "{$this->dbr->tableName( 'page' )}.page_id = {$tableAlias}.cl_from AND (";
						$ors = [];

						foreach ( $categories as $category ) {
							$ors[] = "{$tableAlias}.cl_to {$comparisonType} " .
								$this->dbr->addQuotes( str_replace( ' ', '_', $category ) );
						}

						$joinOn .= implode( " {$operatorType} ", $ors );
						$joinOn .= ')';

						$this->addJoin(
							$tableAlias,
							[
								'INNER JOIN',
								$joinOn
							]
						);
					}
				}
			}
		}
	}

	/**
	 * Set SQL for 'notcategory' parameter.
	 *
	 * @param mixed $option
	 */
	private function _notcategory( $option ) {
		$i = 0;
		foreach ( $option as $operatorType => $categories ) {
			foreach ( $categories as $category ) {
				$i++;

				$tableAlias = "ecl{$i}";
				$this->addTable( 'categorylinks', $tableAlias );

				$this->addJoin(
					$tableAlias, [
						'LEFT OUTER JOIN',
						"{$this->dbr->tableName( 'page' )}.page_id = {$tableAlias}.cl_from AND " .
							"{$tableAlias}.cl_to {$operatorType}" .
							$this->dbr->addQuotes( str_replace( ' ', '_', $category ) )
					]
				);

				$this->addWhere(
					[
						"{$tableAlias}.cl_to" => null
					]
				);
			}
		}
	}

	/**
	 * Set SQL for 'createdby' parameter.
	 *
	 * @param mixed $option
	 */
	private function _createdby( $option ) {
		$user = $this->userFactory->newFromName( $option );
		if ( $user->isHidden() ) {
			return;
		}

		$this->addTable( 'revision', 'creation_rev' );
		$this->_adduser( null, 'creation_rev' );

		$this->addWhere( [
			$this->dbr->addQuotes( $user->getActorId() ) . ' = creation_rev.rev_actor',
			'creation_rev.rev_page = page_id',
			'creation_rev.rev_deleted = 0',
			'creation_rev.rev_parent_id = 0',
		] );
	}

	/**
	 * Set SQL for 'distinct' parameter.
	 *
	 * @param mixed $option
	 */
	private function _distinct( $option ) {
		if ( $option == 'strict' || $option === true ) {
			$this->distinct = true;
		} else {
			$this->distinct = false;
		}
	}

	/**
	 * Set SQL for 'firstrevisionsince' parameter.
	 *
	 * @param mixed $option
	 */
	private function _firstrevisionsince( $option ) {
		$this->addTable( 'revision', 'rev' );
		$this->addSelect(
			[
				'rev.rev_id',
				'rev.rev_timestamp'
			]
		);

		// tell the query optimizer not to look at rows that the following subquery will filter out anyway
		$this->addWhere(
			[
				$this->dbr->tableName( 'page' ) . '.page_id = rev.rev_page',
				'rev.rev_timestamp >= ' . $this->dbr->addQuotes( $option )
			]
		);

		$this->addWhere( [
			$this->dbr->tableName( 'page' ) . '.page_id = rev.rev_page',
			'rev.rev_timestamp = (SELECT MIN(rev_aux_snc.rev_timestamp) FROM ' .
				$this->dbr->tableName( 'revision' ) .
					' AS rev_aux_snc WHERE rev_aux_snc.rev_page=rev.rev_page AND rev_aux_snc.rev_timestamp >= ' .
					$this->convertTimestamp( $option ) . ')'
		] );
	}

	/**
	 * Set SQL for 'goal' parameter.
	 *
	 * @param mixed $option
	 */
	private function _goal( $option ) {
		if ( $option == 'categories' ) {
			$this->setLimit( false );
			$this->setOffset( false );
		}
	}

	/**
	 * Set SQL for 'hiddencategories' parameter.
	 *
	 * @param mixed $option @phan-unused-param
	 */
	private function _hiddencategories( $option ) {
		// @TODO: Unfinished functionality! Never implemented by original author.
	}

	/**
	 * Set SQL for 'imagecontainer' parameter.
	 *
	 * @param mixed $option
	 */
	private function _imagecontainer( $option ) {
		$where = [];

		$this->addTable( 'imagelinks', 'ic' );
		$this->addSelect(
			[
				'sortkey' => 'ic.il_to'
			]
		);

		if ( !$this->parameters->getParameter( 'openreferences' ) ) {
			$where = [
				"{$this->dbr->tableName( 'page' )}.page_namespace = " . NS_FILE,
				"{$this->dbr->tableName( 'page' )}.page_title = ic.il_to"
			];
		}

		$ors = [];
		foreach ( $option as $linkGroup ) {
			foreach ( $linkGroup as $link ) {
				if ( $this->parameters->getParameter( 'ignorecase' ) ) {
					$ors[] = 'LOWER(CAST(ic.il_from AS char) = LOWER(' .
						$this->dbr->addQuotes( $link->getArticleID() ) . ')';
				} else {
					$ors[] = 'ic.il_from = ' . $this->dbr->addQuotes( $link->getArticleID() );
				}
			}
		}

		$where[] = '(' . implode( ' OR ', $ors ) . ')';
		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'imageused' parameter.
	 *
	 * @param mixed $option
	 */
	private function _imageused( $option ) {
		$where = [];

		if ( $this->parameters->getParameter( 'distinct' ) == 'strict' ) {
			$this->addGroupBy( 'page_title' );
		}

		$this->addTable( 'imagelinks', 'il' );
		$this->addSelect(
			[
				'image_sel_title' => 'il.il_to'
			]
		);

		$where[] = $this->dbr->tableName( 'page' ) . '.page_id = il.il_from';
		$ors = [];

		foreach ( $option as $linkGroup ) {
			foreach ( $linkGroup as $link ) {
				if ( $this->parameters->getParameter( 'ignorecase' ) ) {
					$ors[] = 'LOWER(CAST(il.il_to AS char)) = LOWER(' .
						$this->dbr->addQuotes( $link->getDBkey() ) . ')';
				} else {
					$ors[] = 'il.il_to = ' . $this->dbr->addQuotes( $link->getDBkey() );
				}
			}
		}

		$where[] = '(' . implode( ' OR ', $ors ) . ')';
		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'lastmodifiedby' parameter.
	 *
	 * @param mixed $option
	 */
	private function _lastmodifiedby( $option ) {
		$user = $this->userFactory->newFromName( $option );
		if ( $user->isHidden() ) {
			return;
		}

		$this->addWhere(
			$this->dbr->addQuotes( $user->getActorId() ) .
			' = (SELECT rev_actor FROM ' . $this->dbr->tableName( 'revision' ) .
			' WHERE ' . $this->dbr->tableName( 'revision' ) . '.rev_page = page_id' .
			' AND ' . $this->dbr->tableName( 'revision' ) . '.rev_deleted = 0' .
			' ORDER BY ' . $this->dbr->tableName( 'revision' ) . '.rev_timestamp DESC LIMIT 1)'
		);
	}

	/**
	 * Set SQL for 'lastrevisionbefore' parameter.
	 *
	 * @param mixed $option
	 */
	private function _lastrevisionbefore( $option ) {
		$this->addTable( 'revision', 'rev' );
		$this->addSelect( [ 'rev.rev_id', 'rev.rev_timestamp' ] );

		// tell the query optimizer not to look at rows that the following subquery will filter out anyway
		$this->addWhere(
			[
				$this->dbr->tableName( 'page' ) . '.page_id = rev.rev_page',
				'rev.rev_timestamp < ' . $this->convertTimestamp( $option )
			]
		);

		$this->addWhere( [
			$this->dbr->tableName( 'page' ) . '.page_id = rev.rev_page',
			'rev.rev_timestamp = (SELECT MAX(rev_aux_bef.rev_timestamp) FROM ' .
				$this->dbr->tableName( 'revision' ) .
				' AS rev_aux_bef WHERE rev_aux_bef.rev_page=rev.rev_page AND rev_aux_bef.rev_timestamp < ' .
				$this->convertTimestamp( $option ) . ')'
		] );
	}

	/**
	 * Set SQL for 'linksfrom' parameter.
	 *
	 * @param mixed $option
	 */
	private function _linksfrom( $option ) {
		$where = [];

		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			$ors = [];
			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$ors[] = '(pl_from = ' . $link->getArticleID() . ')';
				}
			}

			$where[] = '(' . implode( ' OR ', $ors ) . ')';
		} else {
			$this->addTable( 'pagelinks', 'plf' );
			$this->addTable( 'linktarget', 'lt' );
			$this->addTable( 'page', 'pagesrc' );

			if ( $this->isPageselFormatUsed() ) {
				$this->addSelect(
					[
						'sel_title' => 'pagesrc.page_title',
						'sel_ns' => 'pagesrc.page_namespace'
					]
				);
			}

			$where = [
				$this->dbr->tableName( 'page' ) . '.page_namespace = lt.lt_namespace',
				$this->dbr->tableName( 'page' ) . '.page_title = lt.lt_title',
				'lt.lt_id = plf.pl_target_id',
				'pagesrc.page_id = plf.pl_from'
			];

			$ors = [];
			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$ors[] = 'plf.pl_from = ' . $link->getArticleID();
				}
			}

			$where[] = '(' . implode( ' OR ', $ors ) . ')';
		}

		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'linksto' parameter.
	 *
	 * @param mixed $option
	 */
	private function _linksto( $option ) {
		if ( count( $option ) > 0 ) {
			$this->addTable( 'pagelinks', 'pl' );
			$this->addTable( 'linktarget', 'lt' );

			if ( $this->isPageselFormatUsed() ) {
				$this->addSelect( [ 'sel_title' => 'lt.lt_title', 'sel_ns' => 'lt.lt_namespace' ] );
			}

			$this->addWhere( 'pl.pl_target_id = lt.lt_id' );

			foreach ( $option as $index => $linkGroup ) {
				if ( $index == 0 ) {
					$where = $this->dbr->tableName( 'page' ) . '.page_id=pl.pl_from AND ';
					$ors = [];

					foreach ( $linkGroup as $link ) {
						$_or = '(lt.lt_namespace=' . (int)$link->getNamespace();
						if ( strpos( $link->getDBkey(), '%' ) >= 0 ) {
							$operator = 'LIKE';
						} else {
							$operator = '=';
						}

						if ( $this->parameters->getParameter( 'ignorecase' ) ) {
							$_or .= ' AND LOWER(CAST(lt.lt_title AS char)) ' .
								$operator . ' LOWER(' . $this->dbr->addQuotes( $link->getDBkey() ) . ')';
						} else {
							$_or .= ' AND lt.lt_title ' . $operator . ' ' . $this->dbr->addQuotes( $link->getDBkey() );
						}

						$_or .= ')';
						$ors[] = $_or;
					}

					$where .= '(' . implode( ' OR ', $ors ) . ')';
				} else {
					$where = 'EXISTS(select pl_from FROM ' . $this->dbr->tableName( 'pagelinks' ) . ', ' .
						$this->dbr->tableName( 'linktarget' ) . ' WHERE (' .
						$this->dbr->tableName( 'pagelinks' ) . '.pl_from=page_id AND ';

					$where .= $this->dbr->tableName( 'pagelinks' ) . '.pl_target_id = ' .
						$this->dbr->tableName( 'linktarget' ) . '.lt_id AND ';

					$ors = [];

					foreach ( $linkGroup as $link ) {
						$_or = "({$this->dbr->tableName( 'linktarget' )}.lt_namespace = {$link->getNamespace()}";
						if ( strpos( $link->getDBkey(), '%' ) >= 0 ) {
							$operator = 'LIKE';
						} else {
							$operator = '=';
						}

						if ( $this->parameters->getParameter( 'ignorecase' ) ) {
							$_or .= " AND LOWER(CAST({$this->dbr->tableName( 'linktarget' )}.lt_title AS char)) " .
								$operator . ' LOWER(' . $this->dbr->addQuotes( $link->getDBkey() ) . ')';
						} else {
							$_or .= ' AND ' . $this->dbr->tableName( 'linktarget' ) . '.lt_title ' .
								$operator . ' ' . $this->dbr->addQuotes( $link->getDBkey() );
						}

						$_or .= ')';
						$ors[] = $_or;
					}

					$where .= '(' . implode( ' OR ', $ors ) . ')';
					$where .= '))';
				}

				$this->addWhere( $where );
			}
		}
	}

	/**
	 * Set SQL for 'notlinksfrom' parameter.
	 *
	 * @param mixed $option
	 */
	private function _notlinksfrom( $option ) {
		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			$ands = [];
			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$ands[] = 'pl_from <> ' . (int)$link->getArticleID() . ' ';
				}
			}

			$where = '(' . implode( ' AND ', $ands ) . ')';
		} else {
			$where = 'CONCAT(page_namespace,page_title) NOT IN (SELECT CONCAT(lt.lt_namespace,lt.lt_title) FROM ' .
				$this->dbr->tableName( 'pagelinks' ) . ' pl JOIN ' .
				$this->dbr->tableName( 'linktarget' ) . ' lt ON pl.pl_target_id = lt.lt_id WHERE ';

			$ors = [];

			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$ors[] = 'pl.pl_from = ' . (int)$link->getArticleID();
				}
			}

			$where .= implode( ' OR ', $ors ) . ')';
		}

		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'notlinksto' parameter.
	 *
	 * @param mixed $option
	 */
	private function _notlinksto( $option ) {
		if ( count( $option ) ) {
			$where = $this->dbr->tableName( 'page' ) . '.page_id NOT IN (SELECT pl.pl_from FROM ' .
				$this->dbr->tableName( 'pagelinks' ) . ' pl JOIN ' .
				$this->dbr->tableName( 'linktarget' ) . ' lt ON pl.pl_target_id = lt.lt_id WHERE ';

			$ors = [];

			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$_or = '(lt.lt_namespace=' . (int)$link->getNamespace();
					if ( strpos( $link->getDBkey(), '%' ) >= 0 ) {
						$operator = 'LIKE';
					} else {
						$operator = '=';
					}

					if ( $this->parameters->getParameter( 'ignorecase' ) ) {
						$_or .= ' AND LOWER(CAST(lt.lt_title AS char)) ' . $operator .
							' LOWER(' . $this->dbr->addQuotes( $link->getDBkey() ) . '))';
					} else {
						$_or .= ' AND lt.lt_title ' . $operator . ' ' .
							$this->dbr->addQuotes( $link->getDBkey() ) . ')';
					}

					$ors[] = $_or;
				}
			}

			$where .= '(' . implode( ' OR ', $ors ) . '))';
		}

		$this->addWhere( $where ?? '' );
	}

	/**
	 * Set SQL for 'linkstoexternal' parameter.
	 *
	 * @param mixed $option
	 */
	private function _linkstoexternal( $option ) {
		$this->_linkstoexternaldomain( $option );
	}

	/**
	 * Set SQL for 'linkstoexternaldomain' parameter.
	 *
	 * @param mixed $option
	 */
	private function _linkstoexternaldomain( $option ) {
		if ( $this->parameters->getParameter( 'distinct' ) == 'strict' ) {
			$this->addGroupBy( 'page_title' );
		}

		if ( count( $option ) == 0 ) {
			// Nothing to do
			return;
		}
		$this->addTable( 'externallinks', 'el' );
		$this->addSelect( [ 'el_to_domain_index' => 'el.el_to_domain_index' ] );

		foreach ( $option as $index => $domains ) {
			$domainPatterns = array_map(
				fn ( string $domain ) => $this->parseDomainPattern( $domain ),
				$domains
			);
			if ( $index == 0 ) {
				$ors = array_map(
					fn ( $pattern ) => "el.el_to_domain_index LIKE {$this->dbr->addQuotes( $pattern )}",
					$domainPatterns
				);

				$where = "{$this->dbr->tableName( 'page' )}.page_id=el.el_from " .
					" AND ({$this->dbr->makeList( $ors, IDatabase::LIST_OR )})";
			} else {
				$linksTable = $this->dbr->tableName( 'externallinks' );
				$ors = array_map(
					fn ( $pattern ) => "$linksTable.el_to_domain_index LIKE {$this->dbr->addQuotes( $pattern )}",
					$domainPatterns
				);

				$where = "EXISTS(SELECT el_from FROM $linksTable " .
					" WHERE ($linksTable.el_from=page_id " .
					" AND ({$this->dbr->makeList( $ors, IDatabase::LIST_OR )})))";
			}

			$this->addWhere( $where );
		}
	}

	/**
	 * Set SQL for 'linkstoexternalpath' parameter.
	 *
	 * @param mixed $option
	 */
	private function _linkstoexternalpath( $option ) {
		if ( $this->parameters->getParameter( 'distinct' ) == 'strict' ) {
			$this->addGroupBy( 'page_title' );
		}

		if ( count( $option ) == 0 ) {
			// Nothing to do
			return;
		}

		$this->addTable( 'externallinks', 'el' );
		$this->addSelect( [ 'el_to_path' => 'el.el_to_path' ] );

		foreach ( $option as $index => $paths ) {
			if ( $index == 0 ) {
				$ors = array_map(
					fn ( $path ) => "el.el_to_path LIKE {$this->dbr->addQuotes( $path )}",
					$paths
				);

				$where = "{$this->dbr->tableName( 'page' )}.page_id=el.el_from " .
					" AND ({$this->dbr->makeList( $ors, IDatabase::LIST_OR )})";
			} else {
				$linksTable = $this->dbr->tableName( 'externallinks' );
				$ors = array_map(
					fn ( $path ) => "$linksTable.el_to_path LIKE {$this->dbr->addQuotes( $path )}",
					$paths
				);

				$where = "EXISTS(SELECT el_from FROM $linksTable " .
					" WHERE ($linksTable.el_from=page_id " .
					" AND ({$this->dbr->makeList( $ors, IDatabase::LIST_OR )})))";
			}

			$this->addWhere( $where );
		}
	}

	/**
	 * Set SQL for 'maxrevisions' parameter.
	 *
	 * @param mixed $option
	 */
	private function _maxrevisions( $option ) {
		$this->addWhere(
			"((SELECT count(rev_aux3.rev_page) FROM {$this->dbr->tableName( 'revision' )}" .
			" AS rev_aux3 WHERE rev_aux3.rev_page = {$this->dbr->tableName( 'page' )}.page_id) <= {$option})"
		);
	}

	/**
	 * Set SQL for 'minrevisions' parameter.
	 *
	 * @param mixed $option
	 */
	private function _minrevisions( $option ) {
		$this->addWhere(
			"((SELECT count(rev_aux2.rev_page) FROM {$this->dbr->tableName( 'revision' )}" .
			" AS rev_aux2 WHERE rev_aux2.rev_page = {$this->dbr->tableName( 'page' )}.page_id) >= {$option})"
		);
	}

	/**
	 * Set SQL for 'modifiedby' parameter.
	 *
	 * @param mixed $option
	 */
	private function _modifiedby( $option ) {
		$user = $this->userFactory->newFromName( $option );
		if ( $user->isHidden() ) {
			return;
		}

		$this->addTable( 'revision', 'change_rev' );

		$this->addWhere(
			$this->dbr->addQuotes( $user->getActorId() ) .
			' = change_rev.rev_actor AND change_rev.rev_deleted = 0 AND change_rev.rev_page = page_id'
		);
	}

	/**
	 * Set SQL for 'namespace' parameter.
	 *
	 * @param mixed $option
	 */
	private function _namespace( $option ) {
		if ( is_array( $option ) && count( $option ) ) {
			if ( $this->parameters->getParameter( 'openreferences' ) ) {
				$this->addWhere(
					[
						"{$this->dbr->tableName( 'linktarget' )}.lt_namespace" => $option
					]
				);
			} else {
				$this->addWhere(
					[
						"{$this->dbr->tableName( 'page' )}.page_namespace" => $option
					]
				);
			}
		}
	}

	/**
	 * Set SQL for 'notcreatedby' parameter.
	 *
	 * @param mixed $option
	 */
	private function _notcreatedby( $option ) {
		$user = $this->userFactory->newFromName( $option );
		if ( $user->isHidden() ) {
			return;
		}

		$this->addTable( 'revision', 'no_creation_rev' );

		$this->addWhere(
			$this->dbr->addQuotes( $user->getActorId() ) .
			' != no_creation_rev.rev_actor AND no_creation_rev.rev_deleted = 0 ' .
			'AND no_creation_rev.rev_page = page_id AND no_creation_rev.rev_parent_id = 0'
		);
	}

	/**
	 * Set SQL for 'notlastmodifiedby' parameter.
	 *
	 * @param mixed $option
	 */
	private function _notlastmodifiedby( $option ) {
		$user = $this->userFactory->newFromName( $option );
		if ( $user->isHidden() ) {
			return;
		}

		$this->addWhere(
			$this->dbr->addQuotes( $user->getActorId() ) .
			' != (SELECT rev_actor FROM ' . $this->dbr->tableName( 'revision' ) .
			' WHERE ' . $this->dbr->tableName( 'revision' ) . '.rev_page = page_id' .
			' AND ' . $this->dbr->tableName( 'revision' ) . '.rev_deleted = 0' .
			' ORDER BY ' . $this->dbr->tableName( 'revision' ) . '.rev_timestamp DESC LIMIT 1)'
		);
	}

	/**
	 * Set SQL for 'notmodifiedby' parameter.
	 *
	 * @param mixed $option
	 */
	private function _notmodifiedby( $option ) {
		$user = $this->userFactory->newFromName( $option );
		if ( $user->isHidden() ) {
			return;
		}

		$actorID = $this->dbr->addQuotes( $user->getActorId() );
		$this->addWhere(
			'NOT EXISTS (SELECT 1 FROM ' .
			$this->dbr->tableName( 'revision' ) .
			' WHERE ' . $this->dbr->tableName( 'revision' ) . '.rev_page = page_id' .
			' AND ' . $this->dbr->tableName( 'revision' ) . '.rev_actor = ' . $actorID .
			' AND ' . $this->dbr->tableName( 'revision' ) . '.rev_deleted = 0' .
			' LIMIT 1)'
		);
	}

	/**
	 * Set SQL for 'notnamespace' parameter.
	 *
	 * @param mixed $option
	 */
	private function _notnamespace( $option ) {
		if ( is_array( $option ) && count( $option ) ) {
			if ( $this->parameters->getParameter( 'openreferences' ) ) {
				$this->addNotWhere(
					[
						"{$this->dbr->tableName( 'linktarget' )}.lt_namespace" => $option
					]
				);
			} else {
				$this->addNotWhere(
					[
						"{$this->dbr->tableName( 'page' )}.page_namespace" => $option
					]
				);
			}
		}
	}

	/**
	 * Set SQL for 'count' parameter.
	 *
	 * @param mixed $option
	 */
	private function _count( $option ) {
		$this->setLimit( $option );
	}

	/**
	 * Set SQL for 'offset' parameter.
	 *
	 * @param mixed $option
	 */
	private function _offset( $option ) {
		$this->setOffset( $option );
	}

	/**
	 * Set SQL for 'order' parameter.
	 *
	 * @param mixed $option
	 */
	private function _order( $option ) {
		$orderMethod = $this->parameters->getParameter( 'ordermethod' );

		if ( $orderMethod && is_array( $orderMethod ) && $orderMethod[0] !== 'none' ) {
			if ( $option === 'descending' || $option === 'desc' ) {
				$this->setOrderDir( 'DESC' );
			} else {
				$this->setOrderDir( 'ASC' );
			}
		}
	}

	/**
	 * Set SQL for 'ordercollation' parameter.
	 *
	 * @param mixed $option
	 * @return bool
	 */
	private function _ordercollation( $option ) {
		$option = mb_strtolower( $option );

		$res = $this->dbr->query( 'SHOW CHARACTER SET', __METHOD__ );
		if ( !$res ) {
			return false;
		}

		foreach ( $res as $row ) {
			if ( $option == $row->{'Default collation'} ) {
				$this->setCollation( $option );
				break;
			}
		}

		return true;
	}

	/**
	 * Set SQL for 'ordermethod' parameter.
	 *
	 * @param mixed $option
	 * @return bool
	 */
	private function _ordermethod( $option ) {
		if ( $this->parameters->getParameter( 'goal' ) == 'categories' ) {
			// No order methods for returning categories.
			return true;
		}

		$services = MediaWikiServices::getInstance();
		$namespaces = $services->getContentLanguage()->getNamespaces();

		// $aStrictNs = array_slice(
		// (array)Config::getSetting( 'allowedNamespaces' ), 1,
		// count( Config::getSetting( 'allowedNamespaces' ) ), true );

		$namespaces = array_slice( $namespaces, 3, count( $namespaces ), true );
		$_namespaceIdToText = "CASE {$this->dbr->tableName( 'page' )}.page_namespace";

		foreach ( $namespaces as $id => $name ) {
			$_namespaceIdToText .= ' WHEN ' . (int)$id . ' THEN ' . $this->dbr->addQuotes( $name . ':' );
		}

		$_namespaceIdToText .= ' END';

		$option = (array)$option;

		foreach ( $option as $orderMethod ) {
			switch ( $orderMethod ) {
				case 'category':
					$this->addOrderBy( 'cl_head.cl_to' );
					$this->addSelect( [ 'cl_head.cl_to' ] );

					if (
						(
							is_array( $this->parameters->getParameter( 'catheadings' ) ) &&
							in_array( '', $this->parameters->getParameter( 'catheadings' ) )
						) ||
						(
							is_array( $this->parameters->getParameter( 'catnotheadings' ) ) &&
							in_array( '', $this->parameters->getParameter( 'catnotheadings' ) )
						)
					) {
						$_clTableName = 'dpl_clview';
						$_clTableAlias = $_clTableName;
					} else {
						$_clTableName = 'categorylinks';
						$_clTableAlias = 'cl_head';
					}

					$this->addTable( $_clTableName, $_clTableAlias );
					$this->addJoin(
						$_clTableAlias,
						[
							'LEFT OUTER JOIN',
							'page_id = cl_head.cl_from'
						]
					);

					if (
						is_array( $this->parameters->getParameter( 'catheadings' ) ) &&
						count( $this->parameters->getParameter( 'catheadings' ) )
					) {
						$this->addWhere(
							[
								'cl_head.cl_to' => $this->parameters->getParameter( 'catheadings' )
							]
						);
					}

					if (
						is_array( $this->parameters->getParameter( 'catnotheadings' ) ) &&
						count( $this->parameters->getParameter( 'catnotheadings' ) )
					) {
						$this->addNotWhere(
							[
								'cl_head.cl_to' => $this->parameters->getParameter( 'catnotheadings' )
							]
						);
					}
					break;
				case 'categoryadd':
					// @TODO: See TODO in __addfirstcategorydate().
					$this->addOrderBy( 'cl1.cl_timestamp' );
					break;
				case 'counter':
					if ( ExtensionRegistry::getInstance()->isLoaded( 'HitCounters' ) ) {
						// If the "addpagecounter" parameter was not used the table and join need to be added now.
						if ( !array_key_exists( 'hit_counter', $this->tables ) ) {
							$this->addTable( 'hit_counter', 'hit_counter' );

							if ( !isset( $this->join['hit_counter'] ) ) {
								$this->addJoin(
									'hit_counter',
									[
										'LEFT JOIN',
										'hit_counter.page_id = ' . $this->dbr->tableName( 'page' ) . '.page_id'
									]
								);
							}
						}

						$this->addOrderBy( 'hit_counter.page_counter' );
					}
					break;
				case 'firstedit':
					$this->addOrderBy( 'rev.rev_timestamp' );
					$this->addTable( 'revision', 'rev' );

					$this->addSelect(
						[
							'rev.rev_timestamp'
						]
					);

					if ( !$this->revisionAuxWhereAdded ) {
						$this->addWhere( [
							"{$this->dbr->tableName( 'page' )}.page_id = rev.rev_page",
							"rev.rev_timestamp = (SELECT MIN(rev_aux.rev_timestamp) FROM " .
							"{$this->dbr->tableName( 'revision' )} AS rev_aux WHERE rev_aux.rev_page=rev.rev_page)"
						] );
					}

					$this->revisionAuxWhereAdded = true;
					break;
				case 'lastedit':
					if ( Hooks::isLikeIntersection() ) {
						$this->addOrderBy( 'page_touched' );
						$this->addSelect(
							[
								'page_touched' => "{$this->dbr->tableName( 'page' )}.page_touched"
							]
						);
					} else {
						$this->addOrderBy( 'rev.rev_timestamp' );
						$this->addTable( 'revision', 'rev' );
						$this->addSelect( [ 'rev.rev_timestamp' ] );

						if ( !$this->revisionAuxWhereAdded ) {
							$this->addWhere( "{$this->dbr->tableName( 'page' )}.page_id = rev.rev_page" );

							if ( $this->parameters->getParameter( 'minoredits' ) == 'exclude' ) {
								$this->addWhere(
									'rev.rev_timestamp = (SELECT MAX(rev_aux.rev_timestamp) FROM ' .
									$this->dbr->tableName( 'revision' ) .
									' AS rev_aux WHERE rev_aux.rev_page = rev.rev_page AND rev_aux.rev_minor_edit = 0)'
								);
							} else {
								$this->addWhere(
									'rev.rev_timestamp = (SELECT MAX(rev_aux.rev_timestamp) FROM ' .
									$this->dbr->tableName( 'revision' ) .
									' AS rev_aux WHERE rev_aux.rev_page = rev.rev_page)'
								);
							}
						}

						$this->revisionAuxWhereAdded = true;
					}
					break;
				case 'pagesel':
					$this->addOrderBy( 'sortkey' );
					$this->addSelect(
						[
							'sortkey' => 'CONCAT(lt.lt_namespace, lt.lt_title) ' . $this->getCollateSQL()
						]
					);
					break;
				case 'pagetouched':
					$this->addOrderBy( 'page_touched' );
					$this->addSelect(
						[
							'page_touched' => "{$this->dbr->tableName( 'page' )}.page_touched"
						]
					);
					break;
				case 'size':
					$this->addOrderBy( 'page_len' );
					break;
				case 'sortkey':
					$this->addOrderBy( 'sortkey' );

					// If cl_sortkey is null (uncategorized page), generate a sortkey in
					// the usual way (full page name, underscores replaced with spaces).
					// UTF-8 created problems with non-utf-8 MySQL databases
					$replaceConcat = "REPLACE(CONCAT({$_namespaceIdToText}, " .
						$this->dbr->tableName( 'page' ) . ".page_title), '_', ' ')";

					$category = (array)$this->parameters->getParameter( 'category' );
					$notCategory = (array)$this->parameters->getParameter( 'notcategory' );
					if ( count( $category ) + count( $notCategory ) > 0 ) {
						if ( in_array( 'category', $this->parameters->getParameter( 'ordermethod' ) ) ) {
							$this->addSelect( [
								'sortkey' => "IFNULL(cl_head.cl_sortkey, {$replaceConcat}) " .
									 $this->getCollateSQL()
							] );
						} else {
							// This runs on the assumption that at least one category parameter
							// was used and that numbering starts at 1.
							$this->addSelect(
								[
									'sortkey' => "IFNULL(cl1.cl_sortkey, {$replaceConcat}) " . $this->getCollateSQL()
								]
							);
						}
					} else {
						$this->addSelect(
							[
								'sortkey' => $replaceConcat . $this->getCollateSQL()
							]
						);
					}
					break;
				case 'titlewithoutnamespace':
					if ( $this->parameters->getParameter( 'openreferences' ) ) {
						$this->addOrderBy( 'lt_title' );
					} else {
						$this->addOrderBy( 'page_title' );
					}

					$this->addSelect(
						[
							'sortkey' => "{$this->dbr->tableName( 'page' )}.page_title " . $this->getCollateSQL()
						]
					);
					break;
				case 'title':
					$this->addOrderBy( 'sortkey' );
					if ( $this->parameters->getParameter( 'openreferences' ) ) {
						$this->addSelect( [
							'sortkey' => "REPLACE(CONCAT(IF(lt_namespace =0, '', CONCAT(" .
								 $_namespaceIdToText . ", ':')), lt_title), '_', ' ') " .
								 $this->getCollateSQL()
						] );
					} else {
						// Generate sortkey like for category links.
						// UTF-8 created problems with non-utf-8 MySQL databases.
						$this->addSelect( [
							'sortkey' => "REPLACE(CONCAT(IF(" . $this->dbr->tableName( 'page' ) .
								".page_namespace = 0, '', CONCAT(" . $_namespaceIdToText . ", ':')), " .
								$this->dbr->tableName( 'page' ) . ".page_title), '_', ' ') " .
								$this->getCollateSQL()
						] );
					}
					break;
				case 'user':
					$this->addOrderBy( 'rev.rev_actor' );
					$this->addTable( 'revision', 'rev' );

					$this->_adduser( null, 'rev' );
					break;
				case 'none':
					break;
			}
		}
	}

	/**
	 * Set SQL for 'redirects' parameter.
	 *
	 * @param mixed $option
	 */
	private function _redirects( $option ) {
		if ( !$this->parameters->getParameter( 'openreferences' ) ) {
			switch ( $option ) {
				case 'only':
					$this->addWhere(
						[
							$this->dbr->tableName( 'page' ) . '.page_is_redirect' => 1
						]
					);
					break;
				case 'exclude':
					$this->addWhere(
						[
							$this->dbr->tableName( 'page' ) . '.page_is_redirect' => 0
						]
					);
					break;
			}
		}
	}

	/**
	 * Set SQL for 'stablepages' parameter.
	 *
	 * @param mixed $option
	 */
	private function _stablepages( $option ) {
		if ( function_exists( 'efLoadFlaggedRevs' ) ) {
			// Do not add this again if 'qualitypages' has already added it.
			if ( !$this->parametersProcessed['qualitypages'] ) {
				$this->addJoin(
					'flaggedpages',
					[
						'LEFT JOIN',
						'page_id = fp_page_id'
					]
				);
			}

			switch ( $option ) {
				case 'only':
					$this->addWhere(
						[
							'fp_stable IS NOT NULL'
						]
					);
					break;
				case 'exclude':
					$this->addWhere(
						[
							'fp_stable' => null
						]
					);
					break;
			}
		}
	}

	/**
	 * Set SQL for 'qualitypages' parameter.
	 *
	 * @param mixed $option
	 */
	private function _qualitypages( $option ) {
		if ( function_exists( 'efLoadFlaggedRevs' ) ) {
			// Do not add this again if 'stablepages' has already added it.
			if ( !$this->parametersProcessed['stablepages'] ) {
				$this->addJoin(
					'flaggedpages',
					[
						'LEFT JOIN',
						'page_id = fp_page_id'
					]
				);
			}

			switch ( $option ) {
				case 'only':
					$this->addWhere( 'fp_quality >= 1' );
					break;
				case 'exclude':
					$this->addWhere( 'fp_quality = 0' );
					break;
			}
		}
	}

	/**
	 * Set SQL for 'title' parameter.
	 *
	 * @param mixed $option
	 */
	private function _title( $option ) {
		$ors = [];

		foreach ( $option as $comparisonType => $titles ) {
			foreach ( $titles as $title ) {
				if ( $this->parameters->getParameter( 'openreferences' ) ) {
					if ( $this->parameters->getParameter( 'ignorecase' ) ) {
						$_or = "LOWER(CAST(lt_title AS char)) {$comparisonType}" .
							strtolower( $this->dbr->addQuotes( $title ) );
					} else {
						$_or = "lt_title {$comparisonType} " . $this->dbr->addQuotes( $title );
					}
				} else {
					if ( $this->parameters->getParameter( 'ignorecase' ) ) {
						$_or = "LOWER(CAST({$this->dbr->tableName( 'page' )}.page_title AS char)) {$comparisonType}" .
							strtolower( $this->dbr->addQuotes( $title ) );
					} else {
						$_or = "{$this->dbr->tableName( 'page' )}.page_title {$comparisonType}" .
							$this->dbr->addQuotes( $title );
					}
				}

				$ors[] = $_or;
			}
		}

		$where = '(' . implode( ' OR ', $ors ) . ')';
		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'nottitle' parameter.
	 *
	 * @param mixed $option
	 */
	private function _nottitle( $option ) {
		$ors = [];

		foreach ( $option as $comparisonType => $titles ) {
			foreach ( $titles as $title ) {
				if ( $this->parameters->getParameter( 'openreferences' ) ) {
					if ( $this->parameters->getParameter( 'ignorecase' ) ) {
						$_or = "LOWER(CAST(lt_title AS char)) {$comparisonType}" .
							strtolower( $this->dbr->addQuotes( $title ) );
					} else {
						$_or = "lt_title {$comparisonType} " . $this->dbr->addQuotes( $title );
					}
				} else {
					if ( $this->parameters->getParameter( 'ignorecase' ) ) {
						$_or = "LOWER(CAST({$this->dbr->tableName( 'page' )}.page_title AS char)) {$comparisonType}" .
							strtolower( $this->dbr->addQuotes( $title ) );
					} else {
						$_or = "{$this->dbr->tableName( 'page' )}.page_title {$comparisonType}" .
							$this->dbr->addQuotes( $title );
					}
				}

				$ors[] = $_or;
			}
		}

		$where = 'NOT (' . implode( ' OR ', $ors ) . ')';
		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'titlegt' parameter.
	 *
	 * @param mixed $option
	 */
	private function _titlegt( $option ) {
		$operator = '>';
		if ( substr( $option, 0, 2 ) === '=_' ) {
			$option = substr( $option, 2 );
			$operator = '>=';
		}

		if ( $option === '' ) {
			$operator = 'LIKE';
			$option = '%';
		}

		$option = $this->dbr->addQuotes( $option );

		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			$where = "(lt_title {$operator} {$option})";
		} else {
			$where = "({$this->dbr->tableName( 'page' )}.page_title {$operator} {$option})";
		}

		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'titlelt' parameter.
	 *
	 * @param mixed $option
	 */
	private function _titlelt( $option ) {
		$operator = '<';
		if ( substr( $option, 0, 2 ) === '=_' ) {
			$option = substr( $option, 2 );
			$operator = '<=';
		}

		if ( $option === '' ) {
			$operator = 'LIKE';
			$option = '%';
		}

		$option = $this->dbr->addQuotes( $option );

		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			$where = "(lt_title {$operator} {$option})";
		} else {
			$where = "({$this->dbr->tableName( 'page' )}.page_title {$operator} {$option})";
		}

		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'usedby' parameter.
	 *
	 * @param mixed $option
	 */
	private function _usedby( $option ) {
		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			$ors = [];

			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$ors[] = 'tpl_from = ' . (int)$link->getArticleID();
				}
			}

			$where = '(' . implode( ' OR ', $ors ) . ')';
		} else {
			$this->addTables( [
				'linktarget' => 'lt',
				'templatelinks' => 'tpl',
			] );

			$linksMigration = MediaWikiServices::getInstance()->getLinksMigration();
			[ $nsField, $titleField ] = $linksMigration->getTitleFields( 'templatelinks' );

			$this->addSelect( [
				'tpl_sel_title' => "{$this->dbr->tableName( 'page' )}.page_title",
				'tpl_sel_ns' => "{$this->dbr->tableName( 'page' )}.page_namespace"
			] );

			$this->addJoin(
				'lt',
				[ 'JOIN', [ "page_title = $titleField", "page_namespace = $nsField" ] ]
			);
			$this->addJoin( 'tpl', [ 'JOIN', 'lt_id = tl_target_id', ]
			);
			$ors = [];

			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$ors[] = 'tpl.tl_from = ' . (int)$link->getArticleID();
				}
			}

			$where = '(' . implode( ' OR ', $ors ) . ')';
		}
		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'uses' parameter.
	 *
	 * @param mixed $option
	 */
	private function _uses( $option ) {
		$this->addTables( [
			'linktarget' => 'lt',
			'templatelinks' => 'tl',
		] );

		$where = $this->dbr->tableName( 'page' ) . '.page_id=tl.tl_from AND lt.lt_id = tl.tl_target_id AND (';
		$ors = [];

		$linksMigration = MediaWikiServices::getInstance()->getLinksMigration();
		[ $nsField, $titleField ] = $linksMigration->getTitleFields( 'templatelinks' );

		foreach ( $option as $linkGroup ) {
			foreach ( $linkGroup as $link ) {
				$_or = '(lt.' . $nsField . '=' . (int)$link->getNamespace();

				if ( $this->parameters->getParameter( 'ignorecase' ) ) {
					$_or .= ' AND LOWER(CAST(lt.' . $titleField . ' AS char)) = LOWER(' .
						$this->dbr->addQuotes( $link->getDBkey() ) . '))';
				} else {
					$_or .= ' AND ' . $titleField . ' = ' . $this->dbr->addQuotes( $link->getDBkey() ) . ')';
				}

				$ors[] = $_or;
			}
		}

		$where .= implode( ' OR ', $ors ) . ')';
		$this->addWhere( $where );
	}

	/**
	 * Set SQL for 'notuses' parameter.
	 *
	 * @param mixed $option
	 */
	private function _notuses( $option ) {
		if ( count( $option ) > 0 ) {
			$where = $this->dbr->tableName( 'page' ) . '.page_id NOT IN (SELECT ' .
				$this->dbr->tableName( 'templatelinks' ) . '.tl_from FROM ' .
				$this->dbr->tableName( 'templatelinks' ) . ' INNER JOIN ' .
				$this->dbr->tableName( 'linktarget' ) . ' ON ' .
				$this->dbr->tableName( 'linktarget' ) . '.lt_id = ' .
				$this->dbr->tableName( 'templatelinks' ) . '.tl_target_id WHERE (';

			$ors = [];

			$linksMigration = MediaWikiServices::getInstance()->getLinksMigration();
			[ $nsField, $titleField ] = $linksMigration->getTitleFields( 'templatelinks' );

			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$_or = "({$this->dbr->tableName( 'linktarget' )}.$nsField = {$link->getNamespace()}";

					if ( $this->parameters->getParameter( 'ignorecase' ) ) {
						$_or .= ' AND LOWER(CAST(' . $this->dbr->tableName( 'linktarget' ) . '.' .
							$titleField . ' AS char)) = LOWER(' .
							$this->dbr->addQuotes( $link->getDBkey() ) . '))';
					} else {
						$_or .= ' AND ' . $this->dbr->tableName( 'linktarget' ) . '.' .
							$titleField . ' = ' . $this->dbr->addQuotes( $link->getDBkey() ) . ')';
					}
					$ors[] = $_or;
				}
			}

			$where .= implode( ' OR ', $ors ) . '))';
		}

		$this->addWhere( $where ?? '' );
	}

	private function isPageselFormatUsed(): bool {
		if ( $this->parameters->getParameter( 'listseparators' ) ) {
			$format = implode( ',', $this->parameters->getParameter( 'listseparators' ) );
			if ( strstr( $format, '%PAGESEL%' ) ) {
				return true;
			}
		}
		return false;
	}
}
