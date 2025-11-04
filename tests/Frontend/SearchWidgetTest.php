<?php
/**
 * Tests for SearchWidget class
 *
 * @package Ihumbak\SemanticSearch\Tests
 */

namespace Ihumbak\SemanticSearch\Tests\Frontend;

use Ihumbak\SemanticSearch\Frontend\SearchWidget;
use WP_UnitTestCase;

/**
 * SearchWidget test case.
 */
class SearchWidgetTest extends WP_UnitTestCase {

	/**
	 * Test widget registration
	 */
	public function test_widget_registered() {
		global $wp_widget_factory;

		$widget_class = SearchWidget::class;
		$registered   = false;

		foreach ( $wp_widget_factory->widgets as $widget ) {
			if ( $widget instanceof $widget_class ) {
				$registered = true;
				break;
			}
		}

		$this->assertTrue( $registered );
	}

	/**
	 * Test widget instance
	 */
	public function test_widget_instance() {
		$widget = new SearchWidget();

		$this->assertInstanceOf( SearchWidget::class, $widget );
		$this->assertEquals( 'semantic_search_widget', $widget->id_base );
	}

	/**
	 * Test widget update
	 */
	public function test_widget_update() {
		$widget = new SearchWidget();

		$new_instance = array(
			'title'     => 'Test Search',
			'limit'     => 15,
			'post_type' => 'post',
			'mode'      => 'semantic',
		);

		$old_instance = array();

		$updated = $widget->update( $new_instance, $old_instance );

		$this->assertEquals( 'Test Search', $updated['title'] );
		$this->assertEquals( 15, $updated['limit'] );
		$this->assertEquals( 'post', $updated['post_type'] );
		$this->assertEquals( 'semantic', $updated['mode'] );
	}

	/**
	 * Test widget update sanitization
	 */
	public function test_widget_update_sanitization() {
		$widget = new SearchWidget();

		$new_instance = array(
			'title'     => '<script>alert("xss")</script>Search',
			'limit'     => 'invalid',
			'mode'      => 'invalid_mode',
		);

		$updated = $widget->update( $new_instance, array() );

		$this->assertStringNotContainsString( '<script>', $updated['title'] );
		$this->assertEquals( 0, $updated['limit'] );
		$this->assertEquals( 'hybrid', $updated['mode'] ); // Should fallback to default.
	}

	/**
	 * Test widget form output
	 */
	public function test_widget_form() {
		$widget = new SearchWidget();

		ob_start();
		$widget->form( array() );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Title:', $output );
		$this->assertStringContainsString( 'Number of Results:', $output );
		$this->assertStringContainsString( 'Search Mode:', $output );
	}
}
