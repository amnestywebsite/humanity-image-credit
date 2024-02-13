<?php

declare( strict_types = 1 );

namespace Amnesty\Image_Credit;

/**
 * Attempt to retrieve an image ID from the cache
 *
 * @param string $url the image url
 *
 * @return array<string,mixed>|null
 */
function get_image_from_cache( string $url ): ?array {
	$key = 'image_url_to_id_' . md5( $url );
	$img = wp_cache_get( $key );

	if ( $img ) {
		return $img;
	}

	return null;
}

/**
 * Add an image's ID to the cache
 *
 * @param int    $id      the image ID
 * @param string $size    the image size name
 * @param string $url     the image URI
 * @param int    $blog_id the image blog ID
 *
 * @return int
 */
function add_image_to_cache( int $id, string $size, string $url, int $blog_id = 1 ): int {
	$key  = 'image_url_to_id_' . md5( $url );
	$data = [ // phpcs doesn't like compact(), for some reason
		'id'      => $id,
		'size'    => $size,
		'blog_id' => $blog_id,
	];

	wp_cache_set( $key, $data, 'default', DAY_IN_SECONDS );
	return $id;
}

/**
 * Remove an image's ID from the cache
 *
 * @param string $url the image url
 *
 * @return void
 */
function remove_image_from_cache( string $url ): void {
	$key = 'image_url_to_id_' . md5( $url );
	wp_cache_delete( $key, 'default' );
}

/**
 * Test whether an image url has dimensions
 *
 * @param string $url the image url
 *
 * @return bool
 */
function image_has_dimensions( string $url ): bool {
	return 1 === preg_match( '/-\d+x\d+\.[a-zA-Z]{2,4}$/', $url );
}

/**
 * Test whether an image url has been scaled
 *
 * @param string $url the image url
 *
 * @return bool
 */
function image_has_scale( string $url ): bool {
	return false !== strpos( $url, '-scaled' ) && 1 === preg_match( '/\.[a-zA-Z]{2,4}$/', $url );
}

/**
 * Strip dimensions from an image url
 *
 * @param string $url the image url
 *
 * @return string
 */
function strip_image_dimensions( string $url ): string {
	return preg_replace( '/-\d+x\d+(\.[a-zA-Z]{2,4})$/', '$1', $url );
}

/**
 * Add "-scaled" suffix to image url
 *
 * @param string $url the image url
 *
 * @return string
 */
function add_scaled_to_image( string $url ): string {
	return preg_replace( '/(\.[a-zA-Z]{2,4})$/', '-scaled$1', $url );
}

/**
 * Test whether an image has year/month, and the site supports it
 *
 * @param string $url the image url
 *
 * @return bool
 */
function image_has_yearmonth( string $url ): bool {
	return get_option( 'uploads_use_yearmonth_folders' ) && false !== preg_match( '/\d{4}\/\d{2}\/.*\.[a-z]{3,5}$/i', $url );
}

/**
 * Retrieve an image's path with year/month prefix
 *
 * @param string $url the image url
 *
 * @return string|null
 */
function image_get_yearmonth( string $url ): ?string {
	if ( false === preg_match( '/\d{4}\/\d{2}\/.*\.[a-z]{3,5}$/i', $url, $match ) ) {
		return null;
	}

	if ( ! isset( $match[0] ) ) {
		return null;
	}

	return $match[0];
}

/**
 * Retrieve all stored sizes for an image
 *
 * @param int $id the image ID
 *
 * @return array<string,string>
 */
function get_all_sizes_for_image( int $id ): array {
	$full = wp_parse_url( wp_get_attachment_image_url( $id, 'full' ), PHP_URL_PATH );
	$data = wp_get_attachment_metadata( $id );
	$urls = [ 'full' => $full ];
	$dir  = wp_parse_url( dirname( $full ), PHP_URL_PATH );

	if ( ! $data || empty( $data['sizes'] ) ) {
		return $urls;
	}

	foreach ( $data['sizes'] as $size => $info ) {
		$urls[ $size ] = $dir . '/' . $info['file'];
	}

	return array_unique( $urls );
}

/**
 * Attempt to locate an image's ID from its url
 *
 * @param string $url the file to look up
 *
 * @return array<string,mixed>|null
 */
function lookup_image_id( string $url ): ?array {
	global $wpdb;

	$cached = get_image_from_cache( $url );

	if ( $cached ) {
		return $cached;
	}

	$path = wp_parse_url( $url, PHP_URL_PATH );

	// phpcs:ignore WordPress.DB
	$data = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id, blog_id, size, uri FROM {$wpdb->base_prefix}ai_media_lookup WHERE hash = %s LIMIT 1",
			md5( $path )
		) 
	);

	if ( ! $data || $data->uri !== $path ) {
		return null;
	}

	$id = absint( $data->id );

	return [
		'id'      => add_image_to_cache( $id, $data->size, $url, absint( $data->blog_id ) ),
		'size'    => $data->size,
		'blog_id' => $data->blog_id,
	];
}

/**
 * Attempt to locate and image's urls from its ID
 *
 * @param int $id      the image to lookup
 * @param int $blog_id the blog the image belongs to
 *
 * @return array<string,string>|null
 */
function lookup_image_urls( int $id, int $blog_id = 1 ): ?array {
	global $wpdb;

	$key    = __FUNCTION__ . '_' . $id;
	$cached = wp_cache_get( $key );

	if ( $cached ) {
		return $cached;
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$urls = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT size, uri FROM {$wpdb->base_prefix}ai_media_lookup WHERE id = %d AND blog_id = %d",
			$id,
			$blog_id
		),
		ARRAY_A 
	);

	if ( ! $urls ) {
		return null;
	}

	$urls = array_column( $urls, 'uri', 'size' );

	wp_cache_add( $key, $urls );

	return $urls;
}

/**
 * Store an image's ID in the lookup table
 *
 * @param int    $id      the image's ID
 * @param int    $blog_id the image's blog ID
 * @param string $size    the image size
 * @param string $url     the image's URL
 *
 * @return void
 */
function store_image_id( int $id, int $blog_id, string $size, string $url ): void {
	global $wpdb;

	// check it's not already stored in the db (prevents db errors)
	if ( lookup_image_id( $url ) ) {
		return;
	}

	$path = wp_parse_url( $url, PHP_URL_PATH );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
	$wpdb->insert(
		"{$wpdb->base_prefix}ai_media_lookup",
		[
			'id'      => $id,
			'blog_id' => $blog_id,
			'size'    => $size,
			'uri'     => $path,
			'hash'    => md5( $path ),
		],
		[
			'%d',
			'%d',
			'%s',
			'%s',
			'%s',
		]
	);

	add_image_to_cache( $id, $size, $url );
}

/**
 * Remove an image's info from the lookup table
 *
 * @param int $id      the image's ID
 * @param int $blog_id the image's blog ID
 *
 * @return void
 */
function remove_image_id( int $id, int $blog_id = 1 ): void {
	global $wpdb;

	$urls = lookup_image_urls( $id, $blog_id );

	if ( $urls ) {
		array_map( '\Amnesty\Image_Credit\remove_image_from_cache', array_values( $urls ) );
	}

	// phpcs:ignore WordPress.DB
	$wpdb->delete( "{$wpdb->base_prefix}ai_media_lookup", [ 'id' => $id ], [ '%d' ] );
}
