<?php
/**
 * Tests for KeywordSearch class
 *
 * @package Ihumbak\SemanticSearch\Tests
 */

namespace Ihumbak\SemanticSearch\Tests\Search;

use Ihumbak\SemanticSearch\Database\Migrator;
use Ihumbak\SemanticSearch\Database\Schema;
use Ihumbak\SemanticSearch\Search\KeywordSearch;
use WP_UnitTestCase;

/**
 * KeywordSearch test case.
 */
class KeywordSearchTest extends WP_UnitTestCase {

	/**
	 * Schema instance
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * KeywordSearch instance
	 *
	 * @var KeywordSearch
	 */
	private KeywordSearch $search;

	/**
	 * Set up before each test
	 */
	public function setUp(): void {
		parent::setUp();

		$this->schema = new Schema();
		$migrator     = new Migrator( $this->schema );
		$migrator->migrate();

		$this->search = new KeywordSearch();

		// Create test posts.
		$this->factory->post->create(
			array(
				'post_title'   => 'WordPress Development',
				'post_content' => 'Learn about WordPress plugin development and themes.',
				'post_status'  => 'publish',
			)
		);

		$this->factory->post->create(
			array(
				'post_title'   => 'PHP Programming',
				'post_content' => 'Advanced PHP programming techniques.',
				'post_status'  => 'publish',
			)
		);
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
	 * Test search returns results
	 */
	public function test_search_returns_results() {
		$results = $this->search->search( 'WordPress' );
		$this->assertIsArray( $results );
		$this->assertNotEmpty( $results );
	}

	/**
	 * Test search result structure
	 */
	public function test_search_result_structure() {
		$results = $this->search->search( 'WordPress' );

		if ( ! empty( $results ) ) {
			$this->assertArrayHasKey( 'post_id', $results[0] );
			$this->assertArrayHasKey( 'relevance', $results[0] );
		}
	}

	/**
	 * Test fulltext index exists
	 */
	public function test_fulltext_index_exists() {
		$exists = $this->search->fulltext_index_exists();
		$this->assertTrue( $exists );
	}
}
