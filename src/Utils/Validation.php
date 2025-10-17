<?php
/**
 * Validation Utility Class
 *
 * @package UrlImageImporter\Utils
 */

namespace UrlImageImporter\Utils;

/**
 * Class Validation
 *
 * Handles validation operations for URLs, images, and attachments.
 */
class Validation {

	/**
	 * Check if a URL points to an image
	 *
	 * @param string $url The URL to check
	 * @return bool True if URL is an image, false otherwise
	 */
	public static function is_image_url( $url ) {
		$image_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', 'tiff', 'ico' );
		$extension = strtolower( pathinfo( parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
		
		// Check file extension first
		if ( in_array( $extension, $image_extensions ) ) {
			return true;
		}
		
		// Check for common image hosting services (no file extension needed)
		$image_services = array(
			'picsum.photos',
			'images.unsplash.com', 
			'source.unsplash.com',
			'via.placeholder.com',
			'placehold.it',
			'dummyimage.com',
			'lorempixel.com'
		);
		
		$parsed_url = parse_url( $url );
		$host = isset( $parsed_url['host'] ) ? strtolower( $parsed_url['host'] ) : '';
		
		foreach ( $image_services as $service ) {
			if ( strpos( $host, $service ) !== false ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Check if attachment already exists in WordPress
	 *
	 * @param string $filename The filename to check
	 * @return bool True if attachment exists, false otherwise
	 */
	public static function attachment_exists( $filename ) {
		global $wpdb;
		$result = $wpdb->get_var( $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND guid LIKE %s",
			'%' . $wpdb->esc_like( $filename )
		) );
		return ! empty( $result );
	}

	/**
	 * Validate URL format
	 *
	 * @param string $url The URL to validate
	 * @return bool True if valid URL, false otherwise
	 */
	public static function is_valid_url( $url ) {
		return filter_var( $url, FILTER_VALIDATE_URL ) !== false;
	}
}
