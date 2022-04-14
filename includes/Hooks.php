<?php

namespace MediaWiki\Extension\DynamicPageList3;

use DatabaseUpdater;
use MediaWiki\Extension\DynamicPageList3\Maintenance\CreateTemplate;
use MediaWiki\Extension\DynamicPageList3\Maintenance\CreateView;
use Parser;
use PPFrame;

class Hooks {

	public const FATAL_WRONGNS = 1001;

	public const FATAL_WRONGLINKSTO = 1002;

	public const FATAL_TOOMANYCATS = 1003;

	public const FATAL_TOOFEWCATS = 1004;

	public const FATAL_NOSELECTION = 1005;

	public const FATAL_CATDATEBUTNOINCLUDEDCATS = 1006;

	public const FATAL_CATDATEBUTMORETHAN1CAT = 1007;

	public const FATAL_MORETHAN1TYPEOFDATE = 1008;

	public const FATAL_WRONGORDERMETHOD = 1009;

	public const FATAL_DOMINANTSECTIONRANGE = 1010;

	public const FATAL_OPENREFERENCES = 1012;

	public const FATAL_MISSINGPARAMFUNCTION = 1022;

	public const FATAL_POOLCOUNTER = 1023;

	public const FATAL_NOTPROTECTED = 1024;

	public const FATAL_SQLBUILDERROR = 1025;

	public const WARN_UNKNOWNPARAM = 2013;

	public const WARN_PARAMNOOPTION = 2022;

	public const WARN_WRONGPARAM = 2014;

	public const WARN_WRONGPARAM_INT = 2015;

	public const WARN_NORESULTS = 2016;

	public const WARN_CATOUTPUTBUTWRONGPARAMS = 2017;

	public const WARN_HEADINGBUTSIMPLEORDERMETHOD = 2018;

	public const WARN_DEBUGPARAMNOTFIRST = 2019;

	public const WARN_TRANSCLUSIONLOOP = 2020;

	public const DEBUG_QUERY = 3021;

	/** @var array */
	public static $fixedCategories = [];

	/**
	 * @var array
	 * @phan-var array<mixed,mixed>
	 */
	public static $createdLinks;

	/** @var bool */
	private static $likeIntersection = false;

	/** @var int */
	private static $debugLevel = 0;

	/**
	 * @return string
	 */
	public static function getVersion(): string {
		static $version = null;

		if ( $version === null ) {
			$version = json_decode( file_get_contents( __DIR__ . '/../extension.json' ) )->version;
		}

		return $version;
	}

	/**
	 * Sets up this extension's parser functions.
	 *
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		self::init();

		// DPL offers the same functionality as Intersection. So we register the <DynamicPageList> tag in case LabeledSection Extension is not installed so that the section markers are removed.
		if ( Config::getSetting( 'handleSectionTag' ) ) {
			$parser->setHook( 'section', [ __CLASS__, 'dplTag' ] );
		}

		$parser->setHook( 'DPL', [ __CLASS__, 'dplTag' ] );
		$parser->setHook( 'DynamicPageList', [ __CLASS__, 'intersectionTag' ] );

		$parser->setFunctionHook( 'dpl', [ __CLASS__, 'dplParserFunction' ] );
		$parser->setFunctionHook( 'dplnum', [ __CLASS__, 'dplNumParserFunction' ] );
		$parser->setFunctionHook( 'dplvar', [ __CLASS__, 'dplVarParserFunction' ] );
		$parser->setFunctionHook( 'dplreplace', [ __CLASS__, 'dplReplaceParserFunction' ] );
		$parser->setFunctionHook( 'dplchapter', [ __CLASS__, 'dplChapterParserFunction' ] );
		$parser->setFunctionHook( 'dplmatrix', [ __CLASS__, 'dplMatrixParserFunction' ] );
	}

	/**
	 * Sets up this extension's parser functions for migration from Intersection.
	 *
	 * @param Parser $parser
	 */
	public static function setupMigration( Parser $parser ) {
		$parser->setHook( 'Intersection', [ __CLASS__, 'intersectionTag' ] );
		$parser->addTrackingCategory( 'dpl-intersection-tracking-category' );

		self::init();
	}

	/**
	 * Common initializer for usage from parser entry points.
	 */
	private static function init() {
		Config::init();

		if ( !isset( self::$createdLinks ) ) {
			self::$createdLinks = [
				'resetLinks' => false,
				'resetTemplates' => false,
				'resetCategories' => false,
				'resetImages' => false,
				'resetdone' => false,
				'elimdone' => false
			];
		}
	}

	/**
	 * Set to behave like intersection.
	 *
	 * @param bool $mode
	 */
	private static function setLikeIntersection( $mode = false ) {
		self::$likeIntersection = $mode;
	}

	/**
	 * Is like intersection?
	 *
	 * @return bool
	 */
	public static function isLikeIntersection() {
		return (bool)self::$likeIntersection;
	}

	/**
	 * Tag <section> entry point.
	 *
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public static function intersectionTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		self::setLikeIntersection( true );

		return self::executeTag( $input, $args, $parser, $frame );
	}

	/**
	 * Tag <dpl> entry point.
	 *
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public static function dplTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		self::setLikeIntersection( false );
		$parser->addTrackingCategory( 'dpl-tag-tracking-category' );

		return self::executeTag( $input, $args, $parser, $frame );
	}

	/**
	 * The callback function wrapper for converting the input text to HTML output
	 *
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	private static function executeTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		// entry point for user tag <dpl> or <DynamicPageList>
		// create list and do a recursive parse of the output

		$parse = new Parse();
		if ( Config::getSetting( 'recursiveTagParse' ) ) {
			$input = $parser->recursiveTagParse( $input, $frame );
		}

		$text = $parse->parse( $input, $parser, $reset, $eliminate, true );

		// we can remove the templates by save/restore
		if ( $reset['templates'] ?? false ) {
			$saveTemplates = $parser->getOutput()->mTemplates;
		}

		// we can remove the categories by save/restore
		if ( $reset['categories'] ?? false ) {
			$saveCategories = $parser->getOutput()->mCategories;
		}

		// we can remove the images by save/restore
		if ( $reset['images'] ?? false ) {
			$saveImages = $parser->getOutput()->mImages;
		}

		$parsedDPL = $parser->recursiveTagParse( $text );
		if ( $reset['templates'] ?? false ) {
			$parser->getOutput()->mTemplates = $saveTemplates ?? [];
		}

		if ( $reset['categories'] ?? false ) {
			$parser->getOutput()->mCategories = $saveCategories ?? [];
		}

		if ( $reset['images'] ?? false ) {
			$parser->getOutput()->mImages = $saveImages ?? [];
		}

		return $parsedDPL;
	}

	/**
	 * The #dpl parser tag entry point.
	 *
	 * @param Parser $parser
	 * @return array|string
	 */
	public static function dplParserFunction( $parser ) {
		self::setLikeIntersection( false );

		$parser->addTrackingCategory( 'dpl-parserfunc-tracking-category' );

		// callback for the parser function {{#dpl: or {{DynamicPageList::
		$input = "";

		$numargs = func_num_args();
		if ( $numargs < 2 ) {
			$input = "#dpl: no arguments specified";

			return str_replace( '§', '<', '§pre>§nowiki>' . $input . '§/nowiki>§/pre>' );
		}

		// fetch all user-provided arguments (skipping $parser)
		$arg_list = func_get_args();
		for ( $i = 1; $i < $numargs; $i++ ) {
			$p1 = $arg_list[$i];

			$input .= str_replace( "\n", "", $p1 ) . "\n";
		}

		$parse = new Parse();
		$dplresult = $parse->parse( $input, $parser, $reset, $eliminate, false );

		return [
			// @phan-suppress-next-line PhanPluginMixedKeyNoKey
			$parser->getPreprocessor()->preprocessToObj( $dplresult, 1 ),
			'isLocalObj' => true,
			'title' => $parser->getTitle()
		];
	}

	/**
	 * The #dplnum parser tag entry point.
	 * From the old documentation: "Tries to guess a number that is buried in the text. Uses a set of heuristic rules which may work or not. The idea is to extract the number so that it can be used as a sorting value in the column of a DPL table output."
	 *
	 * @param Parser $parser
	 * @param string $text
	 * @return string
	 */
	public static function dplNumParserFunction( $parser, $text = '' ) {
		$parser->addTrackingCategory( 'dplnum-parserfunc-tracking-category' );

		$num = str_replace( '&#160;', ' ', $text );
		$num = str_replace( '&nbsp;', ' ', $text );
		$num = preg_replace( '/([0-9])([.])([0-9][0-9]?[^0-9,])/', '\1,\3', $num );
		$num = preg_replace( '/([0-9.]+),([0-9][0-9][0-9])\s*Mrd/', '\1\2 000000 ', $num );
		$num = preg_replace( '/([0-9.]+),([0-9][0-9])\s*Mrd/', '\1\2 0000000 ', $num );
		$num = preg_replace( '/([0-9.]+),([0-9])\s*Mrd/', '\1\2 00000000 ', $num );
		$num = preg_replace( '/\s*Mrd/', '000000000 ', $num );
		$num = preg_replace( '/([0-9.]+),([0-9][0-9][0-9])\s*Mio/', '\1\2 000 ', $num );
		$num = preg_replace( '/([0-9.]+),([0-9][0-9])\s*Mio/', '\1\2 0000 ', $num );
		$num = preg_replace( '/([0-9.]+),([0-9])\s*Mio/', '\1\2 00000 ', $num );
		$num = preg_replace( '/\s*Mio/', '000000 ', $num );
		$num = preg_replace( '/[. ]/', '', $num );
		$num = preg_replace( '/^[^0-9]+/', '', $num );
		$num = preg_replace( '/[^0-9].*/', '', $num );

		return $num;
	}

	/**
	 * @param Parser &$parser
	 * @param string $cmd
	 * @return string
	 */
	public static function dplVarParserFunction( &$parser, $cmd ) {
		$parser->addTrackingCategory( 'dplvar-parserfunc-tracking-category' );
		$args = func_get_args();

		if ( $cmd == 'set' ) {
			return Variables::setVar( $args );
		} elseif ( $cmd == 'default' ) {
			return Variables::setVarDefault( $args );
		}

		return Variables::getVar( $cmd );
	}

	/**
	 * @param string $needle
	 * @return bool
	 */
	private static function isRegexp( $needle ) {
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

		if ( $needle[0] == $nettoNeedle[strlen( $nettoNeedle ) - 1] ) {
			return true;
		}

		return false;
	}

	/**
	 * @param Parser &$parser
	 * @param string $text
	 * @param string $pat
	 * @param string $repl
	 * @return string
	 */
	public static function dplReplaceParserFunction( &$parser, $text, $pat = '', $repl = '' ) {
		$parser->addTrackingCategory( 'dplreplace-parserfunc-tracking-category' );
		if ( $text == '' || $pat == '' ) {
			return '';
		}

		// convert \n to a real newline character
		$repl = str_replace( '\n', "\n", $repl );

		# replace
		if ( !self::isRegexp( $pat ) ) {
			$pat = '`' . str_replace( '`', '\`', $pat ) . '`';
		}

		// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		return @preg_replace( $pat, $repl, $text );
	}

	/**
	 * @param Parser &$parser
	 * @param string $text
	 * @param string $heading
	 * @param int $maxLength
	 * @param string $page
	 * @param string $link
	 * @param bool $trim
	 * @return string
	 */
	public static function dplChapterParserFunction( &$parser, $text = '', $heading = ' ', $maxLength = -1, $page = '?page?', $link = 'default', $trim = false ) {
		$parser->addTrackingCategory( 'dplchapter-parserfunc-tracking-category' );
		$output = LST::extractHeadingFromText( $parser, $page, '?title?', $text, $heading, '', $sectionHeading, true, $maxLength, $link, $trim );
		return $output[0];
	}

	/**
	 * @param Parser &$parser
	 * @param string $name
	 * @param string $yes
	 * @param string $no
	 * @param string $flip
	 * @param string $matrix
	 * @return string
	 */
	public static function dplMatrixParserFunction( &$parser, $name = '', $yes = '', $no = '', $flip = '', $matrix = '' ) {
		$parser->addTrackingCategory( 'dplmatrix-parserfunc-tracking-category' );
		$lines = explode( "\n", $matrix );
		$m = [];
		$sources = [];
		$targets = [];
		$from = '';
		$to = '';

		if ( $flip == '' || $flip == 'normal' ) {
			$flip = false;
		} else {
			$flip = true;
		}

		if ( $name == '' ) {
			$name = '&#160;';
		}

		if ( $yes == '' ) {
			$yes = ' x ';
		}

		if ( $no == '' ) {
			$no = '&#160;';
		}

		if ( $no[0] == '-' ) {
			$no = " $no ";
		}

		foreach ( $lines as $line ) {
			if ( strlen( $line ) <= 0 ) {
				continue;
			}

			if ( $line[0] != ' ' ) {
				$from = preg_split( ' *\~\~ *', trim( $line ), 2 );
				if ( !array_key_exists( $from[0], $sources ) ) {
					if ( count( $from ) < 2 || $from[1] == '' ) {
						$sources[$from[0]] = $from[0];
					} else {
						$sources[$from[0]] = $from[1];
					}

					$m[$from[0]] = [];
				}
			} elseif ( trim( $line ) != '' ) {
				$to = preg_split( ' *\~\~ *', trim( $line ), 2 );

				if ( count( $to ) < 2 || $to[1] == '' ) {
					$targets[$to[0]] = $to[0];
				} else {
					$targets[$to[0]] = $to[1];
				}

				// @phan-suppress-next-line PhanTypeInvalidDimOffset
				$m[$from[0]][$to[0]] = true;
			}
		}

		ksort( $targets );

		$header = "\n";

		if ( $flip ) {
			foreach ( $sources as $from => $fromName ) {
				$header .= "![[$from|" . $fromName . "]]\n";
			}

			foreach ( $targets as $to => $toName ) {
				$targets[$to] = "[[$to|$toName]]";

				foreach ( $sources as $from => $fromName ) {
					if ( array_key_exists( $to, $m[$from] ) ) {
						$targets[$to] .= "\n|$yes";
					} else {
						$targets[$to] .= "\n|$no";
					}
				}

				$targets[$to] .= "\n|--\n";
			}

			return "{|class=dplmatrix\n|$name" . "\n" . $header . "|--\n!" . implode( "\n!", $targets ) . "\n|}";
		} else {
			foreach ( $targets as $to => $toName ) {
				$header .= "![[$to|" . $toName . "]]\n";
			}

			foreach ( $sources as $from => $fromName ) {
				$sources[$from] = "[[$from|$fromName]]";

				foreach ( $targets as $to => $toName ) {
					if ( array_key_exists( $to, $m[$from] ) ) {
						$sources[$from] .= "\n|$yes";
					} else {
						$sources[$from] .= "\n|$no";
					}
				}

				$sources[$from] .= "\n|--\n";
			}

			return "{|class=dplmatrix\n|$name" . "\n" . $header . "|--\n!" . implode( "\n!", $sources ) . "\n|}";
		}
	}

	/**
	 * @param string $cat
	 */
	public static function fixCategory( $cat ) {
		if ( $cat != '' ) {
			self::$fixedCategories[$cat] = 1;
		}
	}

	/**
	 * Set Debugging Level
	 *
	 * @param int|string $level
	 */
	public static function setDebugLevel( $level ) {
		self::$debugLevel = intval( $level );
	}

	/**
	 * Return Debugging Level
	 *
	 * @return int
	 */
	public static function getDebugLevel() {
		return self::$debugLevel;
	}

	/**
	 * Reset everything; some categories may have been fixed, however via fixcategory=
	 *
	 * @param Parser $parser
	 * @param string $text
	 */
	public static function endReset( $parser, $text ) {
		if ( !self::$createdLinks['resetdone'] ) {
			self::$createdLinks['resetdone'] = true;

			foreach ( $parser->getOutput()->mCategories as $key => $val ) {
				if ( array_key_exists( $key, self::$fixedCategories ) ) {
					self::$fixedCategories[$key] = $val;
				}
			}

			if ( self::$createdLinks['resetLinks'] ) {
				$parser->getOutput()->mLinks = [];
			}

			if ( self::$createdLinks['resetCategories'] ) {
				$parser->getOutput()->mCategories = self::$fixedCategories;
			}

			if ( self::$createdLinks['resetTemplates'] ) {
				$parser->getOutput()->mTemplates = [];
			}

			if ( self::$createdLinks['resetImages'] ) {
				$parser->getOutput()->mImages = [];
			}

			self::$fixedCategories = [];
		}
	}

	/**
	 * @param Parser $parser
	 * @param string &$text
	 */
	public static function endEliminate( $parser, &$text ) {
		// called during the final output phase; removes links created by DPL
		if ( isset( self::$createdLinks ) ) {
			if ( array_key_exists( 0, self::$createdLinks ) ) {
				foreach ( $parser->getOutput()->getLinks() as $nsp => $link ) {
					if ( !array_key_exists( $nsp, self::$createdLinks[0] ) ) {
						continue;
					}

					$parser->getOutput()->mLinks[$nsp] = array_diff_assoc( $parser->getOutput()->mLinks[$nsp], self::$createdLinks[0][$nsp] );

					if ( count( $parser->getOutput()->mLinks[$nsp] ) == 0 ) {
						unset( $parser->getOutput()->mLinks[$nsp] );
					}
				}
			}

			if ( isset( self::$createdLinks ) && array_key_exists( 1, self::$createdLinks ) ) {
				foreach ( $parser->getOutput()->mTemplates as $nsp => $tpl ) {
					if ( !array_key_exists( $nsp, self::$createdLinks[1] ) ) {
						continue;
					}

					$parser->getOutput()->mTemplates[$nsp] = array_diff_assoc( $parser->getOutput()->mTemplates[$nsp], self::$createdLinks[1][$nsp] );

					if ( count( $parser->getOutput()->mTemplates[$nsp] ) == 0 ) {
						unset( $parser->getOutput()->mTemplates[$nsp] );
					}
				}
			}

			if ( isset( self::$createdLinks ) && array_key_exists( 2, self::$createdLinks ) ) {
				$parser->getOutput()->mCategories = array_diff_assoc( $parser->getOutput()->mCategories, self::$createdLinks[2] );
			}

			if ( isset( self::$createdLinks ) && array_key_exists( 3, self::$createdLinks ) ) {
				$parser->getOutput()->mImages = array_diff_assoc( $parser->getOutput()->mImages, self::$createdLinks[3] );
			}
		}
	}

	/**
	 * Setups and Modifies Database Information
	 *
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addPostDatabaseUpdateMaintenance( CreateTemplate::class );
		$updater->addPostDatabaseUpdateMaintenance( CreateView::class );
	}
}
