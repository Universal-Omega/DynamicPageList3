<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'], [
		'../../extensions/Variables',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'], [
		'../../extensions/Variables',
	]
);

$cfg['suppress_issue_types'] = [
//	'PhanPossiblyUndeclaredVariable',
//	'PhanTypePossiblyInvalidDimOffset',
//	'PhanTypeInvalidDimOffset',
//	'PhanTypeMismatchReturnNullable',
//	'PhanTypeArraySuspiciousNullable',
//	'PhanTypeMismatchReturnProbablyReal',
//	'PhanUndeclaredVariableDim',
//	'PhanImpossibleTypeComparison',
//	'PhanTypeMismatchArgument',
//	'PhanPossiblyNullTypeMismatchProperty',
//	'PhanTypeMismatchArgumentInternal',
	'SecurityCheck-LikelyFalsePositive',
	'PhanAccessMethodInternal',
//	'PhanParamReqAfterOpt',
//	'PhanSuspiciousValueComparison',
//	'PhanPluginLoopVariableReuse',
];

// $cfg['scalar_implicit_cast'] = true;

return $cfg;
