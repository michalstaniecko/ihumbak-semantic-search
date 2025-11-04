<?php
/**
 * Database schema manager for semantic search embeddings
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\Database;

/**
 * Schema class for managing database tables
 */
class Schema {

	/**
	 * Table name for embeddings
	 *
	 * @var string
	 */
	private string $embeddings_table;

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
		$this->wpdb             = $wpdb;
		$this->embeddings_table = $wpdb->prefix . 'semantic_embeddings';
	}

	/**
	 * Get the embeddings table name
	 *
	 * @return string
	 */
	public function get_embeddings_table(): string {
		return $this->embeddings_table;
	}

	/**
	 * Create database tables
	 *
	 * @return bool True on success, false on failure
	 */
	public function create_tables(): bool {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->embeddings_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			post_id BIGINT UNSIGNED NOT NULL,
			content_hash VARCHAR(64) NOT NULL,
			embedding LONGTEXT NOT NULL,
			field_type VARCHAR(50) DEFAULT 'post_content',
			indexed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_post_id (post_id),
			KEY idx_content_hash (content_hash),
			KEY idx_indexed_at (indexed_at)
		) $charset_collate;";

		dbDelta( $sql );

		return $this->verify_table_exists();
	}

	/**
	 * Verify that the embeddings table exists
	 *
	 * @return bool
	 */
	public function verify_table_exists(): bool {
		$table_name = $this->embeddings_table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $this->wpdb->get_var( $this->wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		return $result === $table_name;
	}

	/**
	 * Drop the embeddings table
	 *
	 * @return bool
	 */
	public function drop_tables(): bool {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$result = $this->wpdb->query( "DROP TABLE IF EXISTS {$this->embeddings_table}" );
		return false !== $result;
	}

	/**
	 * Add fulltext index to wp_posts table
	 *
	 * @return bool True on success, false on failure
	 */
	public function add_fulltext_index(): bool {
		$posts_table = $this->wpdb->posts;

		// Check if fulltext index already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$indexes = $this->wpdb->get_results(
			$this->wpdb->prepare( 'SHOW INDEX FROM %i WHERE Index_type = %s', $posts_table, 'FULLTEXT' ),
			ARRAY_A
		);

		foreach ( $indexes as $index ) {
			if ( 'fulltext_title_content' === $index['Key_name'] ) {
				return true; // Index already exists.
			}
		}

		// Add fulltext index.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$result = $this->wpdb->query(
			"ALTER TABLE {$posts_table} ADD FULLTEXT INDEX fulltext_title_content (post_title, post_content)"
		);

		return false !== $result;
	}

	/**
	 * Remove fulltext index from wp_posts table
	 *
	 * @return bool
	 */
	public function remove_fulltext_index(): bool {
		$posts_table = $this->wpdb->posts;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$result = $this->wpdb->query(
			"ALTER TABLE {$posts_table} DROP INDEX IF EXISTS fulltext_title_content"
		);

		return false !== $result;
	}

	/**
	 * Get current schema version
	 *
	 * @return string
	 */
	public function get_schema_version(): string {
		return get_option( 'ihumbak_semantic_search_schema_version', '0.0.0' );
	}

	/**
	 * Update schema version
	 *
	 * @param string $version Version number.
	 * @return bool
	 */
	public function update_schema_version( string $version ): bool {
		return update_option( 'ihumbak_semantic_search_schema_version', $version );
	}

	/**
	 * Check if schema needs upgrade
	 *
	 * @return bool
	 */
	public function needs_upgrade(): bool {
		$current_version = $this->get_schema_version();
		$target_version  = IHUMBAK_SEMANTIC_SEARCH_VERSION;

		return version_compare( $current_version, $target_version, '<' );
	}
}
