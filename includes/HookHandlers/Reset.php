<?php

namespace MediaWiki\Extension\DynamicPageList4\HookHandlers;

use MediaWiki\Extension\DynamicPageList4\Utils;
use MediaWiki\Hook\ParserAfterTidyHook;
use ReflectionProperty;

class Reset implements ParserAfterTidyHook {

	/**
	 * End reset
	 * Reset everything; some categories may have been fixed, however via fixcategory=
	 *
	 * @inheritDoc
	 * @param string &$text @phan-unused-param
	 */
	public function onParserAfterTidy( $parser, &$text ) {
		if ( Utils::$createdLinks['resetdone'] ) {
			return;
		}

		Utils::$createdLinks['resetdone'] = true;
		$output = $parser->getOutput();

		foreach ( $output->getCategoryNames() as $key ) {
			if ( !isset( Utils::$fixedCategories[$key] ) ) {
				continue;
			}

			Utils::$fixedCategories[$key] = $output->getCategorySortKey( $key );
		}

		if ( Utils::$createdLinks['resetLinks'] ) {
			$this->setParserOutputProperty( $output, 'mLinks', [] );
		}

		if ( Utils::$createdLinks['resetCategories'] ) {
			$output->setCategories( Utils::$fixedCategories );
		}

		if ( Utils::$createdLinks['resetTemplates'] ) {
			$this->setParserOutputProperty( $output, 'mTemplates', [] );
		}

		if ( Utils::$createdLinks['resetImages'] ) {
			$this->setParserOutputProperty( $output, 'mImages', [] );
		}
		var_dump( Utils::$createdLinks['resetCategories'] );

		Utils::$fixedCategories = [];
	}

	/**
	 * Set private/protected property on an object via reflection.
	 * This is a very messy hack but we have to since ParserOutput
	 * doesn't have any set* method for media or templates and
	 * the properties are private.
	 */
	private function setParserOutputProperty( object $object, string $property, mixed $value ): void {
		$refProp = new ReflectionProperty( $object, $property );
		$refProp->setAccessible( true );
		$refProp->setValue( $object, $value );
	}
}
