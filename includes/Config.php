<?php

namespace MediaWiki\Extension\DynamicPageList4;

use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\MultiConfig;

class Config extends MultiConfig {

	private static ?self $instance = null;

	private function __construct() {
		$globalConfig = new GlobalVarConfig();
		$dplSettings = $globalConfig->has( 'DplSettings' )
			? $globalConfig->get( 'DplSettings' )
			: [];

		parent::__construct( [
			new HashConfig( $dplSettings ),
			$globalConfig,
		] );
	}

	public static function getInstance(): self {
		self::$instance ??= new self();
		return self::$instance;
	}
}
