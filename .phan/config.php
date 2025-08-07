<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['minimum_target_php_version'] = '8.1';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'], [
		'../../extensions/PageImages',
		'../../extensions/Variables',
		'../../extensions/Video',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'], [
		'../../extensions/PageImages',
		'../../extensions/Variables',
		'../../extensions/Video',
	]
);

$cfg['suppress_issue_types'] = [
	'SecurityCheck-LikelyFalsePositive',
	'PhanAccessMethodInternal',
];

$cfg['plugins'] = array_merge( $cfg['plugins'], [
	'AddNeverReturnTypePlugin',
	'AlwaysReturnPlugin',
	'DeprecateAliasPlugin',
	'DollarDollarPlugin',
	'DuplicateConstantPlugin',
	'EmptyMethodAndFunctionPlugin',
	'EmptyStatementListPlugin',
	'FFIAnalysisPlugin',
	'InlineHTMLPlugin',
	'InvalidVariableIssetPlugin',
	'InvokePHPNativeSyntaxCheckPlugin',
	'LoopVariableReusePlugin',
	'NotFullyQualifiedUsagePlugin',
	'PHPDocRedundantPlugin',
	'PHPUnitAssertionPlugin',
	'PHPUnitNotDeadCodePlugin',
	'PreferNamespaceUsePlugin',
	'RedundantAssignmentPlugin',
	'SimplifyExpressionPlugin',
	'SleepCheckerPlugin',
	'StrictComparisonPlugin',
	'StrictLiteralComparisonPlugin',
	'SuspiciousParamOrderPlugin',
	'UnreachableCodePlugin',
	'UnsafeCodePlugin',
	'UseReturnValuePlugin',
] );

$cfg['analyze_signature_compatibility'] = true;
$cfg['enable_class_alias_support'] = true;
$cfg['enable_extended_internal_return_type_plugins'] = true;
$cfg['error_prone_truthy_condition_detection'] = true;
$cfg['redundant_condition_detection'] = true;
$cfg['unused_variable_detection'] = true;
$cfg['warn_about_relative_include_statement'] = true;

return $cfg;
