<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! trait_exists( 'Share_Fonts_Admin_Core' ) ) {
	// Load core admin functions
	require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/core/trait-admin.php';
}

if ( ! trait_exists( 'Share_Fonts_Backend_Custom' ) ) {
	// Load custom admin functions
	require_once dirname( __FILE__ ) . '/trait-backend.php';
}

class Share_Fonts_Admin extends Share_Fonts {
	use Share_Fonts_Admin_Core, Share_Fonts_Backend_Custom;

	// Default plugin variable: Plugin single instance.
	protected static $admin_instance;

	/*
	 * Default plugin function: Check single instance.
	 */
	public static function get_instance() {
		if ( ! ( self::$admin_instance instanceof self ) ) {
			self::$admin_instance = new self();
		}

		return self::$admin_instance;
	}

	/*
	 * Default plugin function: Plugin construct.
	 */
	public function __construct() {
		parent::__construct();

		$version = phpversion();

		if ( version_compare( $this->require_php_version, $version, '>' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notices_require_php_version' ) );

			return;
		}

		add_action( 'admin_init', array( $this, 'admin_init_action' ), 20 );
		add_filter( 'plugin_action_links_' . $this->base_name, array( $this, 'action_links_filter' ), 20 );
		add_action( 'admin_menu', array( $this, 'admin_menu_action' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		if ( $this->array_has_value( $this->required_plugins ) ) {
			add_action( 'admin_notices', array( $this, 'required_plugins_notices' ) );
		}

		add_action( 'admin_footer', array( $this, 'admin_footer' ) );
		add_action( 'admin_footer', array( $this, 'created_by_log' ) );

		if ( $this->array_has_value( $this->one_term_taxonomies ) ) {
			add_action( 'save_post', array( $this, 'one_term_taxonomy_action' ), 99 );
			add_action( 'admin_notices', array( $this, 'one_term_taxonimes_notices' ) );
		}

		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu_action' ), 99 );

		$this->main_load();

		if ( method_exists( $this, 'run_custom_ajax_callback' ) ) {
			add_action( 'wp_ajax_' . $this->get_option_name(), array( $this, 'run_custom_ajax_callback' ) );
			add_action( 'wp_ajax_nopriv_' . $this->get_option_name(), array( $this, 'run_custom_ajax_callback' ) );
		}

		if ( method_exists( $this, 'sanitize_setting_filter' ) ) {
			add_filter( $this->textdomain . '_settings_save_data', array( $this, 'sanitize_setting_filter' ) );
		}
	}

	// Custom functions should be declared below this line.

	public function main_load() {
		add_action( 'admin_init', array( $this, 'custom_admin_init_action' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes_action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'custom_admin_enqueue_scripts' ) );
		add_action( 'pre_get_posts', array( $this, 'custom_pre_get_posts_action' ) );
		add_action( 'restrict_manage_posts', array( $this, 'custom_restrict_manage_posts_action' ) );
		add_filter( 'posts_where', array( $this, 'custom_posts_where_filter' ), 10, 2 );
	}

	public function custom_posts_where_filter( $where, $query ) {
		return $where;
	}

	public function custom_restrict_manage_posts_action( $post_type ) {

	}

	public function custom_pre_get_posts_action( $query ) {

	}

	public function add_meta_boxes_action( $post_type ) {

	}

	public function meta_box_callback( $post ) {

	}

	public function run_custom_ajax_callback() {
		$data = array(
			'message' => __( 'There was an error caused, please try again.', $this->textdomain )
		);

		$do_action = $_REQUEST['do_action'] ?? '';

		switch ( $do_action ) {
			default:
				break;
		}

		wp_send_json_error( $data );
	}

	/**
	 * Default plugin function: Add setting fields on admin_init action.
	 */
	public function custom_admin_init_action() {
		$args = array(
			'class' => 'widefat'
		);

		$this->add_settings_field( 'upload_description', __( 'Upload Description', $this->textdomain ), 'admin_setting_field_input', 'default', $args );
	}

	/**
	 * Sanitize option input data.
	 *
	 * @param array $input The data in $_POST variable.
	 *
	 * @return array The sanitized data.
	 */
	public function sanitize_setting_filter( $input ) {
		return $input;
	}

	public function custom_admin_enqueue_scripts() {
		global $pagenow;

		if ( 'post.php' == $pagenow || 'post-new.php' == $pagenow || 'upload.php' == $pagenow || 'edit.php' == $pagenow ) {
			wp_enqueue_style( $this->textdomain . '-style', $this->custom_url . '/css/backend.css' );

			wp_enqueue_script( $this->textdomain, $this->custom_url . '/js/backend.js', array( 'jquery' ), false, true );

			$l10n = array(
				'textDomain' => $this->textdomain,
				'ajaxUrl'    => $this->get_ajax_url(),
				'optionName' => $this->get_option_name(),
				'text'       => array(

				)
			);

			wp_localize_script( $this->textdomain, 'sFonts', $l10n );
		}
	}
}

function Share_Fonts_Admin() {
	return Share_Fonts_Admin::get_instance();
}

Share_Fonts_Admin();