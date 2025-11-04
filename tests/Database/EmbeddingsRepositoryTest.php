<?php
/**
 * Tests for EmbeddingsRepository class
 *
 * @package Ihumbak\SemanticSearch\Tests
 */

namespace Ihumbak\SemanticSearch\Tests\Database;

use Ihumbak\SemanticSearch\Database\EmbeddingsRepository;
use Ihumbak\SemanticSearch\Database\Migrator;
use Ihumbak\SemanticSearch\Database\Schema;
use WP_UnitTestCase;

/**
 * EmbeddingsRepository test case.
 */
class EmbeddingsRepositoryTest extends WP_UnitTestCase {

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
	 * Test post ID
	 *
	 * @var int
	 */
	private int $post_id;

	/**
	 * Set up before each test
	 */
	public function setUp(): void {
		parent::setUp();

		$this->schema     = new Schema();
		$migrator         = new Migrator( $this->schema );
		$migrator->migrate();

		$this->repository = new EmbeddingsRepository( $this->schema );

		// Create a test post.
		$this->post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'Test content for semantic search',
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
	 * Test saving an embedding
	 */
	public function test_save_embedding() {
		$embedding     = array( 0.1, 0.2, 0.3, 0.4, 0.5 );
		$content_hash = md5( 'test content' );

		$result = $this->repository->save_embedding( $this->post_id, $embedding, $content_hash );
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	/**
	 * Test updating an existing embedding
	 */
	public function test_update_embedding() {
		$embedding1    = array( 0.1, 0.2, 0.3 );
		$content_hash1 = md5( 'test content 1' );

		$id1 = $this->repository->save_embedding( $this->post_id, $embedding1, $content_hash1 );

		// Update with new embedding.
		$embedding2    = array( 0.4, 0.5, 0.6 );
		$content_hash2 = md5( 'test content 2' );

		$id2 = $this->repository->save_embedding( $this->post_id, $embedding2, $content_hash2 );

		// Should return same ID (update, not insert).
		$this->assertEquals( $id1, $id2 );

		$stored = $this->repository->get_embedding_by_post_and_field( $this->post_id );
		$this->assertEquals( $content_hash2, $stored->content_hash );
	}

	/**
	 * Test getting embedding by post and field
	 */
	public function test_get_embedding_by_post_and_field() {
		$embedding    = array( 0.1, 0.2, 0.3 );
		$content_hash = md5( 'test' );

		$this->repository->save_embedding( $this->post_id, $embedding, $content_hash );

		$stored = $this->repository->get_embedding_by_post_and_field( $this->post_id );
		$this->assertIsObject( $stored );
		$this->assertEquals( $this->post_id, $stored->post_id );
		$this->assertEquals( $content_hash, $stored->content_hash );
	}

	/**
	 * Test getting all embeddings for a post
	 */
	public function test_get_embeddings_by_post() {
		$embedding1 = array( 0.1, 0.2, 0.3 );
		$embedding2 = array( 0.4, 0.5, 0.6 );

		$this->repository->save_embedding( $this->post_id, $embedding1, md5( 'test1' ), 'post_content' );
		$this->repository->save_embedding( $this->post_id, $embedding2, md5( 'test2' ), 'acf_field' );

		$embeddings = $this->repository->get_embeddings_by_post( $this->post_id );
		$this->assertCount( 2, $embeddings );
	}

	/**
	 * Test deleting embeddings by post
	 */
	public function test_delete_embeddings_by_post() {
		$this->repository->save_embedding( $this->post_id, array( 0.1, 0.2 ), md5( 'test' ) );

		$result = $this->repository->delete_embeddings_by_post( $this->post_id );
		$this->assertTrue( $result );

		$embeddings = $this->repository->get_embeddings_by_post( $this->post_id );
		$this->assertCount( 0, $embeddings );
	}

	/**
	 * Test checking if post has embeddings
	 */
	public function test_has_embeddings() {
		$this->assertFalse( $this->repository->has_embeddings( $this->post_id ) );

		$this->repository->save_embedding( $this->post_id, array( 0.1, 0.2 ), md5( 'test' ) );

		$this->assertTrue( $this->repository->has_embeddings( $this->post_id ) );
	}

	/**
	 * Test content change detection
	 */
	public function test_content_has_changed() {
		$hash1 = md5( 'content 1' );
		$hash2 = md5( 'content 2' );

		// No existing embedding.
		$this->assertTrue( $this->repository->content_has_changed( $this->post_id, $hash1 ) );

		$this->repository->save_embedding( $this->post_id, array( 0.1 ), $hash1 );

		// Same hash.
		$this->assertFalse( $this->repository->content_has_changed( $this->post_id, $hash1 ) );

		// Different hash.
		$this->assertTrue( $this->repository->content_has_changed( $this->post_id, $hash2 ) );
	}

	/**
	 * Test getting indexed posts count
	 */
	public function test_get_indexed_posts_count() {
		$this->assertEquals( 0, $this->repository->get_indexed_posts_count() );

		$post1 = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$post2 = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		$this->repository->save_embedding( $post1, array( 0.1 ), md5( 'test1' ) );
		$this->repository->save_embedding( $post2, array( 0.2 ), md5( 'test2' ) );

		$this->assertEquals( 2, $this->repository->get_indexed_posts_count() );
	}

	/**
	 * Test getting posts needing indexing
	 */
	public function test_get_posts_needing_indexing() {
		$post1 = $this->factory->post->create( array( 'post_status' => 'publish' ) );
		$post2 = $this->factory->post->create( array( 'post_status' => 'publish' ) );

		// Initially both need indexing.
		$needs_indexing = $this->repository->get_posts_needing_indexing();
		$this->assertGreaterThanOrEqual( 2, count( $needs_indexing ) );

		// Index one post.
		$this->repository->save_embedding( $post1, array( 0.1 ), md5( 'test' ) );

		// Now only unindexed posts should be returned.
		$needs_indexing = $this->repository->get_posts_needing_indexing();
		$this->assertContains( $post2, $needs_indexing );
	}
}
