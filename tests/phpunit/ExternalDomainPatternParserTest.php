<?php

namespace MediaWiki\Extension\DynamicPageList4\Tests;

use Generator;
use MediaWiki\ExternalLinks\LinkFilter;
use MediaWikiIntegrationTestCase;
use function str_replace;

/**
 * @coversNothing
 * @group DynamicPageList4
 */
class ExternalDomainPatternParserTest extends MediaWikiIntegrationTestCase {

	/**
	 * This test documents cases which are correctly supported
	 * @dataProvider provideDomainPattern
	 */
	public function testParseDomainPattern( string $domain, string $expected ): void {
		$actual = str_replace( '%25', '%', LinkFilter::makeIndexes( $domain )[0] );
		$this->assertSame( $expected, $actual );
	}

	public static function provideDomainPattern(): Generator {
		yield 'full domain no wildcards' => [
			// Full domain with extra path and without any wildcards
			'http://www.fandom.com/test123/test?test=%', 'http://com.fandom.www.',
		];

		yield 'protocol preserved irc' => [
			// Protocol is preserved if specified (only protocols separated by `://` are supported)
			'irc://starwars.%/test123/test', 'irc://%.starwars.',
		];

		yield 'protocol preserved https' => [
			'https://starwars.%/test123/test', 'https://%.starwars.',
		];

		yield 'percent at end' => [
			// Domain with `%` at the end
			'http://starwars.%/test123/test?test=%', 'http://%.starwars.',
		];

		yield 'percent at beginning guessing protocol' => [
			// Domain with `%` at the beginning. We have to guess the protocol
			'%.fandom.com/test123/test?test=%', '%://com.fandom.%.',
		];

		yield 'wildcard at beginning no separator' => [
			// Domain with wildcard at the beginning without separation
			'%fandom.com/test123/test?test=%', '%://com.%fandom%.',
		];

		yield 'wildcard in middle separated by dot' => [
			// Domain with wildcard in the middle, separated by `.`
			'www.%.com/test123/test?test=%', '%://com.%.www.',
		];

		yield 'wildcard beginning separated by dot one side' => [
			// Domain with wildcard at the beginning separated by `.` from one side
			'www.%fandom.com', '%://com.%fandom%.www.',
		];

		yield 'wildcard beginning separated by dot other side' => [
			// Domain with wildcard at the beginning separated by `.` from the other side
			'www.fandom%.com', '%://com.%fandom%.www.',
		];

		yield 'duplicated wildcard' => [
			// Duplicated wildcard doesn't matter
			'www.%%fandom.com', '%://com.%%fandom%.www.',
		];
	}

	/**
	 * This test documents cases that are NOT correctly supported
	 * @dataProvider provideUnsupportedDomainPattern
	 */
	public function testUnsupportedDomainPatterns( string $domain, string $expected ): void {
		$actual = str_replace( '%25', '%', LinkFilter::makeIndexes( $domain )[0] );
		$this->assertSame( $expected, $actual );
	}

	public static function provideUnsupportedDomainPattern(): Generator {
		yield 'underscore not dot' => [
			// We are not supporting `_` as a `.`
			'http://www.fandom_com', 'http://fandom_com.www.',
		];

		yield 'wildcard middle no dot after' => [
			// Domain with wildcard in the middle not followed by `.` is not processed
			'ww%fandom.com', '%://com.ww%fandom.',
		];

		yield 'multiple wildcards middle no dot' => [
			'%www%fandom.com', '%://com.%www%fandom%.',
		];

		yield 'wildcard covering slash leads to garbage' => [
			// When wildcard should cover `/` we would generate garbage
			'%fandom.%?test=%', '%://%?test=%%.%fandom%.',
		];
	}
}
