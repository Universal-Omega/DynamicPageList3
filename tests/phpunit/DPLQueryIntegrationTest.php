<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\DynamicPageList4\Tests;

use const NS_MAIN;

/**
 * @group DynamicPageList4
 * @group Database
 * @covers \MediaWiki\Extension\DynamicPageList4\Query
 */
class DPLQueryIntegrationTest extends DPLIntegrationTestCase {

	public function testFindPagesInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1', 'DPLTestArticle 2', 'DPLTestArticle 3', 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesInCategoryWithOrderAndLimit(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticleMultipleCategories', 'DPLTestArticle 3' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'ordermethod' => 'sortkey',
				'order' => 'descending',
				'count' => 2,
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesNotInCategory(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => NS_MAIN,
			'notcategory' => 'DPLTestCategory',
			'nottitle' => 'DPLTestOpenReferences',
		], '%PAGE%' );

		$this->assertContains( 'DPLTestArticleNoCategory', $results );
		foreach ( [ 'DPLTestArticle 1', 'DPLTestArticle 2', 'DPLTestArticle 3' ] as $pageInCat ) {
			$this->assertNotContains( $pageInCat, $results );
		}
	}

	public function testFindPagesNotInCategoryByPrefix(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => NS_MAIN,
			'titlematch' => 'DPLTest%',
			'notcategory' => 'DPLTestCategory',
		], '%PAGE%' );

		$this->assertArrayEquals( [
			'DPLTestArticleNoCategory',
			'DPLTestArticleOtherCategoryWithInfobox',
			'DPLTestOpenReferences',
		], $results, true );
	}

	public function testFindPagesByPrefix(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => NS_MAIN,
			'titlematch' => 'DPLTest%',
		], '%PAGE%' );

		$this->assertNotEmpty( $results );
		foreach ( $results as $result ) {
			$this->assertStringStartsWith( 'DPLTest', $result );
		}
	}

	public function testFindPagesInCategoryIntersection(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => [ 'DPLTestCategory', 'DPLTestOtherCategory' ],
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesUsingTemplate(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1', 'DPLTestArticle 2', 'DPLTestArticleOtherCategoryWithInfobox' ],
			$this->getDPLQueryResults( [
				'uses' => 'Template:DPLInfobox',
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesInCategoryUsingTemplate(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticleOtherCategoryWithInfobox' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestOtherCategory',
				'uses' => 'Template:DPLInfobox',
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesInCategoryNotUsingTemplate(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestOtherCategory',
				'notuses' => 'Template:DPLInfobox',
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesNotInCategoryUsingTemplate(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1', 'DPLTestArticle 2' ],
			$this->getDPLQueryResults( [
				'notcategory' => 'DPLTestOtherCategory',
				'uses' => 'Template:DPLInfobox',
			], '%PAGE%' ),
			true
		);
	}

	public function testFindTemplatesUsedByPage(): void {
		$this->assertArrayEquals(
			[ 'Template:DPLInfobox' ],
			$this->getDPLQueryResults( [
				'usedby' => 'DPLTestArticleOtherCategoryWithInfobox',
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesByTitleRegexpInNamespace(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1', 'DPLTestArticle 2' ],
			$this->getDPLQueryResults( [
				'namespace' => NS_MAIN,
				'titleregexp' => 'DPLTestArticle [12]',
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesBeforeTitleInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1', 'DPLTestArticle 2' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'titlelt' => 'DPLTestArticle 3',
				'count' => 2,
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesInCategoryWithMaxRevisionLimit(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 2', 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'maxrevisions' => 1,
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesInCategoryWithMinRevisionLimit(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1', 'DPLTestArticle 3' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'minrevisions' => 2,
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesByCategoryMin(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'categoriesminmax' => 2,
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesByCategoryMinMax(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1', 'DPLTestArticle 2', 'DPLTestArticle 3' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'categoriesminmax' => '1,1',
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesNotModifiedByUserInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 2', 'DPLTestArticle 3', 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'notmodifiedby' => 'DPLTestUser',
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesCreatedByUserInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticleOtherCategoryWithInfobox' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestOtherCategory',
				'createdby' => 'DPLTestSystemUser',
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesLastModifiedByUserInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'lastmodifiedby' => 'DPLTestUser',
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesNotLastModifiedByUserInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 2', 'DPLTestArticle 3', 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'notlastmodifiedby' => 'DPLTestUser',
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesEverModifiedByUserInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 1', 'DPLTestArticle 2', 'DPLTestArticle 3', 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestCategory',
				'modifiedby' => 'DPLTestAdmin',
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesNotCreatedByUserInCategory(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticleMultipleCategories' ],
			$this->getDPLQueryResults( [
				'category' => 'DPLTestOtherCategory',
				'notcreatedby' => 'DPLTestSystemUser',
			], '%PAGE%' ),
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
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesInCategoryOrderedByLastEdit(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'ordermethod' => 'lastedit',
			'order' => 'descending',
		], '%PAGE%' );

		$this->assertArrayEquals( [
			'DPLTestArticle 3',
			'DPLTestArticle 2',
			'DPLTestArticleMultipleCategories',
			'DPLTestArticle 1',
		], $results, true );
	}

	public function testFindPagesInCategoryOrderedByFirstEdit(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'ordermethod' => 'firstedit',
			'order' => 'descending',
		], '%PAGE%' );

		$this->assertArrayEquals( [
			'DPLTestArticle 2',
			'DPLTestArticleMultipleCategories',
			'DPLTestArticle 1',
			'DPLTestArticle 3',
		], $results, true );
	}

	public function testFindPagesInCategoryOrderedByTitleWithoutNamespace(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'ordermethod' => 'titlewithoutnamespace',
		], '%PAGE%' );

		$this->assertArrayEquals( [
			'DPLTestArticleMultipleCategories',
			'DPLTestArticle 1',
			'DPLTestArticle 2',
			'DPLTestArticle 3',
		], $results, true );
	}

	public function testFindPagesInCategoryOrderedByFullTitle(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'ordermethod' => 'title',
		], '%PAGE%' );

		$this->assertArrayEquals( [
			'DPLTestArticle 1',
			'DPLTestArticle 2',
			'DPLTestArticle 3',
			'DPLTestArticleMultipleCategories',
		], $results, true );
	}

	public function testGetPageAuthors(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'addauthor' => true,
			'order' => 'ascending',
			'ordermethod' => 'title',
		], '%PAGE% %USER%' );

		$this->assertArrayEquals( [
			'DPLTestArticle 1 DPLTestAdmin',
			'DPLTestArticle 2 DPLTestAdmin',
			'DPLTestArticle 3 DPLTestAdmin',
			'DPLTestArticleMultipleCategories DPLTestAdmin',
		], $results, true );
	}

	public function testGetLastEditorsByPage(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'addlasteditor' => true,
		], '%PAGE% %USER%' );

		$this->assertArrayEquals( [
			'DPLTestArticle 1 DPLTestUser',
			'DPLTestArticle 2 DPLTestAdmin',
			'DPLTestArticle 3 DPLTestAdmin',
			'DPLTestArticleMultipleCategories DPLTestAdmin',
		], $results, true );
	}

	public function testTotalPagesInHeader(): void {
		$results = $this->runDPLQuery( [
			'category' => 'DPLTestCategory',
			'resultsheader' => 'TOTALPAGES: %TOTALPAGES%',
			'count' => 2,
		] );

		$this->assertStringContainsString( 'TOTALPAGES: 4', $results );
	}

	public function testShowCurId(): void {
		$results = $this->runDPLQuery( [
			'category' => 'DPLTestCategory',
			'showcurid' => true,
			'count' => 1,
		] );

		$this->assertStringContainsString(
			'curid=4">DPLTestArticle 1</a>',
			$results
		);
	}

	public function testOpenReferencesMissing(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => NS_MAIN,
			'openreferences' => 'missing',
			'count' => 1,
		], '%PAGE%' );

		$this->assertArrayEquals( [ 'RedLink' ], $results, true );
	}

	public function testFindPagesLinkingToPage(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => NS_MAIN,
			'linksto' => 'DPLTestArticle 2',
		], '%PAGE%' );

		$this->assertArrayEquals( [
			'DPLTestArticle 1',
			'DPLTestOpenReferences',
		], $results, true );
	}

	public function testFindPagesNotLinkingToPage(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => NS_MAIN,
			'notlinksto' => 'DPLTestArticle 2',
		], '%PAGE%' );

		$this->assertArrayEquals( [
			'DPLTestArticle 2',
			'DPLTestArticle 3',
			'DPLTestArticleNoCategory',
			'DPLTestArticleMultipleCategories',
			'DPLTestArticleOtherCategoryWithInfobox',
			'DPLUncategorizedPage',
		], $results, true );
	}

	public function testFindPagesLinkedFromPage(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => NS_MAIN,
			'linksfrom' => 'DPLTestArticle 1',
		], '%PAGE%' );

		$this->assertArrayEquals( [
			'DPLTestArticle 2',
			'DPLTestArticleNoCategory',
		], $results, true );
	}

	public function testFindPagesLinkedFromPageWithPagesel(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => NS_MAIN,
			'linksfrom' => 'DPLTestArticle 1',
		], '%PAGESEL% to %PAGE%' );

		$this->assertArrayEquals( [
			'DPLTestArticle 1 to DPLTestArticle 2',
			'DPLTestArticle 1 to DPLTestArticleNoCategory',
		], $results, true );
	}

	public function testFindPagesLinkedFromPageOrderedByPagesel(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => NS_MAIN,
			'linksfrom' => 'DPLTestOpenReferences|DPLTestArticle 1',
			'ordermethod' => 'pagesel',
		], '%PAGE%' );

		$this->assertArrayEquals( [
			'DPLTestArticleNoCategory',
			'DPLTestArticle 1',
			'DPLTestArticle 2',
			'DPLTestArticle 3',
		], $results, true );
	}

	public function testFindPagesLinkedToPageOrderedByPagesel(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => NS_MAIN,
			'linksto' => 'DPLTestArticle 1',
			'ordermethod' => 'pagesel',
		], '%PAGE%' );

		$this->assertArrayEquals( [
			'DPLTestArticle 2',
			'DPLTestArticle 3',
			'DPLTestOpenReferences',
		], $results, true );
	}

	public function testFindPagesNotLinkedFromPage(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => NS_MAIN,
			'notlinksfrom' => 'DPLTestArticle 1',
		], '%PAGE%' );

		$this->assertArrayEquals( [
			'DPLTestArticle 1',
			'DPLTestArticle 3',
			'DPLTestArticleMultipleCategories',
			'DPLTestArticleOtherCategoryWithInfobox',
			'DPLUncategorizedPage',
			'DPLTestOpenReferences',
		], $results, true );
	}

	public function testFindPagesWithOpenReferencesLinkedFromPage(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => NS_MAIN,
			'linksfrom' => 'DPLTestOpenReferences',
			'openreferences' => true,
		], '%PAGE%' );

		$this->assertArrayEquals( [
			'DPLTestArticle 1',
			'DPLTestArticle 2',
			'DPLTestArticle 3',
			'RedLink',
		], $results, true );
	}

	public function testFindPagesLinkingToAndFromPage(): void {
		$this->assertArrayEquals(
			[ 'DPLTestArticle 2', 'DPLTestArticle 3' ],
			$this->getDPLQueryResults( [
				'linksfrom' => 'DPLTestOpenReferences',
				'linksto' => 'DPLTestArticle 1',
			], '%PAGE%' ),
			true
		);
	}

	public function testFindPagesLinkingToAndFromPageWithUses(): void {
		$this->assertArrayEquals(
			// Only this page links both from DPLTestOpenReferences and to DPLTestArticle 1
			// while also using Template:DPLInfobox.
			[ 'DPLTestArticle 2' ],
			$this->getDPLQueryResults( [
				'linksfrom' => 'DPLTestOpenReferences',
				'linksto' => 'DPLTestArticle 1',
				'uses' => 'Template:DPLInfobox',
			], '%PAGE%' ),
			true
		);
	}

	public function testGetEditSummary(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'firstrevisionsince' => '200812041300',
			'ordermethod' => 'firstedit',
			'adduser' => true,
			'count' => 1,
		], '%PAGE%: %EDITSUMMARY%' );

		$this->assertArrayEquals( [
			'DPLTestArticle 3: Initial page version',
		], $results, true );
	}
}
