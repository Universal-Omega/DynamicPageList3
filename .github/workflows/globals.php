<?php

$wgHooks['MediaWikiServices'][] = 'wfOnMediaWikiServices';

function wfOnMediaWikiServices( MediaWiki\MediaWikiServices $services ) {
	global $wgMainCacheType;

	$wgMainCacheType = CACHE_NONE;
}
