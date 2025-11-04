<?php
/**
 * Shortcode handler for search form
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\Frontend;

/**
 * Shortcode class
 */
class Shortcode {

	/**
	 * Register shortcode
	 *
	 * @return void
	 */
	public function register(): void {
		add_shortcode( 'semantic_search', array( $this, 'render_search_form' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Render search form shortcode
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output
	 */
	public function render_search_form( array $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'placeholder'     => __( 'Enter your search query...', 'ihumbak-semantic-search' ),
				'button_text'     => __( 'Search', 'ihumbak-semantic-search' ),
				'limit'           => 10,
				'post_type'       => 'post,page',
				'show_excerpt'    => 'yes',
				'show_thumbnail'  => 'no',
				'mode'            => 'hybrid',
				'class'           => '',
			),
			$atts,
			'semantic_search'
		);

		ob_start();
		?>
		<div class="semantic-search-wrapper <?php echo esc_attr( $atts['class'] ); ?>" 
			data-limit="<?php echo esc_attr( $atts['limit'] ); ?>"
			data-post-type="<?php echo esc_attr( $atts['post_type'] ); ?>"
			data-show-excerpt="<?php echo esc_attr( $atts['show_excerpt'] ); ?>"
			data-show-thumbnail="<?php echo esc_attr( $atts['show_thumbnail'] ); ?>"
			data-mode="<?php echo esc_attr( $atts['mode'] ); ?>">
			
			<form class="semantic-search-form" role="search">
				<div class="semantic-search-input-wrapper">
					<input 
						type="text" 
						class="semantic-search-input" 
						name="q" 
						placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>"
						autocomplete="off"
						required
					/>
					<button type="submit" class="semantic-search-button">
						<?php echo esc_html( $atts['button_text'] ); ?>
					</button>
				</div>
				<div class="semantic-search-loading" style="display: none;">
					<span class="semantic-search-spinner"></span>
					<?php esc_html_e( 'Searching...', 'ihumbak-semantic-search' ); ?>
				</div>
			</form>

			<div class="semantic-search-results" style="display: none;">
				<div class="semantic-search-results-header">
					<span class="semantic-search-results-count"></span>
					<button type="button" class="semantic-search-clear">
						<?php esc_html_e( 'Clear Results', 'ihumbak-semantic-search' ); ?>
					</button>
				</div>
				<div class="semantic-search-results-list"></div>
			</div>

			<div class="semantic-search-error" style="display: none;"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Enqueue frontend assets
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		// Only enqueue if shortcode is present on the page.
		global $post;
		if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'semantic_search' ) ) {
			return;
		}

		wp_enqueue_style(
			'semantic-search',
			IHUMBAK_SEMANTIC_SEARCH_PLUGIN_URL . 'assets/css/semantic-search.css',
			array(),
			IHUMBAK_SEMANTIC_SEARCH_VERSION
		);

		wp_enqueue_script(
			'semantic-search',
			IHUMBAK_SEMANTIC_SEARCH_PLUGIN_URL . 'assets/js/semantic-search.js',
			array( 'jquery' ),
			IHUMBAK_SEMANTIC_SEARCH_VERSION,
			true
		);

		wp_localize_script(
			'semantic-search',
			'semanticSearchConfig',
			array(
				'apiUrl'     => rest_url( 'semantic-search/v1/search' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'i18n'       => array(
					'noResults'     => __( 'No results found.', 'ihumbak-semantic-search' ),
					'error'         => __( 'An error occurred. Please try again.', 'ihumbak-semantic-search' ),
					'resultsFound'  => __( 'results found', 'ihumbak-semantic-search' ),
					'cached'        => __( '(cached)', 'ihumbak-semantic-search' ),
				),
			)
		);
	}
}
