<?php

declare(strict_types=1);

use Mayfair\RealEstatePlatform\Classification\TaxonomyRegistry;
use Mayfair\RealEstatePlatform\Fields\FieldRegistry;
use Mayfair\RealEstatePlatform\ImportExport\ExportSerializer;
use Mayfair\RealEstatePlatform\ImportExport\ImportReport;
use Mayfair\RealEstatePlatform\ImportExport\SchemaCatalog;
use Mayfair\RealEstatePlatform\ImportExport\SourceParser;
use PHPUnit\Framework\TestCase;

final class ImportExportTest extends TestCase {
	public function testSchemaIsExplicitAndExcludesPrivateFields(): void {
		$schema = new SchemaCatalog( new FieldRegistry(), new TaxonomyRegistry() );
		$agent  = $schema->columns( 'agent' );
		self::assertContains( 'relationship_agency_id', $agent );
		self::assertContains( 'public_email', $agent );
		self::assertNotContains( 'private_notes', $agent );
		self::assertNotContains( 'agency_id', $agent );
		self::assertNotContains( 'post_author', $agent );
	}

	public function testCsvParserIsBoundedAndNormalizesHeaders(): void {
		$parser = new SourceParser();
		$result = $parser->parseString( "\xEF\xBB\xBFslug,title\nalpha,Alpha\n", 'csv' );
		self::assertIsArray( $result );
		self::assertSame( array( 'slug', 'title' ), $result['declared_columns'] );
		self::assertSame( 'alpha', $result['rows'][0]['data']['slug'] );
		self::assertSame( 2, $result['rows'][0]['line'] );
	}

	public function testMalformedCsvAndOversizedSourcesAreRejected(): void {
		$parser    = new SourceParser();
		$malformed = $parser->parseString( "slug,title\na\n", 'csv' );
		self::assertInstanceOf( WP_Error::class, $malformed );
		$large = $parser->parseString( str_repeat( 'x', SourceParser::MAX_BYTES + 1 ), 'csv' );
		self::assertInstanceOf( WP_Error::class, $large );
	}

	public function testJsonRowsAreObjectsAndPreserveTypes(): void {
		$parser = new SourceParser();
		$result = $parser->parseString( '{"rows":[{"slug":"alpha","featured":true}],"columns":["slug","featured"]}', 'json' );
		self::assertIsArray( $result );
		self::assertSame( true, $result['rows'][0]['data']['featured'] );
		self::assertSame( array( 'slug', 'featured' ), $result['declared_columns'] );
		$invalid = $parser->parseString( '{"rows":[["alpha"]]}', 'json' );
		self::assertInstanceOf( WP_Error::class, $invalid );
		$duplicate = $parser->parseString( '{"rows":[{"slug":"alpha"," SLUG ":"beta"}]}', 'json' );
		self::assertInstanceOf( WP_Error::class, $duplicate );
	}

	public function testCsvSerializerGuardsSpreadsheetFormulasAndPreservesColumnOrder(): void {
		$serializer = new ExportSerializer();
		$csv        = $serializer->csv(
			array( 'slug', 'title', 'price' ),
			array(
				array(
					'slug'  => 'safe',
					'title' => '=HYPERLINK(\"https://bad.example\")',
					'price' => -10,
				),
			)
		);
		self::assertIsString( $csv );
		self::assertStringContainsString( "'=HYPERLINK", $csv );
		self::assertStringContainsString( "'-10", $csv );
		self::assertLessThan( strpos( $csv, "'=HYPERLINK" ), strpos( $csv, 'safe' ) );
		self::assertSame( "\"a,b\"\n", $serializer->line( array( 'a,b' ) ) );
	}

	public function testJsonSerializerIsStableAndContainsOnlySuppliedRows(): void {
		$serializer = new ExportSerializer();
		$json       = $serializer->json(
			'property',
			array( 'id', 'title' ),
			array(
				array(
					'id'    => 5,
					'title' => 'नमस्ते',
				),
			)
		);
		self::assertIsString( $json );
		self::assertSame( '{"entity":"property","columns":["id","title"],"rows":[{"id":5,"title":"नमस्ते"}]}', $json );
	}

	public function testReportIsDeterministicAndExposesDecisions(): void {
		$report = new ImportReport( 'property', 'dry_run', 'upsert', 'csv' );
		$report->row(
			2,
			'create',
			array(
				'decision' => 'create',
				'identity' => 'slug:alpha',
			)
		);
		$report->row( 3, 'conflict', array( 'identity' => 'slug:beta' ), array( 'conflict: existing record' ) );
		$report->row( 4, 'skipped', array(), array( 'preflight: skipped' ) );
		$data = $report->toArray();
		self::assertSame( 'FAIL', $data['status'] );
		self::assertSame( 1, $data['counts']['create'] );
		self::assertSame( 1, $data['counts']['conflict'] );
		self::assertSame( 1, $data['counts']['skipped'] );
		self::assertSame( 'slug:alpha', $data['rows'][0]['identity'] );
	}
}
