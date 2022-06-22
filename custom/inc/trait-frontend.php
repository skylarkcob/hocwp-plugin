<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! trait_exists( 'Woo_Checkout_Images_Custom' ) ) {
	// Load custom functions
	require_once dirname( __FILE__ ) . '/trait-functions.php';
}

trait Woo_Checkout_Images_Frontend_Custom {
	use Woo_Checkout_Images_Custom;
}