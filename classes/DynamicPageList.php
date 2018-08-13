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
	public $mParser;
	public $mParserOptions;
	public $mParserTitle;
	public $mOutput;

	public function __construct($parameters, $headings, $articles, $headingtype, $hlistmode, $lister) {
		$this->mArticles = $articles;

		$showHeadingCount = $parameters->getParameter('headingcount');
		$columns = $parameters->getParameter('columns');
		$rows = $parameters->getParameter('rows');
		$rowSize = $parameters->getParameter('rowsize');
		$rowColFormat = $parameters->getParameter('rowcolformat');

		if (!empty($headings)) {
			if ($columns != 1 || $rows != 1) {
				$hspace = 2; // the extra space for headings
				// repeat outer tags for each of the specified columns / rows in the output
				// we assume that a heading roughly takes the space of two articles
				$count = count($articles) + $hspace * count($headings);
				if ($columns != 1) {
					$iGroup = $columns;
				} else {
					$iGroup = $rows;
				}
				$nsize = floor($count / $iGroup);
				$rest  = $count - (floor($nsize) * floor($iGroup));
				if ($rest > 0) {
					$nsize += 1;
				}
				$this->mOutput .= "{|".$rowColFormat."\n|\n";
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
					if ($showHeadingCount) {
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
							$this->mOutput .= $lister->formatList($this->mArticles, $nstart - $offset, $portion);
							$nstart += $portion;
							$portion = 0;
							break;
						} else {
							$this->mOutput .= $lister->formatList($this->mArticles, $nstart - $offset, $portion + $greml);
							$nstart += ($portion + $greml);
							$portion = (-$greml);
							if ($columns != 1) {
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
					if ($showHeadingCount) {
						$this->mOutput .= $this->formatCount($headingCount);
					}
					$this->mOutput .= $lister->formatList($this->mArticles, $headingStart, $headingCount);
					$this->mOutput .= $hlistmode->sItemEnd;
					$headingStart += $headingCount;
				}
				$this->mOutput .= $hlistmode->sListEnd;
			}
		} elseif ($columns != 1 || $rows != 1) {
			// repeat outer tags for each of the specified columns / rows in the output
			$nstart = 0;
			$count  = count($articles);
			if ($columns != 1) {
				$iGroup = $columns;
			} else {
				$iGroup = $rows;
			}
			$nsize = floor($count / $iGroup);
			$rest  = $count - (floor($nsize) * floor($iGroup));
			if ($rest > 0) {
				$nsize += 1;
			}
			$this->mOutput .= "{|".$rowColFormat."\n|\n";
			for ($g = 0; $g < $iGroup; $g++) {
				$this->mOutput .= $lister->formatList($this->mArticles, $nstart, $nsize);
				if ($columns != 1) {
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
		} elseif ($rowSize > 0) {
			// repeat row header after n lines of output
			$nstart = 0;
			$nsize  = $rowSize;
			$count  = count($articles);
			$this->mOutput .= '{|'.$rowColFormat."\n|\n";
			do {
				if ($nstart + $nsize > $count) {
					$nsize = $count - $nstart;
				}
				$this->mOutput .= $lister->formatList($this->mArticles, $nstart, $nsize);
				$this->mOutput .= "\n|-\n|\n";
				$nstart = $nstart + $nsize;
				if ($nstart >= $count) {
					break;
				}
			} while (true);
			$this->mOutput .= "\n|}\n";
		} else {
			$this->mOutput .= $lister->formatList($this->mArticles, 0, count($articles));
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

	public function getText() {
		return $this->mOutput;
	}
}
?>