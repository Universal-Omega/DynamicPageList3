# DynamicPageList3

The **DynamicPageList3** extension is a reporting tool for MediaWiki, listing category members and intersections with various formats and details. For full documentation, see the [manual](https://help.gamepedia.com/Extension:DPL3/Manual).

When invoked with a basic set of selection parameters DPL displays a list of pages in one or more categories.  Selections may also be based on factors such as author, namespace, date, name pattern, usage of templates, or references to other articles.  Output takes a variety of forms, some of which incorporate elements of selected articles.

This extension is invoked with the parser function <code>{{#dpl: .... }}</code> or parser tag <code>&lt;DPL&gt;</nowiki></code>.  A [Wikimedia](https://www.mediawiki.org/wiki/Extension:DynamicPageList_(Wikimedia))-compatible implementation of certain features can be invoked with <code>&lt;DynamicPageList&gt;</code>.

Complex look ups can result in computationally expensive database queries.  However, by default all output is cached for a period of one hour to reduce the need to rerun the query every page load.  The [DPL:Parameters: Other Parameters](https://help.gamepedia.com/Extension:DPL3/Parameters:_Other_parameters#cacheperiod) manual page contains information on parameters that can be used to disable the cache and allow instant updates.

* Manual and Complete Documentation: [Documentation at Gamepedia Help Wiki](https://help.gamepedia.com/Extension:DPL3/Manual)
* Source Code: [Source code at Gitlab](https://gitlab.com/hydrawiki/extensions/DynamicPageList)
* Bugs and Feature Requests: [Issues at Gitlab](https://gitlab.com/hydrawiki/extensions/DynamicPageList/issues)
* Licensing: DynamicPageList3 is released under [GNU General Public License, version 3](https://opensource.org/licenses/GPL-3.0).


## Installation
Please see the [releases page](https://gitlab.com/hydrawiki/extensions/DynamicPageList/releases) for the latest releases.

## Configuration
These are DPL's configuration settings and along with their default values.  To change them make sure they are defined before including the extension on the wiki.  More configuration information is available on the **[MediaWiki extension page](https://www.mediawiki.org/wiki/Extension:DynamicPageList3#Configuration)**.

**Note:** In release 3.0.4 the configuration variable name was changed from <code>$dplSettings</code> to <code>$wgDplSettings</code>.  This was to faciliate compatibility with Mediawiki 1.25's extension registration change.

|          Setting                            | Default | Description                                                                                                                                                                                                               |
|:--------------------------------------------|---------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| $wgDplSettings['allowedNamespaces']         | null    | By default all existing namespaces are used when DPL initializes. Customize this setting with an array of namespace constants to restrict DPL to work only in those namespaces.                                           |
| $wgDplSettings['allowUnlimitedCategories']  | false   | Set this to true to ignore 'maxCategoryCount' and allow unlimited categories. Please note that large amounts of categories in a query can slow down or crash servers.                                                     |
| $wgDplSettings['allowUnlimitedResults']     | false   | Set this to true to ignore 'maxResultCount' and allow unlimited results. Please note that large result sets may result in slow or failed page loads.                                                                      |
| $wgDplSettings['behavingLikeIntersection']  | false   | Set DPL to always behave like Extension:Intersection.                                                                                                                                                                     |
| $wgDplSettings['categoryStyleListCutoff']   | 6       | Maximum number of items in a category list before being cut off.                                                                                                                                                          |
| $wgDplSettings['fixedCategories']           | []      | This does something with preventing DPL from "looking" at these categories.                                                                                                                                               |
| $wgDplSettings['functionalRichness']        | 3       | Set the level of parameters available to end users.                                                                                                                                                                       |
| $wgDplSettings['maxCategoryCount']          | 4       | Maximum number of categories to allow in queries.                                                                                                                                                                         |
| $wgDplSettings['minCategoryCount']          | 0       | Minimum number of categories to allow in queries.                                                                                                                                                                         |
| $wgDplSettings['maxResultCount']            | 500     | Maximum number of results to return from a query.                                                                                                                                                                         |
| $wgDplSettings['recursiveTagParse']         | false   | Do recursive tag parsing on <dpl> parser tags converting tags and functions such as magic words like {{PAGENAME}}.  This is similar to the {{#dpl}} parser function call, but may not work exactly the same in all cases. |
| $wgDplSettings['runFromProtectedPagesOnly'] | false   | Set this to true to allow DPL to run from protected pages only. This is recommend if wiki administrators are having issues with malicious users creating computationally intensive queries.                               |
| $wgDplSettings['handleSectionTag']          | false   | Set this to true to have DPL handle <section> tags outside of DPL parser tags.                                                                                                                                            |

The global variable {{manual|$wgNonincludableNamespaces}} is automatically respected by DPL.  It will prevent the contents of the listed namespaces from appearing in DPL's output.

**Note: <code>$wgDplSettings['maxResultCount']</code> is a LIMIT *on the SQL query itself*.  Some DPL query parameters like <code>includematch</code> are applied *after* the SQL query, however, so results here may easily be misleading.**

### Functional Richness

DynamicPageList has many features which are unlocked based on the maximum functional richness level.  There are some that can cause high CPU or database load and should be used sparingly.

* <code>$wgDplSettings['functionalRichness'] = 0</code> is equivalent to Wikimedia's [[Extension:DynamicPageList (Wikimedia)|Wikimedia]]
* <code>$wgDplSettings['functionalRichness'] = 1</code> adds additional formatting parameters
* <code>$wgDplSettings['functionalRichness'] = 2</code> adds performance equivalent features for templates and pagelinks
* <code>$wgDplSettings['functionalRichness'] = 3</code> allows more-expensive page inclusion features and regular expression queries.
* <code>$wgDplSettings['functionalRichness'] = 4</code> permits exotic and potentially dangerous batch update and delete operations; not recommended for public websites.  Includes debugging parameters for testing and development.


## Usage
### Extended DPL Functionality
Extended DPL is invoked by using the parser function <code>{{#dpl: .... }}</code>, or the parser extension tag <code><DPL> .... </DPL></code>.

*See [Manual - **General Usage and Invocation Syntax**](https://help.gamepedia.com/Extension:DPL3/General_usage_and_invocation_syntax) and [DPL:Parameters: **Criteria for Page Selection**](https://help.gamepedia.com/Extension:DPL3/Parameters:_Criteria_for_page_selection)*

### Backwards Compatibility
Functionality compatible with Wikimedia's DPL extension can be invoked with <code>&lt;DynamicPageList&gt; .... &lt;/DynamicPageList&gt;</code>.  Further information can be found on the [Compatibility manual page](https://help.gamepedia.com/Extension:DPL3/Compatibility).

## Usage Philosophy and Overview
With the assumption there are some articles writtne about *countries* those articles will typically have three things in common:
* They will belong to a common category
* They will have a similar chapter structure, i.e. they will contain paragraphs named 'Religion' or 'History'
* They will use a template which is used to present highly structured short data items ('Capital', 'Inhabitants', ..) in a nice way (e.g. as a wikitable)

### Generate a Report Based on **countries**
If there was a need to assemble a report of what countries practice a certain religion this could be easily done with the **category** and **linksto** parameters.
<pre><nowiki>
{{#dpl:
category=countries
|linksto=Pastafarianism
}}
</nowiki></pre>

With DPL one could:
* Generate a list of all those articles (or a random sample)
* Show metadata of the articles (popularity, date of last update, ..)
* Show one or more chapters of the articles ('transclude' content)
* Show parameter values which are passed to the common template
* Order articles appropriately
* Present the result in a sortable table (e.g.)
* Generate multiple column output

### Which steps are necessary?
**Find the articles you want to list:**
* Select by a logical combination (AND,OR,NOT) of categories
* Specify a range for the number of categories the article must be assigned to
* Select by a logical combination (AND,OR,NOT) of namespaces
* Define a pattern which must match the article's name
* Name a page to which the article must or must not link
* Name a template which the article must or must not use
* Name a text pattern which must occur within external links from a page
* Exclude or include redirections
* Restrict your search to stable pages or quality pages ("flagged revisions")
* Use other criteria for selection like author, date of last change etc.
* Define regular expressions to match the contents of pages you want to include

**Order the result list of articles according to**
* Article Name
* Article Size
* Date of last change
* Last User to Make an Edit

**Define attributes you want to see**
* Article Name
* Article Namespace
* Article Size
* Date of Last Change
* Date of Last Access
* Last User to Make an Edit

**Define contents you want to show**
* Whole Article
* Contents of Certain Sections (Identified by headings)
* Text Portions (Defined by special marker tags in the article)
* Values of template calls
* Use a custom template to show output

**Define the output format**
* Specify header and footer for the default output
* Use ordered list, unordered list
* Use tables
* Format table fields individually by applying templates to their content
* Use category style listing
* Truncate title or contents to a certain maximum length
* Add a link to the article or to one or more of its sections

## Considerations
### Performance
DPL's code execution and database access is typically fast for typical category and article look ups.  However, using loose LIKE and REGEXP match parameters and/or requesting large data sets can result in long database access times.  Parser time should also be kept in consideration.  For example, having the query of image results go into a template that displays them will result in a parser media transform for each one.  This can quickly eat up 2MBs of RAM per media transform.

## See Also
### Further Reading
DPL can do much more than we can explain here. A complete **[manual](https://help.gamepedia.com/Extension:DPL3/Manual)** is available with full parameter documentation.
