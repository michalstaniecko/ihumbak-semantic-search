<?php
/**
 * Content indexer for posts
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\Indexing;

use Ihumbak\SemanticSearch\Database\EmbeddingsRepository;
use Ihumbak\SemanticSearch\OpenAI\Client;

/**
 * Indexer class
 */
class Indexer {

	/**
	 * OpenAI Client
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * Embeddings Repository
	 *
	 * @var EmbeddingsRepository
	 */
	private EmbeddingsRepository $repository;

	/**
	 * Constructor
	 *
	 * @param Client               $client     OpenAI client.
	 * @param EmbeddingsRepository $repository Embeddings repository.
	 */
	public function __construct( Client $client, EmbeddingsRepository $repository ) {
		$this->client     = $client;
		$this->repository = $repository;
	}

	/**
	 * Index a single post
	 *
	 * @param int  $post_id     Post ID.
	 * @param bool $force_reindex Force reindexing even if content hasn't changed.
	 * @return bool True on success, false on failure
	 */
	public function index_post( int $post_id, bool $force_reindex = false ): bool {
		$post = get_post( $post_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return false;
		}

		// Get post content.
		$content = $this->get_post_content( $post );

		if ( empty( $content ) ) {
			return false;
		}

		// Calculate content hash.
		$content_hash = md5( $content );

		// Check if content has changed.
		if ( ! $force_reindex && ! $this->repository->content_has_changed( $post_id, $content_hash ) ) {
			return true; // Already indexed with same content.
		}

		// Generate embedding.
		$embedding = $this->client->generate_embedding( $content );

		if ( false === $embedding ) {
			return false;
		}

		// Save embedding.
		$result = $this->repository->save_embedding( $post_id, $embedding, $content_hash, 'post_content' );

		return false !== $result;
	}

	/**
	 * Index multiple posts
	 *
	 * @param array $post_ids     Array of post IDs.
	 * @param bool  $force_reindex Force reindexing.
	 * @return array Array with 'success' and 'failed' counts
	 */
	public function index_posts( array $post_ids, bool $force_reindex = false ): array {
		$success = 0;
		$failed  = 0;

		foreach ( $post_ids as $post_id ) {
			if ( $this->index_post( $post_id, $force_reindex ) ) {
				++$success;
			} else {
				++$failed;
			}

			// Small delay to avoid rate limiting.
			usleep( 100000 ); // 100ms.
		}

		return array(
			'success' => $success,
			'failed'  => $failed,
		);
	}

	/**
	 * Reindex all published posts
	 *
	 * @param int  $batch_size Batch size.
	 * @param bool $force_reindex Force reindexing.
	 * @return int Number of posts indexed
	 */
	public function reindex_all_posts( int $batch_size = 100, bool $force_reindex = false ): int {
		$args = array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);

		$query = new \WP_Query( $args );
		$count = 0;

		if ( $query->have_posts() ) {
			$result = $this->index_posts( $query->posts, $force_reindex );
			$count  = $result['success'];
		}

		return $count;
	}

	/**
	 * Get post content for indexing
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function get_post_content( \WP_Post $post ): string {
		$content = $post->post_title . ' ' . $post->post_content;

		// Strip HTML tags and shortcodes.
		$content = wp_strip_all_tags( strip_shortcodes( $content ) );

		// Normalize whitespace.
		$content = preg_replace( '/\s+/', ' ', $content );

		// Trim and limit length (OpenAI has token limits).
		$content = trim( $content );
		$content = mb_substr( $content, 0, 8000 ); // Approx 2000 tokens.

		return $content;
	}

	/**
	 * Delete index for a post
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function delete_post_index( int $post_id ): bool {
		return $this->repository->delete_embeddings_by_post( $post_id );
	}

	/**
	 * Index ACF field for a post
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $field_name ACF field name.
	 * @return bool
	 */
	public function index_acf_field( int $post_id, string $field_name ): bool {
		if ( ! function_exists( 'get_field' ) ) {
			return false; // ACF not active.
		}

		$field_value = get_field( $field_name, $post_id );

		if ( empty( $field_value ) ) {
			return false;
		}

		// Convert field value to string.
		if ( is_array( $field_value ) ) {
			$field_value = implode( ' ', array_filter( $field_value, 'is_string' ) );
		} else {
			$field_value = (string) $field_value;
		}

		$field_value = wp_strip_all_tags( $field_value );

		if ( empty( $field_value ) ) {
			return false;
		}

		$content_hash = md5( $field_value );

		// Check if changed.
		if ( ! $this->repository->content_has_changed( $post_id, $content_hash, $field_name ) ) {
			return true;
		}

		// Generate embedding.
		$embedding = $this->client->generate_embedding( $field_value );

		if ( false === $embedding ) {
			return false;
		}

		// Save embedding.
		$result = $this->repository->save_embedding( $post_id, $embedding, $content_hash, $field_name );

		return false !== $result;
	}

	/**
	 * Get indexing stats
	 *
	 * @return array
	 */
	public function get_stats(): array {
		$total_posts = wp_count_posts( 'post' )->publish + wp_count_posts( 'page' )->publish;

		return array(
			'total_posts'       => $total_posts,
			'indexed_posts'     => $this->repository->get_indexed_posts_count(),
			'total_embeddings'  => $this->repository->get_total_embeddings_count(),
			'posts_need_index'  => count( $this->repository->get_posts_needing_indexing( 1000 ) ),
		);
	}
}
