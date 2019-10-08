<?php
/**
 * Plugin Name: Auto Fetch Post
 * Plugin URI: http://hocwp.net/project/
 * Description: This plugin is created by HocWP Team.
 * Author: HocWP Team
 * Used For: example.com
 * Last Updated: 08/10/2019
 * Coder: laidinhcuongvn@gmail.com
 * Version: 1.0.0
 * Author URI: http://facebook.com/hocwpnet/
 * Text Domain: auto-fetch-post
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once dirname( __FILE__ ) . '/core/core.php';

class Auto_Fetch_Post extends Auto_Fetch_Post_Core {
	// Default plugin variable
	protected static $instance;

	// Default plugin variable
	protected $plugin_file = __FILE__;

	// Default plugin variable
	public $option_defaults = array();

	/*
	 * Default plugin function
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/*
	 * Default plugin function
	 */
	public function __construct() {
		parent::__construct();

		if ( self::$instance instanceof self ) {
			return;
		}

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'custom_admin_init_action' ) );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'custom_wp_enqueue_scripts_action' ) );
		}
	}

	/*
	 * Default plugin function
	 */
	public function custom_wp_enqueue_scripts_action() {

	}

	public function load_more_button() {
		ob_start();
		?>
		<button type="button"
		        class="bnt-blue load-more-button real-btn"
		        data-text="<?php echo esc_attr( __( 'Load more', $this->textdomain ) ); ?>"
		        data-loading-text="<?php echo esc_attr( __( 'Loading more', $this->textdomain ) ); ?>"><?php _e( 'Load more', $this->textdomain ); ?></button>
		<?php
		return ob_get_clean();
	}

	/*
	 * Default plugin function
	 */
	public function custom_admin_init_action() {
		$args = array(
			'description' => __( 'Add body class you want to skip load more button. Each class separate by commas.', $this->textdomain )
		);

		$this->add_settings_field( 'skip_class', __( 'Skip Body Class', $this->textdomain ), 'admin_setting_field_textarea', 'default', $args );

		$args = array(
			'type' => 'number'
		);

		$this->add_settings_field( 'auto_load_offset', __( 'Auto Load Offset', $this->textdomain ), 'admin_setting_field_input', 'default', $args );
	}
}

function Auto_Fetch_Post() {
	return Auto_Fetch_Post::get_instance();
}

add_action( 'plugins_loaded', function () {
	Auto_Fetch_Post();
} );