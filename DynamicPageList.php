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

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'DynamicPageList' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['DynamicPageList'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for DynamicPageList extension. Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
 } else {
	die( 'This version of the DynamicPageList extension requires MediaWiki 1.25+' );
}
