<?php
/**
 * Tests for OpenAI Client class
 *
 * @package Ihumbak\SemanticSearch\Tests
 */

namespace Ihumbak\SemanticSearch\Tests\OpenAI;

use Ihumbak\SemanticSearch\OpenAI\Client;
use WP_UnitTestCase;

/**
 * Client test case.
 */
class ClientTest extends WP_UnitTestCase {

	/**
	 * Test is_configured with no API key
	 */
	public function test_is_configured_no_key() {
		delete_option( 'ihumbak_semantic_search_openai_key' );
		$client = new Client();
		$this->assertFalse( $client->is_configured() );
	}

	/**
	 * Test is_configured with API key in option
	 */
	public function test_is_configured_with_option() {
		update_option( 'ihumbak_semantic_search_openai_key', 'test-key' );
		$client = new Client();
		$this->assertTrue( $client->is_configured() );
		delete_option( 'ihumbak_semantic_search_openai_key' );
	}

	/**
	 * Test is_configured with constant
	 */
	public function test_is_configured_with_constant() {
		if ( ! defined( 'IHUMBAK_SEMANTIC_SEARCH_OPENAI_KEY' ) ) {
			define( 'IHUMBAK_SEMANTIC_SEARCH_OPENAI_KEY', 'test-key-constant' );
		}
		$client = new Client();
		$this->assertTrue( $client->is_configured() );
	}

	/**
	 * Test set and get model
	 */
	public function test_model_methods() {
		$client = new Client( 'test-key' );
		$this->assertEquals( 'text-embedding-3-small', $client->get_model() );

		$client->set_model( 'text-embedding-3-large' );
		$this->assertEquals( 'text-embedding-3-large', $client->get_model() );
	}

	/**
	 * Test generate_embedding with empty text
	 */
	public function test_generate_embedding_empty_text() {
		$client = new Client( 'test-key' );
		$result = $client->generate_embedding( '' );
		$this->assertFalse( $result );
	}

	/**
	 * Test generate_embedding without API key
	 */
	public function test_generate_embedding_no_key() {
		$client = new Client();
		$result = $client->generate_embedding( 'test text' );
		$this->assertFalse( $result );
	}
}
