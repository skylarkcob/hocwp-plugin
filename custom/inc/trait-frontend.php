<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! trait_exists( 'Share_Fonts_Custom' ) ) {
	// Load custom functions
	require_once dirname( __FILE__ ) . '/trait-functions.php';
}

trait Share_Fonts_Frontend_Custom {
	use Share_Fonts_Custom;
}