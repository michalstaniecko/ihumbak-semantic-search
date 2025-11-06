<?php
/**
 * Fuzzy search with typo tolerance
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\Search;

use WP_Query;

/**
 * FuzzySearch class - implements fuzzy matching for typo tolerance
 */
class FuzzySearch {

	/**
	 * Minimum similarity threshold (0-1)
	 *
	 * @var float
	 */
	private float $min_threshold;

	/**
	 * Maximum number of candidate posts to check
	 *
	 * @var int
	 */
	private int $candidate_limit;

	/**
	 * Optional semantic search instance for reranking
	 *
	 * @var SemanticSearch|null
	 */
	private ?SemanticSearch $semantic_search;

	/**
	 * Cache expiration time in seconds
	 *
	 * @var int
	 */
	private const CACHE_EXPIRATION = 3600; // 1 hour

	/**
	 * Maximum number of search words to use for candidate filtering
	 *
	 * @var int
	 */
	private const MAX_SEARCH_WORDS = 3;

	/**
	 * Constructor
	 *
	 * @param float               $min_threshold    Minimum similarity threshold (default: 0.35).
	 * @param int                 $candidate_limit  Maximum candidates to check (default: 200).
	 * @param SemanticSearch|null $semantic_search  Optional semantic search instance for reranking.
	 */
	public function __construct( float $min_threshold = 0.35, int $candidate_limit = 200, ?SemanticSearch $semantic_search = null ) {
		$this->min_threshold   = $min_threshold;
		$this->candidate_limit = $candidate_limit;
		$this->semantic_search = $semantic_search;
	}

	/**
	 * Search for posts using fuzzy matching
	 *
	 * @param string $search_query          Search query string.
	 * @param bool   $use_semantic_reranking Whether to use semantic reranking (default: true if SemanticSearch available).
	 * @return array Array of post IDs sorted by similarity score
	 */
	public function search( string $search_query, bool $use_semantic_reranking = true ): array {
		if ( empty( $search_query ) ) {
			return array();
		}

		// Apply filter to allow disabling semantic reranking.
		$use_semantic_reranking = apply_filters( 'ihumbak_fuzzy_search_use_semantic_reranking', $use_semantic_reranking );

		// Check cache first.
		$cache_key = 'ihumbak_fuzzy_search_' . md5( $search_query . $this->min_threshold . (int) $use_semantic_reranking );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		// Normalize the search query.
		$normalized_query = $this->normalize( $search_query );

		// Get candidate posts.
		$candidates = $this->get_candidates( $search_query );

		if ( empty( $candidates ) ) {
			return array();
		}

		$results = array();

		// Calculate similarity for each candidate.
		foreach ( $candidates as $post ) {
			$post_text = $this->normalize( $post->post_title . ' ' . $post->post_content );

			// Calculate base similarity.
			$similarity = $this->calculate_similarity( $normalized_query, $post_text );

			// Add word overlap bonus.
			$word_bonus = $this->calculate_word_overlap( $normalized_query, $post_text );
			$similarity = min( 1.0, $similarity + ( $word_bonus * 0.2 ) );

			// Filter by threshold.
			if ( $similarity >= $this->min_threshold ) {
				$results[] = array(
					'post_id'    => $post->ID,
					'similarity' => $similarity,
				);
			}
		}

		// Sort by similarity (highest first).
		usort(
			$results,
			function ( $a, $b ) {
				return $b['similarity'] <=> $a['similarity'];
			}
		);

		// Extract post IDs.
		$post_ids = array_column( $results, 'post_id' );

		// Apply semantic reranking if enabled and SemanticSearch is available.
		if ( $use_semantic_reranking && null !== $this->semantic_search && ! empty( $post_ids ) ) {
			$post_ids = $this->apply_semantic_reranking( $search_query, $post_ids );
		}

		// Cache the results.
		set_transient( $cache_key, $post_ids, self::CACHE_EXPIRATION );

		return $post_ids;
	}

	/**
	 * Apply semantic reranking to fuzzy search results
	 *
	 * @param string $query    Search query.
	 * @param array  $post_ids Array of post IDs to rerank.
	 * @return array Reranked array of post IDs
	 */
	protected function apply_semantic_reranking( string $query, array $post_ids ): array {
		try {
			// Use SemanticSearch to rerank the candidates.
			$reranked_results = $this->semantic_search->rerank( $query, $post_ids, count( $post_ids ) );

			if ( empty( $reranked_results ) ) {
				// Log warning if no results were returned from reranking.
				if ( function_exists( 'error_log' ) ) {
					error_log( 'Ihumbak Semantic Search: Semantic reranking returned no results for query: ' . $query );
				}
				// Fallback to original fuzzy search results.
				return $post_ids;
			}

			// Extract post IDs from reranked results.
			$reranked_ids = array_column( $reranked_results, 'post_id' );

			// Log if some embeddings were not found.
			$missing_count = count( $post_ids ) - count( $reranked_ids );
			if ( $missing_count > 0 && function_exists( 'error_log' ) ) {
				error_log( sprintf( 'Ihumbak Semantic Search: %d embeddings not found during reranking', $missing_count ) );
			}

			return $reranked_ids;
		} catch ( \Exception $e ) {
			// Log error and fallback to fuzzy search results.
			if ( function_exists( 'error_log' ) ) {
				error_log( 'Ihumbak Semantic Search: Error during semantic reranking: ' . $e->getMessage() );
			}
			return $post_ids;
		}
	}

	/**
	 * Invalidate all fuzzy search cache
	 *
	 * @return void
	 */
	public static function invalidate_cache(): void {
		global $wpdb;

		// Delete all fuzzy search transients.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_ihumbak_fuzzy_search_%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timeout_ihumbak_fuzzy_search_%'
			)
		);
	}

	/**
	 * Normalize text for comparison
	 *
	 * Converts to lowercase and removes Polish diacritics
	 *
	 * @param string $text Text to normalize.
	 * @return string Normalized text
	 */
	protected function normalize( string $text ): string {
		// Convert to lowercase.
		$text = mb_strtolower( $text, 'UTF-8' );

		// Remove Polish diacritics.
		$polish_chars = array(
			'ą' => 'a',
			'ć' => 'c',
			'ę' => 'e',
			'ł' => 'l',
			'ń' => 'n',
			'ó' => 'o',
			'ś' => 's',
			'ź' => 'z',
			'ż' => 'z',
		);

		$text = strtr( $text, $polish_chars );

		// Remove extra whitespace.
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		return $text;
	}

	/**
	 * Calculate similarity between two strings
	 *
	 * Uses weighted combination of similar_text and levenshtein
	 *
	 * @param string $a First string.
	 * @param string $b Second string.
	 * @return float Similarity score (0-1)
	 */
	protected function calculate_similarity( string $a, string $b ): float {
		if ( empty( $a ) || empty( $b ) ) {
			return 0.0;
		}

		// Calculate similar_text percentage.
		$percent = 0;
		similar_text( $a, $b, $percent );
		$similar_score = $percent / 100.0;

		// Calculate levenshtein distance.
		// Limit string length to avoid performance issues.
		$max_len = 255;
		$a_trimmed = mb_substr( $a, 0, $max_len );
		$b_trimmed = mb_substr( $b, 0, $max_len );

		$lev_distance = levenshtein( $a_trimmed, $b_trimmed );
		$max_length   = max( mb_strlen( $a_trimmed ), mb_strlen( $b_trimmed ) );

		$lev_score = 0.0;
		if ( $max_length > 0 ) {
			$lev_score = 1.0 - ( $lev_distance / $max_length );
		}

		// Weighted combination: 60% similar_text + 40% levenshtein.
		$final_score = ( $similar_score * 0.6 ) + ( $lev_score * 0.4 );

		return max( 0.0, min( 1.0, $final_score ) );
	}

	/**
	 * Get content snippet
	 *
	 * @param string $content Full content.
	 * @param int    $length  Maximum length (default: 300).
	 * @return string Content snippet
	 */
	protected function get_snippet( string $content, int $length = 300 ): string {
		$content = wp_strip_all_tags( $content );
		$content = preg_replace( '/\s+/', ' ', $content );

		if ( mb_strlen( $content ) <= $length ) {
			return $content;
		}

		return mb_substr( $content, 0, $length ) . '...';
	}

	/**
	 * Calculate word overlap between query and text
	 *
	 * @param string $query Normalized query.
	 * @param string $text  Normalized text.
	 * @return float Overlap ratio (0-1)
	 */
	protected function calculate_word_overlap( string $query, string $text ): float {
		// Split into words.
		$query_words = array_filter( explode( ' ', $query ) );
		$text_words  = array_filter( explode( ' ', $text ) );

		if ( empty( $query_words ) ) {
			return 0.0;
		}

		// Count matching words.
		$matches = 0;
		foreach ( $query_words as $query_word ) {
			foreach ( $text_words as $text_word ) {
				// Exact match.
				if ( $query_word === $text_word ) {
					$matches++;
					break;
				}
				// Fuzzy word match (substring).
				if ( mb_strlen( $query_word ) >= 3 && str_contains( $text_word, $query_word ) ) {
					$matches += 0.5;
					break;
				}
			}
		}

		return $matches / count( $query_words );
	}

	/**
	 * Get candidate posts for fuzzy matching
	 *
	 * @param string $search_query Search query.
	 * @return array Array of WP_Post objects
	 */
	protected function get_candidates( string $search_query ): array {
		// Extract words from query for filtering.
		$normalized = $this->normalize( $search_query );
		$words      = array_filter( explode( ' ', $normalized ) );

		// Build query args.
		$args = array(
			'post_type'      => apply_filters( 'ihumbak_fuzzy_search_post_types', array( 'post', 'page' ) ),
			'post_status'    => 'publish',
			'posts_per_page' => $this->candidate_limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
			'fields'         => 'all',
		);

		// Use first few words for simple search to narrow candidates.
		if ( ! empty( $words ) ) {
			$args['s'] = implode( ' ', array_slice( $words, 0, self::MAX_SEARCH_WORDS ) );
		}

		$query = new WP_Query( $args );

		return $query->posts;
	}
}
