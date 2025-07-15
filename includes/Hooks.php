<?php

namespace MediaWiki\Extension\DynamicPageList4;

use MediaWiki\Extension\DynamicPageList4\Maintenance\CreateView;
use MediaWiki\Installer\DatabaseUpdater;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;
use StringUtils;

class Hooks {

	/** @phan-var array<mixed,mixed> */
	public static array $createdLinks;
	public static array $fixedCategories = [];

	/**
	 * Sets up this extension's parser functions.
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		self::$createdLinks ??= [
			'resetLinks' => false,
			'resetTemplates' => false,
			'resetCategories' => false,
			'resetImages' => false,
			'resetdone' => false,
			'elimdone' => false,
		];

		// We register the <section> tag in case
		// LabeledSection Extension is not installed so that the
		// section markers are removed.
		if ( Config::getSetting( 'handleSectionTag' ) ) {
			$parser->setHook( 'section', [ self::class, 'dplTag' ] );
		}

		$parser->setHook( 'DPL', [ self::class, 'dplTag' ] );

		// DPL offers the same functionality as Intersection.
		$parser->setHook( 'DynamicPageList', [ self::class, 'intersectionTag' ] );

		$parser->setFunctionHook( 'dpl', [ self::class, 'dplParserFunction' ] );
		$parser->setFunctionHook( 'dplnum', [ self::class, 'dplNumParserFunction' ] );
		$parser->setFunctionHook( 'dplvar', [ self::class, 'dplVarParserFunction' ] );
		$parser->setFunctionHook( 'dplreplace', [ self::class, 'dplReplaceParserFunction' ] );
		$parser->setFunctionHook( 'dplchapter', [ self::class, 'dplChapterParserFunction' ] );
		$parser->setFunctionHook( 'dplmatrix', [ self::class, 'dplMatrixParserFunction' ] );
	}

	/**
	 * Tag <DynamicPageList> entry point.
	 */
	public static function intersectionTag(
		string $input,
		array $args,
		Parser $parser,
		PPFrame $frame
	): string {
		Utils::setLikeIntersection( true );
		$parser->addTrackingCategory( 'dpl-intersection-tracking-category' );

		return self::executeTag( $input, $args, $parser, $frame );
	}

	/**
	 * Tag <dpl> entry point.
	 */
	public static function dplTag(
		string $input,
		array $args,
		Parser $parser,
		PPFrame $frame
	): string {
		Utils::setLikeIntersection( false );
		$parser->addTrackingCategory( 'dpl-tag-tracking-category' );

		return self::executeTag( $input, $args, $parser, $frame );
	}

	/**
	 * The callback function wrapper for converting the input text to HTML output
	 *
	 * @param string $input
	 * @param array $args @phan-unused-param
	 */
	private static function executeTag(
		string $input,
		array $args,
		Parser $parser,
		PPFrame $frame
	): string {
		// entry point for user tag <dpl> or <DynamicPageList>
		// create list and do a recursive parse of the output

		$parse = new Parse();

		if ( Config::getSetting( 'recursiveTagParse' ) ) {
			$input = $parser->recursiveTagParse( $input, $frame );
		}

		$reset = [];
		$eliminate = [];
		$text = $parse->parse( $input, $parser, $reset, $eliminate, true );
		$parserOutput = $parser->getOutput();

		// we can remove the templates by save/restore
		$saveTemplates = ( $reset['templates'] ?? false ) ?
			$parserOutput->mTemplates : null;

		// we can remove the categories by save/restore
		$saveCategories = ( $reset['categories'] ?? false ) ? array_combine(
			$parserOutput->getCategoryNames(),
			array_map(
				static fn ( string $value ): ?string =>
					$parserOutput->getCategorySortKey( $value ),
				$parserOutput->getCategoryNames()
			)
		) : null;

		// we can remove the images by save/restore
		$saveImages = ( $reset['images'] ?? false ) ?
			$parserOutput->mImages : null;

		$parsedDPL = $parser->recursiveTagParse( $text );

		if ( $reset['templates'] ?? false ) {
			$parserOutput->mTemplates = $saveTemplates ?? [];
		}

		if ( $reset['categories'] ?? false ) {
			$parserOutput->setCategories( $saveCategories ?? [] );
		}

		if ( $reset['images'] ?? false ) {
			$parserOutput->mImages = $saveImages ?? [];
		}

		return $parsedDPL;
	}

	/**
	 * The #dpl parser tag entry point.
	 */
	public static function dplParserFunction(
		Parser $parser,
		string ...$args
	): array|string {
		Utils::setLikeIntersection( false );

		$parser->addTrackingCategory( 'dpl-parserfunc-tracking-category' );

		// callback for the parser function {{#dpl: or {{DynamicPageList::
		if ( $args === [] ) {
			$input = '#dpl: no arguments specified';
			return str_replace( '§', '<', '§pre>§nowiki>' . $input . '§/nowiki>§/pre>' );
		}

		$input = implode( "\n", array_map(
			static fn ( string $arg ): string => str_replace( "\n", '', $arg ),
			$args
		) ) . "\n";

		$parse = new Parse();
		$reset = $eliminate = [];
		$dplresult = $parse->parse( $input, $parser, $reset, $eliminate, false );

		return [
			// @phan-suppress-next-line PhanPluginMixedKeyNoKey
			$parser->getPreprocessor()->preprocessToObj( $dplresult, 1 ),
			'isLocalObj' => true,
			'title' => $parser->getPage(),
		];
	}

	/**
	 * The #dplnum parser tag entry point.
	 *
	 * From the old documentation: "Tries to guess a number that is buried in the text.
	 * Uses a set of heuristic rules which may work or not. The idea is to extract the
	 * number so that it can be used as a sorting value in the column of a DPL table output."
	 */
	public static function dplNumParserFunction( Parser $parser, string $text ): string {
		$parser->addTrackingCategory( 'dplnum-parserfunc-tracking-category' );

		$num = str_replace( [ '&#160;', '&nbsp;' ], ' ', $text );
		$num = preg_replace( [
			'/([0-9])([.])([0-9][0-9]?[^0-9,])/',
			'/([0-9.]+),([0-9][0-9][0-9])\s*Mrd/',
			'/([0-9.]+),([0-9][0-9])\s*Mrd/',
			'/([0-9.]+),([0-9])\s*Mrd/',
			'/\s*Mrd/',
			'/([0-9.]+),([0-9][0-9][0-9])\s*Mio/',
			'/([0-9.]+),([0-9][0-9])\s*Mio/',
			'/([0-9.]+),([0-9])\s*Mio/',
			'/\s*Mio/',
			'/[. ]/',
			'/^[^0-9]+/',
			'/[^0-9].*/',
		], [
			'\1,\3',
			'\1\2 000000 ',
			'\1\2 0000000 ',
			'\1\2 00000000 ',
			'000000000 ',
			'\1\2 000 ',
			'\1\2 0000 ',
			'\1\2 00000 ',
			'000000 ',
			'',
			'',
			'',
		], $num );

		return $num ?? '';
	}

	public static function dplVarParserFunction(
		Parser $parser,
		string $cmd,
		mixed ...$args
	): string {
		$parser->addTrackingCategory( 'dplvar-parserfunc-tracking-category' );

		return match ( $cmd ) {
			'set' => Variables::setVar( [ $parser, $cmd, ...$args ] ),
			'default' => Variables::setVarDefault( [ $parser, $cmd, ...$args ] ),
			default => Variables::getVar( $cmd ),
		};
	}

	private static function isRegexp( string $needle ): bool {
		if ( strlen( $needle ) < 3 ) {
			return false;
		}

		if ( ctype_alnum( $needle[0] ) ) {
			return false;
		}

		$nettoNeedle = preg_replace( '/[ismu]*$/', '', $needle );
		if ( strlen( $nettoNeedle ) < 2 ) {
			return false;
		}

		return $needle[0] === $nettoNeedle[-1];
	}

	public static function dplReplaceParserFunction(
		Parser $parser,
		string $text,
		string $pat,
		string $repl
	): string {
		$parser->addTrackingCategory( 'dplreplace-parserfunc-tracking-category' );

		if ( $text === '' || $pat === '' ) {
			return '';
		}

		// convert \n to a real newline character
		$repl = str_replace( '\n', "\n", $repl );

		// replace
		if ( !self::isRegexp( $pat ) ) {
			$pat = '`' . str_replace( '`', '\`', $pat ) . '`';
		}

		// Check for buffer overflow
		if ( strlen( $pat ) > 1000 ) {
			return '';
		}

		// Validate that the regex is valid
		// @phan-suppress-next-line SecurityCheck-ReDoS
		if ( !StringUtils::isValidPCRERegex( $pat ) ) {
			return '';
		}

		// @phan-suppress-next-line SecurityCheck-ReDoS
		return preg_replace( $pat, $repl, $text ) ?? '';
	}

	public static function dplChapterParserFunction(
		Parser $parser,
		string $text = '',
		string $heading = ' ',
		int $maxLength = -1,
		string $page = '?page?',
		string $link = 'default',
		bool $trim = false
	): string {
		$parser->addTrackingCategory( 'dplchapter-parserfunc-tracking-category' );
		$output = LST::extractHeadingFromText(
			$parser, $page, $text, $heading, '',
			$sectionHeading, true, $maxLength, $link, $trim
		);

		return $output[0] ?? '';
	}

	public static function dplMatrixParserFunction(
		Parser $parser,
		string $name,
		string $yes,
		string $no,
		string $flip,
		string $matrix
	): string {
		$parser->addTrackingCategory( 'dplmatrix-parserfunc-tracking-category' );

		$lines = explode( "\n", $matrix );
		$m = [];
		$sources = [];
		$targets = [];
		$from = null;

		$flip = $flip !== '' && $flip !== 'normal';
		$name = $name !== '' ? $name : '&#160;';
		$yes = $yes !== '' ? $yes : ' x ';
		$no = $no !== '' ? $no : '&#160;';
		if ( ( $no[0] ?? '' ) === '-' ) {
			$no = " $no ";
		}

		foreach ( $lines as $line ) {
			if ( trim( $line ) === '' ) {
				continue;
			}

			if ( $line[0] !== ' ' ) {
				$fromParts = preg_split( ' *\~\~ *', trim( $line ), 2 );
				$from = $fromParts[0];
				$label = $fromParts[1] ?? '';
				$sources[$from] = $label !== '' ? $label : $from;
				$m[$from] = [];
				continue;
			}

			if ( $from !== null ) {
				$toParts = preg_split( ' *\~\~ *', trim( $line ), 2 );
				$to = $toParts[0];
				$label = $toParts[1] ?? '';
				$targets[$to] = $label !== '' ? $label : $to;
				$m[$from][$to] = true;
			}
		}

		ksort( $targets );
		$header = "\n! $name";
		foreach ( $targets as $to => $toName ) {
			$header .= "\n! [[$to|$toName]]";
		}

		$rows = '';
		if ( $flip ) {
			foreach ( $targets as $to => $toName ) {
				$row = "\n|-\n! [[{$to}|{$toName}]]";
				foreach ( $sources as $from => $_ ) {
					$row .= "\n| " . ( $m[$from][$to] ?? false ? $yes : $no );
				}

				$rows .= $row;
			}

			return "{|class=dplmatrix\n$header\n$rows\n|}";
		}

		foreach ( $sources as $from => $fromName ) {
			$row = "\n|-\n! [[{$from}|{$fromName}]]";
			foreach ( $targets as $to => $_ ) {
				$row .= "\n| " . ( $m[$from][$to] ?? false ? $yes : $no );
			}

			$rows .= $row;
		}

		return "{|class=dplmatrix\n$header\n$rows\n|}";
	}

	public static function fixCategory( string $cat ): void {
		if ( $cat !== '' ) {
			self::$fixedCategories[$cat] = 1;
		}
	}

	/**
	 * Reset everything; some categories may have been fixed, however via fixcategory=
	 */
	public static function endReset( Parser $parser ): void {
		if ( self::$createdLinks['resetdone'] ) {
			return;
		}

		self::$createdLinks['resetdone'] = true;
		$output = $parser->getOutput();

		foreach ( $output->getCategoryNames() as $key ) {
			if ( array_key_exists( $key, self::$fixedCategories ) ) {
				self::$fixedCategories[$key] = $output->getCategorySortKey( $key );
			}
		}

		if ( self::$createdLinks['resetLinks'] ) {
			$output->mLinks = [];
		}

		if ( self::$createdLinks['resetCategories'] ) {
			$output->setCategories( self::$fixedCategories );
		}

		if ( self::$createdLinks['resetTemplates'] ) {
			$output->mTemplates = [];
		}

		if ( self::$createdLinks['resetImages'] ) {
			$output->mImages = [];
		}

		self::$fixedCategories = [];
	}

	public static function endEliminate( Parser $parser ): void {
		// Called during the final output phase; removes links created by DPL
		if ( !self::$createdLinks ) {
			return;
		}

		$output = $parser->getOutput();

		if ( isset( self::$createdLinks[0] ) ) {
			foreach ( $output->mLinks as $nsp => $_ ) {
				if ( !isset( self::$createdLinks[0][$nsp] ) ) {
					continue;
				}

				$output->mLinks[$nsp] = array_diff_assoc(
					$output->mLinks[$nsp],
					self::$createdLinks[0][$nsp]
				);

				if ( $output->mLinks[$nsp] === [] ) {
					unset( $output->mLinks[$nsp] );
				}
			}
		}

		if ( isset( self::$createdLinks[1] ) ) {
			foreach ( $output->mTemplates as $nsp => $_ ) {
				if ( !isset( self::$createdLinks[1][$nsp] ) ) {
					continue;
				}

				$output->mTemplates[$nsp] = array_diff_assoc(
					$output->mTemplates[$nsp],
					self::$createdLinks[1][$nsp]
				);

				if ( $output->mTemplates[$nsp] === [] ) {
					unset( $output->mTemplates[$nsp] );
				}
			}
		}

		if ( isset( self::$createdLinks[2] ) ) {
			$categories = array_combine(
				$output->getCategoryNames(),
				array_map(
					static fn ( string $name ): string =>
						$output->getCategorySortKey( $name ) ?? '',
					$output->getCategoryNames()
				)
			);

			$output->setCategories( array_diff_assoc( $categories, self::$createdLinks[2] ) );
		}

		if ( isset( self::$createdLinks[3] ) ) {
			$output->mImages = array_diff_assoc( $output->mImages, self::$createdLinks[3] );
		}
	}

	/**
	 * Setups and Modifies Database Information
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ): void {
		$updater->addPostDatabaseUpdateMaintenance( CreateView::class );
	}
}
