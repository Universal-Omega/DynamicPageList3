<?php

namespace MediaWiki\Extension\DynamicPageList4;

use MediaWiki\Registration\ExtensionRegistry;

class Utils {

    private static bool $likeIntersection = false;
    private static int $debugLevel = 0;

	public static function getVersion(): string {
		static $version;
		$version ??= ExtensionRegistry::getInstance()->getAllThings()['DynamicPageList3']['version'];
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

	private static function setLikeIntersection( bool $mode ): void {
		self::$likeIntersection = $mode;
	}
}
