<?php
/**
 * DynamicPageList
 * DPL Variables Class
 *
 * @author		IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license		GPL
 * @package		DynamicPageList
 *
 **/
namespace DPL;

class Parameters {
	/**
	 * Parameter Richness
	 * The level of parameters that is accesible for the user.
	 *
	 * @var		integer
	 */
	static private $parameterRichness = 0;

	/**
	 * List of all the valid parameters that can be used per level of functional richness.
	 *
	 * @var		array
	 */
	static private $parametersForRichnessLevel = [
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

	/**
	 * Sets the current parameter richness.
	 *
	 * @access	public
	 * @param	integer	Integer level.
	 * @return	void
	 */
    static public function setRichness($level) {
		self::$parameterRichness = intval($level);
	}

	/**
	 * Returns the current parameter richness.
	 *
	 * @access	public
	 * @return	integer
	 */
	static public function getRichness() {
		return self::$parameterRichness;
	}

	/**
	 * Tests if the function is valid for the current functional richness level.
	 *
	 * @access	public
	 * @param	string	Function to test.
	 * @return	boolean	Valid for this functional richness level.
	 */
	static public function testRichness($function) {
		$valid = false;
		for ($i = 0; $i <= self::getRichness(); $i++) {
			if (in_array($function, self::$parametersForRichnessLevel[$i])) {
				$valid = true;
				break;
			}
		}
		return $valid;
	}

	/**
	 * Returns all parameters for the current richness level or limited to the optional maximum richness.
	 *
	 * @access	public
	 * @param	
	 * @return	array	The functional richness parameters list.
	 */
	static public function getParametersForRichness($level = null) {
		if ($level === null) {
			$level = self::getRichness();
		}

		$parameters = [];
		for ($i = 0; $i <= $level; $i++) {
			$parameters = array_merge($parameters, self::$parametersForRichnessLevel[0]);
		}
		return $parameters;
	}
}
?>