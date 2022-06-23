<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Share_Fonts_Constants {
	const NONCE = 'sf_nonce';

	// Plugin meta data
	const TEXT_DOMAIN = 'share-fonts';

	// Custom Post Type
	const CPT_FONTS = 'font';

	// Post meta
	const PM_INSTALLED = 'installed';

	// Admin page
	const AP_RM_UPDATE = 'remote_update';

	// API params
	const API_ACTION = 'wp_remote_manage';

	protected static $instance;

	public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

function Share_Fonts_Constants() {
	return Share_Fonts_Constants::get_instance();
}