<?php

namespace MediaWiki\Extension\DynamicPageList3;

class Variables {
	/**
	 * Memory storage for variables.
	 *
	 * @var array
	 */
	public static $memoryVar = [];

	/**
	 * Memory storage for arrays of variables.
	 *
	 * @var array
	 */
	public static $memoryArray = [];

	/**
	 * Expects pairs of 'variable name' and 'value'
	 * if the first parameter is empty it will be ignored {{#vardefine:|a|b}} is the same as {{#vardefine:a|b}}
	 *
	 * @param array $arg
	 * @return string
	 */
	public static function setVar( $arg ) {
		$numargs = count( $arg );

		if ( $numargs >= 3 && $arg[2] == '' ) {
			$start = 3;
		} else {
			$start = 2;
		}

		for ( $i = $start; $i < $numargs; $i++ ) {
			$var = $arg[$i];

			if ( ++$i <= $numargs - 1 ) {
				self::$memoryVar[$var] = $arg[$i];
			} else {
				self::$memoryVar[$var] = '';
			}
		}

		return '';
	}

	/**
	 * @param array $arg
	 * @return string
	 */
	public static function setVarDefault( $arg ) {
		$numargs = count( $arg );

		if ( $numargs > 3 ) {
			$value = $arg[3];
		} else {
			return '';
		}

		$var = $arg[2];

		if ( !array_key_exists( $var, self::$memoryVar ) || self::$memoryVar[$var] == '' ) {
			self::$memoryVar[$var] = $value;
		}

		return '';
	}

	/**
	 * @param string $var
	 * @return string
	 */
	public static function getVar( $var ) {
		if ( array_key_exists( $var, self::$memoryVar ) ) {
			return self::$memoryVar[$var];
		}

		return '';
	}

	/**
	 * @param array $arg
	 * @return string
	 */
	public static function setArray( $arg ) {
		$numargs = count( $arg );

		if ( $numargs < 5 ) {
			return '';
		}

		$var = trim( $arg[2] );
		$value = $arg[3];
		$delimiter = $arg[4];

		if ( $var == '' ) {
			return '';
		}

		if ( $value == '' ) {
			self::$memoryArray[$var] = [];
			return '';
		}

		if ( $delimiter == '' ) {
			self::$memoryArray[$var] = [
				$value
			];

			return '';
		}

		if ( strpos( $delimiter, '/' ) !== 0 || ( strlen( $delimiter ) - 1 ) !== strrpos( $delimiter, '/' ) ) {
			$delimiter = '/\s*' . $delimiter . '\s*/';
		}

		self::$memoryArray[$var] = preg_split( $delimiter, $value );

		return "value={$value}, delimiter={$delimiter}," . count( self::$memoryArray[$var] );
	}

	/**
	 * @param array $arg
	 * @return string
	 */
	public static function dumpArray( $arg ) {
		$numargs = count( $arg );

		if ( $numargs < 3 ) {
			return '';
		}

		$var = trim( $arg[2] );
		$text = " array {$var} = {";
		$n = 0;

		if ( array_key_exists( $var, self::$memoryArray ) ) {
			foreach ( self::$memoryArray[$var] as $value ) {
				if ( $n++ > 0 ) {
					$text .= ', ';
				}

				$text .= "{$value}";
			}
		}

		return $text . "}\n";
	}

	/**
	 * @param string $var
	 * @param string $delimiter
	 * @param string $search
	 * @param string $subject
	 * @return array|string
	 */
	public static function printArray( $var, $delimiter, $search, $subject ) {
		$var = trim( $var );

		if ( $var == '' ) {
			return '';
		}

		if ( !array_key_exists( $var, self::$memoryArray ) ) {
			return '';
		}

		$values = self::$memoryArray[$var];
		$rendered_values = [];

		foreach ( $values as $v ) {
			$temp_result_value = str_replace( $search, $v, $subject );
			$rendered_values[] = $temp_result_value;
		}

		return [
			// @phan-suppress-next-line PhanPluginMixedKeyNoKey
			implode( $delimiter, $rendered_values ),
			'noparse' => false,
			'isHTML' => false
		];
	}
}
