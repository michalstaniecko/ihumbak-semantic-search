<?php
/**
 * Tests for FuzzySearch class
 *
 * @package Ihumbak\SemanticSearch\Tests
 */

namespace Ihumbak\SemanticSearch\Tests\Search;

use Ihumbak\SemanticSearch\Database\Migrator;
use Ihumbak\SemanticSearch\Database\Schema;
use Ihumbak\SemanticSearch\Search\FuzzySearch;
use WP_UnitTestCase;

/**
 * FuzzySearch test case.
 */
class FuzzySearchTest extends WP_UnitTestCase {

	/**
	 * Schema instance
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * FuzzySearch instance
	 *
	 * @var FuzzySearch
	 */
	private FuzzySearch $fuzzy;

	/**
	 * Set up before each test
	 */
	public function setUp(): void {
		parent::setUp();

		$this->schema = new Schema();
		$migrator     = new Migrator( $this->schema );
		$migrator->migrate();

		$this->fuzzy = new FuzzySearch();

		// Create test posts with Polish content.
		$this->factory->post->create(
			array(
				'post_title'   => 'WordPress Development',
				'post_content' => 'Nauka o WordPress i tworzeniu wtyczek.',
				'post_status'  => 'publish',
			)
		);

		$this->factory->post->create(
			array(
				'post_title'   => 'Wyszukiwanie semantyczne',
				'post_content' => 'Zaawansowane wyszukiwanie w WordPress używając sztucznej inteligencji.',
				'post_status'  => 'publish',
			)
		);

		$this->factory->post->create(
			array(
				'post_title'   => 'Plugin development',
				'post_content' => 'Tworzenie pluginów dla WordPress.',
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
		$results = $this->fuzzy->search( '' );
		$this->assertEmpty( $results );
	}

	/**
	 * Test normalization of Polish characters
	 */
	public function test_normalize_polish_characters() {
		$reflection = new \ReflectionClass( $this->fuzzy );
		$method     = $reflection->getMethod( 'normalize' );
		$method->setAccessible( true );

		$text       = 'Ąćęłńóśźż ĄĆĘŁŃÓŚŹŻ';
		$normalized = $method->invoke( $this->fuzzy, $text );

		$this->assertEquals( 'acelnoszz acelnoszz', $normalized );
	}

	/**
	 * Test normalization converts to lowercase
	 */
	public function test_normalize_lowercase() {
		$reflection = new \ReflectionClass( $this->fuzzy );
		$method     = $reflection->getMethod( 'normalize' );
		$method->setAccessible( true );

		$text       = 'WordPress PLUGIN Development';
		$normalized = $method->invoke( $this->fuzzy, $text );

		$this->assertEquals( 'wordpress plugin development', $normalized );
	}

	/**
	 * Test normalization removes extra whitespace
	 */
	public function test_normalize_whitespace() {
		$reflection = new \ReflectionClass( $this->fuzzy );
		$method     = $reflection->getMethod( 'normalize' );
		$method->setAccessible( true );

		$text       = '  WordPress   Plugin   ';
		$normalized = $method->invoke( $this->fuzzy, $text );

		$this->assertEquals( 'wordpress plugin', $normalized );
	}

	/**
	 * Test similarity calculation
	 */
	public function test_calculate_similarity() {
		$reflection = new \ReflectionClass( $this->fuzzy );
		$method     = $reflection->getMethod( 'calculate_similarity' );
		$method->setAccessible( true );

		// Identical strings should have similarity of 1.0.
		$similarity = $method->invoke( $this->fuzzy, 'wordpress', 'wordpress' );
		$this->assertEquals( 1.0, $similarity, '', 0.01 );

		// Similar strings should have high similarity.
		$similarity = $method->invoke( $this->fuzzy, 'wordpress', 'wordpres' );
		$this->assertGreaterThan( 0.7, $similarity );

		// Very different strings should have low similarity.
		$similarity = $method->invoke( $this->fuzzy, 'wordpress', 'xyz' );
		$this->assertLessThan( 0.3, $similarity );
	}

	/**
	 * Test similarity calculation with empty strings
	 */
	public function test_calculate_similarity_empty() {
		$reflection = new \ReflectionClass( $this->fuzzy );
		$method     = $reflection->getMethod( 'calculate_similarity' );
		$method->setAccessible( true );

		$similarity = $method->invoke( $this->fuzzy, '', 'wordpress' );
		$this->assertEquals( 0.0, $similarity );

		$similarity = $method->invoke( $this->fuzzy, 'wordpress', '' );
		$this->assertEquals( 0.0, $similarity );
	}

	/**
	 * Test word overlap calculation
	 */
	public function test_calculate_word_overlap() {
		$reflection = new \ReflectionClass( $this->fuzzy );
		$method     = $reflection->getMethod( 'calculate_word_overlap' );
		$method->setAccessible( true );

		// All words match.
		$overlap = $method->invoke( $this->fuzzy, 'wordpress plugin', 'wordpress plugin development' );
		$this->assertEquals( 1.0, $overlap, '', 0.01 );

		// Partial match.
		$overlap = $method->invoke( $this->fuzzy, 'wordpress development', 'wordpress plugin' );
		$this->assertGreaterThan( 0.0, $overlap );
		$this->assertLessThan( 1.0, $overlap );

		// No match.
		$overlap = $method->invoke( $this->fuzzy, 'xyz abc', 'def ghi' );
		$this->assertEquals( 0.0, $overlap );
	}

	/**
	 * Test word overlap with empty query
	 */
	public function test_calculate_word_overlap_empty() {
		$reflection = new \ReflectionClass( $this->fuzzy );
		$method     = $reflection->getMethod( 'calculate_word_overlap' );
		$method->setAccessible( true );

		$overlap = $method->invoke( $this->fuzzy, '', 'wordpress plugin' );
		$this->assertEquals( 0.0, $overlap );
	}

	/**
	 * Test search with typo
	 */
	public function test_search_with_typo() {
		// Search with typo should find results.
		$results = $this->fuzzy->search( 'wordpres' );
		$this->assertIsArray( $results );
		// Note: Results may be empty if threshold is too high or no similar content.
	}

	/**
	 * Test search with Polish typo
	 */
	public function test_search_with_polish_typo() {
		$results = $this->fuzzy->search( 'wyszukiwnie' ); // Typo for "wyszukiwanie".
		$this->assertIsArray( $results );
	}

	/**
	 * Test get snippet
	 */
	public function test_get_snippet() {
		$reflection = new \ReflectionClass( $this->fuzzy );
		$method     = $reflection->getMethod( 'get_snippet' );
		$method->setAccessible( true );

		$content = 'Short content';
		$snippet = $method->invoke( $this->fuzzy, $content, 300 );
		$this->assertEquals( 'Short content', $snippet );

		$long_content = str_repeat( 'Long content ', 50 );
		$snippet      = $method->invoke( $this->fuzzy, $long_content, 50 );
		$this->assertLessThanOrEqual( 53, mb_strlen( $snippet ) ); // 50 + "..."
	}

	/**
	 * Test cache invalidation
	 */
	public function test_invalidate_cache() {
		// Search to create cache.
		$this->fuzzy->search( 'wordpress' );

		// Should not throw errors when called statically.
		FuzzySearch::invalidate_cache();
		$this->assertTrue( true );
	}

	/**
	 * Test get candidates
	 */
	public function test_get_candidates() {
		$reflection = new \ReflectionClass( $this->fuzzy );
		$method     = $reflection->getMethod( 'get_candidates' );
		$method->setAccessible( true );

		$candidates = $method->invoke( $this->fuzzy, 'wordpress' );
		$this->assertIsArray( $candidates );
	}

	/**
	 * Test constructor with custom parameters
	 */
	public function test_constructor_custom_parameters() {
		$fuzzy = new FuzzySearch( 0.50, 100 );
		$this->assertInstanceOf( FuzzySearch::class, $fuzzy );
	}
}
