<?php
$wgMessageCacheType = CACHE_NONE;
$wgHooks['MediaWikiServices'][] = 'wfOnMediaWikiServices';

function wfOnMediaWikiServices( MediaWiki\MediaWikiServices $services ) {
	global $wgMemCachedServers, $wgMemCachedPersistent;

	if ( extension_loaded( 'memcached' ) ) {
		$wgMemCachedServers = [ '0.0.0.0:11211' ];
		$wgMemCachedPersistent = false;
	}
}
