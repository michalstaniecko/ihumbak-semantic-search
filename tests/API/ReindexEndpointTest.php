<?php
/**
 * Tests for ReindexEndpoint class
 *
 * @package Ihumbak\SemanticSearch\Tests
 */

namespace Ihumbak\SemanticSearch\Tests\API;

use Ihumbak\SemanticSearch\API\ReindexEndpoint;
use Ihumbak\SemanticSearch\Database\Migrator;
use Ihumbak\SemanticSearch\Database\Schema;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * ReindexEndpoint test case.
 */
class ReindexEndpointTest extends WP_UnitTestCase {

	/**
	 * Schema instance
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * ReindexEndpoint instance
	 *
	 * @var ReindexEndpoint
	 */
	private ReindexEndpoint $endpoint;

	/**
	 * Set up before each test
	 */
	public function setUp(): void {
		parent::setUp();

		$this->schema = new Schema();
		$migrator     = new Migrator( $this->schema );
		$migrator->migrate();

		$this->endpoint = new ReindexEndpoint();
		$this->endpoint->register_routes();
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
	 * Test routes are registered
	 */
	public function test_routes_are_registered(): void {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/semantic-search/v1/reindex/status', $routes );
		$this->assertArrayHasKey( '/semantic-search/v1/reindex/batch', $routes );
	}

	/**
	 * Test get_reindex_status requires authentication
	 */
	public function test_get_reindex_status_requires_authentication(): void {
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'GET', '/semantic-search/v1/reindex/status' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test get_reindex_status returns correct data
	 */
	public function test_get_reindex_status_returns_data(): void {
		// Create admin user.
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		// Create some test posts.
		$this->factory->post->create_many( 5, array( 'post_status' => 'publish' ) );

		$request  = new WP_REST_Request( 'GET', '/semantic-search/v1/reindex/status' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'total_posts', $data );
		$this->assertArrayHasKey( 'indexed_posts', $data );
		$this->assertArrayHasKey( 'posts_need_index', $data );
	}

	/**
	 * Test process_batch requires authentication
	 */
	public function test_process_batch_requires_authentication(): void {
		wp_set_current_user( 0 );

		$request = new WP_REST_Request( 'POST', '/semantic-search/v1/reindex/batch' );
		$request->set_param( 'offset', 0 );
		$request->set_param( 'batch_size', 10 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test process_batch with no posts
	 */
	public function test_process_batch_with_no_posts(): void {
		// Create admin user.
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$request = new WP_REST_Request( 'POST', '/semantic-search/v1/reindex/batch' );
		$request->set_param( 'offset', 0 );
		$request->set_param( 'batch_size', 10 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'has_more', $data );
		$this->assertFalse( $data['has_more'] );
		$this->assertEquals( 0, $data['processed'] );
	}

	/**
	 * Test process_batch returns correct structure
	 */
	public function test_process_batch_returns_correct_structure(): void {
		// Create admin user.
		$admin_id = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		// Create some test posts.
		$this->factory->post->create_many( 5, array( 'post_status' => 'publish' ) );

		$request = new WP_REST_Request( 'POST', '/semantic-search/v1/reindex/batch' );
		$request->set_param( 'offset', 0 );
		$request->set_param( 'batch_size', 3 );

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'success', $data );
		$this->assertArrayHasKey( 'failed', $data );
		$this->assertArrayHasKey( 'processed', $data );
		$this->assertArrayHasKey( 'has_more', $data );
		$this->assertArrayHasKey( 'next_offset', $data );
		$this->assertArrayHasKey( 'total_posts', $data );
		$this->assertArrayHasKey( 'message', $data );
	}
}
