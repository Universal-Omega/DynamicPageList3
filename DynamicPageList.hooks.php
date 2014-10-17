<?php
/**
 * 
 * @file
 * @ingroup Extensions
 * @link http://www.mediawiki.org/wiki/Extension:DynamicPageList_(third-party)	Documentation
 * @author n:en:User:IlyaHaykinson 
 * @author n:en:User:Amgine 
 * @author w:de:Benutzer:Unendlich 
 * @author m:User:Dangerman <cyril.dangerville@gmail.com>
 * @author m:User:Algorithmix <gero.scholz@gmx.de>
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

class DynamicPageListHooks {
	// FATAL
	const FATAL_WRONGNS								= 1;	// $0: 'namespace' or 'notnamespace'
															// $1: wrong parameter given by user
															// $3: list of possible titles of namespaces (except pseudo-namespaces: Media, Special)

	const FATAL_WRONGLINKSTO						= 2;	// $0: linksto' (left as $0 just in case the parameter is renamed in the future)
															// $1: the wrong parameter given by user

	const FATAL_TOOMANYCATS							= 3;	// $0: max number of categories that can be included

	const FATAL_TOOFEWCATS							= 4;	// $0: min number of categories that have to be included

	const FATAL_NOSELECTION							= 5;

	const FATAL_CATDATEBUTNOINCLUDEDCATS			= 6;

	const FATAL_CATDATEBUTMORETHAN1CAT				= 7;

	const FATAL_MORETHAN1TYPEOFDATE					= 8;

	const FATAL_WRONGORDERMETHOD					= 9;	// $0: param=val that is possible only with $1 as last 'ordermethod' parameter
															// $1: last 'ordermethod' parameter required for $0

	const FATAL_DOMINANTSECTIONRANGE				= 10;	// $0: the number of arguments in includepage

	const FATAL_NOCLVIEW							= 11;	// $0: prefix_dpl_clview where 'prefix' is the prefix of your mediawiki table names
															// $1: SQL query to create the prefix_dpl_clview on your mediawiki DB

	const FATAL_OPENREFERENCES						= 12;

	const FATAL_MISSINGPARAMFUNCTION				= 22;

	const FATAL_NOTPROTECTED						= 23;

	// ERROR

	// WARN

	const WARN_UNKNOWNPARAM							= 13;	// $0: unknown parameter given by user
															// $1: list of DPL available parameters separated by ', '

	const WARN_WRONGPARAM							= 14;	// $3: list of valid param values separated by ' | '

	const WARN_WRONGPARAM_INT						= 15;	// $0: param name
															// $1: wrong param value given by user
															// $2: default param value used instead by program

	const WARN_NORESULTS							= 16;

	const WARN_CATOUTPUTBUTWRONGPARAMS				= 17;

	const WARN_HEADINGBUTSIMPLEORDERMETHOD			= 18;	// $0: 'headingmode' value given by user
															// $1: value used instead by program (which means no heading)

	const WARN_DEBUGPARAMNOTFIRST					= 19;	// $0: 'log' value

	const WARN_TRANSCLUSIONLOOP						= 20;	// $0: title of page that creates an infinite transclusion loop

	// INFO

	// DEBUG

	const DEBUG_QUERY								= 21;	// $0: SQL query executed to generate the dynamic page list

	// TRACE
															// Output formatting
															// $1: number of articles

	/**
	 * Extension options
	 */
	public	static $maxCategoryCount		 = 4;	  // Maximum number of categories allowed in the Query
	public	static $minCategoryCount		 = 0;	  // Minimum number of categories needed in the Query
	public	static $maxResultCount			 = 500;	  // Maximum number of results to allow
	public	static $categoryStyleListCutoff	 = 6;	  // Max length to format a list of articles chunked by letter as bullet list, if list bigger, columnar format user (same as cutoff arg for CategoryPage::formatList())
	public	static $allowUnlimitedCategories = true;  // Allow unlimited categories in the Query
	public	static $allowUnlimitedResults	 = false; // Allow unlimited results to be shown
	public	static $allowedNamespaces		 = null;  // to be initialized at first use of DPL, array of all namespaces except Media and Special, because we cannot use the DB for these to generate dynamic page lists. 
													  // Cannot be customized. Use Options::$options['namespace'] or Options::$options['notnamespace'] for customization.
	public	static $likeIntersection = false; // Changes certain default values to comply with Extension:Intersection

	public static $respectParserCache		 = false; // false = make page dynamic ; true = execute only when parser cache is refreshed
													  // .. to be changed in LocalSettings.php
													  
	public static $fixedCategories			 = array(); // an array which holds categories to which the page containing the DPL query
														// shall be assigned althoug reset_all|categories has been used
														// see the fixcategory command



	// Note: If you add a line like the following to your LocalSetings.php, DPL will only run from protected pages	  
	// Options::$options['RunFromProtectedPagesOnly'] = "<small><i>Extension DPL (warning): current configuration allows execution from protected pages only.</i></small>";

	public static $debugMinLevels = array();
	public static $createdLinks; // the links created by DPL are collected here;
								 // they can be removed during the final ouput
								 // phase of the MediaWiki parser

	/**
	 * Sets up this extension's parser functions.
	 *
	 * @access	public
	 * @param	object	Parser object passed as a reference.
	 * @return	boolean true
	 */
	static public function onParserFirstCallInit(Parser &$parser) {
		// DPL offers the same functionality as Intersection; so we register the <DynamicPageList> tag
		// in case LabeledSection Extension is not installed we need to remove section markers

		$parser->setHook('section',				[__CLASS__, 'dplTag']);
		$parser->setHook('DPL',					[__CLASS__, 'dplTag']);
		$parser->setHook('DynamicPageList',		[__CLASS__, 'intersectionTag']);

		$parser->setFunctionHook('dpl',			[__CLASS__, 'dplParserFunction']);
		$parser->setFunctionHook('dplnum',		[__CLASS__, 'dplNumParserFunction']);
		$parser->setFunctionHook('dplvar',		[__CLASS__, 'dplVarParserFunction']);
		$parser->setFunctionHook('dplreplace',	[__CLASS__, 'dplReplaceParserFunction']);
		$parser->setFunctionHook('dplchapter',	[__CLASS__, 'dplChapterParserFunction']);
		$parser->setFunctionHook('dplmatrix',	[__CLASS__, 'dplMatrixParserFunction']);

		self::init();

		return true;
	}

	/**
	 * Sets up this extension's parser functions for migration from Intersection.
	 *
	 * @access	public
	 * @param	object	Parser object passed as a reference.
	 * @return	boolean true
	 */
	static public function setupMigration(Parser &$parser) {
		$parser->setHook('Intersection', [__CLASS__, 'intersectionTag']);

		self::init();

		return true;
	}

	/**
	 * Common initializer for usage from parser entry points.
	 *
	 * @access	private
	 * @return	void
	 */
	static private function init() {
		global $wgUser;

		if (!isset(self::$createdLinks)) {
			self::$createdLinks=array( 
				'resetLinks'=> false, 'resetTemplates' => false, 
				'resetCategories' => false, 'resetImages' => false, 'resetdone' => false , 'elimdone' => false );
		}

		// make sure page "Template:Extension DPL" exists
		$title = Title::newFromText('Template:Extension DPL');

		if (!$title->exists() && $wgUser->isAllowed('edit')) {
			$article = new Article($title);
			$article->doEdit(
				"<noinclude>This page was automatically created. It serves as an anchor page for all '''[[Special:WhatLinksHere/Template:Extension_DPL|invocations]]''' of [http://mediawiki.org/wiki/Extension:DynamicPageList Extension:DynamicPageList (DPL)].</noinclude>",
				$title,
				EDIT_NEW | EDIT_FORCE_BOT
			);
		}

		/**
		 *	Define codes and map debug message to min debug level above which message can be displayed
		 */
		$debugCodes = array(
			// FATAL
			1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
			// WARN
			2, 2, 2, 2, 2, 2, 2, 2,
			// DEBUG
			3
		);

		foreach ($debugCodes as $i => $minlevel) {
			self::$debugMinLevels[$i] = $minlevel;
		}
	}

	/**
	 * Set to behave like intersection.
	 *
	 * @access	private
	 * @param	boolean	Behave Like Intersection
	 * @return	void
	 */
	static private function setLikeIntersection($mode = false) {
		self::$likeIntersection = $mode;
	}

	/**
	 * Is like intersection?
	 *
	 * @access	public
	 * @return	boolean	Behaving Like Intersection
	 */
	static public function isLikeIntersection() {
		return (bool) self::$likeIntersection;
	}

	/**
	 * Tag <section> entry point.
	 *
	 * @access	public
	 * @param	string	Namespace prefixed article of the PDF file to display.
	 * @param	array	Arguments on the tag.
	 * @param	object	Parser object.
	 * @param	object	PPFrame object.
	 * @return	string	HTML
	 */
	public static function intersectionTag($input, $params = array(), Parser $parser, PPFrame $frame) {
		self::setLikeIntersection(true);
		return self::executeTag($input, $params = array(), $parser, $frame);
	}

	/**
	 * Tag <dpl> entry point.
	 *
	 * @access	public
	 * @param	string	Namespace prefixed article of the PDF file to display.
	 * @param	array	Arguments on the tag.
	 * @param	object	Parser object.
	 * @param	object	PPFrame object.
	 * @return	string	HTML
	 */
	public static function dplTag($input, $params = array(), Parser $parser, PPFrame $frame) {
		self::setLikeIntersection(false);
		return self::executeTag($input, $params = array(), $parser, $frame);
	}

	/**
	 * The callback function wrapper for converting the input text to HTML output
	 *
	 * @access	public
	 * @param	string	Namespace prefixed article of the PDF file to display.
	 * @param	array	Arguments on the tag.
	 * @param	object	Parser object.
	 * @param	object	PPFrame object.
	 * @return	string	HTML
	 */
	private static function executeTag($input, $params = array(), Parser $parser, PPFrame $frame) {
		// entry point for user tag <dpl>  or  <DynamicPageList>
		// create list and do a recursive parse of the output

		// $dump1	= self::dumpParsedRefs($parser,"before DPL tag");
		$parse = new \DPL\Parse();
		$text = $parse->parse($input, $params, $parser, $reset, 'tag');
		// $dump2	= self::dumpParsedRefs($parser,"after DPL tag");
		if ($reset[1]) {	// we can remove the templates by save/restore
			$saveTemplates = $parser->mOutput->mTemplates;
		}
		if ($reset[2]) {	// we can remove the categories by save/restore
			$saveCategories = $parser->mOutput->mCategories;
		}
		if ($reset[3]) {	// we can remove the images by save/restore
			$saveImages = $parser->mOutput->mImages;
		}
		$parsedDPL = $parser->recursiveTagParse($text);
		if ($reset[1]) {	// TEMPLATES
			$parser->mOutput->mTemplates =$saveTemplates;
		}
		if ($reset[2]) {	// CATEGORIES
			$parser->mOutput->mCategories =$saveCategories;
		}
		if ($reset[3]) {	// IMAGES
			$parser->mOutput->mImages =$saveImages;
		}
		// $dump3	= self::dumpParsedRefs($parser,"after tag parse");
		// return $dump1.$parsedDPL.$dump2.$dump3;
		return $parsedDPL;
	}

	/**
	 * The #dpl parser tag entry point.
	 *
	 * @access	public
	 * @param	object	Parser object passed as a reference.
	 * @return	string	Wiki Text
	 */
	static public function dplParserFunction(&$parser) {
		self::setLikeIntersection(false);

		// callback for the parser function {{#dpl:	  or   {{DynamicPageList::
		$params = array();
		$input="";

		$numargs = func_num_args();
		if ($numargs < 2) {
		  $input = "#dpl: no arguments specified";
		  return str_replace('§','<','§pre>§nowiki>'.$input.'§/nowiki>§/pre>');
		}

		// fetch all user-provided arguments (skipping $parser)
		$arg_list = func_get_args();
		for ($i = 1; $i < $numargs; $i++) {
		  $p1 = $arg_list[$i];
		  $input .= str_replace("\n","",$p1) ."\n";
		}
		// for debugging you may want to uncomment the following statement
		// return str_replace('§','<','§pre>§nowiki>'.$input.'§/nowiki>§/pre>');


		// $dump1	= self::dumpParsedRefs($parser,"before DPL func");
		// $text	= \DPL\Parse::parse($input, $params, $parser, $reset, 'func');
		// $dump2	= self::dumpParsedRefs($parser,"after DPL func");
		// return $dump1.$text.$dump2;

		$parse = new \DPL\Parse();
		$dplresult = $parse->parse($input, $params, $parser, $reset, 'func');
		return array( // parser needs to be coaxed to do further recursive processing
			$parser->getPreprocessor()->preprocessToObj($dplresult, Parser::PTD_FOR_INCLUSION ),
			'isLocalObj' => true,
			'title' => $parser->getTitle()
		);

	}

	public static function dplNumParserFunction(&$parser, $text='') {
		$num = str_replace('&#160;',' ',$text);
		$num = str_replace('&nbsp;',' ',$text);
		$num = preg_replace('/([0-9])([.])([0-9][0-9]?[^0-9,])/','\1,\3',$num);
		$num = preg_replace('/([0-9.]+),([0-9][0-9][0-9])\s*Mrd/','\1\2 000000 ',$num);
		$num = preg_replace('/([0-9.]+),([0-9][0-9])\s*Mrd/','\1\2 0000000 ',$num);
		$num = preg_replace('/([0-9.]+),([0-9])\s*Mrd/','\1\2 00000000 ',$num);
		$num = preg_replace('/\s*Mrd/','000000000 ',$num);
		$num = preg_replace('/([0-9.]+),([0-9][0-9][0-9])\s*Mio/','\1\2 000 ',$num);
		$num = preg_replace('/([0-9.]+),([0-9][0-9])\s*Mio/','\1\2 0000 ',$num);
		$num = preg_replace('/([0-9.]+),([0-9])\s*Mio/','\1\2 00000 ',$num);
		$num = preg_replace('/\s*Mio/','000000 ',$num);
		$num = preg_replace('/[. ]/','',$num);
		$num = preg_replace('/^[^0-9]+/','',$num);
		$num = preg_replace('/[^0-9].*/','',$num);
		return $num;
	} 

	public static function dplVarParserFunction(&$parser, $cmd) {
		$args = func_get_args();
		if ($cmd=='set') {
			return \DPL\Variables::setVar($args);
		} elseif ($cmd=='default') {
			return \DPL\Variables::setVarDefault($args);
		}
		return \DPL\Variables::getVar($cmd);
	} 

	private static function isRegexp ($needle) {
		if (strlen($needle)<3) {
			return false;
		}
		if (ctype_alnum($needle[0])) return false;
		$nettoNeedle = preg_replace('/[ismu]*$/','',$needle);
		if (strlen($nettoNeedle)<2) return false;
		if ($needle[0] == $nettoNeedle[strlen($nettoNeedle)-1]) return true;
		return false;
	}

	public static function dplReplaceParserFunction(&$parser, $text, $pat, $repl='') {
		if ($text=='' || $pat=='') return '';
		# convert \n to a real newline character
		$repl = str_replace('\n',"\n",$repl);
 
		# replace
		if (!self::isRegexp($pat) ) $pat='`'.str_replace('`','\`',$pat).'`';

		return preg_replace ( $pat, $repl, $text );
	}

	public static function dplChapterParserFunction(&$parser, $text='', $heading=' ', $maxLength = -1, $page = '?page?', $link = 'default', $trim=false ) {
		$output = \DPL\LST::extractHeadingFromText($parser, $page, '?title?', $text, $heading, '', $sectionHeading, true, $maxLength, $link, $trim);
		return $output[0];
	}

	public static function dplMatrixParserFunction(&$parser, $name, $yes, $no, $flip, $matrix ) {
		$lines = explode("\n",$matrix);
		$m = array();
		$sources = array();
		$targets = array();
		$from = '';
		$to = '';
		if ($flip=='' | $flip=='normal')	$flip=false;
		else								$flip=true;
		if ($name=='') $name='&#160;';
		if ($yes=='') $yes= ' x ';
		if ($no=='') $no = '&#160;';
		if ($no[0]=='-') $no = " $no ";
		foreach ($lines as $line) {
			if (strlen($line)<=0) continue;
			if ($line[0]!=' ') {
				$from = preg_split(' *\~\~ *',trim($line),2);
				if (!array_key_exists($from[0],$sources)) {
					if (count($from)<2 || $from[1]=='') $sources[$from[0]] = $from[0];
					else								$sources[$from[0]] = $from[1];
					$m[$from[0]] = array();
				}
			}
			else if (trim($line) != '') {
				$to = preg_split(' *\~\~ *',trim($line),2);
				if (count($to)<2 || $to[1]=='') $targets[$to[0]] = $to[0];
				else							$targets[$to[0]] = $to[1];
				$m[$from[0]][$to[0]] = true;
			}
		}
		ksort($targets);

		$header = "\n";

		if ($flip) {
			foreach ($sources as $from => $fromName) {
				$header .= "![[$from|".$fromName."]]\n";
			}
			foreach ($targets as $to => $toName) {
				$targets[$to] = "[[$to|$toName]]";
				foreach ($sources as $from => $fromName) {
					if (array_key_exists($to,$m[$from])) {
						$targets[$to] .= "\n|$yes";
					}
					else {
						$targets[$to] .= "\n|$no";
					}
				}
				$targets[$to].= "\n|--\n";
			}
			return "{|class=dplmatrix\n|$name"."\n".$header."|--\n!".join("\n!",$targets)."\n|}";
		}
		else {
			foreach ($targets as $to => $toName) {
				$header .= "![[$to|".$toName."]]\n";
			}
			foreach ($sources as $from => $fromName) {
				$sources[$from] = "[[$from|$fromName]]";			
				foreach ($targets as $to => $toName) {
					if (array_key_exists($to,$m[$from])) {
						$sources[$from] .= "\n|$yes";
					}
					else {
						$sources[$from] .= "\n|$no";
					}
				}
				$sources[$from].= "\n|--\n";
			}
			return "{|class=dplmatrix\n|$name"."\n".$header."|--\n!".join("\n!",$sources)."\n|}";
		}
	} 

	private static function dumpParsedRefs($parser,$label) {
		//if (!preg_match("/Query Q/",$parser->mTitle->getText())) return '';
		echo '<pre>parser mLinks: ';
		ob_start(); var_dump($parser->mOutput->mLinks); $a=ob_get_contents(); ob_end_clean(); echo htmlspecialchars($a,ENT_QUOTES);
		echo '</pre>';
		echo '<pre>parser mTemplates: ';
		ob_start(); var_dump($parser->mOutput->mTemplates); $a=ob_get_contents(); ob_end_clean(); echo htmlspecialchars($a,ENT_QUOTES);
		echo '</pre>';
	}

	//remove section markers in case the LabeledSectionTransclusion extension is not installed.
	public static function removeSectionMarkers( $in, $assocArgs=array(), $parser=null ) {
		return '';
	}

	public static function fixCategory($cat) {
		if ($cat!='') {
			self::$fixedCategories[$cat] = 1;
		}
	} 

	// reset everything; some categories may have been fixed, however via  fixcategory=
	public static function endReset( &$parser, $text ) {
		if (!self::$createdLinks['resetdone']) {
			self::$createdLinks['resetdone'] = true;
			foreach ($parser->mOutput->mCategories as $key => $val) {
				if (array_key_exists($key,self::$fixedCategories)) self::$fixedCategories[$key] = $val;
			}
			// $text .= self::dumpParsedRefs($parser,"before final reset");
			if (self::$createdLinks['resetLinks']) {
				$parser->mOutput->mLinks = [];
			}
			if (self::$createdLinks['resetCategories']) {
				$parser->mOutput->mCategories = self::$fixedCategories;
			}
			if (self::$createdLinks['resetTemplates']) {
				$parser->mOutput->mTemplates = [];
			}
			if (self::$createdLinks['resetImages']) {
				$parser->mOutput->mImages = [];
			}
			// $text .= self::dumpParsedRefs($parser,"after final reset");
			self::$fixedCategories = [];
		}
		return true;
	}

	public static function endEliminate( &$parser, &$text ) {
		// called during the final output phase; removes links created by DPL
		if (isset(self::$createdLinks)) {
			// self::dumpParsedRefs($parser,"before final eliminate");
			if (array_key_exists(0,self::$createdLinks)) {
				foreach ($parser->mOutput->getLinks() as $nsp => $link) {
					if (!array_key_exists($nsp,self::$createdLinks[0])) {
						continue;
					}
					// echo ("<pre> elim: created Links [$nsp] = ". count(DynamicPageListHooks::$createdLinks[0][$nsp])."</pre>\n");
					// echo ("<pre> elim: parser  Links [$nsp] = ". count($parser->mOutput->mLinks[$nsp])			 ."</pre>\n");
					$parser->mOutput->mLinks[$nsp] = array_diff_assoc($parser->mOutput->mLinks[$nsp],self::$createdLinks[0][$nsp]);
					// echo ("<pre> elim: parser  Links [$nsp] nachher = ". count($parser->mOutput->mLinks[$nsp])	  ."</pre>\n");
					if (count($parser->mOutput->mLinks[$nsp])==0) {
						unset ($parser->mOutput->mLinks[$nsp]);
					}
				}
			}
			if (isset(self::$createdLinks) && array_key_exists(1,self::$createdLinks)) {
				foreach ($parser->mOutput->mTemplates as $nsp => $tpl) {
					if (!array_key_exists($nsp,self::$createdLinks[1])) {
						continue;
					}
					// echo ("<pre> elim: created Tpls [$nsp] = ". count(DynamicPageListHooks::$createdLinks[1][$nsp])."</pre>\n");
					// echo ("<pre> elim: parser  Tpls [$nsp] = ". count($parser->mOutput->mTemplates[$nsp])			."</pre>\n");
					$parser->mOutput->mTemplates[$nsp] = array_diff_assoc($parser->mOutput->mTemplates[$nsp],self::$createdLinks[1][$nsp]);
					// echo ("<pre> elim: parser  Tpls [$nsp] nachher = ". count($parser->mOutput->mTemplates[$nsp])	 ."</pre>\n");
					if (count($parser->mOutput->mTemplates[$nsp])==0) {
						unset ($parser->mOutput->mTemplates[$nsp]);
					}
				}
			}
			if (isset(self::$createdLinks) && array_key_exists(2,self::$createdLinks)) {
				$parser->mOutput->mCategories = array_diff_assoc($parser->mOutput->mCategories,self::$createdLinks[2]);
			}
			if (isset(self::$createdLinks) && array_key_exists(3,self::$createdLinks)) {
				$parser->mOutput->mImages = array_diff_assoc($parser->mOutput->mImages,self::$createdLinks[3]);
			}
			// $text .= self::dumpParsedRefs($parser,"after final eliminate".$parser->mTitle->getText());
		}

		//self::$createdLinks=array(
		//		  'resetLinks'=> false, 'resetTemplates' => false,
		//		  'resetCategories' => false, 'resetImages' => false, 'resetdone' => false );
		return true;
	}
}
?>