<?php
/**
 * DynamicPageList3
 * DPL Class
 *
 * @author		IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license		GPL-2.0-or-later
 * @package		DynamicPageList3
 *
 **/
namespace DPL;

use \DPL\Lister\Lister;
use \DPL\Lister\UserFormatLister;

class DynamicPageList {
	public $mArticles;
	public $mHeadingType; // type of heading: category, user, etc. (depends on 'ordermethod' param)
	public $mListMode; // html list mode for pages
	public $mParser;
	public $mParserOptions;
	public $mParserTitle;
	public $mOutput;
	public $mTableRow; // formatting rules for table fields

	public function __construct($headings, $bHeadingCount, $iColumns, $iRows, $iRowSize, $sRowColFormat, $articles, $headingtype, $hlistmode, $listmode, &$parser, $aTableRow) {
		$this->mArticles        = $articles;
		$this->mListMode        = $listmode;

		$this->mTableRow       = $aTableRow;

		// cloning the parser in the following statement leads in some cases to a php error in MW 1.15
		// 	You must apply the following patch to avoid this:
		// add in LinkHoldersArray.php at the beginning of function 'merge' the following code lines:
		//		if (!isset($this->interwikis)) {
		//			$this->internals = array();
		//			$this->interwikis = array();
		//			$this->size = 0;
		//			$this->parent  = $other->parent;
		//		}
		$this->mParser = clone $parser;
		// clear state of cloned parser; if the above patch of LinkHoldersArray is not made this
		// can lead to links not being shown in the original document (probably the UIQ_QINU-tags no longer
		// get replaced properly; in combination with the patch however, it does not do any harm.

		$this->mParserOptions = $parser->mOptions;
		$this->mParserTitle   = $parser->mTitle;

		if (!empty($headings)) {
			if ($iColumns != 1 || $iRows != 1) {
				$hspace = 2; // the extra space for headings
				// repeat outer tags for each of the specified columns / rows in the output
				// we assume that a heading roughly takes the space of two articles
				$count = count($articles) + $hspace * count($headings);
				if ($iColumns != 1) {
					$iGroup = $iColumns;
				} else {
					$iGroup = $iRows;
				}
				$nsize = floor($count / $iGroup);
				$rest  = $count - (floor($nsize) * floor($iGroup));
				if ($rest > 0) {
					$nsize += 1;
				}
				$this->mOutput .= "{|".$sRowColFormat."\n|\n";
				if ($nsize < $hspace + 1) {
					$nsize = $hspace + 1; // correction for result sets with one entry
				}
				$this->mHeadingType = $headingtype;
				$this->mOutput .= $hlistmode->sListStart;
				$nstart = 0;
				$greml  = $nsize; // remaining lines in current group
				$g      = 0;
				$offset = 0;
				foreach ($headings as $headingCount) {
					$headingLink = $articles[$nstart - $offset]->mParentHLink;
					$this->mOutput .= $hlistmode->sItemStart;
					$this->mOutput .= $hlistmode->sHeadingStart.$headingLink.$hlistmode->sHeadingEnd;
					if ($bHeadingCount) {
						$this->mOutput .= $this->formatCount($headingCount);
					}
					$offset += $hspace;
					$nstart += $hspace;
					$portion = $headingCount;
					$greml -= $hspace;
					do {
						$greml -= $portion;
						// $this->mOutput .= "nsize=$nsize, portion=$portion, greml=$greml";
						if ($greml > 0) {
							$this->mOutput .= $this->formatList($nstart - $offset, $portion);
							$nstart += $portion;
							$portion = 0;
							break;
						} else {
							$this->mOutput .= $this->formatList($nstart - $offset, $portion + $greml);
							$nstart += ($portion + $greml);
							$portion = (-$greml);
							if ($iColumns != 1) {
								$this->mOutput .= "\n|valign=top|\n";
							} else {
								$this->mOutput .= "\n|-\n|\n";
							}
							++$g;
							// if ($rest != 0 && $g==$rest) $nsize -= 1;
							if ($nstart + $nsize > $count) {
								$nsize = $count - $nstart;
							}
							$greml = $nsize;
							if ($greml <= 0) {
								break;
							}
						}
					} while ($portion > 0);
					$this->mOutput .= $hlistmode->sItemEnd;
				}
				$this->mOutput .= $hlistmode->sListEnd;
				$this->mOutput .= "\n|}\n";
			} else {
				$this->mHeadingType = $headingtype;
				$this->mOutput .= $hlistmode->sListStart;
				$headingStart = 0;
				foreach ($headings as $headingCount) {
					$headingLink = $articles[$headingStart]->mParentHLink;
					$this->mOutput .= $hlistmode->sItemStart;
					$this->mOutput .= $hlistmode->sHeadingStart.$headingLink.$hlistmode->sHeadingEnd;
					if ($bHeadingCount) {
						$this->mOutput .= $this->formatCount($headingCount);
					}
					$this->mOutput .= $this->formatList($headingStart, $headingCount);
					$this->mOutput .= $hlistmode->sItemEnd;
					$headingStart += $headingCount;
				}
				$this->mOutput .= $hlistmode->sListEnd;
			}
		} elseif ($iColumns != 1 || $iRows != 1) {
			// repeat outer tags for each of the specified columns / rows in the output
			$nstart = 0;
			$count  = count($articles);
			if ($iColumns != 1) {
				$iGroup = $iColumns;
			} else {
				$iGroup = $iRows;
			}
			$nsize = floor($count / $iGroup);
			$rest  = $count - (floor($nsize) * floor($iGroup));
			if ($rest > 0) {
				$nsize += 1;
			}
			$this->mOutput .= "{|".$sRowColFormat."\n|\n";
			for ($g = 0; $g < $iGroup; $g++) {
				$this->mOutput .= $this->formatList($nstart, $nsize);
				if ($iColumns != 1) {
					$this->mOutput .= "\n|valign=top|\n";
				} else {
					$this->mOutput .= "\n|-\n|\n";
				}
				$nstart = $nstart + $nsize;
				// if ($rest != 0 && $g+1==$rest) $nsize -= 1;
				if ($nstart + $nsize > $count) {
					$nsize = $count - $nstart;
				}
			}
			$this->mOutput .= "\n|}\n";
		} elseif ($iRowSize > 0) {
			// repeat row header after n lines of output
			$nstart = 0;
			$nsize  = $iRowSize;
			$count  = count($articles);
			$this->mOutput .= '{|'.$sRowColFormat."\n|\n";
			do {
				if ($nstart + $nsize > $count) {
					$nsize = $count - $nstart;
				}
				$this->mOutput .= $this->formatList($nstart, $nsize);
				$this->mOutput .= "\n|-\n|\n";
				$nstart = $nstart + $nsize;
				if ($nstart >= $count) {
					break;
				}
			} while (true);
			$this->mOutput .= "\n|}\n";
		} else {
			$this->mOutput .= $this->formatList(0, count($articles));
		}
	}

	public function formatCount($numart) {
		if ($this->mHeadingType == 'category') {
			$message = 'categoryarticlecount';
		} else {
			$message = 'dpl_articlecount';
		}
		return '<p>'.wfMessage($message, $numart).'</p>';
	}

	/**
	 * Format the list of items.
	 *
	 * @access	public
	 * @param	integer	Start position for the slice of articles.
	 * @param	integer	Total articles to grab.
	 * @return	void
	 */
	public function formatList($iStart, $iCount) {
		$lister = $this->mListMode;
		//categorypage-style list output mode
		if ($lister->getStyle() == Lister::LIST_CATEGORY) {
			return $this->formatCategoryList($iStart, $iCount);
		}

		//process results of query, outputing equivalent of <li>[[Article]]</li> for each result,
		//or something similar if the list uses other startlist/endlist;
		$rBody = '';
		// the following statement caused a problem with multiple columns:  $this->filteredCount = 0;

		return $lister->formatList($this->mArticles, $iStart, $iCount);
	}

	// generate a hyperlink to the article
	public function articleLink($tag, $article) {
		return $lister->replaceTagParameters($tag, $article, $this->filteredCount, '');
	}

	//format one single item of an entry in the output list (i.e. one occurence of one item from the include parameter)
	public function formatSingleItems(&$pieces, $s, $article) {
		$firstCall = true;
		foreach ($pieces as $key => $val) {
			if (array_key_exists($s, $this->mTableRow)) {
				if ($s == 0 || $firstCall) {
					$pieces[$key] = str_replace('%%', $val, $this->mTableRow[$s]);
				} else {
					$n = strpos($this->mTableRow[$s], '|');
					if ($n === false || !(strpos(substr($this->mTableRow[$s], 0, $n), '{') === false) || !(strpos(substr($this->mTableRow[$s], 0, $n), '[') === false)) {
						$pieces[$key] = str_replace('%%', $val, $this->mTableRow[$s]);
					} else {
						$pieces[$key] = str_replace('%%', $val, substr($this->mTableRow[$s], $n + 1));
					}
				}
				$pieces[$key] = str_replace('%IMAGE%', self::imageWithPath($val), $pieces[$key]);
				$pieces[$key] = str_replace('%PAGE%', $article->mTitle->getPrefixedText(), $pieces[$key]);
				if (!empty($article->mCategoryLinks)) {
					$pieces[$key] = str_replace('%CATLIST%', implode(', ', $article->mCategoryLinks), $pieces[$key]);
					$pieces[$key] = str_replace('%CATBULLETS%', '* '.implode("\n* ", $article->mCategoryLinks), $pieces[$key]);
					$pieces[$key] = str_replace('%CATNAMES%', implode(', ', $article->mCategoryTexts), $pieces[$key]);
				} else {
					$pieces[$key] = str_replace('%CATLIST%', '', $pieces[$key]);
					$pieces[$key] = str_replace('%CATBULLETS%', '', $pieces[$key]);
					$pieces[$key] = str_replace('%CATNAMES%', '', $pieces[$key]);
				}
			}
			$firstCall = false;
		}
	}

	//format one single template argument of one occurence of one item from the include parameter
	// is called via a backlink from LST::includeTemplate()
	public function formatTemplateArg($arg, $s, $argNr, $firstCall, $maxlen, $article) {
		// we could try to format fields differently within the first call of a template
		// currently we do not make such a difference

		// if the result starts with a '-' we add a leading space; thus we avoid a misinterpretation of |- as
		// a start of a new row (wiki table syntax)
		if (array_key_exists("$s.$argNr", $this->mTableRow)) {
			$n = -1;
			if ($s >= 1 && $argNr == 0 && !$firstCall) {
				$n = strpos($this->mTableRow["$s.$argNr"], '|');
				if ($n === false || !(strpos(substr($this->mTableRow["$s.$argNr"], 0, $n), '{') === false) || !(strpos(substr($this->mTableRow["$s.$argNr"], 0, $n), '[') === false)) {
					$n = -1;
				}
			}
			$result = str_replace('%%', $arg, substr($this->mTableRow["$s.$argNr"], $n + 1));
			$result = str_replace('%PAGE%', $article->mTitle->getPrefixedText(), $result);
			$result = str_replace('%IMAGE%', self::imageWithPath($arg), $result);
			$result = $this->cutAt($maxlen, $result);
			if (strlen($result) > 0 && $result[0] == '-') {
				return ' '.$result;
			} else {
				return $result;
			}
		}
		$result = $this->cutAt($maxlen, $arg);
		if (strlen($result) > 0 && $result[0] == '-') {
			return ' '.$result;
		} else {
			return $result;
		}
	}

	/**
	 * Truncate a portion of wikitext so that ..
	 * ... it is not larger that $lim characters
	 * ... it is balanced in terms of braces, brackets and tags
	 * ... can be used as content of a wikitable field without spoiling the whole surrounding wikitext structure
	 * @param  $lim     limit of character count for the result
	 * @param  $text    the wikitext to be truncated
	 * @return the truncated text; note that in some cases it may be slightly longer than the given limit
	 *         if the text is alread shorter than the limit or if the limit is negative, the text
	 *         will be returned without any checks for balance of tags
	 */
	public function cutAt($lim, $text) {
		if ($lim < 0) {
			return $text;
		}
		return LST::limitTranscludedText($text, $lim);
	}

	//slightly different from CategoryViewer::formatList() (no need to instantiate a CategoryViewer object)
	public function formatCategoryList($iStart, $iCount) {
		for ($i = $iStart; $i < $iStart + $iCount; $i++) {
			$aArticles[]            = $this->mArticles[$i]->mLink;
			$aArticles_start_char[] = $this->mArticles[$i]->mStartChar;
			$this->filteredCount    = $this->filteredCount + 1;
		}
		if (count($aArticles) > Config::getSetting('categoryStyleListCutoff')) {
			return "__NOTOC____NOEDITSECTION__".\CategoryViewer::columnList($aArticles, $aArticles_start_char);
		} elseif (count($aArticles) > 0) {
			// for short lists of articles in categories.
			return "__NOTOC____NOEDITSECTION__".\CategoryViewer::shortList($aArticles, $aArticles_start_char);
		}
		return '';
	}

	/**
	 * Prepends an image name with its hash path.
	 *
	 * @param  $imgName name of the image (may start with Image: or File:)
	 * @return $uniq_prefix
	 */
	static public function imageWithPath($imgName) {
		$title = \Title::newfromText('Image:'.$imgName);
		if (!is_null($title)) {
			$iTitle   = \Title::makeTitleSafe(6, $title->getDBKey());
			$imageUrl = preg_replace('~^.*images/(.*)~', '\1', \RepoGroup::singleton()->getLocalRepo()->newFile($iTitle)->getPath());
		} else {
			$imageUrl = '???';
		}
		return $imageUrl;
	}

	public function getText() {
		return $this->mOutput;
	}
}
?>