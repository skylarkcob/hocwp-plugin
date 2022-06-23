<?php
/**
 * Plugin Name: Share Fonts
 * Plugin URI: http://hocwp.net/project/
 * Description: This plugin is created by HocWP Team.
 * Author: HocWP Team
 * Used For: example.com
 * Last Updated: 22/06/2022
 * Coder: laidinhcuongvn@gmail.com
 * Version: 1.0.0
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Author URI: http://facebook.com/hocwpnet/
 * Text Domain: share-fonts
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// Load core functions
require_once dirname( __FILE__ ) . '/core/core.php';

class Share_Fonts extends Share_Fonts_Core {
	// Default plugin variable: Plugin single instance.
	protected static $instance;

	// Default plugin variable: Plugin file path.
	protected $plugin_file = __FILE__;

	/*
	 * Default plugin function: Check single instance.
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/*
	 * Default plugin function: Plugin construct.
	 */
	public function __construct() {
		parent::__construct();

		add_action( 'plugins_loaded', array( $this, 'run_init_action' ), 11 );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 999 );

		add_filter( 'hocwp_theme_backup_wp_content_folders', array( $this, 'add_folder_for_auto_backup' ) );
		add_filter( 'paginate_links', array( $this, 'paginate_links_filter' ) );

		$on = $this->get_option_name();

		if ( empty( $on ) ) {
			parent::re_init();
		}

		add_action( 'init', array( $this, 'init_action' ) );

		add_filter( 'wp_check_filetype_and_ext', array( $this, 'wp_check_filetype_and_ext_filter' ), 10, 2 );
		add_filter( 'file_is_displayable_image', array( $this, 'file_is_displayable_image_filter' ), 10, 2 );
		add_filter( 'wp_get_attachment_metadata', array( $this, 'wp_get_attachment_metadata_filter' ), 10, 2 );
		add_filter( 'upload_mimes', array( $this, 'upload_mimes_filter' ) );

		// Default load action
		add_action( 'after_setup_theme', array( $this, 'load' ) );
	}

	public function paginate_links_filter( $link ) {
		$link  = str_replace( '#038;', '&', $link );
		$parts = parse_url( $link );

		if ( isset( $parts['query'] ) ) {
			parse_str( $parts['query'], $params );

			if ( $this->array_has_value( $params ) ) {
				$link = add_query_arg( $params, $link );
			}
		}

		$link = remove_query_arg( 'wp_http_referer', $link );

		return $link;
	}

	/*
	 * Default plugin function: Load plugin environment.
	 */
	public function load() {
		if ( method_exists( $this, 'custom_global_scripts_action' ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'custom_global_scripts_action' ) );
			add_action( 'login_enqueue_scripts', array( $this, 'custom_global_scripts_action' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'custom_global_scripts_action' ) );
		}

		if ( method_exists( $this, 'custom_load' ) ) {
			$this->custom_load();
		}
	}

	// Custom functions should be declared below this line.

	// Default plugin variable: Plugin default options.
	public $option_defaults = array(
	);

	public $content_dir = true;
}

function Share_Fonts() {
	return Share_Fonts::get_instance();
}

add_action( 'plugins_loaded', function () {
	if ( defined( 'WC_PLUGIN_FILE' ) ) {
		require_once dirname( __FILE__ ) . '/custom/load.php';
	}
} );