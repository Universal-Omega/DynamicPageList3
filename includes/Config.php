<?php

namespace DPL;

use MWException;

class Config {
	/**
	 * Configuration Settings
	 *
	 * @var array
	 */
	private static $settings = [];

	/**
	 * Initialize the static object with settings.
	 *
	 * @param array|false $settings
	 */
	public static function init( $settings = false ) {
		if ( $settings === false ) {
			global $wgDplSettings;

			$settings = $wgDplSettings ?? false;
		}

		if ( !is_array( $settings ) ) {
			throw new MWException( __METHOD__ . ": Invalid settings passed." );
		}

		self::$settings = array_merge( self::$settings, $settings );
	}

	/**
	 * Return a single setting.
	 *
	 * @param string $setting
	 * @return mixed|null
	 */
	public static function getSetting( $setting ) {
		return ( self::$settings[$setting] ?? null );
	}

	/**
	 * Return a all settings.
	 *
	 * @return array
	 */
	public static function getAllSettings() {
		return self::$settings;
	}

	/**
	 * Set a single setting.
	 *
	 * @param string $setting
	 * @param mixed|null $value
	 */
	public static function setSetting( $setting, $value = null ) {
		if ( empty( $setting ) || !is_string( $setting ) ) {
			throw new MWException( __METHOD__ . ": Setting keys can not be blank." );
		}

		self::$settings[$setting] = $value;
	}
}
