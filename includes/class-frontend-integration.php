<?php

declare( strict_types = 1 );

namespace Amnesty\Image_Credit;

use Amnesty\Get_Image_Data;
use DOMDocument;
use DOMXPath;

/**
 * Workflow:
 *
 * Get path from URI
 * Check whether it exists in the lookup table
 *   if it does
 *     get caption from blog id => post id
 *   if it doesn't
 *     check whether the upload path is to a /sites/n or the base site
 *       if to base site
 *         get the caption from there
 *         by rewriting the uri prior to lookup in table, or switching site if it's not there
 *         store it if the image can be found
 *       if to current site
 *         store it if the image can be found
 *         get caption from its origin site
 */


/**
 * Processes images within the content and appends
 * artist credit, where available
 */
class Frontend_Integration {

	/**
	 * The base site ID
	 *
	 * @var int
	 */
	protected int $primary_site_id = 1;

	/**
	 * Register filters
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'boot' ] );
		add_filter( 'the_content', [ $this, 'filter_tags' ], 99 );
		add_filter( 'the_content', [ $this, 'filter_styles' ], 99 );
	}

	/**
	 * Load required data
	 *
	 * @return void
	 */
	public function boot(): void {
		if ( is_multisite() ) {
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			$this->primary_site_id = apply_filters( 'global_media.site_id', BLOG_ID_CURRENT_SITE );
		} else {
			$this->primary_site_id = get_current_blog_id();
		}
	}

	/**
	 * Process <img> tags for credit application
	 *
	 * @param string $content the post's content
	 *
	 * @return string
	 */
	public function filter_tags( string $content = '' ): string {
		if ( ! $this->should_filter() ) {
			return $content;
		}

		preg_match_all( '/<img\s([^>]+)>/i', $content, $matches );

		if ( empty( $matches[0] ) ) {
			return $content;
		}

		foreach ( $matches[0] as $match ) {
			if ( false !== strpos( $match, 'aiic-ignore' ) ) {
				continue;
			}

			$content = str_replace( $match, $this->add_tag_caption( $match ), $content );
		}

		return $content;
	}

	/**
	 * Process inline styles for images for credit application
	 *
	 * @param string $content the post's content
	 *
	 * @return string
	 */
	public function filter_styles( string $content = '' ): string {
		if ( ! $this->should_filter() ) {
			return $content;
		}

		$content = $this->filter_style_tags( $content );
		$content = $this->filter_element_styles( $content );

		return $content;
	}

	/**
	 * Whether to check for images
	 *
	 * @return bool
	 */
	protected function should_filter(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( is_admin() ) {
			return false;
		}

		if ( is_search() ) {
			return false;
		}

		return true;
	}

	/**
	 * Process <style> tags for image declarations
	 *
	 * @param string $content the post's content
	 *
	 * @return string
	 */
	protected function filter_style_tags( string $content = '' ): string {
		if ( ! $content ) {
			return $content;
		}

		$doc = new DOMDocument( '1.0', 'utf-8' );

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$doc->formatOutput       = false;
		$doc->substituteEntities = false;
		$doc->preserveWhiteSpace = true;
		$doc->validateOnParse    = false;

		// ensure string is utf8
		$encoded_content = mb_convert_encoding( $content, 'UTF-8' );
		// encode everything
		$encoded_content = htmlentities( $encoded_content, ENT_NOQUOTES, 'UTF-8' );
		// decode "standard" characters
		$encoded_content = htmlspecialchars_decode( $encoded_content, ENT_NOQUOTES );
		// convert left side of ISO-8859-1 to HTML numeric character reference
		// this was taken from PHP docs for mb_encode_numericentity   vvvvvvvvvvvvvvvvvvvvvvvvv
		$encoded_content = mb_encode_numericentity( $encoded_content, [ 0x80, 0x10FFFF, 0, ~0 ], 'UTF-8' );

		libxml_use_internal_errors( true );
		$doc->loadHTML(
			'<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/></head><body>' .
			$encoded_content .
			'</body>',
			LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOENT
		);
		libxml_use_internal_errors( false );

		$xpath = new DOMXPath( $doc );

		foreach ( $xpath->query( '//style' ) as $style_tag ) {
			// phpcs:ignore Generic.Commenting.DocComment.MissingShort
			/** @var \DOMElement $style_tag */
			if ( $style_tag->getAttribute( 'class' ) && false !== strpos( $style_tag->getAttribute( 'class' ), 'aiic-ignore' ) ) {
				continue;
			}

			$style_text = $style_tag->textContent;

			if ( false === strpos( $style_text, 'background-image' ) ) {
				continue;
			}

			preg_match( '/background-image:\s*?url\([\'"]?([^\'")]+)[\'"]?\)/', $style_text, $matches );

			if ( ! isset( $matches[1] ) ) {
				continue;
			}

			$image = $this->get_id( $matches[1] );

			if ( ! $image ) {
				continue;
			}

			$image_data = new Get_Image_Data( $image['id'] );

			if ( ! $image_data->credit() ) {
				continue;
			}

			$style_text = str_replace( 'background-image', 'position:relative;background-image', $style_text );

			$style_tag->textContent = $style_text;

			$target_element = $style_tag->nextSibling;

			while ( XML_TEXT_NODE === $target_element->nodeType ) {
				$target_element = $target_element->nextSibling;
			}

			$metadata = $doc->createElement( 'div' );
			$caption  = $doc->createElement( 'span' );
			$credit   = $doc->createElement( 'span' );

			$caption->setAttribute( 'class', 'image-metadataItem image-caption' );
			$caption->setAttribute( 'style', 'display:none' ); // possibly temporary
			$caption->textContent = $image_data->caption();

			$credit->setAttribute( 'class', 'image-metadataItem image-copyright' );
			$credit->textContent = $image_data->credit();

			$metadata->setAttribute( 'class', 'image-metadata' );
			$metadata->appendChild( $caption );
			$metadata->appendChild( $credit );

			$target_element->appendChild( $metadata );
		}

		$html = '';

		foreach ( $xpath->query( '/html/body' )->item( 0 )->childNodes as $node ) {
			$html .= $doc->saveHTML( $node );
		}

		return $html;
	}

	/**
	 * Process style attributes for image declarations
	 *
	 * @param string $content the post's content
	 *
	 * @return string
	 */
	protected function filter_element_styles( string $content = '' ): string {
		// 0: full tag, 2: the url
		preg_match_all( '/(\s*?style=[\'"]background-image:\s*?url\([\'"]?([^\'"]+?)[\'"]?\)[^\'"]*?[\'"][^>]*?>)/is', $content, $matches );

		if ( empty( $matches[0] ) ) {
			return $content;
		}

		foreach ( $matches[0] as $index => $match ) {
			$newmatch = $match . $this->add_style_caption( $matches[2][ $index ] );

			if ( ! preg_match( '/position:\s*?relative/i', $match ) ) {
				$newmatch = preg_replace( '/(style=[\'"])/i', '$1position:relative;', $newmatch );
			}

			$content = str_replace( $match, $newmatch, $content );
		}

		return $content;
	}

	/**
	 * Add image credit to <img> tags
	 *
	 * @param string $image the current image
	 *
	 * @return string
	 */
	protected function add_tag_caption( string $image = '' ): string {
		$attrs = $this->parse( $image );

		if ( empty( $attrs['_image_id'] ) ) {
			return $image;
		}

		if ( isset( $attrs['class'] ) && false !== strpos( $attrs['class'], 'aiic-ignore' ) ) {
			return $image;
		}

		$metadata = $this->build_metadata( absint( $attrs['blog_id'] ?? 1 ), absint( $attrs['_image_id'] ) );

		if ( ! $metadata ) {
			return $image;
		}

		// remove important attributes from image
		$attrs_to_strip = [
			'id',
			'class',
			// anything beyond...
		];

		$stripped_image = $image;

		foreach ( $attrs_to_strip as $attr ) {
			if ( empty( $attrs[ $attr ] ) ) {
				continue;
			}

			$find = [
				// account for both single and double quotes
				sprintf( '%s="%s"', $attr, $attrs[ $attr ] ),
				sprintf( "%s='%s'", $attr, $attrs[ $attr ] ),
			];

			$stripped_image = str_replace( $find, '', $stripped_image );
		}

		// cleanup whitespace
		$stripped_image = preg_replace( '/\s+/', ' ', $stripped_image );

		// create div with extracted attrs + additional classname
		$wrapper_open  = sprintf(
			'<div id="%s" class="%s has-caption">',
			esc_attr( $attrs['id'] ?? '' ),
			esc_attr( $attrs['class'] ?? '' ),
		);
		$wrapper_close = '</div>';

		// insert stripped image + span with caption
		return $wrapper_open . $stripped_image . $metadata . $wrapper_close;
	}

	/**
	 * Add image credit to styled items
	 *
	 * @param string $image the current image
	 *
	 * @return string
	 */
	protected function add_style_caption( string $image = '' ): string {
		$image_data = $this->get_id( $image );

		if ( ! $image_data ) {
			return '';
		}

		return $this->build_metadata( absint( $image_data['blog_id'] ), absint( $image_data['id'] ) );
	}

	/**
	 * Build the image metadata
	 *
	 * @param int $blog_id  the image's blog ID
	 * @param int $image_id the image id
	 *
	 * @return string
	 */
	protected function build_metadata( int $blog_id, int $image_id ): string {
		switch_to_blog( $blog_id );
		$image    = new Get_Image_Data( $image_id );
		$metadata = $image->metadata( include_caption: false );
		restore_current_blog();

		return $metadata;
	}

	/**
	 * Parse image data
	 *
	 * @param string $image the image to parse
	 *
	 * @return array<int|string,mixed>
	 */
	protected function parse( string $image = '' ): array {
		$valid_attrs = [
			// phpcs:disable
			'src', 'id', 'class', 'alt',
			'srcset', 'sizes', 'crossorigin',
			'decoding', 'usemap', 'ismap', 'width', 'height',
			'referrerpolicy', 'longdesc', 'data-[a-z]+?',
			// phpcs:enable
		];

		$parsed_attrs = [];

		foreach ( $valid_attrs as $attr ) {
			// indices:- 1: attr, 3: value
			preg_match( sprintf( '/\b(%s)=([\'"])([^\2]*?)\2/i', $attr ), $image, $matched );

			if ( empty( $matched[1] ) ) {
				continue;
			}

			$parsed_attrs[ $matched[1] ] = $matched[3];
		}

		if ( empty( $parsed_attrs ) ) {
			return [];
		}

		// skip finding image id for tags we should ignore - prevents caption output
		if ( false !== strpos( $parsed_attrs['class'] ?? '', 'aiic-ignore' ) ) {
			return $parsed_attrs;
		}

		if ( isset( $parsed_attrs['src'] ) && trim( $parsed_attrs['src'] ) ) {
			$image_data = $this->get_id( $parsed_attrs['src'] );

			if ( $image_data ) {
				$parsed_attrs['_image_id'] = $image_data['id'];
				$parsed_attrs['_blog_id']  = $image_data['blog_id'];
			}
		}

		return $parsed_attrs;
	}

	/**
	 * Retrieve an image's ID
	 *
	 * @param string $url the image url
	 *
	 * @return array<string,int>|null
	 */
	protected function get_id( string $url = '' ): ?array {
		$data = $this->url_to_id( $url );

		if ( $data ) {
			return $data;
		}

		// try again with dimensions stripped
		if ( image_has_dimensions( $url ) ) {
			return $this->get_id( strip_image_dimensions( $url ) );
		}

		// try again with wp-scaled suffix
		if ( ! image_has_scale( $url ) ) {
			return $this->get_id( add_scaled_to_image( $url ) );
		}

		return $this->get_id_from_path( $url );
	}

	/**
	 * Attempt to lookup image ID by path, rather than full URI
	 *
	 * @param string $url the url of the image
	 *
	 * @return array<string,int>|null
	 */
	protected function get_id_from_path( string $url ): ?array {
		if ( ! image_has_yearmonth( $url ) ) {
			return null;
		}

		// uses correct wp format; try without the domain
		$path = image_get_yearmonth( $url );

		if ( ! $path ) {
			return null;
		}

		return $this->url_to_id( $url );
	}

	/**
	 * Cross-site compatible, cached version of {attachment_url_to_postid()}
	 *
	 * @param string $url the image URI
	 *
	 * @return array<string,int>|null
	 */
	protected function url_to_id( string $url ): ?array {
		$image = get_image_from_cache( $url );

		if ( null !== $image ) {
			return $image;
		}

		if ( is_multisite() ) {
			return $this->url_to_id_multisite( $url );
		}

		// base uploads dir, not including /site/n/
		$uploads_dir = wp_upload_dir()['baseurl'];
		$uploads_dir = wp_parse_url( $uploads_dir, PHP_URL_PATH ) ?: $uploads_dir;
		$uploads_dir = strstr( trim( $uploads_dir, '/' ), '/' );

		// if it's a hotlink, don't attempt to locate
		if ( false === strpos( home_url(), $url ) && false === strpos( $url, $uploads_dir ) ) {
			return [
				'id'      => add_image_to_cache( 0, 'none', $url, 0 ),
				'blog_id' => 0,
			];
		}

		$image = lookup_image_id( $url );

		if ( ! $image ) {
			return null;
		}

		return $this->store_image_sizes( $image );
	}

	/**
	 * Attempt to locate image on the "base" site in a multisite
	 *
	 * @param string $url the image URI
	 *
	 * @return array<string,int>|null
	 */
	protected function url_to_id_multisite( string $url ): ?array {
		// is mgm active? - do we check this here?
		switch_to_blog( 1 );
		// base uploads dir, not including /site/n/
		$uploads_dir = wp_upload_dir()['baseurl'];
		restore_current_blog();

		$uploads_dir = wp_parse_url( $uploads_dir, PHP_URL_PATH ) ?: $uploads_dir;
		$uploads_dir = strstr( trim( $uploads_dir, '/' ), '/' );

		// if it's a hotlink, don't attempt to locate
		if ( false === strpos( home_url(), $url ) && false === strpos( $url, $uploads_dir ) ) {
			return [
				'id'      => add_image_to_cache( 0, 'none', $url, 0 ),
				'blog_id' => 0,
			];
		}

		// uri doesn't include /sites/n/ upload path - try the base site
		if ( false === mb_strpos( $url, sprintf( '/sites/%s', get_current_blog_id() ), 0, 'UTF-8' ) ) {
			$current_site = get_site( get_current_blog_id() );
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- third-party
			$target_site = get_site( apply_filters( 'global_media.site_id', BLOG_ID_CURRENT_SITE ) );

			$url = str_replace( $current_site->path, $target_site->path, $url );

			return lookup_image_id( $url );
		}

		$image = lookup_image_id( $url );

		if ( null !== $image ) {
			return $image;
		}

		// this is a last resort
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.attachment_url_to_postid_attachment_url_to_postid
		$id = attachment_url_to_postid( $url );

		if ( 0 === $id ) {
			return null;
		}

		return [
			'id'      => $id,
			'blog_id' => get_current_blog_id(),
		];
	}

	/**
	 * Retrieve the correct site for an image URL
	 *
	 * @param string $url the url to check
	 *
	 * @return ?int
	 */
	protected function get_site_id( string $url ): ?int {
		if ( 0 === strpos( $url, home_url() ) ) {
			return absint( get_current_blog_id() );
		}

		$sites = get_sites();

		if ( ! is_array( $sites ) ) {
			return null;
		}

		/**
		 * \WP_Site
		 *
		 * @var \WP_Site $site site object
		 */
		foreach ( $sites as $site ) {
			$home = get_home_url( absint( $site->blog_id ) );

			if ( 0 === strpos( $url, $home ) ) {
				return absint( $site->blog_id );
			}
		}

		return null;
	}

	/**
	 * Store all sizes for an image in the current site's DB
	 *
	 * @param array<string,int> $image the found image data
	 *
	 * @return array<string,int>
	 */
	protected function store_image_sizes( array $image ): array {
		switch_to_blog( $image['blog_id'] );

		$urls = get_all_sizes_for_image( $image['id'] );

		foreach ( $urls as $size => $url ) {
			store_image_id( $image['id'], $image['blog_id'], $size, $url );
			add_image_to_cache( $image['id'], $size, $url, $image['blog_id'] );
		}

		restore_current_blog();

		return [
			'id'      => $image['id'],
			'blog_id' => $image['blog_id'],
		];
	}

}
