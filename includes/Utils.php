<?php

namespace MediaWiki\Extension\DynamicPageList4;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Registration\ExtensionRegistry;
use Psr\Log\LoggerInterface;
use function ctype_alnum;
use function preg_replace;
use function strlen;

class Utils {

	private static bool $likeIntersection = false;
	private static int $debugLevel = 0;

	/** @phan-var array<mixed,mixed> */
	public static array $createdLinks = [
		'resetLinks' => false,
		'resetTemplates' => false,
		'resetCategories' => false,
		'resetImages' => false,
		'resetdone' => false,
		'elimdone' => false,
	];

	public static array $fixedCategories = [];

	public static function fixCategory( string $cat ): void {
		if ( $cat !== '' ) {
			self::$fixedCategories[$cat] = 1;
		}
	}

	public static function getLogger(): LoggerInterface {
		return LoggerFactory::getInstance( 'DynamicPageList4' );
	}

	public static function getVersion(): string {
		static $version;
		$version ??= ExtensionRegistry::getInstance()->getAllThings()['DynamicPageList4']['version'];
		return $version;
	}

	public static function getDebugLevel(): int {
		return self::$debugLevel;
	}

	public static function setDebugLevel( int $level ): void {
		self::$debugLevel = $level;
	}

	public static function isLikeIntersection(): bool {
		return self::$likeIntersection;
	}

	public static function setLikeIntersection( bool $mode ): void {
		self::$likeIntersection = $mode;
	}

	public static function isRegexp( string $needle ): bool {
		if ( strlen( $needle ) < 3 ) {
			return false;
		}

		if ( ctype_alnum( $needle[0] ) ) {
			return false;
		}

		$nettoNeedle = preg_replace( '/[ismu]*$/', '', $needle );
		if ( strlen( $nettoNeedle ) < 2 ) {
			return false;
		}

		return $needle[0] === $nettoNeedle[-1];
	}
}
