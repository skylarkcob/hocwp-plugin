<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! trait_exists( 'Woo_Checkout_Images_Utils' ) ) {
	// Load core utils functions
	require_once dirname( __FILE__ ) . '/trait-utils.php';
}

abstract class Woo_Checkout_Images_Core {
	use Woo_Checkout_Images_Utils;

	protected static $instance;
	public $core_version = '1.0.6';

	public $require_php_version = '';
	public $required_plugins;

	public $show_credits = true;

	public $user_agent;

	public $is_developing;
	public $css_suffix;
	public $js_suffix;
	public $doing_ajax;
	public $doing_cron;

	protected $plugin_file;
	protected $version;

	protected $base_dir;
	protected $base_url;
	protected $plugins_dir;

	protected $custom_dir;
	protected $custom_url;

	public $content_dir = false;
	public $content_url;

	public $config_file;

	protected $base_name;

	public $textdomain;

	protected $option_name = '';
	protected $option_page_url;
	protected $labels;
	protected $setting_args;
	protected $options_page_callback;
	public $setting_tabs;
	public $setting_tab;

	public $option_defaults;

	public $sub_menu = true;
	public $menu_icon = 'dashicons-admin-generic';
	public $sub_menu_label = '';

	public $one_term_taxonomies = array();

	public $date_format;
	public $time_format;

	public $user;

	private function css_or_js_suffix( $type = 'css' ) {
		return '.' . $type;
	}

	public function css_suffix() {
		return $this->css_or_js_suffix();
	}

	public function js_suffix() {
		return $this->css_or_js_suffix( 'js' );
	}

	public function get_base_dir() {
		return $this->base_dir;
	}

	public function get_base_url() {
		return $this->base_url;
	}

	public function get_content_dir() {
		return $this->content_dir;
	}

	public function get_content_url() {
		return $this->content_url;
	}

	public function get_plugin_data() {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return get_plugin_data( $this->plugin_file );
	}

	public function get_plugin_info( $header ) {
		$data = $this->get_plugin_data();

		return ( is_array( $data ) && isset( $data[ $header ] ) && ! empty( $data[ $header ] ) ) ? $data[ $header ] : '';
	}

	public function pagination( $query, $paged = null, $max_page = null, $args = array() ) {
		if ( ! $query instanceof WP_Query ) {
			$query = $GLOBALS['wp_query'];
		}

		$big = 999999999;

		if ( ! is_numeric( $paged ) ) {
			$paged = $query->get( 'paged' );

			if ( ! is_numeric( $paged ) ) {
				$paged = get_query_var( 'paged' );
			}
		}

		if ( ! is_numeric( $paged ) ) {
			$paged = 1;
		}

		if ( ! $max_page ) {
			$max_page = $query->max_num_pages;
		}

		if ( ! is_numeric( $max_page ) ) {
			$max_page = 1;
		}

		$defaults = array(
			'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
			'current' => max( 1, $paged ),
			'format'  => '?paged=%#%',
			'total'   => $max_page
		);

		$args = wp_parse_args( $args, $defaults );

		return paginate_links( $args );
	}

	public function pagination_html( $query, $paged = null, $max_page = null, $args = array() ) {
		$class     = 'pagination-container';
		$load_more = isset( $args['load_more'] ) ? $args['load_more'] : '';

		if ( $load_more ) {
			$class .= ' load-more';
		}

		echo '<div class="' . $class . '">' . PHP_EOL;
		$pagination = $this->pagination( $query, $paged, $max_page, $args );
		echo $pagination;

		if ( $load_more && ! empty( $pagination ) ) {
			?>
			<a href="javascript:"
			   class="button btn load-more"><?php esc_html_e( 'Load more', $this->textdomain ); ?></a>
			<?php
		}

		echo '</div>' . PHP_EOL;
	}

	public function get_textdomain() {
		if ( empty( $this->textdomain ) ) {
			$this->textdomain = $this->get_plugin_info( 'TextDomain' );
		}

		return $this->textdomain;
	}

	public function load_textdomain() {
		load_plugin_textdomain( $this->get_textdomain(), false, basename( $this->base_dir ) . '/custom/languages/' );
	}

	public function set_option_name( $name ) {
		$name = str_replace( '-', '_', $name );

		$this->option_name = $name;
	}

	public function get_option_name() {
		return $this->option_name;
	}

	public function get_options() {
		$options = (array) get_option( $this->get_option_name() );

		if ( isset( $options[0] ) && $this->is_empty_string( $options[0] ) ) {
			unset( $options[0] );
		}

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( $this->array_has_value( $this->option_defaults ) ) {
			$options = wp_parse_args( $options, $this->option_defaults );
		}

		$options = apply_filters( $this->textdomain . '_options', $options );

		return $options;
	}

	public function get_option( $name, $default = '' ) {
		$options = $this->get_options();

		return isset( $options[ $name ] ) ? $options[ $name ] : $default;
	}

	public function update_options( $value ) {
		update_option( $this->get_option_name(), $value );
	}

	public function udpate_option( $name, $value ) {
		$options = $this->get_options();

		$options[ $name ] = $value;

		$this->update_options( $options );
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

	public function get_options_page_url() {
		$url = $this->option_page_url;

		if ( empty( $url ) && ! empty( $this->option_name ) ) {
			$url = admin_url( 'options-general.php?page=' . $this->option_name );

			$this->option_page_url = $url;
		}

		return $url;
	}

	public function get_posts_per_page() {
		return get_option( 'posts_per_page' );
	}

	public function is_webp_image( $file ) {
		if ( is_file( $file ) && $this->is_image_url( $file ) ) {
			$mime = wp_get_image_mime( $file );

			if ( 'image/webp' == $mime ) {
				return true;
			}
		}

		return false;
	}

	public function require_plugin( $plugin ) {
		if ( ! empty( $plugin ) ) {
			if ( ! is_array( $this->required_plugins ) ) {
				$this->required_plugins = array();
			}

			if ( ! in_array( $plugin, $this->required_plugins ) ) {
				$this->required_plugins[] = $plugin;
			}
		}
	}

	public function re_init() {
		$this->init();
	}

	private function init() {
		$this->base_dir = dirname( $this->plugin_file );
		$this->base_url = plugins_url( '', $this->plugin_file );

		$this->plugins_dir = dirname( $this->base_dir );

		$this->base_name = plugin_basename( $this->plugin_file );

		$this->custom_dir = trailingslashit( $this->base_dir ) . 'custom';
		$this->custom_url = trailingslashit( $this->base_url ) . 'custom';

		$this->textdomain = $this->get_plugin_info( 'TextDomain' );

		$this->version = $this->get_plugin_info( 'Version' );

		$config = trailingslashit( WP_CONTENT_DIR );
		$config .= $this->textdomain;
		$config = trailingslashit( $config ) . 'config.php';

		if ( file_exists( $config ) ) {
			if ( ! $this->config_file ) {
				$this->config_file = true;
			}

			if ( ! $this->content_dir ) {
				$this->content_dir = true;
			}
		}

		if ( $this->config_file ) {
			if ( ! $this->content_dir ) {
				$this->content_dir = true;
			}
		}

		if ( $this->content_dir ) {
			if ( is_string( $this->content_dir ) && false === strpos( $this->content_dir, WP_CONTENT_DIR ) ) {
				$this->content_dir = trailingslashit( WP_CONTENT_DIR ) . $this->content_dir;
			} else {
				$this->content_dir = trailingslashit( WP_CONTENT_DIR ) . $this->textdomain;
			}

			if ( ! is_dir( $this->content_dir ) ) {
				@mkdir( $this->content_dir );
				$silence = '<?php' . PHP_EOL;
				$silence .= '// Silence is golden.';

				@file_put_contents( trailingslashit( $this->content_dir ) . 'index.php', $silence );
			}

			$folder = basename( $this->content_dir );

			$this->content_url = content_url( $folder );
		}

		if ( $this->config_file && ! is_string( $this->config_file ) ) {
			$this->config_file = $config;
		}

		if ( ! $this->config_file && $this->content_dir && file_exists( $config ) ) {
			$this->config_file = $config;
		}

		if ( $this->config_file && file_exists( $this->config_file ) ) {
			require_once $this->config_file;
		}

		if ( null !== $this->option_name ) {
			$this->set_option_name( basename( $this->base_dir ) );
		}

		$this->setting_tabs = array();

		$this->setting_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : '';

		if ( ! is_array( $this->labels ) ) {
			$this->labels = array();
		}

		$this->labels['options_page']['page_title'] = $this->get_plugin_info( 'Name' );

		if ( $this->doing_ajax ) {
			$path = dirname( dirname( __FILE__ ) ) . '/ajax.php';

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	// Allow upload WEBP image mime type
	public function upload_mimes_filter( $mimes ) {
		$mimes['webp'] = 'image/webp';

		return $mimes;
	}

	// Re-update WEBP image metadata
	public function wp_get_attachment_metadata_filter( $data, $media_id ) {
		$path = get_attached_file( $media_id );

		if ( $this->is_webp_image( $path ) ) {
			if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
				require_once ABSPATH . 'wp-admin/includes/image.php';
			}

			$data = wp_generate_attachment_metadata( $media_id, $path );
			wp_update_attachment_metadata( $media_id, $data );
		}

		return $data;
	}

	// Mark WEBP image as real image
	public function file_is_displayable_image_filter( $result, $path ) {
		if ( ! $result && $this->is_webp_image( $path ) ) {
			$result = true;
		}

		return $result;
	}

	// Update WEBP image file type
	public function wp_check_filetype_and_ext_filter( $types, $file ) {
		if ( $this->is_webp_image( $file ) ) {
			$types['ext']  = 'webp';
			$types['type'] = 'image/webp';
		}

		return $types;
	}

	public function created_by_log() {
		$show = apply_filters( 'hocwp_plugin_console_log_created_by', $this->show_credits );

		if ( $show ) {
			$name = $this->get_plugin_info( 'Name' );
			?>
			<script>
				console.log("%c<?php printf( __( 'Plugin %s is created by %s', $this->textdomain ), $name, 'HocWP Team - http://hocwp.net' ); ?>", "font-size:16px;color:red;font-family:tahoma;padding:10px 0");
			</script>
			<?php
		}
	}

	public function init_action() {
		if ( false !== get_transient( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_transient( 'flush_rewrite_rules' );
		}
	}

	public function backup_this_plugin() {
		if ( function_exists( 'hocwp_theme_dev_export_database' ) ) {
			$tr_name = 'backup_plugin_' . md5( $this->get_base_dir() );

			if ( false === get_transient( $tr_name ) || $this->doing_ajax ) {
				hocwp_theme_dev_export_database();

				$time = strtotime( date( 'Y-m-d H:i:s' ) );

				$version = $this->get_plugin_info( 'Version' );
				$source  = untrailingslashit( $this->get_base_dir() );

				$name = $this->get_textdomain();

				$dest = dirname( $source ) . '/' . $name;
				$dest .= '_v' . $version;
				$dest .= '_' . $time;
				$dest .= '.zip';

				$zip = hocwp_theme_zip_folder( $source, $dest );

				if ( $zip ) {
					set_transient( $tr_name, 1, HOUR_IN_SECONDS );
				}
			}
		}
	}

	public function required_plugins_notices() {
		global $pagenow;

		if ( 'update.php' == $pagenow ) {
			return;
		}

		$data   = get_plugin_data( $this->plugin_file );
		$plugin = '';

		foreach ( $this->required_plugins as $rp ) {
			if ( ! is_plugin_active( $rp ) ) {
				$file = trailingslashit( $this->plugins_dir ) . $rp;

				$plugin_slug = dirname( $rp );
				$plugin_name = $plugin_slug;

				if ( file_exists( $file ) ) {
					$info = get_plugin_data( $file );

					if ( is_array( $info ) && isset( $info['Name'] ) && ! empty( $info['Name'] ) ) {
						$plugin_name = $info['Name'];
					}

					$url = admin_url( 'plugins.php' );

					$params = array(
						'action' => 'activate',
						'plugin' => urlencode( $rp )
					);

					$url = add_query_arg( $params, $url );

					$url = wp_nonce_url( $url, 'activate-plugin_' . $rp );

					$plugin .= '<a href="' . $url . '"><strong>' . $plugin_name . '</strong></a>, ';
				} else {
					if ( ! function_exists( 'plugins_api' ) ) {
						require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
					}

					$api = plugins_api( 'plugin_information', array( 'slug' => $plugin_slug ) );

					if ( is_object( $api ) && ! is_wp_error( $api ) && isset( $api->name ) ) {
						$plugin_name = $api->name;
					}

					$url = admin_url( 'update.php' );

					$params = array(
						'action' => 'install-plugin',
						'plugin' => $plugin_slug
					);

					$url = add_query_arg( $params, $url );

					$url = wp_nonce_url( $url, 'install-plugin_' . $plugin_slug );

					$plugin .= '<a href="' . $url . '"><strong>' . $plugin_name . '</strong></a>, ';
				}
			}
		}

		$plugin = rtrim( $plugin, ', ' );

		if ( ! empty( $plugin ) ) {
			?>
			<div class="notice notice-error">
				<p><?php printf( __( '<strong>%s:</strong> You must install and activate thesse required plugins: %s.', $this->textdomain ), $data['Name'], $plugin ); ?></p>
			</div>
			<?php
		}
	}

	public function __construct() {
		$this->user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';

		$this->is_developing = defined( 'WP_DEBUG' ) && true === WP_DEBUG;
		$this->css_suffix    = '.css';
		$this->js_suffix     = '.js';
		$this->doing_ajax    = defined( 'DOING_AJAX' ) && true === DOING_AJAX;
		$this->doing_cron    = defined( 'DOING_CRON' ) && true === DOING_CRON;

		$this->require_php_version = $this->get_plugin_info( 'RequiresPHP' );

		require_once dirname( __FILE__ ) . '/wp-async-request.php';
		require_once dirname( __FILE__ ) . '/wp-background-process.php';
	}

	public function add_folder_for_auto_backup( $folders ) {
		if ( ! is_array( $folders ) ) {
			$folders = array();
		}

		$folders[] = $this->get_base_dir();

		return $folders;
	}

	public function run_init_action() {
		$this->date_format = get_option( 'date_format' );
		$this->time_format = get_option( 'time_format' );

		$this->user = wp_get_current_user();

		$this->init();
	}
}