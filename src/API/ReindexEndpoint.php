<?php
/**
 * REST API endpoint for reindexing posts in batches
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\API;

use Ihumbak\SemanticSearch\Database\EmbeddingsRepository;
use Ihumbak\SemanticSearch\Database\Schema;
use Ihumbak\SemanticSearch\Indexing\Indexer;
use Ihumbak\SemanticSearch\OpenAI\Client;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * ReindexEndpoint class
 */
class ReindexEndpoint {

	/**
	 * Namespace for the REST API
	 *
	 * @var string
	 */
	private string $namespace = 'semantic-search/v1';

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
			'/reindex/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_reindex_status' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		register_rest_route(
			$this->namespace,
			'/reindex/batch',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'process_batch' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args'                => array(
					'offset'        => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
						'description'       => 'Offset for batch processing',
					),
					'batch_size'    => array(
						'required'          => false,
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
						'description'       => 'Number of posts to process in this batch',
					),
					'force_reindex' => array(
						'required'          => false,
						'type'              => 'boolean',
						'default'           => true,
						'description'       => 'Force reindexing even if content hasn\'t changed',
					),
				),
			)
		);
	}

	/**
	 * Get reindex status
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_reindex_status( WP_REST_Request $request ): WP_REST_Response {
		$args = array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		);

		$query = new \WP_Query( $args );

		$schema     = new Schema();
		$repository = new EmbeddingsRepository( $schema );
		$client     = new Client();
		$indexer    = new Indexer( $client, $repository );
		$stats      = $indexer->get_stats();

		return rest_ensure_response(
			array(
				'total_posts'      => $query->found_posts,
				'indexed_posts'    => $stats['indexed_posts'],
				'posts_need_index' => $stats['posts_need_index'],
			)
		);
	}

	/**
	 * Process a batch of posts
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function process_batch( WP_REST_Request $request ) {
		$offset        = $request->get_param( 'offset' );
		$batch_size    = $request->get_param( 'batch_size' );
		$force_reindex = $request->get_param( 'force_reindex' );

		$args = array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		);

		$query = new \WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return rest_ensure_response(
				array(
					'success'       => 0,
					'failed'        => 0,
					'processed'     => 0,
					'has_more'      => false,
					'next_offset'   => $offset,
					'total_posts'   => 0,
					'message'       => __( 'No posts to index', 'ihumbak-semantic-search' ),
				)
			);
		}

		$schema     = new Schema();
		$repository = new EmbeddingsRepository( $schema );
		$client     = new Client();
		$indexer    = new Indexer( $client, $repository );

		try {
			$result = $indexer->index_posts( $query->posts, $force_reindex );

			$has_more    = count( $query->posts ) === $batch_size;
			$next_offset = $offset + $batch_size;

			return rest_ensure_response(
				array(
					'success'     => $result['success'],
					'failed'      => $result['failed'],
					'processed'   => count( $query->posts ),
					'has_more'    => $has_more,
					'next_offset' => $next_offset,
					'total_posts' => $query->found_posts,
					'message'     => sprintf(
						// translators: %1$d: Number of successfully indexed posts, %2$d: Number of failed posts.
						__( 'Indexed %1$d posts, %2$d failed', 'ihumbak-semantic-search' ),
						$result['success'],
						$result['failed']
					),
				)
			);
		} catch ( \Exception $e ) {
			error_log( 'Reindex batch error: ' . $e->getMessage() );
			return new WP_Error(
				'reindex_error',
				__( 'An error occurred while reindexing. Please try again later.', 'ihumbak-semantic-search' ),
				array( 'status' => 500 )
			);
		}
	}
}
