<?php
/**
 * DynamicPageList3
 * DynamicPageList3 Master File
 *
 * @author		IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license		GPL
 * @package		DynamicPageList3
 *
**/

if (!defined('MEDIAWIKI')) {
    die('This is not a valid entry point to MediaWiki.');
}

/******************************************/
/* Credits								  */
/******************************************/
define('DPL_VERSION', '3.0.9');

$credits = [
	'path' 				=> __FILE__,
	'name' 				=> 'DynamicPageList3',
	'author' 			=> ['[http://de.wikipedia.org/wiki/Benutzer:Algorithmix Gero Scholz]', 'Alexia E. Smith'],
	'url' 				=> 'https://www.mediawiki.org/wiki/Extension:DynamicPageList3',
	'descriptionmsg' 	=> 'dpl-desc',
	'license-name'		=> 'GPL-2.0',
	'version' 			=> DPL_VERSION
];
$wgExtensionCredits['parserhook'][] = $credits;

/******************************************/
/* Language Strings, Page Aliases, Hooks  */
/******************************************/
$extDir = __DIR__;

$wgAvailableRights[] = 'dpl_param_update_rules';
$wgAvailableRights[] = 'dpl_param_delete_rules';

$wgMessagesDirs['DynamicPageList']					= "{$extDir}/i18n";
$wgExtensionMessagesFiles['DynamicPageList']		= "{$extDir}/DynamicPageList.i18n.php";
$wgExtensionMessagesFiles['DynamicPageListMagic']	= "{$extDir}/DynamicPageList.i18n.magic.php";

$wgAutoloadClasses['DynamicPageListHooks']			= "{$extDir}/DynamicPageList.hooks.php";
$wgAutoloadClasses['DPL\Article']					= "{$extDir}/classes/Article.php";
$wgAutoloadClasses['DPL\Config']					= "{$extDir}/classes/Config.php";
$wgAutoloadClasses['DPL\DynamicPageList']			= "{$extDir}/classes/DynamicPageList.php";
$wgAutoloadClasses['DPL\Logger']					= "{$extDir}/classes/Logger.php";
$wgAutoloadClasses['DPL\ListMode']					= "{$extDir}/classes/ListMode.php";
$wgAutoloadClasses['DPL\Logger']					= "{$extDir}/classes/Logger.php";
$wgAutoloadClasses['DPL\LST']						= "{$extDir}/classes/LST.php";
$wgAutoloadClasses['DPL\Parameters']				= "{$extDir}/classes/Parameters.php";
$wgAutoloadClasses['DPL\ParametersData']			= "{$extDir}/classes/ParametersData.php";
$wgAutoloadClasses['DPL\Parse']						= "{$extDir}/classes/Parse.php";
$wgAutoloadClasses['DPL\Query']						= "{$extDir}/classes/Query.php";
$wgAutoloadClasses['DPL\Variables']					= "{$extDir}/classes/Variables.php";

if (isset($dplMigrationTesting) && $dplMigrationTesting === true) {
	$wgHooks['ParserFirstCallInit'][]					= 'DynamicPageListHooks::setupMigration';
} else {
	$wgHooks['ParserFirstCallInit'][]					= 'DynamicPageListHooks::onParserFirstCallInit';
}
$wgHooks['LoadExtensionSchemaUpdates'][]				= 'DynamicPageListHooks::onLoadExtensionSchemaUpdates';

//Give sysops permission to use updaterules and deleterules by default.
if (!isset($wgGroupPermissions['sysop']['dpl_param_update_rules'])) {
	$wgGroupPermissions['sysop']['dpl_param_update_rules'] = true;
}
if (!isset($wgGroupPermissions['sysop']['dpl_param_delete_rules'])) {
	$wgGroupPermissions['sysop']['dpl_param_delete_rules'] = true;
}

/******************************************/
/* Final Setup                            */
/******************************************/

//By default all setup namespaces are used when DPL initializes.  Customize this setting with an array of namespace constants to restrict DPL to work only in those namespaces.
/* Example, restrict DPL to look only in the Main and Project namespaces.
	$wgDplSettings['allowedNamespaces'] = [
		NS_MAIN,
		NS_PROJECT
	];
*/
$wgDplSettings['allowedNamespaces'] = null;

//Set this to true to ignore 'maxCategoryCount' and allow unlimited categories.  Please note that large amounts of categories in a query can slow down or crash servers.
$wgDplSettings['allowUnlimitedCategories'] = false;

//Set this to true to ignore 'maxResultCount' and allow unlimited results.  Please note that large result sets may result in slow or failed page loads.
$wgDplSettings['allowUnlimitedResults'] = false;

//Set DPL to always behave like Extension:Intersection.
$wgDplSettings['behavingLikeIntersection'] = false;

//Maximum length to format a list of articles chunked by letter as bullet list, if list bigger or columnar format user.(Same as cut off argument for CategoryViewer::formatList()).
$wgDplSettings['categoryStyleListCutoff'] = 6;

//This does something with preventing DPL from "looking" at these categories.
$wgDplSettings['fixedCategories'] = [];

//Set the level of parameters available to end users.
if (isset($dplMigrationTesting) && $dplMigrationTesting === true) {
	$wgDplSettings['functionalRichness'] = 0;
} else {
	$wgDplSettings['functionalRichness'] = 3;
}


//Maximum number of categories to allow in queries.
$wgDplSettings['maxCategoryCount'] = 4;

//Minimum number of categories to allow in queries.
$wgDplSettings['minCategoryCount'] = 0;

//Maximum number of results to return from a query.
$wgDplSettings['maxResultCount'] = 500;

//Do recursive tag parsing on <dpl> parser tags converting tags and functions such as magic words like {{PAGENAME}}.  This is similar to the {{#dpl}} parser function call, but may not work exactly the same in all cases.
$wgDplSettings['recursiveTagParse'] = false;

//Set this to true to allow DPL to run from protected pages only.  This is recommend if wiki administrators are having issues with malicious users creating computationally intensive queries.
$wgDplSettings['runFromProtectedPagesOnly'] = false;

//Handle <section> tags in an entirely broken manner.  This is disabled by default as version 3.0.8 because it is unknown what that feature was actually supposed to do.
$wgDplSettings['handleSectionTag'] = false;
?>