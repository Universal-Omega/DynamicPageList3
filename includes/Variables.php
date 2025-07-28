<?php

namespace MediaWiki\Extension\DynamicPageList4;

use function count;

class Variables {

	/** Memory storage for variables. */
	private static array $memoryVar = [];

	/**
	 * Expects pairs of 'variable name' and 'value'
	 * if the first parameter is empty it will be ignored {{#vardefine:|a|b}} is the same as {{#vardefine:a|b}}
	 */
	public static function setVar( array $arg ): string {
		$numargs = count( $arg );
		$start = ( $numargs >= 3 && $arg[2] === '' ) ? 3 : 2;
		for ( $i = $start; $i < $numargs; $i++ ) {
			$var = $arg[$i];
			if ( ++$i < $numargs ) {
				self::$memoryVar[$var] = $arg[$i];
				continue;
			}

			self::$memoryVar[$var] = '';
		}

		return '';
	}

	public static function setVarDefault( array $arg ): string {
		if ( count( $arg ) <= 3 ) {
			return '';
		}

		$var = $arg[2];
		$value = $arg[3];
		if ( !isset( self::$memoryVar[$var] ) || self::$memoryVar[$var] === '' ) {
			self::$memoryVar[$var] = $value;
		}

		return '';
	}

	public static function getVar( string $var ): string {
		return self::$memoryVar[$var] ?? '';
	}
}
