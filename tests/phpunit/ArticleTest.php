<?php

namespace MediaWiki\Extension\DynamicPageList4\Tests;

use MediaWiki\Extension\DynamicPageList4\Article;
use MediaWiki\Extension\DynamicPageList4\Parameters;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use stdClass;
use Wikimedia\TestingAccessWrapper;
use const NS_CATEGORY;
use const NS_MAIN;

/**
 * @group DynamicPageList4
 * @group Database
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
			'rev_actor' => 1,
			'rev_timestamp' => '20230101120000',
			'rev_comment_text' => 'Test edit summary',
			'rev_deleted' => 0,
		] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );
		$title->method( 'getPrefixedText' )->willReturn( 'Page' );
		$title->method( 'getFullURL' )->willReturn( 'http://example.com/Page' );

		$parameters = $this->createMockParameters( [ 'lastrevisionbefore' => '20230102000000' ] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		$this->assertSame( 456, $article->mRevision );
		$this->assertSame( '20230101120000', TestingAccessWrapper::newFromObject( $article )->mDate );
		$this->assertNotEmpty( $article->mUser );
		$this->assertStringContainsString( 'Test edit summary', $article->mComment );
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

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );
		$title->method( 'getPrefixedText' )->willReturn( 'Page' );
		$title->method( 'getFullURL' )->willReturn( 'http://example.com/Page' );

		// Test addpagetoucheddate
		$parameters = $this->createMockParameters( [ 'addpagetoucheddate' => true ] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		// The mDate should be set and adjusted for user timezone
		$articleWrapper = TestingAccessWrapper::newFromObject( $article );
		$this->assertNotEmpty( $articleWrapper->mDate );

		// Test addfirstcategorydate
		$parameters2 = $this->createMockParameters( [ 'addfirstcategorydate' => true ] );
		$article2 = Article::newFromRow( $row, $parameters2, $title, NS_MAIN, 'Page' );

		$article2Wrapper = TestingAccessWrapper::newFromObject( $article2 );
		$this->assertNotEmpty( $article2Wrapper->mDate );

		// Test addeditdate
		$parameters3 = $this->createMockParameters( [ 'addeditdate' => true ] );
		$article3 = Article::newFromRow( $row, $parameters3, $title, NS_MAIN, 'Page' );

		$article3Wrapper = TestingAccessWrapper::newFromObject( $article3 );
		$this->assertNotEmpty( $article3Wrapper->mDate );

		// Test with userdateformat
		$parameters4 = $this->createMockParameters( [
			'addeditdate' => true,
			'userdateformat' => 'Y-m-d H:i:s',
		] );

		$article4 = Article::newFromRow( $row, $parameters4, $title, NS_MAIN, 'Page' );
		$this->assertNotEmpty( $article4->myDate );
	}

	/**
	 * Test contribution handling
	 */
	public function testNewFromRowWithContribution(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'contribution' => 50,
			'contributor' => 1,
			'contrib_deleted' => 0,
		] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );
		$title->method( 'getPrefixedText' )->willReturn( 'Page' );
		$title->method( 'getFullURL' )->willReturn( 'http://example.com/Page' );

		$parameters = $this->createMockParameters( [ 'addcontribution' => true ] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		$this->assertSame( 50, $article->mContribution );
		$this->assertNotEmpty( $article->mContributor );
		$this->assertNotEmpty( $article->mContrib );
	}

	/**
	 * Test user/author link handling
	 */
	public function testNewFromRowWithUserLinks(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'rev_actor' => 1,
		] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );
		$title->method( 'getPrefixedText' )->willReturn( 'Page' );
		$title->method( 'getFullURL' )->willReturn( 'http://example.com/Page' );

		// Test adduser parameter
		$parameters = $this->createMockParameters( [ 'adduser' => true ] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		$this->assertNotEmpty( $article->mUserLink );
		$this->assertNotEmpty( $article->mUser );
		$this->assertStringContainsString( '[[User:', $article->mUserLink );

		// Test addauthor parameter
		$parameters2 = $this->createMockParameters( [ 'addauthor' => true ] );
		$article2 = Article::newFromRow( $row, $parameters2, $title, NS_MAIN, 'Page' );

		$this->assertNotEmpty( $article2->mUserLink );
		$this->assertStringContainsString( '[[User:', $article2->mUserLink );

		// Test addlasteditor parameter
		$parameters3 = $this->createMockParameters( [ 'addlasteditor' => true ] );
		$article3 = Article::newFromRow( $row, $parameters3, $title, NS_MAIN, 'Page' );

		$this->assertNotEmpty( $article3->mUserLink );
		$this->assertStringContainsString( '[[User:', $article3->mUserLink );
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
			'rev_actor' => 1,
		] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );
		$title->method( 'getPrefixedText' )->willReturn( 'Page' );
		$title->method( 'getFullURL' )->willReturn( 'http://example.com/Page' );

		$parameters = $this->createMockParameters( [
			'headingmode' => 'definition',
			'ordermethod' => [ 'user' ],
		] );

		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		// Check that user heading link is generated
		$this->assertNotEmpty( $article->mParentHLink );
		$this->assertStringContainsString( '[[User:', $article->mParentHLink );

		// Check that headings contain user information
		$headings = Article::getHeadings();
		$this->assertNotEmpty( $headings );
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
	 * Test getDate method with different date states and language formatting
	 */
	public function testGetDate(): void {
		// Test with empty dates
		$row = $this->createMockRow( [ 'page_id' => 123 ] );
		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );
		$title->method( 'getPrefixedText' )->willReturn( 'Page' );
		$title->method( 'getFullURL' )->willReturn( 'http://example.com/Page' );

		$parameters = $this->createMockParameters( [] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );
		$this->assertSame( '', $article->getDate() );

		// Test with actual date and userdateformat
		$row2 = $this->createMockRow( [
			'page_id' => 124,
			'rev_timestamp' => '20230101120000',
		] );

		$parameters2 = $this->createMockParameters( [
			'addeditdate' => true,
			'userdateformat' => 'Y-m-d H:i:s',
		] );

		$article2 = Article::newFromRow( $row2, $parameters2, $title, NS_MAIN, 'Page' );

		// Should return the formatted date from myDate
		$date = $article2->getDate();
		$this->assertNotEmpty( $date );
		$this->assertStringContainsString( '2023-01-01', $date );

		// Test with date but no userdateformat (uses language formatting)
		$parameters3 = $this->createMockParameters( [ 'addeditdate' => true ] );
		$article3 = Article::newFromRow( $row2, $parameters3, $title, NS_MAIN, 'Page' );

		$date3 = $article3->getDate();
		$this->assertNotEmpty( $date3 );
	}

	/**
	 * Test revision comment handling with deleted comments
	 */
	public function testNewFromRowWithDeletedRevisionComment(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'rev_id' => 456,
			'rev_actor' => 1,
			'rev_timestamp' => '20230101120000',
			'rev_comment_text' => 'Original comment',
			'rev_deleted' => RevisionRecord::DELETED_COMMENT,
		] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );
		$title->method( 'getPrefixedText' )->willReturn( 'Page' );
		$title->method( 'getFullURL' )->willReturn( 'http://example.com/Page' );

		$parameters = $this->createMockParameters( [ 'lastrevisionbefore' => '20230102000000' ] );
		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		// Should show deleted comment message instead of original comment
		$this->assertStringContainsString( 'rev-deleted-comment', $article->mComment );
	}

	/**
	 * Test handling of deleted/hidden users
	 */
	public function testNewFromRowWithDeletedUser(): void {
		$row = $this->createMockRow( [
			'page_id' => 123,
			'rev_actor' => 1,
			'rev_deleted' => RevisionRecord::DELETED_USER,
			'contribution' => 25,
			'contributor' => 1,
			'contrib_deleted' => RevisionRecord::DELETED_USER,
		] );

		$title = $this->createMock( Title::class );
		$title->method( 'getText' )->willReturn( 'Page' );
		$title->method( 'getFullText' )->willReturn( 'Page' );
		$title->method( 'getPrefixedText' )->willReturn( 'Page' );
		$title->method( 'getFullURL' )->willReturn( 'http://example.com/Page' );

		$parameters = $this->createMockParameters( [
			'adduser' => true,
			'addcontribution' => true,
		] );

		$article = Article::newFromRow( $row, $parameters, $title, NS_MAIN, 'Page' );

		// Should show deleted user message
		$this->assertStringContainsString( 'rev-deleted-user', $article->mUser );
		$this->assertStringContainsString( 'rev-deleted-user', $article->mContributor );
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
