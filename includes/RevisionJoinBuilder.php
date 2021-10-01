<?php

namespace DPL;

use ActorMigration;
use CommentStore;
use Wikimedia\Rdbms\IDatabase;

class RevisionJoinBuilder {
	/** @var IDatabase */
	private $dbr;

	/** @var ActorMigration */
	private $actorMigration;

	/** @var CommentStore */
	private $commentStore;

	/**
	 * List of fields to select from the first revision of the article, keyed by alias.
	 * @var string[]
	 */
	private $firstRevisionFields = [];

	/**
	 * List of fields to select from the last revision of the article, keyed by alias.
	 * @var string[]
	 */
	private $lastRevisionFields = [];

	public function __construct(
		IDatabase $dbr,
		ActorMigration $actorMigration,
		CommentStore $commentStore
	) {
		$this->dbr = $dbr;
		$this->actorMigration = $actorMigration;
		$this->commentStore = $commentStore;
	}

	public function addFieldsFromFirst( array $fields ): void {
		$this->firstRevisionFields += $fields;
	}

	public function addFieldsFromLast( array $fields ): void {
		$this->lastRevisionFields += $fields;
	}

	public function getQueryInfo(): array {
		$queryInfo = [
			'fields' => [],
			'tables' => [],
			'joins' => [],
		];

		return array_merge_recursive(
			$queryInfo,
			$this->addRevisionQueryInfo(
				'latest_rev',
				$this->lastRevisionFields,
				[ 'rev_id=page_latest' ]
			),
			$this->addRevisionQueryInfo(
				'first_rev',
				$this->firstRevisionFields,
				[
					// @phan-suppress-next-line PhanPluginMixedKeyNoKey
					'rev_page=page_id',
					'rev_parent_id' => 0,
				]
			)
		);
	}

	private function addRevisionQueryInfo(
		string $tableAlias,
		array $fieldsByAlias,
		array $joinConds
	): array {
		if ( !$fieldsByAlias ) {
			return [];
		}

		$fieldsByAlias += [ 'rev_id', 'rev_page', 'rev_parent_id' ];

		$actorQuery = $this->actorMigration->getJoin( 'rev_user' );
		$commentQuery = $this->commentStore->getJoin( 'rev_comment' );

		// Ensure we correctly fetch aliased fields such as rev_user
		// from the revision_actor_temp table if it is active.
		$actorFields = array_keys( $actorQuery['fields'] );
		$revFields = array_diff( $fieldsByAlias, $actorFields );

		// Since multiple instances of the revision and associated actor tables may be used per
		// query, join on the derived result of an aliased subquery to avoid naming conflicts.
		$revQuery = $this->dbr->buildSelectSubquery(
			[ $tableAlias => 'revision' ] + $actorQuery['tables'] + $commentQuery['tables'],
			$actorQuery['fields'] + $commentQuery['fields'] + array_values( $revFields ),
			[],
			__METHOD__,
			[],
			$actorQuery['joins'] + $commentQuery['joins']
		);

		$fieldsWithPrefix = [];
		$quotedTableAlias = $this->dbr->addIdentifierQuotes( $tableAlias );

		foreach ( $fieldsByAlias as $alias => $revFieldName ) {
			$quotedRevField = $this->dbr->addIdentifierQuotes( $revFieldName );
			$fieldsWithPrefix[$alias] = "$quotedTableAlias.$quotedRevField";
		}

		return [
			'fields' => $fieldsWithPrefix,
			'tables' => [
				$tableAlias => $revQuery
			],
			'joins' => [
				$tableAlias => [ 'JOIN', $joinConds ]
			]
		];
	}
}
