<?php

declare( strict_types = 1 );

namespace Amnesty\Image_Credit;

/**
 * Handle lookup table data CRUD ops when attachment CRUD ops happen
 */
class Media_Library_Integration {

	/**
	 * Bind hooks
	 */
	public function __construct() {
		add_action( 'add_attachment', [ $this, 'create' ] );
		add_action( 'edit_attachment', [ $this, 'update' ] );
		add_action( 'delete_attachment', [ $this, 'delete' ] );
	}

	/**
	 * Handle image creation
	 *
	 * @param int $id the attachment ID
	 *
	 * @return void
	 */
	public function create( int $id ): void {
		$urls = get_all_sizes_for_image( $id );

		foreach ( $urls as $size => $url ) {
			store_image_id( $id, get_current_blog_id(), $size, $url );
		}

		update_post_meta( $id, 'ai_is_indexed', '1' );
	}

	/**
	 * Handle image updation
	 *
	 * @param int $id the attachment id
	 *
	 * @return void
	 */
	public function update( int $id ): void {
		$this->delete( $id );
		$this->create( $id );
	}

	/**
	 * Handle image deletion
	 *
	 * @param int $id the attachment id
	 *
	 * @return void
	 */
	public function delete( int $id ): void {
		remove_image_id( $id );
		delete_post_meta( $id, 'ai_is_indexed' );
	}

}
