<?php
/**
 * Tests for Database Migrator class
 *
 * @package Ihumbak\SemanticSearch\Tests
 */

namespace Ihumbak\SemanticSearch\Tests\Database;

use Ihumbak\SemanticSearch\Database\Migrator;
use Ihumbak\SemanticSearch\Database\Schema;
use WP_UnitTestCase;

/**
 * Migrator test case.
 */
class MigratorTest extends WP_UnitTestCase {

	/**
	 * Schema instance
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * Migrator instance
	 *
	 * @var Migrator
	 */
	private Migrator $migrator;

	/**
	 * Set up before each test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->schema   = new Schema();
		$this->migrator = new Migrator( $this->schema );
	}

	/**
	 * Tear down after each test
	 */
	public function tearDown(): void {
		$this->migrator->rollback();
		parent::tearDown();
	}

	/**
	 * Test fresh migration
	 */
	public function test_migrate_fresh_install() {
		$result = $this->migrator->migrate();
		$this->assertTrue( $result );
		$this->assertTrue( $this->schema->verify_table_exists() );
		$this->assertEquals( IHUMBAK_SEMANTIC_SEARCH_VERSION, $this->schema->get_schema_version() );
	}

	/**
	 * Test migration is idempotent
	 */
	public function test_migrate_idempotent() {
		$this->migrator->migrate();
		$version = $this->schema->get_schema_version();

		// Run migration again.
		$result = $this->migrator->migrate();
		$this->assertTrue( $result );
		$this->assertEquals( $version, $this->schema->get_schema_version() );
	}

	/**
	 * Test rollback
	 */
	public function test_rollback() {
		$this->migrator->migrate();
		$this->assertTrue( $this->schema->verify_table_exists() );

		$result = $this->migrator->rollback();
		$this->assertTrue( $result );
		$this->assertFalse( $this->schema->verify_table_exists() );
		$this->assertEquals( '0.0.0', $this->schema->get_schema_version() );
	}
}
