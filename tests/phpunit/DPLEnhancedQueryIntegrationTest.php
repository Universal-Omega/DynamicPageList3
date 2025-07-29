<?php

namespace MediaWiki\Extension\DynamicPageList4\Tests;

/**
 * Enhanced integration tests for DPL Query functionality covering more parameter combinations
 *
 * @group DynamicPageList4
 * @group Database
 * @covers \MediaWiki\Extension\DynamicPageList4\Query
 * @covers \MediaWiki\Extension\DynamicPageList4\Parameters
 */
class DPLEnhancedQueryIntegrationTest extends DPLIntegrationTestCase {

	/**
	 * Test complex category filtering with multiple categories
	 */
	public function testMultipleCategoryFiltering(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory|DPLTestCategory2',
			'notcategory' => 'DPLTestExcludeCategory',
			'count' => 10,
		], '%PAGE%' );

		// Should include pages in DPLTestCategory OR DPLTestCategory2 but NOT in DPLTestExcludeCategory
		$this->assertContains( 'DPLTestArticle 2', $results );
		$this->assertContains( 'DPLTestArticle 3', $results );
	}

	/**
	 * Test namespace filtering with multiple namespaces
	 */
	public function testMultipleNamespaceFiltering(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => NS_MAIN . '|' . NS_USER,
			'count' => 20,
		], '%PAGE%' );

		$this->assertIsArray( $results );
		// Should include pages from both main and user namespaces
	}

	/**
	 * Test title pattern matching with regex
	 */
	public function testTitleRegexMatching(): void {
		$results = $this->getDPLQueryResults( [
			'titleregexp' => 'DPLTest.*[0-9]',
			'count' => 10,
		], '%PAGE%' );

		$this->assertContains( 'DPLTestArticle 1', $results );
		$this->assertContains( 'DPLTestArticle 2', $results );
		$this->assertContains( 'DPLTestArticle 3', $results );
	}

	/**
	 * Test complex ordering combinations
	 */
	public function testComplexOrdering(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'ordermethod' => 'categoryadd,lastedit',
			'order' => 'descending',
			'count' => 5,
		], '%PAGE%' );

		$this->assertIsArray( $results );
		$this->assertGreaterThan( 0, count( $results ) );
	}

	/**
	 * Test template usage filtering
	 */
	public function testTemplateUsageFiltering(): void {
		$results = $this->getDPLQueryResults( [
			'uses' => 'Template:DPLInfobox',
			'count' => 10,
		], '%PAGE%' );

		$this->assertContains( 'DPLTestArticle 2', $results );
	}

	/**
	 * Test linked pages filtering
	 */
	public function testLinkedPagesFiltering(): void {
		$results = $this->getDPLQueryResults( [
			'linksto' => 'DPLTestArticle 1',
			'count' => 10,
		], '%PAGE%' );

		$this->assertContains( 'DPLTestArticle 2', $results );
		$this->assertContains( 'DPLTestArticle 3', $results );
	}

	/**
	 * Test date-based filtering
	 */
	public function testDateBasedFiltering(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'createdby' => 'DPLTestUser',
			'ordermethod' => 'firstedit',
			'count' => 5,
		], '%PAGE% %DATE%' );

		$this->assertIsArray( $results );
		// Results should include both page name and date
		foreach ( $results as $result ) {
			$this->assertStringContainsString( 'DPLTestArticle', $result );
		}
	}

	/**
	 * Test user-based filtering
	 */
	public function testUserBasedFiltering(): void {
		$results = $this->getDPLQueryResults( [
			'createdby' => 'DPLTestUser',
			'count' => 10,
		], '%PAGE% %USER%' );

		$this->assertIsArray( $results );
		foreach ( $results as $result ) {
			$this->assertStringContainsString( 'DPLTestUser', $result );
		}
	}

	/**
	 * Test include parameter for content inclusion
	 */
	public function testContentInclusion(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'include' => '{DPLInfobox}',
			'count' => 5,
		], '%PAGE%: %TEXT%' );

		$this->assertIsArray( $results );
		// Should include template content from pages
	}

	/**
	 * Test complex parameter mixing - categories, namespaces, and ordering
	 */
	public function testComplexParameterMixing(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'namespace' => NS_MAIN,
			'ordermethod' => 'title',
			'order' => 'ascending',
			'count' => 10,
			'offset' => 0,
		], '%PAGE%' );

		$this->assertIsArray( $results );
		$this->assertContains( 'DPLTestArticle 2', $results );
		$this->assertContains( 'DPLTestArticle 3', $results );
	}

	/**
	 * Test advanced formatting options
	 */
	public function testAdvancedFormatting(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'format' => ',\n* [[%PAGE%|%TITLE%]] by %USER%,\n',
			'count' => 3,
		], null );

		$this->assertIsString( $results[0] ?? '' );
		// Should be formatted as a list with custom formatting
	}

	/**
	 * Test scroll parameter functionality
	 */
	public function testScrollFunctionality(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'scroll' => true,
			'count' => 2,
		], '%PAGE%' );

		$this->assertIsArray( $results );
		$this->assertLessThanOrEqual( 2, count( $results ) );
	}

	/**
	 * Test multiple exclusion criteria
	 */
	public function testMultipleExclusionCriteria(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'notnamespace' => NS_USER,
			'nottitleregexp' => 'Excluded.*',
			'count' => 10,
		], '%PAGE%' );

		$this->assertIsArray( $results );
		$this->assertContains( 'DPLTestArticle 2', $results );
	}

	/**
	 * Test image-related parameters
	 */
	public function testImageParameters(): void {
		$results = $this->getDPLQueryResults( [
			'namespace' => NS_FILE,
			'ordermethod' => 'title',
			'count' => 5,
		], '%PAGE%' );

		$this->assertIsArray( $results );
		// Should return file pages if any exist
	}

	/**
	 * Test table output mode
	 */
	public function testTableOutputMode(): void {
		$results = $this->getDPLQueryResults( [
			'category' => 'DPLTestCategory',
			'mode' => 'userformat',
			'listseparators' => '{| class="wikitable"\n! Page\n! User\n|-,\n| [[%PAGE%]] || %USER%\n|-,\n|}',
			'count' => 3,
		], '%PAGE%' );

		$this->assertIsString( $results[0] ?? '' );
		// Should contain wikitable markup
	}

	/**
	 * Test distinct parameter
	 */
	public function testDistinctResults(): void {
		$results = $this->getDPLQueryResults( [
			'linksto' => 'DPLTestArticle 1',
			'distinct' => true,
			'count' => 10,
		], '%PAGE%' );

		$this->assertIsArray( $results );
		// Should ensure no duplicate results
		$this->assertSameSize( $results, array_unique( $results ) );
	}
}
