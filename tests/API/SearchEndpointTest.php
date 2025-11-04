<?php
/**
 * Tests for SearchEndpoint class
 *
 * @package Ihumbak\SemanticSearch\Tests
 */

namespace Ihumbak\SemanticSearch\Tests\API;

use Ihumbak\SemanticSearch\API\SearchEndpoint;
use Ihumbak\SemanticSearch\Database\Migrator;
use Ihumbak\SemanticSearch\Database\Schema;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * SearchEndpoint test case.
 */
class SearchEndpointTest extends WP_UnitTestCase {

	/**
	 * Schema instance
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * SearchEndpoint instance
	 *
	 * @var SearchEndpoint
	 */
	private SearchEndpoint $endpoint;

	/**
	 * Set up before each test
	 */
	public function setUp(): void {
		parent::setUp();

		$this->schema = new Schema();
		$migrator     = new Migrator( $this->schema );
		$migrator->migrate();

		$this->endpoint = new SearchEndpoint();
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
	 * Test handle_search with empty query
	 */
	public function test_handle_search_empty_query() {
		$request = new WP_REST_Request( 'GET', '/semantic-search/v1/search' );
		$request->set_param( 'q', '' );

		$response = $this->endpoint->handle_search( $request );

		$this->assertInstanceOf( 'WP_Error', $response );
		$this->assertEquals( 'empty_query', $response->get_error_code() );
	}

	/**
	 * Test handle_search with non-existent word returns empty results without error
	 */
	public function test_handle_search_nonexistent_word() {
		$request = new WP_REST_Request( 'GET', '/semantic-search/v1/search' );
		$request->set_param( 'q', 'nonexistentword12345' );
		$request->set_param( 'limit', 10 );
		$request->set_param( 'post_type', 'post,page' );
		$request->set_param( 'mode', 'hybrid' );

		$response = $this->endpoint->handle_search( $request );

		// Should not be a WP_Error.
		$this->assertNotInstanceOf( 'WP_Error', $response );

		// Get response data.
		$data = $response->get_data();

		// Should have results key.
		$this->assertArrayHasKey( 'results', $data );

		// Results should be an array.
		$this->assertIsArray( $data['results'] );

		// Count should be 0 for non-existent word.
		$this->assertEquals( 0, $data['count'] );
	}

	/**
	 * Test handle_search with keyword mode
	 */
	public function test_handle_search_keyword_mode() {
		// Create a test post.
		$post_id = $this->factory->post->create(
			array(
				'post_title'   => 'Test Post',
				'post_content' => 'This is a test content',
				'post_status'  => 'publish',
			)
		);

		$request = new WP_REST_Request( 'GET', '/semantic-search/v1/search' );
		$request->set_param( 'q', 'test' );
		$request->set_param( 'limit', 10 );
		$request->set_param( 'post_type', 'post' );
		$request->set_param( 'mode', 'keyword' );

		$response = $this->endpoint->handle_search( $request );

		// Should not be a WP_Error.
		$this->assertNotInstanceOf( 'WP_Error', $response );

		// Get response data.
		$data = $response->get_data();

		// Should have results key.
		$this->assertArrayHasKey( 'results', $data );

		// Results should be an array.
		$this->assertIsArray( $data['results'] );
	}

	/**
	 * Test handle_search returns proper structure
	 */
	public function test_handle_search_response_structure() {
		$request = new WP_REST_Request( 'GET', '/semantic-search/v1/search' );
		$request->set_param( 'q', 'test query' );
		$request->set_param( 'limit', 10 );
		$request->set_param( 'post_type', 'post,page' );
		$request->set_param( 'mode', 'hybrid' );

		$response = $this->endpoint->handle_search( $request );

		// Should not be a WP_Error.
		$this->assertNotInstanceOf( 'WP_Error', $response );

		// Get response data.
		$data = $response->get_data();

		// Should have required keys.
		$this->assertArrayHasKey( 'results', $data );
		$this->assertArrayHasKey( 'cached', $data );
		$this->assertArrayHasKey( 'count', $data );

		// Validate types.
		$this->assertIsArray( $data['results'] );
		$this->assertIsBool( $data['cached'] );
		$this->assertIsInt( $data['count'] );
	}
}
