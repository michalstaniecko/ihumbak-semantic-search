<?php
/**
 * Tests for Indexer class
 *
 * @package Ihumbak\SemanticSearch\Tests
 */

namespace Ihumbak\SemanticSearch\Tests\Indexing;

use Ihumbak\SemanticSearch\Database\EmbeddingsRepository;
use Ihumbak\SemanticSearch\Database\Migrator;
use Ihumbak\SemanticSearch\Database\Schema;
use Ihumbak\SemanticSearch\Indexing\Indexer;
use Ihumbak\SemanticSearch\OpenAI\Client;
use WP_UnitTestCase;

/**
 * Indexer test case.
 */
class IndexerTest extends WP_UnitTestCase {

	/**
	 * Schema instance
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * Repository instance
	 *
	 * @var EmbeddingsRepository
	 */
	private EmbeddingsRepository $repository;

	/**
	 * Indexer instance
	 *
	 * @var Indexer
	 */
	private Indexer $indexer;

	/**
	 * Set up before each test
	 */
	public function setUp(): void {
		parent::setUp();

		$this->schema     = new Schema();
		$migrator         = new Migrator( $this->schema );
		$migrator->migrate();

		$this->repository = new EmbeddingsRepository( $this->schema );

		// Use a mock client that doesn't make real API calls.
		$client = $this->createMock( Client::class );
		$client->method( 'generate_embedding' )->willReturn( array( 0.1, 0.2, 0.3 ) );

		$this->indexer = new Indexer( $client, $this->repository );
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
	 * Test get stats
	 */
	public function test_get_stats() {
		$stats = $this->indexer->get_stats();

		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'total_posts', $stats );
		$this->assertArrayHasKey( 'indexed_posts', $stats );
		$this->assertArrayHasKey( 'total_embeddings', $stats );
		$this->assertArrayHasKey( 'posts_need_index', $stats );
	}

	/**
	 * Test index post with unpublished post
	 */
	public function test_index_post_unpublished() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'draft' ) );
		$result  = $this->indexer->index_post( $post_id );
		$this->assertFalse( $result );
	}

	/**
	 * Test delete post index
	 */
	public function test_delete_post_index() {
		$post_id = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		// Index the post.
		$this->indexer->index_post( $post_id );
		$this->assertTrue( $this->repository->has_embeddings( $post_id ) );

		// Delete index.
		$this->indexer->delete_post_index( $post_id );
		$this->assertFalse( $this->repository->has_embeddings( $post_id ) );
	}
}
