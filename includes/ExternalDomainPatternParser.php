<?php

namespace MediaWiki\Extension\DynamicPageList3;

use MediaWiki\Extension\DynamicPageList3\Tests\DPLExternalDomainPatternParserTest;

trait ExternalDomainPatternParser {
	/**
	 * We provide:
	 * * full support for "standalone" wildcard usage (eg. `%.fandom.com`)
	 * * partial support for wildcard usage when it is not separated by `.` (eg. `%fandom.com would match starwars.fandom-suffix.com)
	 * * protocols followed by the `://` are supported, like `http://` or `https://` (`mailto:` on the other hand is not supported)
	 *
	 * @See DPLExternalDomainPatternParserTest for example cases
	 */
	private function parseDomainPattern( string $pattern ): string {
		$protocol = false;
		// Protocol is specified. Strip it
		if ( str_contains( $pattern, '://' ) ) {
			[$protocol, $pattern] = explode( '://', $pattern );
		}

		// Previous step will strip protocol if it was specified
		[$domainPattern, ] = explode( '/', $pattern, 2 );
		$parts = explode( '.', $domainPattern );
		$reversed = array_reverse( $parts );
		foreach ( $reversed as &$part ) {
			if ( $part === '%' ) {
				continue;
			}
			if ( str_starts_with( $part, '%' ) ) {
				$part .= '%';
			} else if ( str_ends_with( $part, '%' ) ) {
				$part = '%' . $part;
			}
		}

		$rawPattern = implode( '.', $reversed );
		if ( $protocol ) {
			return "$protocol://$rawPattern.";
		}
		return "%://$rawPattern.";
	}
}
