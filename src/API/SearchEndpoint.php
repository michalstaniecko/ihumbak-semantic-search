<?php
/**
 * REST API endpoint for search
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\API;

use Ihumbak\SemanticSearch\Cache\CacheManager;
use Ihumbak\SemanticSearch\Search\HybridSearch;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * SearchEndpoint class
 */
class SearchEndpoint {

	/**
	 * Namespace for the REST API
	 *
	 * @var string
	 */
	private string $namespace = 'semantic-search/v1';

	/**
	 * HybridSearch instance
	 *
	 * @var HybridSearch
	 */
	private HybridSearch $search;

	/**
	 * CacheManager instance
	 *
	 * @var CacheManager
	 */
	private CacheManager $cache;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->search = new HybridSearch();
		$this->cache  = new CacheManager();
	}

	/**
	 * Register REST API routes
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_search' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'q' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => 'Search query',
					),
					'limit' => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
						'description'       => 'Number of results to return',
					),
					'post_type' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'post,page',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => 'Comma-separated list of post types',
					),
					'mode' => array(
						'required'          => false,
						'type'              => 'string',
						'default'           => 'hybrid',
						'enum'              => array( 'hybrid', 'keyword', 'semantic' ),
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => 'Search mode',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/stats',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_stats' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Handle search request
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_search( WP_REST_Request $request ) {
		$query     = $request->get_param( 'q' );
		$limit     = $request->get_param( 'limit' );
		$post_type = $request->get_param( 'post_type' );
		$mode      = $request->get_param( 'mode' );

		if ( empty( $query ) ) {
			return new WP_Error(
				'empty_query',
				__( 'Search query cannot be empty', 'ihumbak-semantic-search' ),
				array( 'status' => 400 )
			);
		}

		// Parse post types.
		$post_types = array_map( 'trim', explode( ',', $post_type ) );

		$args = array(
			'limit'       => $limit,
			'post_type'   => $post_types,
			'post_status' => 'publish',
		);

		// Check cache.
		$cache_key = array(
			'query' => $query,
			'mode'  => $mode,
			'args'  => $args,
		);

		$cached_results = $this->cache->get_search_results( $query, $cache_key );

		if ( false !== $cached_results ) {
			return rest_ensure_response(
				array(
					'results' => $cached_results,
					'cached'  => true,
					'count'   => count( $cached_results ),
				)
			);
		}

		// Perform search based on mode.
		if ( 'hybrid' === $mode ) {
			$results = $this->search->search( $query, $args );
		} else {
			$results = $this->search->search_with_mode( $query, array_merge( $args, array( 'mode' => $mode ) ) );
		}

		// Cache results.
		$this->cache->set_search_results( $query, $cache_key, $results );

		return rest_ensure_response(
			array(
				'results' => $results,
				'cached'  => false,
				'count'   => count( $results ),
			)
		);
	}

	/**
	 * Handle stats request
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_stats( WP_REST_Request $request ): WP_REST_Response {
		$cache_stats = $this->cache->get_stats();

		return rest_ensure_response(
			array(
				'cache' => $cache_stats,
			)
		);
	}
}
