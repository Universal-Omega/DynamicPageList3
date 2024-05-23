<?php

namespace MediaWiki\Extension\DynamicPageList3\Tests;

/**
 * @group DynamicPageList3
 * @group Database
 * @covers \MediaWiki\Extension\DynamicPageList3\Query
 */
class DPLQueryIntegrationTest extends DPLIntegrationTestCase {

	public function testFindPagesInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1', 'DPLTestArticle 2', 'DPLTestArticle 3', 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [ 'category' => 'DPLTestCategory' ] ),
			true
		);
	}

	public function testFindPagesInCategoryWithOrderAndLimit(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticleMultipleCategories', 'DPLTestArticle 3', ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'ordermethod' => 'sortkey',
				'order' => 'descending',
				'count' => '2'
			] ),
			true
		);
	}

	public function testFindPagesNotInCategory(): void {
		$results = $this->getDPLQueryResults( [
			// NS_MAIN
			'namespace' => '',
			'notcategory' => 'DPLTestCategory',
			'nottitle' => 'DPLTestOpenReferences'
		] );

		$this->assertContains( 'DPLTestArticleNoCategory', $results );
		foreach ( [ 'DPLTestArticle 1', 'DPLTestArticle 2', 'DPLTestArticle 3' ] as $pageInCat ) {
			$this->assertNotContains( $pageInCat, $results );
		}
	}

	public function testFindPagesNotInCategoryByPrefix(): void {
		$results = $this->getDPLQueryResults( [
			// NS_MAIN
			'namespace' => '',
			'titlematch' => 'DPLTest%',
			'notcategory' => 'DPLTestCategory'
		] );

		$this->assertArrayEquals(
			[ 'DPLTestArticleNoCategory', 'DPLTestArticleOtherCategoryWithInfobox', 'DPLTestOpenReferences' ],
			$results,
			true
		);
	}

	public function testFindPagesByPrefix(): void {
		$results = $this->getDPLQueryResults( [
			// NS_MAIN
			'namespace' => '',
			'titlematch' => 'DPLTest%',
		] );

		$this->assertNotEmpty( $results );

		foreach ( $results as $result ) {
			$this->assertStringStartsWith( 'DPLTest', $result );
		}
	}

	public function testFindPagesInCategoryIntersection(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => [ 'DPLTestCategory', 'DPLTestOtherCategory' ]
			] ),
			true
		);
	}

	public function testFindPagesUsingTemplate(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1', 'DPLTestArticleOtherCategoryWithInfobox' ],
			$this->getDPLQueryResults( [
				'uses' => 'Template:DPLInfobox'
			] ),
			true
		);
	}

	public function testFindPagesInCategoryUsingTemplate(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticleOtherCategoryWithInfobox' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestOtherCategory',
				'uses' => 'Template:DPLInfobox'
			] ),
			true
		);
	}

	public function testFindPagesInCategoryNotUsingTemplate(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestOtherCategory',
				'notuses' => 'Template:DPLInfobox'
			] ),
			true
		);
	}

	public function testFindPagesNotInCategoryUsingTemplate(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1' ],
			$this->getDPLQueryResults( [
				'notcategory' => 'DPLTestOtherCategory',
				'uses' => 'Template:DPLInfobox'
			] ),
			true
		);
	}

	public function testFindTemplatesUsedByPage(): void {
		$this->assertArrayEquals(
			[ 'Template:DPLInfobox' ],
			$this->getDPLQueryResults( [
				'usedby' => 'DPLTestArticleOtherCategoryWithInfobox'
			] ),
			true
		);
	}

	public function testFindPagesByTitleRegexpInNamespace(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1', 'DPLTestArticle 2' ],
			$this->getDPLQueryResults( [
				// NS_MAIN
				'namespace' => '',
				'titleregexp' => 'DPLTestArticle [12]'
			] )
		);
	}

	public function testFindPagesBeforeTitleInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1', 'DPLTestArticle 2' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'titlelt' => 'DPLTestArticle 3',
				'count' => '2'
			] )
		);
	}

	public function testFindPagesInCategoryWithMaxRevisionLimit(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 2', 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'maxrevisions' => '1',
			] ),
			true
		);
	}

	public function testFindPagesInCategoryWithMinRevisionLimit(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1', 'DPLTestArticle 3' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'minrevisions' => '2',
			] ),
			true
		);
	}

	public function testFindPagesByCategoryMin(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'categoriesminmax' => '2',
			] ),
			true
		);
	}

	public function testFindPagesByCategoryMinMax(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1', 'DPLTestArticle 2', 'DPLTestArticle 3' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'categoriesminmax' => '1,1',
			] ),
			true
		);
	}

	public function testFindPagesNotModifiedByUserInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 2', 'DPLTestArticle 3', 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'notmodifiedby' => 'DPLTestUser',
			] ),
			true
		);
	}

	public function testFindPagesCreatedByUserInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticleOtherCategoryWithInfobox' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestOtherCategory',
				'createdby' => 'DPLTestSystemUser',
			] ),
			true
		);
	}

	public function testFindPagesLastModifiedByUserInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'lastmodifiedby' => 'DPLTestUser',
			] ),
			true
		);
	}

	public function testFindPagesNotLastModifiedByUserInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 2', 'DPLTestArticle 3', 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'notlastmodifiedby' => 'DPLTestUser',
			] ),
			true
		);
	}

	public function testFindPagesEverModifiedByUserInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1', 'DPLTestArticle 2', 'DPLTestArticle 3', 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'modifiedby' => 'DPLTestAdmin',
			] ),
			true
		);
	}

	public function testFindPagesNotCreatedByUserInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestOtherCategory',
				'notcreatedby' => 'DPLTestSystemUser',
			] ),
			true
		);
	}

	public function testFindPagesViaUserFilterCombinations(): void {
		$this->assertArrayEquals(
			[ 'DPLUncategorizedPage' ],
			$this->getDPLQueryResults( [
				'modifiedby' => 'DPLTestUser',
				'notcreatedby' => 'DPLTestAdmin',
				'notlastmodifiedby' => 'DPLTestUser',
			] ),
			true
		);
	}

	public function testFindPagesInCategoryOrderedByLastEdit(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'ordermethod' => 'lastedit',
			'order' => 'descending',
		] );

		$this->assertArrayEquals(
			[
				'DPLTestArticle 3',
				'DPLTestArticle 2',
				'DPLTestArticleMultipleCategories',
				'DPLTestArticle 1',
			],
			$results,
			true
		);
	}

	public function testFindPagesInCategoryOrderedByFirstEdit(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'ordermethod' => 'firstedit',
			'order' => 'descending',
		] );

		$this->assertArrayEquals(
			[
				'DPLTestArticle 2',
				'DPLTestArticleMultipleCategories',
				'DPLTestArticle 1',
				'DPLTestArticle 3',
			],
			$results,
			true
		);
	}

	public function testGetPageAuthors(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'addauthor' => 'true',
			'order' => 'ascending',
			'ordermethod' => 'title'
		], '%PAGE% %USER%' );

		$this->assertEquals( [
			'DPLTestArticle 1 DPLTestAdmin',
			'DPLTestArticle 2 DPLTestAdmin',
			'DPLTestArticle 3 DPLTestAdmin',
			'DPLTestArticleMultipleCategories DPLTestAdmin',
		], $results );
	}

	public function testGetLastEditorsByPage(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'addlasteditor' => 'true'
		], '%PAGE% %USER%' );

		$this->assertEquals( [
			'DPLTestArticle 1 DPLTestUser',
			'DPLTestArticle 2 DPLTestAdmin',
			'DPLTestArticle 3 DPLTestAdmin',
			'DPLTestArticleMultipleCategories DPLTestAdmin',
		], $results );
	}

	public function testTotalPagesInHeader(): void {
		$results = $this->runDPLQuery( [
			'category' => 'DPLTestCategory',
			'resultsheader' => 'TOTALPAGES: %TOTALPAGES%',
			'count' => 2
		] );

		$this->assertStringContainsString( 'TOTALPAGES: 4', $results );
	}

	public function testOpenReferencesMissing(): void {
		$results = $this->getDPLQueryResults( [
			// NS_MAIN
			'namespace' => '',
			'openreferences' => 'missing',
			'count' => 1
		] );

		$this->assertArrayEquals(
			[
				'RedLink',
			],
			$results,
			true
		);
	}
}
