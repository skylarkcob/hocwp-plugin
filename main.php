<?php
/**
 * Plugin Name: Auto Add Post
 * Plugin URI: http://hocwp.net/project/
 * Description: This plugin is created by HocWP Team.
 * Author: HocWP Team
 * Version: 1.0.0
 * Author URI: http://hocwp.net/
 * Text Domain: auto-add-post
 * Domain Path: /languages/
 */

require_once dirname( __FILE__ ) . '/hocwp/class-hocwp-plugin.php';

class HOCWP_Plugin_Auto_Add_Post extends HOCWP_Plugin_Core {

	public function __construct( $file_path ) {
		$this->set_textdomain( 'auto-add-post' );

		parent::__construct( $file_path );

		$labels = array(
			'action_link_text' => __( 'Settings', 'auto-add-post' ),
			'options_page'     => array(
				'page_title' => __( 'Auto Add Post by HocWP Team', 'auto-add-post' ),
				'menu_title' => __( 'Auto Add Post', 'auto-add-post' )
			),
			'license'          => array(
				'notify'      => array(
					'email_subject' => __( 'Notify plugin license', 'auto-add-post' )
				),
				'die_message' => __( 'Your plugin is blocked.', 'auto-add-post' ),
				'die_title'   => __( 'Plugin Invalid License', 'auto-add-post' )
			)
		);

		$this->set_labels( $labels );
		$this->set_option_name( 'hocwp_auto_add_post' );
		$this->init();

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		}
	}

	public function wp_enqueue_scripts() {
		wp_enqueue_script( 'hocwp-aap-custom', $this->get_baseurl() . '/js/custom' . HP()->js_suffix(), array( 'jquery' ), false, true );

		$l10n = array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'interval' => HOCWP_AAP_INTERVAL
		);

		wp_localize_script( 'hocwp-aap-custom', 'hocwpAAP', $l10n );
	}

	public function admin_notices() {

	}

	public function admin_init() {
		$args = array(
			'type' => 'email'
		);

		$this->add_settings_field( 'auto_author_email', __( 'Email', 'auto-add-post' ), array(
			$this,
			'admin_setting_field_input'
		), 'default', $args );
	}


}

global $hocwp_plugin;

if ( ! is_object( $hocwp_plugin ) ) {
	$hocwp_plugin = new stdClass();
}

$hocwp_plugin->auto_add_post = new HOCWP_Plugin_Auto_Add_Post( __FILE__ );