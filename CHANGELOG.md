#Version 0.9.1
*problem with adduser solved
#Version 0.9.2
*problem with headlines in headingmode corrected
*addcategories: bug fixed
*CATLIST variable defined
#Version 0.9.3
*allow ¦ as an alias for |
*escapelinks= introduced
#Version 0.9.4
*allow "-" with categories = 
*disable UTF8 conversion for sortkey
*headingcount= added
#Version 0.9.5
*"offset=" added (basic mechanism for scrolling through result lists)
#Version 0.9.6
*when including templates (includepage={xx}yy) spaces between {{ and the template name now will be accepted
*syntax and semantics of secseparators changed
*multiple template includes allowed (multisecseparators)
*multiple chapter inclusions of the same heading allowed (multisecseparators)
*single # includes text up to the first heading
*userdateformat introduced
*changed call-time reference passing to avoid warn message
*TITLE var added
#Version 0.9.7
*bug corrected with transclusion of labeled sections
*addfirstcategory works with more than one category selected (risking to produce ambiguous results)
#Version 0.9.8
*fixed problem with section inclusion (multipl einclusion of same page did not work wit user tag variant
*NOTOC and NOEDITSECTION are automatically placed before mode=category
*PAGE and TITLE variables passed to templates
*linksto, uses, titlematch and their not-equivalents now understand a set arguments which form an OR-group
#Version 0.9.9
*default template inclusion added
*rowcolformat added
*multicol tag understands now %PAGE% and other parameters
#Version 1.0.0
*lastrevisionbefore added
*allrevisionsbefore added
*firstrevisionsince added
*allrevisionssince  added
*dominantsection added
*replaceintitle added
#Version 1.0.1
*include as an alias for pageinclude
*title= introduced
#Version 1.0.2
*categorymatch  and notcategorymatch  introduced
*categoryregexp and notcategoryregexp introduced
*titleregexp and nottitleregexp introduced
#Version 1.0.3
*behaviour of categoryregexp slightly changed
#Version 1.0.4
*added linksfrom
#Version 1.0.5
*added createdby, notcreatedby, modifiedby, notmodifiedby, lastmodifiedby, notlastmodifiedby
#Version 1.0.6
*allow selection criteria based on included contents
#Version 1.0.7
*some improvements of includematch (regarding multiple occurencies of the same section)
#Version 1.0.8
*added notlinksfrom
*solved problem with invalid arguments at linksto, linksfrom etc.
*includematch now tests template INPUT against the regexp
*replaceintitle now also works in standard mode
#Version 1.0.9
*added openreferences
#Version 1.1.0
*changed parser cache disabling
#Version 1.1.1
*experimental support for simple category hierarchies
#Version 1.1.2
*allow to include sections by number
#Version 1.1.3
*bug fix for 1.1.2 (pass by reference warning)
#Version 1.1.4
*technical improvement, more flexible argument parsing at DynamicPageList4()
*easy access at include for one single template parameter
*activation of first version of special page (require once)
*allow comment syntax with #
*date parameters now accept separation characters
#Version 1.1.5
*allow cache control via new parameter
#Version 1.1.6
*bug fix for template inclusion
#Version 1.1.7
*removed path from require_once for special page php source
#Version 1.1.8
*addauthor, addlasteditor, goal=categories
#Version 1.1.9
*ordermethod=titlewithoutnamespace
#Version 1.2.0
*replaced " by ' in SQL statements
#Version 1.2.1
*added missing $dbr->addQuotes() on SQL arguments
*titlemaxlength now also works with mode=userformat
#Version 1.2.2
*added variable CATNAMES (i.e. category list without links)
*changed code to allow multiple selection conditions on revisions
#Version 1.2.3
*accept %0 for transclusion of text before the first chapter
*added experimental feature for graph generation
#Version 1.2.4
*error corrected: ordermethod "sortkey" did not work because of missing break in case statement
*removed experimental feature for graph generation
*repair error with wrong counting of selected articles
#Version 1.2.5
*added includenotmatch
#Version 1.2.6
*added 'distinct' option
*added '%PAGESEL%' variable
*linksto, linksfrom etc. no longer complain about empty parameters
*changed SQL query basics to allow duplicate use of page table;
*linksto and linksfrom may cause SQL syntax trouble if something was missed
#Version 1.2.7
*bugfix with %PAGES% and multicolumn output
*bugfix with undefined variable sPageTable near #2257
#Version 1.2.8
*syntax - allow 'format' as an alias for 'listseparators'
*syntax - if 'format' or 'listseparators' is set, 'mode=userformat' will be automatically assumed
*internal - empty parameters are silently ignored
#Version 1.2.9
*resultsfooter
*\n and Para will be replaced by linefeed in resultsheader and -footer
*parameter recognition in 'include={template}:nameOrNumber' improved; nested template calls are now handled correctly
#Version 1.3.0
*accept 'yes' and 'no' as synonyms for 'true' and 'false' at all boolean parameters
#Version 1.3.1
*minor modification: resultsheader and resultsfooter do no longer automatically write a newline
#Version 1.3.2
*the warning caused by missing selection criteria will now only be issued if no DEBUG level was set
*%NAMESPACE% added
*headingmode now works with multiple columns (space for 1 heading == 2 entries)
*bugfix: parameter syntax errors were not shown in some cases
*new parameter: reset (clears references of a DPL page to templates, images, categories, other pages
*to be used with care as ALL links are cleared, regardless where they come from
*bugfix: ambiguous 'page_name' in SQL statement fixed (appeared when namespace= and linksfrom= were used together)
*modification: includematch: uses always preg instead of ereg - patterns must have delimiters! Before #patterns
*had been matched using ereg
*?? includematch should be checked to be a valid preg_match argument
*added oneresultheader
#Version 1.3.3
*bugfix: parameter checking fixed at 'ordermethod'; multiple parameters were not checked correctly
#Version 1.3.4
*column size calculation changed at multi column output
*ambiguity of page_id at linksfrom+...(e.g. uses) eliminated.
*subcategory expansion: replace ' ' by '_' in query
#Version 1.3.5
*bug at ordermethod=category,sortkey resolved
#Version 1.3.6
*special page for DPL deleted
*allow individual collations for sorting, this makes case insensitive sorting possible
*hardwired collation change: for sorting the club suit symbol's sort value is changed 
*so that the club suit will always appear AFTER the diamond suit
*bugfix: %PAGES% did not work in mode=category
*added a switch to include/exclude subpages
#Version 1.3.7
*allow 0 and 1 for boolean parameters, and on / off
*bugfix: in release 1.3.6 using odermethod=sortkey led to a SQL syntax error
#Version 1.3.8
*bugfix at template parameter etxraction: balance of square brackets is now checked when extracting a single parameter
#Version 1.3.9
*added pagesel as sortkey in ordermethod
*added noresultsfooter, oneresultfooter
*added 'table' parameter -- needs a {xyz}.dpl construct as first include parameter
#Version 1.4.0
*added option 'strict' to 'distinct'
#Version 1.4.1
*minor bugfix at option 'strict' of 'distinct'
*behaviour of DEBUG changed
#Version 1.4.2
*bug fix SQL error in 'group by' clause (with table prefix)
*bugfix: ordermethod sortkey now implies ordermethod category
*bugfix: SQL error in some constellations using addpagecounter, addpagesize or add...date
*allow multiple parameters of a template to be returned directly as table columns
*design change: reset is handled differently now; no need for a separate DPL statement
*new parameter 'eliminate'
*debug=5 added
*added 'tablerow'
*added 'ignorecase' (for (not)linksto, (not)uses, (not)titlematch, (not)titleregexp, title,
#Version 1.4.3
*allow regular expression for heading match at include
#Version 1.4.4
*bugfix: handling of numeric template parameters
#Version 1.4.5
*bugfix: make Call extension aware of browser differences in session variable handling
#Version 1.4.6
*added: recent contributions per page/user
#Version 1.4.7
*added: skipthispage
#Version 1.4.8
*nothing changed in DPL, but there were changes in Call and Wgraph
#Version 1.4.9
*improved error handling: parameters without "=" were silently ignored and now raise a warning
*parameters starting with '=' lead to a runtime error and now are caught
#Version 1.5.0
*changed algorithm of parameter recognition in the Call extension (nothing changed in DPL)
#Version 1.5.1
*bugfix at addcontributions:; table name prefix led to invalid SQL statement
*check for 0 results after titlematch was applied
#Version 1.5.2
*includematch now understands parameter limits like {abc}:x[10]:y[20]
*bug fix in parameter limits (limit of 1 led to 2 characters being shown)
*offset and count are now implemented directly in SQL
#Version 1.5.3
*when using title= together with include=* there was a false warning about empty result set
*new parser function {{#dplchapter:text|heading|limit|page|linktext}}
*articlecategory added
*added provision fpr pre and nowiki in wiki text truncation fuction
*support %DATE% and %USER% within phantom templates
*added randomseed
#Version 1.6.0
*internal changes in the code; (no more globals etc ...) 
#Version 1.6.1
*ordermethod= sortkey & categories decoupled, see line 2011
*hooks changed back to global functions due to problems with older MW installations
*Escaping of "/" improved. In some cases a slash in a page name or in a template parameter could lead to php errors at INCLUDE
#Version 1.6.2
*Template matching in include improved. "abc" must not match "abc def" but did so previously.
#Version 1.6.3
*Changed section matching to allow wildcards.
#Version 1.6.4
*Syntax error fixed (self::$createdLinks must not be unset as it is static, near line 3020)
*dplmatrix added
#Version 1.6.5
*added include(not)matchparsed
*bug fix missing array key , line 2248
*bug fix in DPLInclude (call time reference in extractHeadings)
*added %VERSION%
#Version 1.6.6
*SQL escaping (protection against injection) added at "revisions"
*%TOTALPAGES% added
#Version 1.6.7
*bugfix at goal=categories (due to change in 1.6.6)
#Version 1.6.8
*allow & at category 
#Version 1.6.9
*added check against non-includable namespaces
*added includetrim' command
#Version 1.7.0
*bug fix at articlecategory (underscore)
*bug fix in installation checking (#2128)
*new command 'imageused'
#Version 1.7.1
*allow % within included template parameters
#Version 1.7.2
*experimental sorting of result tables (tablesortcol)
#Version 1.7.3
*%SECTION% can now be used within multiseseparators
*preliminary patch for MW 1.12 (recursive template expansion)
#Version 1.7.4
*new command: imagecontainer
#Version 1.7.5
*suppresserrors
*changed UPPER to LOWER in all SQL statements which ignore case
*added updaterules feature
*includematch now also works with include=*; note that it always tries to match the raw text, including template parameters
*allowcachedresults accepts now 'yes+warn'
*usedby
*CATBULLETS variable
#Version 1.7.6
*error correction: non existing array index 0 when trying to includematch content in a non-existing chapter (near #3887) 
#Version 1.7.7
*configuration switch allows to run DPL from protected pages only (ExtDynamicPageList::$options['RunFromProtectedPagesOnly'])
#Version 1.7.8
*allow html/wiki comments within template parameter assignments (include statement, line 540ff of DynamicPageListInclude.php)
*accept include=* together with table=
*Bugfix: %PAGES% was wrong (showing total pages in some cases
*Bugfix: labeled section inclusion did not work because content was automatically truncated to a length of zero
*added minrevisions & maxrevisions
#Version 1.7.9
*Bugfix in errorhandling: parameter substitution within error message did not work.
*Bugfix in ordermethod=lastedit, firstedit -- led to the effect that too few pages/revisions were shown
*new feature: dplcache
*bugfix: with include=* a php warning could arise (Call-time pass-by-reference has been deprecated ..)
*new variable %IMAGE% contains image path
*new variable: %PAGEID%
*DPL command line argument: DPL_offset
#Version 1.8.0
*execution time logging
*added downward compatibility with Extension:Intersection:
*accept "dynamicpagelist" as tag and parser function
*new command: showcurid
*debug=6 added
*source code split into several files
*auto-create Template:Extension DPL
*changed "isChildObj" to "isLocalObj" near line 1160 (see bugreport 'Call to a memeber function getPrefixedKey() on a non-object')
*removal of html-comments within template calls (DPLInclude)
*reset/eliminate = none eingeführt
*DPL_count, DPL_offset, DPL_refresh eingeführt
*New feature: execandexit
#Version 1.8.1
*bugfix: %DATE% was not expanded when addedit=true and ordermethod=lastedit were chosen
*bugfix: allrevisionssince delivered wrong results
#Version 1.8.2
*bugfix: ordermethod=lastedit AND minoredits=exclude produced a SQL error

*bugfix dplcache
*config switch: respectParserCache
*date timestamp adapt to user preferences
#Version 1.8.3
*bugfix: URL variable expansion
#Version 1.8.4
*bugfix: title= & allrevisionssince caused SQL error
*added ordermethod = none
*changed %DPLTIME% to fractions of seconds
*titlematch: We now translate a space to an escaped underscore as the native underscore is a special char within SQL LIKE 
*new commands: linkstoexternal and addexternallink
*changed default for userdateformat to show also seconds DPL only; Intersection will show only the date for compatibility reasons)
*bugfix date/time problem 1977
*time conditions in query are now also translated according to timezone of server/client
#Version 1.8.5
*changed the php source files to UTF8 encoding (i18n was already utf8)
*removed all closing ?> php tags at source file end
*added 'path' and changed href to "third-party" in the hook-registration
*added a space after showing the date in addeditdate etc.
*changed implementation of userdate transformation to wgLang->userAdjust()
*include now understands parserFunctions when used with {#xxx}
*include now understands tag functions when used with {~xxx}
*title< and title> added, 
*new URL arg: DPL_fromTitle, DPL_toTitle
*new built-in vars: %FIRSTTITLE%, %LASTTITLE%, %FIRSTNAMESPACE%, %LASTNAMESPACE%, %SCROLLDIR% (only in header and footer)
*removed replacement of card suit symbols in SQL query due to collation incompatibilities
*added special logic to DPL_fromTitle: reversed sort order for backward scrolling
*changed default sort in DPL to 'titlewithoutnamespace (as this is more efficient than 'title')
#Version 1.8.6
*bugfix at ordermethod = titlewithoutnamespace (led to invalid SQL statements)
#Version 1.8.7
*experimental calls to the CacheAPI; can be switched off by $useCacheAPI = false;
*one can set option[eliminate] to 'all' in LocalSettings now as a default
*editrulesnow takes several triples of 'parameter', 'value' and 'afterparm'
*editrules can now produce a screen form to change template values
*title< and title> now test for greater or less; if you want greater/equal the argument must start with "= "
*the majority of the php modules are now only loaded if a page contains a DPL statement
*added %DPL_findTitle%
*first letter changed toUpper in %DPL_fromTitle%, %DPL_toTitle%, %DPL_findTitle%,
*enhanced syntax for include : [limit text~skipPattern]
*UNIQ-QINU Bug resolved
*convert spaces to underscores in all category (regexp) statements
*we convert html entities in the category command to avoid false interpretation of & as AND
#Version 1.8.8
*offset by one error in updaterules corrected
*bugfix in checking includematch on chapter content
*made size of edit fields depend on value size
*deleterules: does some kind of permission checking now
*various improvements in template editing (calling the edit page now for the real update)
*call to parser->clearState() inserted; does this solve the UNIQ-QINU problem without a patch to LnkHolderArray ??
#Version 1.8.9
*further improvements of updaterules
*include: _ in template names are now treated like spaces
*providing URL-args as variables execandexit = geturlargs
*new command scroll = yes/no
*if %TOTALPAGES% is not used, the number of total hits will not be calculated in SQL
*when searching for a template call the localized word for "Template:" may preceed the template´s name
*categories= : empty argument is now ignored; use _none_ to list articles with NO category assignment
*include: we use :and whitespace for separation of field names
*{{{%CATLIST%}}} is now available in phantom templates
*%IMAGE% is now translated to the image plus hashpath if used within a tablerow statement
*The function which truncates wiki text was improved (logic to check balance of tags)
*setting execandexit to true will prevent the parser cache from being disabled by successive settings of allowcachedresults
*bug fix: replacing %SECTION% in an include link text did not work wit hregukar expressions as section titles
*command fixcategory added
*adding a way to define an alternate namespace for surrogate templates {ns::xyz}.abc
*accept @ as a synonym for # in the include statement, @@ will match regular expressions
*syntax changed in include: regexp~ must precede all other information, allow multiple regexps :[regex1~regex2~regex3~number linkText]
*allow %CATLIST% in tablerow
*allow '-' as a dummy parameter in include
*allow alternate syntax for surrogate template {tpl|surrogate} in include
*multiple linksto statements are now AND-wired (%PAGESEL%) refers to the FIRST statement only
*multiple linkstoexternal statements are now AND-wired
*new parser function #dplnum
*allow like expressions in LINKSTO (depending on % in target name)
*prevent %xx from being misinterpreted as a hex code when used in linksto (e.g. %2c)
*Added hiddencategories = yes / no / only [dead code - not yet WORKING !]
*added %EDITSUMMARY%
#Version 1.9.0
*added dplvar
*added dplreplace
*changed DLPLogger, getting rid of deprecated methods like addMessage()
*minor bugfix in include {tpl¦phantom tpl} , problem with different namespaces
*#dplvar accepts all variables from the URL
#Version 1.9.1
*ordermethod=titlewithoutnamespace now creates capitals in mode=categories according to page title
*bug fix in namespace= , invalid values now lead to an error message (had been silently translated to the main namespace before)
*category mode: first char bugfix
#Version 2.0
*added %ARGS% to template surrogate call
*replaced "makeKnownLinkObjects" by "fullurl:" to get rid of the need to change $rawHtml
*eliminated rawHTML usage 
*eliminated calls to parser->clearState and parser->transformMsg, now CITE and DPL work together
*as a consequence a patch to LinkHolderArray.php is needed.
*added proprietory function to sort bidding sequences for the card game of 'Bridge' according to suit rank
*added workaround to eliminate problems with cite extension (DPL calls parser clearState which erases references)
*added a CAST to CHAR at all LOWER statemens in SQL (MediaWiki changed to varbinary types since 1.17)
*#Version 2.01
*re-merged all changes from SVN since DPL 1.8.6