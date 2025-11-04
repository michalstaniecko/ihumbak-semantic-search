<?php
/**
 * Sample test case for plugin
 *
 * @package Ihumbak\SemanticSearch\Tests
 */

namespace Ihumbak\SemanticSearch\Tests;

use WP_UnitTestCase;

/**
 * Sample test case.
 */
class SampleTest extends WP_UnitTestCase {

	/**
	 * Test that the plugin is loaded.
	 */
	public function test_plugin_loaded() {
		$this->assertTrue( defined( 'IHUMBAK_SEMANTIC_SEARCH_VERSION' ) );
	}

	/**
	 * Test plugin version constant.
	 */
	public function test_plugin_version() {
		$this->assertEquals( '0.1.0', IHUMBAK_SEMANTIC_SEARCH_VERSION );
	}
}
