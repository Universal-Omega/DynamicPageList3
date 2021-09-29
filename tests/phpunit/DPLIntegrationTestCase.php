<?php

namespace DPL;

use DOMDocument;
use DOMXPath;
use ImportStreamSource;
use MediaWiki\Auth\AuthManager;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\UserFactory;
use MediaWikiTestCase;
use ParserOptions;
use RequestContext;
use Title;
use User;
use WikiImporter;

abstract class DPLIntegrationTestCase extends MediaWikiTestCase {
	/**
	 * Guard condition to ensure we only import seed data once per test suite run.
	 * @var bool
	 */
	private static $wasSeedDataImported = false;

	public function addDBData() {
		if ( self::$wasSeedDataImported ) {
			return;
		}

		$seedDataPath = __DIR__ . '/../seed-data.xml';
		$this->seedTestUsers( $seedDataPath );
		$importer = $this->getWikiImporter( $seedDataPath );
		$importer->disableStatisticsUpdate();
		// Ensure we actually create local user accounts in the DB
		$importer->setUsernamePrefix( '', true );
		$importer->doImport();

		self::$wasSeedDataImported = true;
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

		$authManager = $this->getAuthManager();

		foreach ( $userNodes as $node ) {
			$userName = $node->nodeValue;

			// Already created
			if ( isset( $usersByName[$userName] ) ) {
				continue;
			}

			$usersByName[$userName] = true;
			$user = $this->newUserFromName( $userName );

			if ( !$user || $user->idForName() !== 0 ) {
				return; // sanity
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

	private function getWikiImporter( string $seedDataPath ): WikiImporter {
		$seedDataFile = fopen( $seedDataPath, 'rt' );
		$source = new ImportStreamSource( $seedDataFile );
		$services = MediaWikiServices::getInstance();

		if ( $services->hasService( 'WikiImporterFactory' ) ) {
			return $services->getWikiImporterFactory()->getWikiImporter( $source );
		}

		// MW 1.36
		return new WikiImporter( $source, $services->getMainConfig() );
	}

	private function getAuthManager(): AuthManager {
		$services = MediaWikiServices::getInstance();

		return $services->getAuthManager();
	}

	private function newUserFromName( string $name ): ?User {
		$services = MediaWikiServices::getInstance();

		return $services->getUserFactory()->newFromName( $name, UserFactory::RIGOR_CREATABLE );
	}

	/**
	 * Convenience function to return the list of page titles matching a DPL query
	 * @param array $params - DPL invocation parameters
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
		$invocation = '<dpl>';

		foreach ( $params as $paramName => $values ) {
			$values = (array)$values; // multi-value parameters
			foreach ( $values as $value ) {
				$invocation .= "$paramName=$value\n";
			}
		}

		$invocation .= '</dpl>';

		$parser = MediaWikiServices::getInstance()->getParser();
		$title = Title::makeTitle( NS_MAIN, 'DPLQueryTest' );
		$parserOptions = ParserOptions::newCanonical(
			RequestContext::getMain()
		);
		$parserOutput = $parser->parse( $invocation, $title, $parserOptions );

		return $parserOutput->getText();
	}
}
