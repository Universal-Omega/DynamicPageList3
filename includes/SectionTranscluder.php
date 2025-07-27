<?php

namespace MediaWiki\Extension\DynamicPageList4;

use MediaWiki\Extension\DynamicPageList4\Lister\Lister;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\StripState;
use MediaWiki\Title\Title;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;

class SectionTranscluder {

	/**
	 * To do transclusion from an extension, we need to interact with the parser
	 * at a low level. This is the general transclusion functionality
	 */

	/**
	 * Register what we're working on in the parser, so we don't fall into a trap.
	 */
	private static function open( Parser $parser, string $part1 ): bool {
		// Infinite loop test
		/** @phan-suppress-next-line PhanDeprecatedProperty */
		if ( isset( $parser->mTemplatePath[$part1] ) ) {
			self::getLogger()->debug( 'Template loop broken at {part1}',
				[ 'part1' => $part1 ]
			);
			return false;
		}

		/** @phan-suppress-next-line PhanDeprecatedProperty */
		$parser->mTemplatePath ??= [];

		/** @phan-suppress-next-line PhanDeprecatedProperty */
		$parser->mTemplatePath[$part1] = 1;
		return true;
	}

	/**
	 * Finish processing the function.
	 */
	private static function close( Parser $parser, string $part1 ): void {
		// Infinite loop test
		/** @phan-suppress-next-line PhanDeprecatedProperty */
		if ( !isset( $parser->mTemplatePath[$part1] ) ) {
			self::getLogger()->debug( 'Closed an unopened template loop at {part1}',
				[ 'part1' => $part1 ]
			);
			return;
		}

		/** @phan-suppress-next-line PhanDeprecatedProperty */
		unset( $parser->mTemplatePath[$part1] );
	}

	private static function getLogger(): LoggerInterface {
		return LoggerFactory::getInstance( 'DynamicPageList4' );
	}

	/**
	 * Handle recursive substitution here, so we can break cycles, and set up
	 * return values so that edit sections will resolve correctly.
	 */
	private static function parse(
		Parser $parser,
		string $text,
		string $part1,
		bool $recursionCheck,
		int $maxLength,
		string $link,
		bool $trim,
		array $skipPattern
	): string {
		$text = str_replace( '</section>', '', $text );
		foreach ( $skipPattern as $pattern ) {
			$text = preg_replace( $pattern, '', $text );
		}

		if ( self::open( $parser, $part1 ) ) {
			if ( !$recursionCheck ) {
				$text = self::callParserPreprocess( $parser, $text, $parser->getPage(), $parser->getOptions() );
				self::close( $parser, $part1 );
			}

			if ( $maxLength > 0 ) {
				$text = self::limitTranscludedText( $text, $maxLength, $link );
			}

			return $trim ? trim( $text ) : $text;
		}

		$title = Title::castFromPageReference( $parser->getPage() );
		return "[[{$title->getPrefixedText()}]]<!-- WARNING: DPL4 SectionTranscluder loop detected -->";
	}

	/**
	 * And now, the section transclusion
	 */

	/**
	 * Generate a regex to match the section(s) we're interested in.
	 */
	private static function createSectionPattern( string $sec, bool &$any ): string {
		$any = $sec[0] === '*';
		$sec = match ( true ) {
			$any && $sec === '**' => '[^\/>"\']+',
			$any => str_replace( '/', '\/', substr( $sec, 1 ) ),
			default => preg_quote( $sec, '/' ),
		};

		$ws = '(?:\s+[^>]+)?';
		return "/<section$ws\s+(?i:begin)=['\"]?($sec)['\"]?$ws\/?>(.*?)\n?"
			. "<section$ws\s+(?:[^>]+\s+)?(?i:end)=['\"]?\\1['\"]?$ws\/?>/s";
	}

	/**
	 * Fetches content of target page if valid and found, otherwise
	 * produces wikitext of a link to the target page.
	 *
	 * @param Parser $parser
	 * @param string $page title text of target page
	 * @param string &$text wikitext output
	 * @return bool true if returning text, false if target not found
	 */
	private static function text( Parser $parser, string $page, string &$text ): bool {
		$title = Title::newFromText( $page );
		if ( $title === null ) {
			$text = '';
			return true;
		}

		$text = $parser->fetchTemplateAndTitle( $title )[0];
		// If article doesn't exist, return a red link.
		if ( $text === false ) {
			$text = "[[{$title->getPrefixedText()}]]";
			return false;
		}

		return true;
	}

	/**
	 * section inclusion - include all matching sections
	 */
	public static function includeSection(
		Parser $parser,
		string $page,
		string $sec,
		bool $recursionCheck,
		bool $trim,
		array $skipPattern
	): array {
		$text = '';
		if ( !self::text( $parser, $page, $text ) ) {
			return [ $text ];
		}

		$any = false;
		$pat = self::createSectionPattern( $sec, $any );

		preg_match_all( $pat, $text, $matches, PREG_PATTERN_ORDER );

		$output = [];
		foreach ( $matches[2] as $nr => $piece ) {
			$piece = self::parse(
				parser: $parser,
				text: $piece,
				part1: "section:$page|$sec",
				recursionCheck: $recursionCheck,
				maxLength: -1,
				link: '',
				trim: $trim,
				skipPattern: $skipPattern
			);

			$output[] = $any ?
				( $matches[1][$nr] . '::' . $piece ) :
				$piece;
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
	 * @param string $link a link which, if not an empty string, will be appended to the text if it was truncated
	 *
	 * @return string the truncated text;
	 *         note that the returned text may be longer than the limit if this is necessary
	 *         to return something at all. We do not want to return an empty string if the input is not empty
	 *         if the text is already shorter than the limit, the text
	 *         will be returned without any checks for balance of tags
	 */
	public static function limitTranscludedText( string $text, int $limit, string $link ): string {
		// If text is smaller than limit return complete text.
		$length = strlen( $text );
		if ( $limit >= $length ) {
			return $text;
		}

		// Otherwise strip html comments and check again.
		$text = preg_replace( '/<!--.*?-->/s', '', $text );
		if ( $limit >= strlen( $text ) ) {
			return $text;
		}

		// Search latest position with balanced brackets/braces
		// store also the position of the last preceding space.
		$brackets = 0;
		$cbrackets = 0;
		$n0 = -1;
		$nb = 0;

		for ( $i = 0; $i < $limit; $i++ ) {
			$c = $text[$i] ?? '';
			match ( $c ) {
				'[' => $brackets++,
				']' => $brackets--,
				'{' => $cbrackets++,
				'}' => $cbrackets--,
				default => null,
			};

			if ( $brackets === 0 && $cbrackets === 0 ) {
				$n0 = $i;
				if ( $c === ' ' ) {
					$nb = $i;
				}
			}
		}

		// If there is a valid cut-off point we use it; it will be the largest one which is not above the limit.
		if ( $n0 >= 0 ) {
			// We try to cut off at a word boundary, this may lead to a shortening of maximum 15 chars.
			/** @phan-suppress-next-line PhanSuspiciousValueComparison */
			if ( $nb > 0 && $nb + 15 > $n0 ) {
				$n0 = $nb;
			}

			$cut = substr( $text, 0, $n0 + 1 );

			// An open html comment would be fatal, but this should not happen as we already have
			// eliminated html comments at the beginning.

			// Some tags are critical: ref, pre, nowiki
			// if these tags were not balanced they would spoil the result completely
			// we enforce balance by appending the necessary amount of corresponding closing tags
			// currently we ignore the nesting, i.e. all closing tags are appended at the end.
			// This simple approach may fail in some cases...
			preg_match_all( '#<\s*(/?ref|/?pre|/?nowiki)(\s+[^>]*?)?>#im', $cut, $matches );
			$tags = [ 'ref' => 0, 'pre' => 0, 'nowiki' => 0 ];

			foreach ( $matches[1] as $tag ) {
				$tagName = ltrim( $tag, '/' );
				$tags[$tagName] += str_starts_with( $tag, '/' ) ? -1 : 1;
			}

			foreach ( $tags as $tagName => $level ) {
				// Avoid empty <ref> tag
				if ( $tagName === 'ref' && str_ends_with( $cut, '<ref>' ) ) {
					$cut = substr( $cut, 0, -5 );
					$level--;
				}

				$cut .= str_repeat( "</$tagName>", max( 0, $level ) );
			}

			return $cut . $link;
		}

		if ( $limit === 0 ) {
			return $link;
		}

		// Otherwise we recurse and try again with twice the limit size; this will lead to bigger output but
		// it will at least produce some output at all; otherwise the reader might think that there
		// is no information at all.
		return self::limitTranscludedText( $text, $limit * 2, $link );
	}

	public static function includeHeading(
		Parser $parser,
		string $page,
		string $sec,
		string $to,
		array &$sectionHeading,
		bool $recursionCheck,
		int $maxLength,
		string $link,
		bool $trim,
		array $skipPattern
	): array {
		$text = '';
		if ( !self::text( $parser, $page, $text ) ) {
			return [ $text ];
		}

		// Throw away comments
		$text = preg_replace( '/<!--.*?-->/s', '', $text );
		return self::extractHeadingFromText(
			parser: $parser,
			page: $page,
			text: $text,
			sec: $sec,
			to: $to,
			sectionHeading: $sectionHeading,
			recursionCheck: $recursionCheck,
			maxLength: $maxLength,
			cLink: $link,
			trim: $trim,
			skipPattern: $skipPattern
		);
	}

	/**
	 * Extract section(s) from wikitext based on a heading match
	 */
	public static function extractHeadingFromText(
		Parser $parser,
		string $page,
		string $text,
		string $sec,
		string $to,
		array &$sectionHeading,
		bool $recursionCheck,
		int $maxLength,
		string $cLink,
		bool $trim,
		array $skipPattern
	): array {
		$output = [ '' ];
		$n = 0;
		$nr = 0;

		// Check if section selector is a numbered section
		if ( preg_match( '/^%-?[1-9][0-9]*$/', $sec ) ) {
			$nr = (int)substr( $sec, 1 );
		} elseif ( preg_match( '/^%0$/', $sec ) ) {
			// Special case: transclude text before the first heading
			$nr = -2;
		}

		// Determine whether heading match is plain text or regex
		$isPlain = true;
		if ( $sec !== '' && ( str_starts_with( $sec, '#' ) || str_starts_with( $sec, '@' ) ) ) {
			$sec = substr( $sec, 1 );
			$isPlain = false;
		}

		while ( true ) {
			$headLine = '';
			$beginOff = 0;

			// Check if section is empty (match all headings)
			if ( $sec === '' ) {
				$headLength = 6;
			} else {
				// Build match pattern depending on numeric/plain/regex match
				$pat = match ( true ) {
					$nr !== 0 => '^(={1,6})\s*[^=\s\n][^\n=]*\s*\1\s*$',
					$isPlain => '^(={1,6})\s*' . preg_quote( $sec, '/' ) . '\s*\1\s*$',
					default => '^(={1,6})\s*' . str_replace( '/', '\/', $sec ) . '\s*\1\s*$',
				};

				// Match against the section heading
				if ( preg_match( "/$pat/im", $text, $m, PREG_OFFSET_CAPTURE ) ) {
					$beginOff = end( $m )[1];
					$headLength = strlen( $m[1][0] );
					$headLine = trim( $m[0][0], " \t\n=" );
				} elseif ( $nr === -2 ) {
					// No heading found, fallback to full text
					$m[1][1] = strlen( $text ) + 1;
				} else {
					// No match, return empty output
					return $output;
				}
			}

			// Construct link formatting
			$link = match ( true ) {
				$cLink === 'default' => " [[$page#$headLine|..→]]",
				str_contains( $cLink, 'img=' ) => str_replace(
					'img=', "<linkedimage>page=$page#$headLine\nimg=Image:", $cLink
				) . "\n</linkedimage>",
				!str_contains( $cLink, '%SECTION%' ) => " [[$page#$headLine|$cLink]]",
				default => str_replace( '%SECTION%', "$page#$headLine", $cLink ),
			};

			// Handle special case: transclude content before first heading
			if ( $nr === -2 ) {
				$output[0] = self::parse(
					parser: $parser,
					text: substr( $text, 0, $m[1][1] - 1 ),
					part1: "heading:$page|$sec",
					recursionCheck: $recursionCheck,
					maxLength: $maxLength,
					link: $link,
					trim: $trim,
					skipPattern: $skipPattern
				);

				return $output;
			}

			$endOff = null;

			// Try to match target end heading if provided
			if ( $to !== '' ) {
				$pat = $isPlain
					? '^(={1,6})\s*' . preg_quote( $to, '/' ) . '\s*\1\s*$'
					: '^(={1,6})\s*' . str_replace( '/', '\/', $to ) . '\s*\1\s*$';

				if ( preg_match( "/$pat/im", $text, $mm, PREG_OFFSET_CAPTURE, $beginOff ) ) {
					$endOff = $mm[0][1] - 1;
				}
			}

			// If no end offset yet, find next heading of same or higher level
			if ( $endOff === null ) {
				$headLength ??= 6;
				$pat = $nr !== 0
					? '^(={1,6})\s*[^\s\n=][^\n=]*\s*\1\s*$'
					: "^(={1,$headLength})(?!=)\s*.*?\1\s*$";

				if ( preg_match( "/$pat/im", $text, $mm, PREG_OFFSET_CAPTURE, $beginOff ) ) {
					$endOff = $mm[0][1] - 1;
				} elseif ( $sec === '' ) {
					$endOff = -1;
				}
			}

			// Extract the section content based on matched offsets
			$piece = $endOff !== null
				? substr( $text, $beginOff, $endOff - $beginOff )
				: substr( $text, $beginOff );

			if ( $sec === '' || $endOff === null || ( $endOff === 0 && $sec !== '' ) ) {
				break;
			}

			$text = substr( $text, $endOff );

			// Store matched heading
			$sectionHeading[$n] = $headLine;

			// Output based on mode: single section, last section, or match by name
			if ( $nr === 1 ) {
				// Output n-th section and done
				$output[0] = self::parse(
					parser: $parser,
					text: $piece,
					part1: "heading:$page|$sec",
					recursionCheck: $recursionCheck,
					maxLength: $maxLength,
					link: $link,
					trim: $trim,
					skipPattern: $skipPattern
				);
				break;
			}

			if ( $nr === -1 && $endOff === null ) {
				// Output last section and done
				$output[0] = self::parse(
					parser: $parser,
					text: $piece,
					part1: "heading:$page|$sec",
					recursionCheck: $recursionCheck,
					maxLength: $maxLength,
					link: $link,
					trim: $trim,
					skipPattern: $skipPattern
				);
				break;
			}

			if ( $nr > 1 ) {
				// Skip until we reach the n-th section
				$nr--;
				continue;
			}

			// Output section by name and continue search for another section with the same name
			$output[$n++] = self::parse(
				parser: $parser,
				text: $piece,
				part1: "heading:$page|$sec",
				recursionCheck: $recursionCheck,
				maxLength: $maxLength,
				link: $link,
				trim: $trim,
				skipPattern: $skipPattern
			);
		}

		return $output;
	}

	/**
	 * Template inclusion - find the place(s) where template1 is called,
	 * replace its name by template2, then expand template2 and return the result
	 * we return an array containing all occurences of the template call which match the condition "$mustMatch"
	 * and do NOT match the condition "$mustNotMatch" (if specified)
	 * we use a callback function to format retrieved parameters, accessible via $lister->formatTemplateArg()
	 */
	public static function includeTemplate(
		Parser $parser,
		Lister $lister,
		int $dplNr,
		Article $article,
		string $template1,
		string $template2,
		string $defaultTemplate,
		string $mustMatch,
		string $mustNotMatch,
		bool $matchParsed,
		string $catlist
	): array {
		$title = $article->mTitle;
		$page = $title->getPrefixedText();
		$date = $article->myDate;
		$user = $article->mUserLink;

		$text = preg_replace( '/<!--.*?-->/s', '', $parser->fetchTemplateAndTitle( $title )[0] );
		if ( $template1 !== '' && $template1[0] === '#' ) {
			$template1 = substr( $template1, 1 );
			$template2 = substr( $template2, 1 );
			$defaultTemplate = substr( $defaultTemplate, 1 );

			$text2 = preg_replace( '/{{\s*#(' . $template1 . ')(\s*[:}])/i', '°³²|%PFUNC%=$1$2|', $text );
			$tCalls = preg_split( '/°³²/', ' ' . $text2 );

			foreach ( $tCalls as $i => $tCall ) {
				$n = strpos( $tCall, ':' );
				if ( $n !== false ) {
					$tCalls[$i][$n] = ' ';
				}
			}
		} elseif ( $template1 !== '' && $template1[0] === '~' ) {
			$template1 = substr( $template1, 1 );
			$template2 = substr( $template2, 1 );
			$defaultTemplate = substr( $defaultTemplate, 1 );

			$text2 = preg_replace( '/<\s*(' . $template1 . ')\s*>/i', '°³²|%TAG%=$1|%TAGBODY%=', $text );
			$tCalls = preg_split( '/°³²/', ' ' . $text2 );

			foreach ( $tCalls as $i => $tCall ) {
				$tCalls[$i] = preg_replace( '/<\s*\/' . $template1 . '\s*>.*/is', '}}', $tCall );
			}
		} else {
			$contLang = MediaWikiServices::getInstance()->getContentLanguage();
			$nsNames = $contLang->getNamespaces();
			$tCalls = preg_split(
				'/{{\s*(Template:|' . $nsNames[NS_TEMPLATE] . ':)?' .
				self::spaceOrUnderscore( preg_quote( $template1, '/' ) ) . '\s*[|}]/i',
				' ' . $text
			);

			foreach ( $tCalls as $nr => $tCall ) {
				$tCalls[$nr] = ( $tCall[0] === '}' ? '}' : '|' ) . $tCall;
			}
		}

		$output = [];
		$extractParm = [];

		if (
			strlen( $template2 ) > strlen( $template1 ) &&
			substr( $template2, 0, strlen( $template1 ) + 1 ) === ( $template1 . ':' )
		) {
			$extractParm = preg_split( '/:\s*/s', trim( substr( $template2, strlen( $template1 ) + 1 ) ) );
		}

		if ( count( $tCalls ) <= 1 ) {
			if ( $extractParm !== [] ) {
				$output[0] = $lister->formatTemplateArg( '', $dplNr, 0, true, -1, $article );
				for ( $i = 1, $len = count( $extractParm ); $i < $len; $i++ ) {
					$output[0] .= "\n|" . $lister->formatTemplateArg( '', $dplNr, $i, true, -1, $article );
				}
			} else {
				$output[0] = self::callParserPreprocess( $parser,
					"{{{$defaultTemplate}|%PAGE%=$page|%TITLE%={$title->getText()}|%DATE%=$date|%USER%=$user}}",
					$parser->getPage(), $parser->getOptions()
				);
			}

			return $output;
		}

		$output[0] = '';
		$n = -2;
		$firstCall = true;

		foreach ( $tCalls as $tCall ) {
			if ( $n === -2 ) {
				$n++;
				continue;
			}

			if ( $extractParm === [] ) {
				$cbrackets = 0;
				$templateCall = '{{' . $template2 . $tCall;
				$size = strlen( $templateCall );

				for ( $i = 0; $i < $size; $i++ ) {
					$c = $templateCall[$i];
					$cbrackets += (int)( $c === '{' ) - (int)( $c === '}' );

					if ( $cbrackets === 0 ) {
						$callSegment = substr( $templateCall, 0, $i - 1 );
						if ( (
							$mustMatch === '' || preg_match( $mustMatch, $callSegment )
						) && (
							$mustNotMatch === '' || !preg_match( $mustNotMatch, $callSegment )
						) ) {
							$invocation = $callSegment;
							$argChain = "$invocation|%PAGE%=$page|%TITLE%={$title->getText()}";

							if ( $catlist !== '' ) {
								$argChain .= "|%CATLIST%=$catlist";
							}

							$args = substr( $invocation, strlen( $template2 ) + 2 );
							$encodedArgs = str_replace(
								[ '{', '}', '|' ],
								[ '❴', '❵', '§' ],
								$args
							);

							$argChain .= "|%DATE%=$date|%USER%=$user|%ARGS%=$encodedArgs}}";
							$output[++$n] = self::callParserPreprocess(
								$parser, $argChain, $parser->getPage(),
								$parser->getOptions()
							);
						}
						break;
					}
				}
				continue;
			}

			$cbrackets = 2;
			$templateCall = $tCall;
			$size = strlen( $templateCall );
			$parms = [];
			$parm = '';
			$hasParm = false;

			for ( $i = 0; $i < $size; $i++ ) {
				$c = $templateCall[$i];
				$cbrackets += (int)( $c === '{' || $c === '[' ) - (int)( $c === '}' || $c === ']' );

				if ( $cbrackets === 2 && $c === '|' ) {
					$parms[] = trim( $parm );
					$hasParm = true;
					$parm = '';
				} else {
					$parm .= $c;
				}

				if ( $cbrackets === 0 ) {
					if ( $hasParm ) {
						$parms[] = trim( substr( $parm, 0, -2 ) );
					}

					array_shift( $parms );
					$callText = substr( $templateCall, 0, $i - 1 );
					$parsedMatch = $matchParsed ? $parser->recursiveTagParse( $callText ) : $callText;

					if ( (
						$mustMatch === '' || preg_match( $mustMatch, $parsedMatch )
					) && (
						$mustNotMatch === '' || !preg_match( $mustNotMatch, $parsedMatch )
					) ) {
						$output[++$n] = '';
						$second = false;

						foreach ( $extractParm as $exParmKey => $exParm ) {
							$maxlen = -1;
							$limpos = strpos( $exParm, '[' );

							if ( $limpos > 0 && str_ends_with( $exParm, ']' ) ) {
								$maxlen = (int)substr( $exParm, $limpos + 1, -1 - $limpos );
								$exParm = substr( $exParm, 0, $limpos );
							}

							if ( $second && ( $output[$n] === '' || !str_ends_with( $output[$n], "\n" ) ) ) {
								$output[$n] .= "\n|";
							} elseif ( $second ) {
								$output[$n] .= '|';
							}

							$second = true;
							$found = false;

							if ( str_contains( $exParm, '%' ) ) {
								$found = true;
								$output[$n] .= $lister->formatTemplateArg(
									arg: $exParm,
									s: $dplNr,
									argNr: $exParmKey,
									firstCall: $firstCall,
									maxLength: $maxlen,
									article: $article
								);
							} elseif ( !$found ) {
								$exParmQuote = str_replace( '/', '\/', $exParm );
								foreach ( $parms as $parm ) {
									if ( !preg_match( "/^\s*$exParmQuote\s*=/", $parm ) ) {
										continue;
									}
									$found = true;
									$output[$n] .= $lister->formatTemplateArg(
										arg: preg_replace( "/^$exParmQuote\s*=\s*/", '', $parm ),
										s: $dplNr,
										argNr: $exParmKey,
										firstCall: $firstCall,
										maxLength: $maxlen,
										article: $article
									);
									break;
								}
							}

							if ( !$found && filter_var( $exParm, FILTER_VALIDATE_INT ) !== false ) {
								$np = 0;
								foreach ( $parms as $parm ) {
									if ( str_contains( $parm, '=' ) ) {
										continue;
									}

									if ( ++$np !== (int)$exParm ) {
										continue;
									}

									$found = true;
									$output[$n] .= $lister->formatTemplateArg(
										arg: $parm,
										s: $dplNr,
										argNr: $exParmKey,
										firstCall: $firstCall,
										maxLength: $maxlen,
										article: $article
									);
									break;
								}
							}

							if ( !$found ) {
								$output[$n] .= $lister->formatTemplateArg(
									arg: '',
									s: $dplNr,
									argNr: $exParmKey,
									firstCall: $firstCall,
									maxLength: $maxlen,
									article: $article
								);
							}
						}
					}
					break;
				}
			}

			$firstCall = false;
		}

		return $output;
	}

	private static function spaceOrUnderscore( string $pattern ): string {
		// Returns a pattern that matches underscores as well as spaces.
		return str_replace( ' ', '[ _]', $pattern );
	}

	/**
	 * Preprocess given text according to the globally-configured method
	 *
	 * The default method uses Parser::preprocess() which does the job, but clears the internal cache every time.
	 * The improved method uses Parser::recursivePreprocess() that saves a decent amount of processing time
	 * by preserving the internal cache leveraging the repetitive call pattern.
	 *
	 * Parser::preprocess() was mainly called from self::includeTemplate() for the same template(s) with different
	 * set of arguments for each article found. In the original implementation using Parser::preprocess(),
	 * the internal cache is cleared at each call and parsing the same template text into template DOM is repeated
	 * multiple times.
	 *
	 * Using Parser::recursivePreprocess() prevents the cache clear, and thus repetitive calls reuse the
	 * previously generated template DOM which brings a decent performance improvement when called multiple times.
	 */
	private static function callParserPreprocess(
		Parser $parser,
		string $text,
		?PageReference $page,
		ParserOptions $options
	): string {
		$config = Config::getInstance();
		if ( $config->get( 'recursivePreprocess' ) ) {
			self::softResetParser( $parser );
			$parser->setOutputType( Parser::OT_PREPROCESS );

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
			if ( !isset( $reflectionCache[$property] ) ) {
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
