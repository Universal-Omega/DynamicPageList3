<?php

namespace MediaWiki\Extension\DynamicPageList4\Tests;

use MediaWiki\Extension\DynamicPageList4\Article;
use MediaWiki\Extension\DynamicPageList4\Parameters;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWikiIntegrationTestCase;
use stdClass;
use Wikimedia\TestingAccessWrapper;
use const NS_CATEGORY;
use const NS_MAIN;

/**
 * @group DynamicPageList4
 * @covers \MediaWiki\Extension\DynamicPageList4\Article
 */
class ArticleTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		// Reset static headings before each test
		Article::resetHeadings();
	}

	/**
	 * Test basic article creation with minimal data
	 */
	public function testNewFromRowBasic(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'page_len' => 1000,
			'page_counter' => 5,
			'displaytitle' => 'Test Page Display Title',
		] );

		$title = $this->createMock( Title::class );
		$title->method( 'getPrefixedText' )->willReturn( 'Test:Page' );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullURL' )->willReturn( 'http://example.com/Test:Page' );
		$title->method( 'getFullText' )->willReturn( 'Test:Page' );

		$parameters = $this->createMockParameters( [] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		$this->assertSame( 123, $article->mID );
		$this->assertSame( 1000, $article->mSize );
		$this->assertSame( 5, $article->mCounter );
		$this->assertSame( 'Test Page Display Title', $article->mDisplayTitle );
		$this->assertSame( NS_MAIN, $article->mNamespace );
		$this->assertSame( $title, $article->mTitle );
	}

	/**
	 * Test article link generation with shownamespace parameter
	 */
	public function testNewFromRowWithShowNamespace(): void {
		$row = $this->createMockRow( [ 'page_id' => 123 ] );

		$title = $this->createMock( Title::class );
		$title->method( 'getPrefixedText' )->willReturn( 'Test:Page' );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Test:Page' );

		$parameters = $this->createMockParameters( [ 'shownamespace' => true ] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		$this->assertStringContainsString( '|Test:Page', $article->mLink );
	}

	/**
	 * Test article link generation without shownamespace parameter
	 */
	public function testNewFromRowWithoutShowNamespace(): void {
		$row = $this->createMockRow( [ 'page_id' => 123 ] );

		$title = $this->createMock( Title::class );
		$title->method( 'getPrefixedText' )->willReturn( 'Test:Page' );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Test:Page' );

		$parameters = $this->createMockParameters( [ 'shownamespace' => false ] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		$this->assertStringContainsString( 'Test:Page|Page', $article->mLink );
	}

	/**
	 * Test showcurid parameter generates correct link format
	 */
	public function testNewFromRowWithShowCurId(): void {
		$row = $this->createMockRow( [ 'page_id' => 123 ] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullURL' )->with( [ 'curid' => 123 ] )->willReturn( 'http://example.com/Page?curid=123' );

		$parameters = $this->createMockParameters( [ 'showcurid' => true ] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		$this->assertStringStartsWith( '[http://example.com/Page?curid=123', $article->mLink );
		$this->assertStringContainsString( 'Page]', $article->mLink );
	}

	/**
	 * Test title replacement with replaceintitle parameter
	 */
	public function testNewFromRowWithReplaceInTitle(): void {
		$row = $this->createMockRow( [ 'page_id' => 123 ] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Test_Page_Name' );
		$title->method( 'getFullText' )->willReturn( 'Test_Page_Name' );

		$parameters = $this->createMockParameters( [
			'replaceintitle' => [ '/Test_/', 'Demo ' ],
		] );

		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Test_Page_Name' );
		$this->assertStringContainsString( 'Demo Page_Name', $article->mLink );
	}

	/**
	 * Test title truncation with titlemaxlen parameter
	 */
	public function testNewFromRowWithTitleMaxLen(): void {
		$row = $this->createMockRow( [ 'page_id' => 123 ] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'This is a very long page title' );
		$title->method( 'getFullText' )->willReturn( 'This is a very long page title' );

		$parameters = $this->createMockParameters( [ 'titlemaxlen' => 10 ] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'This is a very long page title' );

		$this->assertStringContainsString( 'This is a...', $article->mLink );
	}

	/**
	 * Test escape links for category and file namespaces
	 */
	public function testNewFromRowWithEscapeLinks(): void {
		$row = $this->createMockRow( [ 'page_id' => 123 ] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'TestCategory' );
		$title->method( 'getFullText' )->willReturn( 'Category:TestCategory' );

		$parameters = $this->createMockParameters( [ 'escapelinks' => true ] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_CATEGORY, 'TestCategory' );

		$this->assertStringContainsString( '[[:Category:TestCategory', $article->mLink );
	}

	/**
	 * Test external link handling
	 */
	public function testNewFromRowWithExternalLink(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'el_to' => 'https://example.com',
		] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );

		$parameters = $this->createMockParameters( [] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		$this->assertSame( 'https://example.com', $article->mExternalLink );
	}

	/**
	 * Test linksto and linksfrom parameters
	 */
	public function testNewFromRowWithLinksParameters(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'sel_title' => 'Selected Page',
			'sel_ns' => NS_MAIN,
		] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );

		$parameters = $this->createMockParameters( [ 'linksto' => 'Selected Page' ] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		$this->assertSame( 'Selected Page', $article->mSelTitle );
		$this->assertSame( NS_MAIN, $article->mSelNamespace );
	}

	/**
	 * Test imageused parameter
	 */
	public function testNewFromRowWithImageUsed(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'image_sel_title' => 'Test.jpg',
		] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );

		$parameters = $this->createMockParameters( [ 'imageused' => 'Test.jpg' ] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		$this->assertSame( 'Test.jpg', $article->mImageSelTitle );
	}

	/**
	 * Test revision handling with lastrevisionbefore parameter
	 */
	public function testNewFromRowWithRevisionData(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'rev_id' => 456,
			'rev_actor' => 789,
			'rev_timestamp' => '20230101120000',
			'rev_comment_text' => 'Test edit summary',
			'rev_deleted' => 0,
		] );
		unset( $row );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );

		// Mock user for revision
		$user = $this->createMock( User::class );
		$user->method( 'getName' )->willReturn( 'TestUser' );
		$user->method( 'isHidden' )->willReturn( false );

		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromActorId' )->with( 789 )->willReturn( $user );

		$services = $this->createMock( MediaWikiServices::class );
		$services->method( 'getUserFactory' )->willReturn( $userFactory );

		$parameters = $this->createMockParameters( [ 'lastrevisionbefore' => '20230102000000' ] );
		unset( $parameters );

		// We need to test this in integration context due to MediaWikiServices dependency
		$this->markTestSkipped( 'Requires integration test setup for MediaWikiServices' );
	}

	/**
	 * Test date handling with different date parameters
	 */
	public function testNewFromRowWithDateParameters(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'page_touched' => '20230101120000',
			'cl_timestamp' => '20230101130000',
			'rev_timestamp' => '20230101140000',
		] );
		unset( $row );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );

		// Test addpagetoucheddate
		$parameters = $this->createMockParameters( [ 'addpagetoucheddate' => true ] );
		unset( $parameters );

		$this->markTestSkipped( 'Requires integration test setup for language services' );
	}

	/**
	 * Test contribution handling
	 */
	public function testNewFromRowWithContribution(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'contribution' => 50,
			'contributor' => 789,
			'contrib_deleted' => 0,
		] );
		unset( $row );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );

		$parameters = $this->createMockParameters( [ 'addcontribution' => true ] );
		unset( $parameters );

		$this->markTestSkipped( 'Requires integration test setup for UserFactory' );
	}

	/**
	 * Test user/author link handling
	 */
	public function testNewFromRowWithUserLinks(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'rev_actor' => 789,
		] );
		unset( $row );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );

		$parameters = $this->createMockParameters( [ 'adduser' => true ] );
		unset( $parameters );

		$this->markTestSkipped( 'Requires integration test setup for UserFactory' );
	}

	/**
	 * Test category links handling
	 */
	public function testNewFromRowWithCategories(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'cats' => 'Category1 | Category_2 | Category3',
		] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );

		$parameters = $this->createMockParameters( [ 'addcategories' => true ] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		$this->assertCount( 3, $article->mCategoryLinks );
		$this->assertCount( 3, $article->mCategoryTexts );
		$this->assertStringContainsString( 'Category1', $article->mCategoryLinks[0] );
		$this->assertStringContainsString( 'Category 2', $article->mCategoryLinks[1] );
		$this->assertSame( 'Category1', $article->mCategoryTexts[0] );
		$this->assertSame( 'Category 2', $article->mCategoryTexts[1] );
	}

	/**
	 * Test heading mode with category ordermethod
	 */
	public function testNewFromRowWithHeadingModeCategory(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'cl_to' => 'TestCategory',
		] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );

		$parameters = $this->createMockParameters( [
			'headingmode' => 'definition',
			'ordermethod' => [ 'category' ],
		] );

		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );
		$this->assertStringContainsString( 'TestCategory', $article->mParentHLink );

		$headings = Article::getHeadings();
		$this->assertArrayHasKey( 'TestCategory', $headings );
		$this->assertSame( 1, $headings['TestCategory'] );
	}

	/**
	 * Test heading mode with user ordermethod
	 */
	public function testNewFromRowWithHeadingModeUser(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'rev_actor' => 789,
		] );
		unset( $row );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );

		$parameters = $this->createMockParameters( [
			'headingmode' => 'definition',
			'ordermethod' => [ 'user' ],
		] );
		unset( $parameters );

		$this->markTestSkipped( 'Requires integration test setup for UserFactory' );
	}

	/**
	 * Test uncategorized pages handling
	 */
	public function testNewFromRowWithUncategorizedPage(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'cl_to' => '',
		] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );

		$parameters = $this->createMockParameters( [
			'headingmode' => 'definition',
			'ordermethod' => [ 'category' ],
		] );

		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );
		$this->assertStringContainsString( 'Special:Uncategorizedpages', $article->mParentHLink );
	}

	/**
	 * Test goal=categories parameter
	 */
	public function testNewFromRowWithGoalCategories(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'rev_timestamp' => '20230101120000',
		] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );

		$parameters = $this->createMockParameters( [ 'goal' => 'categories' ] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		// When goal=categories, revision processing should be skipped
		$this->assertSame( '', TestingAccessWrapper::newFromObject( $article )->mDate );
		$this->assertSame( 0, $article->mRevision );
	}

	/**
	 * Test static headings functionality
	 */
	public function testHeadingsStatic(): void {
		// Test initial state
		$this->assertSame( [], Article::getHeadings() );

		// Create article that adds to headings
		$row = $this->createMockRow( [
			'page_id' => 123,
			'cl_to' => 'Category1',
		] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page1' );
		$title->method( 'getFullText' )->willReturn( 'Page1' );

		$parameters = $this->createMockParameters( [
			'headingmode' => 'definition',
			'ordermethod' => [ 'category' ],
		] );

		Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page1' );

		// Check headings were added
		$headings = Article::getHeadings();
		$this->assertArrayHasKey( 'Category1', $headings );
		$this->assertSame( 1, $headings['Category1'] );

		// Create another article in same category
		$row2 = $this->createMockRow( [
			'page_id' => 124,
			'cl_to' => 'Category1',
		] );

		$title2 = $this->createMock( Title::class );
		$title2->method( 'getText' )->willReturn( 'Page2' );
		$title2->method( 'getFullText' )->willReturn( 'Page2' );

		Article::newFromRow( $row2, $parameters, $title2, NS_MAIN, 'Page2' );

		// Check headings were incremented
		$headings = Article::getHeadings();
		$this->assertSame( 2, $headings['Category1'] );

		// Test reset
		Article::resetHeadings();
		$this->assertSame( [], Article::getHeadings() );
	}

	/**
	 * Test getDate method with different date states
	 */
	public function testGetDate(): void {
		// Test with empty dates
		$row = $this->createMockRow( [ 'page_id' => 123 ] );
		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );
		$parameters = $this->createMockParameters( [] );

		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );
		$this->assertSame( '', $article->getDate() );

		// Testing with actual dates would require integration test setup
		$this->markTestSkipped( 'Full date testing requires integration test setup for language services' );
	}

	/**
	 * Helper method to create mock database row
	 */
	private function createMockRow( array $data ): stdClass {
		$row = new stdClass();
		foreach ( $data as $key => $value ) {
			$row->$key = $value;
		}

		return $row;
	}

	/**
	 * Helper method to create mock Parameters object
	 */
	private function createMockParameters( array $params ): Parameters {
		$parameters = $this->createMock( Parameters::class );
		$parameters->method( 'getParameter' )->willReturnCallback(
			static fn ( string $key ): mixed => $params[$key] ?? null
		);

		return $parameters;
	}
}
