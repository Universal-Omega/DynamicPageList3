<?php

declare( strict_types = 1 );

namespace MediaWiki\Extension\DynamicPageList4;

final class Constants {

	public const FATAL_WRONGNS = 1001;

	public const FATAL_WRONGLINKSTO = 1002;

	public const FATAL_TOOMANYCATS = 1003;

	public const FATAL_TOOFEWCATS = 1004;

	public const FATAL_NOSELECTION = 1005;

	public const FATAL_CATDATEBUTNOINCLUDEDCATS = 1006;

	public const FATAL_CATDATEBUTMORETHAN1CAT = 1007;

	public const FATAL_MORETHAN1TYPEOFDATE = 1008;

	public const FATAL_WRONGORDERMETHOD = 1009;

	public const FATAL_DOMINANTSECTIONRANGE = 1010;

	public const FATAL_OPENREFERENCES = 1012;

	public const FATAL_MISSINGPARAMFUNCTION = 1022;

	public const FATAL_POOLCOUNTER = 1023;

	public const FATAL_NOTPROTECTED = 1024;

	public const FATAL_SQLBUILDERROR = 1025;

	public const WARN_UNKNOWNPARAM = 2013;

	public const WARN_PARAMNOOPTION = 2022;

	public const WARN_WRONGPARAM = 2014;

	public const WARN_WRONGPARAM_INT = 2015;

	public const WARN_NORESULTS = 2016;

	public const WARN_CATOUTPUTBUTWRONGPARAMS = 2017;

	public const WARN_HEADINGBUTSIMPLEORDERMETHOD = 2018;

	public const WARN_DEBUGPARAMNOTFIRST = 2019;

	public const WARN_TRANSCLUSIONLOOP = 2020;

	public const DEBUG_QUERY = 3021;
}
