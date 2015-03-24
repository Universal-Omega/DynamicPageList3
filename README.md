Dynamic Page List
=================

The DynamicPageList extension is a reporting tool for MediaWiki, listing category members, and intersections with various formats and details.  For full documentation, see the [DPL Manual on the Gamepedia Help Wiki](http://help.gamepedia.com/DPL:Manual).

In its most basic form DPL displays a list of pages in one or more categories.  Selections may also be based on factors such as author, namespace, date, name pattern, usage of templates, or references to other articles.  Output takes a variety of forms some of which incorporate elements of selected articles.

This extension is invoked with the parser function {{#dpl: .... }} or parser tag <dpl>.  A Wikimedia-compatible implementation of certain features can be invoked with <DynamicPageList>.

Complex DPL parameters, especially those with many categories, can result in computationally expensive database queries.  For best performance, it is not recommend to turn off caching.