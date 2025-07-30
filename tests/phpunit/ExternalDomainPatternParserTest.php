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
		$actual = str_replace( '%25', '%', LinkFilter::makeIndexes( $domain )[0][0] );
		$this->assertSame( $expected, $actual );
	}

	public static function provideDomainPattern(): Generator {
		// Full domain with extra path and without any wildcards
		yield 'full domain no wildcards' => [
			'http://www.example.com/test123/test?test=%',
			'http://com.example.www.',
		];

		// Protocol is preserved if specified (only protocols separated by `://` are supported)
		yield 'protocol preserved irc' => [
			'irc://subdomain.example/%/test123/test',
			'irc://example.subdomain.',
		];

		// Protocol is preserved (https)
		yield 'protocol preserved https' => [
			'https://subdomain.example/%/test123/test',
			'https://example.subdomain.',
		];

		// Domain with `%` at the end
		yield 'percent at end' => [
			'http://subdomain.example/%/test123/test?test=%',
			'http://example.subdomain.',
		];

		// Domain with `%` at the beginning. We have to guess the protocol
		yield 'percent at beginning guessing protocol' => [
			'//%.example.com/test123/test?test=%',
			'https://com.example.%.',
		];

		// Domain with wildcard at the beginning without separation
		yield 'wildcard at beginning no separator' => [
			'//%example.com/test123/test?test=%',
			'https://com.%example%.',
		];

		// Domain with wildcard in the middle, separated by `.`
		yield 'wildcard in middle separated by dot' => [
			'//www.%.com/test123/test?test=%',
			'https://com.%.www.',
		];

		// Domain with wildcard at the beginning separated by `.` from one side
		yield 'wildcard beginning separated by dot one side' => [
			'//www.%example.com',
			'https://com.%example%.www.',
		];

		// Domain with wildcard at the beginning separated by `.` from the other side
		yield 'wildcard beginning separated by dot other side' => [
			'//www.%example%.com',
			'https://com.%example%.www.',
		];

		// Duplicated wildcard doesn't matter
		yield 'duplicated wildcard' => [
			'//www.%%example.com',
			'https://com.%%example.www.',
		];
	}

	/**
	 * This test documents cases that are NOT correctly supported
	 * @dataProvider provideUnsupportedDomainPattern
	 */
	public function testUnsupportedDomainPatterns( string $domain, string $expected ): void {
		$actual = str_replace( '%25', '%', LinkFilter::makeIndexes( $domain )[0][0] );
		$this->assertSame( $expected, $actual );
	}

	public static function provideUnsupportedDomainPattern(): Generator {
		// We are not supporting `_` as a `.`
		yield 'underscore not dot' => [
			'http://www.example_com',
			'http://example_com.www.',
		];

		// Domain with wildcard in the middle not followed by `.` is not processed
		yield 'wildcard middle no dot after' => [
			'//ww%example.com',
			'https://com.ww%example.',
		];

		// Multiple wildcards in domain middle, no dot separation
		yield 'multiple wildcards middle no dot' => [
			'//%www%example.com',
			'https://com.%www%example%.',
		];

		// When wildcard should cover `/` we would generate garbage
		yield 'wildcard covering slash leads to garbage' => [
			'//%example.%?test=%',
			'https://%.%example.',
		];
	}
}
