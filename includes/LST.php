<?php
/**
 * This is a modified and enhanced copy of a mediawiki extension called
 *
 *       LabeledSectionTransclusion
 *
 * @link http://www.mediawiki.org/wiki/Extension:Labeled_Section_Transclusion Documentation
 *
 *
 * @author Steve Sanbeg
 * @copyright Copyright © 2006, Steve Sanbeg
 * @license GPL-2.0-or-later
 *
 *
 * This copy was made to avoid version conflicts between the two extensions.
 * In this copy names were changed (wfLst.. --> wfDplLst..).
 * So any version of LabeledSectionTransclusion can be installed together with DPL
 *
 * Enhancements were made to
 *     -  allow inclusion of templates ("template swapping")
 *     -  reduce the size of the transcluded text to a limit of <n> characters
 *
 *
 * Thanks to Steve for his great work!
 * -- Algorithmix
 */

namespace MediaWiki\Extension\DynamicPageList3;

use MediaWiki\Extension\DynamicPageList3\Lister\Lister;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\StripState;
use MediaWiki\Title\Title;
use ReflectionClass;
use ReflectionException;

class LST {

	/*
	 * To do transclusion from an extension, we need to interact with the parser
	 * at a low level. This is the general transclusion functionality
	 */

	/**
	 * Register what we're working on in the parser, so we don't fall into a trap.
	 *
	 * @param Parser $parser
	 * @param string $part1
	 * @return bool
	 */
	public static function open( $parser, $part1 ) {
		// Infinite loop test
		// @phan-suppress-next-line PhanDeprecatedProperty
		if ( isset( $parser->mTemplatePath[$part1] ) ) {
			wfDebug( __METHOD__ . ": template loop broken at '$part1'\n" );

			return false;
		} else {
			// @phan-suppress-next-line PhanDeprecatedProperty
			if ( !$parser->mTemplatePath ) {
				// @phan-suppress-next-line PhanDeprecatedProperty
				$parser->mTemplatePath = [];
			}

			// @phan-suppress-next-line PhanDeprecatedProperty
			$parser->mTemplatePath[$part1] = 1;

			return true;
		}
	}

	/**
	 * Finish processing the function.
	 *
	 * @param Parser $parser
	 * @param string $part1
	 */
	public static function close( $parser, $part1 ) {
		// Infinite loop test
		// @phan-suppress-next-line PhanDeprecatedProperty
		if ( isset( $parser->mTemplatePath[$part1] ) ) {
			// @phan-suppress-next-line PhanDeprecatedProperty
			unset( $parser->mTemplatePath[$part1] );
		} else {
			wfDebug( __METHOD__ . ": close unopened template loop at '$part1'\n" );
		}
	}

	/**
	 * Handle recursive substitution here, so we can break cycles, and set up
	 * return values so that edit sections will resolve correctly.
	 *
	 * @param Parser $parser
	 * @param string $text
	 * @param string $part1
	 * @param bool $recursionCheck
	 * @param int $maxLength
	 * @param string $link
	 * @param bool $trim
	 * @param array $skipPattern
	 * @return string
	 */
	private static function parse(
		$parser,
		$text,
		$part1,
		$recursionCheck = true,
		$maxLength = -1,
		$link = '',
		$trim = false,
		$skipPattern = []
	) {
		// if someone tries something like<section begin=blah>lst only</section>
		// text, may as well do the right thing.
		$text = str_replace( '</section>', '', $text );

		// if desired we remove portions of the text, esp. template calls
		foreach ( $skipPattern as $skipPat ) {
			$text = preg_replace( $skipPat, '', $text );
		}

		if ( self::open( $parser, $part1 ) ) {

			// Handle recursion here, so we can break cycles.
			if ( $recursionCheck == false ) {
				$text = self::callParserPreprocess( $parser, $text, $parser->getPage(), $parser->getOptions() );
				self::close( $parser, $part1 );
			}

			if ( $maxLength > 0 ) {
				$text = self::limitTranscludedText( $text, $maxLength, $link );
			}
			if ( $trim ) {
				return trim( $text );
			} else {
				return $text;
			}
		} else {
			$title = Title::castFromPageReference( $parser->getPage() );
			return "[[" . $title->getPrefixedText() . "]]" . "<!-- WARNING: LST loop detected -->";
		}
	}

	/*
	 * And now, the labeled section transclusion
	 */

	/**
	 * Generate a regex to match the section(s) we're interested in.
	 *
	 * @param string $sec
	 * @param bool &$any
	 * @return string
	 */
	private static function createSectionPattern( $sec, &$any ) {
		$any = false;

		if ( $sec[0] == '*' ) {
			$any = true;
			if ( $sec == '**' ) {
				$sec = '[^\/>"' . "']+";
			} else {
				$sec = str_replace( '/', '\/', substr( $sec, 1 ) );
			}
		} else {
			$sec = preg_quote( $sec, '/' );
		}

		$ws = "(?:\s+[^>]+)?";

		return "/<section$ws\s+(?i:begin)=['\"]?" . "($sec)" .
			"['\"]?$ws\/?>(.*?)\n?<section$ws\s+(?:[^>]+\s+)?(?i:end)=" .
			"['\"]?\\1['\"]?" . "$ws\/?>/s";
	}

	/**
	 * Fetches content of target page if valid and found, otherwise
	 * produces wikitext of a link to the target page.
	 *
	 * @param Parser $parser
	 * @param string $page title text of target page
	 * @param Title|string &$title normalized title object
	 * @param string &$text wikitext output
	 * @return bool true if returning text, false if target not found
	 */
	public static function text( $parser, $page, &$title, &$text ) {
		$title = Title::newFromText( $page );

		if ( $title === null ) {
			$text = '';
			return true;
		} else {
			$text = $parser->fetchTemplateAndTitle( $title )[0];
		}

		// if article doesn't exist, return a red link.
		if ( $text == false ) {
			$text = "[[" . $title->getPrefixedText() . "]]";
			return false;
		} else {
			return true;
		}
	}

	/**
	 * section inclusion - include all matching sections
	 *
	 * @param Parser $parser
	 * @param string $page
	 * @param string $sec
	 * @param bool $recursionCheck
	 * @param bool $trim
	 * @param array $skipPattern
	 * @return array
	 */
	public static function includeSection(
		$parser,
		$page = '',
		$sec = '',
		$recursionCheck = true,
		$trim = false,
		$skipPattern = []
	) {
		$output = [];

		if ( self::text( $parser, $page, $title, $text ) == false ) {
			$output[] = $text;
			return $output;
		}

		$any = false;
		$pat = self::createSectionPattern( $sec, $any );

		preg_match_all( $pat, $text, $m, PREG_PATTERN_ORDER );

		foreach ( $m[2] as $nr => $piece ) {
			$piece = self::parse(
				$parser, $piece, "#lst:{$page}|{$sec}",
				$recursionCheck, -1, '', $trim, $skipPattern
			);

			if ( $any ) {
				$output[] = $m[1][$nr] . '::' . $piece;
			} else {
				$output[] = $piece;
			}
		}

		return $output;
	}

	/**
	 * Truncate a portion of wikitext so that ..
	 * ... does not contain (open) html comments
	 * ... it is not larger that $lim characters
	 * ... it is balanced in terms of braces, brackets and tags
	 * ... it is cut at a word boundary (white space) if possible
	 * ... can be used as content of a wikitable field without spoiling the whole surrounding wikitext structure
	 *
	 * @param string $text the wikitext to be truncated
	 * @param int $limit limit of character count for the result
	 * @param string $link an optional link which will be appended to the text if it was truncated
	 *
	 * @return string the truncated text;
	 *         note that the returned text may be longer than the limit if this is necessary
	 *         to return something at all. We do not want to return an empty string if the input is not empty
	 *         if the text is already shorter than the limit, the text
	 *         will be returned without any checks for balance of tags
	 */
	public static function limitTranscludedText( $text, $limit, $link = '' ) {
		// if text is smaller than limit return complete text
		if ( $limit >= strlen( $text ) ) {
			return $text;
		}

		// otherwise strip html comments and check again
		$text = preg_replace( '/<!--.*?-->/s', '', $text );
		if ( $limit >= strlen( $text ) ) {
			return $text;
		}

		// search latest position with balanced brackets/braces
		// store also the position of the last preceding space

		$brackets = 0;
		$cbrackets = 0;
		$n0 = -1;
		$nb = 0;

		for ( $i = 0; $i < $limit; $i++ ) {
			$c = $text[$i];
			if ( $c == '[' ) {
				$brackets++;
			}

			if ( $c == ']' ) {
				$brackets--;
			}

			if ( $c == '{' ) {
				$cbrackets++;
			}

			if ( $c == '}' ) {
				$cbrackets--;
			}

			// we store the position if it is valid in terms of parentheses balancing
			if ( $brackets == 0 && $cbrackets == 0 ) {
				$n0 = $i;
				if ( $c == ' ' ) {
					$nb = $i;
				}
			}
		}

		// if there is a valid cut-off point we use it; it will be the largest one which is not above the limit
		if ( $n0 >= 0 ) {
			// we try to cut off at a word boundary, this may lead to a shortening of max. 15 chars
			// @phan-suppress-next-line PhanSuspiciousValueComparison
			if ( $nb > 0 && $nb + 15 > $n0 ) {
				$n0 = $nb;
			}

			$cut = substr( $text, 0, $n0 + 1 );

			// an open html comment would be fatal, but this should not happen as we already have
			// eliminated html comments at the beginning

			// some tags are critical: ref, pre, nowiki
			// if these tags were not balanced they would spoil the result completely
			// we enforce balance by appending the necessary amount of corresponding closing tags
			// currently we ignore the nesting, i.e. all closing tags are appended at the end.
			// This simple approach may fail in some cases ...

			$matches = [];
			$noMatches = preg_match_all( '#<\s*(/?ref|/?pre|/?nowiki)(\s+[^>]*?)*>#im', $cut, $matches );
			$tags = [
				'ref' => 0,
				'pre' => 0,
				'nowiki' => 0
			];

			if ( $noMatches > 0 ) {
				// calculate tag count (ignoring nesting)
				foreach ( $matches[1] as $mm ) {
					if ( $mm[0] == '/' ) {
						$tags[substr( $mm, 1 )]--;
					} else {
						$tags[$mm]++;
					}
				}

				// append missing closing tags - should the tags be ordered by precedence ?
				foreach ( $tags as $tagName => $level ) {
					// @phan-suppress-next-line PhanPluginLoopVariableReuse
					while ( $level > 0 ) {
						// avoid empty ref tag
						if ( $tagName == 'ref' && substr( $cut, strlen( $cut ) - 5 ) == '<ref>' ) {
							$cut = substr( $cut, 0, strlen( $cut ) - 5 );
						} else {
							$cut .= '</' . $tagName . '>';
						}

						$level--;
					}
				}
			}

			return $cut . $link;
		} elseif ( $limit == 0 ) {
			return $link;
		} else {
			// otherwise we recurse and try again with twice the limit size; this will lead to bigger output but
			// it will at least produce some output at all; otherwise the reader might think that there
			// is no information at all
			return self::limitTranscludedText( $text, $limit * 2, $link );
		}
	}

	/**
	 * @param Parser $parser
	 * @param string $page
	 * @param string $sec
	 * @param string $to
	 * @param array &$sectionHeading
	 * @param bool $recursionCheck
	 * @param int $maxLength
	 * @param string $link
	 * @param bool $trim
	 * @param array $skipPattern
	 * @return array
	 */
	public static function includeHeading(
		$parser,
		$page,
		$sec,
		$to,
		&$sectionHeading,
		$recursionCheck,
		$maxLength,
		$link,
		$trim,
		$skipPattern
	) {
		$output = [];

		if ( self::text( $parser, $page, $title, $text ) == false ) {
			$output[0] = $text;
			return $output;
		}

		// throw away comments
		$text = preg_replace( '/<!--.*?-->/s', '', $text );
		return self::extractHeadingFromText(
			$parser, $page, $text,
			$sec, $to, $sectionHeading,
			$recursionCheck, $maxLength,
			$link, $trim, $skipPattern
		);
	}

	/**
	 * Section inclusion - include all matching sections
	 *
	 * @param Parser $parser
	 * @param string $page
	 * @param string $text
	 * @param string $sec
	 * @param string $to
	 * @param array &$sectionHeading
	 * @param bool $recursionCheck
	 * @param int $maxLength
	 * @param string $cLink
	 * @param bool $trim
	 * @param array $skipPattern
	 * @return array
	 */
	public static function extractHeadingFromText(
		$parser,
		$page,
		$text,
		$sec,
		$to,
		&$sectionHeading,
		$recursionCheck,
		$maxLength,
		$cLink,
		$trim,
		$skipPattern = []
	) {
		$continueSearch = true;
		$output = [];

		$n = 0;
		$output[$n] = '';
		$nr = 0;

		// check if we are going to fetch the n-th section
		if ( preg_match( '/^%-?[1-9][0-9]*$/', $sec ) ) {
			$nr = substr( $sec, 1 );
		}

		if ( preg_match( '/^%0$/', $sec ) ) {
			// transclude text before the first section
			$nr = -2;
		}

		// if the section name starts with a # or with a @ we use it as regexp, otherwise as plain string
		$isPlain = true;

		if ( $sec != '' && ( $sec[0] == '#' || $sec[0] == '@' ) ) {
			$sec = substr( $sec, 1 );
			$isPlain = false;
		}

		do {
			// Generate a regex to match the === classical heading section(s) === we're
			//interested in.
			$headLine = '';
			$begin_off = 0;
			if ( $sec == '' ) {
				$head_len = 6;
			} else {
				if ( $nr != 0 ) {
					$pat = '^(={1,6})\s*[^=\s\n][^\n=]*\s*\1\s*($)';
				} elseif ( $isPlain ) {
					$pat = '^(={1,6})\s*' . preg_quote( $sec, '/' ) . '\s*\1\s*($)';
				} else {
					$pat = '^(={1,6})\s*' . str_replace( '/', '\/', $sec ) . '\s*\1\s*($)';
				}

				if ( preg_match( "/$pat/im", $text, $m, PREG_OFFSET_CAPTURE ) ) {
					$begin_off = end( $m )[1];
					$head_len = strlen( $m[1][0] );
					$headLine = trim( $m[0][0], "\n =\t" );
				} elseif ( $nr == -2 ) {
					// take whole article if no heading found
					$m[1][1] = strlen( $text ) + 1;
				} else {
					// match failed
					return $output;
				}
			}

			// create a link symbol (arrow, img, ...) in case we have to cut the text block to maxLength
			$link = $cLink;
			if ( $link == 'default' ) {
				$link = ' [[' . $page . '#' . $headLine . '|..→]]';
			} elseif ( strstr( $link, 'img=' ) != false ) {
				$link = str_replace(
					'img=', "<linkedimage>page=" . $page . '#' .
					$headLine . "\nimg=Image:", $link
				) . "\n</linkedimage>";
			} elseif ( strstr( $link, '%SECTION%' ) == false ) {
				$link = ' [[' . $page . '#' . $headLine . '|' . $link . ']]';
			} else {
				$link = str_replace( '%SECTION%', $page . '#' . $headLine, $link );
			}

			if ( $nr == -2 ) {
				// output text before first section and done
				$piece = substr( $text, 0, $m[1][1] - 1 );
				$output[0] = self::parse(
					$parser, $piece, "#lsth:{$page}|{$sec}",
					$recursionCheck, $maxLength,
					$link, $trim, $skipPattern
				);

				return $output;
			}

			if ( isset( $end_off ) ) {
				unset( $end_off );
			}

			if ( $to != '' ) {
				// if $to is supplied, try and match it. If we don't match, just ignore it.
				if ( $isPlain ) {
					$pat = '^(={1,6})\s*' . preg_quote( $to, '/' ) . '\s*\1\s*$';
				} else {
					$pat = '^(={1,6})\s*' . str_replace( '/', '\/', $to ) . '\s*\1\s*$';
				}

				if ( preg_match( "/$pat/im", $text, $mm, PREG_OFFSET_CAPTURE, $begin_off ) ) {
					$end_off = $mm[0][1] - 1;
				}
			}

			if ( ( $end_off ?? null ) === null ) {
				if ( $nr != 0 ) {
					$pat = '^(={1,6})\s*[^\s\n=][^\n=]*\s*\1\s*$';
				} else {
					// @phan-suppress-next-line PhanPossiblyUndeclaredVariable
					$pat = '^(={1,' . $head_len . '})(?!=)\s*.*?\1\s*$';
				}

				if ( preg_match( "/$pat/im", $text, $mm, PREG_OFFSET_CAPTURE, $begin_off ) ) {
					$end_off = $mm[0][1] - 1;
				} elseif ( $sec == '' ) {
					$end_off = -1;
				}
			}

			if ( $end_off ?? false ) {
				if ( $end_off == -1 ) {
					return $output;
				}

				$piece = substr( $text, $begin_off, $end_off - $begin_off );
				if ( $sec == '' ) {
					$continueSearch = false;
				} else {
					if ( $end_off == 0 ) {
						// we have made no progress - something has gone wrong, but at least don't loop forever
						break;
					}
					// this could lead to quadratic runtime...
					$text = substr( $text, $end_off );
				}
			} else {
				$piece = substr( $text, $begin_off );
				$continueSearch = false;
			}

			if ( $nr > 1 ) {
				// skip until we reach the n-th section
				$nr--;
				continue;
			}

			if ( isset( $m[0][0] ) ) {
				$sectionHeading[$n] = $headLine;
			} else {
				$sectionHeading[0] = $headLine;
			}

			if ( $nr == 1 ) {
				// output n-th section and done
				$output[0] = self::parse(
					$parser, $piece, "#lsth:{$page}|{$sec}",
					$recursionCheck, $maxLength,
					$link, $trim, $skipPattern
				);
				break;
			}

			if ( $nr == -1 ) {
				if ( !$end_off ) {
					// output last section and done
					$output[0] = self::parse(
						$parser, $piece, "#lsth:{$page}|{$sec}",
						$recursionCheck, $maxLength,
						$link, $trim, $skipPattern
					);
					break;
				}
			} else {
				// output section by name and continue search for another section with the same name
				$output[$n++] = self::parse(
					$parser, $piece, "#lsth:{$page}|{$sec}",
					$recursionCheck, $maxLength,
					$link, $trim, $skipPattern
				);
			}
		} while ( $continueSearch );

		return $output;
	}

	/**
	 * Template inclusion - find the place(s) where template1 is called,
	 * replace its name by template2, then expand template2 and return the result
	 * we return an array containing all occurences of the template call which match the condition "$mustMatch"
	 * and do NOT match the condition "$mustNotMatch" (if specified)
	 * we use a callback function to format retrieved parameters, accessible via $lister->formatTemplateArg()
	 *
	 * @param Parser $parser
	 * @param Lister $lister
	 * @param mixed $dplNr
	 * @param Article $article
	 * @param string $template1
	 * @param string $template2
	 * @param string $defaultTemplate
	 * @param string $mustMatch
	 * @param string $mustNotMatch
	 * @param bool $matchParsed
	 * @param string $catlist
	 * @return array
	 */
	public static function includeTemplate(
		$parser,
		Lister $lister,
		$dplNr,
		$article,
		$template1,
		$template2,
		$defaultTemplate,
		$mustMatch,
		$mustNotMatch,
		$matchParsed,
		$catlist
	) {
		$page = $article->mTitle->getPrefixedText();
		$date = $article->myDate;
		$user = $article->mUserLink;
		$title = Title::newFromText( $page );

		// get text and throw away html comments
		$text = preg_replace( '/<!--.*?-->/s', '', $parser->fetchTemplateAndTitle( $title )[0] );

		if ( $template1 != '' && $template1[0] == '#' ) {
			// --------------------------------------------- looking for a parser function call
			$template1 = substr( $template1, 1 );
			$template2 = substr( $template2, 1 );
			$defaultTemplate = substr( $defaultTemplate, 1 );

			// when looking for parser function calls we accept regexp search patterns
			$text2 = preg_replace( "/\{\{\s*#(" . $template1 . ')(\s*[:}])/i', '°³²|%PFUNC%=\1\2|', $text );
			$tCalls = preg_split( '/°³²/', ' ' . $text2 );

			foreach ( $tCalls as $i => $tCall ) {
				$n = strpos( $tCall, ':' );

				if ( $n !== false ) {
					$tCalls[$i][$n] = ' ';
				}
			}
		} elseif ( $template1 != '' && $template1[0] == '~' ) {
			// --------------------------------------------- looking for an xml-tag extension call
			$template1 = substr( $template1, 1 );
			$template2 = substr( $template2, 1 );
			$defaultTemplate = substr( $defaultTemplate, 1 );

			// looking for tags
			$text2 = preg_replace( '/\<\s*(' . $template1 . ')\s*\>/i', '°³²|%TAG%=\1|%TAGBODY%=', $text );
			$tCalls = preg_split( '/°³²/', ' ' . $text2 );

			foreach ( $tCalls as $i => $tCall ) {
				$tCalls[$i] = preg_replace( '/\<\s*\/' . $template1 . '\s*\>.*/is', '}}', $tCall );
			}
		} else {
			// --------------------------------------------- looking for template call
			// we accept plain text as a template name, space or underscore are the same
			// the localized name for "Template:" may preceed the template name
			// the name may start with a different namespace for the surrogate template, followed by ::
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();

			$nsNames = $contLang->getNamespaces();
			$tCalls = preg_split(
				'/\{\{\s*(Template:|' . $nsNames[10] . ':)?' .
				self::spaceOrUnderscore( preg_quote( $template1, '/' ) ) .
				'\s*[|}]/i', ' ' . $text
			);

			// We restore the first separator symbol (we had to include that symbol into the SPLIT, because we must make
			// sure that we only accept exact matches of the complete template name
			// (e.g. when looking for "foo" we must not accept "foo xyz")
			foreach ( $tCalls as $nr => $tCall ) {
				if ( $tCall[0] == '}' ) {
					$tCalls[$nr] = '}' . $tCall;
				} else {
					$tCalls[$nr] = '|' . $tCall;
				}
			}
		}

		$output = [];
		$extractParm = [];

		// check if we want to extract parameters directly from the call
		// in that case we won´t invoke template2 but will directly return the extracted parameters
		// as a sequence of table columns;
		if (
			strlen( $template2 ) > strlen( $template1 ) &&
			substr( $template2, 0, strlen( $template1 ) + 1 ) == ( $template1 . ':' )
		) {
			$extractParm = preg_split( '/:\s*/s', trim( substr( $template2, strlen( $template1 ) + 1 ) ) );
		}

		if ( count( $tCalls ) <= 1 ) {
			// template was not called (note that count will be 1 if there is no template invocation)
			if ( count( $extractParm ) > 0 ) {
				// if parameters are required directly: return empty columns
				if ( count( $extractParm ) > 1 ) {
					$output[0] = $lister->formatTemplateArg( '', $dplNr, 0, true, -1, $article );

					for ( $i = 1; $i < count( $extractParm ); $i++ ) {
						$output[0] .= "\n|" . $lister->formatTemplateArg( '', $dplNr, $i, true, -1, $article );
					}
				} else {
					$output[0] = $lister->formatTemplateArg( '', $dplNr, 0, true, -1, $article );
				}
			} else {
				// put a red link into the output
				$output[0] = self::callParserPreprocess( $parser,
					'{{' . $defaultTemplate . '|%PAGE%=' .
					$page . '|%TITLE%=' . $title->getText() .
					'|%DATE%=' . $date . '|%USER%=' . $user . '}}',
					$parser->getPage(), $parser->getOptions()
				);
			}

			unset( $title );

			return $output;
		}

		$output[0] = '';
		$n = -2;

		// loop for all template invocations
		$firstCall = true;

		foreach ( $tCalls as $tCall ) {
			if ( $n == -2 ) {
				$n++;
				continue;
			}

			$c = $tCall[0];
			// normally we construct a call for template2 with the parameters of template1
			if ( count( $extractParm ) == 0 ) {
				// find the end of the call: bracket level must be zero
				$cbrackets = 0;
				$templateCall = '{{' . $template2 . $tCall;
				$size = strlen( $templateCall );

				for ( $i = 0; $i < $size; $i++ ) {
					$c = $templateCall[$i];
					if ( $c == '{' ) {
						$cbrackets++;
					}

					if ( $c == '}' ) {
						$cbrackets--;
					}

					if ( $cbrackets == 0 ) {
						// if we must match a condition: test against it
						if ( (
							$mustMatch == '' ||
							preg_match( $mustMatch, substr( $templateCall, 0, $i - 1 ) )
						) && (
							$mustNotMatch == '' ||
							!preg_match( $mustNotMatch, substr( $templateCall, 0, $i - 1 ) )
						) ) {
							$invocation = substr( $templateCall, 0, $i - 1 );
							$argChain = $invocation . '|%PAGE%=' . $page . '|%TITLE%=' . $title->getText();

							if ( $catlist != '' ) {
								$argChain .= "|%CATLIST%=$catlist";
							}

							$argChain .= '|%DATE%=' . $date .
								'|%USER%=' . $user . '|%ARGS%=' .
								str_replace(
									'|', '§',
									preg_replace(
										'/[}]+/', '}',
										preg_replace(
											'/[{]+/', '{',
											substr( $invocation, strlen( $template2 ) + 2 )
										)
									)
								) . '}}';

							$output[++$n] = self::callParserPreprocess(
								$parser, $argChain, $parser->getPage(), $parser->getOptions()
							);
						}
						break;
					}
				}
			} else {
				// if the user wants parameters directly from the call line of template1 we return just those
				$cbrackets = 2;
				$templateCall = $tCall;
				$size = strlen( $templateCall );
				$parms = [];
				$parm = '';
				$hasParm = false;

				for ( $i = 0; $i < $size; $i++ ) {
					$c = $templateCall[$i];

					if ( $c == '{' || $c == '[' ) {
						// we count both types of brackets
						$cbrackets++;
					}

					if ( $c == '}' || $c == ']' ) {
						$cbrackets--;
					}

					if ( $cbrackets == 2 && $c == '|' ) {
						$parms[] = trim( $parm );
						$hasParm = true;
						$parm = '';
					} else {
						$parm .= $c;
					}

					if ( $cbrackets == 0 ) {
						if ( $hasParm ) {
							$parms[] = trim( substr( $parm, 0, strlen( $parm ) - 2 ) );
						}

						array_splice( $parms, 0, 1 );
						// if we must match a condition: test against it
						$callText = substr( $templateCall, 0, $i - 1 );

						if ( ( $mustMatch == '' || (
							( $matchParsed && preg_match(
								$mustMatch, $parser->recursiveTagParse( $callText )
							) ) || ( !$matchParsed && preg_match(
								$mustMatch, $callText
							) ) ) ) && (
							$mustNotMatch == '' || (
								( $matchParsed && !preg_match(
									$mustNotMatch, $parser->recursiveTagParse( $callText )
								) ) || (
									!$matchParsed && !preg_match(
										$mustNotMatch, $callText
									)
								)
							)
						) ) {
							$output[++$n] = '';
							$second = false;

							foreach ( $extractParm as $exParmKey => $exParm ) {
								$maxlen = -1;
								$limpos = strpos( $exParm, '[' );

								if ( $limpos > 0 && $exParm[strlen( $exParm ) - 1] == ']' ) {
									$maxlen = (int)substr( $exParm, $limpos + 1, strlen( $exParm ) - $limpos - 2 );
									$exParm = substr( $exParm, 0, $limpos );
								}

								if ( $second ) {
									// @phan-suppress-next-line PhanTypeInvalidDimOffset
									if ( $output[$n] == '' || $output[$n][strlen( $output[$n] ) - 1] != "\n" ) {
										$output[$n] .= "\n";
									}

									$output[$n] .= "|";
								}

								$found = false;

								// % in parameter name
								if ( strpos( $exParm, '%' ) !== false ) {
									// %% is a short form for inclusion of %PAGE% and %TITLE%
									$found = true;
									$output[$n] .= $lister->formatTemplateArg(
										$exParm, $dplNr, $exParmKey,
										$firstCall, $maxlen, $article
									);
								}

								if ( !$found ) {
									// named parameter
									$exParmQuote = str_replace( '/', '\/', $exParm );

									foreach ( $parms as $parm ) {
										if ( !preg_match( "/^\s*$exParmQuote\s*=/", $parm ) ) {
											continue;
										}

										$found = true;
										$output[$n] .= $lister->formatTemplateArg(
											preg_replace( "/^$exParmQuote\s*=\s*/", "", $parm ),
											$dplNr, $exParmKey, $firstCall,
											$maxlen, $article
										);
										break;
									}
								}

								if ( !$found && is_numeric( $exParm ) && (int)$exParm == $exParm ) {
									// numeric parameter
									$np = 0;

									foreach ( $parms as $parm ) {
										if ( strstr( $parm, '=' ) === false ) {
											++$np;
										}

										if ( $np != $exParm ) {
											continue;
										}

										$found = true;
										$output[$n] .= $lister->formatTemplateArg(
											$parm, $dplNr, $exParmKey,
											$firstCall, $maxlen, $article
										);
										break;
									}
								}

								if ( !$found ) {
									$output[$n] .= $lister->formatTemplateArg(
										'', $dplNr, $exParmKey, $firstCall, $maxlen, $article
									);
								}

								$second = true;
							}
						}
						break;
					}
				}
			}

			$firstCall = false;
		}

		return $output;
	}

	/**
	 * @param string $pattern
	 * @return string
	 */
	public static function spaceOrUnderscore( $pattern ) {
		// returns a pettern that matches underscores as well as spaces
		return str_replace( ' ', '[ _]', $pattern );
	}

	/**
	 * Preprocess given text according to the globally-configured method
	 *
	 * The default method uses Parser::preprocess() which does the job, but clears the internal cache every time.
	 * The improved method uses Parser::recursivePreprocess() that saves a decent amount of processing time
	 * by preserving the internal cache leveraging the repetitive call pattern.
	 *
	 * Parser::preprocess() was mainly called from LST::includeTemplate() for the same template(s) with different
	 * set of arguments for each article found. In the original implementation using Parser::preprocess(),
	 * the internal cache is cleared at each call and parsing the same template text into template DOM is repeated
	 * multiple times.
	 *
	 * Using Parser::recursivePreprocess() prevents the cache clear, and thus repetitive calls reuse the
	 * previously generated template DOM which brings a decent performance improvement when called multiple times.
	 */
	protected static function callParserPreprocess(
		Parser $parser,
		string $text,
		?PageReference $page,
		ParserOptions $options
	): string {
		if ( Config::getSetting( 'recursivePreprocess' ) ) {
			self::softResetParser( $parser );
			$parser->setOutputType( OT_PREPROCESS );

			$text = $parser->recursivePreprocess( $text );
			return $text;
		}

		return $parser->preprocess( $text, $page, $options );
	}

	/**
	 * Reset Parser's internal counters to avoid kicking in the limits when rendering long lists of results.
	 */
	private static function softResetParser( Parser $parser ): void {
		self::setParserProperties( $parser, [
			'mStripState' => new StripState( $parser ),
			'mIncludeSizes' => [
				'post-expand' => 0,
				'arg' => 0,
			],
			'mPPNodeCount' => 0,
			'mHighestExpansionDepth' => 0,
			'mExpensiveFunctionCount' => 0,
		] );
	}

	private static function setParserProperties( Parser $parser, array $properties ): void {
		static $reflectionCache = [];
		foreach ( $properties as $property => $value ) {
			if ( !array_key_exists( $property, $reflectionCache ) ) {
				try {
					$reflectionCache[$property] = ( new ReflectionClass( Parser::class ) )->getProperty( $property );
				} catch ( ReflectionException ) {
					$reflectionCache[$property] = null;
				}
			}

			if ( $reflectionCache[$property] ) {
				$reflectionCache[$property]->setValue( $parser, $value );
			}
		}
	}
}
