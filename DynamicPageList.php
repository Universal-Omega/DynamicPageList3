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

$wgMessagesDirs['DynamicPageList']					= "{$extDir}/i18n";
$wgExtensionMessagesFiles['DynamicPageList']		= "{$extDir}/DynamicPageList.i18n.php";
$wgExtensionMessagesFiles['DynamicPageListMagic']	= "{$extDir}/DynamicPageList.i18n.magic.php";

$wgAutoloadClasses['DynamicPageListHooks']			= "{$extDir}/DPLSetup.php";
$wgAutoloadClasses['DPL\Article']					= "{$extDir}/classes/Article.php";
$wgAutoloadClasses['DPL\DynamicPageList']			= "{$extDir}/classes/DynamicPageList.php";
$wgAutoloadClasses['DPL\Logger']					= "{$extDir}/classes/Logger.php";
$wgAutoloadClasses['DPL\ListMode']					= "{$extDir}/classes/ListMode.php";
$wgAutoloadClasses['DPL\Logger']					= "{$extDir}/classes/Logger.php";
$wgAutoloadClasses['DPL\LST']						= "{$extDir}/classes/LST.php";
$wgAutoloadClasses['DPL\Main']						= "{$extDir}/classes/Main.php";
$wgAutoloadClasses['DPL\Parameters']				= "{$extDir}/classes/Parameters.php";
$wgAutoloadClasses['DPL\ParametersData']			= "{$extDir}/classes/ParametersData.php";
$wgAutoloadClasses['DPL\Query']						= "{$extDir}/classes/Query.php";
$wgAutoloadClasses['DPL\Variables']					= "{$extDir}/classes/Variables.php";

if (isset($dplMigrationTesting) && $dplMigrationTesting === true) {
	$wgHooks['ParserFirstCallInit'][]					= 'DynamicPageListHooks::setupMigration';
} else {
	$wgHooks['ParserFirstCallInit'][]					= 'DynamicPageListHooks::onParserFirstCallInit';
}

/******************************************/
/* Final Setup                            */
/******************************************/
if (isset($dplMigrationTesting) && $dplMigrationTesting === true) {
	//Use full functionality by default.
	\DPL\ParametersData::setRichness(0);
} else {
	\DPL\ParametersData::setRichness(4);
}
?>