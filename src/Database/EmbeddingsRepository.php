<?php
/**
 * Repository for managing embedding records
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\Database;

/**
 * Embeddings Repository class
 */
class EmbeddingsRepository {

	/**
	 * WordPress database object
	 *
	 * @var \wpdb
	 */
	private \wpdb $wpdb;

	/**
	 * Schema instance
	 *
	 * @var Schema
	 */
	private Schema $schema;

	/**
	 * Constructor
	 *
	 * @param Schema $schema Schema instance.
	 */
	public function __construct( Schema $schema ) {
		global $wpdb;
		$this->wpdb   = $wpdb;
		$this->schema = $schema;
	}

	/**
	 * Insert or update embedding for a post
	 *
	 * @param int    $post_id      Post ID.
	 * @param array  $embedding    Embedding vector.
	 * @param string $content_hash Content hash.
	 * @param string $field_type   Field type (post_content, acf_field_name, etc).
	 * @return int|false Insert ID on success, false on failure
	 */
	public function save_embedding( int $post_id, array $embedding, string $content_hash, string $field_type = 'post_content' ) {
		$table = $this->schema->get_embeddings_table();

		// Check if embedding already exists.
		$existing = $this->get_embedding_by_post_and_field( $post_id, $field_type );

		$data = array(
			'post_id'      => $post_id,
			'content_hash' => $content_hash,
			'embedding'    => wp_json_encode( $embedding ),
			'field_type'   => $field_type,
			'indexed_at'   => current_time( 'mysql' ),
		);

		if ( $existing ) {
			// Update existing record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $this->wpdb->update(
				$table,
				$data,
				array(
					'post_id'    => $post_id,
					'field_type' => $field_type,
				),
				array( '%d', '%s', '%s', '%s', '%s' ),
				array( '%d', '%s' )
			);

			return false !== $result ? $existing->id : false;
		}

		// Insert new record.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->insert(
			$table,
			$data,
			array( '%d', '%s', '%s', '%s', '%s' )
		);

		return false !== $result ? $this->wpdb->insert_id : false;
	}

	/**
	 * Get embedding by post ID and field type
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $field_type Field type.
	 * @return object|null
	 */
	public function get_embedding_by_post_and_field( int $post_id, string $field_type = 'post_content' ): ?object {
		$table = $this->schema->get_embeddings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d AND field_type = %s",
				$post_id,
				$field_type
			)
		);

		return $result ?: null;
	}

	/**
	 * Get all embeddings for a post
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_embeddings_by_post( int $post_id ): array {
		$table = $this->schema->get_embeddings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT * FROM {$table} WHERE post_id = %d",
				$post_id
			)
		);

		return $results ?: array();
	}

	/**
	 * Delete embeddings for a post
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function delete_embeddings_by_post( int $post_id ): bool {
		$table = $this->schema->get_embeddings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete(
			$table,
			array( 'post_id' => $post_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete specific embedding by post and field
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $field_type Field type.
	 * @return bool
	 */
	public function delete_embedding_by_post_and_field( int $post_id, string $field_type ): bool {
		$table = $this->schema->get_embeddings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->delete(
			$table,
			array(
				'post_id'    => $post_id,
				'field_type' => $field_type,
			),
			array( '%d', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Check if post has embeddings
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function has_embeddings( int $post_id ): bool {
		$table = $this->schema->get_embeddings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE post_id = %d",
				$post_id
			)
		);

		return (int) $count > 0;
	}

	/**
	 * Get total number of indexed posts
	 *
	 * @return int
	 */
	public function get_indexed_posts_count(): int {
		$table = $this->schema->get_embeddings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $this->wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$table}"
		);

		return (int) $count;
	}

	/**
	 * Get total number of embeddings
	 *
	 * @return int
	 */
	public function get_total_embeddings_count(): int {
		$table = $this->schema->get_embeddings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $this->wpdb->get_var(
			"SELECT COUNT(*) FROM {$table}"
		);

		return (int) $count;
	}

	/**
	 * Check if content has changed since last indexing
	 *
	 * @param int    $post_id      Post ID.
	 * @param string $content_hash New content hash.
	 * @param string $field_type   Field type.
	 * @return bool True if content changed, false otherwise
	 */
	public function content_has_changed( int $post_id, string $content_hash, string $field_type = 'post_content' ): bool {
		$existing = $this->get_embedding_by_post_and_field( $post_id, $field_type );

		if ( ! $existing ) {
			return true; // No existing embedding, content is "new".
		}

		return $existing->content_hash !== $content_hash;
	}

	/**
	 * Get posts that need reindexing (no embeddings or old embeddings)
	 *
	 * @param int $limit Number of posts to retrieve.
	 * @return array
	 */
	public function get_posts_needing_indexing( int $limit = 100 ): array {
		$table = $this->schema->get_embeddings_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $this->wpdb->get_results(
			$this->wpdb->prepare(
				"SELECT p.ID 
				FROM {$this->wpdb->posts} p
				LEFT JOIN {$table} e ON p.ID = e.post_id AND e.field_type = 'post_content'
				WHERE p.post_status = 'publish' 
				AND p.post_type IN ('post', 'page')
				AND e.id IS NULL
				LIMIT %d",
				$limit
			)
		);

		return array_map(
			function ( $row ) {
				return (int) $row->ID;
			},
			$results
		);
	}

	/**
	 * Get embeddings table name
	 *
	 * @return string
	 */
	public function get_embeddings_table(): string {
		return $this->schema->get_embeddings_table();
	}
}
