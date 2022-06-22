<?php
defined( 'ABSPATH' ) || exit;

require_once dirname( __FILE__ ) . '/inc/constants.php';

if ( is_admin() ) {
	require_once dirname( __FILE__ ) . '/inc/class-plugin-admin.php';
} else {
	require_once dirname( __FILE__ ) . '/inc/class-plugin-frontend.php';
}