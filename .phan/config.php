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
	'PhanUndeclaredTypeReturnType',
	'PhanPossiblyUndeclaredVariable',
	'PhanTypePossiblyInvalidDimOffset',
	'PhanTypeInvalidDimOffset',
	'PhanTypeMismatchReturnNullable',
	'PhanImpossibleCondition',
	'PhanTypeArraySuspiciousNullable',
	'PhanTypeMismatchReturnProbablyReal',
	'PhanUndeclaredVariableDim',
	'PhanUnextractableAnnotation',
	'PhanImpossibleTypeComparison',
	'PhanTypeMismatchArgument',
	'PhanUnextractableAnnotationElementName',
	'PhanPossiblyNullTypeMismatchProperty',
	'PhanTypeMismatchArgumentInternal',
	'SecurityCheck-LikelyFalsePositive',
	'PhanPluginMixedKeyNoKey',
	'PhanAccessMethodInternal',
	'PhanParamReqAfterOpt',
];

$cfg['scalar_implicit_cast'] = true;

return $cfg;
