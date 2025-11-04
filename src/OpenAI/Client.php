<?php
/**
 * OpenAI API Client for generating embeddings
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\OpenAI;

/**
 * OpenAI Client class
 */
class Client {

	/**
	 * API key
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * API endpoint
	 *
	 * @var string
	 */
	private string $endpoint = 'https://api.openai.com/v1/embeddings';

	/**
	 * Default model
	 *
	 * @var string
	 */
	private string $model = 'text-embedding-3-small';

	/**
	 * Constructor
	 *
	 * @param string|null $api_key Optional API key. If not provided, will use get_api_key().
	 */
	public function __construct( ?string $api_key = null ) {
		$this->api_key = $api_key ?? $this->get_api_key();
	}

	/**
	 * Get API key from constant or option
	 *
	 * @return string
	 */
	private function get_api_key(): string {
		// First check for constant (wp-config.php).
		if ( defined( 'IHUMBAK_SEMANTIC_SEARCH_OPENAI_KEY' ) ) {
			return IHUMBAK_SEMANTIC_SEARCH_OPENAI_KEY;
		}

		// Fall back to option.
		return get_option( 'ihumbak_semantic_search_openai_key', '' );
	}

	/**
	 * Check if API key is configured
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return ! empty( $this->api_key );
	}

	/**
	 * Set custom model
	 *
	 * @param string $model Model name.
	 * @return void
	 */
	public function set_model( string $model ): void {
		$this->model = $model;
	}

	/**
	 * Get current model
	 *
	 * @return string
	 */
	public function get_model(): string {
		return $this->model;
	}

	/**
	 * Generate embedding for text
	 *
	 * @param string $text Text to embed.
	 * @return array|false Embedding vector on success, false on failure
	 */
	public function generate_embedding( string $text ) {
		if ( ! $this->is_configured() ) {
			error_log( 'OpenAI API key not configured' );
			return false;
		}

		if ( empty( $text ) ) {
			error_log( 'Cannot generate embedding for empty text' );
			return false;
		}

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'input' => $text,
						'model' => $this->model,
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'OpenAI API error: ' . $response->get_error_message() );
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			$body = wp_remote_retrieve_body( $response );
			error_log( sprintf( 'OpenAI API returned status %d: %s', $status_code, $body ) );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['data'][0]['embedding'] ) ) {
			error_log( 'OpenAI API response missing embedding data' );
			return false;
		}

		return $body['data'][0]['embedding'];
	}

	/**
	 * Generate embeddings for multiple texts (batch)
	 *
	 * @param array $texts Array of texts to embed.
	 * @return array|false Array of embeddings on success, false on failure
	 */
	public function generate_embeddings_batch( array $texts ) {
		if ( ! $this->is_configured() ) {
			error_log( 'OpenAI API key not configured' );
			return false;
		}

		if ( empty( $texts ) ) {
			return array();
		}

		$response = wp_remote_post(
			$this->endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'input' => $texts,
						'model' => $this->model,
					)
				),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'OpenAI API batch error: ' . $response->get_error_message() );
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			$body = wp_remote_retrieve_body( $response );
			error_log( sprintf( 'OpenAI API batch returned status %d: %s', $status_code, $body ) );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! isset( $body['data'] ) ) {
			error_log( 'OpenAI API batch response missing data' );
			return false;
		}

		$embeddings = array();
		foreach ( $body['data'] as $item ) {
			if ( isset( $item['embedding'] ) ) {
				$embeddings[] = $item['embedding'];
			}
		}

		return $embeddings;
	}

	/**
	 * Test API connection
	 *
	 * @return bool True if connection successful, false otherwise
	 */
	public function test_connection(): bool {
		$embedding = $this->generate_embedding( 'test' );
		return false !== $embedding;
	}
}
