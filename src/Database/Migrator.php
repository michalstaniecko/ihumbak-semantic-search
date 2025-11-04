<?php
/**
 * Database migration manager
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\Database;

/**
 * Migrator class for handling database migrations
 */
class Migrator {

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
		$this->schema = $schema;
	}

	/**
	 * Run all necessary migrations
	 *
	 * @return bool True on success, false on failure
	 */
	public function migrate(): bool {
		$current_version = $this->schema->get_schema_version();

		// Fresh install.
		if ( '0.0.0' === $current_version ) {
			return $this->fresh_install();
		}

		// Run incremental migrations.
		return $this->run_migrations( $current_version );
	}

	/**
	 * Fresh installation
	 *
	 * @return bool
	 */
	private function fresh_install(): bool {
		$success = true;

		// Create embeddings table.
		if ( ! $this->schema->create_tables() ) {
			error_log( 'Failed to create semantic_embeddings table' );
			$success = false;
		}

		// Add fulltext index to posts table.
		if ( ! $this->schema->add_fulltext_index() ) {
			error_log( 'Failed to add fulltext index to wp_posts' );
			$success = false;
		}

		if ( $success ) {
			$this->schema->update_schema_version( IHUMBAK_SEMANTIC_SEARCH_VERSION );
		}

		return $success;
	}

	/**
	 * Run incremental migrations
	 *
	 * @param string $from_version Starting version.
	 * @return bool
	 */
	private function run_migrations( string $from_version ): bool {
		$migrations = $this->get_migrations();
		$success    = true;

		foreach ( $migrations as $version => $callback ) {
			if ( version_compare( $from_version, $version, '<' ) ) {
				if ( ! call_user_func( $callback ) ) {
					error_log( sprintf( 'Migration to version %s failed', $version ) );
					$success = false;
					break;
				}
				$this->schema->update_schema_version( $version );
			}
		}

		return $success;
	}

	/**
	 * Get available migrations
	 *
	 * @return array<string, callable>
	 */
	private function get_migrations(): array {
		return array(
			'0.1.0' => array( $this, 'migrate_to_0_1_0' ),
		);
	}

	/**
	 * Migration to version 0.1.0
	 *
	 * @return bool
	 */
	private function migrate_to_0_1_0(): bool {
		// This is the initial version, no migration needed.
		return true;
	}

	/**
	 * Rollback all migrations
	 *
	 * @return bool
	 */
	public function rollback(): bool {
		$success = true;

		// Remove fulltext index.
		if ( ! $this->schema->remove_fulltext_index() ) {
			error_log( 'Failed to remove fulltext index' );
			$success = false;
		}

		// Drop embeddings table.
		if ( ! $this->schema->drop_tables() ) {
			error_log( 'Failed to drop semantic_embeddings table' );
			$success = false;
		}

		if ( $success ) {
			delete_option( 'ihumbak_semantic_search_schema_version' );
		}

		return $success;
	}
}
