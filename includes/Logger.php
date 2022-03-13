<?php

namespace MediaWiki\Extension\DynamicPageList3;

class Logger {
	/**
	 * Buffer of debug messages.
	 *
	 * @var array
	 */
	private $buffer = [];

	public function addMessage() {
		$args = func_get_args();
		$args = array_map( 'htmlspecialchars', $args );

		call_user_func_array( [ $this, 'msg' ], $args );
	}

	/**
	 * Return the buffer of messages.
	 *
	 * @param bool $clearBuffer
	 * @return array
	 */
	public function getMessages( $clearBuffer = true ) {
		$buffer = $this->buffer;

		if ( $clearBuffer === true ) {
			$this->buffer = [];
		}

		return $buffer;
	}

	/**
	 * Get a message, with optional parameters
	 * Parameters from user input must be escaped for HTML *before* passing to this function
	 */
	public function msg() {
		$args = func_get_args();
		$errorId = array_shift( $args );
		$errorLevel = floor( $errorId / 1000 );
		$errorMessageId = $errorId % 1000;

		if ( Hooks::getDebugLevel() >= $errorLevel ) {
			if ( Hooks::isLikeIntersection() ) {
				if ( $errorId == Hooks::FATAL_TOOMANYCATS ) {
					$text = wfMessage( 'intersection_toomanycats', $args )->text();
				} elseif ( $errorId == Hooks::FATAL_TOOFEWCATS ) {
					$text = wfMessage( 'intersection_toofewcats', $args )->text();
				} elseif ( $errorId == Hooks::WARN_NORESULTS ) {
					$text = wfMessage( 'intersection_noresults', $args )->text();
				} elseif ( $errorId == Hooks::FATAL_NOSELECTION ) {
					$text = wfMessage( 'intersection_noincludecats', $args )->text();
				} elseif ( $errorId == Hooks::FATAL_POOLCOUNTER ) {
					$text = wfMessage( 'intersection_pcerror', $args )->text();
				}
			}

			if ( empty( $text ) ) {
				$text = wfMessage( 'dpl_log_' . $errorMessageId, $args )->text();
			}

			$this->buffer[] = '<p>Extension:DynamicPageList3 (DPL3), version ' . Hooks::getVersion() . ': ' . $text . '</p>';
		}
	}
}
