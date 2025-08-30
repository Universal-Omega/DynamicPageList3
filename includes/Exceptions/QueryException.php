<?php

namespace MediaWiki\Extension\DynamicPageList4\Exceptions;

use FatalError;

/** Exceptions for failures related to DynamicPageList4 queries. */
class QueryException extends FatalError {

	/**
	 * We don't want the default exception logging as we got our own logging.
	 * @inheritDoc
	 */
    public function isLoggable(): false {
		return false;
	}
}
