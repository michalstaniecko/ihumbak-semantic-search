<?php
/**
 * Post hooks for automatic indexing
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\Indexing;

use Ihumbak\SemanticSearch\Database\EmbeddingsRepository;
use Ihumbak\SemanticSearch\Database\Schema;
use Ihumbak\SemanticSearch\OpenAI\Client;

/**
 * PostHooks class
 */
class PostHooks {

	/**
	 * Indexer instance
	 *
	 * @var Indexer
	 */
	private Indexer $indexer;

	/**
	 * Whether auto-indexing is enabled
	 *
	 * @var bool
	 */
	private bool $auto_index_enabled;

	/**
	 * Constructor
	 */
	public function __construct() {
		$schema     = new Schema();
		$repository = new EmbeddingsRepository( $schema );
		$client     = new Client();

		$this->indexer            = new Indexer( $client, $repository );
		$this->auto_index_enabled = get_option( 'ihumbak_semantic_search_auto_index', true );
	}

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	public function register(): void {
		if ( ! $this->auto_index_enabled ) {
			return;
		}

		add_action( 'save_post', array( $this, 'on_save_post' ), 10, 2 );
		add_action( 'delete_post', array( $this, 'on_delete_post' ) );
		add_action( 'transition_post_status', array( $this, 'on_transition_post_status' ), 10, 3 );
	}

	/**
	 * Handle post save
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function on_save_post( int $post_id, \WP_Post $post ): void {
		// Skip autosaves and revisions.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Only index published posts.
		if ( 'publish' !== $post->post_status ) {
			return;
		}

		// Only index posts and pages.
		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}

		// Index in background to avoid slowing down post save.
		wp_schedule_single_event(
			time() + 10,
			'ihumbak_semantic_search_index_post',
			array( $post_id )
		);
	}

	/**
	 * Handle post deletion
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function on_delete_post( int $post_id ): void {
		$this->indexer->delete_post_index( $post_id );
	}

	/**
	 * Handle post status transition
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post object.
	 * @return void
	 */
	public function on_transition_post_status( string $new_status, string $old_status, \WP_Post $post ): void {
		// If post is unpublished, remove from index.
		if ( 'publish' === $old_status && 'publish' !== $new_status ) {
			$this->indexer->delete_post_index( $post->ID );
		}
	}

	/**
	 * Background indexing action
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function background_index_post( int $post_id ): void {
		$schema     = new Schema();
		$repository = new EmbeddingsRepository( $schema );
		$client     = new Client();
		$indexer    = new Indexer( $client, $repository );

		$indexer->index_post( $post_id );
	}
}
