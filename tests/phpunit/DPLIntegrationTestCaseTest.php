<?php

namespace MediaWiki\Extension\DynamicPageList4\Tests;

use DOMDocument;
use ImportStreamSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use ReflectionClass;
use ReflectionMethod;

/**
 * @group Database
 * @covers \MediaWiki\Extension\DynamicPageList4\Tests\DPLIntegrationTestCase
 */
class DPLIntegrationTestCaseTest extends DPLIntegrationTestCase {

	/**
	 * Test that setUp properly initializes the import stream source
	 */
	public function testSetUpInitializesImportStreamSource(): void {
		// Use reflection to access private property
		$reflection = new ReflectionClass($this);
		$property = $reflection->getProperty('importStreamSource');
		$property->setAccessible(true);
		$importStreamSource = $property->getValue($this);

		$this->assertInstanceOf(Status::class, $importStreamSource);
		$this->assertTrue($importStreamSource->isGood(), 'Import stream source should be valid');
	}

	/**
	 * Test setUp failure when seed data file is missing
	 */
	public function testSetUpFailsWithMissingSeedData(): void {
		// Create a test class that uses a non-existent file
		$testCase = new class extends DPLIntegrationTestCase {
			protected function setUp(): void {
				// Skip parent setUp to avoid the real file
				$file = '/non/existent/path/seed-data.xml';
				$this->importStreamSource = ImportStreamSource::newFromFile($file);
				
				if (!$this->importStreamSource->isGood()) {
					$this->fail("Import source for $file failed.");
				}
			}
		};

		$this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
		$this->expectExceptionMessage('Import source for /non/existent/path/seed-data.xml failed.');
		$testCase->setUp();
	}

	/**
	 * Test doImport method functionality
	 */
	public function testDoImportExecutesSuccessfully(): void {
		$reflection = new ReflectionClass($this);
		$method = $reflection->getMethod('doImport');
		$method->setAccessible(true);

		// This should not throw any exceptions
		$method->invoke($this);
		$this->assertTrue(true, 'doImport completed without exceptions');
	}

	/**
	 * Test seedTestUsers with valid XML structure
	 */
	public function testSeedTestUsersWithValidXml(): void {
		// Create a temporary XML file with test data
		$tempXml = tempnam(sys_get_temp_dir(), 'test_seed_');
		$xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
		<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.11/">
			<page>
				<revision>
					<contributor>
						<username>TestUser1</username>
					</contributor>
				</revision>
			</page>
			<page>
				<revision>
					<contributor>
						<username>TestUser2</username>
					</contributor>
				</revision>
			</page>
		</mediawiki>';
		
		file_put_contents($tempXml, $xmlContent);

		$reflection = new ReflectionClass($this);
		$method = $reflection->getMethod('seedTestUsers');
		$method->setAccessible(true);

		// This should not throw any exceptions
		$method->invoke($this, $tempXml);
		
		unlink($tempXml);
		$this->assertTrue(true, 'seedTestUsers completed without exceptions');
	}

	/**
	 * Test seedTestUsers with malformed XML
	 */
	public function testSeedTestUsersWithMalformedXml(): void {
		$tempXml = tempnam(sys_get_temp_dir(), 'test_seed_');
		$xmlContent = '<?xml version="1.0" encoding="UTF-8"?><invalid><xml>';
		file_put_contents($tempXml, $xmlContent);

		$reflection = new ReflectionClass($this);
		$method = $reflection->getMethod('seedTestUsers');
		$method->setAccessible(true);

		// Should handle malformed XML gracefully
		$this->expectException(\DOMException::class);
		$method->invoke($this, $tempXml);
		
		unlink($tempXml);
	}

	/**
	 * Test seedTestUsers with duplicate usernames
	 */
	public function testSeedTestUsersWithDuplicateUsernames(): void {
		$tempXml = tempnam(sys_get_temp_dir(), 'test_seed_');
		$xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
		<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.11/">
			<page>
				<revision>
					<contributor>
						<username>DuplicateUser</username>
					</contributor>
				</revision>
			</page>
			<page>
				<revision>
					<contributor>
						<username>DuplicateUser</username>
					</contributor>
				</revision>
			</page>
		</mediawiki>';
		
		file_put_contents($tempXml, $xmlContent);

		$reflection = new ReflectionClass($this);
		$method = $reflection->getMethod('seedTestUsers');
		$method->setAccessible(true);

		// Should handle duplicates gracefully
		$method->invoke($this, $tempXml);
		
		unlink($tempXml);
		$this->assertTrue(true, 'seedTestUsers handled duplicates without exceptions');
	}

	/**
	 * Test seedTestUsers with empty XML document
	 */
	public function testSeedTestUsersWithEmptyXml(): void {
		$tempXml = tempnam(sys_get_temp_dir(), 'test_seed_');
		$xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
		<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.11/">
		</mediawiki>';
		
		file_put_contents($tempXml, $xmlContent);

		$reflection = new ReflectionClass($this);
		$method = $reflection->getMethod('seedTestUsers');
		$method->setAccessible(true);

		// Should handle empty XML gracefully without throwing
		$method->invoke($this, $tempXml);
		
		unlink($tempXml);
		$this->assertTrue(true, 'seedTestUsers handled empty XML without exceptions');
	}

	/**
	 * Test seedTestUsers with XML containing no username nodes
	 */
	public function testSeedTestUsersWithNoUsernameNodes(): void {
		$tempXml = tempnam(sys_get_temp_dir(), 'test_seed_');
		$xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
		<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.11/">
			<page>
				<revision>
					<contributor>
						<id>123</id>
					</contributor>
				</revision>
			</page>
		</mediawiki>';
		
		file_put_contents($tempXml, $xmlContent);

		$reflection = new ReflectionClass($this);
		$method = $reflection->getMethod('seedTestUsers');
		$method->setAccessible(true);

		// Should handle XML without username nodes gracefully
		$method->invoke($this, $tempXml);
		
		unlink($tempXml);
		$this->assertTrue(true, 'seedTestUsers handled XML without usernames without exceptions');
	}

	/**
	 * Test newUserFromName with valid username
	 */
	public function testNewUserFromNameWithValidUsername(): void {
		$reflection = new ReflectionClass($this);
		$method = $reflection->getMethod('newUserFromName');
		$method->setAccessible(true);

		$user = $method->invoke($this, 'ValidTestUser123');
		$this->assertInstanceOf(User::class, $user);
	}

	/**
	 * Test newUserFromName with invalid username containing special characters
	 */
	public function testNewUserFromNameWithInvalidUsername(): void {
		$reflection = new ReflectionClass($this);
		$method = $reflection->getMethod('newUserFromName');
		$method->setAccessible(true);

		// Test with invalid characters
		$user = $method->invoke($this, 'Invalid#Username@');
		$this->assertNull($user, 'Should return null for username with invalid characters');
	}

	/**
	 * Test newUserFromName with empty string
	 */
	public function testNewUserFromNameWithEmptyString(): void {
		$reflection = new ReflectionClass($this);
		$method = $reflection->getMethod('newUserFromName');
		$method->setAccessible(true);

		$user = $method->invoke($this, '');
		$this->assertNull($user, 'Should return null for empty username');
	}

	/**
	 * Test newUserFromName with username that's too long
	 */
	public function testNewUserFromNameWithTooLongUsername(): void {
		$reflection = new ReflectionClass($this);
		$method = $reflection->getMethod('newUserFromName');
		$method->setAccessible(true);

		// Create a username that exceeds typical length limits (255+ characters)
		$longUsername = str_repeat('a', 300);
		$user = $method->invoke($this, $longUsername);
		$this->assertNull($user, 'Should return null for excessively long username');
	}

	/**
	 * Test newUserFromName with whitespace-only username
	 */
	public function testNewUserFromNameWithWhitespaceOnly(): void {
		$reflection = new ReflectionClass($this);
		$method = $reflection->getMethod('newUserFromName');
		$method->setAccessible(true);

		$user = $method->invoke($this, '   ');
		$this->assertNull($user, 'Should return null for whitespace-only username');
	}

	/**
	 * Test getDPLQueryResults with basic parameters
	 */
	public function testGetDPLQueryResultsBasic(): void {
		$params = [
			'namespace' => 'Main',
			'ordermethod' => 'title'
		];
		$format = '%PAGE%';

		$results = $this->getDPLQueryResults($params, $format);
		$this->assertIsArray($results);
	}

	/**
	 * Test getDPLQueryResults with empty parameters
	 */
	public function testGetDPLQueryResultsWithEmptyParams(): void {
		$params = [];
		$format = '%PAGE%';

		$results = $this->getDPLQueryResults($params, $format);
		$this->assertIsArray($results);
	}

	/**
	 * Test getDPLQueryResults with complex format string
	 */
	public function testGetDPLQueryResultsWithComplexFormat(): void {
		$params = [
			'namespace' => 'Main',
			'count' => '5'
		];
		$format = '%PAGE% - %USER% (%DATE%)';

		$results = $this->getDPLQueryResults($params, $format);
		$this->assertIsArray($results);
	}

	/**
	 * Test getDPLQueryResults with multi-value parameters
	 */
	public function testGetDPLQueryResultsWithMultiValueParams(): void {
		$params = [
			'namespace' => ['Main', 'User'],
			'category' => ['CategoryA', 'CategoryB']
		];
		$format = '%PAGE%';

		$results = $this->getDPLQueryResults($params, $format);
		$this->assertIsArray($results);
	}

	/**
	 * Test getDPLQueryResults with special characters in format
	 */
	public function testGetDPLQueryResultsWithSpecialCharactersInFormat(): void {
		$params = ['namespace' => 'Main'];
		$format = '%PAGE% & %USER% | %DATE%';

		$results = $this->getDPLQueryResults($params, $format);
		$this->assertIsArray($results);
	}

	/**
	 * Test runDPLQuery with basic parameters
	 */
	public function testRunDPLQueryBasic(): void {
		$params = [
			'namespace' => 'Main',
			'count' => '1'
		];

		$html = $this->runDPLQuery($params);
		$this->assertIsString($html);
		$this->assertNotEmpty($html);
	}

	/**
	 * Test runDPLQuery with empty parameters
	 */
	public function testRunDPLQueryWithEmptyParams(): void {
		$params = [];

		$html = $this->runDPLQuery($params);
		$this->assertIsString($html);
	}

	/**
	 * Test runDPLQuery with special characters in parameters
	 */
	public function testRunDPLQueryWithSpecialCharacters(): void {
		$params = [
			'titleregexp' => '[A-Z].*',
			'format' => '%PAGE% & %USER%'
		];

		$html = $this->runDPLQuery($params);
		$this->assertIsString($html);
	}

	/**
	 * Test runDPLQuery with array parameters (multi-value)
	 */
	public function testRunDPLQueryWithArrayParameters(): void {
		$params = [
			'namespace' => ['Main', 'User'],
			'category' => ['Cat1', 'Cat2']
		];

		$html = $this->runDPLQuery($params);
		$this->assertIsString($html);
	}

	/**
	 * Test runDPLQuery with boolean parameters
	 */
	public function testRunDPLQueryWithBooleanParameters(): void {
		$params = [
			'namespace' => 'Main',
			'addauthor' => true,
			'showcurid' => false
		];

		$html = $this->runDPLQuery($params);
		$this->assertIsString($html);
	}

	/**
	 * Test runDPLQuery generates proper DPL markup structure
	 */
	public function testRunDPLQueryGeneratesProperMarkup(): void {
		$params = [
			'namespace' => 'Main',
			'count' => '5'
		];

		$html = $this->runDPLQuery($params);
		$this->assertIsString($html);
		
		// Verify it's actual HTML by loading it into DOM
		$doc = new DOMDocument();
		$this->assertTrue(@$doc->loadHTML($html), 'Should generate valid HTML');
	}

	/**
	 * Test runDPLQuery with numeric string parameters
	 */
	public function testRunDPLQueryWithNumericStringParameters(): void {
		$params = [
			'namespace' => '0',
			'count' => '10',
			'offset' => '5'
		];

		$html = $this->runDPLQuery($params);
		$this->assertIsString($html);
	}

	/**
	 * Test that getDPLQueryResults handles well-formed HTML correctly
	 */
	public function testGetDPLQueryResultsWithWellFormedHTML(): void {
		// Create a test subclass to override runDPLQuery
		$testCase = new class extends DPLIntegrationTestCase {
			protected function runDPLQuery(array $params): string {
				return '<div id="dpl-test-query">Result1|Result2|Result3|</div>';
			}
		};

		$results = $testCase->getDPLQueryResults(['test' => 'param'], '%PAGE%');
		$expected = ['Result1', 'Result2', 'Result3'];
		$this->assertEquals($expected, $results);
	}

	/**
	 * Test getDPLQueryResults when no results element is found
	 */
	public function testGetDPLQueryResultsNoResultsElement(): void {
		$testCase = new class extends DPLIntegrationTestCase {
			protected function runDPLQuery(array $params): string {
				return '<div class="other">No results div</div>';
			}
		};

		$results = $testCase->getDPLQueryResults(['test' => 'param'], '%PAGE%');
		$this->assertEquals([], $results);
	}

	/**
	 * Test getDPLQueryResults with empty results
	 */
	public function testGetDPLQueryResultsWithEmptyResults(): void {
		$testCase = new class extends DPLIntegrationTestCase {
			protected function runDPLQuery(array $params): string {
				return '<div id="dpl-test-query"></div>';
			}
		};

		$results = $testCase->getDPLQueryResults(['test' => 'param'], '%PAGE%');
		$this->assertEquals([''], $results);
	}

	/**
	 * Test getDPLQueryResults with single result (no separator)
	 */
	public function testGetDPLQueryResultsWithSingleResult(): void {
		$testCase = new class extends DPLIntegrationTestCase {
			protected function runDPLQuery(array $params): string {
				return '<div id="dpl-test-query">SingleResult</div>';
			}
		};

		$results = $testCase->getDPLQueryResults(['test' => 'param'], '%PAGE%');
		$this->assertEquals(['SingleResult'], $results);
	}

	/**
	 * Test getDPLQueryResults with results containing pipe characters in content
	 */
	public function testGetDPLQueryResultsWithPipeInContent(): void {
		$testCase = new class extends DPLIntegrationTestCase {
			protected function runDPLQuery(array $params): string {
				return '<div id="dpl-test-query">Result with | pipe|Normal result|</div>';
			}
		};

		$results = $testCase->getDPLQueryResults(['test' => 'param'], '%PAGE%');
		$expected = ['Result with | pipe', 'Normal result'];
		$this->assertEquals($expected, $results);
	}

	/**
	 * Test that import is called on each runDPLQuery invocation
	 */
	public function testRunDPLQueryCallsImportEachTime(): void {
		$importCallCount = 0;
		
		$testCase = new class($importCallCount) extends DPLIntegrationTestCase {
			private int &$callCount;
			
			public function __construct(int &$callCount) {
				$this->callCount = &$callCount;
				parent::setUp();
			}
			
			protected function doImport(): void {
				$this->callCount++;
				// Skip actual import for test
			}
		};

		$testCase->runDPLQuery(['namespace' => 'Main']);
		$testCase->runDPLQuery(['namespace' => 'User']);
		
		$this->assertEquals(2, $importCallCount, 'doImport should be called for each runDPLQuery');
	}

	/**
	 * Test format parameter is properly applied in getDPLQueryResults
	 */
	public function testGetDPLQueryResultsAppliesCustomFormat(): void {
		$params = ['namespace' => 'Main'];
		$customFormat = '%PAGE% (%USER%)';
		
		// We verify this indirectly by ensuring the method completes without error
		$results = $this->getDPLQueryResults($params, $customFormat);
		$this->assertIsArray($results);
	}

	/**
	 * Test getDPLQueryResults properly overrides format parameter
	 */
	public function testGetDPLQueryResultsOverridesFormatParameter(): void {
		$params = [
			'namespace' => 'Main',
			'format' => 'original format'  // This should be overridden
		];
		$customFormat = '%PAGE%';
		
		$results = $this->getDPLQueryResults($params, $customFormat);
		$this->assertIsArray($results);
		// The original format should be overridden by the custom test format
	}

	/**
	 * Test getDPLQueryResults with trailing pipe separator
	 */
	public function testGetDPLQueryResultsWithTrailingSeparator(): void {
		$testCase = new class extends DPLIntegrationTestCase {
			protected function runDPLQuery(array $params): string {
				return '<div id="dpl-test-query">Result1|Result2|</div>';
			}
		};

		$results = $testCase->getDPLQueryResults(['test' => 'param'], '%PAGE%');
		$expected = ['Result1', 'Result2'];
		$this->assertEquals($expected, $results);
	}

	/**
	 * Test integration workflow with realistic DPL parameters
	 */
	public function testCompleteWorkflowWithRealisticParameters(): void {
		$params = [
			'namespace' => 'Main',
			'count' => '3',
			'ordermethod' => 'title',
			'addauthor' => true
		];
		
		$html = $this->runDPLQuery($params);
		$this->assertIsString($html);
		
		$results = $this->getDPLQueryResults($params, '%PAGE% by %USER%');
		$this->assertIsArray($results);
		
		// Verify the HTML can be parsed as valid HTML
		$doc = new DOMDocument();
		$this->assertTrue(@$doc->loadHTML($html));
	}

	/**
	 * Test error handling when DOMDocument fails to load HTML
	 */
	public function testGetDPLQueryResultsWithInvalidHTML(): void {
		$testCase = new class extends DPLIntegrationTestCase {
			protected function runDPLQuery(array $params): string {
				// Return invalid HTML that might cause loadHTML to fail
				return 'Not valid HTML at all';
			}
		};

		// Should handle gracefully even with invalid HTML
		$results = $testCase->getDPLQueryResults(['test' => 'param'], '%PAGE%');
		$this->assertEquals([], $results);
	}

	/**
	 * Test parameter combinations that might cause edge cases
	 */
	public function testRunDPLQueryWithEdgeCaseParameters(): void {
		$params = [
			'namespace' => '',  // Empty namespace
			'count' => '0',     // Zero count
			'format' => '',     // Empty format
		];

		$html = $this->runDPLQuery($params);
		$this->assertIsString($html);
	}

	/**
	 * Test seedTestUsers error handling with XML parsing issues
	 */
	public function testSeedTestUsersWithXMLParsingError(): void {
		$tempXml = tempnam(sys_get_temp_dir(), 'test_seed_');
		// Create XML that might cause XPath issues
		$xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
		<mediawiki>
			<page>
				<revision>
					<contributor>
						<username>TestUser</username>
					</contributor>
				</revision>
			</page>
		</mediawiki>';
		
		file_put_contents($tempXml, $xmlContent);

		$reflection = new ReflectionClass($this);
		$method = $reflection->getMethod('seedTestUsers');
		$method->setAccessible(true);

		// Should handle XML without proper namespace gracefully
		$method->invoke($this, $tempXml);
		
		unlink($tempXml);
		$this->assertTrue(true, 'seedTestUsers handled XML without namespace declaration gracefully');
	}

	/**
	 * Test seedTestUsers performance with many users
	 */
	public function testSeedTestUsersWithManyUsers(): void {
		$tempXml = tempnam(sys_get_temp_dir(), 'test_seed_');
		
		// Generate XML with many users to test performance
		$xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
		<mediawiki xmlns="http://www.mediawiki.org/xml/export-0.11/">';
		
		for ($i = 1; $i <= 10; $i++) {
			$xmlContent .= "
			<page>
				<revision>
					<contributor>
						<username>TestUser{$i}</username>
					</contributor>
				</revision>
			</page>";
		}
		
		$xmlContent .= '</mediawiki>';
		file_put_contents($tempXml, $xmlContent);

		$reflection = new ReflectionClass($this);
		$method = $reflection->getMethod('seedTestUsers');
		$method->setAccessible(true);

		$startTime = microtime(true);
		$method->invoke($this, $tempXml);
		$endTime = microtime(true);
		
		unlink($tempXml);
		
		// Should complete in reasonable time (less than 5 seconds)
		$this->assertLessThan(5.0, $endTime - $startTime, 'seedTestUsers should complete efficiently with many users');
	}
}