<?php

$wgHooks['MediaWikiServices'][] = 'wfOnMediaWikiServices';

function wfOnMediaWikiServices( MediaWiki\MediaWikiServices $services ) {
	global $wgMainCacheType, $wgMessageCacheType, $wgUseLocalMessageCache;

	$wgMainCacheType = 'memcached-pecl';
	$wgMessageCacheType = CACHE_ANYTHING;
	$wgUseLocalMessageCache = true;
}
