<?php

namespace MediaWiki\Extension\DynamicPageList3;

use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\MultiConfig;

class Config extends MultiConfig {

	private static ?self $instance = null;

	public function __construct() {
		$globalConfig = new GlobalVarConfig();

		$dplSettings = $globalConfig->has( 'DplSettings' )
			? $globalConfig->get( 'DplSettings' )
			: [];

		parent::__construct( [
			new HashConfig( $dplSettings ),
			$globalConfig
		] );
	}

	public static function getInstance(): self {
		return self::$instance ??= new self();
	}

	public static function getSetting( string $setting ): mixed {
		$config = self::getInstance();
		return $config->get( $setting );
	}
}
