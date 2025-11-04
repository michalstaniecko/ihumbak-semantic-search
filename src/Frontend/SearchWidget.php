<?php
/**
 * Search widget
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\Frontend;

use WP_Widget;

/**
 * SearchWidget class
 */
class SearchWidget extends WP_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			'semantic_search_widget',
			__( 'Semantic Search', 'ihumbak-semantic-search' ),
			array(
				'description' => __( 'A semantic search form powered by AI', 'ihumbak-semantic-search' ),
			)
		);
	}

	/**
	 * Front-end display of widget
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 * @return void
	 */
	public function widget( $args, $instance ): void {
		$title      = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$limit      = ! empty( $instance['limit'] ) ? absint( $instance['limit'] ) : 10;
		$post_type  = ! empty( $instance['post_type'] ) ? $instance['post_type'] : 'post,page';
		$mode       = ! empty( $instance['mode'] ) ? $instance['mode'] : 'hybrid';

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( ! empty( $title ) ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		// Use shortcode to render the search form.
		$shortcode = new Shortcode();
		echo $shortcode->render_search_form( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			array(
				'limit'     => $limit,
				'post_type' => $post_type,
				'mode'      => $mode,
				'class'     => 'semantic-search-widget',
			)
		);

		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Back-end widget form
	 *
	 * @param array $instance Previously saved values from database.
	 * @return void
	 */
	public function form( $instance ): void {
		$title     = ! empty( $instance['title'] ) ? $instance['title'] : __( 'Search', 'ihumbak-semantic-search' );
		$limit     = ! empty( $instance['limit'] ) ? absint( $instance['limit'] ) : 10;
		$post_type = ! empty( $instance['post_type'] ) ? $instance['post_type'] : 'post,page';
		$mode      = ! empty( $instance['mode'] ) ? $instance['mode'] : 'hybrid';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
				<?php esc_html_e( 'Title:', 'ihumbak-semantic-search' ); ?>
			</label>
			<input 
				class="widefat" 
				id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" 
				name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" 
				type="text" 
				value="<?php echo esc_attr( $title ); ?>"
			/>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>">
				<?php esc_html_e( 'Number of Results:', 'ihumbak-semantic-search' ); ?>
			</label>
			<input 
				class="tiny-text" 
				id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>" 
				name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" 
				type="number" 
				min="1"
				max="50"
				value="<?php echo esc_attr( $limit ); ?>"
			/>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'post_type' ) ); ?>">
				<?php esc_html_e( 'Post Types (comma-separated):', 'ihumbak-semantic-search' ); ?>
			</label>
			<input 
				class="widefat" 
				id="<?php echo esc_attr( $this->get_field_id( 'post_type' ) ); ?>" 
				name="<?php echo esc_attr( $this->get_field_name( 'post_type' ) ); ?>" 
				type="text" 
				value="<?php echo esc_attr( $post_type ); ?>"
			/>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>">
				<?php esc_html_e( 'Search Mode:', 'ihumbak-semantic-search' ); ?>
			</label>
			<select 
				class="widefat" 
				id="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>" 
				name="<?php echo esc_attr( $this->get_field_name( 'mode' ) ); ?>"
			>
				<option value="hybrid" <?php selected( $mode, 'hybrid' ); ?>>
					<?php esc_html_e( 'Hybrid', 'ihumbak-semantic-search' ); ?>
				</option>
				<option value="keyword" <?php selected( $mode, 'keyword' ); ?>>
					<?php esc_html_e( 'Keyword Only', 'ihumbak-semantic-search' ); ?>
				</option>
				<option value="semantic" <?php selected( $mode, 'semantic' ); ?>>
					<?php esc_html_e( 'Semantic Only', 'ihumbak-semantic-search' ); ?>
				</option>
			</select>
		</p>
		<?php
	}

	/**
	 * Sanitize widget form values as they are saved
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ): array {
		$instance = array();

		$instance['title']     = ! empty( $new_instance['title'] ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['limit']     = ! empty( $new_instance['limit'] ) ? absint( $new_instance['limit'] ) : 10;
		$instance['post_type'] = ! empty( $new_instance['post_type'] ) ? sanitize_text_field( $new_instance['post_type'] ) : 'post,page';
		$instance['mode']      = ! empty( $new_instance['mode'] ) ? sanitize_text_field( $new_instance['mode'] ) : 'hybrid';

		// Validate mode.
		if ( ! in_array( $instance['mode'], array( 'hybrid', 'keyword', 'semantic' ), true ) ) {
			$instance['mode'] = 'hybrid';
		}

		return $instance;
	}
}
