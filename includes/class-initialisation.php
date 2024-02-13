<?php

declare( strict_types = 1 );

namespace Amnesty\Image_Credit;

use WP_Site;

/**
 * Functionality that runs on intialisation
 */
class Initialisation {

	/**
	 * Bind hooks
	 */
	public function __construct() {
		register_activation_hook( AI_IMAGE_CREDIT_PLUGIN_FILE, [ $this, 'activate_plugin' ] );
	}

	/**
	 * Run plugin activation code
	 *
	 * @return void
	 */
	public function activate_plugin(): void {
		$this->create_table();
	}

	/**
	 * Run site deletion code
	 *
	 * Deletes all stored images for that site ID
	 *
	 * @param \WP_Site $site the site being deleted
	 *
	 * @return void
	 */
	public function delete_site( WP_Site $site ): void {
		/**
		 * WP DB
		 *
		 * @var \wpdb $wpdb the global WP DB object
		 */
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( "{$wpdb->base_prefix}ai_media_lookup", [ 'blog_id' => $site->blog_id ], [ '%d' ] );
	}

	/**
	 * Create the database table
	 *
	 * @return void
	 */
	protected function create_table(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		/**
		 * WP DB
		 *
		 * @var \wpdb $wpdb the global WP DB object
		 */
		global $wpdb;

		dbDelta(
			"CREATE TABLE IF NOT EXISTS `{$wpdb->base_prefix}ai_media_lookup` (
				`id` bigint(20) unsigned NOT NULL,
				`blog_id` smallint(8) unsigned NOT NULL,
				`size` varchar(50) COLLATE {$wpdb->collate} NOT NULL,
				`uri` tinytext COLLATE {$wpdb->collate} NOT NULL,
				`hash` varchar(255) COLLATE {$wpdb->collate} NOT NULL,
				KEY `id` (`id`),
				UNIQUE KEY `hash` (`hash`) USING BTREE
			) ENGINE=InnoDB DEFAULT CHARSET={$wpdb->charset} COLLATE={$wpdb->collate};"
		);
	}

}
