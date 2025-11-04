<?php
/**
 * Cache manager for search results
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\Cache;

/**
 * CacheManager class
 */
class CacheManager {

	/**
	 * Cache group
	 *
	 * @var string
	 */
	private string $cache_group = 'ihumbak_semantic_search';

	/**
	 * Cache expiration time in seconds
	 *
	 * @var int
	 */
	private int $expiration;

	/**
	 * Whether caching is enabled
	 *
	 * @var bool
	 */
	private bool $enabled;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->expiration = (int) get_option( 'ihumbak_semantic_search_cache_ttl', 3600 );
		$this->enabled    = (bool) get_option( 'ihumbak_semantic_search_cache_enabled', true );
	}

	/**
	 * Get cached search results
	 *
	 * @param string $query Search query.
	 * @param array  $args  Search arguments.
	 * @return array|false Cached results or false if not found
	 */
	public function get_search_results( string $query, array $args = array() ) {
		if ( ! $this->enabled ) {
			return false;
		}

		$cache_key = $this->generate_cache_key( $query, $args );
		return wp_cache_get( $cache_key, $this->cache_group );
	}

	/**
	 * Set cached search results
	 *
	 * @param string $query   Search query.
	 * @param array  $args    Search arguments.
	 * @param array  $results Search results.
	 * @return bool True on success, false on failure
	 */
	public function set_search_results( string $query, array $args, array $results ): bool {
		if ( ! $this->enabled ) {
			return false;
		}

		$cache_key = $this->generate_cache_key( $query, $args );
		return wp_cache_set( $cache_key, $results, $this->cache_group, $this->expiration );
	}

	/**
	 * Generate cache key
	 *
	 * @param string $query Search query.
	 * @param array  $args  Search arguments.
	 * @return string Cache key
	 */
	private function generate_cache_key( string $query, array $args ): string {
		$key_data = array(
			'query' => $query,
			'args'  => $args,
		);

		return 'search_' . md5( wp_json_encode( $key_data ) );
	}

	/**
	 * Invalidate all search caches
	 *
	 * @return bool
	 */
	public function flush_all(): bool {
		return wp_cache_flush_group( $this->cache_group );
	}

	/**
	 * Invalidate cache for a specific post
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function invalidate_post( int $post_id ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		// Since we can't easily determine which cached queries include this post,
		// we flush all search caches when a post is updated.
		// The $post_id parameter is kept for API consistency and future use.
		$this->flush_all();
	}

	/**
	 * Get cached embedding
	 *
	 * @param string $text Text to get embedding for.
	 * @return array|false Cached embedding or false
	 */
	public function get_embedding( string $text ) {
		if ( ! $this->enabled ) {
			return false;
		}

		$cache_key = $this->generate_embedding_cache_key( $text );
		return wp_cache_get( $cache_key, $this->cache_group );
	}

	/**
	 * Set cached embedding
	 *
	 * @param string $text      Text.
	 * @param array  $embedding Embedding vector.
	 * @return bool
	 */
	public function set_embedding( string $text, array $embedding ): bool {
		if ( ! $this->enabled ) {
			return false;
		}

		$cache_key = $this->generate_embedding_cache_key( $text );
		// Embeddings can be cached longer.
		return wp_cache_set( $cache_key, $embedding, $this->cache_group, DAY_IN_SECONDS );
	}

	/**
	 * Generate embedding cache key
	 *
	 * @param string $text Text.
	 * @return string Cache key
	 */
	private function generate_embedding_cache_key( string $text ): string {
		return 'embedding_' . md5( $text );
	}

	/**
	 * Check if caching is enabled
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return $this->enabled;
	}

	/**
	 * Get cache statistics
	 *
	 * @return array
	 */
	public function get_stats(): array {
		// WordPress doesn't provide built-in cache stats,
		// so we'll return basic configuration info.
		return array(
			'enabled' => $this->enabled,
			'ttl'     => $this->expiration,
			'group'   => $this->cache_group,
		);
	}
}
