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

	// Initialize plugin components here in future phases.
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\\init' );

/**
 * Activation hook.
 */
function activate() {
	// Activation tasks will be added in future phases.
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
