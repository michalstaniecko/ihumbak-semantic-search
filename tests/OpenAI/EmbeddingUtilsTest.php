<?php
/**
 * Tests for EmbeddingUtils class
 *
 * @package Ihumbak\SemanticSearch\Tests
 */

namespace Ihumbak\SemanticSearch\Tests\OpenAI;

use Ihumbak\SemanticSearch\OpenAI\EmbeddingUtils;
use WP_UnitTestCase;

/**
 * EmbeddingUtils test case.
 */
class EmbeddingUtilsTest extends WP_UnitTestCase {

	/**
	 * Test cosine similarity with identical vectors
	 */
	public function test_cosine_similarity_identical() {
		$vec = array( 1.0, 2.0, 3.0 );
		$similarity = EmbeddingUtils::cosine_similarity( $vec, $vec );
		$this->assertEquals( 1.0, $similarity, '', 0.0001 );
	}

	/**
	 * Test cosine similarity with orthogonal vectors
	 */
	public function test_cosine_similarity_orthogonal() {
		$vec1 = array( 1.0, 0.0, 0.0 );
		$vec2 = array( 0.0, 1.0, 0.0 );
		$similarity = EmbeddingUtils::cosine_similarity( $vec1, $vec2 );
		$this->assertEquals( 0.0, $similarity, '', 0.0001 );
	}

	/**
	 * Test cosine similarity with opposite vectors
	 */
	public function test_cosine_similarity_opposite() {
		$vec1 = array( 1.0, 2.0, 3.0 );
		$vec2 = array( -1.0, -2.0, -3.0 );
		$similarity = EmbeddingUtils::cosine_similarity( $vec1, $vec2 );
		$this->assertEquals( -1.0, $similarity, '', 0.0001 );
	}

	/**
	 * Test cosine similarity with empty vectors
	 */
	public function test_cosine_similarity_empty() {
		$similarity = EmbeddingUtils::cosine_similarity( array(), array() );
		$this->assertEquals( 0.0, $similarity );
	}

	/**
	 * Test cosine similarity with different length vectors
	 */
	public function test_cosine_similarity_different_length() {
		$vec1 = array( 1.0, 2.0, 3.0 );
		$vec2 = array( 1.0, 2.0 );
		$similarity = EmbeddingUtils::cosine_similarity( $vec1, $vec2 );
		$this->assertEquals( 0.0, $similarity );
	}

	/**
	 * Test normalize
	 */
	public function test_normalize() {
		$vec = array( 3.0, 4.0 );
		$normalized = EmbeddingUtils::normalize( $vec );

		// Length should be 1.
		$length = sqrt( $normalized[0] * $normalized[0] + $normalized[1] * $normalized[1] );
		$this->assertEquals( 1.0, $length, '', 0.0001 );
	}

	/**
	 * Test normalize zero vector
	 */
	public function test_normalize_zero() {
		$vec = array( 0.0, 0.0, 0.0 );
		$normalized = EmbeddingUtils::normalize( $vec );
		$this->assertEquals( $vec, $normalized );
	}

	/**
	 * Test Euclidean distance
	 */
	public function test_euclidean_distance() {
		$vec1 = array( 0.0, 0.0 );
		$vec2 = array( 3.0, 4.0 );
		$distance = EmbeddingUtils::euclidean_distance( $vec1, $vec2 );
		$this->assertEquals( 5.0, $distance, '', 0.0001 );
	}

	/**
	 * Test dot product
	 */
	public function test_dot_product() {
		$vec1 = array( 1.0, 2.0, 3.0 );
		$vec2 = array( 4.0, 5.0, 6.0 );
		$dot = EmbeddingUtils::dot_product( $vec1, $vec2 );
		// 1*4 + 2*5 + 3*6 = 4 + 10 + 18 = 32.
		$this->assertEquals( 32.0, $dot );
	}
}
