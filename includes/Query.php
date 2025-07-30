<?php

namespace MediaWiki\Extension\DynamicPageList4;

use LogicException;
use MediaWiki\ExternalLinks\LinkFilter;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;
use MediaWiki\PoolCounter\PoolCounterWorkViaCallback;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserFactory;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\DBQueryError;
use Wikimedia\Rdbms\Expression;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LikeMatch;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\Rdbms\SelectQueryBuilder;
use Wikimedia\Rdbms\Subquery;
use Wikimedia\Timestamp\TimestampException;
use function array_map;
use function array_merge;
use function array_slice;
use function array_unique;
use function bin2hex;
use function count;
use function floor;
use function hash;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_string;
use function iterator_to_array;
use function mb_strtolower;
use function mb_strtoupper;
use function method_exists;
use function min;
use function preg_split;
use function random_bytes;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtotime;
use function substr;
use function wfMessage;
use const NS_CATEGORY;
use const NS_FILE;
use const NS_MAIN;
use const PREG_SPLIT_DELIM_CAPTURE;
use const PREG_SPLIT_NO_EMPTY;

class Query {

	private readonly Config $config;
	private readonly IReadableDatabase $dbr;
	private readonly SelectQueryBuilder $queryBuilder;
	private readonly UserFactory $userFactory;

	/** Parameters that have already been processed. */
	private array $parametersProcessed = [];

	/** The generated SQL Query. */
	private string $sqlQuery = '';
	private array $orderBy = [];

	private ?int $limit = null;
	private ?int $offset = null;

	private string $direction = SelectQueryBuilder::SORT_ASC;

	private ?string $charset = null;
	private ?string $collation = null;

	/** Was the revision auxiliary table select added for firstedit and lastedit? */
	private bool $revisionAuxWhereAdded = false;

	public function __construct(
		private readonly Parameters $parameters
	) {
		$this->dbr = MediaWikiServices::getInstance()->getConnectionProvider()
			->getReplicaDatabase( false, 'dpl4' );

		$this->config = Config::getInstance();
		$this->queryBuilder = $this->dbr->newSelectQueryBuilder();
		$this->userFactory = MediaWikiServices::getInstance()->getUserFactory();
	}

	/**
	 * Start a query build. Returns found rows.
	 *
	 * @param bool $calcRows Whether we need to calculate the found rows count.
	 * @param string $profilingContext Used to see the origin of a query in the profiling.
	 */
	public function buildAndSelect( bool $calcRows, string $profilingContext ): array|false {
		$parameters = $this->parameters->getAllParameters();
		foreach ( $parameters as $parameter => $option ) {
			if ( $option === [] ) {
				// We don't need to run on empty arrays.
				continue;
			}

			$method = '_' . $parameter;
			// Some parameters do not modify the query so we check if the function to modify the query exists first.
			if ( method_exists( $this, $method ) ) {
				$this->$method( $option );
			}

			$this->parametersProcessed[$parameter] = true;
		}

		if ( !$this->parameters->getParameter( 'openreferences' ) ) {
			// Add things that are always part of the query.
			$this->queryBuilder->table( 'page' );
			$this->queryBuilder->select( [
				'page_namespace' => 'page.page_namespace',
				'page_id' => 'page.page_id',
				'page_title' => 'page.page_title',
			] );
		}

		// Never add nonincludeable namespaces.
		if ( $this->config->get( MainConfigNames::NonincludableNamespaces ) ) {
			$this->queryBuilder->andWhere( $this->dbr->expr(
				'page.page_namespace', '!=',
				$this->config->get( MainConfigNames::NonincludableNamespaces )
			) );
		}

		if ( $this->offset !== null ) {
			$this->queryBuilder->offset( $this->offset );
		}

		if ( $this->limit !== null ) {
			$this->queryBuilder->limit( $this->limit );
		} elseif ( $this->offset !== null ) {
			$this->queryBuilder->limit( $this->parameters->getParameter( 'count' ) );
		}

		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			if ( $this->parameters->getParameter( 'imagecontainer' ) ) {
				$this->queryBuilder->select( 'il_to' );
				$this->queryBuilder->table( 'imagelinks', 'ic' );
			} else {
				$this->queryBuilder->table( 'pagelinks', 'pl' );
				$this->queryBuilder->join( 'linktarget', 'lt', 'pl.pl_target_id = lt.lt_id' );
				$this->queryBuilder->leftJoin( 'page', null, [
					'lt.lt_namespace = page.page_namespace',
					'lt.lt_title = page.page_title',
				] );

				if ( $this->parameters->getParameter( 'openreferences' ) === 'missing' ) {
					$this->queryBuilder->select( [
						'page_namespace' => 'page.page_namespace',
						'page_id' => 'page.page_id',
						'page_title' => 'page.page_title',
						'lt_namespace' => 'lt.lt_namespace',
						'lt_title' => 'lt.lt_title',
					] );

					$this->queryBuilder->where( [ 'page.page_namespace' => null ] );
				} else {
					$this->queryBuilder->select( [
						'page_id' => 'page.page_id',
						'lt_namespace' => 'lt.lt_namespace',
						'lt_title' => 'lt.lt_title',
					] );
				}
			}
		} elseif ( $this->orderBy ) {
			$this->queryBuilder->orderBy( $this->orderBy, $this->direction );
		}

		if ( $this->parameters->getParameter( 'goal' ) === 'categories' ) {
			$categoriesGoal = true;
			$this->queryBuilder->select( 'page.page_id' );
			$this->queryBuilder->distinct();
		} else {
			if ( $calcRows ) {
				$this->queryBuilder->calcFoundRows();
			}

			$categoriesGoal = false;
		}

		try {
			if ( $categoriesGoal ) {
				$this->queryBuilder->caller( __METHOD__ );
				$res = $this->queryBuilder->fetchResultSet();

				$pageIds = [];
				foreach ( $res as $row ) {
					$pageIds[] = $row->page_id;
				}

				$query = $this->dbr->newSelectQueryBuilder()
					->table( 'categorylinks', 'clgoal' )
					->select( 'clgoal.cl_to' )
					->where( [ 'clgoal.cl_from' => $pageIds ] )
					->caller( __METHOD__ )
					->orderBy( 'clgoal.cl_to', $this->direction )
					->getSQL();
			} else {
				$this->queryBuilder->caller( __METHOD__ );
				$query = $this->queryBuilder->getSQL();
			}

			if ( Utils::getDebugLevel() >= 4 && $this->config->get( MainConfigNames::DebugDumpSql ) ) {
				$this->sqlQuery = $query;
			}
		} catch ( DBQueryError $e ) {
			$errorMessage = $this->dbr->lastError();
			if ( $errorMessage === '' ) {
				$errorMessage = (string)$e;
			}

			throw new LogicException( __METHOD__ . ': ' . wfMessage(
				'dpl_query_error', Utils::getVersion(), $errorMessage
			)->text() );
		}

		// Partially taken from intersection
		$queryCacheTime = $this->config->get( 'queryCacheTime' );
		$maxQueryTime = $this->config->get( 'maxQueryTime' );

		if ( $maxQueryTime ) {
			$this->queryBuilder->setMaxExecutionTime( $maxQueryTime );
		}

		$qname = __METHOD__;
		if ( $profilingContext !== '' ) {
			$qname .= ' - ' . $profilingContext;
		}

		$this->queryBuilder->caller( $qname );

		$doQuery = function () use ( $calcRows ): array {
			$res = $this->queryBuilder->fetchResultSet();
			$res = iterator_to_array( $res );

			if ( $calcRows ) {
				$res['count'] = $this->dbr->newSelectQueryBuilder()
					->tables( $this->queryBuilder->getQueryInfo()['tables'] )
					->select( 'FOUND_ROWS()' )
					->caller( $this->queryBuilder->getQueryInfo()['caller'] )
					->fetchField();
			}

			return $res;
		};

		$poolCounterKey = 'nowait:dpl4-query:' . WikiMap::getCurrentWikiId();
		$worker = new PoolCounterWorkViaCallback( 'DPL4', $poolCounterKey, [
			'doWork' => $doQuery,
		] );

		if ( $queryCacheTime <= 0 ) {
			return $worker->execute();
		}

		$cache = MediaWikiServices::getInstance()->getMainWANObjectCache();

		return $cache->getWithSetCallback(
			$cache->makeKey( 'DPL4Query', hash( 'sha256', $query ) ),
			$queryCacheTime,
			function ( mixed $oldVal, int &$ttl, array &$setOpts ) use ( $worker ): array|false {
				$setOpts += Database::getCacheSetOptions( $this->dbr );
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
				'pcTTL' => min( $cache::TTL_PROC_LONG, $queryCacheTime ),
			]
		);
	}

	/**
	 * Returns the generated SQL Query.
	 */
	public function getSqlQuery(): string {
		return $this->sqlQuery;
	}

	/**
	 * Add a ORDER BY clause to the query builder.
	 */
	private function addOrderBy( string $orderBy ): void {
		$this->orderBy[] = $orderBy;
	}

	/**
	 * Set the limit to the query builder.
	 */
	private function setLimit( ?int $limit ): void {
		$this->limit = $limit;
	}

	/**
	 * Set the offset to the query builder.
	 */
	private function setOffset( ?int $offset ): void {
		$this->offset = $offset;
	}

	/**
	 * Set the ORDER BY direction to the query builder.
	 */
	private function setOrderDir( string $direction ): void {
		$this->direction = $direction;
	}

	private function applyCollation( string $expression ): string {
		if ( $this->collation === null ) {
			return $expression;
		}

		$dbType = $this->dbr->getType();
		return match ( $dbType ) {
			'mysql' => mb_strtolower( $this->collation ) === 'binary' ? "CAST($expression AS BINARY)" :
				"CAST($expression AS CHAR CHARACTER SET {$this->charset}) COLLATE {$this->collation}",
			'postgres' => "$expression COLLATE \"{$this->collation}\"",
			'sqlite' => "$expression COLLATE {$this->collation}",
			default => $expression,
		};
	}

	/**
	 * Recursively get and return an array of subcategories.
	 */
	public static function getSubcategories( string $categoryName, int $depth ): array {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()
			->getReplicaDatabase( group: 'dpl4' );

		if ( $depth > 2 ) {
			// Hard constrain depth because lots of recursion is bad.
			$depth = 2;
		}

		$categories = $dbr->newSelectQueryBuilder()
			->select( 'page_title' )
			->from( 'page' )
			->join( 'categorylinks', 'cl', 'page_id = cl.cl_from' )
			->where( [
				'page_namespace' => NS_CATEGORY,
				'cl.cl_to' => str_replace( ' ', '_', $categoryName ),
			] )
			->caller( __METHOD__ )
			->distinct()
			->fetchFieldValues();

		foreach ( $categories as $category ) {
			if ( $depth > 1 ) {
				$categories = array_merge( $categories, self::getSubcategories( $category, $depth - 1 ) );
			}
		}

		return array_unique( $categories );
	}

	/**
	 * Helper method to handle relative timestamps.
	 */
	private function convertTimestamp( string $inputDate ): string {
		try {
			if ( is_numeric( $inputDate ) ) {
				return $this->dbr->timestamp( $inputDate );
			}

			// Apply relative time modifications like 'last week', '-1 day', '5 days ago', etc...
			$timestamp = strtotime( $inputDate );
			if ( $timestamp !== false ) {
				return $this->dbr->timestamp( $timestamp );
			}
		} catch ( TimestampException ) {
			// Handle the failure below
		}

		throw new LogicException( "Invalid timestamp: $inputDate" );
	}

	private function caseInsensitiveComparison(
		string $field,
		string $operator,
		string|LikeValue $value
	): string {
		$dbType = $this->dbr->getType();
		if ( is_string( $value ) ) {
			// For REGEXP we'll lowercase without quoting here since
			// buildRegexpExpression already handles quoting.
			$lowered = mb_strtolower( $value, 'UTF-8' );
			$value = $operator === 'REGEXP' ? $lowered :
				$this->dbr->addQuotes( $lowered );
		} else {
			$value = $value->toSql( $this->dbr );
		}

		if ( $dbType === 'mysql' ) {
			$fieldExpr = "LOWER(CAST($field AS CHAR CHARACTER SET utf8mb4))";
			if ( $operator === 'REGEXP' ) {
				return $this->buildRegexpExpression( $fieldExpr, $value );
			}
			return "$fieldExpr $operator $value";
		}

		if ( $dbType === 'postgres' ) {
			$fieldExpr = "LOWER($field::TEXT)";
			if ( $operator === 'REGEXP' ) {
				return $this->buildRegexpExpression( $fieldExpr, $value );
			}
			return "$fieldExpr $operator $value";
		}

		if ( $dbType === 'sqlite' ) {
			$fieldExpr = "LOWER($field)";
			if ( $operator === 'REGEXP' ) {
				return $this->buildRegexpExpression( $fieldExpr, $value );
			}
			return "$fieldExpr $operator $value";
		}

		throw new LogicException( 'You are using an unsupported database type for ignorecase.' );
	}

	private function buildRegexpExpression( string $field, string $value ): string {
		$dbType = $this->dbr->getType();
		$value = $this->dbr->addQuotes( $value );
		if ( $dbType === 'mysql' || $dbType === 'sqlite' ) {
			return "$field REGEXP $value";
		}

		if ( $dbType === 'postgres' ) {
			return "$field ~ $value";
		}

		throw new LogicException( 'You are using an unsupported database type for REGEXP.' );
	}

	/**
	 * @return non-empty-array<string|LikeMatch>
	 */
	private function splitLikePattern( string $subject ): array {
		$segments = preg_split( '/(%)/', $subject, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		$parts = array_map(
			fn ( string $segment ): string|LikeMatch =>
				$segment === '%' ? $this->dbr->anyString() : $segment,
			$segments
		);
		return $parts ?: [ '' ];
	}

	private function adduser( string $tableAlias ): void {
		if ( $tableAlias !== '' ) {
			$tableAlias .= '.';
		}

		$this->queryBuilder->select( [
			"{$tableAlias}rev_actor",
			"{$tableAlias}rev_deleted",
		] );

		if ( $this->isFormatUsed( '%EDITSUMMARY%' ) &&
			!isset( $this->queryBuilder->getQueryInfo()['fields']['rev_comment_text'] )
		) {
			$subquery = $this->queryBuilder->newSubquery()
				->select( 'comment_text' )
				->from( 'comment' )
				->where( "comment.comment_id = {$tableAlias}rev_comment_id" )
				->limit( 1 )
				->caller( __METHOD__ )
				->getSQL();

			$this->queryBuilder->select( [ 'rev_comment_text' => new Subquery( $subquery ) ] );
		}
	}

	// @phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName
	// @phpcs:disable PSR2.Methods.MethodDeclaration.Underscore

	/**
	 * Set SQL for 'addauthor' parameter.
	 *
	 * @param bool $option @phan-unused-param
	 */
	private function _addauthor( bool $option ): void {
		// Addauthor cannot be used with addlasteditor.
		if ( !isset( $this->parametersProcessed['addlasteditor'] ) || !$this->parametersProcessed['addlasteditor'] ) {
			$this->queryBuilder->table( 'revision', 'rev' );
			$minTimestampSubquery = $this->queryBuilder->newSubquery()
				->select( 'MIN(rev_aux_min.rev_timestamp)' )
				->from( 'revision', 'rev_aux_min' )
				->where( 'rev_aux_min.rev_page = page.page_id' )
				->caller( __METHOD__ )
				->getSQL();

			$this->queryBuilder->where( [
				'page.page_id = rev.rev_page',
				"rev.rev_timestamp = ($minTimestampSubquery)",
			] );

			$this->adduser( tableAlias: 'rev' );
		}
	}

	/**
	 * Set SQL for 'addcategories' parameter.
	 *
	 * @param bool $option @phan-unused-param
	 */
	private function _addcategories( bool $option ): void {
		$this->queryBuilder->table( 'categorylinks', 'cl_gc' );
		$this->queryBuilder->leftJoin( 'categorylinks', 'cl_gc', 'page_id = cl_gc.cl_from' );
		$this->queryBuilder->groupBy( 'page.page_id' );

		$dbType = $this->dbr->getType();
		if ( $dbType === 'mysql' ) {
			$this->queryBuilder->select( [
				'cats' => "GROUP_CONCAT(DISTINCT cl_gc.cl_to ORDER BY cl_gc.cl_to ASC SEPARATOR ' | ')",
			] );
			return;
		}

		if ( $dbType === 'postgres' ) {
			$this->queryBuilder->select( [
				'cats' => "STRING_AGG(cl_gc.cl_to, ' | ' ORDER BY cl_gc.cl_to ASC)",
			] );
			return;
		}

		if ( $dbType === 'sqlite' ) {
			$subquery = $this->queryBuilder->newSubquery()
				->select( 'cl_to' )
				->from( 'categorylinks' )
				->where( 'cl_from = page.page_id' )
				->distinct()
				->orderBy( 'cl_to', SelectQueryBuilder::SORT_ASC )
				->caller( __METHOD__ )
				->getSQL();

			$this->queryBuilder->select( [
				'cats' => "(SELECT GROUP_CONCAT(cl_to, ' | ') FROM ($subquery))",
			] );
			return;
		}

		throw new LogicException( 'You are using an unsupported database type for addcategories.' );
	}

	/**
	 * Set SQL for 'addcontribution' parameter.
	 *
	 * @param bool $option @phan-unused-param
	 */
	private function _addcontribution( bool $option ): void {
		$this->queryBuilder->table( 'recentchanges', 'rc' );
		$this->queryBuilder->select( [
			'contribution' => 'SUM(ABS(rc.rc_new_len - rc.rc_old_len))',
			'contributor' => 'rc.rc_actor',
			'contrib_deleted' => 'rc.rc_deleted',
		] );

		$this->queryBuilder->where( 'page.page_id = rc.rc_cur_id' );
		$this->queryBuilder->groupBy( 'rc.rc_cur_id' );
	}

	/**
	 * Set SQL for 'addeditdate' parameter.
	 *
	 * @param bool $option @phan-unused-param
	 */
	private function _addeditdate( bool $option ): void {
		$this->queryBuilder->table( 'revision', 'rev' );
		$this->queryBuilder->select( 'rev.rev_timestamp' );
		$this->queryBuilder->where( 'page.page_id = rev.rev_page' );
	}

	/**
	 * Set SQL for 'addfirstcategorydate' parameter.
	 *
	 * @param bool $option @phan-unused-param
	 */
	private function _addfirstcategorydate( bool $option ): void {
		// @TODO: This should be programmatically determining which
		// categorylink table to use instead of assuming the first one.
		$this->queryBuilder->select( [ 'cl_timestamp' => 'cl1.cl_timestamp' ] );
	}

	/**
	 * Set SQL for 'addlasteditor' parameter.
	 *
	 * @param bool $option @phan-unused-param
	 */
	private function _addlasteditor( bool $option ): void {
		// Addlasteditor cannot be used with addauthor.
		if ( !isset( $this->parametersProcessed['addauthor'] ) || !$this->parametersProcessed['addauthor'] ) {
			$this->queryBuilder->table( 'revision', 'rev' );
			$maxTimestampSubquery = $this->queryBuilder->newSubquery()
				->select( 'MAX(rev_aux_max.rev_timestamp)' )
				->from( 'revision', 'rev_aux_max' )
				->where( 'rev_aux_max.rev_page = page.page_id' )
				->caller( __METHOD__ )
				->getSQL();

			$this->queryBuilder->where( [
				'page.page_id = rev.rev_page',
				"rev.rev_timestamp = ($maxTimestampSubquery)",
			] );

			$this->adduser( tableAlias: 'rev' );
		}
	}

	/**
	 * Set SQL for 'addpagecounter' parameter.
	 *
	 * @param bool $option @phan-unused-param
	 */
	private function _addpagecounter( bool $option ): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'HitCounters' ) ) {
			return;
		}

		$this->queryBuilder->table( 'hit_counter' );
		$this->queryBuilder->select( [ 'page_counter' => 'hit_counter.page_counter' ] );
		if ( !isset( $this->queryBuilder->getQueryInfo()['join_conds']['hit_counter'] ) ) {
			$this->queryBuilder->leftJoin( 'hit_counter', null,
				'hit_counter.page_id = page.page_id'
			);
		}
	}

	/**
	 * Set SQL for 'addpagesize' parameter.
	 *
	 * @param bool $option @phan-unused-param
	 */
	private function _addpagesize( bool $option ): void {
		$this->queryBuilder->select( [ 'page_len' => 'page.page_len' ] );
	}

	/**
	 * Set SQL for 'addpagetoucheddate' parameter.
	 *
	 * @param bool $option @phan-unused-param
	 */
	private function _addpagetoucheddate( bool $option ): void {
		$this->queryBuilder->select( [ 'page_touched' => 'page.page_touched' ] );
	}

	/**
	 * Set SQL for 'adduser' parameter.
	 *
	 * @param bool $option @phan-unused-param
	 */
	private function _adduser( bool $option ): void {
		$this->addUser( tableAlias: '' );
	}

	/**
	 * Set SQL for 'allrevisionsbefore' parameter.
	 */
	private function _allrevisionsbefore( string $option ): void {
		$this->queryBuilder->table( 'revision', 'rev' );
		$this->queryBuilder->select( [ 'rev.rev_id', 'rev.rev_timestamp' ] );

		$this->addOrderBy( 'rev.rev_id' );
		$this->setOrderDir( SelectQueryBuilder::SORT_DESC );

		$this->queryBuilder->where( [
			'page.page_id = rev.rev_page',
			$this->dbr->expr( 'rev.rev_timestamp', '<', $this->convertTimestamp( $option ) ),
		] );
	}

	/**
	 * Set SQL for 'allrevisionssince' parameter.
	 */
	private function _allrevisionssince( string $option ): void {
		$this->queryBuilder->table( 'revision', 'rev' );
		$this->queryBuilder->select( [ 'rev.rev_id', 'rev.rev_timestamp' ] );

		$this->addOrderBy( 'rev.rev_id' );
		$this->setOrderDir( SelectQueryBuilder::SORT_DESC );

		$this->queryBuilder->where( [
			'page.page_id = rev.rev_page',
			$this->dbr->expr( 'rev.rev_timestamp', '>=', $this->convertTimestamp( $option ) ),
		] );
	}

	/**
	 * Set SQL for 'articlecategory' parameter.
	 */
	private function _articlecategory( string $option ): void {
		$subquery = $this->queryBuilder->newSubquery()
			->select( 'p2.page_title' )
			->from( 'page', 'p2' )
			->join( 'categorylinks', 'clstc', 'clstc.cl_from = p2.page_id' )
			->where( [
				'clstc.cl_to' => $option,
				'p2.page_namespace' => NS_MAIN,
			] )
			->caller( __METHOD__ )
			->getSQL();

		$this->queryBuilder->where( "page.page_title IN ($subquery)" );
	}

	/**
	 * Set SQL for 'categoriesminmax' parameter.
	 */
	private function _categoriesminmax( array $option ): void {
		if ( !is_numeric( $option[0] ) &&
			( !isset( $option[1] ) || !is_numeric( $option[1] ) )
		) {
			// Prevent running the subquery if we aren't doing anything with it.
			return;
		}

		$countSubquery = $this->queryBuilder->newSubquery()
			->select( 'COUNT(*)' )
			->from( 'categorylinks' )
			->where( 'cl_from = page.page_id' )
			->caller( __METHOD__ )
			->getSQL();

		if ( is_numeric( $option[0] ) ) {
			$this->queryBuilder->where( (int)$option[0] . " <= ($countSubquery)" );
		}

		if ( isset( $option[1] ) && is_numeric( $option[1] ) ) {
			$this->queryBuilder->where( (int)$option[1] . " >= ($countSubquery)" );
		}
	}

	/**
	 * Set SQL for 'category' parameter. This includes 'category', 'categorymatch', and 'categoryregexp'.
	 */
	private function _category( array $option ): void {
		$i = 0;
		foreach ( $option as $comparisonType => $operatorTypes ) {
			foreach ( $operatorTypes as $operatorType => $categoryGroups ) {
				foreach ( $categoryGroups as $categories ) {
					if ( !is_array( $categories ) ) {
						continue;
					}

					$tableName = in_array( '', $categories, true ) ? 'dpl_clview' : 'categorylinks';

					if ( $operatorType === 'AND' ) {
						foreach ( $categories as $category ) {
							$i++;
							$tableAlias = "cl{$i}";
							$this->queryBuilder->table( $tableName, $tableAlias );
							$category = str_replace( ' ', '_', $category );
							if ( $comparisonType === IExpression::LIKE ) {
								$category = new LikeValue( ...$this->splitLikePattern( $category ) );
							}

							if ( $comparisonType === 'REGEXP' ) {
								$expr = $this->buildRegexpExpression( "$tableAlias.cl_to", $category );
							}

							$condition = $this->dbr->makeList( [
								"page.page_id = $tableAlias.cl_from",
								$expr ?? $this->dbr->expr( "$tableAlias.cl_to", $comparisonType, $category ),
							], IDatabase::LIST_AND );

							$this->queryBuilder->join( $tableName, $tableAlias, $condition );
						}
						continue;
					}

					if ( $operatorType === 'OR' ) {
						$i++;
						$tableAlias = "cl{$i}";
						$this->queryBuilder->table( $tableName, $tableAlias );

						$ors = [];
						foreach ( $categories as $category ) {
							$category = str_replace( ' ', '_', $category );
							if ( $comparisonType === IExpression::LIKE ) {
								$category = new LikeValue( ...$this->splitLikePattern( $category ) );
							}
							if ( $comparisonType === 'REGEXP' ) {
								$ors[] = $this->buildRegexpExpression( "$tableAlias.cl_to", $category );
								continue;
							}
							$ors[] = $this->dbr->expr( "$tableAlias.cl_to", $comparisonType, $category );
						}

						$condition = $this->dbr->makeList( [
							"page.page_id = $tableAlias.cl_from",
							$this->dbr->makeList( $ors, IDatabase::LIST_OR ),
						], IDatabase::LIST_AND );

						$this->queryBuilder->join( $tableName, $tableAlias, $condition );
					}
				}
			}
		}
	}

	/**
	 * Set SQL for 'notcategory' parameter.
	 */
	private function _notcategory( array $option ): void {
		$i = 0;
		foreach ( $option as $operatorType => $categories ) {
			foreach ( $categories as $category ) {
				$i++;
				$tableAlias = "ecl{$i}";
				$this->queryBuilder->table( 'categorylinks', $tableAlias );
				$category = str_replace( ' ', '_', $category );
				if ( $operatorType === IExpression::LIKE ) {
					$category = new LikeValue( ...$this->splitLikePattern( $category ) );
				}

				if ( $operatorType === 'REGEXP' ) {
					$expr = $this->buildRegexpExpression( "$tableAlias.cl_to", $category );
				}

				$condition = $this->dbr->makeList( [
					"page.page_id = $tableAlias.cl_from",
					$expr ?? $this->dbr->expr( "$tableAlias.cl_to", $operatorType, $category ),
				], IDatabase::LIST_AND );

				$this->queryBuilder->leftJoin( 'categorylinks', $tableAlias, $condition );
				$this->queryBuilder->where( [ "$tableAlias.cl_to" => null ] );
			}
		}
	}

	/**
	 * Set SQL for 'createdby' parameter.
	 */
	private function _createdby( string $option ): void {
		$user = $this->userFactory->newFromName( $option );
		if ( $user->isHidden() ) {
			return;
		}

		$this->queryBuilder->table( 'revision', 'creation_rev' );
		$this->adduser( tableAlias: 'creation_rev' );

		$this->queryBuilder->where( [
			$this->dbr->expr( 'creation_rev.rev_actor', '=', $user->getActorId() ),
			'creation_rev.rev_page = page.page_id',
			'creation_rev.rev_deleted = 0',
			'creation_rev.rev_parent_id = 0',
		] );
	}

	/**
	 * Set SQL for 'distinct' parameter. Either 'strict' or true
	 */
	private function _distinct( string|bool $option ): void {
		if ( $option === 'strict' || $option === true ) {
			$this->queryBuilder->distinct();
		}
	}

	/**
	 * Set SQL for 'firstrevisionsince' parameter.
	 */
	private function _firstrevisionsince( string $option ): void {
		$this->queryBuilder->table( 'revision', 'rev' );
		$this->queryBuilder->select( [ 'rev.rev_id', 'rev.rev_timestamp' ] );

		// Tell the query optimizer not to look at rows that the following subquery will filter out anyway
		$this->queryBuilder->where( [
			'page.page_id = rev.rev_page',
			$this->dbr->expr( 'rev.rev_timestamp', '>=', $this->convertTimestamp( $option ) ),
		] );

		$minTimestampSinceSubquery = $this->queryBuilder->newSubquery()
			->select( 'MIN(rev_aux_snc.rev_timestamp)' )
			->from( 'revision', 'rev_aux_snc' )
			->where( [
				'rev_aux_snc.rev_page = page.page_id',
				$this->dbr->expr( 'rev_aux_snc.rev_timestamp', '>=',
					$this->convertTimestamp( $option )
				),
			] )
			->caller( __METHOD__ )
			->getSQL();

		$this->queryBuilder->where( "rev.rev_timestamp = ($minTimestampSinceSubquery)" );
	}

	/**
	 * Set SQL for 'goal' parameter.
	 *
	 * @param string $option 'pages' or 'categories'.
	 */
	private function _goal( string $option ): void {
		if ( $option !== 'categories' ) {
			// We only remove limit and offset if using 'categories' here.
			return;
		}

		$this->setLimit( null );
		$this->setOffset( null );
	}

	/**
	 * Set SQL for 'hiddencategories' parameter.
	 *
	 * @param mixed $option @phan-unused-param
	 */
	private function _hiddencategories( mixed $option ): never {
		// @TODO: Unfinished functionality! Never implemented by original author.
		throw new LogicException( 'hiddencategories has not been added to DynamicPageList4 yet.' );
	}

	/**
	 * Set SQL for 'imagecontainer' parameter.
	 */
	private function _imagecontainer( array $option ): void {
		$this->queryBuilder->table( 'imagelinks', 'ic' );
		$this->queryBuilder->select( [ 'sortkey' => 'ic.il_to' ] );

		$where = [];
		if ( !$this->parameters->getParameter( 'openreferences' ) ) {
			$where = [
				'page.page_namespace = ' . NS_FILE,
				'page.page_title = ic.il_to',
			];
		}

		$ors = [];
		foreach ( $option as $linkGroup ) {
			foreach ( $linkGroup as $link ) {
				$ors[] = $this->dbr->expr( 'ic.il_from', '=', $link->getArticleID() );
			}
		}

		$where[] = $this->dbr->makeList( $ors, IDatabase::LIST_OR );
		$this->queryBuilder->where( $where );
	}

	/**
	 * Set SQL for 'imageused' parameter.
	 */
	private function _imageused( array $option ): void {
		if ( $this->parameters->getParameter( 'distinct' ) === 'strict' ) {
			$this->queryBuilder->groupBy( 'page.page_title' );
		}

		$this->queryBuilder->table( 'imagelinks', 'il' );
		$this->queryBuilder->select( [ 'image_sel_title' => 'il.il_to' ] );

		$where = [ 'page.page_id = il.il_from' ];
		$ignoreCase = $this->parameters->getParameter( 'ignorecase' );

		$ors = [];
		foreach ( $option as $linkGroup ) {
			foreach ( $linkGroup as $link ) {
				$dbkey = $link->getDBkey();
				$fieldExpr = 'il.il_to';

				if ( $ignoreCase ) {
					$ors[] = $this->caseInsensitiveComparison( $fieldExpr, '=', $dbkey );
					continue;
				}

				$ors[] = $this->dbr->expr( $fieldExpr, '=', $dbkey );
			}
		}

		$where[] = $this->dbr->makeList( $ors, IDatabase::LIST_OR );
		$this->queryBuilder->where( $where );
	}

	/**
	 * Set SQL for 'lastmodifiedby' parameter.
	 */
	private function _lastmodifiedby( string $option ): void {
		$user = $this->userFactory->newFromName( $option );
		if ( $user->isHidden() ) {
			return;
		}

		$subquery = $this->queryBuilder->newSubquery()
			->select( 'rev_actor' )
			->from( 'revision' )
			->where( [
				'rev_page = page.page_id',
				'rev_deleted = 0',
			] )
			->orderBy( 'rev_timestamp', SelectQueryBuilder::SORT_DESC )
			->limit( 1 )
			->caller( __METHOD__ )
			->getSQL();

		$this->queryBuilder->where(
			$this->dbr->addQuotes( $user->getActorId() ) . " = ($subquery)"
		);
	}

	/**
	 * Set SQL for 'lastrevisionbefore' parameter.
	 */
	private function _lastrevisionbefore( string $option ): void {
		$this->queryBuilder->table( 'revision', 'rev' );
		$this->queryBuilder->select( [ 'rev.rev_id', 'rev.rev_timestamp' ] );

		// Tell the query optimizer not to look at rows that the following subquery will filter out anyway
		$this->queryBuilder->where( [
			'page.page_id = rev.rev_page',
			$this->dbr->expr( 'rev.rev_timestamp', '<', $this->convertTimestamp( $option ) ),
		] );

		$subquery = $this->queryBuilder->newSubquery()
			->select( 'MAX(rev_aux_bef.rev_timestamp)' )
			->from( 'revision', 'rev_aux_bef' )
			->where( [
				'rev_aux_bef.rev_page = page.page_id',
				$this->dbr->expr( 'rev_aux_bef.rev_timestamp', '<',
					$this->convertTimestamp( $option )
				),
			] )
			->caller( __METHOD__ )
			->getSQL();

		$this->queryBuilder->where( "rev.rev_timestamp = ($subquery)" );
	}

	/**
	 * Set SQL for 'linksfrom' parameter.
	 */
	private function _linksfrom( array $option ): void {
		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			$ors = [];
			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$ors[] = $this->dbr->expr( 'pl_from', '=', $link->getArticleID() );
				}
			}

			$this->queryBuilder->where( $this->dbr->makeList( $ors, IDatabase::LIST_OR ) );
			return;
		}

		$this->queryBuilder->tables( [
			'ltf' => 'linktarget',
			'pagesrc' => 'page',
			'plf' => 'pagelinks',
		] );

		if ( $this->isFormatUsed( '%PAGESEL%' ) ) {
			$this->queryBuilder->select( [
				'sel_title' => 'pagesrc.page_title',
				'sel_ns' => 'pagesrc.page_namespace',
			] );
		}

		$ors = [];
		foreach ( $option as $linkGroup ) {
			foreach ( $linkGroup as $link ) {
				$ors[] = $this->dbr->expr( 'plf.pl_from', '=', $link->getArticleID() );
			}
		}

		$this->queryBuilder->where( [
			'page.page_namespace = ltf.lt_namespace',
			'page.page_title = ltf.lt_title',
			'ltf.lt_id = plf.pl_target_id',
			'pagesrc.page_id = plf.pl_from',
			$this->dbr->makeList( $ors, IDatabase::LIST_OR ),
		] );
	}

	/**
	 * Set SQL for 'linksto' parameter.
	 */
	private function _linksto( array $option ): void {
		$this->queryBuilder->tables( [
			'lt' => 'linktarget',
			'pl' => 'pagelinks',
		] );

		if ( $this->isFormatUsed( '%PAGESEL%' ) ) {
			$this->queryBuilder->select( [
				'sel_title' => 'lt.lt_title',
				'sel_ns' => 'lt.lt_namespace',
			] );
		}

		$this->queryBuilder->where( 'pl.pl_target_id = lt.lt_id' );
		$ignoreCase = $this->parameters->getParameter( 'ignorecase' );

		foreach ( $option as $index => $linkGroup ) {
			$ors = [];
			foreach ( $linkGroup as $link ) {
				$title = $link->getDBkey();
				$operator = str_contains( $title, '%' ) ? IExpression::LIKE : '=';
				$fieldExpr = 'lt.lt_title';

				if ( $operator === IExpression::LIKE ) {
					if ( $ignoreCase ) {
						$title = mb_strtolower( $title, 'UTF-8' );
					}
					$title = new LikeValue( ...$this->splitLikePattern( $title ) );
				}

				if ( $ignoreCase ) {
					$comparison = $this->caseInsensitiveComparison( $fieldExpr, $operator, $title );
				} else {
					$comparison = $this->dbr->expr( $fieldExpr, $operator, $title );
				}

				$ors[] = $this->dbr->makeList( [
					$this->dbr->expr( 'lt.lt_namespace', '=', $link->getNamespace() ),
					$comparison,
				], IDatabase::LIST_AND );
			}

			if ( $index === 0 ) {
				$this->queryBuilder->where( [
					'page.page_id = pl.pl_from',
					$this->dbr->makeList( $ors, IDatabase::LIST_OR ),
				] );
				continue;
			}

			$subquery = $this->queryBuilder->newSubquery()
				->select( 'pl_from' )
				->from( 'pagelinks', 'pl' )
				->join( 'linktarget', 'lt', 'pl.pl_target_id = lt.lt_id' )
				->where( [
					'pl.pl_from = page.page_id',
					$this->dbr->makeList( $ors, IDatabase::LIST_OR ),
				] )
				->caller( __METHOD__ )
				->getSQL();

			$this->queryBuilder->where( "EXISTS($subquery)" );
		}
	}

	/**
	 * Set SQL for 'notlinksfrom' parameter.
	 */
	private function _notlinksfrom( array $option ): void {
		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			$ands = [];
			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$ands[] = $this->dbr->expr( 'pl_from', '!=', $link->getArticleID() );
				}
			}

			$this->queryBuilder->where( $this->dbr->makeList( $ands, IDatabase::LIST_AND ) );
			return;
		}

		$subquery = $this->queryBuilder->newSubquery()
			->select( $this->dbr->buildConcat( [ 'lt.lt_namespace', 'lt.lt_title' ] ) )
			->from( 'pagelinks', 'pl' )
			->join( 'linktarget', 'lt', 'pl.pl_target_id = lt.lt_id' );

		$ors = [];
		foreach ( $option as $linkGroup ) {
			foreach ( $linkGroup as $link ) {
				$ors[] = $this->dbr->expr( 'pl.pl_from', '=', $link->getArticleID() );
			}
		}

		if ( $ors ) {
			$subquery->where( $this->dbr->makeList( $ors, IDatabase::LIST_OR ) );
		}

		$subquery->caller( __METHOD__ );
		$this->queryBuilder->where(
			$this->dbr->buildConcat( [ 'page_namespace', 'page_title' ] ) .
			" NOT IN ({$subquery->getSQL()})"
		);
	}

	/**
	 * Set SQL for 'notlinksto' parameter.
	 */
	private function _notlinksto( array $option ): void {
		$ignoreCase = $this->parameters->getParameter( 'ignorecase' );
		$ors = [];

		foreach ( $option as $linkGroup ) {
			foreach ( $linkGroup as $link ) {
				$title = $link->getDBkey();
				$operator = str_contains( $title, '%' ) ? IExpression::LIKE : '=';
				$fieldExpr = 'lt.lt_title';

				if ( $operator === IExpression::LIKE ) {
					if ( $ignoreCase ) {
						$title = mb_strtolower( $title, 'UTF-8' );
					}
					$title = new LikeValue( ...$this->splitLikePattern( $title ) );
				}

				if ( $ignoreCase ) {
					$comparison = $this->caseInsensitiveComparison( $fieldExpr, $operator, $title );
				} else {
					$comparison = $this->dbr->expr( $fieldExpr, $operator, $title );
				}

				$ors[] = $this->dbr->makeList( [
					$this->dbr->expr( 'lt.lt_namespace', '=', $link->getNamespace() ),
					$comparison,
				], IDatabase::LIST_AND );
			}
		}

		$subquery = $this->queryBuilder->newSubquery()
			->select( 'pl.pl_from' )
			->from( 'pagelinks', 'pl' )
			->join( 'linktarget', 'lt', 'pl.pl_target_id = lt.lt_id' )
			->where( $this->dbr->makeList( $ors, IDatabase::LIST_OR ) )
			->caller( __METHOD__ )
			->getSQL();

		$this->queryBuilder->where( "page.page_id NOT IN ($subquery)" );
	}

	/**
	 * Set SQL for 'linkstoexternal' parameter.
	 */
	private function _linkstoexternal( array $option ): void {
		if ( $this->parameters->getParameter( 'distinct' ) === 'strict' ) {
			$this->queryBuilder->groupBy( 'page.page_title' );
		}

		$this->queryBuilder->table( 'externallinks', 'el' );
		// We use random bytes to avoid any possible conflicts where
		// a page actually uses this placeholder.
		$likePlaceholder = 'dpl4_like_' . bin2hex( random_bytes( 4 ) ) . '_x';

		$groups = [];

		foreach ( $option as $linkGroup ) {
			$group = [];

			foreach ( $linkGroup as $link ) {
				// Encode real percent signs used for LIKE matches to avoid
				// LinkFilter encoding it as %25.
				$link = str_replace( '%', $likePlaceholder, $link );
				if (
				!str_contains( $link, '://' ) &&
				!str_starts_with( $link, 'mailto:' ) &&
				!str_starts_with( $link, '//' )
				) {
					$link = "//$link";
				}

				$indexes = LinkFilter::makeIndexes( $link );
				if ( isset( $indexes[0] ) && is_array( $indexes[0] ) ) {
					[ $domain, $path ] = $indexes[0];

					$conditions = [];

					if ( $domain !== null ) {
						$conditions[] = [ 'el_to_domain_index', str_replace( $likePlaceholder, '%', $domain ) ];
					}

					if ( $path !== null ) {
						$conditions[] = [ 'el_to_path', str_replace( $likePlaceholder, '%', $path ) ];
					}

					if ( $conditions !== [] ) {
						$group[] = $conditions;
					}
				}
			}

			if ( $group !== [] ) {
				$groups[] = $group;
			}
		}

		foreach ( $groups as $index => $group ) {
			$orConditions = [];

			foreach ( $group as $conditions ) {
				$ands = [];

				foreach ( $conditions as [ $field, $pattern ] ) {
					// Ensure field is selected
					$this->queryBuilder->select( [ $field => "el.$field" ] );

					$ands[] = $this->dbr->expr(
					"el.$field",
					IExpression::LIKE,
					new LikeValue( ...$this->splitLikePattern( $pattern ) )
					);
				}

				$orConditions[] = $this->dbr->makeList( $ands, IDatabase::LIST_AND );
			}

			$where = [
			'el.el_from = page.page_id',
			$this->dbr->makeList( $orConditions, IDatabase::LIST_OR )
			];

			if ( $index === 0 ) {
				$this->queryBuilder->where( $where );
				continue;
			}

			$subquery = $this->queryBuilder->newSubquery()
			->select( 'el_from' )
			->from( 'externallinks', 'el' )
			->where( $where )
			->caller( __METHOD__ )
			->getSQL();

			$this->queryBuilder->where( "EXISTS($subquery)" );
		}
	}

	/**
	 * Set SQL for 'maxrevisions' parameter.
	 */
	private function _maxrevisions( int $option ): void {
		$subquery = $this->queryBuilder->newSubquery()
			->select( 'COUNT(rev_aux3.rev_page)' )
			->from( 'revision', 'rev_aux3' )
			->where( 'rev_aux3.rev_page = page.page_id' )
			->caller( __METHOD__ )
			->getSQL();

		$this->queryBuilder->where( "($subquery) <= $option" );
	}

	/**
	 * Set SQL for 'minrevisions' parameter.
	 */
	private function _minrevisions( int $option ): void {
		$subquery = $this->queryBuilder->newSubquery()
			->select( 'COUNT(rev_aux2.rev_page)' )
			->from( 'revision', 'rev_aux2' )
			->where( 'rev_aux2.rev_page = page.page_id' )
			->caller( __METHOD__ )
			->getSQL();

		$this->queryBuilder->where( "($subquery) >= $option" );
	}

	/**
	 * Set SQL for 'modifiedby' parameter.
	 */
	private function _modifiedby( string $option ): void {
		$user = $this->userFactory->newFromName( $option );
		if ( $user->isHidden() ) {
			return;
		}

		$this->queryBuilder->table( 'revision', 'change_rev' );
		$this->queryBuilder->where( [
			$this->dbr->expr( 'change_rev.rev_actor', '=', $user->getActorId() ),
			'change_rev.rev_deleted = 0',
			'change_rev.rev_page = page.page_id',
		] );
	}

	/**
	 * Set SQL for 'namespace' parameter.
	 */
	private function _namespace( array $option ): void {
		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			$this->queryBuilder->where( [ 'lt.lt_namespace' => $option ] );
			return;
		}

		$this->queryBuilder->where( [ 'page.page_namespace' => $option ] );
	}

	/**
	 * Set SQL for 'notcreatedby' parameter.
	 */
	private function _notcreatedby( string $option ): void {
		$user = $this->userFactory->newFromName( $option );
		if ( $user->isHidden() ) {
			return;
		}

		$this->queryBuilder->table( 'revision', 'no_creation_rev' );
		$this->queryBuilder->where( [
			$this->dbr->expr( 'no_creation_rev.rev_actor', '!=', $user->getActorId() ),
			'no_creation_rev.rev_deleted = 0',
			'no_creation_rev.rev_page = page.page_id',
			'no_creation_rev.rev_parent_id = 0',
		] );
	}

	/**
	 * Set SQL for 'notlastmodifiedby' parameter.
	 */
	private function _notlastmodifiedby( string $option ): void {
		$user = $this->userFactory->newFromName( $option );
		if ( $user->isHidden() ) {
			return;
		}

		$subquery = $this->queryBuilder->newSubquery()
			->select( 'rev_actor' )
			->from( 'revision' )
			->where( [
				'revision.rev_page = page.page_id',
				'revision.rev_deleted = 0',
			] )
			->orderBy( 'revision.rev_timestamp', SelectQueryBuilder::SORT_DESC )
			->limit( 1 )
			->caller( __METHOD__ )
			->getSQL();

		$this->queryBuilder->where(
			$this->dbr->addQuotes( $user->getActorId() ) . " != ($subquery)"
		);
	}

	/**
	 * Set SQL for 'notmodifiedby' parameter.
	 */
	private function _notmodifiedby( string $option ): void {
		$user = $this->userFactory->newFromName( $option );
		if ( $user->isHidden() ) {
			return;
		}

		$actorID = $this->dbr->addQuotes( $user->getActorId() );
		$subquery = $this->queryBuilder->newSubquery()
			->select( '1' )
			->from( 'revision' )
			->where( [
				'revision.rev_page = page.page_id',
				"revision.rev_actor = $actorID",
				'revision.rev_deleted = 0',
			] )
			->limit( 1 )
			->caller( __METHOD__ )
			->getSQL();

		$this->queryBuilder->where( "NOT EXISTS ($subquery)" );
	}

	/**
	 * Set SQL for 'notnamespace' parameter.
	 */
	private function _notnamespace( array $option ): void {
		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			$this->queryBuilder->andWhere( $this->dbr->expr( 'lt.lt_namespace', '!=', $option ) );
			return;
		}

		$this->queryBuilder->andWhere( $this->dbr->expr( 'page.page_namespace', '!=', $option ) );
	}

	/**
	 * Set SQL for 'count' parameter.
	 */
	private function _count( int $option ): void {
		$this->setLimit( $option );
	}

	/**
	 * Set SQL for 'offset' parameter.
	 */
	private function _offset( int $option ): void {
		$this->setOffset( $option );
	}

	/**
	 * Set SQL for 'order' parameter.
	 */
	private function _order( string $option ): void {
		$orderMethod = $this->parameters->getParameter( 'ordermethod' );
		if ( !$orderMethod || $orderMethod[0] === 'none' ) {
			return;
		}

		if ( $option === 'descending' || $option === 'desc' ) {
			$this->setOrderDir( SelectQueryBuilder::SORT_DESC );
			return;
		}

		$this->setOrderDir( SelectQueryBuilder::SORT_ASC );
	}

	/**
	 * Set SQL for 'ordercollation' parameter.
	 */
	private function _ordercollation( string $option ): void {
		$option = mb_strtolower( $option );
		$dbType = $this->dbr->getType();

		if ( $dbType === 'mysql' ) {
			$res = $this->dbr->newSelectQueryBuilder()
				->select( [
					'CHARACTER_SET_NAME',
					'DEFAULT_COLLATE_NAME',
				] )
				->from( 'information_schema.CHARACTER_SETS' )
				->caller( __METHOD__ )
				->fetchResultSet();

			foreach ( $res as $row ) {
				if ( $option === mb_strtolower( $row->DEFAULT_COLLATE_NAME ) ||
					$option === mb_strtolower( $row->CHARACTER_SET_NAME )
				) {
					$this->charset = $row->CHARACTER_SET_NAME;
					$this->collation = $row->DEFAULT_COLLATE_NAME;
					return;
				}
			}

			throw new LogicException( "No default order collation found matching $option." );
		}

		if ( $dbType === 'postgres' ) {
			// Fetch the current DB encoding using pg_encoding_to_char()
			$collation = $this->dbr->newSelectQueryBuilder()
				->select( 'pg_encoding_to_char(encoding)' )
				->from( 'pg_database' )
				->where( [ 'datname' => $this->dbr->getDBname() ] )
				->caller( __METHOD__ )
				->fetchField();

			if ( $collation !== false && $option === mb_strtolower( $collation ) ) {
				$this->collation = $collation;
				return;
			}

			throw new LogicException( "No default order collation found matching $option." );
		}

		if ( $dbType === 'sqlite' ) {
			// SQLite built-in collations are BINARY, NOCASE, RTRIM
			$validCollations = [ 'binary', 'nocase', 'rtrim' ];
			if ( in_array( $option, $validCollations, true ) ) {
				$this->collation = mb_strtoupper( $option );
				return;
			}

			throw new LogicException( "No default order collation found matching $option." );
		}

		// Not supported on SQLite or mystery engines
		throw new LogicException( 'Order collation is not supported on the database type you are using.' );
	}

	/**
	 * Set SQL for 'ordermethod' parameter.
	 */
	private function _ordermethod( array $option ): void {
		if ( $this->parameters->getParameter( 'goal' ) === 'categories' ) {
			// No order methods for returning categories.
			return;
		}

		// Don't do this unless we are actually using it.
		$namespaceIdToText = '';
		if ( in_array( 'sortkey', $option, true ) || in_array( 'title', $option, true ) ) {
			$services = MediaWikiServices::getInstance();
			$namespaces = $services->getContentLanguage()->getNamespaces();

			$namespaces = array_slice( $namespaces, 3, null, true );
			$namespaceIdToText = 'CASE page.page_namespace';

			foreach ( $namespaces as $id => $name ) {
				$namespaceIdToText .= ' WHEN ' . (int)$id . ' THEN ' . $this->dbr->addQuotes( $name . ':' );
			}

			$namespaceIdToText .= ' END';
		}

		foreach ( $option as $orderMethod ) {
			switch ( $orderMethod ) {
				case 'category':
					$this->addOrderBy( 'cl_head.cl_to' );
					$this->queryBuilder->select( 'cl_head.cl_to' );

					$catHeadings = $this->parameters->getParameter( 'catheadings' ) ?? [];
					$catNotHeadings = $this->parameters->getParameter( 'catnotheadings' ) ?? [];

					if ( in_array( '', $catHeadings, true ) || in_array( '', $catNotHeadings, true ) ) {
						$clTableName = 'dpl_clview';
						$clTableAlias = $clTableName;
					} else {
						$clTableName = 'categorylinks';
						$clTableAlias = 'cl_head';
					}

					$this->queryBuilder->table( $clTableName, $clTableAlias );
					$this->queryBuilder->leftJoin( $clTableName, $clTableAlias,
						'page_id = cl_head.cl_from'
					);

					if ( $catHeadings !== [] ) {
						if ( !empty( $catHeadings['AND'] ) ) {
							$this->queryBuilder->where(
								$this->dbr->makeList( array_map(
									fn ( string $cat ): Expression =>
										$this->dbr->expr( 'cl_head.cl_to', '=', $cat ),
									$catHeadings['AND']
								), IDatabase::LIST_AND )
							);
						}

						if ( !empty( $catHeadings['OR'] ) ) {
							$this->queryBuilder->where(
								$this->dbr->makeList( array_map(
									fn ( string $cat ): Expression =>
										$this->dbr->expr( 'cl_head.cl_to', '=', $cat ),
									$catHeadings['OR']
								), IDatabase::LIST_OR )
							);
						}
					}

					if ( $catNotHeadings !== [] ) {
						if ( !empty( $catNotHeadings['AND'] ) ) {
							$this->queryBuilder->andWhere(
								$this->dbr->makeList( array_map(
									fn ( string $cat ): Expression =>
										$this->dbr->expr( 'cl_head.cl_to', '!=', $cat ),
									$catNotHeadings['AND']
								), IDatabase::LIST_AND )
							);
						}

						if ( !empty( $catNotHeadings['OR'] ) ) {
							$this->queryBuilder->andWhere(
								$this->dbr->makeList( array_map(
									fn ( string $cat ): Expression =>
										$this->dbr->expr( 'cl_head.cl_to', '!=', $cat ),
									$catNotHeadings['OR']
								), IDatabase::LIST_OR )
							);
						}
					}
					break;
				case 'categoryadd':
					// @TODO: See TODO in __addfirstcategorydate().
					$this->addOrderBy( 'cl1.cl_timestamp' );
					break;
				case 'counter':
					if ( !ExtensionRegistry::getInstance()->isLoaded( 'HitCounters' ) ) {
						break;
					}

					// If the "addpagecounter" parameter was not used the table and join need to be added now.
					if ( !isset( $this->queryBuilder->getQueryInfo()['tables']['hit_counter'] ) ) {
						$this->queryBuilder->table( 'hit_counter' );
						if ( !isset( $this->queryBuilder->getQueryInfo()['join_conds']['hit_counter'] ) ) {
							$this->queryBuilder->leftJoin( 'hit_counter', null,
								'hit_counter.page_id = page.page_id'
							);
						}
					}

					$this->addOrderBy( 'hit_counter.page_counter' );
					break;
				case 'displaytitle':
					$this->addOrderBy( 'COALESCE(displaytitle, page.page_title)' );
					if ( !isset( $this->queryBuilder->getQueryInfo()['fields']['displaytitle'] ) ) {
						$this->queryBuilder->table( 'page_props', 'pp' );
						$joinCondition = $this->dbr->makeList( [
							'pp.pp_page = page.page_id',
							$this->dbr->expr( 'pp.pp_propname', '=', 'displaytitle' ),
						], IDatabase::LIST_AND );

						$this->queryBuilder->leftJoin( 'page_props', 'pp', $joinCondition );
						$this->queryBuilder->select( [ 'displaytitle' => 'pp.pp_value' ] );
					}
					break;
				case 'firstedit':
					$this->addOrderBy( 'rev.rev_timestamp' );
					$this->queryBuilder->table( 'revision', 'rev' );
					$this->queryBuilder->select( 'rev.rev_timestamp' );

					if ( !$this->revisionAuxWhereAdded ) {
						$subquery = $this->queryBuilder->newSubquery()
							->select( 'MIN(rev_aux.rev_timestamp)' )
							->from( 'revision', 'rev_aux' )
							->where( 'rev_aux.rev_page = page.page_id' )
							->caller( __METHOD__ )
							->getSQL();

						$this->queryBuilder->where( [
							'page.page_id = rev.rev_page',
							"rev.rev_timestamp = ($subquery)",
						] );
					}

					$this->revisionAuxWhereAdded = true;
					break;
				case 'lastedit':
					if ( Utils::isLikeIntersection() ) {
						$this->addOrderBy( 'page_touched' );
						$this->queryBuilder->select( [ 'page_touched' => 'page.page_touched' ] );
						break;
					}

					$this->addOrderBy( 'rev.rev_timestamp' );
					$this->queryBuilder->table( 'revision', 'rev' );
					$this->queryBuilder->select( 'rev.rev_timestamp' );

					if ( !$this->revisionAuxWhereAdded ) {
						$this->queryBuilder->where( 'page.page_id = rev.rev_page' );

						$subqueryBuilder = $this->queryBuilder->newSubquery()
							->select( 'MAX(rev_aux.rev_timestamp)' )
							->from( 'revision', 'rev_aux' )
							->where( 'rev_aux.rev_page = page.page_id' );

						if ( $this->parameters->getParameter( 'minoredits' ) === 'exclude' ) {
							$subqueryBuilder->where( [ 'rev_aux.rev_minor_edit' => 0 ] );
						}

						$subquery = $subqueryBuilder
							->caller( __METHOD__ )
							->getSQL();

						$this->queryBuilder->where( "rev.rev_timestamp = ($subquery)" );
					}

					$this->revisionAuxWhereAdded = true;
					break;
				case 'pagesel':
					$this->addOrderBy( 'sortkey' );
					$alias = match ( true ) {
						count( $this->parameters->getParameter( 'linksfrom' ) ?? [] ) > 0 => 'ltf',
						count( $this->parameters->getParameter( 'linksto' ) ?? [] ) > 0 => 'lt',
						count( $this->parameters->getParameter( 'usedby' ) ?? [] ) > 0 => 'lt_usedby',
						count( $this->parameters->getParameter( 'uses' ) ?? [] ) > 0 => 'lt_uses',
						default => throw new LogicException(
							'The ordermethod \'pagesel\' is only supported when using at least one of the ' .
							'following parameters: linksfrom, linksto, usedby, or uses.'
						),
					};

					$this->queryBuilder->select( [
						'sortkey' => $this->applyCollation( $this->dbr->buildConcat(
							[ "$alias.lt_namespace", "$alias.lt_title" ]
						) ),
					] );
					break;
				case 'pagetouched':
					$this->addOrderBy( 'page_touched' );
					$this->queryBuilder->select( [ 'page_touched' => 'page.page_touched' ] );
					break;
				case 'size':
					$this->addOrderBy( 'page_len' );
					break;
				case 'sortkey':
					$this->addOrderBy( 'sortkey' );

					// If cl_sortkey is null (uncategorized page), generate a sortkey in
					// the usual way (full page name, underscores replaced with spaces).
					// UTF-8 created problems with non-utf-8 MySQL databases
					$replaceConcat = $this->dbr->strreplace(
						$this->dbr->buildConcat( [ $namespaceIdToText, 'page.page_title' ] ),
						$this->dbr->addQuotes( '_' ),
						$this->dbr->addQuotes( ' ' )
					);

					$category = $this->parameters->getParameter( 'category' ) ?? [];
					$notCategory = $this->parameters->getParameter( 'notcategory' ) ?? [];
					if ( $category !== [] || $notCategory !== [] ) {
						if ( in_array( 'category', $this->parameters->getParameter( 'ordermethod' ), true ) ) {
							$this->queryBuilder->select( [
								'sortkey' => $this->applyCollation( "COALESCE(cl_head.cl_sortkey, $replaceConcat)" ),
							] );
						} else {
							// This runs on the assumption that at least one category parameter
							// was used and that numbering starts at 1.
							$this->queryBuilder->select( [
								'sortkey' => $this->applyCollation( "COALESCE(cl1.cl_sortkey, $replaceConcat)" ),
							] );
						}
					} else {
						$this->queryBuilder->select( [
							'sortkey' => $this->applyCollation( $replaceConcat ),
						] );
					}
					break;
				case 'titlewithoutnamespace':
					if ( $this->parameters->getParameter( 'openreferences' ) ) {
						$this->addOrderBy( 'lt_title' );
					} else {
						$this->addOrderBy( 'page_title' );
					}

					$this->queryBuilder->select( [
						'sortkey' => $this->applyCollation( 'page.page_title' ),
					] );
					break;
				case 'title':
					$this->addOrderBy( 'sortkey' );
					$namespaceColumn = $this->parameters->getParameter( 'openreferences' ) ?
						'lt_namespace' : 'page.page_namespace';

					$titleColumn = $this->parameters->getParameter( 'openreferences' ) ?
						'lt_title' : 'page.page_title';

					// Generate sortkey like for category links.
					// UTF-8 created problems with non-utf-8 MySQL databases.
					$this->queryBuilder->select( [
						// Select 'sortkey' with applied collation for sorting
						'sortkey' => $this->applyCollation(
							// Replace underscores with spaces in the concatenated string
							$this->dbr->strreplace(
								// Concatenate namespace prefix (if applicable) with the title
								$this->dbr->buildConcat( [
									// If the namespace is the main namespace, use an empty string
									// Otherwise, concatenate namespace text and a colon
									$this->dbr->conditional(
										[ $namespaceColumn => NS_MAIN ], $this->dbr->addQuotes( '' ),
										$this->dbr->buildConcat( [ $namespaceIdToText, $this->dbr->addQuotes( ':' ) ] )
									),
									// Append the actual title
									$titleColumn
								] ),
								$this->dbr->addQuotes( '_' ),
								$this->dbr->addQuotes( ' ' )
							)
						)
					] );
					break;
				case 'user':
					$this->addOrderBy( 'rev.rev_actor' );
					$this->queryBuilder->table( 'revision', 'rev' );
					$this->adduser( tableAlias: 'rev' );
					break;
				case 'none':
					break;
			}
		}
	}

	/**
	 * Set SQL for 'redirects' parameter.
	 */
	private function _redirects( string $option ): void {
		if ( $option === 'include' || $this->parameters->getParameter( 'openreferences' ) ) {
			return;
		}

		$this->queryBuilder->where( match ( $option ) {
			'only' => [ 'page.page_is_redirect' => 1 ],
			'exclude' => [ 'page.page_is_redirect' => 0 ],
		} );
	}

	/**
	 * Set SQL for 'includesubpages' parameter.
	 */
	private function _includesubpages( bool $option ): void {
		if ( $option ) {
			// If we are including subpages we don't need to do anything here.
			return;
		}

		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			$this->queryBuilder->andWhere( $this->dbr->expr( 'lt_title', IExpression::NOT_LIKE,
				new LikeValue( $this->dbr->anyString(), '/', $this->dbr->anyString() )
			) );
			return;
		}

		$this->queryBuilder->andWhere( $this->dbr->expr( 'page.page_title', IExpression::NOT_LIKE,
			new LikeValue( $this->dbr->anyString(), '/', $this->dbr->anyString() )
		) );
	}

	/**
	 * Set SQL for 'stablepages' parameter.
	 */
	private function _stablepages( string $option ): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'FlaggedRevs' ) ) {
			return;
		}

		// Do not add this again if 'qualitypages' has already added it.
		if ( !$this->parametersProcessed['qualitypages'] ) {
			$this->queryBuilder->leftJoin( 'flaggedpages', null, 'page_id = fp_page_id' );
		}

		$this->queryBuilder->where( match ( $option ) {
			'only' => $this->dbr->expr( 'fp_stable', '!=', null ),
			'exclude' => [ 'fp_stable' => null ],
		} );
	}

	/**
	 * Set SQL for 'qualitypages' parameter.
	 */
	private function _qualitypages( string $option ): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'FlaggedRevs' ) ) {
			return;
		}

		// Do not add this again if 'stablepages' has already added it.
		if ( !$this->parametersProcessed['stablepages'] ) {
			$this->queryBuilder->leftJoin( 'flaggedpages', null, 'page_id = fp_page_id' );
		}

		$this->queryBuilder->where( match ( $option ) {
			'only' => $this->dbr->expr( 'fp_quality', '>=', 1 ),
			'exclude' => [ 'fp_quality' => 0 ],
		} );
	}

	/**
	 * Set SQL for 'title' parameter.
	 */
	private function _title( array $option ): void {
		$ors = [];
		$ignoreCase = $this->parameters->getParameter( 'ignorecase' );
		$openReferences = $this->parameters->getParameter( 'openreferences' );

		foreach ( $option as $comparisonType => $titles ) {
			foreach ( $titles as $title ) {
				$field = $openReferences ? 'lt_title' : 'page.page_title';
				if ( $comparisonType === IExpression::LIKE ) {
					if ( $ignoreCase ) {
						$title = mb_strtolower( $title, 'UTF-8' );
					}
					$title = new LikeValue( ...$this->splitLikePattern( $title ) );
				}

				if ( $ignoreCase ) {
					$ors[] = $this->caseInsensitiveComparison( $field, $comparisonType, $title );
					continue;
				}

				if ( $comparisonType === 'REGEXP' ) {
					$ors[] = $this->buildRegexpExpression( $field, $title );
					continue;
				}

				$ors[] = $this->dbr->expr( $field, $comparisonType, $title );
			}
		}

		$this->queryBuilder->where( $this->dbr->makeList( $ors, IDatabase::LIST_OR ) );

		if ( $this->isFormatUsed( '%DISPLAYTITLE%' ) ) {
			$this->queryBuilder->table( 'page_props', 'pp' );
			$joinCondition = $this->dbr->makeList( [
				'pp.pp_page = page.page_id',
				$this->dbr->expr( 'pp.pp_propname', '=', 'displaytitle' ),
			], IDatabase::LIST_AND );

			$this->queryBuilder->leftJoin( 'page_props', 'pp', $joinCondition )
				->select( [ 'displaytitle' => 'pp.pp_value' ] );
		}
	}

	/**
	 * Set SQL for 'nottitle' parameter.
	 */
	private function _nottitle( array $option ): void {
		$ors = [];
		$ignoreCase = $this->parameters->getParameter( 'ignorecase' );
		$openReferences = $this->parameters->getParameter( 'openreferences' );

		foreach ( $option as $comparisonType => $titles ) {
			foreach ( $titles as $title ) {
				$field = $openReferences ? 'lt_title' : 'page.page_title';
				if ( $comparisonType === IExpression::LIKE ) {
					if ( $ignoreCase ) {
						$title = mb_strtolower( $title, 'UTF-8' );
					}
					$title = new LikeValue( ...$this->splitLikePattern( $title ) );
				}

				if ( $ignoreCase ) {
					$ors[] = $this->caseInsensitiveComparison( $field, $comparisonType, $title );
					continue;
				}

				if ( $comparisonType === 'REGEXP' ) {
					$ors[] = $this->buildRegexpExpression( $field, $title );
					continue;
				}

				$ors[] = $this->dbr->expr( $field, $comparisonType, $title );
			}
		}

		$this->queryBuilder->where( 'NOT ' . $this->dbr->makeList( $ors, IDatabase::LIST_OR ) );
	}

	/**
	 * Set SQL for 'titlegt' parameter.
	 */
	private function _titlegt( string $option ): void {
		$openReferences = $this->parameters->getParameter( 'openreferences' );
		$field = $openReferences ? 'lt_title' : 'page.page_title';

		if ( str_starts_with( $option, '=_' ) ) {
			$option = substr( $option, 2 );
			$this->queryBuilder->where( $this->dbr->expr( $field, '>=', $option ) );
			return;
		}

		if ( $option === '' ) {
			$this->queryBuilder->where( $this->dbr->expr( $field, IExpression::LIKE,
				new LikeValue( $this->dbr->anyString() )
			) );
			return;
		}

		$this->queryBuilder->where( $this->dbr->expr( $field, '>', $option ) );
	}

	/**
	 * Set SQL for 'titlelt' parameter.
	 */
	private function _titlelt( string $option ): void {
		$openReferences = $this->parameters->getParameter( 'openreferences' );
		$field = $openReferences ? 'lt_title' : 'page.page_title';

		if ( str_starts_with( $option, '=_' ) ) {
			$option = substr( $option, 2 );
			$this->queryBuilder->where( $this->dbr->expr( $field, '<=', $option ) );
			return;
		}

		if ( $option === '' ) {
			$this->queryBuilder->where( $this->dbr->expr( $field, IExpression::LIKE,
				new LikeValue( $this->dbr->anyString() )
			) );
			return;
		}

		$this->queryBuilder->where( $this->dbr->expr( $field, '<', $option ) );
	}

	/**
	 * Set SQL for 'usedby' parameter.
	 */
	private function _usedby( array $option ): void {
		if ( $this->parameters->getParameter( 'openreferences' ) ) {
			$ors = [];
			foreach ( $option as $linkGroup ) {
				foreach ( $linkGroup as $link ) {
					$ors[] = $this->dbr->expr( 'tpl_from', '=', $link->getArticleID() );
				}
			}

			$this->queryBuilder->where( $this->dbr->makeList( $ors, IDatabase::LIST_OR ) );
			return;
		}

		$linksMigration = MediaWikiServices::getInstance()->getLinksMigration();
		[ $nsField, $titleField ] = $linksMigration->getTitleFields( 'templatelinks' );

		$this->queryBuilder->select( [
			'tpl_sel_title' => 'page.page_title',
			'tpl_sel_ns' => 'page.page_namespace',
		] );

		$this->queryBuilder->table( 'linktarget', 'lt_usedby' );
		$this->queryBuilder->join( 'linktarget', 'lt_usedby', [
			"page_title = lt_usedby.$titleField",
			"page_namespace = lt_usedby.$nsField",
		] );

		$this->queryBuilder->join( 'templatelinks', 'tpl', 'lt_usedby.lt_id = tl_target_id' );

		$ors = [];
		foreach ( $option as $linkGroup ) {
			foreach ( $linkGroup as $link ) {
				$ors[] = $this->dbr->expr( 'tpl.tl_from', '=', $link->getArticleID() );
			}
		}

		$this->queryBuilder->where( $this->dbr->makeList( $ors, IDatabase::LIST_OR ) );
	}

	/**
	 * Set SQL for 'uses' parameter.
	 */
	private function _uses( array $option ): void {
		$this->queryBuilder->tables( [
			'lt_uses' => 'linktarget',
			'tl' => 'templatelinks',
		] );

		$linksMigration = MediaWikiServices::getInstance()->getLinksMigration();
		[ $nsField, $titleField ] = $linksMigration->getTitleFields( 'templatelinks' );

		$ignoreCase = $this->parameters->getParameter( 'ignorecase' );
		$ors = [];

		foreach ( $option as $linkGroup ) {
			foreach ( $linkGroup as $link ) {
				$dbkey = $link->getDBkey();
				$fieldExpr = "lt_uses.$titleField";

				if ( $ignoreCase ) {
					$comparison = $this->caseInsensitiveComparison( $fieldExpr, '=', $dbkey );
				} else {
					$comparison = $this->dbr->expr( $fieldExpr, '=', $dbkey );
				}

				$ors[] = $this->dbr->makeList( [
					$this->dbr->expr( "lt_uses.$nsField", '=', $link->getNamespace() ),
					$comparison,
				], IDatabase::LIST_AND );
			}
		}

		$this->queryBuilder->where( [
			'page.page_id = tl.tl_from',
			'lt_uses.lt_id = tl.tl_target_id',
			$this->dbr->makeList( $ors, IDatabase::LIST_OR ),
		] );
	}

	/**
	 * Set SQL for 'notuses' parameter.
	 */
	private function _notuses( array $option ): void {
		$linksMigration = MediaWikiServices::getInstance()->getLinksMigration();
		[ $nsField, $titleField ] = $linksMigration->getTitleFields( 'templatelinks' );

		$subquery = $this->queryBuilder->newSubquery()
			->select( 'templatelinks.tl_from' )
			->from( 'templatelinks' )
			->join( 'linktarget', null, 'linktarget.lt_id = templatelinks.tl_target_id' );

		$ignoreCase = $this->parameters->getParameter( 'ignorecase' );
		$ors = [];

		foreach ( $option as $linkGroup ) {
			foreach ( $linkGroup as $link ) {
				$dbkey = $link->getDBkey();
				$fieldExpr = "linktarget.$titleField";

				if ( $ignoreCase ) {
					$comparison = $this->caseInsensitiveComparison( $fieldExpr, '=', $dbkey );
				} else {
					$comparison = $this->dbr->expr( $fieldExpr, '=', $dbkey );
				}

				$ors[] = $this->dbr->makeList( [
					$this->dbr->expr( "linktarget.$nsField", '=', $link->getNamespace() ),
					$comparison,
				], IDatabase::LIST_AND );
			}
		}

		$subquery->where( $this->dbr->makeList( $ors, IDatabase::LIST_OR ) );
		$subquery->caller( __METHOD__ );

		$this->queryBuilder->where( "page.page_id NOT IN ({$subquery->getSQL()})" );
	}

	// @phpcs:enable

	private function isFormatUsed( string $search ): bool {
		$listSeparators = $this->parameters->getParameter( 'listseparators' );
		if ( !$listSeparators ) {
			return false;
		}

		$format = implode( ',', $listSeparators );
		return str_contains( $format, $search );
	}
}
