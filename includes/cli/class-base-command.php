<?php

declare( strict_types = 1 );

namespace Amnesty\Image_Credit\Cli;

use WP_CLI_Command;

/**
 * Base class for CLI commands for the Amnesty theme
 */
class Base_Command extends WP_CLI_Command {

	/**
	 * Call at the start of an expensive task
	 *
	 * @return void
	 */
	protected function expensive_task_start(): void {
		wp_defer_term_counting( true );
	}

	/**
	 * Call at the end of a "page" during an expensive task
	 *
	 * @return void
	 */
	protected function expensive_task_did_page(): void {
		$this->clear_local_object_cache();
		$this->clear_query_log();

		// give the cache a chance to revalidate
		sleep( 2 );
	}

	/**
	 * Call at the end of an expensive task
	 *
	 * @return void
	 */
	protected function expensive_task_finish(): void {
		wp_defer_term_counting( false );
	}

	/**
	 * Reset the *local* WordPress object cache
	 *
	 * Does not impact memcache
	 *
	 * @return void
	 */
	protected function clear_local_object_cache(): void {
		global $wp_object_cache;

		if ( ! is_object( $wp_object_cache ) ) {
			return;
		}

		$wp_object_cache->group_ops      = [];
		$wp_object_cache->memcache_debug = [];
		$wp_object_cache->cache          = [];

		if ( method_exists( $wp_object_cache, '__remoteset' ) ) {
			$wp_object_cache->__remoteset(); // important
		}
	}

	/**
	 * Clear the WP_Query query log
	 *
	 * @return void
	 */
	protected function clear_query_log(): void {
		global $wpdb;

		$wpdb->queries = [];
	}

}
