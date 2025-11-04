<?php
/**
 * Utility class for embedding calculations
 *
 * @package Ihumbak\SemanticSearch
 */

namespace Ihumbak\SemanticSearch\OpenAI;

/**
 * EmbeddingUtils class
 */
class EmbeddingUtils {

	/**
	 * Calculate cosine similarity between two vectors
	 *
	 * @param array $vec1 First vector.
	 * @param array $vec2 Second vector.
	 * @return float Similarity score (0-1)
	 */
	public static function cosine_similarity( array $vec1, array $vec2 ): float {
		if ( empty( $vec1 ) || empty( $vec2 ) || count( $vec1 ) !== count( $vec2 ) ) {
			return 0.0;
		}

		$dot  = 0.0;
		$mag1 = 0.0;
		$mag2 = 0.0;

		foreach ( $vec1 as $i => $v ) {
			$dot  += $v * $vec2[ $i ];
			$mag1 += $v * $v;
			$mag2 += $vec2[ $i ] * $vec2[ $i ];
		}

		if ( 0.0 === $mag1 || 0.0 === $mag2 ) {
			return 0.0;
		}

		return $dot / ( sqrt( $mag1 ) * sqrt( $mag2 ) );
	}

	/**
	 * Normalize a vector
	 *
	 * @param array $vector Vector to normalize.
	 * @return array Normalized vector
	 */
	public static function normalize( array $vector ): array {
		$magnitude = 0.0;

		foreach ( $vector as $value ) {
			$magnitude += $value * $value;
		}

		$magnitude = sqrt( $magnitude );

		if ( 0.0 === $magnitude ) {
			return $vector;
		}

		return array_map(
			function ( $value ) use ( $magnitude ) {
				return $value / $magnitude;
			},
			$vector
		);
	}

	/**
	 * Calculate Euclidean distance between two vectors
	 *
	 * @param array $vec1 First vector.
	 * @param array $vec2 Second vector.
	 * @return float Distance
	 */
	public static function euclidean_distance( array $vec1, array $vec2 ): float {
		if ( empty( $vec1 ) || empty( $vec2 ) || count( $vec1 ) !== count( $vec2 ) ) {
			return PHP_FLOAT_MAX;
		}

		$sum = 0.0;

		foreach ( $vec1 as $i => $v ) {
			$diff = $v - $vec2[ $i ];
			$sum += $diff * $diff;
		}

		return sqrt( $sum );
	}

	/**
	 * Calculate dot product of two vectors
	 *
	 * @param array $vec1 First vector.
	 * @param array $vec2 Second vector.
	 * @return float Dot product
	 */
	public static function dot_product( array $vec1, array $vec2 ): float {
		if ( empty( $vec1 ) || empty( $vec2 ) || count( $vec1 ) !== count( $vec2 ) ) {
			return 0.0;
		}

		$dot = 0.0;

		foreach ( $vec1 as $i => $v ) {
			$dot += $v * $vec2[ $i ];
		}

		return $dot;
	}
}
