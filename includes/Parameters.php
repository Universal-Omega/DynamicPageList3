<?php

namespace MediaWiki\Extension\DynamicPageList4;

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use PermissionsError;
use StringUtils;
use Wikimedia\Rdbms\IExpression;

class Parameters extends ParametersData {

	private readonly Config $config;

	/** Set parameter options. */
	private array $parameterOptions = [];

	/** Selection Criteria Found */
	private bool $selectionCriteriaFound = false;

	/** Open References Conflict */
	private bool $openReferencesConflict = false;

	/** Parameters that have already been processed. */
	private array $parametersProcessed = [];

	public function __construct() {
		parent::__construct();
		$this->config = Config::getInstance();
		$this->setDefaults();
	}

	/**
	 * Handle simple parameter functions.
	 */
	public function __call( string $parameter, array $arguments ): bool {
		$parameterData = $this->getData( $parameter );
		if ( $parameterData === false ) {
			return false;
		}

		if ( isset( $parameterData['permission'] ) ) {
			$user = RequestContext::getMain()->getUser();
			if ( !$user->isAllowed( $parameterData['permission'] ) ) {
				throw new PermissionsError( $parameterData['permission'] );
			}
		}

		$function = '_' . $parameter;
		$this->parametersProcessed[$parameter] = true;

		if ( method_exists( $this, $function ) ) {
			return call_user_func_array( [ $this, $function ], $arguments );
		}

		$option = $arguments[0];
		$parameter = strtolower( $parameter );
		$success = true;

		// Validate allowed values
		if ( isset( $parameterData['values'] ) && is_array( $parameterData['values'] ) ) {
			if ( !in_array( strtolower( $option ), $parameterData['values'], true ) ) {
				$success = false;
			}
		}

		// Case normalization
		if (
			( $parameterData['preserve_case'] ?? false ) === false &&
			( $parameterData['page_name_list'] ?? false ) !== true
		) {
			$option = strtolower( $option );
		}

		// Strip HTML
		if ( $parameterData['strip_html'] ?? false ) {
			$option = $this->stripHtmlTags( $option );
		}

		// Integer conversion
		if ( $parameterData['integer'] ?? false ) {
			if ( !is_numeric( $option ) ) {
				$option = $parameterData['default'] ?? null;
				$success = $option !== null;
			}
			$option = (int)$option;
		}

		// Boolean conversion
		if ( $parameterData['boolean'] ?? false ) {
			$option = $this->filterBoolean( $option );
			if ( $option === null ) {
				$success = false;
			}
		}

		// Timestamp handling
		if ( $parameterData['timestamp'] ?? false ) {
			$option = strtolower( $option );
			$option = match ( $option ) {
				'today', 'last hour', 'last day', 'last week',
				'last month', 'last year' => $option,
				default => wfTimestamp( TS_MW,
					str_pad( preg_replace( '#[^0-9]#', '', $option ), 14, '0' )
				) ?: false,
			};

			if ( $option === false ) {
				$success = false;
			}
		}

		// Page name list
		if ( $parameterData['page_name_list'] ?? false ) {
			$pageGroups = $this->getParameter( $parameter ) ?? [];
			$pages = $this->getPageNameList( $option,
				(bool)( $parameterData['page_name_must_exist'] ?? false )
			);

			if ( $pages === false ) {
				$success = false;
			} else {
				$pageGroups[] = $pages;
				$option = $pageGroups;
			}
		}

		// Regex pattern
		if ( isset( $parameterData['pattern'] ) ) {
			if ( preg_match( $parameterData['pattern'], $option, $matches ) ) {
				array_shift( $matches );
				$option = $matches;
			} else {
				$success = false;
			}
		}

		// DB format
		if ( $parameterData['db_format'] ?? false ) {
			$option = str_replace( ' ', '_', $option );
		}

		if ( $success ) {
			$this->setParameter( $parameter, $option );

			if ( $parameterData['set_criteria_found'] ?? false ) {
				$this->setSelectionCriteriaFound( true );
			}

			if ( $parameterData['open_ref_conflict'] ?? false ) {
				$this->setOpenReferencesConflict( true );
			}
		}

		return $success;
	}

	/**
	 * Sort cleaned parameter arrays by priority.
	 *
	 * Users can not be told to put the parameters into a specific order each time.
	 * Some parameters are dependent on each other coming in a certain order due to some
	 * procedural legacy issues.
	 */
	public static function sortByPriority( array $parameters ): array {
		// 'category' to get category headings first for ordermethod.
		// 'include'/'includepage' to make sure section labels are ready for 'table'.
		$priority = [
			'distinct' => 1,
			'openreferences' => 2,
			'ignorecase' => 3,
			'category' => 4,
			'title' => 5,
			'goal' => 6,
			'ordercollation' => 7,
			'ordermethod' => 8,
			'includepage' => 9,
			'include' => 10,
		];

		$first = [];
		foreach ( $priority as $parameter => $_ ) {
			if ( isset( $parameters[$parameter] ) ) {
				$first[$parameter] = $parameters[$parameter];
				unset( $parameters[$parameter] );
			}
		}

		return $first + $parameters;
	}

	/**
	 * Set Selection Criteria Found
	 */
	private function setSelectionCriteriaFound( bool $found ): void {
		$this->selectionCriteriaFound = $found;
	}

	/**
	 * Get Selection Criteria Found
	 */
	public function isSelectionCriteriaFound(): bool {
		return $this->selectionCriteriaFound;
	}

	/**
	 * Set Open References Conflict - See 'openreferences' parameter.
	 */
	private function setOpenReferencesConflict( bool $conflict ): void {
		$this->openReferencesConflict = $conflict;
	}

	/**
	 * Get Open References Conflict - See 'openreferences' parameter.
	 */
	public function isOpenReferencesConflict(): bool {
		return $this->openReferencesConflict;
	}

	/**
	 * Set default parameters based on ParametersData.
	 */
	private function setDefaults(): void {
		$this->setParameter( 'defaulttemplatesuffix', '.default' );
		foreach ( $this->getParametersForRichness() as $parameter ) {
			$data = $this->getData( $parameter );
			$default = $data['default'] ?? null;
			$isBoolean = $data['boolean'] ?? false;

			if ( $default !== null && !( $default === false && $isBoolean === true ) ) {
				if ( $parameter === 'debug' ) {
					Utils::setDebugLevel( $default );
				}

				$this->setParameter( $parameter, $default );
			}
		}
	}

	public function setParameter( string $parameter, mixed $option ): void {
		$this->parameterOptions[$parameter] = $option;
	}

	public function getParameter( string $parameter ): mixed {
		return $this->parameterOptions[$parameter] ?? null;
	}

	public function getAllParameters(): array {
		return self::sortByPriority( $this->parameterOptions );
	}

	/**
	 * Filter a standard boolean like value into an actual boolean.
	 */
	private function filterBoolean( string $boolean ): ?bool {
		return filter_var( $boolean, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
	}

	/**
	 * Strip <html> tags.
	 */
	private function stripHtmlTags( string $text ): string {
		return preg_replace( '#<.*?html.*?>#is', '', $text ) ?? '';
	}

	/**
	 * Get a list of valid page names.
	 */
	private function getPageNameList( string $text, bool $mustExist ): array|false {
		$list = [];
		foreach ( explode( '|', trim( $text ) ) as $page ) {
			$page = rtrim( trim( $page ), '\\' );
			if ( $page === '' ) {
				continue;
			}

			if ( $mustExist ) {
				$title = Title::newFromText( $page );
				if ( !$title ) {
					return false;
				}

				$list[] = $title;
				continue;
			}

			$list[] = $page;
		}

		return $list;
	}

	/**
	 * Check if a regular expression is valid.
	 */
	private function isRegexValid( array|string $regexes, bool $forDb = false ): bool {
		foreach ( (array)$regexes as $regex ) {
			$regex = trim( $regex );
			if ( $regex === '' ) {
				continue;
			}

			if ( $forDb ) {
				$regex = '#' . str_replace( '#', '\#', $regex ) . '#';
			}

			if ( !StringUtils::isValidPCRERegex( $regex ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Clean and test 'category' parameter.
	 */
	public function _category( string $option ): bool {
		$option = trim( html_entity_decode( $option, ENT_QUOTES ) );

		if ( $option === '' ) {
			return false;
		}

		$categories = [];
		$heading = str_starts_with( $option, '+' );
		$notHeading = !$heading && str_starts_with( $option, '-' );

		if ( $heading || $notHeading ) {
			$option = ltrim( $option, '+-' );
		}

		[ $parameters, $operator ] = str_contains( $option, '|' )
			? [ explode( '|', $option ), 'OR' ]
			: [ explode( '<&>', $option ), 'AND' ];

		foreach ( $parameters as $parameter ) {
			$parameter = trim( $parameter );
			if ( $parameter === '' ) {
				continue;
			}

			if ( $parameter === '_none_' ) {
				$this->setParameter( 'includeuncat', true );
				$categories[] = '';
				continue;
			}

			if ( str_starts_with( $parameter, '*' ) && strlen( $parameter ) >= 2 ) {
				$depth = str_starts_with( substr( $parameter, 1, 1 ), '*' ) ? 2 : 1;
				$parameter = ltrim( $parameter, '*' );
				$subCategories = Query::getSubcategories( $parameter, $depth );
				$subCategories[] = $parameter;

				foreach ( $subCategories as $subCategory ) {
					$title = Title::newFromText( $subCategory );
					if ( $title ) {
						$categories['OR'][] = $title->getDbKey();
					}
				}
			} else {
				$title = Title::newFromText( $parameter );
				if ( $title ) {
					$categories[$operator][] = $title->getDbKey();
				}
			}
		}

		if ( $categories === [] ) {
			return false;
		}

		$data = $this->getParameter( 'category' ) ?? [];
		$data['='] ??= [];

		foreach ( $categories as $operatorType => $categoryList ) {
			$data['='][$operatorType] ??= [];
			$data['='][$operatorType][] = $categoryList;
		}

		$this->setParameter( 'category', $data );

		if ( $heading ) {
			$this->setParameter( 'catheadings', array_unique(
				array_merge( $this->getParameter( 'catheadings' ) ?? [], $categories )
			) );
		}

		if ( $notHeading ) {
			$this->setParameter( 'catnotheadings', array_unique(
				array_merge( $this->getParameter( 'catnotheadings' ) ?? [], $categories )
			) );
		}

		$this->setOpenReferencesConflict( true );

		return true;
	}

	/**
	 * Clean and test 'categoryregexp' parameter.
	 */
	public function _categoryregexp( string $option ): bool {
		if ( !$this->isRegexValid( $option, true ) ) {
			return false;
		}

		$data = $this->getParameter( 'category' );

		// REGEXP input only supports AND operator.
		$data['REGEXP']['AND'][] = [ $option ];

		$this->setParameter( 'category', $data );
		$this->setOpenReferencesConflict( true );

		return true;
	}

	/**
	 * Clean and test 'categorymatch' parameter.
	 */
	public function _categorymatch( string $option ): bool {
		[ $newMatches, $operator ] = str_contains( $option, '|' )
			? [ explode( '|', $option ), 'OR' ]
			: [ explode( '<&>', $option ), 'AND' ];

		$data = $this->getParameter( 'category' ) ?? [];
		$data[IExpression::LIKE][$operator] ??= [];
		$data[IExpression::LIKE][$operator][] = $newMatches;

		$this->setParameter( 'category', $data );
		$this->setOpenReferencesConflict( true );

		return true;
	}

	/**
	 * Clean and test 'notcategory' parameter.
	 */
	public function _notcategory( string $option ): bool {
		$title = Title::newFromText( $option );
		if ( $title === null ) {
			return false;
		}

		$data = $this->getParameter( 'notcategory' ) ?? [];
		$data['='][] = $title->getDbKey();

		$this->setParameter( 'notcategory', $data );
		$this->setOpenReferencesConflict( true );

		return true;
	}

	/**
	 * Clean and test 'notcategoryregexp' parameter.
	 */
	public function _notcategoryregexp( string $option ): bool {
		if ( !$this->isRegexValid( $option, true ) ) {
			return false;
		}

		$data = $this->getParameter( 'notcategory' );
		$data['REGEXP'][] = $option;

		$this->setParameter( 'notcategory', $data );
		$this->setOpenReferencesConflict( true );

		return true;
	}

	/**
	 * Clean and test 'notcategorymatch' parameter.
	 */
	public function _notcategorymatch( string $option ): bool {
		$data = $this->getParameter( 'notcategory' ) ?? [];
		$data[IExpression::LIKE] ??= [];

		$newMatches = explode( '|', $option );
		$data[IExpression::LIKE] = array_merge( $data[IExpression::LIKE], $newMatches );

		$this->setParameter( 'notcategory', $data );
		$this->setOpenReferencesConflict( true );

		return true;
	}

	/**
	 * Clean and test 'count' parameter.
	 */
	public function _count( string|int $option ): bool {
		if ( !is_numeric( $option ) || (int)$option <= 0 ) {
			return false;
		}

		$max = $this->config->get( 'allowUnlimitedResults' ) ? INF :
			$this->config->get( 'maxResultCount' );

		$this->setParameter( 'count', min( (int)$option, $max ) );

		return true;
	}

	/**
	 * Clean and test 'namespace' parameter.
	 */
	public function _namespace( string $option ): bool {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		$allowedNamespaces = $this->config->get( 'allowedNamespaces' );
		$data = $this->getParameter( 'namespace' ) ?? [];

		foreach ( explode( '|', $option ) as $parameter ) {
			$parameter = trim( $parameter );
			$lowerParam = strtolower( $parameter );

			if ( $lowerParam === 'main' || $lowerParam === '(main)' ) {
				$parameter = '';
			}

			$namespaceId = $contLang->getNsIndex( $parameter );
			if ( $namespaceId === false && is_numeric( $parameter ) &&
				in_array( (int)$parameter, $contLang->getNamespaceIds(), true )
			) {
				$namespaceId = (int)$parameter;
			}

			if (
				$namespaceId === false || (
				is_array( $allowedNamespaces ) &&
					!in_array( $parameter, $allowedNamespaces, true )
				)
			) {
				return false;
			}

			$data[] = $namespaceId;
		}

		$this->setParameter( 'namespace', array_unique( $data ) );
		$this->setSelectionCriteriaFound( true );

		return true;
	}

	/**
	 * Clean and test 'notnamespace' parameter.
	 */
	public function _notnamespace( string $option ): bool {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();
		$data = $this->getParameter( 'notnamespace' ) ?? [];

		foreach ( explode( '|', $option ) as $parameter ) {
			$parameter = trim( $parameter );
			$lowerParam = strtolower( $parameter );

			if ( $lowerParam === 'main' || $lowerParam === '(main)' ) {
				$parameter = '';
			}

			$namespaceId = $contLang->getNsIndex( $parameter );

			if (
				$namespaceId === false &&
				is_numeric( $parameter ) &&
				in_array( (int)$parameter, $contLang->getNamespaceIds(), true )
			) {
				$namespaceId = (int)$parameter;
			}

			if ( $namespaceId === false ) {
				return false;
			}

			$data[] = $namespaceId;
		}

		$this->setParameter( 'notnamespace', array_unique( $data ) );
		$this->setSelectionCriteriaFound( true );

		return true;
	}

	/**
	 * Clean and test 'openreferences' parameter.
	 */
	public function _openreferences( string $option ): bool {
		if ( $option !== 'missing' ) {
			$option = $this->filterBoolean( $option );
		}

		if ( $option === null ) {
			return false;
		}

		// Force 'ordermethod' back to none.
		$this->setParameter( 'ordermethod', [ 'none' ] );
		$this->setParameter( 'openreferences', $option );

		return true;
	}

	/**
	 * Clean and test 'ordermethod' parameter.
	 */
	public function _ordermethod( string $option ): bool {
		$methods = array_map( 'trim', explode( ',', $option ) );
		$validMethods = $this->getData( 'ordermethod' )['values'] ?? [];

		foreach ( $methods as $method ) {
			if ( !in_array( $method, $validMethods, true ) ) {
				return false;
			}
		}

		$this->setParameter( 'ordermethod', $methods );
		if ( $methods[0] !== 'none' ) {
			$this->setOpenReferencesConflict( true );
		}

		return true;
	}

	/**
	 * Clean and test 'mode' parameter.
	 */
	public function _mode( string $option ): bool {
		if ( !in_array( $option, $this->getData( 'mode' )['values'] ?? [], true ) ) {
			return false;
		}

		switch ( $option ) {
			case 'none':
				$this->setParameter( 'mode', 'inline' );
				$this->setParameter( 'inlinetext', '<br />' );
				break;
			case 'userformat':
				$this->setParameter( 'inlinetext', '' );
				$this->setParameter( 'mode', $option );
				break;
			default:
				$this->setParameter( 'mode', $option );
		}

		return true;
	}

	/**
	 * Clean and test 'distinct' parameter.
	 */
	public function _distinct( string $option ): bool {
		if ( $option === 'strict' ) {
			$this->setParameter( 'distinctresultset', 'strict' );
			return true;
		}

		$boolean = $this->filterBoolean( $option );
		if ( $boolean !== null ) {
			$this->setParameter( 'distinctresultset', $boolean );
			return true;
		}

		return false;
	}

	/**
	 * Clean and test 'ordercollation' parameter.
	 */
	public function _ordercollation( string $option ): bool {
		if ( $option === 'bridge' ) {
			$this->setParameter( 'ordersuitsymbols', true );
			return true;
		}

		if ( $option !== '' ) {
			$this->setParameter( 'ordercollation', $option );
			return true;
		}

		return false;
	}

	/**
	 * Shortcut to _format().
	 */
	public function _listseparators( string $option ): bool {
		return $this->_format( $option );
	}

	/**
	 * Clean and test 'format' parameter.
	 */
	public function _format( string $option ): bool {
		// Parsing of wikitext will happen at the end of the output phase.
		// Replace '\n' in the input by linefeed because wiki syntax
		// depends on linefeeds.
		$option = $this->stripHtmlTags( $option );
		$option = Parse::replaceNewLines( $option );

		$this->setParameter( 'listseparators', explode( ',', $option, 4 ) );

		// Set the 'mode' parameter to userformat automatically.
		$this->setParameter( 'mode', 'userformat' );
		$this->setParameter( 'inlinetext', '' );

		return true;
	}

	/**
	 * Clean and test 'title' parameter.
	 */
	public function _title( string $option ): bool {
		$title = Title::newFromText( $option );
		if ( !$title ) {
			return false;
		}

		$titleText = str_replace( ' ', '_', $title->getText() );
		$titleData = $this->getParameter( 'title' ) ?? [];
		$titleData['='][] = $titleText;
		$this->setParameter( 'title', $titleData );

		$namespaceData = $this->getParameter( 'namespace' ) ?? [];
		$namespaceData[] = $title->getNamespace();
		$this->setParameter( 'namespace', array_unique( $namespaceData ) );

		$this->setParameter( 'mode', 'userformat' );
		$this->setSelectionCriteriaFound( true );
		$this->setOpenReferencesConflict( true );

		return true;
	}

	/**
	 * Clean and test 'titlemaxlength' parameter.
	 */
	public function _titlemaxlength( string $option ): bool {
		$this->setParameter( 'titlemaxlen', (int)$option );
		return true;
	}

	/**
	 * Clean and test 'titleregexp' parameter.
	 */
	public function _titleregexp( string $option ): bool {
		$data = $this->getParameter( 'title' ) ?? [];
		$data['REGEXP'] ??= [];

		$newMatches = explode( '|', str_replace( ' ', '\_', $option ) );
		if ( !$this->isRegexValid( $newMatches, true ) ) {
			return false;
		}

		$data['REGEXP'] = array_merge( $data['REGEXP'], $newMatches );

		$this->setParameter( 'title', $data );
		$this->setSelectionCriteriaFound( true );

		return true;
	}

	/**
	 * Clean and test 'titlematch' parameter.
	 */
	public function _titlematch( string $option ): bool {
		$data = $this->getParameter( 'title' ) ?? [];
		$data[IExpression::LIKE] ??= [];

		$newMatches = explode( '|', str_replace( ' ', '\_', $option ) );
		$data[IExpression::LIKE] = array_merge( $data[IExpression::LIKE], $newMatches );

		$this->setParameter( 'title', $data );
		$this->setSelectionCriteriaFound( true );

		return true;
	}

	/**
	 * Clean and test 'nottitleregexp' parameter.
	 */
	public function _nottitleregexp( string $option ): bool {
		$data = $this->getParameter( 'nottitle' ) ?? [];
		$data['REGEXP'] ??= [];

		$newMatches = explode( '|', str_replace( ' ', '\_', $option ) );
		if ( !$this->isRegexValid( $newMatches, true ) ) {
			return false;
		}

		$data['REGEXP'] = array_merge( $data['REGEXP'], $newMatches );

		$this->setParameter( 'nottitle', $data );
		$this->setSelectionCriteriaFound( true );

		return true;
	}

	/**
	 * Clean and test 'nottitlematch' parameter.
	 */
	public function _nottitlematch( string $option ): bool {
		$data = $this->getParameter( 'nottitle' ) ?? [];
		$data[IExpression::LIKE] ??= [];

		$newMatches = explode( '|', str_replace( ' ', '\_', $option ) );
		$data[IExpression::LIKE] = array_merge( $data[IExpression::LIKE], $newMatches );

		$this->setParameter( 'nottitle', $data );
		$this->setSelectionCriteriaFound( true );

		return true;
	}

	/**
	 * Clean and test 'scroll' parameter.
	 */
	public function _scroll( string $option ): bool {
		$option = $this->filterBoolean( $option );
		$this->setParameter( 'scroll', $option );

		// If scrolling is active we adjust the values for certain other parameters based on URL arguments.
		if ( $option === true ) {
			$request = RequestContext::getMain()->getRequest();

			// The 'findTitle' option has argument over the 'fromTitle' argument.
			$titlegt = $request->getVal( 'DPL_findTitle', '' );
			$titlegt = $titlegt !== '' ? '=_' . ucfirst( $titlegt ) :
				ucfirst( $request->getVal( 'DPL_fromTitle', '' ) );

			$this->setParameter( 'titlegt', str_replace( ' ', '_', $titlegt ) );

			// Lets get the 'toTitle' argument.
			$titlelt = ucfirst( $request->getVal( 'DPL_toTitle', '' ) );
			$this->setParameter( 'titlelt', str_replace( ' ', '_', $titlelt ) );

			// Make sure the 'scrollDir' arugment is captured. This is mainly used for the
			// Variables extension and in the header/footer replacements.
			$this->setParameter( 'scrolldir', $request->getVal( 'DPL_scrollDir', '' ) );

			// Also set count limit from URL if not otherwise set.
			$this->_count( $request->getInt( 'DPL_count' ) );
		}

		// We do not return false since they could have just left it out.
		// Who knows why they put the parameter in the list in the first place.
		return true;
	}

	/**
	 * Clean and test 'replaceintitle' parameter.
	 */
	public function _replaceintitle( string $option ): bool {
		// We offer a possibility to replace some part of the title
		$replaceInTitle = explode( ',', $option, 2 );

		if ( isset( $replaceInTitle[1] ) ) {
			$replaceInTitle[1] = $this->stripHtmlTags( $replaceInTitle[1] );
		}

		$this->setParameter( 'replaceintitle', $replaceInTitle );

		return true;
	}

	/**
	 * Clean and test 'debug' parameter.
	 */
	public function _debug( string $option ): bool {
		if ( !is_numeric( $option ) ) {
			return false;
		}

		$option = (int)$option;
		if ( in_array( $option, $this->getData( 'debug' )['values'] ?? [], true ) ) {
			Utils::setDebugLevel( $option );
			return true;
		}

		return false;
	}

	/**
	 * Shortcut to _include().
	 */
	public function _includepage( string $option ): bool {
		return $this->_include( $option );
	}

	/**
	 * Clean and test 'include' parameter.
	 */
	public function _include( string $option ): bool {
		if ( $option === '' ) {
			return false;
		}

		$this->setParameter( 'incpage', true );
		$this->setParameter( 'seclabels', explode( ',', $option ) );

		return true;
	}

	/**
	 * Clean and test 'includematch' parameter.
	 */
	public function _includematch( string $option ): bool {
		$regexes = explode( ',', $option );
		if ( !$this->isRegexValid( $regexes ) ) {
			return false;
		}

		$this->setParameter( 'seclabelsmatch', $regexes );
		return true;
	}

	/**
	 * Clean and test 'includemaxlength' parameter.
	 */
	public function _includemaxlength( string $option ): bool {
		if ( !is_numeric( $option ) ) {
			return false;
		}

		$this->setParameter( 'includemaxlen', (int)$option );
		return true;
	}

	/**
	 * Clean and test 'includematchparsed' parameter.
	 */
	public function _includematchparsed( string $option ): bool {
		$regexes = explode( ',', $option );
		if ( !$this->isRegexValid( $regexes ) ) {
			return false;
		}

		$this->setParameter( 'incparsed', true );
		$this->setParameter( 'seclabelsmatch', $regexes );
		return true;
	}

	/**
	 * Clean and test 'includenotmatch' parameter.
	 */
	public function _includenotmatch( string $option ): bool {
		$regexes = explode( ',', $option );
		if ( !$this->isRegexValid( $regexes ) ) {
			return false;
		}

		$this->setParameter( 'seclabelsnotmatch', $regexes );
		return true;
	}

	/**
	 * Clean and test 'includenotmatchparsed' parameter.
	 */
	public function _includenotmatchparsed( string $option ): bool {
		$regexes = explode( ',', $option );
		if ( !$this->isRegexValid( $regexes ) ) {
			return false;
		}

		$this->setParameter( 'incparsed', true );
		$this->setParameter( 'seclabelsnotmatch', $regexes );
		return true;
	}

	/**
	 * Clean and test 'secseparators' parameter.
	 */
	public function _secseparators( string $option ): bool {
		// We replace '\n' by newline to support wiki syntax within the section separators
		$this->setParameter( 'secseparators', explode( ',', Parse::replaceNewLines( $option ) ) );
		return true;
	}

	/**
	 * Clean and test 'multisecseparators' parameter.
	 */
	public function _multisecseparators( string $option ): bool {
		// We replace '\n' by newline to support wiki syntax within the section separators
		$this->setParameter( 'multisecseparators', explode( ',', Parse::replaceNewLines( $option ) ) );
		return true;
	}

	/**
	 * Clean and test 'table' parameter.
	 */
	public function _table( string $option ): bool {
		$this->setParameter( 'defaulttemplatesuffix', '' );
		$this->setParameter( 'mode', 'userformat' );
		$this->setParameter( 'inlinetext', '' );

		$withHLink = "[[%PAGE%|%TITLE%]]\n|";
		$listSeparators = [];

		foreach ( explode( ',', $option ) as $tabnr => $tab ) {
			if ( $tabnr === 0 ) {
				$tab = $tab !== '' ? $tab : 'class=wikitable';
				$listSeparators[0] = '{|' . $tab;
			} elseif ( $tabnr === 1 ) {
				if ( $tab === '-' ) {
					$withHLink = '';
					continue;
				}

				$tab = $tab !== '' ? $tab : wfMessage( 'article' )->text();
				$listSeparators[0] .= "\n!{$tab}";
			} else {
				$listSeparators[0] .= "\n!{$tab}";
			}
		}

		$listSeparators[1] = '';

		// The user may have specified the third parameter of 'format' to
		// add meta attributes of articles to the table.
		$listSeparators[2] = '';

		$listSeparators[3] = "\n|}";

		// Overwrite 'listseparators'.
		$this->setParameter( 'listseparators', $listSeparators );

		$sectionLabels = (array)$this->getParameter( 'seclabels' );
		$sectionSeparators = $this->getParameter( 'secseparators' ) ?? [];
		$multiSectionSeparators = $this->getParameter( 'multisecseparators' ) ?? [];

		foreach ( array_keys( $sectionLabels ) as $i ) {
			if ( $i === 0 ) {
				$sectionSeparators[0] = "\n|-\n|" . $withHLink;
				$sectionSeparators[1] = '';
				$multiSectionSeparators[0] = "\n|-\n|" . $withHLink;
			} else {
				$sectionSeparators[2 * $i] = "\n|";
				$sectionSeparators[2 * $i + 1] = '';

				$multiSectionSeparators[$i] = (
					is_array( $sectionLabels[$i] ) && $sectionLabels[$i][0] === '#'
				) ? "\n----\n" : "<br/>\n";
			}
		}

		// Overwrite 'secseparators' and 'multisecseparators'.
		$this->setParameter( 'secseparators', $sectionSeparators );
		$this->setParameter( 'multisecseparators', $multiSectionSeparators );
		$this->setParameter( 'table', Parse::replaceNewLines( $option ) );

		return true;
	}

	/**
	 * Clean and test 'tablerow' parameter.
	 */
	public function _tablerow( string $option ): bool {
		$option = Parse::replaceNewLines( trim( $option ) );
		$this->setParameter( 'tablerow', $option === '' ? [] : explode( ',', $option ) );
		return true;
	}

	/**
	 * Clean and test 'allowcachedresults' parameter.
	 * This function is necessary for the custom 'yes+warn' option that sets 'warncachedresults'.
	 */
	public function _allowcachedresults( string $option ): bool {
		// If execAndExit was previously set (i.e. if it is not empty) we will ignore all
		// cache settings which are placed AFTER the execandexit statement thus we make sure
		// that the cache will only become invalid if the query is really executed.
		if ( $this->getParameter( 'execandexit' ) !== null ) {
			$this->setParameter( 'allowcachedresults', false );
			return true;
		}

		if ( $option === 'yes+warn' ) {
			$this->setParameter( 'allowcachedresults', true );
			$this->setParameter( 'warncachedresults', true );
			return true;
		}

		$option = $this->filterBoolean( $option );

		if ( $option !== null ) {
			$this->setParameter( 'allowcachedresults', $option );
			return true;
		}

		return false;
	}

	/**
	 * Clean and test 'fixcategory' parameter.
	 */
	public function _fixcategory( string $option ): bool {
		Hooks::fixCategory( $option );
		return true;
	}

	/**
	 * Clean and test 'reset' parameter.
	 */
	public function _reset( string $option ): bool {
		$arguments = array_map( 'trim', explode( ',', $option ) );
		$values = $this->getData( 'reset' )['values'] ?? [];
		$reset = [];

		foreach ( $arguments as $argument ) {
			if ( $argument === '' ) {
				continue;
			}

			if ( !in_array( $argument, $values, true ) ) {
				return false;
			}

			if ( $argument === 'all' || $argument === 'none' ) {
				$boolean = ( $argument === 'all' );
				$subValues = array_diff( $values, [ 'all', 'none' ] );
				$reset = array_fill_keys( $subValues, $boolean );
				// No need to process further after 'all' or 'none'
				break;
			}

			$reset[$argument] = true;
		}

		$data = $this->getParameter( 'reset' ) ?? [];
		$this->setParameter( 'reset', array_merge( $data, $reset ) );

		return true;
	}

	/**
	 * Clean and test 'eliminate' parameter.
	 */
	public function _eliminate( string $option ): bool {
		$arguments = array_map( 'trim', explode( ',', $option ) );
		$values = $this->getData( 'eliminate' )['values'] ?? [];
		$eliminate = [];

		foreach ( $arguments as $argument ) {
			if ( $argument === '' ) {
				continue;
			}

			if ( !in_array( $argument, $values, true ) ) {
				return false;
			}

			if ( $argument === 'all' || $argument === 'none' ) {
				$boolean = $argument === 'all';
				$subValues = array_diff( $values, [ 'all', 'none' ] );
				$eliminate = array_fill_keys( $subValues, $boolean );
				// No need to process further
				break;
			}

			$eliminate[$argument] = true;
		}

		$data = $this->getParameter( 'eliminate' ) ?? [];
		$this->setParameter( 'eliminate', array_merge( $data, $eliminate ) );

		return true;
	}
}
