<?php

namespace DPL;

use MediaWiki\MediaWikiServices;
use MWException;
use PermissionsError;
use RequestContext;
use Title;

class Parameters extends ParametersData {
	/**
	 * Set parameter options.
	 *
	 * @var array
	 */
	private $parameterOptions = [];

	/**
	 * Selection Criteria Found
	 *
	 * @var bool
	 */
	private $selectionCriteriaFound = false;

	/**
	 * Open References Conflict
	 *
	 * @var bool
	 */
	private $openReferencesConflict = false;

	/**
	 * Parameters that have already been processed.
	 *
	 * @var array
	 */
	private $parametersProcessed = [];

	public function __construct() {
		parent::__construct();

		$this->setDefaults();
	}

	/**
	 * Handle simple parameter functions.
	 *
	 * @param string $parameter
	 * @param mixed $arguments
	 * @return bool
	 */
	public function __call( $parameter, $arguments ) {
		$parameterData = $this->getData( $parameter );

		if ( $parameterData === false ) {
			return false;
		}

		// Check permission to use this parameter.
		if ( array_key_exists( 'permission', $parameterData ) ) {
			$user = RequestContext::getMain()->getUser();

			if ( !$user->isAllowed( $parameterData['permission'] ) ) {
				throw new PermissionsError( $parameterData['permission'] );
			}
		}

		// Subvert to the real function if it exists. This keeps code elsewhere clean from needed to check if it exists first.
		$function = '_' . $parameter;
		$this->parametersProcessed[$parameter] = true;

		if ( method_exists( $this, $function ) ) {
			return call_user_func_array( [ $this, $function ], $arguments );
		}

		$option = $arguments[0];
		$parameter = strtolower( $parameter );

		// Assume by default that these simple parameter options should not failed, but if they do we will set $success to false below.
		$success = true;
		if ( $parameterData !== false ) {
			// If a parameter specifies options then enforce them.
			if ( array_key_exists( 'values', $parameterData ) && is_array( $parameterData['values'] ) === true && !in_array( strtolower( $option ), $parameterData['values'] ) ) {
				$success = false;
			} else {
				if ( ( array_key_exists( 'preserve_case', $parameterData ) && !$parameterData['preserve_case'] ) && ( array_key_exists( 'page_name_list', $parameterData ) && $parameterData['page_name_list'] !== true ) ) {
					$option = strtolower( $option );
				}
			}

			// Strip <html> tag.
			if ( array_key_exists( 'strip_html', $parameterData ) && $parameterData['strip_html'] === true ) {
				$option = $this->stripHtmlTags( $option );
			}

			// Simple integer intval().
			if ( array_key_exists( 'integer', $parameterData ) && $parameterData['integer'] === true ) {
				if ( !is_numeric( $option ) ) {
					if ( $parameterData['default'] !== null ) {
						$option = intval( $parameterData['default'] );
					} else {
						$success = false;
					}
				} else {
					$option = intval( $option );
				}
			}

			// Booleans
			if ( array_key_exists( 'boolean', $parameterData ) && $parameterData['boolean'] === true ) {
				$option = $this->filterBoolean( $option );

				if ( $option === null ) {
					$success = false;
				}
			}

			// Timestamps
			if ( array_key_exists( 'timestamp', $parameterData ) && $parameterData['timestamp'] === true ) {
				$option = strtolower( $option );

				switch ( $option ) {
					case 'today':
					case 'last hour':
					case 'last day':
					case 'last week':
					case 'last month':
					case 'last year':
						break;
					default:
						$option = str_pad( preg_replace( '#[^0-9]#', '', $option ), 14, '0' );
						$option = wfTimestamp( TS_MW, $option );

						if ( $option === false ) {
							$success = false;
						}
						break;
				}
			}

			// List of Pages
			if ( array_key_exists( 'page_name_list', $parameterData ) && $parameterData['page_name_list'] === true ) {
				$pageGroups = $this->getParameter( $parameter );

				if ( !is_array( $pageGroups ) ) {
					$pageGroups = [];
				}

				$pages = $this->getPageNameList( $option, (bool)$parameterData['page_name_must_exist'] );

				if ( $pages === false ) {
					$success = false;
				} else {
					$pageGroups[] = $pages;
					$option = $pageGroups;
				}
			}

			// Regex Pattern Matching
			if ( array_key_exists( 'pattern', $parameterData ) ) {
				if ( preg_match( $parameterData['pattern'], $option, $matches ) ) {
					// Nuke the total pattern match off the beginning of the array.
					array_shift( $matches );
					$option = $matches;
				} else {
					$success = false;
				}
			}

			// Database Key Formatting
			if ( array_key_exists( 'db_format', $parameterData ) && $parameterData['db_format'] === true ) {
				$option = str_replace( ' ', '_', $option );
			}

			// If none of the above checks marked this as a failure then set it.
			if ( $success ) {
				$this->setParameter( $parameter, $option );

				// Set that criteria was found for a selection.
				if ( array_key_exists( 'set_criteria_found', $parameterData ) && $parameterData['set_criteria_found'] === true ) {
					$this->setSelectionCriteriaFound( true );
				}

				// Set open references conflict possibility.
				if ( array_key_exists( 'open_ref_conflict', $parameterData ) && $parameterData['open_ref_conflict'] === true ) {
					$this->setOpenReferencesConflict( true );
				}
			}
		}

		return $success;
	}

	/**
	 * Sort cleaned parameter arrays by priority.
	 * Users can not be told to put the parameters into a specific order each time. Some parameters are dependent on each other coming in a certain order due to some procedural legacy issues.
	 *
	 * @param array	$parameters
	 * @return array
	 */
	public static function sortByPriority( $parameters ) {
		if ( !is_array( $parameters ) ) {
			throw new MWException( __METHOD__ . ': A non-array was passed.' );
		}

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
			'include' => 10
		];

		$_first = [];
		foreach ( $priority as $parameter => $order ) {
			if ( isset( $parameters[$parameter] ) ) {
				$_first[$parameter] = $parameters[$parameter];
				unset( $parameters[$parameter] );
			}
		}

		$parameters = $_first + $parameters;

		return $parameters;
	}

	/**
	 * Set Selection Criteria Found
	 *
	 * @param bool $found
	 * @return void
	 */
	private function setSelectionCriteriaFound( $found = true ) {
		if ( !is_bool( $found ) ) {
			throw new MWException( __METHOD__ . ': A non-boolean was passed.' );
		}

		$this->selectionCriteriaFound = $found;
	}

	/**
	 * Get Selection Criteria Found
	 *
	 * @return bool
	 */
	public function isSelectionCriteriaFound() {
		return $this->selectionCriteriaFound;
	}

	/**
	 * Set Open References Conflict - See 'openreferences' parameter.
	 *
	 * @param bool $conflict
	 * @return void
	 */
	private function setOpenReferencesConflict( $conflict = true ) {
		if ( !is_bool( $conflict ) ) {
			throw new MWException( __METHOD__ . ': A non-boolean was passed.' );
		}

		$this->openReferencesConflict = $conflict;
	}

	/**
	 * Get Open References Conflict - See 'openreferences' parameter.
	 *
	 * @return bool
	 */
	public function isOpenReferencesConflict() {
		return $this->openReferencesConflict;
	}

	/**
	 * Set default parameters based on ParametersData.
	 */
	private function setDefaults() {
		$this->setParameter( 'defaulttemplatesuffix', '.default' );

		$parameters = $this->getParametersForRichness();
		foreach ( $parameters as $parameter ) {
			if ( $this->getData( $parameter )['default'] !== null && !( $this->getData( $parameter )['default'] === false && $this->getData( $parameter )['boolean'] === true ) ) {
				if ( $parameter == 'debug' ) {
					DynamicPageListHooks::setDebugLevel( $this->getData( $parameter )['default'] );
				}

				$this->setParameter( $parameter, $this->getData( $parameter )['default'] );
			}
		}
	}

	/**
	 * Set a parameter's option.
	 *
	 * @param string $parameter
	 * @param mixed	$option
	 */
	public function setParameter( $parameter, $option ) {
		$this->parameterOptions[$parameter] = $option;
	}

	/**
	 * Get a parameter's option.
	 *
	 * @param string $parameter
	 * @return mixed Option for specified parameter.
	 */
	public function getParameter( $parameter ) {
		return array_key_exists( $parameter, $this->parameterOptions ) ? $this->parameterOptions[$parameter] : null;
	}

	/**
	 * Get all parameters.
	 *
	 * @return array
	 */
	public function getAllParameters() {
		return self::sortByPriority( $this->parameterOptions );
	}

	/**
	 * Filter a standard boolean like value into an actual boolean.
	 *
	 * @param int|string|bool $boolean
	 * @return bool
	 */
	public function filterBoolean( $boolean ) {
		return filter_var( $boolean, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
	}

	/**
	 * Strip <html> tags.
	 *
	 * @param string $text
	 * @return string
	 */
	private function stripHtmlTags( $text ) {
		$text = preg_replace( "#<.*?html.*?>#is", "", $text );

		return $text;
	}

	/**
	 * Get a list of valid page names.
	 *
	 * @param string $text
	 * @param bool $mustExist
	 * @return array|bool
	 */
	private function getPageNameList( $text, $mustExist = true ) {
		$list = [];
		$pages = explode( '|', trim( $text ) );

		foreach ( $pages as $page ) {
			$page = trim( $page );
			$page = rtrim( $page, '\\' );

			if ( empty( $page ) ) {
				continue;
			}

			if ( $mustExist === true ) {
				$title = Title::newFromText( $page );

				if ( !$title ) {
					return false;
				}

				$list[] = $title;
			} else {
				$list[] = $page;
			}
		}

		return $list;
	}

	/**
	 * Check if a regular expression is valid.
	 *
	 * @param array|string $regexes
	 * @param bool $forDb
	 * @return bool
	 */
	private function isRegexValid( $regexes, $forDb = false ) {
		if ( !is_array( $regexes ) ) {
			$regexes = [ $regexes ];
		}

		foreach ( $regexes as $regex ) {
			if ( empty( trim( $regex ) ) ) {
				continue;
			}

			if ( $forDb ) {
				$regex = '#' . str_replace( '#', '\#', $regex ) . '#';
			}

			// Purposely silencing the errors here since we are testing if preg_match
			// would throw an error due to a bad regex from user input.

			// phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			if ( @preg_match( $regex, null ) === false ) {
				// @phan-suppress-previous-line PhanParamSuspiciousOrder, PhanTypeMismatchArgumentInternalProbablyReal
				return false;
			}
		}

		return true;
	}

	/**
	 * Clean and test 'category' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _category( $option ) {
		$option = trim( $option );

		if ( empty( $option ) ) {
			return false;
		}

		// Init array of categories to include
		$categories = [];
		$heading = false;
		$notHeading = false;

		if ( substr( $option, 0, 1 ) == '+' ) { // categories are headings
			$heading = true;
			$option = ltrim( $option, '+' );
		}

		if ( substr( $option, 0, 1 ) == '-' ) { // categories are NOT headings
			$notHeading = true;
			$option = ltrim( $option, '-' );
		}

		// We expand html entities because they contain an '& 'which would be interpreted as an AND condition
		$option = html_entity_decode( $option, ENT_QUOTES );

		if ( strpos( $option, '|' ) !== false ) {
			$parameters = explode( '|', $option );
			$operator = 'OR';
		} else {
			$parameters = explode( '&', $option );
			$operator = 'AND';
		}

		foreach ( $parameters as $parameter ) {
			$parameter = trim( $parameter );

			if ( $parameter === '_none_' || $parameter === '' ) {
				$this->setParameter( 'includeuncat', true );
				$categories[] = '';
			} elseif ( !empty( $parameter ) ) {
				if ( strpos( $parameter, '*' ) === 0 && strlen( $parameter ) >= 2 ) {
					if ( strpos( $parameter, '*', 1 ) === 1 ) {
						$parameter = substr( $parameter, 2 );
						$subCategories = Query::getSubcategories( $parameter, 2 );
					} else {
						$parameter = substr( $parameter, 1 );
						$subCategories = Query::getSubcategories( $parameter, 1 );
					}

					$subCategories[] = $parameter;
					foreach ( $subCategories as $subCategory ) {
						$title = Title::newFromText( $subCategory );

						if ( $title !== null ) {
							// The * helper is just like listing "Category1|SubCategory1". This gets hard coded here for this purpose.
							$categories['OR'][] = $title->getDbKey();
						}
					}
				} else {
					$title = Title::newFromText( $parameter );

					if ( $title !== null ) {
						$categories[$operator][] = $title->getDbKey();
					}
				}
			}
		}

		if ( !empty( $categories ) ) {
			$data = $this->getParameter( 'category' );

			// Do a bunch of data integrity checks to avoid E_NOTICE.
			if ( !is_array( $data ) ) {
				$data = [];
			}

			if ( !array_key_exists( '=', $data ) || !is_array( $data['='] ) ) {
				$data['='] = [];
			}

			foreach ( $categories as $_operator => $_categories ) {
				if ( !array_key_exists( $_operator, $data['='] ) || !is_array( $data['='][$_operator] ) ) {
					$data['='][$_operator] = [];
				}

				$data['='][$_operator][] = $_categories;
			}

			$this->setParameter( 'category', $data );
			if ( $heading ) {
				$this->setParameter( 'catheadings', array_unique( array_merge( ( is_array( $this->getParameter( 'catheadings' ) ) ? $this->getParameter( 'catheadings' ) : [] ), $categories ) ) );
			}

			if ( $notHeading ) {
				$this->setParameter( 'catnotheadings', array_unique( array_merge( ( is_array( $this->getParameter( 'catnotheadings' ) ) ? $this->getParameter( 'catnotheadings' ) : [] ), $categories ) ) );
			}

			$this->setOpenReferencesConflict( true );

			return true;
		}

		return false;
	}

	/**
	 * Clean and test 'categoryregexp' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _categoryregexp( $option ) {
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
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _categorymatch( $option ) {
		if ( strpos( $option, '|' ) !== false ) {
			$newMatches = explode( '|', $option );
			$operator = 'OR';
		} else {
			$newMatches = explode( '&', $option );
			$operator = 'AND';
		}

		$data = $this->getParameter( 'category' );
		if ( isset( $data['LIKE'] ) && !is_array( $data['LIKE'][$operator] ) ) {
			$data['LIKE'][$operator] = [];
		}

		$data['LIKE'][$operator][] = $newMatches;

		$this->setParameter( 'category', $data );
		$this->setOpenReferencesConflict( true );

		return true;
	}

	/**
	 * Clean and test 'notcategory' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _notcategory( $option ) {
		$title = Title::newFromText( $option );

		if ( $title !== null ) {
			$data = $this->getParameter( 'notcategory' );
			$data['='][] = $title->getDbKey();

			$this->setParameter( 'notcategory', $data );
			$this->setOpenReferencesConflict( true );

			return true;
		}

		return false;
	}

	/**
	 * Clean and test 'notcategoryregexp' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _notcategoryregexp( $option ) {
		if ( !$this->isRegexValid( $option, true ) ) {
			return false;
		}

		$data = $this->getParameter( 'notcategory' );
		$data['regexp'][] = $option;

		$this->setParameter( 'notcategory', $data );
		$this->setOpenReferencesConflict( true );

		return true;
	}

	/**
	 * Clean and test 'notcategorymatch' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _notcategorymatch( $option ) {
		$data = $this->getParameter( 'notcategory' );

		if ( !is_array( $data['like'] ?? false ) ) {
			$data['like'] = [];
		}

		$newMatches = explode( '|', $option );
		$data['like'] = array_merge( $data['like'], $newMatches );

		$this->setParameter( 'notcategory', $data );
		$this->setOpenReferencesConflict( true );

		return true;
	}

	/**
	 * Clean and test 'count' parameter.
	 *
	 * @param string|int $option
	 * @return bool
	 */
	public function _count( $option ) {
		if ( !Config::getSetting( 'allowUnlimitedResults' ) && $option <= Config::getSetting( 'maxResultCount' ) && $option > 0 ) {
			$this->setParameter( 'count', intval( $option ) );

			return true;
		}

		return false;
	}

	/**
	 * Clean and test 'namespace' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _namespace( $option ) {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();

		$extraParams = explode( '|', $option );
		foreach ( $extraParams as $parameter ) {
			$parameter = trim( $parameter );
			$namespaceId = $contLang->getNsIndex( $parameter );

			if ( $namespaceId === false || ( is_array( Config::getSetting( 'allowedNamespaces' ) ) && !in_array( $parameter, Config::getSetting( 'allowedNamespaces' ) ) ) ) {
				// Let the user know this namespace is not allowed or does not exist.
				return false;
			}

			$data = $this->getParameter( 'namespace' );
			$data[] = $namespaceId;
			$data = array_unique( $data );

			$this->setParameter( 'namespace', $data );
			$this->setSelectionCriteriaFound( true );
		}

		return true;
	}

	/**
	 * Clean and test 'notnamespace' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _notnamespace( $option ) {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();

		$extraParams = explode( '|', $option );
		foreach ( $extraParams as $parameter ) {
			$parameter = trim( $parameter );
			$namespaceId = $contLang->getNsIndex( $parameter );

			if ( $namespaceId === false ) {
				// Let the user know this namespace is not allowed or does not exist.
				return false;
			}

			$data = $this->getParameter( 'notnamespace' );
			$data[] = $namespaceId;
			$data = array_unique( $data );

			$this->setParameter( 'notnamespace', $data );
			$this->setSelectionCriteriaFound( true );
		}

		return true;
	}

	/**
	 * Clean and test 'openreferences' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _openreferences( $option ) {
		$option = $this->filterBoolean( $option );

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
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _ordermethod( $option ) {
		$methods = explode( ',', $option );
		$success = true;

		foreach ( $methods as $method ) {
			if ( !in_array( $method, $this->getData( 'ordermethod' )['values'] ) ) {
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
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _mode( $option ) {
		if ( in_array( $option, $this->getData( 'mode' )['values'] ) ) {
			// 'none' mode is implemented as a specific submode of 'inline' with <br/> as inline text
			if ( $option == 'none' ) {
				$this->setParameter( 'mode', 'inline' );
				$this->setParameter( 'inlinetext', '<br/>' );
			} elseif ( $option == 'userformat' ) {
				// userformat resets inline text to empty string
				$this->setParameter( 'inlinetext', '' );
				$this->setParameter( 'mode', $option );
			} else {
				$this->setParameter( 'mode', $option );
			}

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Clean and test 'distinct' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _distinct( $option ) {
		$boolean = $this->filterBoolean( $option );

		if ( $option == 'strict' ) {
			$this->setParameter( 'distinctresultset', 'strict' );
		} elseif ( $boolean !== null ) {
			$this->setParameter( 'distinctresultset', $boolean );
		} else {
			return false;
		}

		return true;
	}

	/**
	 * Clean and test 'ordercollation' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _ordercollation( $option ) {
		if ( $option == 'bridge' ) {
			$this->setParameter( 'ordersuitsymbols', true );
		} elseif ( !empty( $option ) ) {
			$this->setParameter( 'ordercollation', $option );
		} else {
			return false;
		}

		return true;
	}

	/**
	 * Short cut to _format();
	 *
	 * @return mixed
	 */
	public function _listseparators() {
		return call_user_func_array( [ $this, '_format' ], func_get_args() );
	}

	/**
	 * Clean and test 'format' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _format( $option ) {
		// Parsing of wikitext will happen at the end of the output phase. Replace '\n' in the input by linefeed because wiki syntax depends on linefeeds.
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
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _title( $option ) {
		$title = Title::newFromText( $option );

		if ( $title ) {
			$data = $this->getParameter( 'title' );
			$data['='][] = str_replace( ' ', '_', $title->getText() );
			$this->setParameter( 'title', $data );

			$data = $this->getParameter( 'namespace' );
			$data[] = $title->getNamespace();
			$data = array_unique( $data );

			$this->setParameter( 'namespace', $data );
			$this->setParameter( 'mode', 'userformat' );

			$this->setSelectionCriteriaFound( true );
			$this->setOpenReferencesConflict( true );

			return true;
		}

		return false;
	}

	/**
	 * Clean and test 'titleregexp' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _titleregexp( $option ) {
		$data = $this->getParameter( 'title' );

		if ( !is_array( $data['regexp'] ?? false ) ) {
			$data['regexp'] = [];
		}

		$newMatches = explode( '|', str_replace( ' ', '\_', $option ) );

		if ( !$this->isRegexValid( $newMatches, true ) ) {
			return false;
		}

		$data['regexp'] = array_merge( $data['regexp'], $newMatches );

		$this->setParameter( 'title', $data );
		$this->setSelectionCriteriaFound( true );

		return true;
	}

	/**
	 * Clean and test 'titlematch' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _titlematch( $option ) {
		$data = $this->getParameter( 'title' );

		if ( !is_array( $data['like'] ?? false ) ) {
			$data['like'] = [];
		}

		$newMatches = explode( '|', str_replace( ' ', '\_', $option ) );
		$data['like'] = array_merge( $data['like'], $newMatches );

		$this->setParameter( 'title', $data );
		$this->setSelectionCriteriaFound( true );

		return true;
	}

	/**
	 * Clean and test 'nottitleregexp' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _nottitleregexp( $option ) {
		$data = $this->getParameter( 'nottitle' );

		if ( !is_array( $data['regexp'] ) ) {
			$data['regexp'] = [];
		}

		$newMatches = explode( '|', str_replace( ' ', '\_', $option ) );
		$data['regexp'] = array_merge( $data['regexp'], $newMatches );

		if ( !$this->isRegexValid( $newMatches, true ) ) {
			return false;
		}

		$this->setParameter( 'nottitle', $data );
		$this->setSelectionCriteriaFound( true );

		return true;
	}

	/**
	 * Clean and test 'nottitlematch' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _nottitlematch( $option ) {
		$data = $this->getParameter( 'nottitle' );

		if ( !is_array( $data['like'] ?? false ) ) {
			$data['like'] = [];
		}

		$newMatches = explode( '|', str_replace( ' ', '\_', $option ) );
		$data['like'] = array_merge( $data['like'], $newMatches );

		$this->setParameter( 'nottitle', $data );
		$this->setSelectionCriteriaFound( true );

		return true;
	}

	/**
	 * Clean and test 'scroll' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _scroll( $option ) {
		$option = $this->filterBoolean( $option );
		$this->setParameter( 'scroll', $option );

		// If scrolling is active we adjust the values for certain other parameters based on URL arguments
		if ( $option === true ) {
			global $wgRequest;

			// The 'findTitle' option has argument over the 'fromTitle' argument.
			$titlegt = $wgRequest->getVal( 'DPL_findTitle', '' );

			if ( !empty( $titlegt ) ) {
				$titlegt = '=_' . ucfirst( $titlegt );
			} else {
				$titlegt = $wgRequest->getVal( 'DPL_fromTitle', '' );
				$titlegt = ucfirst( $titlegt );
			}

			$this->setParameter( 'titlegt', str_replace( ' ', '_', $titlegt ) );

			// Lets get the 'toTitle' argument.
			$titlelt = $wgRequest->getVal( 'DPL_toTitle', '' );
			$titlelt = ucfirst( $titlelt );

			$this->setParameter( 'titlelt', str_replace( ' ', '_', $titlelt ) );

			// Make sure the 'scrollDir' arugment is captured. This is mainly used for the Variables extension and in the header/footer replacements.
			$this->setParameter( 'scrolldir', $wgRequest->getVal( 'DPL_scrollDir', '' ) );

			// Also set count limit from URL if not otherwise set.
			$this->_count( $wgRequest->getInt( 'DPL_count' ) );
		}

		// We do not return false since they could have just left it out. Who knows why they put the parameter in the list in the first place.
		return true;
	}

	/**
	 * Clean and test 'replaceintitle' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _replaceintitle( $option ) {
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
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _debug( $option ) {
		if ( in_array( $option, $this->getData( 'debug' )['values'] ) ) {
			DynamicPageListHooks::setDebugLevel( $option );
		} else {
			return false;
		}

		return true;
	}

	/**
	 * Short cut to _include();
	 *
	 * @return mixed
	 */
	public function _includepage() {
		return call_user_func_array( [ $this, '_include' ], func_get_args() );
	}

	/**
	 * Clean and test 'include' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _include( $option ) {
		if ( !empty( $option ) ) {
			$this->setParameter( 'incpage', true );
			$this->setParameter( 'seclabels', explode( ',', $option ) );
		} else {
			return false;
		}

		return true;
	}

	/**
	 * Clean and test 'includematch' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _includematch( $option ) {
		$regexes = explode( ',', $option );

		if ( !$this->isRegexValid( $regexes ) ) {
			return false;
		}

		$this->setParameter( 'seclabelsmatch', $regexes );

		return true;
	}

	/**
	 * Clean and test 'includematchparsed' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _includematchparsed( $option ) {
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
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _includenotmatch( $option ) {
		$regexes = explode( ',', $option );

		if ( !$this->isRegexValid( $regexes ) ) {
			return false;
		}

		$this->setParameter( 'seclabelsnotmatch', $regexes );

		return true;
	}

	/**
	 * Clean and test 'includenotmatchparsed' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _includenotmatchparsed( $option ) {
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
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _secseparators( $option ) {
		// We replace '\n' by newline to support wiki syntax within the section separators
		$this->setParameter( 'secseparators', explode( ',', Parse::replaceNewLines( $option ) ) );

		return true;
	}

	/**
	 * Clean and test 'multisecseparators' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _multisecseparators( $option ) {
		// We replace '\n' by newline to support wiki syntax within the section separators
		$this->setParameter( 'multisecseparators', explode( ',', Parse::replaceNewLines( $option ) ) );

		return true;
	}

	/**
	 * Clean and test 'table' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _table( $option ) {
		$this->setParameter( 'defaulttemplatesuffix', '' );
		$this->setParameter( 'mode', 'userformat' );
		$this->setParameter( 'inlinetext', '' );

		$withHLink = "[[%PAGE%|%TITLE%]]\n|";
		$listSeparators = [];

		foreach ( explode( ',', $option ) as $tabnr => $tab ) {
			if ( $tabnr == 0 ) {
				if ( $tab == '' ) {
					$tab = 'class=wikitable';
				}

				$listSeparators[0] = '{|' . $tab;
			} else {
				if ( $tabnr == 1 && $tab == '-' ) {
					$withHLink = '';
					continue;
				}

				if ( $tabnr == 1 && $tab == '' ) {
					$tab = wfMessage( 'article' )->text();
				}

				$listSeparators[0] .= "\n!{$tab}";
			}
		}

		$listSeparators[1] = '';

		// the user may have specified the third parameter of 'format' to add meta attributes of articles to the table
		$listSeparators[2] = '';

		$listSeparators[3] = "\n|}";

		// Overwrite 'listseparators'.
		$this->setParameter( 'listseparators', $listSeparators );

		$sectionLabels = (array)$this->getParameter( 'seclabels' );
		$sectionSeparators = $this->getParameter( 'secseparators' );
		$multiSectionSeparators = $this->getParameter( 'multisecseparators' );

		for ( $i = 0; $i < count( $sectionLabels ); $i++ ) {
			if ( $i == 0 ) {
				$sectionSeparators[0] = "\n|-\n|" . $withHLink; // ."\n";
				$sectionSeparators[1] = '';
				$multiSectionSeparators[0] = "\n|-\n|" . $withHLink; // ."\n";
			} else {
				$sectionSeparators[2 * $i] = "\n|"; // ."\n";
				$sectionSeparators[2 * $i + 1] = '';

				if ( is_array( $sectionLabels[$i] ) && $sectionLabels[$i][0] == '#' ) {
					$multiSectionSeparators[$i] = "\n----\n";
				} else {
					$multiSectionSeparators[$i] = "<br/>\n";
				}
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
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _tablerow( $option ) {
		$option = Parse::replaceNewLines( trim( $option ) );

		if ( empty( $option ) ) {
			$this->setParameter( 'tablerow', [] );
		} else {
			$this->setParameter( 'tablerow', explode( ',', $option ) );
		}

		return true;
	}

	/**
	 * Clean and test 'allowcachedresults' parameter.
	 * This function is necessary for the custom 'yes+warn' option that sets 'warncachedresults'.
	 *
	 * @param string $option
	 * @return bool|int|string
	 */
	public function _allowcachedresults( $option ) {
		// If execAndExit was previously set (i.e. if it is not empty) we will ignore all cache settings which are placed AFTER the execandexit statement thus we make sure that the cache will only become invalid if the query is really executed.
		if ( $this->getParameter( 'execandexit' ) === null ) {
			if ( $option === 'yes+warn' ) {
				$this->setParameter( 'allowcachedresults', true );
				$this->setParameter( 'warncachedresults', true );

				return true;
			}

			$option = $this->filterBoolean( $option );
			if ( $option !== null ) {
				$this->setParameter( 'allowcachedresults', $this->filterBoolean( $option ) );
			} else {
				return false;
			}
		} else {
			$this->setParameter( 'allowcachedresults', false );
		}

		return true;
	}

	/**
	 * Clean and test 'fixcategory' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _fixcategory( $option ) {
		DynamicPageListHooks::fixCategory( $option );

		return true;
	}

	/**
	 * Clean and test 'reset' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _reset( $option ) {
		$arguments = explode( ',', $option );
		$reset = [];

		foreach ( $arguments as $argument ) {
			$argument = trim( $argument );

			if ( empty( $argument ) ) {
				continue;
			}

			$values = $this->getData( 'reset' )['values'];
			if ( !in_array( $argument, $values ) ) {
				return false;
			} else {
				if ( $argument == 'all' || $argument == 'none' ) {
					$boolean = ( $argument == 'all' );
					$values = array_diff( $values, [ 'all', 'none' ] );
					$reset = array_flip( $values );

					foreach ( $reset as $value => $key ) {
						$reset[$value] = $boolean;
					}
				} else {
					$reset[$argument] = true;
				}
			}
		}

		$data = $this->getParameter( 'reset' );
		$data = array_merge( $data, $reset );

		$this->setParameter( 'reset', $data );

		return true;
	}

	/**
	 * Clean and test 'eliminate' parameter.
	 *
	 * @param string $option
	 * @return bool
	 */
	public function _eliminate( $option ) {
		$arguments = explode( ',', $option );
		$eliminate = [];

		foreach ( $arguments as $argument ) {
			$argument = trim( $argument );

			if ( empty( $argument ) ) {
				continue;
			}

			$values = $this->getData( 'eliminate' )['values'];

			if ( !in_array( $argument, $values ) ) {
				return false;
			} else {
				if ( $argument == 'all' || $argument == 'none' ) {
					$boolean = ( $argument == 'all' );
					$values = array_diff( $values, [ 'all', 'none' ] );
					$eliminate = array_flip( $values );

					foreach ( $eliminate as $value => $key ) {
						$eliminate[$value] = $boolean;
					}
				} else {
					$eliminate[$argument] = true;
				}
			}
		}

		$data = $this->getParameter( 'eliminate' );
		$data = array_merge( $data, $eliminate );
		$this->setParameter( 'eliminate', $data );

		return true;
	}
}
