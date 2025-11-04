<?php
/**
 * Tests for Database Schema class
 *
 * @package Ihumbak\SemanticSearch\Tests
 */

namespace Ihumbak\SemanticSearch\Tests\Database;

use Ihumbak\SemanticSearch\Database\Schema;
use WP_UnitTestCase;

/**
 * Schema test case.
 */
class SchemaTest extends WP_UnitTestCase {

	/**
	 * Schema instance
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * Set up before each test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->schema = new Schema();
	}

	/**
	 * Tear down after each test
	 */
	public function tearDown(): void {
		$this->schema->drop_tables();
		$this->schema->remove_fulltext_index();
		delete_option( 'ihumbak_semantic_search_schema_version' );
		parent::tearDown();
	}

	/**
	 * Test table creation
	 */
	public function test_create_tables() {
		$result = $this->schema->create_tables();
		$this->assertTrue( $result );
		$this->assertTrue( $this->schema->verify_table_exists() );
	}

	/**
	 * Test table name getter
	 */
	public function test_get_embeddings_table() {
		global $wpdb;
		$expected = $wpdb->prefix . 'semantic_embeddings';
		$this->assertEquals( $expected, $this->schema->get_embeddings_table() );
	}

	/**
	 * Test table drop
	 */
	public function test_drop_tables() {
		$this->schema->create_tables();
		$this->assertTrue( $this->schema->verify_table_exists() );

		$result = $this->schema->drop_tables();
		$this->assertTrue( $result );
		$this->assertFalse( $this->schema->verify_table_exists() );
	}

	/**
	 * Test fulltext index addition
	 */
	public function test_add_fulltext_index() {
		$result = $this->schema->add_fulltext_index();
		$this->assertTrue( $result );

		// Should return true if called again (idempotent).
		$result = $this->schema->add_fulltext_index();
		$this->assertTrue( $result );
	}

	/**
	 * Test schema version management
	 */
	public function test_schema_version() {
		$this->assertEquals( '0.0.0', $this->schema->get_schema_version() );

		$this->schema->update_schema_version( '0.1.0' );
		$this->assertEquals( '0.1.0', $this->schema->get_schema_version() );
	}

	/**
	 * Test needs upgrade check
	 */
	public function test_needs_upgrade() {
		$this->assertTrue( $this->schema->needs_upgrade() );

		$this->schema->update_schema_version( IHUMBAK_SEMANTIC_SEARCH_VERSION );
		$this->assertFalse( $this->schema->needs_upgrade() );
	}
}
