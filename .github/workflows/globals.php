<?php

$wgObjectCaches['redis'] = [
	'class' => RedisBagOStuff::class,
	'servers' => [ '127.0.0.1:6379' ],
	'persistent' => true,
	'loggroup' => 'redis',
	'reportDupes' => false,
	'connectTimeout' => 2,
];

$wgMessageCacheType = CACHE_ACCEL;
$wgSessionCacheType = 'redis';
