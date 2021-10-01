# Version 0.9.1
* Problem with adduser solved

# Version 0.9.2
* Problem with headlines in headingmode corrected
* Addcategories: bug fixed
* CATLIST variable defined

# Version 0.9.3
* Allow ¦ as an alias for |
* Escapelinks= introduced

# Version 0.9.4
* Allow "-" with categories =
* Disable UTF8 conversion for sortkey
* Headingcount= added

# Version 0.9.5
* "offset=" added (basic mechanism for scrolling through result lists)

# Version 0.9.6
* When including templates (includepage={xx}yy) spaces between {{ and the template name now will be accepted
* Syntax and semantics of secseparators changed
* Multiple template includes allowed (multisecseparators)
* Multiple chapter inclusions of the same heading allowed (multisecseparators)
* Single # includes text up to the first heading
* Userdateformat introduced
* Changed call-time reference passing to avoid warn message
* TITLE var added

# Version 0.9.7
* Bug corrected with transclusion of labeled sections
* Addfirstcategory works with more than one category selected (risking to produce ambiguous results)

# Version 0.9.8
* Fixed problem with section inclusion (multipl einclusion of same page did not work wit user tag variant
* NOTOC and NOEDITSECTION are automatically placed before mode=category
* PAGE and TITLE variables passed to templates
* Linksto, uses, titlematch and their not-equivalents now understand a set arguments which form an OR-group

# Version 0.9.9
* Default template inclusion added
* Rowcolformat added
* Multicol tag understands now %PAGE% and other parameters

# Version 1.0.0
* Lastrevisionbefore added
* Allrevisionsbefore added
* Firstrevisionsince added
* Allrevisionssince  added
* Dominantsection added
* Replaceintitle added

# Version 1.0.1
* Include as an alias for pageinclude
* Title= introduced

# Version 1.0.2
* Categorymatch  and notcategorymatch  introduced
* Categoryregexp and notcategoryregexp introduced
* Titleregexp and nottitleregexp introduced

# Version 1.0.3
* Behaviour of categoryregexp slightly changed

# Version 1.0.4
* Added linksfrom

# Version 1.0.5
* Added createdby, notcreatedby, modifiedby, notmodifiedby, lastmodifiedby, notlastmodifiedby

# Version 1.0.6
* Allow selection criteria based on included contents

# Version 1.0.7
* Some improvements of includematch (regarding multiple occurencies of the same section)

# Version 1.0.8
* Added notlinksfrom
* Solved problem with invalid arguments at linksto, linksfrom etc.
* Includematch now tests template INPUT against the regexp
* Replaceintitle now also works in standard mode

# Version 1.0.9
* Added openreferences

# Version 1.1.0
* Changed parser cache disabling

# Version 1.1.1
* Experimental support for simple category hierarchies

# Version 1.1.2
* Allow to include sections by number

# Version 1.1.3
* Bug fix for 1.1.2 (pass by reference warning)

# Version 1.1.4
* Technical improvement, more flexible argument parsing at DynamicPageList4()
* Easy access at include for one single template parameter
* Activation of first version of special page (require once)
* Allow comment syntax with #
* Date parameters now accept separation characters

# Version 1.1.5
* Allow cache control via new parameter

# Version 1.1.6
* Bug fix for template inclusion

# Version 1.1.7
* Removed path from require_once for special page php source

# Version 1.1.8
* Addauthor, addlasteditor, goal=categories

# Version 1.1.9
* Ordermethod=titlewithoutnamespace

# Version 1.2.0
* Replaced " by ' in SQL statements

# Version 1.2.1
* Added missing $dbr->addQuotes() on SQL arguments
* Titlemaxlength now also works with mode=userformat

# Version 1.2.2
* Added variable CATNAMES (i.e. category list without links)
* Changed code to allow multiple selection conditions on revisions

# Version 1.2.3
* Accept %0 for transclusion of text before the first chapter
* Added experimental feature for graph generation

# Version 1.2.4
* Error corrected: ordermethod "sortkey" did not work because of missing break in case statement
* Removed experimental feature for graph generation
* Repair error with wrong counting of selected articles

# Version 1.2.5
* Added includenotmatch

# Version 1.2.6
* Added 'distinct' option
* Added '%PAGESEL%' variable
* Linksto, linksfrom etc. no longer complain about empty parameters
* Changed SQL query basics to allow duplicate use of page table;
* Linksto and linksfrom may cause SQL syntax trouble if something was missed

# Version 1.2.7
* Bugfix with %PAGES% and multicolumn output
* Bugfix with undefined variable sPageTable near #2257

# Version 1.2.8
* Syntax - allow 'format' as an alias for 'listseparators'
* Syntax - if 'format' or 'listseparators' is set, 'mode=userformat' will be automatically assumed
* Internal - empty parameters are silently ignored

# Version 1.2.9
* Resultsfooter
* \n and Para will be replaced by linefeed in resultsheader and -footer
* Parameter recognition in 'include={template}:nameOrNumber' improved; nested template calls are now handled correctly

# Version 1.3.0
* Accept 'yes' and 'no' as synonyms for 'true' and 'false' at all boolean parameters

# Version 1.3.1
* Minor modification: resultsheader and resultsfooter do no longer automatically write a newline

# Version 1.3.2
* The warning caused by missing selection criteria will now only be issued if no DEBUG level was set
* %NAMESPACE% added
* Headingmode now works with multiple columns (space for 1 heading == 2 entries)
* Bugfix: parameter syntax errors were not shown in some cases
* New parameter: reset (clears references of a DPL page to templates, images, categories, other pages
* To be used with care as ALL links are cleared, regardless where they come from
* Bugfix: ambiguous 'page_name' in SQL statement fixed (appeared when namespace= and linksfrom= were used together)
* Modification: includematch: uses always preg instead of ereg - patterns must have delimiters! Before #patterns
* Had been matched using ereg
* ?? includematch should be checked to be a valid preg_match argument
* Added oneresultheader

# Version 1.3.3
* Bugfix: parameter checking fixed at 'ordermethod'; multiple parameters were not checked correctly

# Version 1.3.4
* Column size calculation changed at multi column output
* Ambiguity of page_id at linksfrom+...(e.g. uses) eliminated.
* Subcategory expansion: replace ' ' by '_' in query

# Version 1.3.5
* Bug at ordermethod=category,sortkey resolved

# Version 1.3.6
* Special page for DPL deleted
* Allow individual collations for sorting, this makes case insensitive sorting possible
* Hardwired collation change: for sorting the club suit symbol's sort value is changed
* So that the club suit will always appear AFTER the diamond suit
* Bugfix: %PAGES% did not work in mode=category
* Added a switch to include/exclude subpages

# Version 1.3.7
* Allow 0 and 1 for boolean parameters, and on / off
* Bugfix: in release 1.3.6 using odermethod=sortkey led to a SQL syntax error

# Version 1.3.8
* Bugfix at template parameter etxraction: balance of square brackets is now checked when extracting a single parameter

# Version 1.3.9
* Added pagesel as sortkey in ordermethod
* Added noresultsfooter, oneresultfooter
* Added 'table' parameter -- needs a {xyz}.dpl construct as first include parameter

# Version 1.4.0
* Added option 'strict' to 'distinct'

# Version 1.4.1
* Minor bugfix at option 'strict' of 'distinct'
* Behaviour of DEBUG changed

# Version 1.4.2
* Bug fix SQL error in 'group by' clause (with table prefix)
* Bugfix: ordermethod sortkey now implies ordermethod category
* Bugfix: SQL error in some constellations using addpagecounter, addpagesize or add...date
* Allow multiple parameters of a template to be returned directly as table columns
* Design change: reset is handled differently now; no need for a separate DPL statement
* New parameter 'eliminate'
* Debug=5 added
* Added 'tablerow'
* Added 'ignorecase' (for (not)linksto, (not)uses, (not)titlematch, (not)titleregexp, title,

# Version 1.4.3
* Allow regular expression for heading match at include

# Version 1.4.4
* Bugfix: handling of numeric template parameters

# Version 1.4.5
* Bugfix: make Call extension aware of browser differences in session variable handling

# Version 1.4.6
* Added: recent contributions per page/user

# Version 1.4.7
* Added: skipthispage

# Version 1.4.8
* Nothing changed in DPL, but there were changes in Call and Wgraph

# Version 1.4.9
* Improved error handling: parameters without "=" were silently ignored and now raise a warning
* Parameters starting with '=' lead to a runtime error and now are caught

# Version 1.5.0
* Changed algorithm of parameter recognition in the Call extension (nothing changed in DPL)

# Version 1.5.1
* Bugfix at addcontributions:; table name prefix led to invalid SQL statement
* Check for 0 results after titlematch was applied

# Version 1.5.2
* Includematch now understands parameter limits like {abc}:x[10]:y[20]
* Bug fix in parameter limits (limit of 1 led to 2 characters being shown)
* Offset and count are now implemented directly in SQL

# Version 1.5.3
* When using title= together with include=* there was a false warning about empty result set
* New parser function {{#dplchapter:text|heading|limit|page|linktext}}
* Articlecategory added
* Added provision fpr pre and nowiki in wiki text truncation fuction
* Support %DATE% and %USER% within phantom templates
* Added randomseed

# Version 1.6.0
* Internal changes in the code; (no more globals etc ...)

# Version 1.6.1
* Ordermethod= sortkey & categories decoupled, see line 2011
* Hooks changed back to global functions due to problems with older MW installations
* Escaping of "/" improved. In some cases a slash in a page name or in a template parameter could lead to php errors at INCLUDE

# Version 1.6.2
* Template matching in include improved. "abc" must not match "abc def" but did so previously.

# Version 1.6.3
* Changed section matching to allow wildcards.

# Version 1.6.4
* Syntax error fixed (self::$createdLinks must not be unset as it is static, near line 3020)
* Dplmatrix added

# Version 1.6.5
* Added include(not)matchparsed
* Bug fix missing array key , line 2248
* Bug fix in DPLInclude (call time reference in extractHeadings)
* Added %VERSION%

# Version 1.6.6
* SQL escaping (protection against injection) added at "revisions"
* %TOTALPAGES% added

# Version 1.6.7
* Bugfix at goal=categories (due to change in 1.6.6)

# Version 1.6.8
* Allow & at category

# Version 1.6.9
* Added check against non-includable namespaces
* Added includetrim' command

# Version 1.7.0
* Bug fix at articlecategory (underscore)
* Bug fix in installation checking (#2128)
* New command 'imageused'

# Version 1.7.1
* Allow % within included template parameters

# Version 1.7.2
* Experimental sorting of result tables (tablesortcol)

# Version 1.7.3
* %SECTION% can now be used within multiseseparators
* Preliminary patch for MW 1.12 (recursive template expansion)

# Version 1.7.4
* New command: imagecontainer

# Version 1.7.5
* Suppresserrors
* Changed UPPER to LOWER in all SQL statements which ignore case
* Added updaterules feature
* Includematch now also works with include=*; note that it always tries to match the raw text, including template parameters
* Allowcachedresults accepts now 'yes+warn'
* Usedby
* CATBULLETS variable

# Version 1.7.6
* Error correction: non existing array index 0 when trying to includematch content in a non-existing chapter (near #3887)

# Version 1.7.7
* Configuration switch allows to run DPL from protected pages only (Options::$options['RunFromProtectedPagesOnly'])

# Version 1.7.8
* Allow html/wiki comments within template parameter assignments (include statement, line 540ff of DynamicPageListInclude.php)
* Accept include=* together with table=
* Bugfix: %PAGES% was wrong (showing total pages in some cases
* Bugfix: labeled section inclusion did not work because content was automatically truncated to a length of zero
* Added minrevisions & maxrevisions

# Version 1.7.9
* Bugfix in errorhandling: parameter substitution within error message did not work.
* Bugfix in ordermethod=lastedit, firstedit -- led to the effect that too few pages/revisions were shown
* New feature: dplcache
* Bugfix: with include=* a php warning could arise (Call-time pass-by-reference has been deprecated ..)
* New variable %IMAGE% contains image path
* New variable: %PAGEID%
* DPL command line argument: DPL_offset

# Version 1.8.0
* Execution time logging
* Added downward compatibility with Extension:Intersection:
* Accept "dynamicpagelist" as tag and parser function
* New command: showcurid
* Debug=6 added
* Source code split into several files
* Auto-create Template:Extension DPL
* Changed "isChildObj" to "isLocalObj" near line 1160 (see bugreport 'Call to a memeber function getPrefixedKey() on a non-object')
* Removal of html-comments within template calls (DPLInclude)
* Reset/eliminate = none eingeführt
* DPL_count, DPL_offset, DPL_refresh eingeführt
* New feature: execandexit

# Version 1.8.1
* Bugfix: %DATE% was not expanded when addedit=true and ordermethod=lastedit were chosen
* Bugfix: allrevisionssince delivered wrong results

# Version 1.8.2
* Bugfix: ordermethod=lastedit AND minoredits=exclude produced a SQL error

* Bugfix dplcache
* Config switch: respectParserCache
* Date timestamp adapt to user preferences

# Version 1.8.3
* Bugfix: URL variable expansion

# Version 1.8.4
* Bugfix: title= & allrevisionssince caused SQL error
* Added ordermethod = none
* Changed %DPLTIME% to fractions of seconds
* Titlematch: We now translate a space to an escaped underscore as the native underscore is a special char within SQL LIKE
* New commands: linkstoexternal and addexternallink
* Changed default for userdateformat to show also seconds DPL only; Intersection will show only the date for compatibility reasons)
* Bugfix date/time problem 1977
* Time conditions in query are now also translated according to timezone of server/client

# Version 1.8.5
* Changed the php source files to UTF8 encoding (i18n was already utf8)
* Removed all closing ?> php tags at source file end
* Added 'path' and changed href to "third-party" in the hook-registration
* Added a space after showing the date in addeditdate etc.
* Changed implementation of userdate transformation to wgLang->userAdjust()
* Include now understands parserFunctions when used with {#xxx}
* Include now understands tag functions when used with {~xxx}
* Title< and title> added,
* New URL arg: DPL_fromTitle, DPL_toTitle
* New built-in vars: %FIRSTTITLE%, %LASTTITLE%, %FIRSTNAMESPACE%, %LASTNAMESPACE%, %SCROLLDIR% (only in header and footer)
* Removed replacement of card suit symbols in SQL query due to collation incompatibilities
* Added special logic to DPL_fromTitle: reversed sort order for backward scrolling
* Changed default sort in DPL to 'titlewithoutnamespace (as this is more efficient than 'title')

# Version 1.8.6
* Bugfix at ordermethod = titlewithoutnamespace (led to invalid SQL statements)

# Version 1.8.7
* Experimental calls to the CacheAPI; can be switched off by $useCacheAPI = false;
* One can set option[eliminate] to 'all' in LocalSettings now as a default
* Editrulesnow takes several triples of 'parameter', 'value' and 'afterparm'
* Editrules can now produce a screen form to change template values
* Title< and title> now test for greater or less; if you want greater/equal the argument must start with "= "
* The majority of the php modules are now only loaded if a page contains a DPL statement
* Added %DPL_findTitle%
* First letter changed toUpper in %DPL_fromTitle%, %DPL_toTitle%, %DPL_findTitle%,
* Enhanced syntax for include : [limit text~skipPattern]
* UNIQ-QINU Bug resolved
* Convert spaces to underscores in all category (regexp) statements
* We convert html entities in the category command to avoid false interpretation of & as AND

# Version 1.8.8
* Offset by one error in updaterules corrected
* Bugfix in checking includematch on chapter content
* Made size of edit fields depend on value size
* Deleterules: does some kind of permission checking now
* Various improvements in template editing (calling the edit page now for the real update)
* Call to parser->clearState() inserted; does this solve the UNIQ-QINU problem without a patch to LnkHolderArray ??

# Version 1.8.9
* Further improvements of updaterules
* Include: _ in template names are now treated like spaces
* Providing URL-args as variables execandexit = geturlargs
* New command scroll = yes/no
* If %TOTALPAGES% is not used, the number of total hits will not be calculated in SQL
* When searching for a template call the localized word for "Template:" may preceed the template´s name
* Categories= : empty argument is now ignored; use _none_ to list articles with NO category assignment
* Include: we use :and whitespace for separation of field names
* {{{%CATLIST%}}} is now available in phantom templates
* %IMAGE% is now translated to the image plus hashpath if used within a tablerow statement
* The function which truncates wiki text was improved (logic to check balance of tags)
* Setting execandexit to true will prevent the parser cache from being disabled by successive settings of allowcachedresults
* Bug fix: replacing %SECTION% in an include link text did not work wit hregukar expressions as section titles
* Command fixcategory added
* Adding a way to define an alternate namespace for surrogate templates {ns::xyz}.abc
* Accept @ as a synonym for # in the include statement, @@ will match regular expressions
* Syntax changed in include: regexp~ must precede all other information, allow multiple regexps :[regex1~regex2~regex3~number linkText]
* Allow %CATLIST% in tablerow
* Allow '-' as a dummy parameter in include
* Allow alternate syntax for surrogate template {tpl|surrogate} in include
* Multiple linksto statements are now AND-wired (%PAGESEL%) refers to the FIRST statement only
* Multiple linkstoexternal statements are now AND-wired
* New parser function #dplnum
* Allow like expressions in LINKSTO (depending on % in target name)
* Prevent %xx from being misinterpreted as a hex code when used in linksto (e.g. %2c)
* Added hiddencategories = yes / no / only [dead code - not yet WORKING !]
* Added %EDITSUMMARY%

# Version 1.9.0
* Added dplvar
* Added dplreplace
* Changed DLPLogger, getting rid of deprecated methods like addMessage()
* Minor bugfix in include {tpl¦phantom tpl} , problem with different namespaces
* #dplvar accepts all variables from the URL

# Version 1.9.1
* Ordermethod=titlewithoutnamespace now creates capitals in mode=categories according to page title
* Bug fix in namespace= , invalid values now lead to an error message (had been silently translated to the main namespace before)
* Category mode: first char bugfix

# Version 2.0.0
* Added %ARGS% to template surrogate call
* Replaced "makeKnownLinkObjects" by "fullurl:" to get rid of the need to change $rawHtml
* Eliminated rawHTML usage
* Eliminated calls to parser->clearState and parser->transformMsg, now CITE and DPL work together
* As a consequence a patch to LinkHolderArray.php is needed.
* Added proprietory function to sort bidding sequences for the card game of 'Bridge' according to suit rank
* Added workaround to eliminate problems with cite extension (DPL calls parser clearState which erases references)
* Added a CAST to CHAR at all LOWER statemens in SQL (MediaWiki changed to varbinary types since 1.17)

# Version 2.01
* Re-merged all changes from SVN since DPL 1.8.6

# Version 3.0.0
* THE MOTHER OF ALL OVERHAULS! - Seriously, the entire code base was ripped to shreds and redone to be easily worked on in the future.
* Configuration is now standardized instead of calling into static class functions or modifying objects directly.
* Fixed several SQL injection exploits with 'ordercollation' and 'category'.
* Cache now works with the built Mediawiki Parser cache.  The built in DPL cache was fundamentally broken.
* Parameter 'dplcache' was removed as it is now obselete.  Please use 'allowcachedresults' to control caching.
* The 'allowcachedresults' parameter default was changed to true.
* The 'dplcacheperiod' parameter was renamed to 'cacheperiod'.
* The 'cacheperiod' parameter default was changed to 3600 seconds.(One Hour)
* URL argument 'DPL_refresh' was removed.  To purge the Parser cache perform a null edit on the page or place 'action=purge' as part of the URL.
* Configuration value 'respectParserCache' was removed.
* The card suit sort function no longer has a massive memory leak.

# Version 3.0.1
Many thanks to GreenReaper on GitHub for reporting and finding issues with core functionality that previously went unreported.

* Code quality improvements.  Various changes to squash E_NOTICE errors helped find unnoticed issues.
* The "headingmode" functionality was not previously repaired from the code rework.  It is now fixed.
* Removed an unused $logger in the DynamicPageList class.
* Category depth recursion was broken.  #41
* New timestamp handling functionality caused unexpected behavior.  It has been reverted to work the same as the previous implementation.  #40
* Parse time profiling was broken.
* Scroll direction parameter was broken.
* Early parsing was accidentally clearing minor warnings and errors preventing them from being seen.
* Category links relied on Mediawiki functionality that ended up causing double parsing and potentially broken links on some wiki setups.  This is now handled all in the extension to reduce the number of parses and ensure links are valid.  #38
* Article processing would clear the number of query results causing an issue in which blank output would be produced when all articles were excluded from the output.  This has been fixed to display a warning for no results.
* Databases that use table prefixes were broken due to a few bugged selects.  #37  Thanks to @nsradke for reporting this issue.

# Version 3.0.2
* Fixed issues with usedby parameter causing fatal errors.
* Fixed issue where the allrevisionssince would display a blank [[User:|]] link despite not being specified to show user information.
* The addpagecounter parameter now requires Extension:HitCounter which itself requires MediaWiki 1.25.  All other features and parameters will continue to work on MediaWiki 1.23+.
  * https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/44
* Preliminary support for Mediawiki 1.25+ wfLoadExtension() style extension loading.  Current implementation is broken due to extension design issues that need fixed.
* Fix an issue with an array not being initialized in DPL's list formatter.
  * https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/43

# Version 3.0.3
* Fixed default sorting and title sort for ordermethod parameter.
* Fixed logical AND and OR issues with category, categorymatch, and categoryregexp parameters.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/47
* Fixed an additional issue with the new Extension:HitCounter support.  Using ordermethod=counter without specifying the addpagecounter parameter would cause a database error.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/44#issuecomment-129951800

# Version 3.0.4
* Added functionality to enable recursive tag parsing when using the <dpl> parser tag.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/49
* The %VERSION% variable was not available to noresultsheader and noresultsfooter parameters.
* Fixed and revamp how headers/footers are handled to be more consistent.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/48
* Fixed a fundamental flaw in how parameters are tested.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/48
* Fixed how headers/footers are checked for being empty.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/48
* Mediawiki 1.24+ compatibility changes for extension registration and loading.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/46
	* $dplSettings configuration variable has been renamed to $wgDplSettings to facilitate this change.  Existing configurations will need to be updated.

# Version 3.0.5
* Fixed an issue with detecting how many categories were added to a query.
	* https://github.com/Alexia/DynamicPageList/commit/30c7cbfb7bc1c82f5c53f405bdf269bfe1fd62d3
* Reverted to the old hard coded method of which categorylinks table to use for the addfirstcategorydate parameter.  This resolves issues variable parameters used in conjunction with it.
	* https://github.com/Alexia/DynamicPageList/commit/30c7cbfb7bc1c82f5c53f405bdf269bfe1fd62d3
* Fixed and improved the functionality of statements that created "NOT IN" or "!=" SQL where conditions.
	* https://github.com/Alexia/DynamicPageList/commit/df82d75bf9fd81968ef77729b1542ff32f4852b5
* Fixed the reset parameter being broken due to a typographical error.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/52

# Version 3.0.6
* Fixed a regression with a previous fix related to category counting.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/53

# Version 3.0.7
* Using ordermethod=firstedit with certain other parameters would cause SQL generation errors.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/57
* Fixed implementation of CategoryViewer.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/55
* Fixed an issue that was causing Article class taint across separate tag parses.
	* https://github.com/Alexia/DynamicPageList/commit/74f99a3c2bbdeb5a076705369fc6d3df17e40810
* In relation to the taint issue an error check was put in place to prevent invalid articles from passing through.
	* https://github.com/Alexia/DynamicPageList/commit/5fc841f6bdae4ea8c63bbb3334125120889970bf

# Version 3.0.8
* Changed instances of $dplSettings in language strings to $wgDplSettings.
* Moved the DPL template installation to the database updater.  Previously this was done on every page load and would also interfere with Special:Import.
* Fixed end resets and eliminates being flipped for what context(function or tag) they should run.
* DPL will no longer handle the raw <section> tag outside of the DPL context by default.  It will still handle <section> tags found inside a DPL context.
	* New setting: $wgDplSettings['handleSectionTag'], default of false.  Set this to true for the old broken behavior.

# Version 3.0.9
* Fixed interaction with Extension:HitCounters that was causing a fatal exception.
* Fixed language string used to display numbers from Extension:HitCounters.
* Added support for mode=gallery.
* Fixed ordermethod=category where it no longer times out.
* Fixed minrevisions tag causing SQL error.
* Fixed ordermethod=firstedit. It now sorts in the correct direction.

# Version 3.0.10
* Really fixed ordermethod=firstedit this time thanks to cillianderoiste.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/13
* Fixed a warning that could occur in uncommon cases.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/11
* Fixed and improved parameters that take regular expressions.
	* Regular expressions are now validated to prevent warnings from being tossed and DPL will now provide a generic error that the parameter was bad.
* Fixed various uses of deprecated language function calls.

# Version 3.0.11
* Fixed a bug introduced in 3.0.10 related to database regular expressions.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/15
* Fixed empty category headings throwing warnings.
* Fixed the #dplmatrix parser function throwing errors if the sixth parameter was omitted.

# Version 3.0.12
* Fixed SQL debug output being broken.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/19
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/21
* Fixed E_NOTICE attempting to access null as an array.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/20
* Fixed #dplreplace throwing errors if parameters were not passed.

# Version 3.1.0
* Update to WikiPage and ContenHandler classes for article modifications.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/26
* Swap out deprecated `NS_IMAGE` define for `NS_FILE`.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/27
* Fix issue with an issue with a parameter not always being an array passed into array merge. - Thanks to plegault3397 and Developaws.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/24
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/28
* Improved performance of lastrevisionbefore/firstrevisionsince for small result sets. - Thanks to Cotto.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/29
* Fixed issue with ORDER BY sort direction not applying to all columns and only the last column. - Thanks to Cotto.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/30
* Ability to user relative timestamps for limiting query results.  Best used with allowcachedresults=false. - Thanks to Cotto.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/31

# Version 3.1.1
* Issues with legacy Extension:Cite conflicts have been resolved in the Cite extension and this in turn breaks DynamicPageList for MediaWiki 1.29.  FoXFTW removed the now unnecessary code.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/42
* Now requires a wfLoadExtension() entry point.

# Version 3.1.2
* Fixed issues with ordermethod conflicting with the title parameter and creating artificially limited output.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/32
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/44
* Fixed an E_NOTICE for the addauthor parameter.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/45
* Fixed sortByPriority not applying correctly in some cases.

# Version 3.1.3
* The dpl_clview view will just be created on database update instead of complaining on wiki text parsing that the administrator did not do it manually.

# Version 3.2.0
* Various fixes and updates for PHP 7.2 support.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/55
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/59
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/60
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/61
* Remove wfProfileIn() and wfProfileOut() that were removed from MediaWiki 1.31.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/57
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/58
* Use LoggedUpdateMaintenance to create Template
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/56
* Remove deprecated legacy extension entry point.

# Version 3.2.1
* Eliminate parameter was broken.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/64
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues/65
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/66
* Fix for PHP 7.2 support and a non-countable variable.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/62

# Version 3.2.2
* Minor bug fix for the category parameter to stop producing a warning.

# Version 3.3.0
* Overhaul and rewrite of the list generation class(DynamicPageList).
	* Two new class bases, Lister and Heading.
* New listing mode, subpage.

# Version 3.3.1
* Fixed regression with parameter tablesortcol breaking table output.
* Fixed regression with page tranclusion parsing that was done to improve performance.

# Version 3.3.2
* Fixed UserFormat improperly using sprintf on list start which sometimes would break output.
* Fixed instances of formatItem() declaration being slightly incorrect.
* Fixed $article->mContrib passing a rounded float to function expecting an integer.

# Version 3.3.3
* Thank you to Jack Phoenix and PCJ for numerous fixes in this release.
* Add 'dpl' query group to database replication connections to allow them to be grouped to specific servers.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/92
* Fixed undefined array offsets in Article class.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/91
* Add missing user rights descriptions in language files.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/90
* Fixed the Lister::formatItem function using an undeclared object.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/89
* The maxrevisions parameter was broken entirely at the query generation step.
	* https://gitlab.com/hydrawiki/extensions/DynamicPageList/merge_requests/88

# Version 3.3.4
* Initial fork release
* 1.35/actor support
* numorous bug fixes
* phpcs cleanup

# Version 3.3.5
* Fix syntax error in extension.json
* 1.36.0 support

# Version 3.3.6
* Major code cleanup
* Numerous bug fixes
* Now requires MediaWiki 1.36.0 or later
* 1.37.0 support
