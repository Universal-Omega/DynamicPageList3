<?php

namespace MediaWiki\Extension\DynamicPageList3\Tests;
use MediaWiki\Extension\DynamicPageList3\ExternalDomainPatternParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @group DynamicPageList3
 */
class DPLExternalDomainPatternParserTest extends TestCase {
	use ExternalDomainPatternParser;

	#[DataProvider( 'getDomainPattern' )]
	public function testParseDomainPattern( string $domain, string $expected ): void {
		$actual = $this->parseDomainPattern( $domain );
		$this->assertSame( $expected, $actual );
	}

	public static function getDomainPattern(): array {
		return [
			// Full domain with extra path and without any wildcards
			[ 'http://www.fandom.com/test123/test?test=%', 'http://com.fandom.www.' ],
			// Protocol is preserved if specified (only protocols separated by `://` are supported)
			[ 'irc://starwars.%/test123/test', 'irc://%.starwars.' ],
			[ 'https://starwars.%/test123/test', 'https://%.starwars.' ],
			// Domain with `%` at the end
			[ 'http://starwars.%/test123/test?test=%', 'http://%.starwars.' ],
			// Domain with `%` at the begging. We have to guess the protocol
			[ '%.fandom.com/test123/test?test=%', '%://com.fandom.%.' ],
			// Domain with wildcard at the begging without separation
			[ '%fandom.com/test123/test?test=%', '%://com.%fandom%.' ],
			// Domain with wildcard in the middle, separated by `.`
			[ 'www.%.com/test123/test?test=%', '%://com.%.www.' ],
			// Domain with wildcard at the begging separated by `.` from one side
			[ 'www.%fandom.com', '%://com.%fandom%.www.' ],
			// Domain with wildcard at the begging separated by `.` from the other side
			[ 'www.fandom%.com', '%://com.%fandom%.www.' ],
			// Duplicated wildcard doesn't matter
			[ 'www.%%fandom.com', '%://com.%%fandom%.www.' ],
		];
	}

	/**
	 * This test documents cases that are not correctly supported
	 */
	#[DataProvider( 'getUnsupportedDomainPattern' )]
	public function testUnsupportedDomainPatterns( string $domain, string $expected ): void {
		$actual = $this->parseDomainPattern( $domain );
		$this->assertSame( $expected, $actual );
	}

	public static function getUnsupportedDomainPattern(): array {
		return [
			// We are not supporting `_` as a `.`
			[ 'http://www.fandom_com', 'http://fandom_com.www.' ],
			// Domain with wildcard in the middle not followed by `.` is not processed
			[ 'ww%fandom.com', '%://com.ww%fandom.' ],
			[ '%www%fandom.com', '%://com.%www%fandom%.' ],
			// When wildcard should cover `/` we would generate garbage
			[ '%fandom.%?test=%', '%://%?test=%%.%fandom%.' ],
		];
	}
}
