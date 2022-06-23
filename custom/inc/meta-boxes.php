<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Share_Fonts_Meta_Boxes {
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes_action' ) );
		add_action( 'save_post', array( $this, 'save_post_action' ) );
	}

	public function add_meta_boxes_action( $post_type ) {

	}

	public function save_post_action( $post_id ) {

	}
}

new Share_Fonts_Meta_Boxes();