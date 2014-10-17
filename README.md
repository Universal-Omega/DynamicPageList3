Dynamic Page List
=================

The DynamicPageList extension is a reporting tool for MediaWiki, listing category members, and intersections with various formats and details.  For full documentation, see the [manual](http://semeb.com/dpldemo/DPL:Manual) (Handbuch in Deutsch [Hilfe:DynamicPageList](http://www.wiki-aventurica.de/wiki/Hilfe:Dynamic_Page_List)).

In its most basic form DPL displays a list of pages in one or more categories.  Selections may also be based on factors such as author, namespace, date, name pattern, usage of templates, or references to other articles.  Output takes a variety of forms some of which incorporate elements of selected articles.

This extension is invoked with the parser function {{#dpl: .... }} or parser tag <dpl>.  A Wikimedia-compatible implementation of certain features can be invoked with <DynamicPageList>.

DPL can result in computationally-expensive database queries. For best performance, use the optional parameters allowcachedresults and/or dplcache where possible.