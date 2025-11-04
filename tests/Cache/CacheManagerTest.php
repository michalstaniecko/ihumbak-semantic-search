<?php
/**
 * Tests for CacheManager class
 *
 * @package Ihumbak\SemanticSearch\Tests
 */

namespace Ihumbak\SemanticSearch\Tests\Cache;

use Ihumbak\SemanticSearch\Cache\CacheManager;
use WP_UnitTestCase;

/**
 * CacheManager test case.
 */
class CacheManagerTest extends WP_UnitTestCase {

	/**
	 * CacheManager instance
	 *
	 * @var CacheManager
	 */
	private CacheManager $cache;

	/**
	 * Set up before each test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->cache = new CacheManager();

		// Enable cache for tests.
		update_option( 'ihumbak_semantic_search_cache_enabled', true );
	}

	/**
	 * Test is enabled
	 */
	public function test_is_enabled() {
		$enabled = $this->cache->is_enabled();
		$this->assertTrue( $enabled );
	}

	/**
	 * Test set and get search results
	 */
	public function test_set_get_search_results() {
		$query   = 'test query';
		$args    = array( 'limit' => 10 );
		$results = array(
			array( 'post_id' => 1, 'score' => 0.9 ),
		);

		$set_result = $this->cache->set_search_results( $query, $args, $results );
		$this->assertTrue( $set_result );

		$cached = $this->cache->get_search_results( $query, $args );
		$this->assertEquals( $results, $cached );
	}

	/**
	 * Test get non-existent results
	 */
	public function test_get_nonexistent_results() {
		$cached = $this->cache->get_search_results( 'nonexistent query', array() );
		$this->assertFalse( $cached );
	}

	/**
	 * Test flush all
	 */
	public function test_flush_all() {
		$this->cache->set_search_results( 'query1', array(), array( 'result1' ) );
		$this->cache->set_search_results( 'query2', array(), array( 'result2' ) );

		$this->cache->flush_all();

		// After flush, cache should be empty.
		$this->assertFalse( $this->cache->get_search_results( 'query1', array() ) );
		$this->assertFalse( $this->cache->get_search_results( 'query2', array() ) );
	}

	/**
	 * Test invalidate post
	 */
	public function test_invalidate_post() {
		$this->cache->set_search_results( 'query', array(), array( 'result' ) );

		$this->cache->invalidate_post( 123 );

		// Should flush all caches.
		$this->assertFalse( $this->cache->get_search_results( 'query', array() ) );
	}

	/**
	 * Test cache stats
	 */
	public function test_get_stats() {
		$stats = $this->cache->get_stats();

		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'enabled', $stats );
		$this->assertArrayHasKey( 'ttl', $stats );
		$this->assertArrayHasKey( 'group', $stats );
	}

	/**
	 * Test embedding cache
	 */
	public function test_embedding_cache() {
		$text      = 'test text';
		$embedding = array( 0.1, 0.2, 0.3 );

		$this->cache->set_embedding( $text, $embedding );
		$cached = $this->cache->get_embedding( $text );

		$this->assertEquals( $embedding, $cached );
	}
}
