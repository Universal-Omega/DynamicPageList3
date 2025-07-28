<?php

namespace MediaWiki\Extension\DynamicPageList4\Tests;

use MediaWiki\Extension\DynamicPageList4\Config;
use MediaWikiIntegrationTestCase;
use function microtime;

/**
 * @group DynamicPageList4
 * @covers \MediaWiki\Extension\DynamicPageList4\Config
 */
class ConfigTest extends MediaWikiIntegrationTestCase {

	/**
	 * Test config performance characteristics
	 */
	public function testConfigPerformance() {
		$startTime = microtime( true );

		// Multiple singleton calls should be fast
		for ( $i = 0; $i < 1000; $i++ ) {
			Config::getInstance();
		}

		$endTime = microtime( true );
		$duration = $endTime - $startTime;

		// Should be very fast since it's just returning the same instance
		$this->assertLessThan( 0.1, $duration, 'Singleton pattern should be performant' );
	}
}
