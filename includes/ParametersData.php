<?php

namespace DPL;

use MWException;

class ParametersData {
	/**
	 * Parameter Richness
	 * The level of parameters that is accesible for the user.
	 *
	 * @var int
	 */
	private $parameterRichness = 0;

	/**
	 * List of all the valid parameters that can be used per level of functional richness.
	 *
	 * @var array
	 */
	private static $parametersForRichnessLevel = [
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
			'titlelt',
			'titlegt',
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
			'cacheperiod',
			'categoriesminmax',
			'createdby',
			'dominantsection',
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
			'tablesortmethod',
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

	/**
	 * Map parameters to possible values.
	 * A 'default' key indicates the default value for the parameter.
	 * A 'pattern' key indicates a pattern for regular expressions (that the value must match).
	 * A 'values' key is the set of possible values.
	 * For some options (e.g. 'namespace'), possible values are not yet defined, but will be if necessary (for debugging).
	 *
	 * @var array
	 */
	private $data = [
		'addauthor' => [
			'default' => false,
			'boolean' => true,
			'open_ref_conflict' => true
		],
		'addcategories' => [
			'default' => false,
			'boolean' => true,
			'open_ref_conflict' => true
		],
		'addcontribution' => [
			'default' => false,
			'boolean' => true,
			'open_ref_conflict' => true
		],
		'addeditdate' => [
			'default' => false,
			'boolean' => true,
			'open_ref_conflict' => true
		],
		'addexternallink' => [
			'default' => false,
			'boolean' => true,
			'open_ref_conflict' => true
		],
		'addfirstcategorydate' => [
			'default' => false,
			'boolean' => true,
			'open_ref_conflict' => true
		],
		'addlasteditor' => [
			'default' => false,
			'boolean' => true,
			'open_ref_conflict' => true
		],
		'addpagecounter' => [
			'default' => false,
			'boolean' => true,
			'open_ref_conflict' => true
		],
		'addpagesize' => [
			'default' => false,
			'boolean' => true,
			'open_ref_conflict' => true
		],
		'addpagetoucheddate' => [
			'default' => false,
			'boolean' => true,
			'open_ref_conflict' => true
		],
		'adduser' => [
			'default' => false,
			'boolean' => true,
			'open_ref_conflict' => true
		],

		// default of allowcachedresults depends on behaveasIntersetion and on LocalSettings ...
		'allowcachedresults' => [
			'default' => true,
			'boolean' => true
		],
		/**
		 * search for a page with the same title in another namespace (this is normally the article to a talk page)
		 */
		'articlecategory' => [
			'default' => null,
			'db_format' => true
		],

		/**
		 * category= Cat11 | Cat12 | ...
		 * category= Cat21 | Cat22 | ...
		 * ...
		 * [Special value] catX='' (empty string without quotes) means pseudo-categoy of Uncategorized pages
		 * Means pages have to be in category (Cat11 OR (inclusive) Cat2 OR...) AND (Cat21 OR Cat22 OR...) AND...
		 * If '+' prefixes the list of categories (e.g. category=+ Cat1 | Cat 2 ...), only these categories can be used as headings in the DPL. See	 'headingmode' param.
		 * If '-' prefixes the list of categories (e.g. category=- Cat1 | Cat 2 ...), these categories will not appear as headings in the DPL. See	'headingmode' param.
		 * Magic words allowed.
		 * @todo define 'category' options (retrieve list of categories from 'categorylinks' table?)
		 */
		'category' => [
			'default' => null,
		],
		'categorymatch' => [
			'default' => null,
		],
		'categoryregexp' => [
			'default' => null,
		],
		/**
		 * Min and Max of categories allowed for an article
		 */
		'categoriesminmax' => [
			'default' => null,
			'pattern' => '#^(\d*)(?:,(\d*))?$#'
		],
		/**
		 * hiddencategories
		 */
		'hiddencategories' => [
			'default' => 'include',
			'values' => [ 'include', 'exclude', 'only' ]
		],
		/**
		 * Perform the command and do not query the database.
		 */
		'execandexit' => [
			'default' => null
		],

		/**
		 * number of results which shall be skipped before display starts
		 * default is 0
		 */
		'offset' => [
			'default' => 0,
			'integer' => true
		],
		/**
		 * Max of results to display, selection is based on random.
		 */
		'count' => [
			'default' => 500,
			'integer' => true
		],
		/**
		 * Max number of results to display, selection is based on random.
		 */
		'randomcount' => [
			'default' => null,
			'integer' => true
		],
		/**
		 * shall the result set be distinct (=default) or not?
		 */
		'distinct' => [
			'default' => true,
			'values' => [ 'strict' ]
		],
		'cacheperiod' => [
			'default' => 3600,
			'integer' => true
		],
		/**
		 * number of columns for output, default is 1
		 */
		'columns' => [
			'default' => 1,
			'integer' => true
		],

		/**
		 * debug=...
		 * - 0: displays no debug message;
		 * - 1: displays fatal errors only;
		 * - 2: fatal errors + warnings only;
		 * - 3: every debug message.
		 * - 4: The SQL statement as an echo before execution.
		 * - 5: <nowiki> tags around the ouput
		 */
		'debug' => [
			'default' => 1,
			'values' => [ 0, 1, 2, 3, 4, 5 ]
		],

		/**
		 * eliminate=.. avoid creating unnecessary backreferences which point to to DPL results.
		 *				it is expensive (in terms of performance) but more precise than "reset"
		 * categories: eliminate all category links which result from a DPL call (by transcluded contents)
		 * templates:  the same with templates
		 * images:	   the same with images
		 * links:	   the same with internal and external links
		 * all		   all of the above
		 */
		'eliminate' => [
			'default' => [],
			'values' => [
				'categories',
				'templates',
				'links',
				'images',
				'all',
				'none'
			]
		],

		'format' => [
			'default' => null,
		],

		'goal' => [
			'default' => 'pages',
			'values' => [
				'pages',
				'categories'
			],
			'open_ref_conflict' => true
		],

		// Include the lowercase variants of header tiers for ease of use.
		'headingmode' => [
			'default' => 'none',
			'values' => [
				'H1',
				'H2',
				'H3',
				'H4',
				'H5',
				'H6',
				'h1',
				'h2',
				'h3',
				'h4',
				'h5',
				'h6',
				// 'header',
				'definition',
				'none',
				'ordered',
				'unordered'
			],
			'open_ref_conflict' => true
		],

		/**
		 * we can display the number of articles within a heading group
		 */
		'headingcount' => [
			'default' => false,
			'boolean' => true,
			'open_ref_conflict' => true
		],

		/**
		 * Attributes for HTML list items (headings) at the heading level, depending on 'headingmode' (e.g. 'li' for ordered/unordered)
		 * Not yet applicable to 'headingmode=none | definition | H2 | H3 | H4'.
		 * @todo Make 'hitemattr' param applicable to  'none', 'definition', 'H2', 'H3', 'H4' headingmodes.
		 * Example: hitemattr= class="topmenuli" style="color: red;"
		 */
		'hitemattr' => [
			'default' => null
		],

		/**
		 * Attributes for the HTML list element at the heading/top level, depending on 'headingmode' (e.g. 'ol' for ordered, 'ul' for unordered, 'dl' for definition)
		 * Not yet applicable to 'headingmode=none'.
		 * @todo Make 'hlistattr' param applicable to  headingmode=none.
		 * Example: hlistattr= class="topmenul" id="dmenu"
		 */
		'hlistattr' => [
			'default' => null
		],

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

		'includepage' => [
			'default' => null
		],

		/**
		 * make comparisons (linksto, linksfrom ) case insensitive
		 */
		'ignorecase' => [
			'default' => false,
			'boolean' => true
		],
		'include' => [
			'default' => null
		],

		/**
		 * includesubpages
		 */
		'includesubpages' => [
			'default' => true,
			'boolean' => true
		],

		/**
		 * includematch=..,..	 allows to specify regular expressions which must match the included contents
		 */
		'includematch' => [
			'default' => null
		],
		'includematchparsed' => [
			'default' => false,
			'boolean' => true
		],
		/**
		 * includenotmatch=..,..	allows to specify regular expressions which must NOT match the included contents
		 */
		'includenotmatch' => [
			'default' => null
		],
		'includenotmatchparsed' => [
			'default' => false,
			'boolean' => true
		],
		'includetrim' => [
			'default' => false,
			'boolean' => true
		],
		/**
		 * Inline text is some wiki text used to separate list items with 'mode=inline'.
		 */
		'inlinetext' => [
			'default' => '&#160;-&#160;',
			'strip_html' => true
		],
		/**
		 * Max # characters of included page to display.
		 * Null means no limit.
		 * If we include sections the limit will apply to each section.
		 */
		'includemaxlength' => [
			'default' => null,
			'integer' => true
		],
		/**
		 * Attributes for HTML list items, depending on 'mode' ('li' for ordered/unordered, 'span' for others).
		 * Not applicable to 'mode=category'.
		 * @todo Make 'itemattr' param applicable to 'mode=category'.
		 * Example: itemattr= class="submenuli" style="color: red;"
		 */
		'itemattr' => [
			'default' => null
		],
		/**
		 * listseparators is an array of four tags (in wiki syntax) which defines the output of DPL
		 * if mode = 'userformat' was specified.
		 *	 '\n' or '¶'  in the input will be interpreted as a newline character.
		 *	 '%xxx%'	  in the input will be replaced by a corresponding value (xxx= PAGE, NR, COUNT etc.)
		 * t1 and t4 are the "outer envelope" for the whole result list,
		 * t2,t3 form an inner envelope around the article name of each entry.
		 * Examples: listseparators={|,,\n#[[%PAGE%]]
		 * Note: use of html tags was abolished from version 2.0; the first example must be written as:
		 *		   : listseparators={|,\n|-\n|[[%PAGE%]],,\n|}
		 */
		'listseparators' => [
			'default' => []
		],
		/**
		 * sequence of four wiki tags (separated by ",") to be used together with mode = 'userformat'
		 *				t1 and t4 define an outer frame for the article list
		 *				t2 and t3 build an inner frame for each article name
		 *	 example:	listattr=<ul>,<li>,</li>,</ul>
		 */
		'listattr' => [
			'default' => null
		],
		/**
		 * this parameter restricts the output to articles which can reached via a link from the specified pages.
		 * Examples:   linksfrom=my article|your article
		 */
		'linksfrom' => [
			'default' => null,
			'page_name_list' => true,
			'page_name_must_exist' => true,
			'set_criteria_found' => true
		],
		/**
		 * this parameter restricts the output to articles which cannot be reached via a link from the specified pages.
		 * Examples:   notlinksfrom=my article|your article
		 */
		'notlinksfrom' => [
			'default' => null,
			'page_name_list' => true,
			'page_name_must_exist' => true,
			'set_criteria_found' => true
		],
		/**
		 * this parameter restricts the output to articles which contain a reference to one of the specified pages.
		 * Examples:   linksto=my article|your article	 ,	linksto=Template:my template   ,  linksto = {{FULLPAGENAME}}
		 */
		'linksto' => [
			'default' => null,
			'open_ref_conflict' => true,
			'page_name_list' => true,
			'page_name_must_exist' => true,
			'set_criteria_found' => true
		],
		/**
		 * this parameter restricts the output to articles which do not contain a reference to the specified page.
		 */
		'notlinksto' => [
			'default' => null,
			'open_ref_conflict' => true,
			'page_name_list' => true,
			'page_name_must_exist' => true,
			'set_criteria_found' => true
		],
		/**
		 * this parameter restricts the output to articles which contain an external reference that conatins a certain pattern
		 * Examples:   linkstoexternal= www.xyz.com|www.xyz2.com
		 */
		'linkstoexternal' => [
			'default' => null,
			'open_ref_conflict' => true,
			'page_name_list' => true,
			'page_name_must_exist' => false,
			'set_criteria_found' => true
		],
		/**
		 * this parameter restricts the output to articles which use one of the specified images.
		 * Examples:   imageused=Image:my image|Image:your image
		 */
		'imageused' => [
			'default' => null,
			'open_ref_conflict' => true,
			'page_name_list' => true,
			'page_name_must_exist' => true,
			'set_criteria_found' => true
		],
		 /**
		  * this parameter restricts the output to images which are used (contained) by one of the specified pages.
		  * Examples:   imagecontainer=my article|your article
		  */
		'imagecontainer' => [
			'default' => null,
			'open_ref_conflict' => false,
			'page_name_list' => true,
			'page_name_must_exist' => true,
			'set_criteria_found' => true
		],
		/**
		 * this parameter restricts the output to articles which use the specified template.
		 * Examples:   uses=Template:my template
		 */
		'uses' => [
			'default' => null,
			'open_ref_conflict' => true,
			'page_name_list' => true,
			'page_name_must_exist' => true,
			'set_criteria_found' => true
		],
		/**
		 * this parameter restricts the output to articles which do not use the specified template.
		 * Examples:   notuses=Template:my template
		 */
		'notuses' => [
			'default' => null,
			'open_ref_conflict' => true,
			'page_name_list' => true,
			'page_name_must_exist' => true,
			'set_criteria_found' => true
		],
		/**
		 * this parameter restricts the output to the template used by the specified page.
		 */
		'usedby' => [
			'default' => null,
			'open_ref_conflict' => true,
			'page_name_list' => true,
			'page_name_must_exist' => true,
			'set_criteria_found' => true
		],
		/**
		 * allows to specify a username who must be the first editor of the pages we select
		 */
		'createdby' => [
			'default' => null,
			'set_criteria_found' => true,
			'open_ref_conflict' => true,
			'preserve_case' => true
		],
		/**
		 * allows to specify a username who must not be the first editor of the pages we select
		 */
		'notcreatedby' => [
			'default' => null,
			'set_criteria_found' => true,
			'open_ref_conflict' => true,
			'preserve_case' => true
		],
		/**
		 * allows to specify a username who must be among the editors of the pages we select
		 */
		'modifiedby' => [
			'default' => null,
			'set_criteria_found' => true,
			'open_ref_conflict' => true,
			'preserve_case' => true
		],
		/**
		 * allows to specify a username who must not be among the editors of the pages we select
		 */
		'notmodifiedby' => [
			'default' => null,
			'set_criteria_found' => true,
			'open_ref_conflict' => true,
			'preserve_case' => true
		],
		/**
		 * allows to specify a username who must be the last editor of the pages we select
		 */
		'lastmodifiedby' => [
			'default' => null,
			'set_criteria_found' => true,
			'open_ref_conflict' => true,
			'preserve_case' => true
		],
		/**
		 * allows to specify a username who must not be the last editor of the pages we select
		 */
		'notlastmodifiedby' => [
			'default' => null,
			'set_criteria_found' => true,
			'open_ref_conflict' => true,
			'preserve_case' => true
		],
		/**
		 * Mode for list of pages (possibly within a heading, see 'headingmode' param).
		 * 'none' mode is implemented as a specific submode of 'inline' with <br /> as inline text
		 * 'userformat' does not produce any html tags unless 'listseparators' are specified
		 */
		'mode' => [
			'default' => 'unordered',
			'values' => [
				'category',
				'definition',
				'gallery',
				'inline',
				'none',
				'ordered',
				'subpage',
				'unordered',
				'userformat'
			]
		],
		/**
		 * by default links to articles of type image or category are escaped (i.e. they appear as a link and do not
		 * actually assign the category or show the image; this can be changed.
		 * 'true' default
		 * 'false'	images are shown, categories are assigned to the current document
		 */
		'escapelinks' => [
			'default' => true,
			'boolean' => true
		],
		/**
		 * By default the page containingthe query will not be part of the result set.
		 * This can be changed via 'skipthispage=no'. This should be used with care as it may lead to
		 * problems which are hard to track down, esp. in combination with contents transclusion.
		 */
		'skipthispage' => [
			'default' => true,
			'boolean' => true
		],
		/**
		 * namespace= Ns1 | Ns2 | ...
		 * [Special value] NsX='' (empty string without quotes) means Main namespace
		 * Means pages have to be in namespace Ns1 OR Ns2 OR...
		 * Magic words allowed.
		 */
		'namespace' => [
			'default' => null,
		],
		/**
		 * notcategory= Cat1
		 * notcategory = Cat2
		 * ...
		 * Means pages can be NEITHER in category Cat1 NOR in Cat2 NOR...
		 * @todo define 'notcategory' options (retrieve list of categories from 'categorylinks' table?)
		 */
		'notcategory' => [
			'default' => null,
		],
		'notcategorymatch' => [
			'default' => null,
		],
		'notcategoryregexp' => [
			'default' => null,
		],
		/**
		 * notnamespace= Ns1
		 * notnamespace= Ns2
		 * ...
		 * [Special value] NsX='' (empty string without quotes) means Main namespace
		 * Means pages have to be NEITHER in namespace Ns1 NOR Ns2 NOR...
		 * Magic words allowed.
		 */
		'notnamespace' => [
			'default' => null,
		],
		/**
		 * title is the exact name of a page; this is useful if you want to use DPL
		 * just for contents inclusion; mode=userformat is automatically implied with title=
		 */
		'title' => [
			'default' => null,
		],
		/**
		 * titlematch is a (SQL-LIKE-expression) pattern
		 * which restricts the result to pages matching that pattern
		 */
		'titlelt' => [
			'default' => null,
			'db_format' => true,
			'set_criteria_found' => true
		],
		'titlegt' => [
			'default' => null,
			'db_format' => true,
			'set_criteria_found' => true
		],
		'scroll' => [
			'default' => false,
			'boolean' => true
		],
		'titlematch' => [
			'default' => null
		],
		'titleregexp' => [
			'default' => null
		],
		'userdateformat' => [
			'default' => 'Y-m-d H:i:s',
			'strip_html' => true
		],
		'updaterules' => [
			'default' => null,
			'permission' => 'dpl_param_update_rules'
		],
		'deleterules' => [
			'default' => null,
			'permission' => 'dpl_param_delete_rules'
		],

		/**
		 * nottitlematch is a (SQL-LIKE-expression) pattern
		 * which excludes pages matching that pattern from the result
		 */
		'nottitlematch' => [
			'default' => null
		],
		'nottitleregexp' => [
			'default' => null
		],
		'order' => [
			'default' => 'ascending',
			'values' => [ 'ascending', 'descending', 'asc', 'desc' ]
		],
		/**
		 * we can specify something like "latin1_swedish_ci" for case insensitive sorting
		 */
		'ordercollation' => [
			'default' => null
		],
		/**
		 * 'ordermethod=param1,param2' means ordered by param1 first, then by param2.
		 * @todo: add 'ordermethod=category,categoryadd' (for each category CAT, pages ordered by date when page was added to CAT).
		 */
		'ordermethod' => [
			'default' => [ 'none' ],
			'values' => [
				'counter',
				'size',
				'category',
				'sortkey',
				'categoryadd',
				'firstedit',
				'lastedit',
				'pagetouched',
				'pagesel',
				'title',
				'titlewithoutnamespace',
				'user',
				'none'
			]
		],
		/**
		 * minoredits =... (compatible with ordermethod=...,firstedit | lastedit only)
		 * - exclude: ignore minor edits (rev_minor_edit = 0 only)
		 * - include: include minor edits
		 */
		'minoredits' => [
			'default' => null,
			'values' => [ 'include', 'exclude' ],
			'open_ref_conflict' => true
		],
		/**
		 * lastrevisionbefore = select the latest revision which was existent before the specified point in time
		 */
		'lastrevisionbefore' => [
			'default' => null,
			'timestamp' => true,
			'open_ref_conflict' => true
		],
		/**
		 * allrevisionsbefore = select the revisions which were created before the specified point in time
		 */
		'allrevisionsbefore' => [
			'default' => null,
			'timestamp' => true,
			'open_ref_conflict' => true
		],
		/**
		 * firstrevisionsince = select the first revision which was created after the specified point in time
		 */
		'firstrevisionsince' => [
			'default' => null,
			'timestamp' => true,
			'open_ref_conflict' => true
		],
		/**
		 * allrevisionssince = select the latest revisions which were created after the specified point in time
		 */
		'allrevisionssince' => [
			'default' => null,
			'timestamp' => true,
			'open_ref_conflict' => true
		],
		/**
		 * Minimum/Maximum number of revisions required
		 */
		'minrevisions' => [
			'default' => null,
			'integer' => true
		],
		'maxrevisions' => [
			'default' => null,
			'integer' => true
		],
		'suppresserrors' => [
			'default' => false,
			'boolean' => true
		],
		/**
		 * noresultsheader / footer is some wiki text which will be output (instead of a warning message)
		 * if the result set is empty; setting 'noresultsheader' to something like ' ' will suppress
		 * the warning about empty result set.
		 */
		'noresultsheader' => [
			'default' => null,
			'strip_html' => true,
			'preserve_case' => true
		],
		'noresultsfooter' => [
			'default' => null,
			'strip_html' => true,
			'preserve_case' => true
		],
		/**
		 * oneresultsheader / footer is some wiki text which will be output
		 * if the result set contains exactly one entry.
		 */
		'oneresultheader' => [
			'default' => null,
			'strip_html' => true,
			'preserve_case' => true
		],
		'oneresultfooter' => [
			'default' => null,
			'strip_html' => true,
			'preserve_case' => true
		],
		/**
		 * openreferences =...
		 * - no: excludes pages which do not exist (=default)
		 * - yes: includes pages which do not exist -- this conflicts with some other options
		 */
		'openreferences' => [
			'default' => false,
			'boolean' => true
		],
		/**
		 * redirects =...
		 * - exclude: excludes redirect pages from lists (page_is_redirect = 0 only)
		 * - include: allows redirect pages to appear in lists
		 * - only: lists only redirect pages in lists (page_is_redirect = 1 only)
		 */
		'redirects' => [
			'default' => 'exclude',
			'values' => [ 'include', 'exclude', 'only' ]
		],
		/**
		 * stablepages =...
		 * - exclude: excludes stable pages from lists
		 * - include: allows stable pages to appear in lists
		 * - only: lists only stable pages in lists
		 */
		'stablepages' => [
			'default' => null,
			'values' => [ 'exclude', 'only' ]
		],
		/**
		 * qualitypages =...
		 * - exclude: excludes quality pages from lists
		 * - include: allows quality pages to appear in lists
		 * - only: lists only quality pages in lists
		 */
		'qualitypages' => [
			'default' => null,
			'values' => [ 'exclude', 'only' ]
		],
		/**
		 * resultsheader / footer is some wiki text which will be output before / after the result list
		 * (if there is at least one result); if 'oneresultheader / footer' is specified it will only be
		 * used if there are at least TWO results
		 */
		'resultsheader' => [
			'default' => null,
			'strip_html' => true,
			'preserve_case' => true
		],
		'resultsfooter' => [
			'default' => null,
			'strip_html' => true,
			'preserve_case' => true
		],
		/**
		 * reset=..
		 * categories: remove all category links which have been defined before the dpl call,
		 *			   typically resulting from template calls or transcluded contents
		 * templates:  the same with templates
		 * images:	   the same with images
		 * links:	   the same with internal and external links, throws away ALL links, not only DPL generated links!
		 * all		   all of the above
		 */
		'reset' => [
			'default' => [],
			'values' => [
				'categories',
				'templates',
				'links',
				'images',
				'all',
				'none'
			]
		],

		/**
		 * fixcategory=..	prevents a category from being reset
		 */
		'fixcategory' => [
			'default' => null
		],

		/**
		 * Number of rows for output, default is 1
		 * Note: a "row" is a group of lines for which the heading tags defined in listseparators/format will be repeated
		 */
		'rows' => [
			'default' => 1,
			'integer' => true
		],

		/**
		 * Number of elements in a rows for output, default is "all"
		 * Note: a "row" is a group of lines for which the heading tags defined in listeseparators will be repeated
		 */
		'rowsize' => [
			'default' => 0,
			'integer' => true
		],

		/**
		 * The HTML attribute tags(class, cellspacing) used for columns and rows in MediaWiki table markup.
		 */
		'rowcolformat' => [
			'default' => null,
			'strip_html' => true
		],
		/**
		 * secseparators  is a sequence of pairs of tags used to separate sections (see "includepage=name1, name2, ..")
		 * each pair corresponds to one entry in the includepage command
		 * if only one tag is given it will be used for all sections as a start tag (end tag will be empty then)
		 */
		'secseparators' => [
			'default' => []
		],
		/**
		 * multisecseparators is a list of tags (which correspond to the items in includepage)
		 * and which are put between identical sections included from the same file
		 */
		'multisecseparators' => [
			'default' => []
		],
		/**
		 * dominantSection is the number (starting from 1) of an includepage argument which shall be used
		 * as a dominant value set for the creation of additional output rows (one per value of the
		 * dominant column
		 */
		'dominantsection' => [
			'default' => 0,
			'integer' => true
		],
		/**
		 * showcurid creates a stable link to the current revision of a page
		 */
		'showcurid' => [
			'default' => false,
			'boolean' => true,
			'open_ref_conflict' => true
		],
		/**
		 * shownamespace decides whether to show the namespace prefix or not
		 */
		'shownamespace' => [
			'default' => true,
			'boolean' => true
		],
		/**
		 * replaceintitle applies a regex replacement to %TITLE%
		 */
		'replaceintitle' => [
			'default' => null
		],
		/**
		 * table is a short hand for combined values of listseparators, colseparators and mulicolseparators
		 */
		'table' => [
			'default' => null
		],
		/**
		 * tablerow allows to define individual formats for table columns
		 */
		'tablerow' => [
			'default' => []
		],
		/**
		 * The number (starting with 1) of the column to be used for sorting
		 */
		'tablesortcol' => [
			'default' => null,
			'integer' => true
		],
		/**
		 * The sorting algorithm for table columns when 'tablesortcol'
		 * is used.
		 * - standard: Use PHP asort() and arsort()
		 * - natural: Use PHP natsort()
		 */
		'tablesortmethod' => [
			'default' => null,
			'values' => [ 'standard', 'natural' ]
		],
		/**
		 * Max # characters of page title to display.
		 * Empty value (default) means no limit.
		 * Not applicable to mode=category.
		 */
		'titlemaxlength' => [
			'default' => null,
			'integer' => true
		]
	];

	public function __construct() {
		$this->setRichness( Config::getSetting( 'functionalRichness' ) );

		if ( DynamicPageListHooks::isLikeIntersection() ) {
			$this->data['ordermethod'] = [
				'default' => 'categoryadd',
				'values' => [
					'categoryadd',
					'lastedit',
					'none'
				]
			];

			$this->data['order'] = [
				'default' => 'descending',
				'values' => [
					'ascending',
					'descending'
				]
			];

			$this->data['mode'] = [
				'default' => 'unordered',
				'values' => [
					'none',
					'ordered',
					'unordered'
				]
			];

			$this->data['userdateformat'] = [
				'default' => 'Y-m-d: '
			];

			$this->data['allowcachedresults']['default'] = 'true';
		}
	}

	/**
	 * Return if the parameter exists.
	 *
	 * @param string $parameter
	 * @return bool
	 */
	public function exists( $parameter ) {
		return array_key_exists( $parameter, $this->data );
	}

	/**
	 * Return data for the supplied parameter.
	 *
	 * @param string $parameter
	 * @return mixed
	 */
	public function getData( $parameter ) {
		if ( array_key_exists( $parameter, $this->data ) ) {
			return $this->data[$parameter];
		} else {
			return false;
		}
	}

	/**
	 * Sets the current parameter richness.
	 *
	 * @param int $level
	 */
	public function setRichness( $level ) {
		$this->parameterRichness = intval( $level );
	}

	/**
	 * Returns the current parameter richness.
	 *
	 * @return int
	 */
	public function getRichness() {
		return $this->parameterRichness;
	}

	/**
	 * Tests if the function is valid for the current functional richness level.
	 *
	 * @param string $function
	 * @return bool
	 */
	public function testRichness( $function ) {
		$valid = false;

		for ( $i = 0; $i <= $this->getRichness(); $i++ ) {
			if ( in_array( $function, self::$parametersForRichnessLevel[$i] ) ) {
				$valid = true;
				break;
			}
		}

		return $valid;
	}

	/**
	 * Returns all parameters for the current richness level or limited to the optional maximum richness.
	 *
	 * @param int|null $level
	 * @return array
	 */
	public function getParametersForRichness( $level = null ) {
		if ( $level === null ) {
			$level = $this->getRichness();
		}

		$parameters = [];

		for ( $i = 0; $i <= $level; $i++ ) {
			$parameters = array_merge( $parameters, self::$parametersForRichnessLevel[$i] );
		}

		sort( $parameters );

		return $parameters;
	}

	/**
	 * Return the default value for the parameter.
	 *
	 * @param string $parameter
	 * @return mixed
	 */
	public function getDefault( $parameter ) {
		if ( array_key_exists( $parameter, $this->data ) ) {
			if ( array_key_exists( 'default', $this->data[$parameter] ) ) {
				return (bool)$this->data[$parameter]['default'];
			}

			return null;
		}

		throw new MWException( __METHOD__ . ": Attempted to load a parameter that does not exist." );
	}

	/**
	 * Return the acceptable values for the parameter.
	 *
	 * @param string $parameter
	 * @return mixed
	 */
	public function getValues( $parameter ) {
		if ( array_key_exists( $parameter, $this->data ) ) {
			if ( array_key_exists( 'values', $this->data[$parameter] ) ) {
				return (bool)$this->data[$parameter]['values'];
			}

			return false;
		}

		throw new MWException( __METHOD__ . ": Attempted to load a parameter that does not exist." );
	}

	/**
	 * Does the parameter set that criteria for selection was found?
	 *
	 * @param string $parameter
	 * @return bool
	 */
	public function setsCriteriaFound( $parameter ) {
		if ( array_key_exists( $parameter, $this->data ) ) {
			if ( array_key_exists( 'set_criteria_found', $this->data[$parameter] ) ) {
				return (bool)$this->data[$parameter]['set_criteria_found'];
			}

			return false;
		}

		throw new MWException( __METHOD__ . ": Attempted to load a parameter that does not exist." );
	}

	/**
	 * Does the parameter cause an open reference conflict?
	 *
	 * @param string $parameter
	 * @return bool
	 */
	public function isOpenReferenceConflict( $parameter ) {
		if ( array_key_exists( $parameter, $this->data ) ) {
			if ( array_key_exists( 'open_ref_conflict', $this->data[$parameter] ) ) {
				return (bool)$this->data[$parameter]['open_ref_conflict'];
			}

			return false;
		}

		throw new MWException( __METHOD__ . ": Attempted to load a parameter that does not exist." );
	}

	/**
	 * Should this parameter preserve the case of the user supplied input?
	 *
	 * @param string $parameter
	 * @return bool
	 */
	public function shouldPreserveCase( $parameter ) {
		if ( array_key_exists( $parameter, $this->data ) ) {
			if ( array_key_exists( 'preserve_case', $this->data[$parameter] ) ) {
				return (bool)$this->data[$parameter]['preserve_case'];
			}

			return false;
		}

		throw new MWException( __METHOD__ . ": Attempted to load a parameter that does not exist." );
	}

	/**
	 * Does this parameter take a list of page names?
	 *
	 * @param string $parameter
	 * @return bool
	 */
	public function isPageNameList( $parameter ) {
		if ( array_key_exists( $parameter, $this->data ) ) {
			if ( array_key_exists( 'page_name_list', $this->data[$parameter] ) ) {
				return (bool)$this->data[$parameter]['page_name_list'];
			}

			return false;
		}

		throw new MWException( __METHOD__ . ": Attempted to load a parameter that does not exist." );
	}

	/**
	 * Is the parameter supposed to be parsed as a boolean?
	 *
	 * @param string $parameter
	 * @return bool
	 */
	public function isBoolean( $parameter ) {
		if ( array_key_exists( $parameter, $this->data ) ) {
			if ( array_key_exists( 'boolean', $this->data[$parameter] ) ) {
				return (bool)$this->data[$parameter]['boolean'];
			}

			return false;
		}

		throw new MWException( __METHOD__ . ": Attempted to load a parameter that does not exist." );
	}

	/**
	 * Is the parameter supposed to be parsed as a Mediawiki timestamp?
	 *
	 * @param string $parameter
	 * @return bool
	 */
	public function isTimestamp( $parameter ) {
		if ( array_key_exists( $parameter, $this->data ) ) {
			if ( array_key_exists( 'timestamp', $this->data[$parameter] ) ) {
				return (bool)$this->data[$parameter]['timestamp'];
			}

			return false;
		}

		throw new MWException( __METHOD__ . ": Attempted to load a parameter that does not exist." );
	}
}
