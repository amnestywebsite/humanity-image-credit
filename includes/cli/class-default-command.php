<?php

declare( strict_types = 1 );

namespace Amnesty\Image_Credit\Cli;

use WP_CLI;

WP_CLI::add_command( 'ai', Default_Command::class );

/**
 * A suite of commands designed specifically for Amnesty International.
 */
class Default_Command extends Base_Command {

	/**
	 * Display version information about installed Amnesty International products.
	 *
	 * ## EXAMPLES
	 *
	 *   wp ai
	 *
	 * @when after_wp_load
	 *
	 * @return int
	 */
	public function version(): int {
		$theme = wp_get_theme();

		WP_CLI::line( sprintf( '%s: %s', esc_html__( 'Theme Version', 'amnesty' ), $theme['Version'] ) );

		return 0;
	}

}
