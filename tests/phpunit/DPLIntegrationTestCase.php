<?php

namespace MediaWiki\Extension\DynamicPageList3\Tests;

use DOMDocument;
use DOMXPath;
use ImportStreamSource;
use MediaWiki\User\UserFactory;
use MediaWikiIntegrationTestCase;
use ParserOptions;
use RequestContext;
use Status;
use User;

abstract class DPLIntegrationTestCase extends MediaWikiIntegrationTestCase {

	/** @var Status */
	private $importStreamSource;

	/**
	 * Guard condition to ensure we only import seed data once per test suite run.
	 * Only used before 1.42 as it breaks on 1.42 if not running for each test
	 *
	 * @var bool
	 */
	private static $wasSeedDataImported = false;

	protected function setUp(): void {
		parent::setUp();

		$file = dirname( __DIR__ ) . '/seed-data.xml';
		$this->importStreamSource = ImportStreamSource::newFromFile( $file );

		if ( !$this->importStreamSource->isGood() ) {
			$this->fail( "Import source for {$file} failed" );
		}
	}

	private function doImport(): void {
		$services = $this->getServiceContainer();
		if ( version_compare( MW_VERSION, '1.42', '>=' ) ) {
			$file = dirname( __DIR__ ) . '/seed-data.xml';
			$this->seedTestUsers( $file );
			$importer = $services->getWikiImporterFactory()->getWikiImporter(
				$this->importStreamSource->value,
				$this->getTestSysop()->getAuthority()
			);
		} else {
			if ( self::$wasSeedDataImported ) {
				return;
			}
			self::$wasSeedDataImported = true;
			$file = dirname( __DIR__ ) . '/seed-data.xml';
			$this->seedTestUsers( $file );
			$importer = $services->getWikiImporterFactory()->getWikiImporter(
				$this->importStreamSource->value
			);
		}

		$importer->disableStatisticsUpdate();

		// Ensure we actually create local user accounts in the DB
		$importer->setUsernamePrefix( '', true );
		$importer->doImport();
	}

	/**
	 * Import test accounts from seed data so that DPL queries can refer to them.
	 * @param string $seedDataPath - path to seed data to be loaded
	 */
	private function seedTestUsers( string $seedDataPath ): void {
		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = false;
		$doc->load( $seedDataPath );

		$xpath = new DOMXPath( $doc );
		$xpath->registerNamespace( 'mw', 'http://www.mediawiki.org/xml/export-0.11/' );

		$userNodes = $xpath->query( '//mw:mediawiki/mw:page/mw:revision/mw:contributor/mw:username' );
		$usersByName = [];

		$authManager = $this->getServiceContainer()->getAuthManager();

		foreach ( $userNodes as $node ) {
			$userName = $node->nodeValue;

			// Already created
			if ( isset( $usersByName[$userName] ) ) {
				continue;
			}

			$usersByName[$userName] = true;
			$user = $this->newUserFromName( $userName );

			if ( !$user || $user->idForName() !== 0 ) {
				// sanity
				return;
			}

			$status = $authManager->autoCreateUser(
				$user,
				$authManager::AUTOCREATE_SOURCE_MAINT,
				false
			);

			if ( !$status->isOK() ) {
				return;
			}
		}
	}

	private function newUserFromName( string $name ): ?User {
		$services = $this->getServiceContainer();
		return $services->getUserFactory()->newFromName( $name, UserFactory::RIGOR_CREATABLE );
	}

	/**
	 * Convenience function to return the list of page titles matching a DPL query
	 * @param array $params - DPL invocation parameters
	 * @param string $format
	 * @return string[]
	 */
	protected function getDPLQueryResults( array $params, string $format = '%PAGE%' ): array {
		$params += [
			// Use a custom format for executing the query to allow easily extracting results
			'format' => "<div id=\"dpl-test-query\">,$format,|,</div>"
		];

		$html = $this->runDPLQuery( $params );
		$doc = new DOMDocument();
		$doc->loadHTML( $html );
		$queryResults = $doc->getElementById( 'dpl-test-query' );
		if ( $queryResults ) {
			return explode( "|", rtrim( $queryResults->textContent, "|" ) );
		}

		return [];
	}

	/**
	 * Build and execute a DPL invocation using the given parameters and return the HTML output.
	 * @param array $params
	 * @return string
	 */
	protected function runDPLQuery( array $params ): string {
		$this->doImport();

		$invocation = '<dpl>';

		foreach ( $params as $paramName => $values ) {
			// multi-value parameters
			$values = (array)$values;

			foreach ( $values as $value ) {
				$invocation .= "$paramName=$value\n";
			}
		}

		$invocation .= '</dpl>';

		$parser = $this->getServiceContainer()->getParserFactory()->getInstance();
		$title = $this->getServiceContainer()->getTitleFactory()->makeTitle( NS_MAIN, 'DPLQueryTest' );
		$parserOptions = ParserOptions::newCanonical(
			RequestContext::getMain()
		);

		$parserOutput = $parser->parse( $invocation, $title, $parserOptions );

		return $parserOutput->getText();
	}
}
