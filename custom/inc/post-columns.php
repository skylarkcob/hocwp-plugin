<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Woo_Checkout_Images_Post_Columns {
	public function __construct() {
		add_filter( 'manage_posts_columns', array( $this, 'manage_posts_columns_filter' ), 10, 2 );
		add_action( 'manage_posts_custom_column', array( $this, 'manage_posts_custom_column_action' ), 10, 2 );
	}

	public function manage_posts_columns_filter( $posts_columns, $post_type ) {
		return $posts_columns;
	}

	public function manage_posts_custom_column_action( $column_name, $post_id ) {

	}
}

new Woo_Checkout_Images_Post_Columns();