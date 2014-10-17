<?php
/**
 * DynamicPageList
 * DynamicPageList Master File
 *
 * @author		IlyaHaykinson, Unendlich, Dangerville, Algorithmix, Theaitetos, Alexia E. Smith
 * @license		GPL
 * @package		DynamicPageList
 *
**/

if (!defined('MEDIAWIKI')) {
    die('This is not a valid entry point to MediaWiki.');
}

/******************************************/
/* Credits								  */
/******************************************/
define('DPL_VERSION', '3.0');

$credits = [
	'path' 				=> __FILE__,
	'name' 				=> 'DynamicPageList (third party)',
	'author' 			=> '[http://de.wikipedia.org/wiki/Benutzer:Algorithmix Gero Scholz], Alexia E. Smith',
	'url' 				=> 'https://www.mediawiki.org/wiki/Extension:DynamicPageList_(third-party)',
	'descriptionmsg' 	=> 'dpl-desc',
  	'version' 			=> $DPLVersion
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
	$dplSettings['allowedNamespaces'] = [
		NS_MAIN,
		NS_PROJECT
	];
*/
if (!isset($dplSettings['allowedNamespaces']) {
	$dplSettings['allowedNamespaces'] = null;
}

//Set this to true to ignore 'maxCategoryCount' and allow unlimited categories.  Please note that large amounts of categories in a query can slow down or crash servers.
if (!isset($dplSettings['allowUnlimitedCategories']) {
	$dplSettings['allowUnlimitedCategories'] = false;
}

//Set this to true to ignore 'maxResultCount' and allow unlimited results.  Please note that large result sets may result in slow or failed page loads.
if (!isset($dplSettings['allowUnlimitedResults']) {
	$dplSettings['allowUnlimitedResults'] = false;
}

//Set DPL to always behave like intersection.
if (!isset($dplSettings['behavingLikeIntersection']) {
	$dplSettings['behavingLikeIntersection'] = false;
}

//This sets up a standard Mediawiki caching interface, whether it be file, Memcache, or Redis.
if (!isset($dplSettings['cacheType']) {
	$dplSettings['cacheType'] = CACHE_ANYTHING;
}

//Maximum number of items in a category list before being cut off.
if (!isset($dplSettings['categoryStyleListCutoff']) {
	$dplSettings['categoryStyleListCutoff'] = 6;
}

//This does something with preventing DPL from "looking" at these categories.  @TODO: I will figure this out later.
if (!isset($dplSettings['fixedCategories']) {
	$dplSettings['fixedCategories'] = [];
}

//Set the level of parameters available to end users.
if (!isset($dplSettings['functionalRichness']) {
	if (isset($dplMigrationTesting) && $dplMigrationTesting === true) {
		$dplSettings['functionalRichness'] = 0;
	} else {
		$dplSettings['functionalRichness'] = 3;
	}
}

//Maximum number of categories to allow in queries.
if (!isset($dplSettings['maxCategoryCount']) {
	$dplSettings['maxCategoryCount'] = 4;
}

//Maximum number of categories to allow in queries.  I guess this is needed somewhere?
if (!isset($dplSettings['minCategoryCount']) {
	$dplSettings['minCategoryCount'] = 0;
}

//Maximum number of results to return from a query.
if (!isset($dplSettings['maxResultCount']) {
	$dplSettings['maxResultCount'] = 500;
}

//Set this to true to allow DPL to run from protected pages only.
if (!isset($dplSettings['runFromProtectedPagesOnly']) {
	$dplSettings['runFromProtectedPagesOnly'] = false;
}

//Force DPL to respect the parser cache.  It makes DPL less dynamic, but reduces overall server load.
if (!isset($dplSettings['respectParserCache']) {
	$dplSettings['respectParserCache'] = false;
}

\DPL\Config::init($dplSettings);
?>