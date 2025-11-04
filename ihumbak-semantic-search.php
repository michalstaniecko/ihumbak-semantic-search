<?php
/**
 * Plugin Name: Ihumbak Semantic Search
 * Plugin URI: https://github.com/ihumbak/semantic-search
 * Description: Semantic Hybrid Search for WordPress + ACF + OpenAI
 * Version: 0.1.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Michal Staniecko
 * Author URI: https://ihumbak.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ihumbak-semantic-search
 * Domain Path: /languages
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'IHUMBAK_SEMANTIC_SEARCH_VERSION', '0.1.0' );
define( 'IHUMBAK_SEMANTIC_SEARCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IHUMBAK_SEMANTIC_SEARCH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IHUMBAK_SEMANTIC_SEARCH_PLUGIN_FILE', __FILE__ );

// Require Composer autoloader.
if ( file_exists( IHUMBAK_SEMANTIC_SEARCH_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once IHUMBAK_SEMANTIC_SEARCH_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Initialize the plugin.
 */
function init() {
	// Load plugin textdomain.
	load_plugin_textdomain(
		'ihumbak-semantic-search',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	// Check if database needs upgrade.
	$schema = new Database\Schema();
	if ( $schema->needs_upgrade() ) {
		$migrator = new Database\Migrator( $schema );
		$migrator->migrate();
	}

	// Initialize admin settings.
	if ( is_admin() ) {
		$settings = new Admin\Settings();
		$settings->register();
	}

	// Initialize post hooks for auto-indexing.
	$post_hooks = new Indexing\PostHooks();
	$post_hooks->register();

	// Register background indexing action.
	add_action( 'ihumbak_semantic_search_index_post', array( Indexing\PostHooks::class, 'background_index_post' ) );

	// Register REST API endpoints.
	$search_endpoint = new API\SearchEndpoint();
	$search_endpoint->register();

	// Register shortcode.
	$shortcode = new Frontend\Shortcode();
	$shortcode->register();

	// Register widget.
	add_action( 'widgets_init', __NAMESPACE__ . '\\register_widgets' );

	// Register cache invalidation hooks.
	add_action( 'save_post', __NAMESPACE__ . '\\invalidate_cache_on_post_save' );
	add_action( 'delete_post', __NAMESPACE__ . '\\invalidate_cache_on_post_delete' );
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

/**
 * Register widgets
 *
 * @return void
 */
function register_widgets(): void {
	register_widget( Frontend\SearchWidget::class );
}

/**
 * Activation hook.
 */
function activate() {
	// Run database migrations.
	$schema   = new Database\Schema();
	$migrator = new Database\Migrator( $schema );
	$migrator->migrate();

	flush_rewrite_rules();
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );

/**
 * Deactivation hook.
 */
function deactivate() {
	flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

/**
 * Uninstall hook - cleanup database.
 */
function uninstall() {
	$schema   = new Database\Schema();
	$migrator = new Database\Migrator( $schema );
	$migrator->rollback();
}

register_uninstall_hook( __FILE__, __NAMESPACE__ . '\\uninstall' );

/**
 * Invalidate cache on post save
 *
 * @param int $post_id Post ID.
 */
function invalidate_cache_on_post_save( int $post_id ): void {
	$cache = new Cache\CacheManager();
	$cache->invalidate_post( $post_id );

	// Invalidate fuzzy search cache.
	Search\FuzzySearch::invalidate_cache();
}

/**
 * Invalidate cache on post delete
 *
 * @param int $post_id Post ID.
 */
function invalidate_cache_on_post_delete( int $post_id ): void {
	$cache = new Cache\CacheManager();
	$cache->invalidate_post( $post_id );

	// Invalidate fuzzy search cache.
	Search\FuzzySearch::invalidate_cache();
}
