<?php
/**
 * Tests for HybridSearch class
 *
 * @package Ihumbak\SemanticSearch\Tests
 */

namespace Ihumbak\SemanticSearch\Tests\Search;

use Ihumbak\SemanticSearch\Database\Migrator;
use Ihumbak\SemanticSearch\Database\Schema;
use Ihumbak\SemanticSearch\Search\HybridSearch;
use WP_UnitTestCase;

/**
 * HybridSearch test case.
 */
class HybridSearchTest extends WP_UnitTestCase {

	/**
	 * Schema instance
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * HybridSearch instance
	 *
	 * @var HybridSearch
	 */
	private HybridSearch $search;

	/**
	 * Set up before each test
	 */
	public function setUp(): void {
		parent::setUp();

		$this->schema = new Schema();
		$migrator     = new Migrator( $this->schema );
		$migrator->migrate();

		$this->search = new HybridSearch();
	}

	/**
	 * Tear down after each test
	 */
	public function tearDown(): void {
		$migrator = new Migrator( $this->schema );
		$migrator->rollback();
		parent::tearDown();
	}

	/**
	 * Test search with empty query
	 */
	public function test_search_empty_query() {
		$results = $this->search->search( '' );
		$this->assertEmpty( $results );
	}

	/**
	 * Test get search mode
	 */
	public function test_get_search_mode() {
		$mode = $this->search->get_search_mode();
		$this->assertIsString( $mode );
		$this->assertContains( $mode, array( 'hybrid', 'keyword', 'semantic' ) );
	}

	/**
	 * Test search with mode
	 */
	public function test_search_with_mode() {
		update_option( 'ihumbak_semantic_search_mode', 'hybrid' );

		$results = $this->search->search_with_mode( 'test query' );
		$this->assertIsArray( $results );
	}
}
