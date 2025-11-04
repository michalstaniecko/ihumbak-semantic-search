<?php
/**
 * Semantic search using embeddings
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\Search;

use Ihumbak\SemanticSearch\Database\EmbeddingsRepository;
use Ihumbak\SemanticSearch\OpenAI\Client;
use Ihumbak\SemanticSearch\OpenAI\EmbeddingUtils;

/**
 * SemanticSearch class
 */
class SemanticSearch {

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
	 * Rerank posts by semantic similarity
	 *
	 * @param string $query    Search query.
	 * @param array  $post_ids Array of post IDs to rerank.
	 * @param int    $limit    Number of results to return.
	 * @return array Array of post IDs with similarity scores
	 */
	public function rerank( string $query, array $post_ids, int $limit = 10 ): array {
		if ( empty( $query ) || empty( $post_ids ) ) {
			return array();
		}

		// Generate query embedding.
		$query_embedding = $this->client->generate_embedding( $query );

		if ( false === $query_embedding ) {
			return array();
		}

		$scored = array();

		foreach ( $post_ids as $post_id ) {
			// Get post embedding.
			$embedding_data = $this->repository->get_embedding_by_post_and_field( $post_id, 'post_content' );

			if ( ! $embedding_data ) {
				continue;
			}

			// Decode embedding vector.
			$post_embedding = json_decode( $embedding_data->embedding, true );

			if ( empty( $post_embedding ) ) {
				continue;
			}

			// Calculate similarity.
			$similarity = EmbeddingUtils::cosine_similarity( $query_embedding, $post_embedding );

			$scored[] = array(
				'post_id'    => $post_id,
				'similarity' => $similarity,
			);
		}

		// Sort by similarity (descending).
		usort(
			$scored,
			function ( $a, $b ) {
				return $b['similarity'] <=> $a['similarity'];
			}
		);

		// Return top results.
		return array_slice( $scored, 0, $limit );
	}

	/**
	 * Pure semantic search (no keyword prefiltering)
	 *
	 * @param string $query Search query.
	 * @param array  $args  Additional arguments.
	 * @return array Array of post IDs with similarity scores
	 */
	public function search( string $query, array $args = array() ): array {
		if ( empty( $query ) ) {
			return array();
		}

		$defaults = array(
			'limit' => 10,
		);

		$args = wp_parse_args( $args, $defaults );

		// Generate query embedding.
		$query_embedding = $this->client->generate_embedding( $query );

		if ( false === $query_embedding ) {
			return array();
		}

		// Get all indexed post IDs.
		$post_ids = $this->get_all_indexed_post_ids();

		if ( empty( $post_ids ) ) {
			return array();
		}

		return $this->rerank( $query, $post_ids, $args['limit'] );
	}

	/**
	 * Get all indexed post IDs
	 *
	 * @return array
	 */
	private function get_all_indexed_post_ids(): array {
		global $wpdb;

		$table = $this->repository->get_embeddings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_col(
			"SELECT DISTINCT post_id FROM {$table} WHERE field_type = 'post_content'"
		);

		return array_map( 'intval', $results );
	}

	/**
	 * Get embeddings table name
	 *
	 * @return string
	 */
	private function get_embeddings_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'semantic_embeddings';
	}
}
