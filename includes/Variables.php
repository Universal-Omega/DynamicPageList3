<?php

namespace MediaWiki\Extension\DynamicPageList4;

class Variables {

	/** Memory storage for variables. */
	private static array $memoryVar = [];

	/** Memory storage for arrays of variables. */
	private static array $memoryArray = [];

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

	public static function setArray( array $arg ): string {
		if ( count( $arg ) < 5 ) {
			return '';
		}

		$var = trim( $arg[2] );
		$value = $arg[3];
		$delimiter = $arg[4];

		if ( $var === '' ) {
			return '';
		}

		if ( $value === '' ) {
			self::$memoryArray[$var] = [];
			return '';
		}

		if ( $delimiter === '' ) {
			self::$memoryArray[$var] = [ $value ];
			return '';
		}

		if ( !str_starts_with( $delimiter, '/' ) || !str_ends_with( $delimiter, '/' ) ) {
			$delimiter = '/\s*' . $delimiter . '\s*/';
		}

		self::$memoryArray[$var] = preg_split( $delimiter, $value );
		return "value=$value, delimiter=$delimiter," . count( self::$memoryArray[$var] );
	}

	public static function dumpArray( array $arg ): string {
		if ( count( $arg ) < 3 ) {
			return '';
		}

		$var = trim( $arg[2] );
		$text = " array $var = {";

		if ( isset( self::$memoryArray[$var] ) ) {
			$text .= implode( ', ', self::$memoryArray[$var] );
		}

		return "$text}\n";
	}

	public static function printArray(
		string $var,
		string $delimiter,
		string $search,
		string $subject
	): array|string {
		$var = trim( $var );
		if ( $var === '' || !isset( self::$memoryArray[$var] ) ) {
			return '';
		}

		$values = self::$memoryArray[$var];
		$rendered_values = [];
		foreach ( $values as $v ) {
			$rendered_values[] = str_replace( $search, $v, $subject );
		}

		return [
			// @phan-suppress-next-line PhanPluginMixedKeyNoKey
			implode( $delimiter, $rendered_values ),
			'noparse' => false,
			'isHTML' => false,
		];
	}
}
