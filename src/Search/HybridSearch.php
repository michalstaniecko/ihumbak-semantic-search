<?php
/**
 * Hybrid search combining keyword and semantic search
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\Search;

use Ihumbak\SemanticSearch\Database\EmbeddingsRepository;
use Ihumbak\SemanticSearch\Database\Schema;
use Ihumbak\SemanticSearch\OpenAI\Client;

/**
 * HybridSearch class
 */
class HybridSearch {

	/**
	 * Keyword search instance
	 *
	 * @var KeywordSearch
	 */
	private KeywordSearch $keyword_search;

	/**
	 * Semantic search instance
	 *
	 * @var SemanticSearch
	 */
	private SemanticSearch $semantic_search;

	/**
	 * Fuzzy search instance
	 *
	 * @var FuzzySearch|null
	 */
	private ?FuzzySearch $fuzzy_search = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->keyword_search = new KeywordSearch();

		$schema     = new Schema();
		$repository = new EmbeddingsRepository( $schema );
		$client     = new Client();

		$this->semantic_search = new SemanticSearch( $client, $repository );
	}

	/**
	 * Hybrid search
	 *
	 * @param string $query Search query.
	 * @param array  $args  Additional arguments.
	 * @return array Search results with post objects and scores
	 */
	public function search( string $query, array $args = array() ): array {
		if ( empty( $query ) ) {
			return array();
		}

		$defaults = array(
			'limit'           => 10,
			'keyword_limit'   => 50,
			'post_type'       => array( 'post', 'page' ),
			'post_status'     => 'publish',
			'semantic_weight' => 0.7,
			'keyword_weight'  => 0.3,
		);

		$args = wp_parse_args( $args, $defaults );

		// Step 1: Get keyword search results.
		$keyword_results = $this->keyword_search->search(
			$query,
			array(
				'post_type'   => $args['post_type'],
				'post_status' => $args['post_status'],
				'limit'       => $args['keyword_limit'],
			)
		);

		if ( empty( $keyword_results ) ) {
			// Fallback to pure semantic search.
			return $this->semantic_only_search( $query, $args );
		}

		// Extract post IDs.
		$post_ids = array_column( $keyword_results, 'post_id' );

		// Step 2: Rerank using semantic similarity.
		$semantic_results = $this->semantic_search->rerank( $query, $post_ids, $args['limit'] * 2 );

		if ( empty( $semantic_results ) ) {
			// Return keyword results only.
			return $this->format_results( $keyword_results, $args['limit'] );
		}

		// Step 3: Combine scores.
		$combined = $this->combine_scores(
			$keyword_results,
			$semantic_results,
			$args['semantic_weight'],
			$args['keyword_weight']
		);

		// Step 4: Format and return.
		$results = $this->format_results( $combined, $args['limit'] );

		// Step 5: Fuzzy search fallback if no results.
		if ( empty( $results ) && apply_filters( 'ihumbak_semantic_search_enable_fuzzy', true ) ) {
			$fuzzy_ids = $this->get_fuzzy_search()->search( $query );
			if ( ! empty( $fuzzy_ids ) ) {
				foreach ( $fuzzy_ids as $post_id ) {
					$results[] = array(
						'post_id' => $post_id,
						'score'   => 0.5,
						'type'    => 'fuzzy',
					);
				}
				$results = $this->format_results( $results, $args['limit'] );
			}
		}

		return $results;
	}

	/**
	 * Combine keyword and semantic scores
	 *
	 * @param array $keyword_results  Keyword search results.
	 * @param array $semantic_results Semantic search results.
	 * @param float $semantic_weight  Weight for semantic score.
	 * @param float $keyword_weight   Weight for keyword score.
	 * @return array Combined results
	 */
	private function combine_scores(
		array $keyword_results,
		array $semantic_results,
		float $semantic_weight,
		float $keyword_weight
	): array {
		// Normalize scores.
		$keyword_results  = $this->normalize_scores( $keyword_results, 'relevance' );
		$semantic_results = $this->normalize_scores( $semantic_results, 'similarity' );

		// Create lookup maps.
		$keyword_map  = array_column( $keyword_results, 'relevance', 'post_id' );
		$semantic_map = array_column( $semantic_results, 'similarity', 'post_id' );

		$combined = array();

		// Combine scores for posts in semantic results.
		foreach ( $semantic_results as $result ) {
			$post_id = $result['post_id'];

			$semantic_score = $result['similarity'];
			$keyword_score  = $keyword_map[ $post_id ] ?? 0.0;

			$combined_score = ( $semantic_score * $semantic_weight ) + ( $keyword_score * $keyword_weight );

			$combined[] = array(
				'post_id' => $post_id,
				'score'   => $combined_score,
			);
		}

		// Sort by combined score.
		usort(
			$combined,
			function ( $a, $b ) {
				return $b['score'] <=> $a['score'];
			}
		);

		return $combined;
	}

	/**
	 * Normalize scores to 0-1 range
	 *
	 * @param array  $results   Results array.
	 * @param string $score_key Key of the score field.
	 * @return array Normalized results
	 */
	private function normalize_scores( array $results, string $score_key ): array {
		if ( empty( $results ) ) {
			return array();
		}

		$scores = array_column( $results, $score_key );
		$max    = max( $scores );
		$min    = min( $scores );

		// Avoid division by zero.
		if ( $max === $min ) {
			return array_map(
				function ( $result ) use ( $score_key ) {
					$result[ $score_key ] = 1.0;
					return $result;
				},
				$results
			);
		}

		return array_map(
			function ( $result ) use ( $score_key, $max, $min ) {
				$result[ $score_key ] = ( $result[ $score_key ] - $min ) / ( $max - $min );
				return $result;
			},
			$results
		);
	}

	/**
	 * Format results with post objects
	 *
	 * @param array $results Results array.
	 * @param int   $limit   Number of results to return.
	 * @return array Formatted results
	 */
	private function format_results( array $results, int $limit ): array {
		$results = array_slice( $results, 0, $limit );

		$formatted = array_map(
			function ( $result ) {
				$post = get_post( $result['post_id'] );

				if ( ! $post ) {
					return null;
				}

				$thumbnail_url = null;
				if ( has_post_thumbnail( $post->ID ) ) {
					$thumbnail_url = get_the_post_thumbnail_url( $post->ID, 'medium' );
				}

				return array(
					'post'      => array(
						'ID'             => $post->ID,
						'post_title'     => $post->post_title,
						'post_type'      => $post->post_type,
						'post_date'      => $post->post_date,
						'featured_image' => $thumbnail_url,
					),
					'score'     => $result['score'] ?? $result['relevance'] ?? $result['similarity'] ?? 0,
					'permalink' => get_permalink( $post ),
					'excerpt'   => get_the_excerpt( $post ),
				);
			},
			$results
		);

		// Filter out null values.
		return array_values( array_filter( $formatted ) );
	}

	/**
	 * Semantic-only search fallback
	 *
	 * @param string $query Search query.
	 * @param array  $args  Arguments.
	 * @return array Results
	 */
	private function semantic_only_search( string $query, array $args ): array {
		$semantic_results = $this->semantic_search->search(
			$query,
			array(
				'limit' => $args['limit'],
			)
		);

		$results = $this->format_results( $semantic_results, $args['limit'] );

		// Fuzzy search fallback if no results.
		if ( empty( $results ) && apply_filters( 'ihumbak_semantic_search_enable_fuzzy', true ) ) {
			$fuzzy_ids = $this->get_fuzzy_search()->search( $query );
			if ( ! empty( $fuzzy_ids ) ) {
				foreach ( $fuzzy_ids as $post_id ) {
					$results[] = array(
						'post_id' => $post_id,
						'score'   => 0.5,
						'type'    => 'fuzzy',
					);
				}
				$results = $this->format_results( $results, $args['limit'] );
			}
		}

		return $results;
	}

	/**
	 * Get fuzzy search instance
	 *
	 * @return FuzzySearch Fuzzy search instance
	 */
	protected function get_fuzzy_search(): FuzzySearch {
		if ( null === $this->fuzzy_search ) {
			$this->fuzzy_search = new FuzzySearch();
		}

		return $this->fuzzy_search;
	}

	/**
	 * Get search mode based on configuration
	 *
	 * @return string Search mode: hybrid, keyword, or semantic
	 */
	public function get_search_mode(): string {
		return get_option( 'ihumbak_semantic_search_mode', 'hybrid' );
	}

	/**
	 * Search with configurable mode
	 *
	 * @param string $query Search query.
	 * @param array  $args  Additional arguments.
	 * @return array Search results
	 */
	public function search_with_mode( string $query, array $args = array() ): array {
		$mode = $this->get_search_mode();

		switch ( $mode ) {
			case 'keyword':
				$results = $this->keyword_search->search( $query, $args );
				return $this->format_results( $results, $args['limit'] ?? 10 );

			case 'semantic':
				return $this->semantic_only_search( $query, $args );

			case 'hybrid':
			default:
				return $this->search( $query, $args );
		}
	}
}
