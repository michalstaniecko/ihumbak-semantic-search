<?php
/**
 * Tests for Shortcode class
 *
 * @package Ihumbak\SemanticSearch\Tests
 */

namespace Ihumbak\SemanticSearch\Tests\Frontend;

use Ihumbak\SemanticSearch\Frontend\Shortcode;
use WP_UnitTestCase;

/**
 * Shortcode test case.
 */
class ShortcodeTest extends WP_UnitTestCase {

	/**
	 * Shortcode instance
	 *
	 * @var Shortcode
	 */
	private Shortcode $shortcode;

	/**
	 * Set up before each test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->shortcode = new Shortcode();
		$this->shortcode->register();
	}

	/**
	 * Test shortcode is registered
	 */
	public function test_shortcode_registered() {
		$this->assertTrue( shortcode_exists( 'semantic_search' ) );
	}

	/**
	 * Test shortcode renders form
	 */
	public function test_shortcode_renders_form() {
		$output = do_shortcode( '[semantic_search]' );

		$this->assertStringContainsString( 'semantic-search-wrapper', $output );
		$this->assertStringContainsString( 'semantic-search-form', $output );
		$this->assertStringContainsString( 'semantic-search-input', $output );
		$this->assertStringContainsString( 'semantic-search-button', $output );
	}

	/**
	 * Test shortcode with custom attributes
	 */
	public function test_shortcode_with_attributes() {
		$output = do_shortcode( '[semantic_search placeholder="Test Search" button_text="Go" limit="5"]' );

		$this->assertStringContainsString( 'Test Search', $output );
		$this->assertStringContainsString( 'Go', $output );
		$this->assertStringContainsString( 'data-limit="5"', $output );
	}

	/**
	 * Test shortcode with post_type attribute
	 */
	public function test_shortcode_post_type_attribute() {
		$output = do_shortcode( '[semantic_search post_type="post"]' );

		$this->assertStringContainsString( 'data-post-type="post"', $output );
	}

	/**
	 * Test shortcode with mode attribute
	 */
	public function test_shortcode_mode_attribute() {
		$output = do_shortcode( '[semantic_search mode="semantic"]' );

		$this->assertStringContainsString( 'data-mode="semantic"', $output );
	}

	/**
	 * Test shortcode with custom class
	 */
	public function test_shortcode_custom_class() {
		$output = do_shortcode( '[semantic_search class="my-custom-class"]' );

		$this->assertStringContainsString( 'my-custom-class', $output );
	}

	/**
	 * Test render method directly
	 */
	public function test_render_search_form() {
		$output = $this->shortcode->render_search_form(
			array(
				'placeholder' => 'Direct Test',
				'limit'       => 20,
			)
		);

		$this->assertStringContainsString( 'Direct Test', $output );
		$this->assertStringContainsString( 'data-limit="20"', $output );
	}

	/**
	 * Test show_excerpt attribute
	 */
	public function test_show_excerpt_attribute() {
		$output = do_shortcode( '[semantic_search show_excerpt="no"]' );

		$this->assertStringContainsString( 'data-show-excerpt="no"', $output );
	}

	/**
	 * Test show_thumbnail attribute
	 */
	public function test_show_thumbnail_attribute() {
		$output = do_shortcode( '[semantic_search show_thumbnail="yes"]' );

		$this->assertStringContainsString( 'data-show-thumbnail="yes"', $output );
	}
}
