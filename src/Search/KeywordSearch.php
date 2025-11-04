<?php
/**
 * Keyword search using MySQL FULLTEXT
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\Search;

/**
 * KeywordSearch class
 */
class KeywordSearch {

	/**
	 * WordPress database object
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	/**
	 * Search posts using FULLTEXT
	 *
	 * @param string $query      Search query.
	 * @param array  $args       Additional arguments.
	 * @return array Array of post IDs with relevance scores
	 */
	public function search( string $query, array $args = array() ): array {
		if ( empty( $query ) ) {
			return array();
		}

		$defaults = array(
			'post_type'   => array( 'post', 'page' ),
			'post_status' => 'publish',
			'limit'       => 50,
		);

		$args = wp_parse_args( $args, $defaults );

		// Sanitize query for FULLTEXT.
		$search_query = $this->sanitize_fulltext_query( $query );

		// Build post type conditions.
		$post_types = (array) $args['post_type'];
		$post_type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT ID, 
					MATCH(post_title, post_content) AGAINST (%s IN NATURAL LANGUAGE MODE) as relevance
				FROM {$this->wpdb->posts}
				WHERE MATCH(post_title, post_content) AGAINST (%s IN NATURAL LANGUAGE MODE)
					AND post_status = %s
					AND post_type IN ($post_type_placeholders)
				ORDER BY relevance DESC
				LIMIT %d",
				array_merge(
					array( $search_query, $search_query, $args['post_status'] ),
					$post_types,
					array( $args['limit'] )
				)
			)
		);

		if ( empty( $results ) ) {
			return array();
		}

		// Return array of post IDs with scores.
		return array_map(
			function ( $row ) {
				return array(
					'post_id'   => (int) $row->ID,
					'relevance' => (float) $row->relevance,
				);
			},
			$results
		);
	}

	/**
	 * Search posts using FULLTEXT with Boolean mode
	 *
	 * @param string $query Search query.
	 * @param array  $args  Additional arguments.
	 * @return array Array of post IDs with relevance scores
	 */
	public function search_boolean( string $query, array $args = array() ): array {
		if ( empty( $query ) ) {
			return array();
		}

		$defaults = array(
			'post_type'   => array( 'post', 'page' ),
			'post_status' => 'publish',
			'limit'       => 50,
		);

		$args = wp_parse_args( $args, $defaults );

		// Build post type conditions.
		$post_types = (array) $args['post_type'];
		$post_type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT ID, 
					MATCH(post_title, post_content) AGAINST (%s IN BOOLEAN MODE) as relevance
				FROM {$this->wpdb->posts}
				WHERE MATCH(post_title, post_content) AGAINST (%s IN BOOLEAN MODE)
					AND post_status = %s
					AND post_type IN ($post_type_placeholders)
				ORDER BY relevance DESC
				LIMIT %d",
				array_merge(
					array( $query, $query, $args['post_status'] ),
					$post_types,
					array( $args['limit'] )
				)
			)
		);

		if ( empty( $results ) ) {
			return array();
		}

		return array_map(
			function ( $row ) {
				return array(
					'post_id'   => (int) $row->ID,
					'relevance' => (float) $row->relevance,
				);
			},
			$results
		);
	}

	/**
	 * Sanitize FULLTEXT query
	 *
	 * @param string $query Query string.
	 * @return string Sanitized query
	 */
	private function sanitize_fulltext_query( string $query ): string {
		// Remove special characters that might break FULLTEXT.
		$query = preg_replace( '/[+\-><()~*\"@]+/', ' ', $query );

		// Normalize whitespace.
		$query = preg_replace( '/\s+/', ' ', $query );

		return trim( $query );
	}

	/**
	 * Check if FULLTEXT index exists
	 *
	 * @return bool
	 */
	public function fulltext_index_exists(): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$indexes = $this->wpdb->get_results(
			$this->wpdb->prepare(
				'SHOW INDEX FROM %i WHERE Index_type = %s',
				$this->wpdb->posts,
				'FULLTEXT'
			),
			ARRAY_A
		);

		foreach ( $indexes as $index ) {
			if ( 'fulltext_title_content' === $index['Key_name'] ) {
				return true;
			}
		}

		return false;
	}
}
