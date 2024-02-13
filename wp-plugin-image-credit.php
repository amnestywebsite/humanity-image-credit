<?php

/**
 * Plugin Name:       Humanity Image Credit
 * Plugin URI:        https://github.com/amnestywebsite/humanity-image-credit
 * Description:       Handles automatic rendering of an image's "credit" (description field) on images on the frontend
 * Version:           1.0.0
 * Author:            Amnesty International
 * Author URI:        https://www.amnesty.org
 * License:           GPLv2
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aiic
 * Domain Path:       /languages
 * Network:           true
 * Requires PHP:      8.2.0
 * Requires at least: 5.6.0
 * Tested up to:      6.4.2
 */

declare( strict_types = 1 );

namespace Amnesty\Image_Credit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AI_IMAGE_CREDIT_PLUGIN_FILE', __FILE__ );

require_once realpath( __DIR__ . '/functions.php' );
require_once realpath( __DIR__ . '/includes/class-initialisation.php' );
require_once realpath( __DIR__ . '/includes/class-media-library-integration.php' );
require_once realpath( __DIR__ . '/includes/class-frontend-integration.php' );

if ( class_exists( 'WP_CLI', false ) ) {
	require_once realpath( __DIR__ . '/includes/cli/class-base-command.php' );
	require_once realpath( __DIR__ . '/includes/cli/class-default-command.php' );
	require_once realpath( __DIR__ . '/includes/cli/class-index-command.php' );
}

new Initialisation();
new Media_Library_Integration();
new Frontend_Integration();

if ( ! function_exists( __NAMESPACE__ . '\amnesty_image_credit_theme_requirements' ) ) {
	/**
	 * Check that the theme is compatible with this plugin.
	 *
	 * @return void
	 */
	function amnesty_image_credit_theme_requirements() {
		$theme  = wp_get_theme();
		$themes = [
			'Amnesty Core',
			'Amnesty WP Theme',
			'Amnesty WP Child Theme',
			'Humanity Theme',
			'Humanity Child Theme',
		];

		// site is using correct theme for this plugin
		if ( in_array( $theme['Name'], $themes, true ) ) {
			return;
		}

		// theme version is high enough
		if ( version_compare( $theme['Version'], '1.21.7', '>' ) ) {
			return;
		}

		// we're not in production, so allow it to load
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'production' !== WP_ENVIRONMENT_TYPE ) {
			return;
		}

		// wrong theme or wrong version - plugin would break frontend of site
		deactivate_plugins( plugin_basename( AI_IMAGE_CREDIT_PLUGIN_FILE ), false, is_multisite() );
	}
}

add_action( 'all_admin_notices', __NAMESPACE__ . '\amnesty_image_credit_theme_requirements' );
