<?php

namespace MediaWiki\Extension\DynamicPageList4;

use ExtVariables;
use LogicException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\DynamicPageList4\Heading\Heading;
use MediaWiki\Extension\DynamicPageList4\HookHandlers\Eliminate;
use MediaWiki\Extension\DynamicPageList4\HookHandlers\Reset;
use MediaWiki\Extension\DynamicPageList4\Lister\Lister;
use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\Title;
use function array_filter;
use function array_intersect;
use function array_key_last;
use function array_keys;
use function array_merge;
use function array_reverse;
use function array_slice;
use function array_sum;
use function array_values;
use function asort;
use function count;
use function date;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function mb_strtoupper;
use function microtime;
use function min;
use function preg_replace;
use function preg_split;
use function random_int;
use function range;
use function shuffle;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function substr_count;
use function trim;
use function ucfirst;
use const NS_CATEGORY;
use const NS_FILE;

class Parse {

	private readonly Config $config;
	private readonly Logger $logger;
	private readonly Parameters $parameters;
	private readonly WebRequest $request;

	private string $header = '';
	private string $footer = '';
	private string $output = '';

	private array $replacementVariables = [];
	private array $urlArguments = [
		'DPL_offset',
		'DPL_count',
		'DPL_fromTitle',
		'DPL_findTitle',
		'DPL_toTitle',
	];

	public function __construct() {
		$this->config = Config::getInstance();
		$this->logger = new Logger();
		$this->parameters = new Parameters();
		$this->request = RequestContext::getMain()->getRequest();
	}

	/**
	 * The real callback function for converting the input text to wiki text output
	 */
	public function parse(
		string $input,
		Parser $parser,
		array &$reset,
		array &$eliminate,
		bool $isParserTag
	): string {
		$dplStartTime = microtime( true );

		// Reset headings when being ran more than once in the same page load.
		Article::resetHeadings();
		$title = Title::castFromPageReference( $parser->getPage() );

		// Check that we are not in an infinite transclusion loop
		// @phan-suppress-next-line PhanDeprecatedProperty
		if ( isset( $parser->mTemplatePath[$title->getPrefixedText()] ) ) {
			$this->logger->addMessage( Constants::WARN_TRANSCLUSIONLOOP, $title->getPrefixedText() );
			return $this->getFullOutput( totalResults: 0, skipHeaderFooter: true );
		}

		// Check if DPL shall only be executed from protected pages.
		$restrictionStore = MediaWikiServices::getInstance()->getRestrictionStore();
		if (
			$this->config->get( 'runFromProtectedPagesOnly' ) &&
			$title && !$restrictionStore->isProtected( $title, 'edit' )
		) {
			// Ideally we would like to allow using a DPL query if the query itself is coded on a
			// template page which is protected. Then there would be no need for the article to
			// be protected. However, how can one find out from which wiki source an extension
			// has been invoked???
			$this->logger->addMessage( Constants::FATAL_NOTPROTECTED, $title->getPrefixedText() );
			return $this->getFullOutput( totalResults: 0, skipHeaderFooter: true );
		}

		/************************************/
		/* Check for URL Arguments in Input */
		/************************************/
		if ( str_contains( $input, '{%DPL_' ) ) {
			foreach ( range( 1, 5 ) as $index ) {
				$this->urlArguments[] = "DPL_arg$index";
			}
		}

		$input = $this->resolveUrlArguments( $input, $this->urlArguments );
		$this->getUrlArgs( $parser );

		$this->parameters->setParameter( 'offset', $this->request->getInt(
			'DPL_offset', $this->parameters->getData( 'offset' )['default']
		) );

		/***************************************/
		/* User Input preparation and parsing. */
		/***************************************/
		$cleanParameters = $this->prepareUserInput( $input );
		if ( $cleanParameters === [] ) {
			// Short circuit.
			$this->logger->addMessage( Constants::FATAL_NOSELECTION );
			return $this->getFullOutput( totalResults: 0, skipHeaderFooter: true );
		}

		$cleanParameters = Parameters::sortByPriority( $cleanParameters );

		// To check if pseudo-category of Uncategorized pages is included
		$this->parameters->setParameter( 'includeuncat', false );

		foreach ( $cleanParameters as $parameter => $options ) {
			foreach ( $options as $option ) {
				// Parameter functions return true or false. The full parameter data will be
				// passed into the Query object later.
				if ( !$this->parameters->processParameter( $parameter, $option ) ) {
					// Do not build this into the output just yet. It will be collected at the end.
					$this->logger->addMessage( Constants::WARN_WRONGPARAM, $parameter, $option );
				}
			}
		}

		/*************************/
		/* Execute and Exit Only */
		/*************************/
		$exec = $this->parameters->getParameter( 'execandexit' );
		if ( $exec ) {
			// The keyword "geturlargs" is used to return the Url arguments and do nothing else.
			// In all other cases we return the value of the argument which may contain parser function calls.
			return $exec === 'geturlargs' ? '' : $exec;
		}

		// Construct internal keys for TableRow according to the structure of "include".
		// This will be needed in the output phase.
		$secLabels = $this->parameters->getParameter( 'seclabels' ) ?? [];
		if ( $secLabels !== [] ) {
			$this->parameters->setParameter( 'tablerow', $this->updateTableRowKeys(
				$this->parameters->getParameter( 'tablerow' ),
				$secLabels
			) );
		}

		/****************/
		/* Check Errors */
		/****************/
		if ( !$this->doQueryErrorChecks() ) {
			return $this->getFullOutput( totalResults: 0, skipHeaderFooter: true );
		}

		$needsCalcRows = !$this->config->get( 'allowUnlimitedResults' ) &&
			$this->parameters->getParameter( 'goal' ) !== 'categories' &&
			str_contains(
				$this->parameters->getParameter( 'resultsheader' ) .
				$this->parameters->getParameter( 'noresultsheader' ) .
				$this->parameters->getParameter( 'resultsfooter' ),
				'%TOTALPAGES%'
			);

		/***/
		/* Query */
		/***/
		try {
			$query = new Query( $this->parameters );
			$currentTitle = $parser->getPage();
			$profilingContext = $currentTitle instanceof Title
				? str_replace( [ '*', '/' ], '-', $currentTitle->getPrefixedDBkey() )
				: '';

			$rows = $query->buildAndSelect( $needsCalcRows, $profilingContext );
			if ( $rows === false ) {
				// This error path is very fast (We exit immediately if poolcounter is full)
				// Thus it should be safe to try again in ~5 minutes.
				$parser->getOutput()->updateCacheExpiry( 4 * 60 + random_int( 0, 120 ) );

				// All pool counter threads in use.
				$this->logger->addMessage( Constants::FATAL_POOLCOUNTER );
				return $this->getFullOutput( totalResults: 0, skipHeaderFooter: true );
			}
		} catch ( LogicException $e ) {
			$this->logger->addMessage( Constants::FATAL_SQLBUILDERROR, $e->getMessage() );
			return $this->getFullOutput( totalResults: 0, skipHeaderFooter: true );
		}

		$foundRows = $rows['count'] ?? null;
		unset( $rows['count'] );

		$numRows = count( $rows );
		$articles = $this->processQueryResults( $rows, $parser );

		$sql = $query->getSqlQuery();
		if ( $sql !== '' ) {
			$this->addOutput( "$sql\n" );
		}

		$parser->addTrackingCategory( 'dpl-tracking-category' );

		// Preset these to defaults.
		$this->setVariable( 'TOTALPAGES', '0' );
		$this->setVariable( 'PAGES', '0' );
		$this->setVariable( 'VERSION', Utils::getVersion() );

		/*********************/
		/* Handle No Results */
		/*********************/
		if ( $numRows === 0 || $articles === [] ) {
			return $this->getFullOutput( totalResults: 0, skipHeaderFooter: false );
		}

		// Backward scrolling: If the user specified only titlelt with descending reverse the output order.
		if (
			$this->parameters->getParameter( 'titlelt' ) &&
			!$this->parameters->getParameter( 'titlegt' ) &&
			$this->parameters->getParameter( 'order' ) === 'descending'
		) {
			$articles = array_reverse( $articles );
		}

		// Special sort for card suits (Bridge)
		if ( $this->parameters->getParameter( 'ordersuitsymbols' ) ) {
			$articles = $this->cardSuitSort( $articles );
		}

		/*******************/
		/* Generate Output */
		/*******************/
		$lister = Lister::newFromStyle( $this->parameters->getParameter( 'mode' ), $this->parameters, $parser );
		$heading = Heading::newFromStyle( $this->parameters->getParameter( 'headingmode' ), $this->parameters );
		$this->addOutput( $heading !== null ?
			$heading->format( $articles, $lister ) :
			$lister->format( $articles )
		);

		if ( $foundRows === null ) {
			// Get row count after calling format() otherwise the count will be inaccurate.
			$foundRows = $lister->getRowCount();
		}

		/*******************************/
		/* Replacement Variables       */
		/*******************************/

		// Guaranteed to be an accurate count if SQL_CALC_FOUND_ROWS was used.
		// Otherwise only accurate if results are less than the SQL LIMIT.
		$this->setVariable( 'TOTALPAGES', (string)$foundRows );

		// This could be different than TOTALPAGES. PAGES represents the total
		// results within the constraints of SQL LIMIT.
		$this->setVariable( 'PAGES', (string)$lister->getRowCount() );

		// Replace %DPLTIME% by execution time and timestamp in header and footer
		$dplTime = sprintf( '%.3f sec. (%s)', microtime( true ) - $dplStartTime, date( 'Y/m/d H:i:s' ) );
		$this->setVariable( 'DPLTIME', $dplTime );

		// Replace %LASTTITLE% / %LASTNAMESPACE% by the last title found in header and footer
		$first = $articles[0]->mTitle;
		$last = $articles[array_key_last( $articles )]->mTitle;

		$firstNamespace = $first->getNsText();
		$firstTitle = $first->getDBkey();
		$lastNamespace = $last->getNsText();
		$lastTitle = $last->getDBkey();

		$this->setVariable( 'FIRSTNAMESPACE', $firstNamespace );
		$this->setVariable( 'FIRSTTITLE', $firstTitle );
		$this->setVariable( 'LASTNAMESPACE', $lastNamespace );
		$this->setVariable( 'LASTTITLE', $lastTitle );
		$this->setVariable( 'SCROLLDIR', $this->parameters->getParameter( 'scrolldir' ) ?? '' );

		/*******************************/
		/* Scroll Variables            */
		/*******************************/
		$this->defineScrollVariables( [
			'DPL_firstNamespace' => $firstNamespace,
			'DPL_firstTitle' => $firstTitle,
			'DPL_lastNamespace' => $lastNamespace,
			'DPL_lastTitle' => $lastTitle,
			'DPL_scrollDir' => $this->parameters->getParameter( 'scrolldir' ),
			'DPL_time' => $dplTime,
			'DPL_count' => $this->parameters->getParameter( 'count' ),
			'DPL_totalPages' => $foundRows,
			'DPL_pages' => $lister->getRowCount(),
		], $parser );

		$expiry = $this->parameters->getParameter( 'allowcachedresults' ) || $this->config->get( 'alwaysCacheResults' )
			? $this->parameters->getParameter( 'cacheperiod' ) ?? 3600
			: 0;
		$parser->getOutput()->updateCacheExpiry( $expiry );

		$finalOutput = $this->getFullOutput(
			totalResults: $foundRows,
			skipHeaderFooter: false
		);

		$this->triggerEndResets( $finalOutput, $reset, $eliminate, $isParserTag, $parser );
		return $finalOutput;
	}

	/** @return Article[] */
	private function processQueryResults( array $rows, Parser $parser ): array {
		/*******************************/
		/* Random Count Pick Generator */
		/*******************************/
		$randomCount = $this->parameters->getParameter( 'randomcount' );
		$pick = [];

		if ( $randomCount > 0 ) {
			$nResults = count( $rows );

			// Constrain the total amount of random results to not be greater than the total results.
			$randomCount = min( $randomCount, $nResults );

			// Generate pick numbers for results.
			$pick = range( 1, $nResults );

			// Shuffle and select the first N picks
			shuffle( $pick );
			$pick = array_slice( $pick, 0, $randomCount );
		}

		/**********************/
		/* Article Processing */
		/**********************/
		$articles = [];
		foreach ( array_values( $rows ) as $index => $row ) {
			$position = $index + 1;
			// In random mode skip articles which were not chosen.
			if ( $randomCount > 0 && !in_array( $position, $pick, true ) ) {
				continue;
			}

			if ( $this->parameters->getParameter( 'goal' ) === 'categories' ) {
				$pageNamespace = NS_CATEGORY;
				$pageTitle = $row->cl_to;
			} elseif ( $this->parameters->getParameter( 'openreferences' ) ) {
				$imageContainer = $this->parameters->getParameter( 'imagecontainer' ) ?? [];
				if ( $imageContainer !== [] ) {
					$pageNamespace = NS_FILE;
					$pageTitle = $row->il_to;
				} else {
					// Maybe non-existing title
					$pageNamespace = $row->lt_namespace;
					$pageTitle = $row->lt_title;
				}
			} else {
				// Existing PAGE TITLE
				$pageNamespace = $row->page_namespace;
				$pageTitle = $row->page_title;
			}

			$title = Title::makeTitle( $pageNamespace, $pageTitle );
			$thisTitle = Title::castFromPageReference( $parser->getPage() );

			// Block recursion from happening by seeing if this result row is the page the DPL query was ran from.
			if ( $this->parameters->getParameter( 'skipthispage' ) && $thisTitle?->equals( $title ) ) {
				continue;
			}

			$articles[] = Article::newFromRow( $row, $this->parameters, $title, $pageNamespace, $pageTitle );
		}

		return $articles;
	}

	/**
	 * Do basic clean up and structuring of raw user input.
	 * @return array<string, list<string>>
	 */
	private function prepareUserInput( string $input ): array {
		// We replace double angle brackets with single angle brackets to avoid premature tag expansion in the input.
		// The ¦ symbol is an alias for |.
		// The combination '²{' and '}²' will be translated to double curly braces; this allows postponed template
		// execution which is crucial for DPL queries which call other DPL queries.
		$input = str_replace( [ '«', '»', '¦', '²{', '}²' ], [ '<', '>', '|', '{{', '}}' ], $input );

		// Standard new lines into the standard \n and clean up any hanging new lines.
		$input = str_replace( [ "\r\n", "\r" ], "\n", $input );
		$input = trim( $input, "\n" );
		$rawParameters = explode( "\n", $input );

		$parameters = [];
		foreach ( $rawParameters as $parameterOption ) {
			if ( $parameterOption === '' ) {
				// Softly ignore blank lines.
				continue;
			}

			if ( !str_contains( $parameterOption, '=' ) ) {
				$this->logger->addMessage( Constants::WARN_PARAMNOOPTION, $parameterOption );
				continue;
			}

			[ $parameter, $option ] = explode( '=', $parameterOption, 2 );
			$parameter = strtolower( str_replace( [ '<', '>' ], [ 'lt', 'gt' ], trim( $parameter ) ) );
			$option = trim( $option );

			if ( $parameter === '' || str_starts_with( $parameter, '#' ) ) {
				continue;
			}

			if ( !$this->parameters->exists( $parameter ) ) {
				$this->logger->addMessage( Constants::WARN_UNKNOWNPARAM, $parameter,
					implode( ', ', $this->parameters->getParametersForRichness() )
				);

				continue;
			}

			if ( !$this->parameters->testRichness( $parameter ) ) {
				continue;
			}

			// Ignore parameter settings without argument (except namespace for backward compatibility).
			if (
				$option === '' &&
				!in_array( $parameter, [ 'namespace', 'notnamespace' ], true )
			) {
				continue;
			}

			$parameters[$parameter][] = $option;
		}

		return $parameters;
	}

	private function addOutput( string $output ): void {
		$this->output .= $output;
	}

	private function getOutput(): string {
		return $this->output;
	}

	/**
	 * Return output optionally including header and footer.
	 */
	private function getFullOutput( int $totalResults, bool $skipHeaderFooter ): string {
		if ( !$skipHeaderFooter ) {
			$headerType = $this->getHeaderFooterType( 'header', $totalResults );
			$footerType = $this->getHeaderFooterType( 'footer', $totalResults );

			$this->setHeader( $headerType !== false ? $this->parameters->getParameter( $headerType ) : '' );
			$this->setFooter( $footerType !== false ? $this->parameters->getParameter( $footerType ) : '' );
		}

		if ( $totalResults === 0 && $this->getHeader() === '' && $this->getFooter() === '' ) {
			$this->logger->addMessage( Constants::WARN_NORESULTS );
		}

		$messages = $this->logger->getMessages( clearBuffer: false );
		return ( $messages ? implode( Html::element( 'br' ), $messages ) : '' )
			. $this->getHeader()
			. $this->getOutput()
			. $this->getFooter();
	}

	private function setHeader( string $header ): void {
		if ( Utils::getDebugLevel() === 5 ) {
			$header = Html::openElement( 'pre' ) .
				Html::openElement( 'nowiki' ) . $header;
		}

		$this->header = $this->replaceVariables( $header );
	}

	private function getHeader(): string {
		return $this->header;
	}

	private function setFooter( string $footer ): void {
		if ( Utils::getDebugLevel() === 5 ) {
			$footer .= Html::closeElement( 'nowiki' ) .
				Html::closeElement( 'pre' );
		}

		$this->footer = $this->replaceVariables( $footer );
	}

	private function getFooter(): string {
		return $this->footer;
	}

	/**
	 * Determine the header/footer type to use based on what output format
	 * parameters were chosen and the number of results.
	 */
	private function getHeaderFooterType( string $position, int $count ): string|false {
		if ( $position !== 'header' && $position !== 'footer' ) {
			return false;
		}

		if (
			$this->parameters->getParameter( 'results' . $position ) !== null &&
			( $count >= 2 || ( $this->parameters->getParameter( 'oneresult' . $position ) === null && $count >= 1 ) )
		) {
			return 'results' . $position;
		}

		if ( $count === 1 && $this->parameters->getParameter( 'oneresult' . $position ) !== null ) {
			return 'oneresult' . $position;
		}

		if ( $count === 0 && $this->parameters->getParameter( 'noresults' . $position ) !== null ) {
			return 'noresults' . $position;
		}

		return false;
	}

	/**
	 * Set a variable to be replaced with the provided text later at the end of the output.
	 */
	private function setVariable( string $variable, string $replacement ): void {
		$variable = '%' . mb_strtoupper( $variable, 'UTF-8' ) . '%';
		$this->replacementVariables[$variable] = $replacement;
	}

	/**
	 * Return text with variables replaced.
	 */
	private function replaceVariables( string $text ): string {
		return str_replace(
			array_keys( $this->replacementVariables ),
			array_values( $this->replacementVariables ),
			self::replaceNewLines( $text )
		);
	}

	/**
	 * Return text with custom new line characters replaced.
	 */
	public static function replaceNewLines( string $text ): string {
		return str_replace( [ '\n', '¶' ], "\n", $text );
	}

	/**
	 * Work through processed parameters and check for potential issues.
	 */
	private function doQueryErrorChecks(): bool {
		/**************************/
		/* Parameter Error Checks */
		/**************************/
		$totalCategories = 0;
		foreach ( [ 'category', 'notcategory' ] as $param ) {
			foreach ( $this->parameters->getParameter( $param ) ?? [] as $operatorTypes ) {
				foreach ( $operatorTypes as $categoryGroups ) {
					if ( is_string( $categoryGroups ) ) {
						// If $categoryGroups is a string, just count as 1.
						// This may be the case for notcategory.
						$totalCategories++;
						continue;
					}

					foreach ( $categoryGroups as $categories ) {
						if ( is_array( $categories ) ) {
							$totalCategories += count( $categories );
						}
					}
				}
			}
		}

		// Too many categories.
		if (
			$totalCategories > $this->config->get( 'maxCategoryCount' ) &&
			!$this->config->get( 'allowUnlimitedCategories' )
		) {
			$this->logger->addMessage( Constants::FATAL_TOOMANYCATS, $this->config->get( 'maxCategoryCount' ) );
			return false;
		}

		// Not enough categories.
		if ( $totalCategories < $this->config->get( 'minCategoryCount' ) ) {
			$this->logger->addMessage( Constants::FATAL_TOOFEWCATS, $this->config->get( 'minCategoryCount' ) );
			return false;
		}

		// Selection criteria needs to be found.
		if ( !$totalCategories && !$this->parameters->isSelectionCriteriaFound() ) {
			$this->logger->addMessage( Constants::FATAL_NOSELECTION );
			return false;
		}

		// ordermethod = sortkey requires ordermethod = category.
		$orderMethods = $this->parameters->getParameter( 'ordermethod' ) ?? [];

		// Throw an error in no categories were selected when using category sorting
		// modes or requesting category information.
		if (
			$totalCategories === 0 &&
			( in_array( 'categoryadd', $orderMethods, true ) ||
			$this->parameters->getParameter( 'addfirstcategorydate' ) )
		) {
			$this->logger->addMessage( Constants::FATAL_CATDATEBUTNOINCLUDEDCATS );
			return false;
		}

		// No more than one type of date at a time!
		// @TODO: Can this be fixed to allow all three later after fixing the Article class?
		if (
			(int)$this->parameters->getParameter( 'addpagetoucheddate' ) +
			(int)$this->parameters->getParameter( 'addfirstcategorydate' ) +
			(int)$this->parameters->getParameter( 'addeditdate' ) > 1
		) {
			$this->logger->addMessage( Constants::FATAL_MORETHAN1TYPEOFDATE );
			return false;
		}

		// The dominant section must be one of the sections mentioned in includepage.
		$dominantSection = $this->parameters->getParameter( 'dominantsection' );
		$secLabelsCount = count( $this->parameters->getParameter( 'seclabels' ) ?? [] );
		if ( $dominantSection > 0 && $secLabelsCount < $dominantSection ) {
			$this->logger->addMessage( Constants::FATAL_DOMINANTSECTIONRANGE, (string)$secLabelsCount );
			return false;
		}

		// Category-style output requested with not compatible order method.
		if (
			$this->parameters->getParameter( 'mode' ) === 'category' &&
			!array_intersect( $orderMethods, [ 'sortkey', 'title', 'titlewithoutnamespace' ] )
		) {
			$this->logger->addMessage( Constants::FATAL_WRONGORDERMETHOD,
				'mode=category', 'sortkey | title | titlewithoutnamespace'
			);

			return false;
		}

		// addpagetoucheddate = true with unappropriate order methods.
		if (
			$this->parameters->getParameter( 'addpagetoucheddate' ) &&
			!array_intersect( $orderMethods, [ 'pagetouched', 'title' ] )
		) {
			$this->logger->addMessage( Constants::FATAL_WRONGORDERMETHOD,
				'addpagetoucheddate=true', 'pagetouched | title'
			);

			return false;
		}

		// addeditdate = true but not (ordermethod = ..., firstedit or ordermethod = ..., lastedit).
		// Firstedit (resp. lastedit) -> add date of first (resp. last) revision.
		if (
			$this->parameters->getParameter( 'addeditdate' ) &&
			!array_intersect( $orderMethods, [ 'firstedit', 'lastedit' ] ) &&
			(
				$this->parameters->getParameter( 'allrevisionsbefore' ) ||
				$this->parameters->getParameter( 'allrevisionssince' ) ||
				$this->parameters->getParameter( 'firstrevisionsince' ) ||
				$this->parameters->getParameter( 'lastrevisionbefore' )
			)
		) {
			$this->logger->addMessage( Constants::FATAL_WRONGORDERMETHOD, 'addeditdate=true', 'firstedit | lastedit' );
			return false;
		}

		// adduser = true but not (ordermethod = ..., firstedit or ordermethod = ..., lastedit).
		/**
		 * @TODO allow to add user for other order methods.
		 * The fact is a page may be edited by multiple users.
		 * Which user(s) should we show? all? the first or the last one?
		 * Ideally, we could use values such as 'all', 'first' or 'last' for the adduser parameter.
		 */
		if (
			$this->parameters->getParameter( 'adduser' ) &&
			!array_intersect( $orderMethods, [ 'firstedit', 'lastedit' ] ) &&
			!$this->parameters->getParameter( 'allrevisionsbefore' ) &&
			!$this->parameters->getParameter( 'allrevisionssince' ) &&
			!$this->parameters->getParameter( 'firstrevisionsince' ) &&
			!$this->parameters->getParameter( 'lastrevisionbefore' )
		) {
			$this->logger->addMessage( Constants::FATAL_WRONGORDERMETHOD, 'adduser=true', 'firstedit | lastedit' );
			return false;
		}

		if (
			$this->parameters->getParameter( 'minoredits' ) &&
			!array_intersect( $orderMethods, [ 'firstedit', 'lastedit' ] )
		) {
			$this->logger->addMessage( Constants::FATAL_WRONGORDERMETHOD, 'minoredits', 'firstedit | lastedit' );
			return false;
		}

		// add*** parameters have no effect with 'mode=category' (only namespace/title can be viewed in this mode).
		if (
			$this->parameters->getParameter( 'mode' ) === 'category' && array_filter( [
				'addcategories',
				'addeditdate',
				'addfirstcategorydate',
				'addpagetoucheddate',
				'incpage',
				'adduser',
				'addauthor',
				'addcontribution',
				'addlasteditor',
			], fn ( string $param ): bool => $this->parameters->getParameter( $param ) ?? false )
		) {
			$this->logger->addMessage( Constants::WARN_CATOUTPUTBUTWRONGPARAMS );
		}

		// headingmode has effects with ordermethod on multiple components only.
		if ( $this->parameters->getParameter( 'headingmode' ) !== 'none' && count( $orderMethods ) < 2 ) {
			$this->logger->addMessage( Constants::WARN_HEADINGBUTSIMPLEORDERMETHOD,
				$this->parameters->getParameter( 'headingmode' ), 'none'
			);

			$this->parameters->setParameter( 'headingmode', 'none' );
		}

		// The 'openreferences' parameter is incompatible with many other options.
		if (
			$this->parameters->isOpenReferencesConflict() &&
			$this->parameters->getParameter( 'openreferences' )
		) {
			$this->logger->addMessage( Constants::FATAL_OPENREFERENCES );
			return false;
		}

		return true;
	}

	/**
	 * Create keys for TableRow which represent the structure of the "include=" arguments.
	 */
	private function updateTableRowKeys( array $tableRow, array $sectionLabels ): array {
		$originalRow = $tableRow;
		$updatedRow = [];
		$sectionIndex = -1;
		$cellIndex = 0;

		foreach ( $sectionLabels as $label ) {
			$sectionIndex++;
			if ( !str_contains( $label, '}:' ) ) {
				if ( isset( $originalRow[$cellIndex] ) ) {
					$updatedRow[$sectionIndex] = $originalRow[$cellIndex];
				}
				$cellIndex++;
				continue;
			}

			[ , $columnPart ] = explode( '}:', $label, 2 );
			$columnCount = substr_count( $columnPart, ':' ) + 1;
			for ( $columnIndex = 0; $columnIndex < $columnCount; $columnIndex++, $cellIndex++ ) {
				if ( isset( $originalRow[$cellIndex] ) ) {
					$updatedRow["$sectionIndex.$columnIndex"] = $originalRow[$cellIndex];
				}
			}
		}

		return $updatedRow;
	}

	/**
	 * Resolve arguments in the input that would normally be in the URL.
	 */
	private function resolveUrlArguments( string $input, array $arguments ): string {
		foreach ( $arguments as $arg ) {
			$dplArg = $this->request->getText( $arg );
			if ( $dplArg === '' ) {
				$input = preg_replace( '/\{%' . $arg . ':(.*)%\}/U', '$1', $input );
				$input = str_replace( '{%' . $arg . '%}', '', $input );
				continue;
			}

			$input = preg_replace( '/\{%' . $arg . ':.*%\}/U', $dplArg, $input );
			$input = str_replace( '{%' . $arg . '%}', $dplArg, $input );
		}

		return $input;
	}

	/**
	 * This function uses the Variables extension to provide URL-arguments like &DPL_xyz=abc in the form
	 * of a variable which can be accessed as {{#var:xyz}} if Extension:Variables is installed.
	 */
	private function getUrlArgs( Parser $parser ): void {
		foreach ( $this->request->getValues() as $argName => $argValue ) {
			if ( !str_starts_with( $argName, 'DPL_' ) ) {
				continue;
			}

			Variables::setVar( [ '', '', $argName, $argValue ] );
			if ( ExtensionRegistry::getInstance()->isLoaded( 'Variables' ) ) {
				ExtVariables::get( $parser )->setVarValue( $argName, $argValue );
			}
		}
	}

	/**
	 * This function uses the Variables extension to provide navigation aids such as
	 * DPL_firstTitle, DPL_lastTitle, or DPL_findTitle. These variables can be accessed
	 * as {{#var:DPL_firstTitle}} if Extension:Variables is installed.
	 */
	private function defineScrollVariables( array $scrollVariables, Parser $parser ): void {
		foreach ( $scrollVariables as $variable => $value ) {
			Variables::setVar( [ '', '', $variable, $value ?? '' ] );
			if ( ExtensionRegistry::getInstance()->isLoaded( 'Variables' ) ) {
				ExtVariables::get( $parser )->setVarValue( $variable, $value ?? '' );
			}
		}
	}

	/**
	 * Trigger Resets and Eliminates that run at the end of parsing.
	 */
	private function triggerEndResets(
		string $output,
		array &$reset,
		array &$eliminate,
		bool $isParserTag,
		Parser $parser
	): void {
		$localParser = MediaWikiServices::getInstance()->getParserFactory()->create();

		$page = $parser->getPage();
		$parserOutput = $page ? $localParser->parse( $output, $page, $parser->getOptions() ) : null;

		$reset = array_merge( $reset, $this->parameters->getParameter( 'reset' ) ?? [] );
		$eliminate = array_merge( $eliminate, $this->parameters->getParameter( 'eliminate' ) ?? [] );

		if ( $isParserTag ) {
			// In tag mode 'eliminate' is the same as 'reset' for templates, categories, and images.
			foreach ( [ 'templates', 'categories', 'images' ] as $key ) {
				if ( !empty( $eliminate[$key] ) ) {
					$reset[$key] = true;
					$eliminate[$key] = false;
				}
			}
		} else {
			foreach ( [ 'templates', 'categories', 'images' ] as $key ) {
				if ( !empty( $reset[$key] ) ) {
					Utils::$createdLinks[ 'reset' . ucfirst( $key ) ] = true;
				}
			}
		}

		$hookContainer = MediaWikiServices::getInstance()->getHookContainer();
		if ( ( $isParserTag && isset( $reset['links'] ) ) || !$isParserTag ) {
			if ( isset( $reset['links'] ) ) {
				Utils::$createdLinks['resetLinks'] = true;
			}

			// Register a hook to reset links which were produced during parsing DPL output.
			$hookContainer->register( 'ParserAfterTidy', [ new Reset(), 'onParserAfterTidy' ] );
		}

		if ( array_sum( $eliminate ) ) {
			if ( $parserOutput ) {
				if ( !empty( $eliminate['links'] ) ) {
					// Trigger the mediawiki parser to find links, images, categories etc.
					// which are contained in the DPL output. This allows us to remove these
					// links from the link list later. If the article containing the DPL
					// statement itself uses one of these links they will be thrown away!
					Utils::$createdLinks[0] = [];
					foreach (
						$parserOutput->getLinkList( ParserOutputLinkTypes::LOCAL )
						as [ 'link' => $link, 'pageid' => $pageid ]
					) {
						Utils::$createdLinks[0][$link->getNamespace()][$link->getDBkey()] = $pageid;
					}
				}

				if ( !empty( $eliminate['templates'] ) ) {
					Utils::$createdLinks[1] = [];
					foreach (
						$parserOutput->getLinkList( ParserOutputLinkTypes::TEMPLATE )
						as [ 'link' => $link, 'pageid' => $pageid ]
					) {
						Utils::$createdLinks[1][$link->getNamespace()][$link->getDBkey()] = $pageid;
					}
				}

				if ( !empty( $eliminate['categories'] ) ) {
					Utils::$createdLinks[2] = [];
					foreach ( $parserOutput->getCategoryNames() as $name ) {
						Utils::$createdLinks[2][$name] = $parserOutput->getCategorySortKey( $name ) ?? '';
					}
				}

				if ( !empty( $eliminate['images'] ) ) {
					Utils::$createdLinks[3] = [];
					foreach (
						$parserOutput->getLinkList( ParserOutputLinkTypes::MEDIA )
						as [ 'link' => $link ]
					) {
						Utils::$createdLinks[3][$link->getDBkey()] = 1;
					}
				}
			}

			// Register a hook to reset links which were produced during parsing DPL output.
			$hookContainer->register( 'ParserAfterTidy', [ new Eliminate(), 'onParserAfterTidy' ] );
		}
	}

	/**
	 * Sort an array of Article objects by the card suit symbol.
	 *
	 * @param Article[] $articles
	 * @return Article[]
	 */
	private function cardSuitSort( array $articles ): array {
		$sortKeys = [];
		foreach ( $articles as $key => $article ) {
			$title = preg_replace( '/.*:/', '', $article->mTitle->getPrefixedText() );
			$tokens = preg_split( '/ - */', $title ) ?: [];
			$newKey = '';

			foreach ( $tokens as $token ) {
				$initial = strtolower( $token[0] ?? '' );
				if ( $initial >= '1' && $initial <= '7' ) {
					$newKey .= $initial;
					$suit = substr( $token, 1 );
					$newKey .= match ( strtolower( $suit ) ) {
						'♣' => '1',
						'♦' => '2',
						'♥' => '3',
						'♠' => '4',
						'sa', 'nt' => '5 ',
						default => $suit,
					};
					continue;
				}

				if ( $initial === 'p' ) {
					$newKey .= '0 ';
					continue;
				}

				if ( $initial === 'x' ) {
					$newKey .= '8 ';
					continue;
				}

				$newKey .= $token;
			}

			$sortKeys[$key] = $newKey;
		}

		asort( $sortKeys );

		$sortedArticles = [];
		foreach ( array_keys( $sortKeys ) as $oldKey ) {
			$sortedArticles[] = $articles[$oldKey];
		}

		return $sortedArticles;
	}
}
