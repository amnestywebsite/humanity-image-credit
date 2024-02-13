<?php

declare( strict_types = 1 );

namespace Amnesty\Image_Credit\Cli;

use WP_CLI;
use WP_Post;
use WP_Query;
use WP_Site;

use function Amnesty\Image_Credit\get_all_sizes_for_image;
use function Amnesty\Image_Credit\store_image_id;

WP_CLI::add_command( 'ai index', Index_Command::class );

/**
 * Indexes content for performance.
 */
class Index_Command extends Base_Command {

	/**
	 * Number of media items to process at a time
	 */
	protected const PER_PAGE = 100;

	/**
	 * Positional args store
	 *
	 * @var array<int,mixed>
	 */
	protected array $args = [];

	/**
	 * Named args store
	 *
	 * @var array<string,mixed>
	 */
	protected array $assoc_args = [];

	/**
	 * Index media into a quick lookup table.
	 *
	 * ## OPTIONS
	 *
	 * [--network]
	 * : whether to run for all sites on the network
	 *
	 * [--reindex]
	 * : whether to reindex from scratch
	 *
	 * ## EXAMPLES
	 *
	 *   wp ai index media
	 *   wp ai index media --network
	 *
	 * @subcommand media
	 * @when after_wp_load
	 *
	 * @param array<int,mixed>    $args       positional arguments
	 * @param array<string,mixed> $assoc_args named arguments
	 *
	 * @return int
	 */
	public function media( array $args, array $assoc_args ): int {
		$this->args       = $args;
		$this->assoc_args = $assoc_args;

		$reindex = isset( $assoc_args['reindex'] );

		WP_CLI::line( escapeshellarg( __( 'This will take a while.', 'amnesty' ) ) );

		if ( array_key_exists( 'network', $assoc_args ) ) {
			$sites = get_sites();

			if ( is_array( $sites ) ) {
				foreach ( get_sites() as $site ) {
					switch_to_blog( absint( $site->blog_id ) );
					$this->index_for_site( $site, $reindex );
					restore_current_blog();
				}
			}

			return 0;
		}

		$this->index_for_site( get_site( get_current_blog_id() ), $reindex );

		return 0;
	}

	/**
	 * Index media for a single site
	 *
	 * @param WP_Site $site    the site to index
	 * @param bool    $reindex whether to truncate table before indexing
	 *
	 * @return void
	 */
	protected function index_for_site( WP_Site $site, bool $reindex ): void {
		WP_CLI::line( escapeshellarg( sprintf( '%s %s (%d):', __( 'Indexing media on', 'amnesty' ), $site->blogname, $site->blog_id ) ) );

		global $wpdb;

		if ( $reindex ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $this->get_table_name(), [ 'blog_id' => $site->blog_id ], [ '%d' ] );
		}

		$this->expensive_task_start();

		$quantity  = $this->count();
		$num_pages = absint( ceil( $quantity / self::PER_PAGE ) );
		$progress  = \WP_CLI\Utils\make_progress_bar( 'Progress', $quantity );

		for ( $i = 1; $i <= $num_pages; $i++ ) {
			$this->process_page( $i, $progress );
			$this->expensive_task_did_page();
		}

		$this->expensive_task_finish();

		if ( method_exists( $progress, 'tick' ) ) {
			$progress->finish();
		}
	}

	/**
	 * Get the table name for the current context
	 *
	 * @return string
	 */
	protected function get_table_name(): string {
		return "{$GLOBALS['wpdb']->base_prefix}ai_media_lookup";
	}

	/**
	 * Count number of items to process
	 *
	 * @return int
	 */
	protected function count(): int {
		$query = new WP_Query(
			[
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				'posts_per_page' => 1,
			] 
		);

		return absint( $query->found_posts );
	}

	/**
	 * Process a page of media
	 *
	 * @param int                                 $page       the page number
	 * @param \cli\progress\Bar|\WP_CLI\NoOp|null $progress the active progress bar
	 *
	 * @return void
	 */
	protected function process_page( int $page = 1, $progress = null ): void {
		$items = $this->get_page( $page );

		foreach ( $items as $item ) {
			if ( ! $this->should_index_item( $item ) ) {
				if ( method_exists( $progress, 'tick' ) ) {
					$progress->tick();
				}
				continue;
			}

			delete_post_meta( $item->ID, 'ai_is_indexed' );

			$urls = get_all_sizes_for_image( $item->ID );

			foreach ( $urls as $size => $url ) {
				store_image_id( $item->ID, get_current_blog_id(), $size, $url );
			}

			update_post_meta( $item->ID, 'ai_is_indexed', '1' );

			if ( method_exists( $progress, 'tick' ) ) {
				$progress->tick();
			}
		}
	}

	/**
	 * Retrieve a page of media to process
	 *
	 * @param int $page the page number
	 *
	 * @return array<int,WP_Post>
	 */
	protected function get_page( int $page = 1 ): array {
		$query = new WP_Query(
			[
				'paged'          => $page,
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'post_type'      => 'attachment',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'posts_per_page' => self::PER_PAGE,
			]
		);

		return $query->posts;
	}

	/**
	 * Check whether this item needs to be processed
	 *
	 * @param WP_Post $item the item to check
	 *
	 * @return bool
	 */
	protected function should_index_item( WP_Post $item ): bool {
		if ( isset( $this->assoc_args['reindex'] ) ) {
			return true;
		}

		if ( '1' === get_post_meta( $item->ID, 'ai_is_indexed', true ) ) {
			return false;
		}

		return true;
	}

}
