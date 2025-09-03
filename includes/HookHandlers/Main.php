<?php

namespace MediaWiki\Extension\DynamicPageList4\HookHandlers;

use MediaWiki\Extension\DynamicPageList4\Config;
use MediaWiki\Extension\DynamicPageList4\Parse;
use MediaWiki\Extension\DynamicPageList4\SectionTranscluder;
use MediaWiki\Extension\DynamicPageList4\Utils;
use MediaWiki\Extension\DynamicPageList4\Variables;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Parser\PPFrame;
use ReflectionProperty;
use StringUtils;
use function array_keys;
use function array_map;
use function explode;
use function implode;
use function ksort;
use function preg_replace;
use function preg_split;
use function str_replace;
use function strlen;
use function trim;

class Main implements ParserFirstCallInitHook {

	private readonly Config $config;

	public function __construct() {
		$this->config = Config::getInstance();
	}

	/** @inheritDoc */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'DPL', [ $this, 'dplTag' ] );

		// DPL4 offers the same functionality as Intersection.
		$parser->setHook( 'DynamicPageList', [ $this, 'intersectionTag' ] );

		$parser->setFunctionHook( 'dpl', [ $this, 'dplParserFunction' ] );
		$parser->setFunctionHook( 'dplnum', [ $this, 'dplNumParserFunction' ] );
		$parser->setFunctionHook( 'dplvar', [ $this, 'dplVarParserFunction' ] );
		$parser->setFunctionHook( 'dplreplace', [ $this, 'dplReplaceParserFunction' ] );
		$parser->setFunctionHook( 'dplchapter', [ $this, 'dplChapterParserFunction' ] );
		$parser->setFunctionHook( 'dplmatrix', [ $this, 'dplMatrixParserFunction' ] );
	}

	/**
	 * Tag <DynamicPageList> entry point.
	 */
	public function intersectionTag(
		string $input,
		array $args,
		Parser $parser,
		PPFrame $frame
	): string {
		Utils::setLikeIntersection( true );
		$parser->addTrackingCategory( 'dpl-intersection-tracking-category' );

		return $this->executeTag( $input, $args, $parser, $frame );
	}

	/**
	 * Tag <dpl> entry point.
	 */
	public function dplTag(
		string $input,
		array $args,
		Parser $parser,
		PPFrame $frame
	): string {
		Utils::setLikeIntersection( false );
		$parser->addTrackingCategory( 'dpl-tag-tracking-category' );

		return $this->executeTag( $input, $args, $parser, $frame );
	}

	/**
	 * The callback function wrapper for converting the input text to HTML output
	 *
	 * @param string $input
	 * @param array $args @phan-unused-param
	 */
	private function executeTag(
		string $input,
		array $args,
		Parser $parser,
		PPFrame $frame
	): string {
		// entry point for user tag <dpl> or <DynamicPageList>
		// create list and do a recursive parse of the output

		$parse = new Parse();
		if ( $this->config->get( 'recursiveTagParse' ) ) {
			$input = $parser->recursiveTagParse( $input, $frame );
		}

		$reset = [];
		$eliminate = [];
		$text = $parse->parse( $input, $parser, $reset, $eliminate, true );
		$parserOutput = $parser->getOutput();

		// we can remove the templates by save/restore
		$saveTemplates = [];
		if ( $reset['templates'] ?? false ) {
			foreach (
				$parserOutput->getLinkList( ParserOutputLinkTypes::TEMPLATE )
				as [ 'link' => $link, 'pageid' => $pageid ]
			) {
				$saveTemplates[ $link->getNamespace() ][ $link->getDBkey() ] = $pageid;
			}
		}

		// we can remove the categories by save/restore
		$saveCategories = [];
		if ( $reset['categories'] ?? false ) {
			foreach ( $parserOutput->getCategoryNames() as $name ) {
				$saveCategories[ $name ] = $parserOutput->getCategorySortKey( $name );
			}
		}

		// we can remove the images by save/restore
		$saveImages = [];
		if ( $reset['images'] ?? false ) {
			foreach (
				$parserOutput->getLinkList( ParserOutputLinkTypes::MEDIA )
				as [ 'link' => $link ]
			) {
				$saveImages[ $link->getDBkey() ] = 1;
			}
		}

		$parsedDPL = $parser->recursiveTagParse( $text );

		if ( $reset['templates'] ?? false ) {
			$refProp = new ReflectionProperty( $parserOutput, 'mTemplates' );
			$refProp->setAccessible( true );
			$refProp->setValue( $parserOutput, $saveTemplates );
		}

		if ( $reset['categories'] ?? false ) {
			$parserOutput->setCategories( $saveCategories );
		}

		if ( $reset['images'] ?? false ) {
			$refProp = new ReflectionProperty( $parserOutput, 'mImages' );
			$refProp->setAccessible( true );
			$refProp->setValue( $parserOutput, $saveImages );
		}

		return $parsedDPL;
	}

	/**
	 * The #dpl parser tag entry point.
	 */
	public function dplParserFunction(
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
	public function dplNumParserFunction( Parser $parser, string $text ): string {
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

	public function dplVarParserFunction(
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

	public function dplReplaceParserFunction(
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
		if ( !Utils::isRegexp( $pat ) ) {
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

	public function dplChapterParserFunction(
		Parser $parser,
		string $text = '',
		string $heading = ' ',
		int $maxLength = -1,
		string $page = '?page?',
		string $link = 'default',
		bool $trim = false
	): string {
		$parser->addTrackingCategory( 'dplchapter-parserfunc-tracking-category' );
		$sectionHeading = [];
		$output = SectionTranscluder::extractHeadingFromText(
			parser: $parser,
			page: $page,
			text: $text,
			sec: $heading,
			to: '',
			sectionHeading: $sectionHeading,
			recursionCheck: true,
			maxLength: $maxLength,
			cLink: $link,
			trim: $trim,
			skipPattern: []
		);

		return $output[0] ?? '';
	}

	public function dplMatrixParserFunction(
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
				$row = "\n|-\n! [[$to|$toName]]";
				foreach ( array_keys( $sources ) as $from ) {
					$row .= "\n| " . ( $m[$from][$to] ?? false ? $yes : $no );
				}

				$rows .= $row;
			}

			return "{|class=dplmatrix\n$header\n$rows\n|}";
		}

		foreach ( $sources as $from => $fromName ) {
			$row = "\n|-\n! [[$from|$fromName]]";
			foreach ( array_keys( $targets ) as $to ) {
				$row .= "\n| " . ( $m[$from][$to] ?? false ? $yes : $no );
			}

			$rows .= $row;
		}

		return "{|class=dplmatrix\n$header\n$rows\n|}";
	}
}
