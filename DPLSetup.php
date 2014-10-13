<?php
/**
 * 
 * @file
 * @ingroup Extensions
 * @link http://www.mediawiki.org/wiki/Extension:DynamicPageList_(third-party)  Documentation
 * @author n:en:User:IlyaHaykinson 
 * @author n:en:User:Amgine 
 * @author w:de:Benutzer:Unendlich 
 * @author m:User:Dangerman <cyril.dangerville@gmail.com>
 * @author m:User:Algorithmix <gero.scholz@gmx.de>
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 *
 */

class ExtDynamicPageList {
    // FATAL
    const FATAL_WRONGNS                             = 1;    // $0: 'namespace' or 'notnamespace'
                                                            // $1: wrong parameter given by user
                                                            // $3: list of possible titles of namespaces (except pseudo-namespaces: Media, Special)
															
    const FATAL_WRONGLINKSTO                        = 2;    // $0: linksto' (left as $0 just in case the parameter is renamed in the future)
                                                            // $1: the wrong parameter given by user
	
    const FATAL_TOOMANYCATS                         = 3;    // $0: max number of categories that can be included

    const FATAL_TOOFEWCATS                          = 4;    // $0: min number of categories that have to be included

    const FATAL_NOSELECTION                         = 5;

    const FATAL_CATDATEBUTNOINCLUDEDCATS            = 6;

    const FATAL_CATDATEBUTMORETHAN1CAT              = 7;

    const FATAL_MORETHAN1TYPEOFDATE                 = 8;

    const FATAL_WRONGORDERMETHOD                    = 9;    // $0: param=val that is possible only with $1 as last 'ordermethod' parameter
                                                            // $1: last 'ordermethod' parameter required for $0

    const FATAL_DOMINANTSECTIONRANGE                = 10;   // $0: the number of arguments in includepage

    const FATAL_NOCLVIEW                            = 11;   // $0: prefix_dpl_clview where 'prefix' is the prefix of your mediawiki table names
                                                            // $1: SQL query to create the prefix_dpl_clview on your mediawiki DB

    const FATAL_OPENREFERENCES                      = 12;
	
    // ERROR
	
    // WARN
	
    const WARN_UNKNOWNPARAM                         = 13;   // $0: unknown parameter given by user
                                                            // $1: list of DPL available parameters separated by ', '

    const WARN_WRONGPARAM                           = 14;   // $3: list of valid param values separated by ' | '

    const WARN_WRONGPARAM_INT                       = 15;   // $0: param name
                                                            // $1: wrong param value given by user
                                                            // $2: default param value used instead by program

    const WARN_NORESULTS                            = 16;

    const WARN_CATOUTPUTBUTWRONGPARAMS              = 17;

    const WARN_HEADINGBUTSIMPLEORDERMETHOD          = 18;   // $0: 'headingmode' value given by user
                                                            // $1: value used instead by program (which means no heading)

    const WARN_DEBUGPARAMNOTFIRST                   = 19;   // $0: 'log' value

    const WARN_TRANSCLUSIONLOOP                     = 20;   // $0: title of page that creates an infinite transclusion loop

    // INFO

    // DEBUG

    const DEBUG_QUERY                               = 21;   // $0: SQL query executed to generate the dynamic page list

    // TRACE
															// Output formatting
                                                            // $1: number of articles
	
    /**
     * Extension options
     */
    public  static $maxCategoryCount         = 4;     // Maximum number of categories allowed in the Query
    public  static $minCategoryCount         = 0;     // Minimum number of categories needed in the Query
    public  static $maxResultCount           = 500;   // Maximum number of results to allow
    public  static $categoryStyleListCutoff  = 6;     // Max length to format a list of articles chunked by letter as bullet list, if list bigger, columnar format user (same as cutoff arg for CategoryPage::formatList())
    public  static $allowUnlimitedCategories = true;  // Allow unlimited categories in the Query
    public  static $allowUnlimitedResults    = false; // Allow unlimited results to be shown
    public  static $allowedNamespaces        = null;  // to be initialized at first use of DPL, array of all namespaces except Media and Special, because we cannot use the DB for these to generate dynamic page lists. 
										              // Cannot be customized. Use ExtDynamicPageList::$options['namespace'] or ExtDynamicPageList::$options['notnamespace'] for customization.
	public  static $behavingLikeIntersection = false; // Changes certain default values to comply with Extension:Intersection

	/**
	 * Functional Richness
	 * The amount of functionality of DPL that is accesible for the user.
	 *
	 * @var		integer
	 */
	static private $functionalRichness		 = 0;

    public static $respectParserCache		 = false; // false = make page dynamic ; true = execute only when parser cache is refreshed
													  // .. to be changed in LocalSettings.php
													  
	public static $fixedCategories			 = array(); // an array which holds categories to which the page containing the DPL query
														// shall be assigned althoug reset_all|categories has been used
														// see the fixcategory command

    /**
     * Map parameters to possible values.
     * A 'default' key indicates the default value for the parameter.
     * A 'pattern' key indicates a pattern for regular expressions (that the value must match).
     * For some options (e.g. 'namespace'), possible values are not yet defined but will be if necessary (for debugging) 
     */	
    public static $options = array(
        'addauthor'            => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
        'addcategories'        => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
        'addcontribution'      => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
        'addeditdate'          => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
        'addexternallink'      => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
        'addfirstcategorydate' => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
        'addlasteditor'        => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
        'addpagecounter'       => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
        'addpagesize'          => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
        'addpagetoucheddate'   => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
        'adduser'              => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
		
		// default of allowcachedresults depends on behaveasIntersetion and on LocalSettings ...
        'allowcachedresults'   => array( 'true', 'false', 'no', 'yes', 'yes+warn', '0', '1', 'off', 'on'),
        /**
         * search for a page with the same title in another namespace (this is normally the article to a talk page)
         */
        'articlecategory'    => null,

        /**
         * category= Cat11 | Cat12 | ...
         * category= Cat21 | Cat22 | ...
         * ...
         * [Special value] catX='' (empty string without quotes) means pseudo-categoy of Uncategorized pages
         * Means pages have to be in category (Cat11 OR (inclusive) Cat2 OR...) AND (Cat21 OR Cat22 OR...) AND...
         * If '+' prefixes the list of categories (e.g. category=+ Cat1 | Cat 2 ...), only these categories can be used as headings in the DPL. See  'headingmode' param.
         * If '-' prefixes the list of categories (e.g. category=- Cat1 | Cat 2 ...), these categories will not appear as headings in the DPL. See  'headingmode' param.
         * Magic words allowed.
         * @todo define 'category' options (retrieve list of categories from 'categorylinks' table?)
         */
        'category'             => null,
        'categorymatch'        => null,
        'categoryregexp'       => null,
        /**
         * Min and Max of categories allowed for an article
         */
        'categoriesminmax'     => array('default' => '', 'pattern' => '/^\d*,?\d*$/'),
        /**
         * hiddencategories
         */
        'hiddencategories'     => array('default' => 'include', 'exclude', 'only'),
		/**
		 * perform the command and do not query the database
		 */
        'execandexit'		   => array('default' => ''),
		
        /**
         * number of results which shall be skipped before display starts
         * default is 0
         */
        'offset'               => array('default' => '0', 'pattern' => '/^\d*$/'),
        /**
         * Max of results to display, selection is based on random.
         */
        'count'                => array('default' => '500', 'pattern' => '/^\d*$/'),
        /**
         * Max number of results to display, selection is based on random.
         */
        'randomcount'          => array('default' => '', 'pattern' => '/^\d*$/'),
        /**
         * shall the result set be distinct (=default) or not?
         */
        'distinct'             => array('default' => 'true', 'strict', 'false', 'no', 'yes', '0', '1', 'off', 'on'),

        'dplcache'		       => array('default' => ''),
        'dplcacheperiod'       => array('default' => '86400', 'pattern' => '/^\d+$/'), // 86400 = # seconds for one day

        /**
         * number of columns for output, default is 1
         */
        'columns'              => array('default' => '', 'pattern' => '/^\d+$/'),

        /**
         * debug=...
         * - 0: displays no debug message;
         * - 1: displays fatal errors only; 
         * - 2: fatal errors + warnings only;
         * - 3: every debug message.
         * - 4: The SQL statement as an echo before execution.
         * - 5: <nowiki> tags around the ouput
		 * - 6: don't execute SQL statement, only show it
         */
        'debug'                => array( 'default' => '2', '0', '1', '2', '3', '4', '5', '6'),

        /**
         * eliminate=.. avoid creating unnecessary backreferences which point to to DPL results.
         *				it is expensive (in terms of performance) but more precise than "reset"
         * categories: eliminate all category links which result from a DPL call (by transcluded contents)
         * templates:  the same with templates
         * images:	   the same with images
         * links:  	   the same with internal and external links
         * all		   all of the above
         */
        'eliminate'                => array( 'default' => '', 'categories', 'templates', 'links', 'images', 'all', 'none'),
        /**
         * Mode at the heading level with ordermethod on multiple components, e.g. category heading with ordermethod=category,...: 
         * html headings (H2, H3, H4), definition list, no heading (none), ordered, unordered.
         */

        'format'       		   => null,

        'goal'                 => array('default' => 'pages', 'pages', 'categories'),

        'headingmode'          => array( 'default' => 'none', 'H2', 'H3', 'H4', 'definition', 'none', 'ordered', 'unordered'),
        /**
         * we can display the number of articles within a heading group
         */
        'headingcount'         => array( 'default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
        /**
         * Attributes for HTML list items (headings) at the heading level, depending on 'headingmode' (e.g. 'li' for ordered/unordered)
         * Not yet applicable to 'headingmode=none | definition | H2 | H3 | H4'.
         * @todo Make 'hitemattr' param applicable to  'none', 'definition', 'H2', 'H3', 'H4' headingmodes.
         * Example: hitemattr= class="topmenuli" style="color: red;"
         */
        'hitemattr'            => array('default' => ''),
        /**
         * Attributes for the HTML list element at the heading/top level, depending on 'headingmode' (e.g. 'ol' for ordered, 'ul' for unordered, 'dl' for definition)
         * Not yet applicable to 'headingmode=none'.
         * @todo Make 'hlistattr' param applicable to  headingmode=none.
         * Example: hlistattr= class="topmenul" id="dmenu"
         */
        'hlistattr'            => array('default' => ''),
        /**
         * PAGE TRANSCLUSION: includepage=... or include=...
         * To include the whole page, use a wildcard:
         * includepage =*
         * To include sections labeled 'sec1' or 'sec2' or... from the page (see the doc of the LabeledSectionTransclusion extension for more info):
         * includepage = sec1,sec2,..
         * To include from the first occurrence of the heading 'heading1' (resp. 'heading2') until the next heading of the same or lower level. Note that this comparison is case insensitive. (See http://www.mediawiki.org/wiki/Extension:Labeled_Section_Transclusion#Transcluding_visual_headings.) :
         * includepage = #heading1,#heading2,....
         * You can combine:
         * includepage= sec1,#heading1,...
         * To include nothing from the page (no transclusion), leave empty:
         * includepage =
         */

        'includepage'          => array('default' => ''),
        /**
         * make comparisons (linksto, linksfrom ) case insensitive
         */
        'ignorecase'		   => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
        'include'          	   => null,
        /**
         * includesubpages    default is TRUE
         */
        'includesubpages'      => array('default' => 'true', 'false', 'no', 'yes', '0', '1', 'off', 'on'),
        /**
         * includematch=..,..    allows to specify regular expressions which must match the included contents
         */
        'includematch'       => array('default' => ''),
        'includematchparsed' => array('default' => ''),
        /** 
         * includenotmatch=..,..    allows to specify regular expressions which must NOT match the included contents
         */
        'includenotmatch'       => array('default' => ''),
        'includenotmatchparsed' => array('default' => ''),
        'includetrim'           => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
        /** 
         * Inline text is some wiki text used to separate list items with 'mode=inline'.
         */
        'inlinetext'           => array('default' => '&#160;-&#160;'),
        /**
         * Max # characters of included page to display.
         * Empty value (default) means no limit.
         * If we include setcions the limit will apply to each section.
         */
        'includemaxlength'     => array('default' => '', 'pattern' => '/^\d*$/'),
        /**
         * Attributes for HTML list items, depending on 'mode' ('li' for ordered/unordered, 'span' for others).
         * Not applicable to 'mode=category'.
         * @todo Make 'itemattr' param applicable to 'mode=category'.
         * Example: itemattr= class="submenuli" style="color: red;"
         */
        'itemattr'             => array('default' => ''),
        /**
         * listseparators is an array of four tags (in wiki syntax) which defines the output of DPL
         * if mode = 'userformat' was specified.
         *   '\n' or '¶'  in the input will be interpreted as a newline character.
         *   '%xxx%'      in the input will be replaced by a corresponding value (xxx= PAGE, NR, COUNT etc.)
         * t1 and t4 are the "outer envelope" for the whole result list, 
         * t2,t3 form an inner envelope around the article name of each entry.
         * Examples: listseparators={|,,\n#[[%PAGE%]]
         * Note: use of html tags was abolished from version 2.0; the first example must be written as:
         *         : listseparators={|,\n|-\n|[[%PAGE%]],,\n|}
         */
        'listseparators'       => array('default' => ''),
        /**
         * sequence of four wiki tags (separated by ",") to be used together with mode = 'userformat'
         *              t1 and t4 define an outer frame for the article list 
         *              t2 and t3 build an inner frame for each article name
         *   example:   listattr=<ul>,<li>,</li>,</ul>
         */
        'listattr'             => array('default' => ''),
        /**
         * this parameter restricts the output to articles which can reached via a link from the specified pages.
         * Examples:   linksfrom=my article|your article
         */
        'linksfrom'            => array('default' => ''),
        /**
         * this parameter restricts the output to articles which cannot be reached via a link from the specified pages.
         * Examples:   notlinksfrom=my article|your article
         */
        'notlinksfrom'         => array('default' => ''),
        /**
         * this parameter restricts the output to articles which contain a reference to one of the specified pages.
         * Examples:   linksto=my article|your article   ,  linksto=Template:my template   ,  linksto = {{FULLPAGENAME}}
         */
        'linksto'              => array('default' => ''),
        /**
         * this parameter restricts the output to articles which do not contain a reference to the specified page.
         */
        'notlinksto'           => array('default' => ''),
        /**
         * this parameter restricts the output to articles which contain an external reference that conatins a certain pattern
         * Examples:   linkstoexternal= www.xyz.com|www.xyz2.com
         */
        'linkstoexternal'      => array('default' => ''),
        /**
         * this parameter restricts the output to articles which use one of the specified images.
         * Examples:   imageused=Image:my image|Image:your image
         */
        'imageused'              => array('default' => ''),
         /**
		 * this parameter restricts the output to images which are used (contained) by one of the specified pages.
		 * Examples:   imagecontainer=my article|your article
		 */
		'imagecontainer'	 => array('default' => ''),
        /**
         * this parameter restricts the output to articles which use the specified template.
         * Examples:   uses=Template:my template
         */
        'uses'                 => array('default' => ''),
        /**
         * this parameter restricts the output to articles which do not use the specified template.
         * Examples:   notuses=Template:my template
         */
        'notuses'              => array('default' => ''),
        /**
         * this parameter restricts the output to the template used by the specified page.
         */
        'usedby'               => array('default' => ''),
        /**
         * allows to specify a username who must be the first editor of the pages we select
         */
        'createdby'            => null,
        /**
         * allows to specify a username who must not be the first editor of the pages we select
         */
        'notcreatedby'            => null,
        /**
         * allows to specify a username who must be among the editors of the pages we select
         */
        'modifiedby'           => null,
        /**
         * allows to specify a username who must not be among the editors of the pages we select
         */
        'notmodifiedby'           => null,
        /**
         * allows to specify a username who must be the last editor of the pages we select
         */
        'lastmodifiedby'           => null,
        /**
         * allows to specify a username who must not be the last editor of the pages we select
         */
        'notlastmodifiedby'           => null,
        /**
         * Mode for list of pages (possibly within a heading, see 'headingmode' param).
         * 'none' mode is implemented as a specific submode of 'inline' with <br /> as inline text
         * 'userformat' does not produce any html tags unless 'listseparators' are specified
         */
        'mode'				   => null,  // depends on behaveAs... mode
        /**
         * by default links to articles of type image or category are escaped (i.e. they appear as a link and do not
         * actually assign the category or show the image; this can be changed.
         * 'true' default
         * 'false'  images are shown, categories are assigned to the current document
         */
        'escapelinks'          => array('default' => 'true','false', 'no', 'yes', '0', '1', 'off', 'on'),
        /**
         * by default the oage containingthe query will not be part of the result set.
         * This can be changed via 'skipthispage=no'. This should be used with care as it may lead to
         * problems which are hard to track down, esp. in combination with contents transclusion.
         */
        'skipthispage'         => array('default' => 'true','false', 'no', 'yes', '0', '1', 'off', 'on'),
        /**
         * namespace= Ns1 | Ns2 | ...
         * [Special value] NsX='' (empty string without quotes) means Main namespace
         * Means pages have to be in namespace Ns1 OR Ns2 OR...
         * Magic words allowed.
         */
        'namespace'            => null,
        /**
         * notcategory= Cat1
         * notcategory = Cat2
         * ...
         * Means pages can be NEITHER in category Cat1 NOR in Cat2 NOR...
         * @todo define 'notcategory' options (retrieve list of categories from 'categorylinks' table?)
         */
        'notcategory'          => null,
        'notcategorymatch'     => null,
        'notcategoryregexp'    => null,
        /**
         * notnamespace= Ns1
         * notnamespace= Ns2
         * ...
         * [Special value] NsX='' (empty string without quotes) means Main namespace
         * Means pages have to be NEITHER in namespace Ns1 NOR Ns2 NOR...
         * Magic words allowed.
        */
        'notnamespace'         => null,
        /**
         * title is the exact name of a page; this is useful if you want to use DPL
         * just for contents inclusion; mode=userformat is automatically implied with title=
        */
        'title'		           => null,
        /**
         * titlematch is a (SQL-LIKE-expression) pattern
         * which restricts the result to pages matching that pattern
        */
        'title<'	           => null,
        'title>'	           => null,
        'scroll'               => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'), 
        'titlematch'           => null,
        'titleregexp'          => null,
        'userdateformat'	   => null,  // depends on behaveAs... mode
        'updaterules'          => array('default' => ''),
        'deleterules'          => array('default' => ''),

        /**
         * nottitlematch is a (SQL-LIKE-expression) pattern
         * which excludes pages matching that pattern from the result
        */
        'nottitlematch'        => null,
        'nottitleregexp'       => null,
        'order'				   => null,  // depends on behaveAs... mode
        /**
         * we can specify something like "latin1_swedish_ci" for case insensitive sorting
        */
        'ordercollation' => array('default' => ''),
        /**
         * 'ordermethod=param1,param2' means ordered by param1 first, then by param2.
         * @todo: add 'ordermethod=category,categoryadd' (for each category CAT, pages ordered by date when page was added to CAT).
         */
        'ordermethod'          => null, // depends on behaveAs... mode
        /**
         * minoredits =... (compatible with ordermethod=...,firstedit | lastedit only)
         * - exclude: ignore minor edits when sorting the list (rev_minor_edit = 0 only)
         * - include: include minor edits
         */
        'minoredits'           => array('default' => 'include', 'exclude', 'include'),
        /**
         * lastrevisionbefore = select the latest revision which was existent before the specified point in time
         */
        'lastrevisionbefore'   => array('default' => '', 'pattern' => '#^[-./:0-9]+$#'),
        /**
         * allrevisionsbefore = select the revisions which were created before the specified point in time
         */
        'allrevisionsbefore'   => array('default' => '', 'pattern' => '#^[-./:0-9]+$#'),
        /**
         * firstrevisionsince = select the first revision which was created after the specified point in time
         */
        'firstrevisionsince'   => array('default' => '', 'pattern' => '#^[-./:0-9]+$#'),
        /**
         * allrevisionssince = select the latest revisions which were created after the specified point in time
         */
        'allrevisionssince'    => array('default' => '', 'pattern' => '#^[-./:0-9]+$#'),
        /**
         * Minimum/Maximum number of revisions required
         */
        'minrevisions'         => array('default' => '', 'pattern' => '/^\d*$/'),
        'maxrevisions'         => array('default' => '', 'pattern' => '/^\d*$/'),
        /**
         * noresultsheader / footer is some wiki text which will be output (instead of a warning message)
         * if the result set is empty; setting 'noresultsheader' to something like ' ' will suppress
         * the warning about empty result set.
         */
        'suppresserrors'       => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'), 
        'noresultsheader'      => array('default' => ''),
        'noresultsfooter'      => array('default' => ''),
        /**
         * oneresultsheader / footer is some wiki text which will be output
         * if the result set contains exactly one entry.
         */
        'oneresultheader'      => array('default' => ''),
        'oneresultfooter'      => array('default' => ''),
        /**
         * openreferences =...
         * - no: excludes pages which do not exist (=default)
         * - yes: includes pages which do not exist -- this conflicts with some other options
         * - only: show only non existing pages [ not implemented so far ]
         */
        'openreferences'       => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
        /**
         * redirects =...
         * - exclude: excludes redirect pages from lists (page_is_redirect = 0 only)
         * - include: allows redirect pages to appear in lists
         * - only: lists only redirect pages in lists (page_is_redirect = 1 only)
         */
        'redirects'            => array('default' => 'exclude', 'exclude', 'include', 'only'),
        /**
         * stablepages =...
         * - exclude: excludes stable pages from lists 
         * - include: allows stable pages to appear in lists
         * - only: lists only stable pages in lists
         */
        'stablepages'          => array('default' => 'include', 'exclude', 'include', 'only'),
        /**
         * qualitypages =...
         * - exclude: excludes quality pages from lists
         * - include: allows quality pages to appear in lists
         * - only: lists only quality pages in lists
         */
        'qualitypages'         => array('default' => 'include', 'exclude', 'include', 'only'),
        /**
         * resultsheader / footer is some wiki text which will be output before / after the result list
         * (if there is at least one result); if 'oneresultheader / footer' is specified it will only be
         * used if there are at least TWO results
         */
        'resultsheader'        => array('default' => ''),
        'resultsfooter'        => array('default' => ''),
        /**
         * reset=..
         * categories: remove all category links which have been defined before the dpl call,
         * 			   typically resulting from template calls or transcluded contents
         * templates:  the same with templates
         * images:	   the same with images
         * links:  	   the same with internal and external links, throws away ALL links, not only DPL generated links!
         * all		   all of the above
         */
        'reset'                => array( 'default' => '', 'categories', 'templates', 'links', 'images', 'all', 'none'),
        /**
         * fixcategory=..   prevents a category from being reset
         */
        'fixcategory'          => array( 'default' => ''),
        /**
         * number of rows for output, default is 1
         * note: a "row" is a group of lines for which the heading tags defined in listseparators/format will be repeated
         */
        'rows'                 => array('default' => '', 'pattern' => '/^\d+$/'),
        /**
         * number of elements in a rows for output, default is "all"
         * note: a "row" is a group of lines for which the heading tags defined in listeseparators will be repeated
         */
        'rowsize'              => array('default' => '', 'pattern' => '/^\d+$/'),
        /**
         * the html tags used for columns and rows
         */
        'rowcolformat'         => array('default' => ''),
        /**
         * secseparators  is a sequence of pairs of tags used to separate sections (see "includepage=name1, name2, ..") 
         * each pair corresponds to one entry in the includepage command
         * if only one tag is given it will be used for all sections as a start tag (end tag will be empty then)
         */
        'secseparators'        => array('default' => ''),
        /**
         * multisecseparators is a list of tags (which correspond to the items in includepage)
         * and which are put between identical sections included from the same file
         */
        'multisecseparators'   => array('default' => ''),
        /**
         * dominantSection is the number (starting from 1) of an includepage argument which shall be used
         * as a dominant value set for the creation of additional output rows (one per value of the 
         * dominant column
         */
        'dominantsection'      => array('default' => '0', 'pattern' => '/^\d*$/'),
        /**
         * showcurid creates a stable link to the current revision of a page
         */
        'showcurid'        	   => array('default' => 'false', 'true', 'no', 'yes', '0', '1', 'off', 'on'),
        /**
         * shownamespace decides whether to show the namespace prefix or not
         */
        'shownamespace'        => array('default' => 'true', 'false', 'no', 'yes', '0', '1', 'off', 'on'),
        /**
         * replaceintitle applies a regex replacement to %TITLE%
         */
        'replaceintitle'       => array('default' => ''),
        /**
         * table is a short hand for combined values of listseparators, colseparators and mulicolseparators
         */
        'table'			       => array('default' => ''),
        /**
         * tablerow allows to define individual formats for table columns
         */
        'tablerow'		       => array('default' => ''),
        /**
         * The number (starting with 1) of the column to be used for sorting
         */
        'tablesortcol'	       => array('default' => '0', 'pattern' => '/^-?\d*$/'),
        /**
         * Max # characters of page title to display.
         * Empty value (default) means no limit.
         * Not applicable to mode=category.
         */
        'titlemaxlength'       => array('default' => '', 'pattern' => '/^\d*$/')
    );

    // Note: If you add a line like the following to your LocalSetings.php, DPL will only run from protected pages    
	// ExtDynamicPageList::$options['RunFromProtectedPagesOnly'] = "<small><i>Extension DPL (warning): current configuration allows execution from protected pages only.</i></small>";


	/**
	 * List of all the valid parameters that can be used per level of functional richness.
	 *
	 * @var		array
	 */
	static private $validParametersForRichnessLevel = [
		0 => [
			'addfirstcategorydate',
			'category',
			'count',
			'hiddencategories',
			'mode',
			'namespace',
			'notcategory',
			'order',
			'ordermethod',
			'qualitypages',
			'redirects',
			'showcurid',
			'shownamespace',
			'stablepages',
			'suppresserrors'
		],
		1 => [
			'allowcachedresults',
			'execandexit',
			'columns',
			'debug',
			'distinct',
			'escapelinks',
			'format',
			'inlinetext',
			'listseparators',
			'notnamespace',
			'offset',
			'oneresultfooter',
			'oneresultheader',
			'ordercollation',
			'noresultsfooter',
			'noresultsheader',
			'randomcount',
			'replaceintitle',
			'resultsfooter',
			'resultsheader',
			'rowcolformat',
			'rows',
			'rowsize',
			'scroll',
			'title',
			'title<',
			'title>',
			'titlemaxlength',
			'userdateformat'
		],
		2 => [
			'addauthor',
			'addcategories',
			'addcontribution',
			'addeditdate',
			'addexternallink',
			'addlasteditor',
			'addpagecounter',
			'addpagesize',
			'addpagetoucheddate',
			'adduser',
			'categoriesminmax',
			'createdby',
			'dominantsection',
			'dplcache',
			'dplcacheperiod',
			'eliminate',
			'fixcategory',
			'headingcount',
			'headingmode',
			'hitemattr',
			'hlistattr',
			'ignorecase',
			'imagecontainer',
			'imageused',
			'include',
			'includematch',
			'includematchparsed',
			'includemaxlength',
			'includenotmatch',
			'includenotmatchparsed',
			'includepage',
			'includesubpages',
			'includetrim',
			'itemattr',
			'lastmodifiedby',
			'linksfrom',
			'linksto',
			'linkstoexternal',
			'listattr',
			'minoredits',
			'modifiedby',
			'multisecseparators',
			'notcreatedby',
			'notlastmodifiedby',
			'notlinksfrom',
			'notlinksto',
			'notmodifiedby',
			'notuses',
			'reset',
			'secseparators',
			'skipthispage',
			'table',
			'tablerow',
			'tablesortcol',
			'titlematch',
			'usedby',
			'uses'
		],
		3 => [
			'allrevisionsbefore',
			'allrevisionssince',
			'articlecategory',
			'categorymatch',
			'categoryregexp',
			'firstrevisionsince',
			'lastrevisionbefore',
			'maxrevisions',
			'minrevisions',
			'notcategorymatch',
			'notcategoryregexp',
			'nottitlematch',
			'nottitleregexp',
			'openreferences',
			'titleregexp'
		],
		4 => [
			'deleterules',
			'goal',
			'updaterules'
		]
	];
		


    public static $debugMinLevels = array();
    public static $createdLinks; // the links created by DPL are collected here;
                                 // they can be removed during the final ouput
                                 // phase of the MediaWiki parser

    private static function behaveLikeIntersection($mode) {
		self::$behavingLikeIntersection = $mode;
	}

	/**
	 * Sets the current functional richness.
	 *
	 * @access	public
	 * @param	integer	Integer level.
	 * @return	void
	 */
    public static function setFunctionalRichness($level) {
		self::$functionalRichness = intval($level);
	}

	/**
	 * Returns the current functional richness.
	 *
	 * @access	public
	 * @return	integer
	 */
	static public function getFunctionalRichness() {
		return self::$functionalRichness;
	}

	/**
	 * Tests if the function is valid for the current functional richness level.
	 *
	 * @access	public
	 * @param	string	Function to test.
	 * @return	boolean	Valid for this functional richness level.
	 */
	static public function testFunctionalRichness($function) {
		$valid = false;
		for ($i = 0; $i <= self::getFunctionalRichness(); $i++) {
			if (in_array($function, self::$validParametersForRichnessLevel[$i])) {
				$valid = true;
				break;
			}
		}
		return $valid;
	}

	/**
	 * Returns the functional richness paramters list list.
	 *
	 * @access	public
	 * @return	array	The functional richness paramters list list.
	 */
	static public function getParametersForFunctionalRichness() {
		return self::$validParametersForRichnessLevel;
	}

	/**
	 * Sets up this extension's parser functions.
	 *
	 * @access	public
	 * @param	object	Parser object passed as a reference.
	 * @return	boolean	true
	 */
	static public function onParserFirstCallInit(Parser &$parser) {
		// DPL offers the same functionality as Intersection; so we register the <DynamicPageList> tag
		// in case LabeledSection Extension is not installed we need to remove section markers

        $parser->setHook('section', 			[__CLASS__, 'dplTag']);
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
	 * @return	boolean	true
	 */
	static public function setupMigration(Parser &$parser) {
		$parser->setHook('Intersection', [__CLASS__, 'intersectionTag']);
		
		self::init();

		return true;
    }

	private static function init() {
		
        if (!isset(self::$createdLinks)) {
            self::$createdLinks=array( 
                'resetLinks'=> false, 'resetTemplates' => false, 
                'resetCategories' => false, 'resetImages' => false, 'resetdone' => false , 'elimdone' => false );
        }

		// make sure page "Template:Extension DPL" exists
        $title = Title::newFromText('Template:Extension DPL');
		global $wgUser;
		if (!$title->exists() && $wgUser->isAllowed('edit')) {
			$article = new Article($title);
			$article->doEdit( "<noinclude>This page was automatically created. It serves as an anchor page for ".
							  "all '''[[Special:WhatLinksHere/Template:Extension_DPL|invocations]]''' ".
							  "of [http://mediawiki.org/wiki/Extension:DynamicPageList Extension:DynamicPageList (DPL)].</noinclude>",
							  $title, EDIT_NEW | EDIT_FORCE_BOT );
			die(header('Location: '.Title::newFromText('Template:Extension DPL')->getFullURL()));
		}
	}

	private static function loadMessages() {

        /**
         *  Define codes and map debug message to min debug level above which message can be displayed
         */
        $debugCodes = array(
            // FATAL
            1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1,
            // WARN
            2, 2, 2, 2, 2, 2, 2, 2,
            // DEBUG
            3
        );

        foreach ($debugCodes as $i => $minlevel )
        {
            self::$debugMinLevels[$i] = $minlevel;
        }

	}

    //------------------------------------------------------------------------------------- ENTRY parser TAG intersection
    public static function intersectionTag( $input, $params, $parser ) {
		self::behaveLikeIntersection(true);
		return self::executeTag($input, $params, $parser);
	}

    //------------------------------------------------------------------------------------- ENTRY parser TAG dpl
    public static function dplTag( $input, $params, $parser ) {
		self::behaveLikeIntersection(false);
		return self::executeTag($input, $params, $parser);
	}

    //------------------------------------------------------------------------------------- ENTRY parser TAG
    // The callback function wrapper for converting the input text to HTML output
    private static function executeTag( $input, $params, $parser ) {

		// late loading of php modules, only if needed
		self::loadMessages();

        // entry point for user tag <dpl>  or  <DynamicPageList>
        // create list and do a recursive parse of the output
    
        // $dump1   = self::dumpParsedRefs($parser,"before DPL tag");
        $text    = \DPL\Main::dynamicPageList($input, $params, $parser, $reset, 'tag');
        // $dump2   = self::dumpParsedRefs($parser,"after DPL tag");
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
        // $dump3   = self::dumpParsedRefs($parser,"after tag parse");
        // return $dump1.$parsedDPL.$dump2.$dump3;
        return $parsedDPL;
    }



    //------------------------------------------------------------------------------------- ENTRY parser FUNCTION #dpl
    public static function dplParserFunction(&$parser) {
		self::loadMessages();

		self::behaveLikeIntersection(false);
		
        // callback for the parser function {{#dpl:   or   {{DynamicPageList::
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
    
        
        // $dump1   = self::dumpParsedRefs($parser,"before DPL func");
        // $text    = \DPL\Main::dynamicPageList($input, $params, $parser, $reset, 'func');
        // $dump2   = self::dumpParsedRefs($parser,"after DPL func");
        // return $dump1.$text.$dump2;
        
        $dplresult = \DPL\Main::dynamicPageList($input, $params, $parser, $reset, 'func');
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
        $output = DPLInclude::extractHeadingFromText($parser, $page, '?title?', $text, $heading, '', $sectionHeading, true, $maxLength, $link, $trim);
        return $output[0];
    } 

    public static function dplMatrixParserFunction(&$parser, $name, $yes, $no, $flip, $matrix ) {
        $lines = explode("\n",$matrix);
        $m = array();
        $sources = array();
        $targets = array();
        $from = '';
        $to = '';
        if ($flip=='' | $flip=='normal') 	$flip=false;
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
		ob_start(); var_dump($parser->mOutput->mLinks);	$a=ob_get_contents(); ob_end_clean(); echo htmlspecialchars($a,ENT_QUOTES);
		echo '</pre>';
		echo '<pre>parser mTemplates: ';
		ob_start(); var_dump($parser->mOutput->mTemplates);	$a=ob_get_contents(); ob_end_clean(); echo htmlspecialchars($a,ENT_QUOTES);
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
					// echo ("<pre> elim: created Links [$nsp] = ". count(ExtDynamicPageList::$createdLinks[0][$nsp])."</pre>\n");
					// echo ("<pre> elim: parser  Links [$nsp] = ". count($parser->mOutput->mLinks[$nsp])            ."</pre>\n");
					$parser->mOutput->mLinks[$nsp] = array_diff_assoc($parser->mOutput->mLinks[$nsp],self::$createdLinks[0][$nsp]);
					// echo ("<pre> elim: parser  Links [$nsp] nachher = ". count($parser->mOutput->mLinks[$nsp])     ."</pre>\n");
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
					// echo ("<pre> elim: created Tpls [$nsp] = ". count(ExtDynamicPageList::$createdLinks[1][$nsp])."</pre>\n");
					// echo ("<pre> elim: parser  Tpls [$nsp] = ". count($parser->mOutput->mTemplates[$nsp])            ."</pre>\n");
					$parser->mOutput->mTemplates[$nsp] = array_diff_assoc($parser->mOutput->mTemplates[$nsp],self::$createdLinks[1][$nsp]);
					// echo ("<pre> elim: parser  Tpls [$nsp] nachher = ". count($parser->mOutput->mTemplates[$nsp])     ."</pre>\n");
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
        //        'resetLinks'=> false, 'resetTemplates' => false, 
        //        'resetCategories' => false, 'resetImages' => false, 'resetdone' => false );
        return true;
    }

}
?>