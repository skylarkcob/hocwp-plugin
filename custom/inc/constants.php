<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Woo_Checkout_Images_Constants {
	const NONCE = 'rm_nonce';

	// Plugin meta data
	const TEXT_DOMAIN = 'remote-manage';

	// Custom Post Type
	const CPT_LIST_SITES = 'list_sites';

	// Post meta
	const PM_INSTALLED = 'installed';
	const PM_FTP_HOST = 'ftp_host';
	const PM_FTP_USER = 'ftp_username';
	const PM_FTP_PASS = 'ftp_password';
	const PM_FTP_PORT = 'ftp_port';

	// Admin page
	const AP_RM_UPDATE = 'remote_update';

	// API params
	const API_ACTION = 'wp_remote_manage';
	const API_DA_INSTALL = 'install';
	const API_DA_CHECK_INSTALL = 'check_installed';
	const API_DA_INSTALL_PLUGIN = 'install_plugin';
	const API_DA_LIST_PLUGIN = 'list_plugin';
	const API_DA_UPDATE_PLUGIN = 'udpate_plugin';

	protected static $instance;

	public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}

function Woo_Checkout_Images_Constants() {
	return Woo_Checkout_Images_Constants::get_instance();
}