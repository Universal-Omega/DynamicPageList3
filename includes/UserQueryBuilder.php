<?php

namespace DPL;

use ActorMigration;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use Wikimedia\Rdbms\IDatabase;

class UserQueryBuilder {
	/** @var IDatabase */
	private $dbr;

	/** @var ActorMigration */
	private $actorMigration;

	/** @var UserIdentity|array */
	private $modifiedByConstraints = [];

	/** @var UserIdentity|array */
	private $notModifiedByConstraints = [];

	/** @var UserIdentity|array */
	private $notCreatedByConstraints = [];

	/** @var UserIdentity|array */
	private $notLastModifiedByConstraints = [];

	/** @var UserIdentity|null */
	private $createdByConstraint;

	/** @var UserIdentity|null */
	private $lastModifiedByConstraint;

	public function __construct( IDatabase $dbr, ActorMigration $actorMigration ) {
		$this->dbr = $dbr;
		$this->actorMigration = $actorMigration;
	}

	public function addModifiedByConstraint( string $userName ): void {
		$this->modifiedByConstraints[] = $this->getUserForQuery( $userName );
	}

	public function addNotModifiedByConstraint( string $userName ): void {
		$this->notModifiedByConstraints[] = $this->getUserForQuery( $userName );
	}

	public function addCreatedByConstraint( string $userName ): void {
		$this->createdByConstraint = $this->getUserForQuery( $userName );
	}

	public function addNotCreatedByConstraint( string $userName ): void {
		$this->notCreatedByConstraints[] = $this->getUserForQuery( $userName );
	}

	public function addLastModifiedByConstraint( string $userName ): void {
		$this->lastModifiedByConstraint = $this->getUserForQuery( $userName );
	}

	public function addNotLastModifiedByConstraint( string $userName ): void {
		$this->notLastModifiedByConstraints[] = $this->getUserForQuery( $userName );
	}

	/**
	 * Combine the constraints into a single condition suitable for inclusion in a WHERE clause.
	 * @return string
	 */
	public function getWhere(): string {
		$conds = [];

		$modifiedByQuery = $this->getSubqueryForConstraint( $this->modifiedByConstraints );
		if ( $modifiedByQuery ) {
			$conds[] = "page_id IN $modifiedByQuery";
		}

		$notModifiedByQuery = $this->getSubqueryForConstraint( $this->notModifiedByConstraints );
		if ( $notModifiedByQuery ) {
			$conds[] = "NOT EXISTS $notModifiedByQuery";
		}

		$lastModifiedByQuery = $this->getSubqueryForConstraint(
			[ $this->lastModifiedByConstraint ],
			$this->notLastModifiedByConstraints,
			'rev_id'
		);
		if ( $lastModifiedByQuery ) {
			$conds[] = "page_latest IN $lastModifiedByQuery";
		}

		$createdByQuery = $this->getSubqueryForConstraint(
			[ $this->createdByConstraint ],
			$this->notCreatedByConstraints,
			'rev_page',
			[ 'rev_parent_id' => 0 ]
		);
		if ( $createdByQuery ) {
			$conds[] = "page_id IN $createdByQuery";
		}

		return $this->dbr->makeList( $conds, IDatabase::LIST_AND );
	}

	private function getUserForQuery( string $userName ): ?UserIdentity {
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();

		return $userFactory->newFromName( $userName, UserFactory::RIGOR_NONE ) ?: null;
	}

	/**
	 * Construct a subquery for filtering revisions based on given user-specific criteria.
	 * @param UserIdentity[] $constraints -users to include in the result set
	 * @param UserIdentity[] $notConstraints - users to exclude from the result set
	 * @param string $selectField - field name to select from the revision table
	 * @param array $conds - additional query conditions
	 * @return string|null - the subquery, or null if no constraints were given
	 */
	private function getSubqueryForConstraint(
		array $constraints,
		array $notConstraints = [],
		string $selectField = 'rev_page',
		array $conds = []
	): ?string {
		$constraints = array_filter( $constraints );
		$notConstraints = array_filter( $notConstraints );
		if ( !$constraints && !$notConstraints ) {
			return null;
		}

		$constraintActorQuery = $this->actorMigration->getWhere(
			$this->dbr,
			'rev_user',
			$constraints
		);

		$notConstraintActorQuery = $this->actorMigration->getWhere(
			$this->dbr,
			'rev_user',
			$notConstraints
		);

		if ( $constraints ) {
			$conds[] = $constraintActorQuery['conds'];
		}

		if ( $notConstraints ) {
			$conds[] = "NOT ({$notConstraintActorQuery['conds']})";
		}

		$conds[] = 'rev_page=page_id';

		return (string)$this->dbr->buildSelectSubquery(
			[ 'revision' ] + $constraintActorQuery['tables'] + $notConstraintActorQuery['tables'],
			[ $selectField ],
			$conds,
			__METHOD__,
			[],
			(array)$constraintActorQuery['joins'] + (array)$notConstraintActorQuery['joins']
		);
	}
}
