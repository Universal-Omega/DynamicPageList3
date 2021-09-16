<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

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
];

$cfg['scalar_implicit_cast'] = true;

return $cfg;
