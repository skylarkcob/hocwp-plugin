<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! trait_exists( 'Share_Fonts_Frontend_Custom' ) ) {
	// Load custom front-end functions
	require_once dirname( __FILE__ ) . '/trait-frontend.php';
}

class Share_Fonts_Frontend extends Share_Fonts {
	use Share_Fonts_Frontend_Custom;

	// Default plugin variable: Plugin single instance.
	protected static $frontend_instance;

	/*
	 * Default plugin function: Check single instance.
	 */
	public static function get_instance() {
		if ( ! ( self::$frontend_instance instanceof self ) ) {
			self::$frontend_instance = new self();
		}

		return self::$frontend_instance;
	}

	/*
	 * Default plugin function: Plugin construct.
	 */
	public function __construct() {
		parent::__construct();
		$this->main_load();
	}

	// Custom functions should be declared below this line.

	public function main_load() {
		add_action( 'wp', array( $this, 'wp_action' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'custom_wp_enqueue_scripts_action' ) );
	}

	public function wp_action() {

	}

	/*
	 * Default plugin function: Load styles and scripts on frontend.
	 */
	public function custom_wp_enqueue_scripts_action() {
		wp_enqueue_style( $this->textdomain . '-style', $this->custom_url . '/css/frontend.css' );
		wp_enqueue_script( $this->textdomain, $this->custom_url . '/js/frontend.js', array( 'jquery' ), false, true );

		$l10n = array(
			'textDomain'      => $this->textdomain,
			'ajaxUrl'         => $this->get_ajax_url(),
			'optionName'      => $this->get_option_name(),
			'text'            => array(

			)
		);

		wp_localize_script( $this->textdomain, 'sFonts', $l10n );
	}
}

function Share_Fonts_Frontend() {
	return Share_Fonts_Frontend::get_instance();
}

Share_Fonts_Frontend();