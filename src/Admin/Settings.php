<?php
/**
 * Admin settings page
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\Admin;

use Ihumbak\SemanticSearch\Database\EmbeddingsRepository;
use Ihumbak\SemanticSearch\Database\Schema;
use Ihumbak\SemanticSearch\Indexing\Indexer;
use Ihumbak\SemanticSearch\OpenAI\Client;

/**
 * Settings class
 */
class Settings {

	/**
	 * Register hooks
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_ihumbak_semantic_search_reindex', array( $this, 'handle_reindex' ) );
		add_action( 'admin_post_ihumbak_semantic_search_test_connection', array( $this, 'handle_test_connection' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add admin menu page
	 *
	 * @return void
	 */
	public function add_menu_page(): void {
		add_options_page(
			__( 'Semantic Search Settings', 'ihumbak-semantic-search' ),
			__( 'Semantic Search', 'ihumbak-semantic-search' ),
			'manage_options',
			'ihumbak-semantic-search',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'ihumbak_semantic_search',
			'ihumbak_semantic_search_openai_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		register_setting(
			'ihumbak_semantic_search',
			'ihumbak_semantic_search_auto_index',
			array(
				'type'    => 'boolean',
				'default' => true,
			)
		);

		register_setting(
			'ihumbak_semantic_search',
			'ihumbak_semantic_search_model',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'text-embedding-3-small',
			)
		);

		register_setting(
			'ihumbak_semantic_search',
			'ihumbak_semantic_search_mode',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'hybrid',
			)
		);

		register_setting(
			'ihumbak_semantic_search',
			'ihumbak_semantic_search_cache_enabled',
			array(
				'type'    => 'boolean',
				'default' => true,
			)
		);

		register_setting(
			'ihumbak_semantic_search',
			'ihumbak_semantic_search_cache_ttl',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 3600,
			)
		);

		add_settings_section(
			'ihumbak_semantic_search_api',
			__( 'API Configuration', 'ihumbak-semantic-search' ),
			array( $this, 'render_api_section' ),
			'ihumbak-semantic-search'
		);

		add_settings_section(
			'ihumbak_semantic_search_search',
			__( 'Search Settings', 'ihumbak-semantic-search' ),
			array( $this, 'render_search_section' ),
			'ihumbak-semantic-search'
		);

		add_settings_section(
			'ihumbak_semantic_search_indexing',
			__( 'Indexing Settings', 'ihumbak-semantic-search' ),
			array( $this, 'render_indexing_section' ),
			'ihumbak-semantic-search'
		);

		add_settings_section(
			'ihumbak_semantic_search_cache',
			__( 'Cache Settings', 'ihumbak-semantic-search' ),
			array( $this, 'render_cache_section' ),
			'ihumbak-semantic-search'
		);

		// Only show API key field if not defined in wp-config.php.
		if ( ! defined( 'IHUMBAK_SEMANTIC_SEARCH_OPENAI_KEY' ) ) {
			add_settings_field(
				'ihumbak_semantic_search_openai_key',
				__( 'OpenAI API Key', 'ihumbak-semantic-search' ),
				array( $this, 'render_api_key_field' ),
				'ihumbak-semantic-search',
				'ihumbak_semantic_search_api'
			);
		}

		add_settings_field(
			'ihumbak_semantic_search_model',
			__( 'Embedding Model', 'ihumbak-semantic-search' ),
			array( $this, 'render_model_field' ),
			'ihumbak-semantic-search',
			'ihumbak_semantic_search_api'
		);

		add_settings_field(
			'ihumbak_semantic_search_mode',
			__( 'Search Mode', 'ihumbak-semantic-search' ),
			array( $this, 'render_search_mode_field' ),
			'ihumbak-semantic-search',
			'ihumbak_semantic_search_search'
		);

		add_settings_field(
			'ihumbak_semantic_search_auto_index',
			__( 'Auto-index on Save', 'ihumbak-semantic-search' ),
			array( $this, 'render_auto_index_field' ),
			'ihumbak-semantic-search',
			'ihumbak_semantic_search_indexing'
		);

		add_settings_field(
			'ihumbak_semantic_search_cache_enabled',
			__( 'Enable Cache', 'ihumbak-semantic-search' ),
			array( $this, 'render_cache_enabled_field' ),
			'ihumbak-semantic-search',
			'ihumbak_semantic_search_cache'
		);

		add_settings_field(
			'ihumbak_semantic_search_cache_ttl',
			__( 'Cache TTL (seconds)', 'ihumbak-semantic-search' ),
			array( $this, 'render_cache_ttl_field' ),
			'ihumbak-semantic-search',
			'ihumbak_semantic_search_cache'
		);
	}

	/**
	 * Render API section
	 *
	 * @return void
	 */
	public function render_api_section(): void {
		if ( defined( 'IHUMBAK_SEMANTIC_SEARCH_OPENAI_KEY' ) ) {
			echo '<p>' . esc_html__( 'API key is configured in wp-config.php', 'ihumbak-semantic-search' ) . '</p>';
		}
	}

	/**
	 * Render indexing section
	 *
	 * @return void
	 */
	public function render_indexing_section(): void {
		$schema     = new Schema();
		$repository = new EmbeddingsRepository( $schema );
		$client     = new Client();
		$indexer    = new Indexer( $client, $repository );
		$stats      = $indexer->get_stats();

		echo '<table class="widefat">';
		echo '<tr><th>' . esc_html__( 'Total Posts', 'ihumbak-semantic-search' ) . '</th><td>' . esc_html( $stats['total_posts'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Indexed Posts', 'ihumbak-semantic-search' ) . '</th><td>' . esc_html( $stats['indexed_posts'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Total Embeddings', 'ihumbak-semantic-search' ) . '</th><td>' . esc_html( $stats['total_embeddings'] ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Posts Needing Index', 'ihumbak-semantic-search' ) . '</th><td>' . esc_html( $stats['posts_need_index'] ) . '</td></tr>';
		echo '</table>';
	}

	/**
	 * Render search section
	 *
	 * @return void
	 */
	public function render_search_section(): void {
		echo '<p>' . esc_html__( 'Configure how search results are generated.', 'ihumbak-semantic-search' ) . '</p>';
	}

	/**
	 * Render cache section
	 *
	 * @return void
	 */
	public function render_cache_section(): void {
		echo '<p>' . esc_html__( 'Cache settings to improve search performance.', 'ihumbak-semantic-search' ) . '</p>';
	}

	/**
	 * Render API key field
	 *
	 * @return void
	 */
	public function render_api_key_field(): void {
		$value = get_option( 'ihumbak_semantic_search_openai_key', '' );
		echo '<input type="password" name="ihumbak_semantic_search_openai_key" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'Enter your OpenAI API key. Alternatively, define IHUMBAK_SEMANTIC_SEARCH_OPENAI_KEY in wp-config.php', 'ihumbak-semantic-search' ) . '</p>';
	}

	/**
	 * Render model field
	 *
	 * @return void
	 */
	public function render_model_field(): void {
		$value  = get_option( 'ihumbak_semantic_search_model', 'text-embedding-3-small' );
		$models = array(
			'text-embedding-3-small' => 'text-embedding-3-small (Recommended)',
			'text-embedding-3-large' => 'text-embedding-3-large',
			'text-embedding-ada-002' => 'text-embedding-ada-002 (Legacy)',
		);

		echo '<select name="ihumbak_semantic_search_model">';
		foreach ( $models as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Render auto-index field
	 *
	 * @return void
	 */
	public function render_auto_index_field(): void {
		$value = get_option( 'ihumbak_semantic_search_auto_index', true );
		echo '<label>';
		echo '<input type="checkbox" name="ihumbak_semantic_search_auto_index" value="1" ' . checked( $value, true, false ) . ' />';
		echo ' ' . esc_html__( 'Automatically index posts when they are saved or published', 'ihumbak-semantic-search' );
		echo '</label>';
	}

	/**
	 * Render search mode field
	 *
	 * @return void
	 */
	public function render_search_mode_field(): void {
		$value = get_option( 'ihumbak_semantic_search_mode', 'hybrid' );
		$modes = array(
			'hybrid'   => __( 'Hybrid (Keyword + Semantic)', 'ihumbak-semantic-search' ),
			'keyword'  => __( 'Keyword Only', 'ihumbak-semantic-search' ),
			'semantic' => __( 'Semantic Only', 'ihumbak-semantic-search' ),
		);

		echo '<select name="ihumbak_semantic_search_mode">';
		foreach ( $modes as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '" ' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Hybrid mode combines keyword search with semantic reranking for best results.', 'ihumbak-semantic-search' ) . '</p>';
	}

	/**
	 * Render cache enabled field
	 *
	 * @return void
	 */
	public function render_cache_enabled_field(): void {
		$value = get_option( 'ihumbak_semantic_search_cache_enabled', true );
		echo '<label>';
		echo '<input type="checkbox" name="ihumbak_semantic_search_cache_enabled" value="1" ' . checked( $value, true, false ) . ' />';
		echo ' ' . esc_html__( 'Enable caching of search results', 'ihumbak-semantic-search' );
		echo '</label>';
	}

	/**
	 * Render cache TTL field
	 *
	 * @return void
	 */
	public function render_cache_ttl_field(): void {
		$value = get_option( 'ihumbak_semantic_search_cache_ttl', 3600 );
		echo '<input type="number" name="ihumbak_semantic_search_cache_ttl" value="' . esc_attr( $value ) . '" min="60" step="60" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'How long to cache search results (in seconds). Default: 3600 (1 hour)', 'ihumbak-semantic-search' ) . '</p>';
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( string $hook ): void {
		// Only load on our settings page.
		if ( 'settings_page_ihumbak-semantic-search' !== $hook ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style(
			'semantic-search-admin',
			IHUMBAK_SEMANTIC_SEARCH_PLUGIN_URL . 'build/admin-reindex.css',
			array(),
			IHUMBAK_SEMANTIC_SEARCH_VERSION
		);

		// Enqueue JavaScript.
		wp_enqueue_script(
			'semantic-search-admin',
			IHUMBAK_SEMANTIC_SEARCH_PLUGIN_URL . 'build/admin-reindex.js',
			array(),
			IHUMBAK_SEMANTIC_SEARCH_VERSION,
			true
		);

		// Localize script with data.
		wp_localize_script(
			'semantic-search-admin',
			'semanticSearchAdmin',
			array(
				'apiUrl' => rest_url( 'semantic-search/v1' ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'i18n'   => array(
					'confirmReindex' => __( 'This will reindex all published posts. This may take a while. Continue?', 'ihumbak-semantic-search' ),
					'reindexButton'  => __( 'Reindex All Posts', 'ihumbak-semantic-search' ),
					'reindexing'     => __( 'Reindexing...', 'ihumbak-semantic-search' ),
					'processing'     => __( 'Processing {processed} of {total} posts...', 'ihumbak-semantic-search' ),
					'completed'      => __( 'Reindexing completed! Successfully indexed {indexed} posts. {failed} failed.', 'ihumbak-semantic-search' ),
					'cancelled'      => __( 'Reindexing cancelled.', 'ihumbak-semantic-search' ),
					'error'          => __( 'An error occurred during reindexing. Please try again.', 'ihumbak-semantic-search' ),
					'failedCount'    => __( '{failed} posts failed to index.', 'ihumbak-semantic-search' ),
				),
			)
		);
	}

	/**
	 * Render settings page
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if reindex was successful.
		if ( isset( $_GET['reindexed'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'reindex_success' ) ) {
			$count = isset( $_GET['count'] ) ? intval( $_GET['count'] ) : 0;
			echo '<div class="notice notice-success"><p>';
			// translators: %d: Number of posts indexed.
			echo esc_html( sprintf( __( 'Successfully indexed %d posts.', 'ihumbak-semantic-search' ), $count ) );
			echo '</p></div>';
		}

		// Check if connection test was successful.
		if ( isset( $_GET['connection_test'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'connection_test' ) ) {
			$success = 'success' === $_GET['connection_test'];
			$class   = $success ? 'notice-success' : 'notice-error';
			$message = $success ? __( 'Connection to OpenAI API successful!', 'ihumbak-semantic-search' ) : __( 'Failed to connect to OpenAI API. Please check your API key.', 'ihumbak-semantic-search' );
			echo '<div class="notice ' . esc_attr( $class ) . '"><p>' . esc_html( $message ) . '</p></div>';
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( 'ihumbak_semantic_search' );
				do_settings_sections( 'ihumbak-semantic-search' );
				submit_button();
				?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Actions', 'ihumbak-semantic-search' ); ?></h2>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="ihumbak_semantic_search_test_connection">
				<?php wp_nonce_field( 'ihumbak_semantic_search_test_connection' ); ?>
				<?php submit_button( __( 'Test OpenAI Connection', 'ihumbak-semantic-search' ), 'secondary', 'submit', false ); ?>
			</form>

			<br>

			<div class="reindex-section">
				<button type="button" id="reindex-button" class="button button-primary">
					<?php esc_html_e( 'Reindex All Posts', 'ihumbak-semantic-search' ); ?>
				</button>

				<div id="reindex-progress" style="display: none;">
					<h3><?php esc_html_e( 'Reindex Progress', 'ihumbak-semantic-search' ); ?></h3>
					<div class="reindex-progress-wrapper">
						<div id="reindex-progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0%;">
							0%
						</div>
					</div>
					<div id="reindex-progress-text"></div>
					<div class="reindex-actions">
						<button type="button" id="reindex-cancel" class="button">
							<?php esc_html_e( 'Cancel', 'ihumbak-semantic-search' ); ?>
						</button>
					</div>
				</div>

				<div id="reindex-status" style="display: none;"></div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle reindex action
	 *
	 * @return void
	 */
	public function handle_reindex(): void {
		check_admin_referer( 'ihumbak_semantic_search_reindex' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ihumbak-semantic-search' ) );
		}

		$schema     = new Schema();
		$repository = new EmbeddingsRepository( $schema );
		$client     = new Client();
		$indexer    = new Indexer( $client, $repository );

		$count = $indexer->reindex_all_posts( 100, true );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'ihumbak-semantic-search',
					'reindexed' => '1',
					'count'     => $count,
					'_wpnonce'  => wp_create_nonce( 'reindex_success' ),
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	/**
	 * Handle test connection action
	 *
	 * @return void
	 */
	public function handle_test_connection(): void {
		check_admin_referer( 'ihumbak_semantic_search_test_connection' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'ihumbak-semantic-search' ) );
		}

		$client  = new Client();
		$success = $client->test_connection();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'            => 'ihumbak-semantic-search',
					'connection_test' => $success ? 'success' : 'failed',
					'_wpnonce'        => wp_create_nonce( 'connection_test' ),
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}
}
