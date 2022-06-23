<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Share_Fonts_Admin_Menu_Page {
	public function __construct() {
		add_action( 'admin_init', array( $this, 'admin_init_action' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu_action' ), 99 );
	}

	public function admin_init_action() {

	}

	public function admin_menu_action() {

	}
}

new Share_Fonts_Admin_Menu_Page();