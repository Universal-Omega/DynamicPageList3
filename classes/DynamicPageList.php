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

class DynamicPageList {
	public $mArticles;
	public $mHeadingType; // type of heading: category, user, etc. (depends on 'ordermethod' param)
	public $mListMode; // html list mode for pages
	public $mParser;
	public $mParserOptions;
	public $mParserTitle;
	public $mOutput;

	public function __construct($headings, $bHeadingCount, $iColumns, $iRows, $iRowSize, $sRowColFormat, $articles, $headingtype, $hlistmode, $listmode, &$parser) {
		$this->mArticles        = $articles;
		$this->mListMode        = $listmode;

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

		return $lister->formatList($this->mArticles, $iStart, $iCount);
	}

	// generate a hyperlink to the article
	public function articleLink($tag, $article) {
		return $lister->replaceTagParameters($tag, $article, $this->filteredCount, '');
	}

	public function getText() {
		return $this->mOutput;
	}
}
?>