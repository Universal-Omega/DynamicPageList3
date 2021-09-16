<?php
namespace DPL;

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
			'namespace' => '', // NS_MAIN
			'notcategory' => 'DPLTestCategory'
		] );

		$this->assertContains( 'DPLTestArticleNoCategory', $results );
		foreach ( [ 'DPLTestArticle 1', 'DPLTestArticle 2', 'DPLTestArticle 3' ] as $pageInCat ) {
			$this->assertNotContains( $pageInCat, $results );
		}
	}

	public function testFindPagesNotInCategoryByPrefix(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => '', // NS_MAIN
			'titlematch' => 'DPLTest%',
			'notcategory' => 'DPLTestCategory'
		] );

		$this->assertArrayEquals(
			[ 'DPLTestArticleNoCategory', 'DPLTestArticleOtherCategoryWithInfobox' ],
			$results,
			true
		);
	}

	public function testFindPagesByPrefix(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => '', // NS_MAIN
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
				'namespace' => '', // NS_MAIN
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
}
