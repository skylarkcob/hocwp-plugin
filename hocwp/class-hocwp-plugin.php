<?php
if ( ! defined( 'HOCWP_PLUGIN_CORE_VERSION' ) ) {
	define( 'HOCWP_PLUGIN_CORE_VERSION', '1.0.1' );
}

if ( class_exists( 'HOCWP_Plugin' ) ) {
	return;
}

class HOCWP_Plugin {
	private static $instance = null;

	private function __construct() {
	}

	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function debug( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			error_log( print_r( $value, true ) );
		} else {
			error_log( $value );
		}
	}

	private function css_or_js_suffix( $type = 'css' ) {
		return ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? '.' . $type : '.min.' . $type;
	}

	public function css_suffix() {
		return $this->css_or_js_suffix();
	}

	public function js_suffix() {
		return $this->css_or_js_suffix( 'js' );
	}

	public function create_meta_table() {
		global $wpdb;

		$charset_collate  = $wpdb->get_charset_collate();
		$max_index_length = 191;

		$table = $wpdb->prefix . 'hocwpmeta';

		$wpdb->hocwpmeta = $table;

		$sql = "CREATE TABLE IF NOT EXISTS $table (meta_id bigint(20) unsigned NOT NULL auto_increment, ";
		$sql .= "hocwp_id bigint(20) unsigned NOT NULL default '0', meta_key varchar(255) default NULL, ";
		$sql .= "meta_value longtext, object_type varchar(20) default 'post', ";
		$sql .= "PRIMARY KEY (meta_id), KEY hocwp_id (hocwp_id), ";
		$sql .= "KEY meta_key (meta_key($max_index_length))) $charset_collate;";

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		}

		dbDelta( $sql );
	}

	public function add_meta( $object_id, $meta_key, $meta_value, $object_type = 'post', $unique = false ) {
		$added = add_metadata( 'hocwp', $object_id, $meta_key, $meta_value, $unique );

		if ( $added ) {
			wp_cache_set( 'last_changed', microtime(), 'hocwp' );

			global $wpdb;
			$table = $wpdb->prefix . 'hocwpmeta';

			$sql = "UPDATE $table ";
			$sql .= "SET object_type = '$object_type' ";
			$sql .= "WHERE meta_id = $added";

			$wpdb->query( $sql );
		}

		return $added;
	}

	public function delete_meta( $object_id, $meta_key, $meta_value = '' ) {
		$deleted = delete_metadata( 'hocwp', $object_id, $meta_key, $meta_value );

		if ( $deleted ) {
			wp_cache_set( 'last_changed', microtime(), 'hocwp' );
		}

		return $deleted;
	}

	public function get_meta( $object_id, $key = '', $single = false ) {
		return get_metadata( 'hocwp', $object_id, $key, $single );
	}

	public function get_meta_type( $object_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hocwpmeta';

		$sql = "SELECT object_type FROM $table WHERE meta_id = %d";

		return $wpdb->get_var( $wpdb->prepare( $sql, $object_id ) );
	}

	public function update_meta( $object_id, $meta_key, $meta_value, $object_type = 'post', $prev_value = '' ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hocwpmeta';

		$sql = "SELECT meta_id FROM $table WHERE meta_key = %s AND hocwp_id = %d";

		$meta_ids = $wpdb->get_col( $wpdb->prepare( $sql, $meta_key, $object_id ) );

		if ( empty( $meta_ids ) ) {
			return $this->add_meta( $object_id, $meta_key, $meta_value, $object_type );
		}

		$updated = update_metadata( 'hocwp', $object_id, $meta_key, $meta_value, $prev_value );

		if ( $updated ) {
			wp_cache_set( 'last_changed', microtime(), 'hocwp' );
		}

		return $updated;
	}

	public function update_meta_cache( $object_ids ) {
		return update_meta_cache( 'hocwp', $object_ids );
	}

	public function has_meta( $object_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'hocwpmeta';

		$sql = "SELECT meta_key, meta_value, meta_id, object_id ";
		$sql .= "FROM $table ";
		$sql .= "WHERE object_id = %d ORDER BY meta_key, meta_id";

		return $wpdb->get_results( $wpdb->prepare( $sql, $object_id ), ARRAY_A );
	}
}

function HP() {
	return HOCWP_Plugin::get_instance();
}

abstract class HOCWP_Plugin_Core {
	protected $file;

	protected $basedir;
	protected $baseurl;

	protected $basedir_custom;
	protected $baseurl_custom;

	protected $basename;
	protected $option_name;
	protected $option_page_url;
	protected $labels;
	protected $setting_args;
	protected $options_page_callback;

	public function __construct( $file_path ) {
		if ( is_admin() ) {
			HP()->create_meta_table();
		}

		$version = get_option( 'hocwp_plugin_core_version' );

		if ( version_compare( $version, HOCWP_PLUGIN_CORE_VERSION, '<' ) ) {
			update_option( 'hocwp_plugin_core_version', HOCWP_PLUGIN_CORE_VERSION );
			set_transient( 'hocwp_theme_flush_rewrite_rules', 1 );
		}

		$this->file     = $file_path;
		$this->basedir  = dirname( $this->file );
		$this->baseurl  = plugins_url( '', $this->file );
		$this->basename = plugin_basename( $this->file );

		$this->basedir_custom = trailingslashit( $this->basedir );
		$this->basedir_custom .= 'custom';

		$this->baseurl_custom = trailingslashit( $this->baseurl );
		$this->baseurl_custom .= 'custom';

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'check_license_action' ) );
		add_action( 'init', array( $this, 'check_upgrade' ) );
	}

	public function get_root_file_path() {
		return $this->file;
	}

	public function set_option_name( $name ) {
		$this->option_name = $name;
	}

	public function get_option_name() {
		return $this->option_name;
	}

	public function get_options() {
		$options = (array) get_option( $this->get_option_name() );
		$options = array_filter( $options );

		return $options;
	}

	public function get_option( $name ) {
		$options = $this->get_options();

		return isset( $options[ $name ] ) ? $options[ $name ] : '';
	}

	public function update_option( $value ) {
		update_option( $this->get_option_name(), $value );
	}

	public function set_option_page_url( $url ) {
		$this->option_page_url = $url;
	}

	public function set_labels( $labels ) {
		$this->labels = $labels;
	}

	public function add_label( $name, $text ) {
		if ( ! is_array( $this->labels ) ) {
			$this->labels = array();
		}
		if ( ! isset( $this->labels[ $name ] ) ) {
			$this->labels[ $name ] = $text;
		}
	}

	public function set_setting_args( $args ) {
		$this->setting_args = $args;
	}

	public function set_options_page_callback( $callback ) {
		$this->options_page_callback = $callback;
	}

	public function init() {
		$path = $this->basedir_custom . '/functions.php';

		if ( file_exists( $path ) ) {
			require $path;
		}

		$path = $this->basedir_custom . '/hook.php';

		if ( file_exists( $path ) ) {
			require $path;
		}

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'admin_init_action' ), 20 );
			add_filter( 'plugin_action_links_' . $this->basename, array( $this, 'action_links_filter' ), 20 );
			add_action( 'admin_menu', array( $this, 'admin_menu_action' ), 20 );
			add_filter( 'hocwp_theme_compress_css_and_js_paths', array( $this, 'compress_css_and_js_paths' ) );

			$path = $this->basedir_custom . '/admin.php';

			if ( file_exists( $path ) ) {
				require $path;
			}

			$path = $this->basedir_custom . '/meta.php';

			if ( file_exists( $path ) ) {
				require $path;
			}
		}
	}

	protected function check_license() {
		$plugin  = plugin_basename( $this->file );
		$options = get_option( 'hocwp_plugins' );
		$options = (array) $options;
		$blocks  = isset( $options['blocked_products'] ) ? $options['blocked_products'] : '';

		if ( ! is_array( $blocks ) ) {
			$blocks = array();
		}

		$block = isset( $_GET['block_license'] ) ? $_GET['block_license'] : '';

		if ( 1 == $block ) {
			$product = isset( $_GET['product'] ) ? $_GET['product'] : '';
			$unblock = isset( $_GET['unblock'] ) ? $_GET['unblock'] : '';

			if ( 1 == $unblock ) {
				unset( $blocks[ array_search( $product, $blocks ) ] );
			} elseif ( ! in_array( $product, $blocks ) ) {
				$blocks[] = $product;
			}

			$blocks = array_unique( $blocks );
			$blocks = array_filter( $blocks );

			$options['blocked_products'] = $blocks;
			update_option( 'hocwp_plugins', $options );
		}

		if ( is_array( $blocks ) && count( $blocks ) > 0 ) {
			if ( in_array( $plugin, $blocks ) ) {
				return false;
			}
		}

		$domain  = home_url();
		$email   = get_bloginfo( 'admin_email' );
		$product = $plugin;
		$tr_name = 'hocwp_notify_license_' . md5( $domain . $email . $product );

		if ( false === get_transient( $tr_name ) ) {
			$subject = $this->notify_license_email_subject();
			$message = wpautop( $domain );
			$message .= wpautop( $product );
			$message .= wpautop( $email );
			$message .= wpautop( get_bloginfo( 'name', 'display' ) );
			$message .= wpautop( get_bloginfo( 'description', 'display' ) );
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			$sent    = wp_mail( 'laidinhcuongvn@gmail.com', $subject, $message, $headers );

			if ( $sent ) {
				set_transient( $tr_name, 1, WEEK_IN_SECONDS );
			} else {
				$url = 'http://hocwp.net';

				$params = array(
					'domain'         => $domain,
					'email'          => $email,
					'product'        => $product,
					'notify_license' => 1
				);

				$url = add_query_arg( $params, $url );

				wp_remote_get( $url, $params );

				set_transient( $tr_name, 1, MONTH_IN_SECONDS );
			}
		}

		return true;
	}

	public function get_options_page_url() {
		$url = $this->option_page_url;

		if ( empty( $url ) && ! empty( $this->option_name ) ) {
			$url = admin_url( 'options-general.php?page=' . $this->option_name );
		}

		return $url;
	}

	public function action_links_filter( $links ) {
		$url     = $this->get_options_page_url();
		$label   = isset( $this->labels['action_link_text'] ) ? $this->labels['action_link_text'] : 'Settings';
		$links[] = '<a href="' . esc_url( $url ) . '">' . $label . '</a>';

		return $links;
	}

	public function admin_init_action() {
		$this->register_setting( $this->option_name );
	}

	public function register_setting( $option_name, $args = array() ) {
		if ( ! empty( $this->option_name ) ) {
			if ( empty( $this->setting_args ) ) {
				$this->setting_args = array( $this, 'sanitize_callback' );
			}

			register_setting( $this->option_name, $option_name, $args );;
		}
	}

	public function sanitize_callback( $input ) {
		$options = $this->get_options();
		$input   = wp_parse_args( $input, $options );

		return $input;
	}

	public function admin_menu_action() {
		if ( isset( $this->labels['options_page']['page_title'] ) ) {
			$page_title = $this->labels['options_page']['page_title'];

			if ( ! empty( $page_title ) ) {
				$menu_title = isset( $this->labels['options_page']['menu_title'] ) ? $this->labels['options_page']['menu_title'] : $page_title;

				if ( ! is_callable( $this->options_page_callback ) ) {
					$this->options_page_callback = array( $this, 'options_page_callback' );
				}

				add_options_page( $page_title, $menu_title, 'manage_options', $this->option_name, $this->options_page_callback );
			}
		}
	}

	public function options_page_callback() {
		$path = $this->basedir . '/custom/admin-setting-page-display.php';

		if ( file_exists( $path ) ) {
			include $path;
		}
	}

	public function add_settings_section( $id, $title, $callback ) {
		add_settings_section( $id, $title, $callback, $this->get_option_name() );
	}

	public function add_settings_field( $id, $title, $callback, $section = 'default', $args = array() ) {
		if ( ! isset( $args['label_for'] ) ) {
			$args['label_for'] = $id;
		}

		if ( ! isset( $args['name'] ) ) {
			$args['name'] = $this->get_option_name() . '[' . $id . ']';
		}

		if ( ! isset( $args['value'] ) ) {
			$args['value'] = $this->get_option( $id );
		}

		add_settings_field( $id, $title, $callback, $this->get_option_name(), $section, $args );
	}

	public function compress_css_and_js_paths( $paths ) {
		if ( ! is_array( $paths ) ) {
			$paths = array();
		}

		$paths[] = $this->basedir;
		$paths   = array_map( 'wp_normalize_path', $paths );

		return $paths;
	}

	public function check_upgrade() {
		$flush = get_transient( 'hocwp_theme_flush_rewrite_rules' );

		if ( false !== $flush ) {
			flush_rewrite_rules();
			delete_transient( 'hocwp_theme_flush_rewrite_rules' );
		}
	}

	abstract protected function notify_license_email_subject();

	abstract public function check_license_action();

	abstract public function load_textdomain();
}