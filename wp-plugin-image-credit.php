<?php

/**
 * Plugin Name:       Humanity Image Credit
 * Plugin URI:        https://github.com/amnestywebsite/humanity-image-credit
 * Description:       Handles automatic rendering of an image's "credit" (description field) on images on the frontend
 * Version:           1.0.1
 * Author:            Amnesty International
 * Author URI:        https://www.amnesty.org
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aiic
 * Domain Path:       /languages
 * Network:           true
 * Requires PHP:      8.2.0
 * Requires at least: 5.6.0
 * Tested up to:      6.6.2
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
