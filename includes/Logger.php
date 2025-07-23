<?php

namespace MediaWiki\Extension\DynamicPageList4;

use MediaWiki\Html\Html;

class Logger {

	/** Buffer of debug messages. */
	private array $buffer = [];

	public function addMessage( int $errorId, string ...$args ): void {
		$errorLevel = (int)floor( $errorId / 1000 );
		$errorMessageId = $errorId % 1000;

		if ( Utils::getDebugLevel() < $errorLevel ) {
			return;
		}

		$text = null;
		if ( Utils::isLikeIntersection() ) {
			$text = match ( $errorId ) {
				Constants::FATAL_TOOMANYCATS => wfMessage( 'intersection_toomanycats', $args )->text(),
				Constants::FATAL_TOOFEWCATS => wfMessage( 'intersection_toofewcats', $args )->text(),
				Constants::WARN_NORESULTS => wfMessage( 'intersection_noresults', $args )->text(),
				Constants::FATAL_NOSELECTION => wfMessage( 'intersection_noincludecats', $args )->text(),
				Constants::FATAL_POOLCOUNTER => wfMessage( 'intersection_pcerror', $args )->text(),
				default => null,
			};
		}

		$text ??= wfMessage( "dpl_log_$errorMessageId", $args )->text();

		$version = Utils::getVersion();
		$this->buffer[] = Html::element( 'p', [],
			"Extension:DynamicPageList4 (DPL4), version $version: $text"
		);
	}

	public function getMessages( bool $clearBuffer ): array {
		$buffer = $this->buffer;
		if ( $clearBuffer ) {
			$this->buffer = [];
		}

		return $buffer;
	}
}
