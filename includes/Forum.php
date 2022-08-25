<?php

namespace MediaWiki\Extension\DynamicPageList3;

use MediaWiki\MediaWikiServices;
use Parser;
use RequestContext;
use Title;
use User;

class Forum {

	/**
	 * Minimum number of categories to look for
	 *
	 * @var int
	 */
	public $minCategories = 1;

	/**
	 * Maximum number of categories to look for
	 *
	 * @var int
	 */
	public $maxCategories = 6;

	/**
	 * Maximum number of results to allow
	 *
	 * @var int
	 */
	public $maxResultCount = 50;

	/**
	 * Allow unlimited results
	 *
	 * @var bool
	 */
	public $unlimitedResults = true;

	/**
	 * Allow unlimited categories
	 *
	 * @var bool
	 */
	public $unlimitedCategories = false;

	/**
	 * Restricted namespaces cannot be searched for page author or creation time.
	 * Unless this array is empty, namespace-free searches are also restricted.
	 * Note: Only integers in this array are checked.
	 *
	 * @var array
	 */
	public $restrictNamespace = [];

	/** @var bool */
	public $bTableMode;

	/** @var bool */
	public $bTimestamp;

	/** @var bool */
	public $bLinkHistory;

	/** @var bool */
	public $bEmbedHistory;

	/** @var bool */
	public $bShowNamespace;

	/** @var bool */
	public $bAddAuthor;

	/** @var bool */
	public $bAddCreationDate;

	/** @var bool */
	public $bAddLastEdit;

	/** @var bool */
	public $bAddLastEditor;

	/** @var bool */
	public $bCompactAuthor;

	/** @var bool */
	public $bCompactEdit;

	/** @var string */
	public $sInput;

	/** @var string */
	public $sOmit;

	/** @var int */
	public $vMarkNew;

	/** @var bool|string */
	public $sCreationDateFormat;

	/** @var bool|string */
	public $sLastEditFormat;

	/**
	 * @param Parser $parser
	 * @param string $name
	 *
	 * @return Title[]
	 */
	public function cat( $parser, $name ) {
		$cats = [];
		if ( preg_match_all( "/^\s*$name\s*=\s*(.*)/mi", $this->sInput, $matches ) ) {
			foreach ( $matches[1] as $cat ) {
				$title = Title::newFromText( $parser->replaceVariables( trim( $cat ) ) );
				if ( $title !== null ) {
					$cats[] = $title;
				}
			}
		}

		return $cats;
	}

	/**
	 * @param string $name
	 * @param int|null $value
	 * @param Parser|null $parser
	 *
	 * @return int|null|string
	 */
	public function get( $name, $value = null, $parser = null ) {
		if ( preg_match( "/^\s*$name\s*=\s*(.*)/mi", $this->sInput, $matches ) ) {
			$arg = trim( $matches[1] );
			if ( is_int( $value ) ) {
				return intval( $arg );
			} elseif ( $parser === null ) {
				return htmlspecialchars( $arg );
			} else {
				return $parser->replaceVariables( $arg );
			}
		}

		return $value;
	}

	/**
	 * @param string $type
	 * @param mixed $error
	 *
	 * @return string
	 */
	public function msg( $type, $error = null ) {
		if ( $error && ( $this->get( 'suppresserrors' ) == 'true' ) ) {
			return '';
		}

		return wfMessage( $type )->escaped();
	}

	/**
	 * @param string $ts
	 * @param string $type
	 * @param bool $df
	 *
	 * @return string
	 */
	public function date( $ts, $type = 'date', $df = false ) {
		// based on Language::date()
		$lang = RequestContext::getMain()->getLanguage();

		$ts = wfTimestamp( TS_MW, $ts );
		$ts = $lang->userAdjust( $ts );

		if ( $df === false ) {
			$df = $lang->getDateFormatString( $type, $lang->dateFormat( true ) );
		}

		return $lang->sprintfDate( $df, $ts );
	}

	/**
	 * @param string &$input
	 * @param Parser $parser
	 *
	 * @return string
	 */
	public function parse( &$input, $parser ) {
		$this->sInput =& $input;
		$sPrefix = $this->get( 'prefix', '', $parser );
		$this->sOmit = $this->get( 'omit', $sPrefix, $parser );
		$this->bAddAuthor = ( $this->get( 'addauthor' ) == 'true' );
		$this->bTimestamp = ( $this->get( 'timestamp' ) != 'false' );
		$this->bAddLastEdit = ( $this->get( 'addlastedit' ) != 'false' );
		$this->sLastEditFormat = $this->get( 'lasteditformat', false );
		$this->bAddLastEditor = ( $this->get( 'addlasteditor' ) == 'true' );
		$this->bAddCreationDate = ( $this->get( 'addcreationdate' ) == 'true' );
		$this->sCreationDateFormat = $this->get( 'creationdateformat', false );

		switch ( $this->get( 'historylink' ) ) {
			case 'embed':
			case 'true':
				$this->bEmbedHistory = true;
			case 'append':
			case 'show':
				$this->bLinkHistory = true;
		}

		$sOrder = 'rev_timestamp';
		switch ( $this->get( 'ordermethod' ) ) {
			case 'categoryadd':
			case 'created':
				$sOrder = 'first_time';
				break;
			case 'pageid':
				$sOrder = 'page_id';
		}

		$arg = $this->get( 'compact' );
		if ( $arg == 'all' || strpos( $arg, 'edit' ) === 0 ) {
			$this->bCompactEdit = $this->bAddLastEdit;
		}

		$this->bCompactAuthor = ( $arg == 'author' || $arg == 'all' );

		$arg = $this->get( 'namespace', '', $parser );
		$iNamespace = MediaWikiServices::getInstance()->getContentLanguage()->getNsIndex( $arg );
		if ( !$iNamespace ) {
			if ( ( $arg ) || ( $arg === '0' ) ) {
				$iNamespace = intval( $arg );
			} else {
				$iNamespace = -1;
			}
		}
		if ( $iNamespace < 0 ) {
			$this->bShowNamespace = ( $this->get( 'shownamespace' ) != 'false' );
		} else {
			$this->bShowNamespace = ( $this->get( 'shownamespace' ) == 'true' );
		}

		$this->bTableMode = false;
		$sStartItem = $sEndItem = '';
		$bCountMode = false;
		$arg = $this->get( 'mode' );

		switch ( $arg ) {
			case 'none':
				$sEndItem = '<br />';
				break;
			case 'count':
				$bCountMode = true;
				break;
			case 'list':
			case 'ordered':
			case 'unordered':
				$sStartItem = '<li>';
				$sEndItem = '</li>';
				break;
			case 'table':
			default:
				$this->bTableMode = true;
				$sStartItem = '<tr>';
				$sEndItem = '</tr>';
		}

		$aCategories = $this->cat( $parser, 'category' );
		$aExcludeCategories = $this->cat( $parser, 'notcategory' );
		$cats = count( $aCategories );
		$nocats = count( $aExcludeCategories );
		$total = $cats + $nocats;
		$output = '';

		if ( $sPrefix === '' && ( ( $cats < 1 && $iNamespace < 0 ) ||
		( $total < $this->minCategories ) ) ) {
			return $this->msg( 'dplforum-toofew', 1 );
		}
		if ( ( $total > $this->maxCategories ) && ( !$this->unlimitedCategories ) ) {
			return $this->msg( 'dplforum-toomany', 1 );
		}

		$count = 1;
		$start = $this->get( 'start', 0 );
		$title = Title::newFromText( $parser->replaceVariables( trim( $this->get( 'title' ) ) ) );
		if ( !( $bCountMode || $this->get( 'cache' ) == 'true' ) ) {
			$parser->getOutput()->updateCacheExpiry( 0 );

			if ( $title === null ) {
				$request = RequestContext::getMain()->getRequest();

				$start += intval( $request->getVal( 'offset' ) );
			}
		}

		if ( $start < 0 ) {
			$start = 0;
		}

		if ( $title === null ) {
			$count = $this->get( 'count', 0 );
			if ( $count > 0 ) {
				if ( $count > $this->maxResultCount ) {
					$count = $this->maxResultCount;
				}
			} elseif ( $this->unlimitedResults ) {
				// maximum integer value
				$count = 0x7FFFFFFF;
			} else {
				$count = $this->maxResultCount;
			}
		}

		// build the SQL query
		$dbr = wfGetDB( DB_REPLICA );
		$sPageTable = $dbr->tableName( 'page' );
		$sRevTable = $dbr->tableName( 'revision' );
		$categorylinks = $dbr->tableName( 'categorylinks' );

		$sSqlSelectFrom = "SELECT page_namespace, page_title, r.rev_timestamp, r.rev_actor";
		$arg = " FROM $sPageTable INNER JOIN $sRevTable AS r ON page_latest = r.rev_id";

		if ( $bCountMode ) {
			$sSqlSelectFrom = "SELECT COUNT(*) AS num_rows FROM $sPageTable";
		} elseif (
			( $this->bAddAuthor || $this->bAddCreationDate || ( $sOrder == 'first_time' ) ) &&
			( ( !$this->restrictNamespace ) ||
				( $iNamespace >= 0 && !in_array( $iNamespace, $this->restrictNamespace ) ) )
		) {
			$sSqlSelectFrom .= ", r.rev_actor AS first_actor, o.rev_timestamp AS first_time" . $arg .
				" INNER JOIN $sRevTable AS o ON o.rev_id =( SELECT MIN(q.rev_id) FROM $sRevTable AS q WHERE q.rev_page = page_id )";
		} else {
			if ( $sOrder == 'first_time' ) {
				$sOrder = 'page_id';
			}

			$sSqlSelectFrom .= $arg;
		}

		$sSqlWhere = ' WHERE 1=1';
		if ( $iNamespace >= 0 ) {
			$sSqlWhere = ' WHERE page_namespace=' . $iNamespace;
		}

		if ( $sPrefix !== '' ) {
			// Escape SQL special characters
			$sPrefix = strtr( $sPrefix,
				[
					'\\' => '\\\\\\\\',
					' ' => '\\_',
					'_' => '\\_',
					'%' => '\\%',
					'\'' => '\\\''
				]
			);

			$sSqlWhere .= " AND page_title LIKE BINARY '" . $sPrefix . "%'";
		}

		switch ( $this->get( 'redirects' ) ) {
			case 'only':
				$sSqlWhere .= ' AND page_is_redirect = 1';
			case 'include':
				break;
			case 'exclude':
			default:
				$sSqlWhere .= ' AND page_is_redirect = 0';
				break;
		}

		$n = 1;
		for ( $i = 0; $i < $cats; $i++ ) {
			$sSqlSelectFrom .= " INNER JOIN $categorylinks AS" .
				" c{$n} ON page_id = c{$n}.cl_from AND c{$n}.cl_to=" .
				$dbr->addQuotes( $aCategories[$i]->getDBkey() );
			$n++;
		}

		for ( $i = 0; $i < $nocats; $i++ ) {
			$sSqlSelectFrom .= " LEFT OUTER JOIN $categorylinks AS" .
				" c{$n} ON page_id = c{$n}.cl_from AND c{$n}.cl_to=" .
				$dbr->addQuotes( $aExcludeCategories[$i]->getDBkey() );
			$sSqlWhere .= " AND c{$n}.cl_to IS NULL";
			$n++;
		}

		if ( !$bCountMode ) {
			$sSqlWhere .= " ORDER BY $sOrder ";

			if ( $this->get( 'order' ) == 'ascending' ) {
				$sSqlWhere .= 'ASC';
			} else {
				$sSqlWhere .= 'DESC';
			}
		}

		// DEBUG: output SQL query
		// $output .= 'QUERY: [' . $dbr->limitResult( $sSqlSelectFrom . $sSqlWhere, $count, $start ) . "]<br />";

		// process the query
		$res = $dbr->query( $dbr->limitResult( $sSqlSelectFrom . $sSqlWhere, $count, $start ), __METHOD__ );

		$this->vMarkNew = $dbr->timestamp( time() -
			intval( $this->get( 'newdays', 7 ) * 86400 ) );

		if ( $bCountMode ) {
			$row = $res->fetchObject();
			if ( $row ) {
				$output .= $row->num_rows;
			} else {
				$output .= '0';
			}
		} elseif ( $title === null ) {
			foreach ( $res as $row ) {
				if ( isset( $row->first_time ) ) {
					$first_time = $row->first_time;
				} else {
					$first_time = '';
				}

				if ( isset( $row->first_actor ) ) {
					$first_user = User::newFromActorId( $row->first_actor )->getName();
				} else {
					$first_user = '';
				}

				$title = Title::makeTitle( $row->page_namespace, $row->page_title );
				$output .= $sStartItem;
				$output .= $this->buildOutput(
					$title,
					$title,
					$row->rev_timestamp,
					User::newFromActorId( $row->rev_actor )->getName(),
					$first_user,
					$first_time
				);

				$output .= $sEndItem . "\n";
			}
		} else {
			$output .= $sStartItem;
			$row = $res->fetchObject();

			if ( $row ) {
				$userText = User::newFromActorId( $row->rev_actor )->getName();
				$output .= $this->buildOutput(
					Title::makeTitle( $row->page_namespace, $row->page_title ),
					$title,
					$row->rev_timestamp,
					$userText
				);
			} else {
				$output .= $this->buildOutput( null, $title, $this->msg( 'dplforum-never' ) );
			}

			$output .= $sEndItem . "\n";
		}

		return $output;
	}

	/**
	 * Generates a single line of output.
	 *
	 * @param Title $page
	 * @param Title $title
	 * @param int|string $time Usually revision.rev_timestamp but can be an i18n msg (dplforum-never)
	 * @param string $user
	 * @param string $author
	 * @param string $made
	 *
	 * @return string
	 */
	public function buildOutput( $page, $title, $time, $user = '', $author = '', $made = '' ) {
		$tableMode =& $this->bTableMode;
		$output = '';
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		if ( $this->bAddCreationDate ) {
			if ( is_numeric( $made ) ) {
				$made = $this->date( $made, 'date', $this->sCreationDateFormat );
			}

			if ( $page && $this->bLinkHistory && !$this->bAddLastEdit ) {
				if ( $this->bEmbedHistory ) {
					$made = $linkRenderer->makeKnownLink( $page, $made, [], [ 'action' => 'history' ] );
				} else {
					$made .= ' (' . $linkRenderer->makeKnownLink( $page,
						wfMessage( 'hist' )->text(), [], [ 'action' => 'history' ] ) . ')';
				}
			}

			if ( $tableMode ) {
				$output .= "<td class='forum_created'>$made</td>";
			} elseif ( $made ) {
				$output = "{$made}: ";
			}
		}

		if ( $tableMode ) {
			$output .= '<td class="forum_title">';
		}

		if ( $this->bShowNamespace == true ) {
			$text = $title->getPrefixedText();
		} else {
			$text = $title->getText();
		}

		if ( ( $this->sOmit ) && strpos( $text, $this->sOmit ) === 0 ) {
			$text = substr( $text, strlen( $this->sOmit ) );
		}

		$props = $query = [];
		if ( is_numeric( $time ) ) {
			if ( $this->bTimestamp ) {
				$query['t'] = $time;
			}

			if ( $time > $this->vMarkNew ) {
				$props['class'] = 'forum_new';
			}
		}

		$output .= $linkRenderer->makeKnownLink( $title, $text, $props, $query );
		$text = '';

		if ( $this->bAddAuthor ) {
			$author = Title::newFromText( $author, NS_USER );

			if ( $author ) {
				$author = $linkRenderer->makeKnownLink( $author, $author->getText() );
			}

			if ( $tableMode ) {
				if ( $this->bCompactAuthor ) {
					if ( $author ) {
						$byAuthor = wfMessage( 'word-separator' )->escaped() . wfMessage( 'dplforum-by' )->rawParams( $author )->escaped();
						$output .= " <span class='forum_author'>$byAuthor</span>";
					} else {
						$output .= " <span class='forum_author'>&nb" . "sp;</span>";
					}
				} else {
					$output .= "</td><td class='forum_author'>$author";
				}
			} elseif ( $author ) {
				$byAuthor = wfMessage( 'word-separator' )->escaped() . wfMessage( 'dplforum-by' )->rawParams( $author )->escaped();
				$output .= $byAuthor;
			}
		}

		if ( $this->bAddLastEdit ) {
			if ( is_numeric( $time ) ) {
				$time = $this->date( $time, 'both', $this->sLastEditFormat );
			}

			if ( $page && $this->bLinkHistory ) {
				if ( $this->bEmbedHistory ) {
					$time = $linkRenderer->makeKnownLink( $page, $time, [], [ 'action' => 'history' ] );
				} else {
					$time .= ' (' . $linkRenderer->makeKnownLink( $page,
						wfMessage( 'hist' )->text(), [], [ 'action' => 'history' ] ) . ')';
				}
			}

			if ( $tableMode ) {
				$output .= "</td><td class='forum_edited'>$time";
			} else {
				$text .= "$time ";
			}
		}

		if ( $this->bAddLastEditor ) {
			$user = Title::newFromText( $user, NS_USER );

			if ( $user ) {
				$user = $linkRenderer->makeKnownLink( $user, $user->getText() );
			}

			if ( $tableMode ) {
				if ( $this->bCompactEdit ) {
					if ( $user ) {
						$byUser = wfMessage( 'dplforum-by' )->rawParams( $user )->escaped();
						$output .= " <span class='forum_editor'>$byUser</span>";
					} else {
						$output .= " <span class='forum_editor'>&nb" . "sp;</span>";
					}
				} else {
					$output .= "</td><td class='forum_editor'>$user";
				}
			} elseif ( $user ) {
				$byUser = wfMessage( 'dplforum-by' )->rawParams( $user )->escaped();
				$text .= $byUser;
			}
		}

		if ( $tableMode ) {
			$output .= '</td>';
		} elseif ( $text ) {
			$output .= wfMessage( 'word-separator' )->escaped() . $this->msg( 'dplforum-edited' ) . " $text";
		}

		return $output;
	}
}
