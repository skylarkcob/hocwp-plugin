<?php

/**
 * Plugin Name: FTP Upload ACF
 * Plugin URI: http://hocwp.net/project/
 * Description: This plugin is created by HocWP Team.
 * Author: HocWP Team
 * Version: 1.0.0
 * Author URI: http://facebook.com/hocwpnet/
 * Text Domain: ftp-upload-acf
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class FTP_Upload_ACF {
	protected static $instance;

	protected $plugin_file = __FILE__;
	protected $version;

	protected $base_dir;
	protected $base_url;

	protected $base_name;

	public $textdomain;

	protected $option_name;
	protected $option_page_url;
	protected $labels;
	protected $setting_args;
	protected $options_page_callback;

	public function is_empty_string( $string ) {
		return ( is_string( $string ) && empty( $string ) );
	}

	public function size_in_bytes( $size ) {
		$result = floatval( trim( $size ) );

		$last = strtolower( $size[ strlen( $size ) - 1 ] );

		switch ( $last ) {
			case 'g':
				$result *= 1024 * 1024 * 1024;
				break;
			case 'm':
				$result *= 1024 * 1024;
				break;
			case 'k':
				$result *= 1024;
				break;
		}

		return $result;
	}

	public function json_string_to_array( $json_string ) {
		if ( is_string( $json_string ) ) {
			$json_string = stripslashes( $json_string );
			$json_string = json_decode( $json_string, true );
		}

		return $json_string;
	}

	public function get_upload_max_file_size() {
		$max_upload   = $this->size_in_bytes( ini_get( 'upload_max_filesize' ) );
		$max_post     = $this->size_in_bytes( ini_get( 'post_max_size' ) );
		$memory_limit = $this->size_in_bytes( ini_get( 'memory_limit' ) );

		return min( $max_upload, $max_post, $memory_limit );
	}

	public function ftp_connect( $host, $user, $password, $port = 21 ) {
		$conn_id = @ftp_connect( $host, $port );

		if ( $conn_id ) {
			$logged_in = @ftp_login( $conn_id, $user, $password );

			if ( $logged_in ) {
				return $conn_id;
			}
		}

		return false;
	}

	public function ftp_upload( $file, $host, $user, $password, $port = 21 ) {
		if ( is_array( $file ) && isset( $file['tmp_name'] ) ) {
			$conn = $this->ftp_connect( $host, $user, $password, $port );

			if ( false !== $conn ) {
				@ftp_pwd( $conn );
				@ftp_chdir( $conn, '~' );
				$new_name = $file['name'];
				$info     = pathinfo( $new_name );

				if ( isset( $info['extension'] ) && ! empty( $info['extension'] ) ) {
					$new_name = $info['filename'] . '-' . current_time( 'timestamp' ) . '.' . $info['extension'];
				}

				$uploaded = @ftp_put( $conn, $new_name, $file['tmp_name'], FTP_BINARY );

				@ftp_close( $conn );
			}
		}

		return false;
	}

	public function is_string_empty( $string ) {
		return $this->is_empty_string( $string );
	}

	public static function array_has_value( $arr ) {
		return ( is_array( $arr ) && count( $arr ) > 0 );
	}

	public function is_positive_number( $number ) {
		return ( is_numeric( $number ) && $number > 0 );
	}

	public function sanitize_media_url( $url, $media_id ) {
		if ( $this->is_positive_number( $media_id ) && $this->is_media_file_exists( $media_id ) ) {
			if ( wp_attachment_is_image( $media_id ) ) {
				$details = wp_get_attachment_image_src( $media_id, 'full' );
				$url     = isset( $details[0] ) ? $details[0] : '';
			} else {
				$url = wp_get_attachment_url( $media_id );
			}
		}

		return $url;
	}

	public function is_media_file_exists( $id ) {
		if ( file_exists( get_attached_file( $id ) ) ) {
			return true;
		}

		return false;
	}

	public function is_image( $url, $id = 0 ) {
		if ( $this->is_positive_number( $id ) ) {
			return wp_attachment_is_image( $id );
		}

		return $this->is_image_url( $url );
	}

	public function sanitize_media_value( $value ) {
		$id   = 0;
		$url  = '';
		$icon = '';
		$size = '';

		if ( ! is_array( $value ) ) {
			if ( is_numeric( $value ) ) {
				$id = $value;
			} else {
				$url = $value;
			}
		} else {
			$url = isset( $value['url'] ) ? $value['url'] : '';
			$id  = isset( $value['id'] ) ? $value['id'] : '';
			$id  = absint( $id );
		}

		if ( ! $this->is_positive_number( $id ) ) {
			$id = attachment_url_to_postid( $url );
		}

		if ( $this->is_positive_number( $id ) ) {
			$url  = $this->sanitize_media_url( $url, $id );
			$icon = wp_mime_type_icon( $id );
			$size = filesize( get_attached_file( $id ) );
		}

		$result = array(
			'id'          => $id,
			'url'         => $url,
			'type_icon'   => $icon,
			'is_image'    => $this->is_image( $url, $id ),
			'size'        => $size,
			'size_format' => size_format( $size, 2 ),
			'mime_type'   => get_post_mime_type( $id )
		);

		return $result;
	}

	public function is_image_url( $url ) {
		$img_formats = array( 'png', 'jpg', 'jpeg', 'gif', 'tiff', 'bmp', 'ico' );

		$path_info = pathinfo( $url );
		$extension = isset( $path_info['extension'] ) ? $path_info['extension'] : '';
		$extension = trim( strtolower( $extension ) );

		if ( in_array( $extension, $img_formats ) ) {
			return true;
		}

		return false;
	}

	public function string_contain( $haystack, $needle, $offset = 0, $output = 'boolean' ) {
		$pos = strpos( $haystack, $needle, $offset );

		if ( false === $pos && function_exists( 'mb_strpos' ) ) {
			$pos = mb_strpos( $haystack, $needle, $offset );
		}

		if ( 'int' == $output || 'integer' == $output || 'numeric' == $output ) {
			return $pos;
		}

		return ( false !== $pos );
	}

	public function has_image( $string ) {
		$result = false;

		if ( false !== $this->string_contain( $string, '.jpg' ) ) {
			$result = true;
		} elseif ( false !== $this->string_contain( $string, '.png' ) ) {
			$result = true;
		} elseif ( false !== $this->string_contain( $string, '.gif' ) ) {
			$result = true;
		}

		return $result;
	}

	public function get_first_image_source( $content ) {
		$doc = new DOMDocument();
		@$doc->loadHTML( $content );
		$xpath = new DOMXPath( $doc );
		$src   = $xpath->evaluate( 'string(//img/@src)' );
		unset( $doc, $xpath );

		return $src;
	}

	public function get_all_image_from_string( $data, $output = 'img' ) {
		$output = trim( $output );
		preg_match_all( '/<img[^>]+>/i', $data, $matches );
		$matches = isset( $matches[0] ) ? $matches[0] : array();

		if ( ! $this->array_has_value( $matches ) && ! empty( $data ) ) {
			if ( false !== $this->string_contain( $data, '//' ) ) {
				if ( $this->has_image( $data ) ) {
					$sources = explode( PHP_EOL, $data );

					if ( $this->array_has_value( $sources ) ) {
						foreach ( $sources as $src ) {
							if ( $this->is_image_url( $src ) ) {
								if ( 'img' == $output ) {
									$matches[] = '<img src="' . $src . '" alt="">';
								} else {
									$matches[] = $src;
								}
							}
						}
					}
				}
			}
		} elseif ( 'img' != $output && $this->array_has_value( $matches ) ) {
			$tmp = array();

			foreach ( $matches as $img ) {
				$src   = $this->get_first_image_source( $img );
				$tmp[] = $src;
			}

			$matches = $tmp;
		}

		return $matches;
	}

	public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
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

	public function get_base_dir() {
		return $this->base_dir;
	}

	public function get_base_url() {
		return $this->base_url;
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

	public function get_textdomain() {
		return $this->textdomain;
	}

	public function load_textdomain() {
		load_plugin_textdomain( $this->get_textdomain(), false, basename( $this->base_dir ) . '/languages/' );
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
		}

		return $url;
	}

	public function action_links_filter( $links ) {
		$url     = $this->get_options_page_url();
		$label   = isset( $this->labels['action_link_text'] ) ? $this->labels['action_link_text'] : __( 'Settings', $this->textdomain );
		$links[] = '<a href="' . esc_url( $url ) . '">' . $label . '</a>';

		return $links;
	}

	public function register_setting( $option_name, $args = array() ) {
		if ( ! empty( $this->option_name ) ) {
			if ( empty( $this->setting_args ) ) {
				$this->setting_args = array( $this, 'sanitize_callback' );
			}

			register_setting( $this->option_name, $option_name, $args );
		}
	}

	public function admin_init_action() {
		$this->register_setting( $this->option_name );
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
		$option_name = $this->get_option_name();

		if ( empty( $option_name ) ) {
			return;
		}

		$base_url = $this->get_options_page_url();

		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';

		$tabs = apply_filters( 'plugin_' . $this->get_option_name() . '_setting_tabs', array() );

		if ( 0 < count( $tabs ) && ! array_key_exists( $tab, $tabs ) ) {
			reset( $tabs );
			$tab = key( $tabs );
		}

		$headline = apply_filters( 'plugin_' . $option_name . '_setting_page_title', $this->get_plugin_info( 'Name' ) );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $headline ); ?></h1>
			<hr class="wp-header-end">
			<?php
			if ( 1 < count( $tabs ) ) {
				?>
				<div id="nav">
					<h2 class="nav-tab-wrapper">
						<?php
						foreach ( $tabs as $key => $value ) {
							$class = 'nav-tab';

							if ( $key == $tab ) {
								$class .= ' nav-tab-active';
							}

							$url = $base_url;
							$url = add_query_arg( 'tab', $key, $url );
							?>
							<a class="<?php echo $class; ?>"
							   href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $value ); ?></a>
							<?php
						}
						?>
					</h2>
				</div>
				<?php
			}

			$html = apply_filters( 'plugin_' . $option_name . '_setting_page_form', '', $tab );

			if ( empty( $html ) ) {
				$action = 'options.php';
				?>
				<form method="post" action="<?php echo $action; ?>" novalidate="novalidate" autocomplete="off">
					<?php settings_fields( $option_name ); ?>
					<table class="form-table">
						<?php do_settings_fields( $option_name, 'default' ); ?>
					</table>
					<?php
					do_settings_sections( $option_name );
					submit_button();
					?>
				</form>
				<?php
			} else {
				echo $html;
			}
			?>
		</div>
		<?php
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

	public function field_description( $args = array() ) {
		if ( isset( $args['description'] ) && ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', $args['description'] );
		}
	}

	public function admin_setting_field_input( $args ) {
		$value = $args['value'];
		$type  = isset( $args['type'] ) ? $args['type'] : 'text';
		$id    = isset( $args['label_for'] ) ? $args['label_for'] : '';
		$name  = isset( $args['name'] ) ? $args['name'] : '';
		?>
		<label for="<?php echo esc_attr( $id ); ?>"></label>
		<input name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>"
		       id="<?php echo esc_attr( $id ); ?>"
		       value="<?php echo esc_attr( $value ); ?>"
		       class="regular-text">
		<?php
		$this->field_description( $args );
	}

	public function admin_setting_field_textarea( $args ) {
		$value = $args['value'];
		$id    = isset( $args['label_for'] ) ? $args['label_for'] : '';
		$name  = isset( $args['name'] ) ? $args['name'] : '';
		?>
		<label for="<?php echo esc_attr( $id ); ?>"></label>
		<textarea name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" class="widefat"
		          rows="5"><?php echo esc_attr( $value ); ?></textarea>
		<?php
		$this->field_description( $args );
	}

	public function admin_setting_field_select( $args ) {
		$value   = $args['value'];
		$id      = isset( $args['label_for'] ) ? $args['label_for'] : '';
		$name    = isset( $args['name'] ) ? $args['name'] : '';
		$options = isset( $args['options'] ) ? $args['options'] : '';

		$option_none = isset( $args['option_none'] ) ? $args['option_none'] : '';
		?>
		<label for="<?php echo esc_attr( $id ); ?>"></label>
		<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>" class="widefat">
			<?php
			if ( empty( $option_none ) ) {
				?>
				<option value=""></option>
				<?php
			} else {
				echo $option_none;
			}

			if ( is_array( $options ) ) {
				foreach ( $options as $key => $data ) {
					$current = $key;
					$text    = is_string( $data ) ? $data : '';

					if ( is_array( $data ) ) {
						$current = isset( $data['value'] ) ? $data['value'] : '';
						$text    = isset( $data['text'] ) ? $data['text'] : '';
					}
					?>
					<option
						value="<?php echo esc_attr( $current ); ?>"<?php selected( $value, $current ); ?>><?php echo $text; ?></option>
					<?php
				}
			} elseif ( is_string( $options ) ) {
				echo $options;
			}
			?>
		</select>
		<?php
		$this->field_description( $args );
	}

	public function admin_setting_field_media_upload( $args ) {
		$value = $args['value'];
		$type  = isset( $args['type'] ) ? $args['type'] : 'text';
		$id    = isset( $args['label_for'] ) ? $args['label_for'] : '';
		$name  = isset( $args['name'] ) ? $args['name'] : '';

		$remove_text = __( 'Remove', $this->textdomain );
		$add_text    = __( 'Add', $this->textdomain );

		$button_text = $add_text;

		if ( ! empty( $value ) ) {
			$button_text = $remove_text;
		}

		$media_url = ( is_array( $value ) && isset( $value['url'] ) ) ? $value['url'] : '';
		$media_id  = ( is_array( $value ) && isset( $value['id'] ) ) ? $value['id'] : '';
		?>
		<label for="<?php echo esc_attr( $id ); ?>"></label>
		<input name="<?php echo esc_attr( $name ); ?>[url]" type="<?php echo esc_attr( $type ); ?>"
		       id="<?php echo esc_attr( $id ); ?>"
		       value="<?php echo esc_attr( $media_url ); ?>"
		       class="regular-text">
		<button type="button" class="change-media hocwp button"
		        data-remove-text="<?php echo $remove_text; ?>"
		        data-add-text="<?php echo $add_text; ?>"><?php echo $button_text; ?></button>
		<input name="<?php echo esc_attr( $name ); ?>[id]" type="hidden" value="<?php echo esc_attr( $media_id ); ?>">
		<?php
		$this->field_description( $args );
	}

	public function sanitize_callbacks( $input ) {
		return $input;
	}

	public function remove_setting_page() {
		remove_action( 'admin_init', array( $this, 'admin_init_action' ), 20 );
		remove_filter( 'plugin_action_links_' . $this->base_name, array( $this, 'action_links_filter' ), 20 );
		remove_action( 'admin_menu', array( $this, 'admin_menu_action' ), 20 );
	}

	public function admin_enqueue_scripts() {
		global $plugin_page;

		if ( $this->option_name == $plugin_page ) {
			wp_enqueue_media();
		}
	}

	public function admin_footer() {
		?>
		<script>
			jQuery(document).ready(function ($) {
				$("body").on("click", ".hocwp.change-media", function (e) {
					e.preventDefault();
					var element = $(this),
						input = element.prev(),
						value = input.val(),
						inputId = element.next(),
						custom_uploader;

					if ($.trim(value)) {
						input.val("");
						element.text(element.attr("data-add-text"));
						inputId.val("");
					} else {
						custom_uploader = wp.media({
							title: "Insert Media",
							library: {},
							button: {
								text: "Insert Media"
							},
							multiple: false
						}).on("select", function () {
							var attachment = custom_uploader.state().get("selection").first().toJSON();

							input.val(attachment.url);
							element.text(element.attr("data-remove-text"));
							inputId.val(attachment.id);
						}).open();
					}
				});
			});
		</script>
		<?php
	}

	private function init() {
		$this->base_dir = dirname( $this->plugin_file );
		$this->base_url = plugins_url( '', $this->plugin_file );

		$this->base_name = plugin_basename( $this->plugin_file );

		$this->textdomain = $this->get_plugin_info( 'TextDomain' );
		$this->version    = $this->get_plugin_info( 'Version' );

		$this->set_option_name( basename( $this->base_dir ) );

		if ( ! is_array( $this->labels ) ) {
			$this->labels = array();
		}

		$this->labels['options_page']['page_title'] = $this->get_plugin_info( 'Name' );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'admin_init_action' ), 20 );
			add_filter( 'plugin_action_links_' . $this->base_name, array( $this, 'action_links_filter' ), 20 );
			add_action( 'admin_menu', array( $this, 'admin_menu_action' ), 20 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'admin_footer', array( $this, 'admin_footer' ) );
		}
	}

	public function __construct() {
		$this->init();

		if ( self::$instance instanceof self ) {
			return;
		}

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'custom_admin_init_action' ) );

			add_action( 'wp_ajax_ftp_upload_acf_test_connection', array( $this, 'test_connection_ajax_callback' ) );
			add_action( 'wp_ajax_nopriv_ftp_upload_acf_test_connection', array(
				$this,
				'test_connection_ajax_callback'
			) );

			add_action( 'wp_ajax_ftp_upload_acf_get_mime_icon', array(
				$this,
				'get_mime_icon_ajax_callback'
			) );
			add_action( 'wp_ajax_nopriv_ftp_upload_acf_get_mime_icon', array(
				$this,
				'get_mime_icon_ajax_callback'
			) );

			add_action( 'wp_ajax_ftp_upload_acf_upload_file', array(
				$this,
				'upload_file_ajax_callback'
			) );
			add_action( 'wp_ajax_nopriv_ftp_upload_acf_upload_file', array(
				$this,
				'upload_file_ajax_callback'
			) );
		} else {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'acf/render_field/type=file', array( $this, 'add_input_file' ) );
		}

		add_shortcode( 'ftp_upload_acf', array( $this, 'shortcode' ) );
		add_filter( 'wp_mime_type_icon', array( $this, 'mime_type_icon_filter' ), 10, 2 );
	}

	public function upload_file_ajax_callback() {
		$data = array();

		$file = isset( $_FILES['file'] ) ? $_FILES['file'] : isset( $_POST['file'] ) ? $_POST['file'] : isset( $_POST['custom_file'] ) ? $_POST['custom_file'] : '';

		if ( is_string( $file ) && ! empty( $file ) ) {
			$file = maybe_unserialize( $file );

			if ( ! is_array( $file ) ) {
				$file = $this->json_string_to_array( $file );
			}
		}

		if ( is_array( $file ) ) {
			$host     = isset( $_POST['ftp_host'] ) ? $_POST['ftp_host'] : $this->get_option( 'ftp_host' );
			$user     = isset( $_POST['ftp_user'] ) ? $_POST['ftp_user'] : $this->get_option( 'ftp_user' );
			$password = isset( $_POST['ftp_password'] ) ? $_POST['ftp_password'] : $this->get_option( 'ftp_password' );
			$port     = isset( $_POST['ftp_port'] ) ? $_POST['ftp_port'] : $this->get_option( 'ftp_port', 21 );

			$this->ftp_upload( $file, $host, $user, $password, $port );

			wp_send_json_success( $data );
		}

		wp_send_json_error();
	}

	public function get_mime_type_icon( $file_name ) {
		return trailingslashit( $this->base_url ) . $file_name;
	}

	public function mime_type_icon_filter( $icon, $mime ) {
		switch ( $mime ) {
			case 'application/pdf':
				$icon = $this->get_mime_type_icon( 'mime-types/icon-pdf.png' );
				break;
			case 'application/vnd.ms-word.document.macroEnabled.12':
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.template':
			case 'application/vnd.ms-word.template.macroEnabled.12':
			case 'application/vnd.openxmlformats-officedocument.wordprocessingml.document':
			case 'application/msword':
				$icon = $this->get_mime_type_icon( 'mime-types/icon-doc.png' );
				break;
			case 'application/rar':
				$icon = $this->get_mime_type_icon( 'mime-types/icon-rar.png' );
				break;
			case 'application/zip':
				$icon = $this->get_mime_type_icon( 'mime-types/icon-zip.png' );
				break;
			case 'application/x-gzip':
				$icon = $this->get_mime_type_icon( 'mime-types/icon-gz.png' );
				break;
			case 'application/x-7z-compressed':
				$icon = $this->get_mime_type_icon( 'mime-types/icon-7z.png' );
				break;
			case 'application/vnd.ms-excel.addin.macroEnabled.12':
			case 'application/vnd.ms-excel.template.macroEnabled.12':
			case 'application/vnd.ms-excel.sheet.binary.macroEnabled.12':
			case 'application/vnd.ms-excel.sheet.macroEnabled.12':
			case 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet':
			case 'application/vnd.ms-excel':
				$icon = $this->get_mime_type_icon( 'mime-types/icon-xls.png' );
				break;
		}

		return $icon;
	}

	public function get_mime_icon_ajax_callback() {
		$data = array();

		$name = isset( $_GET['name'] ) ? $_GET['name'] : '';

		if ( ! empty( $name ) ) {
			$types = wp_check_filetype( $name );

			if ( isset( $types['type'] ) ) {
				$icon = wp_mime_type_icon( $types['type'] );

				if ( ! empty( $icon ) ) {
					$data['icon'] = $icon;

					wp_send_json_success( $data );
				}
			}
		}

		wp_send_json_error( $data );
	}

	public function test_connection_ajax_callback() {
		$data = array();

		$host     = isset( $_GET['host'] ) ? $_GET['host'] : '';
		$port     = isset( $_GET['port'] ) ? $_GET['port'] : 21;
		$user     = isset( $_GET['user'] ) ? $_GET['user'] : '';
		$password = isset( $_GET['password'] ) ? $_GET['password'] : '';

		$conn = $this->ftp_connect( $host, $user, $password, $port );

		if ( false !== $conn ) {
			wp_send_json_success( $data );
		}

		wp_send_json_error( $data );
	}

	public function add_input_file( $field ) {
		$name = isset( $field['name'] ) ? $field['name'] : '';

		if ( empty( $name ) ) {
			//$this->debug( $field );
		}

		$value = '';

		if ( isset( $field['file_id'] ) && $this->is_positive_number( $field['file_id'] ) ) {
			$value = get_attached_file( $field['file_id'] );

			$value = array(
				'name'     => basename( $value ),
				'type'     => mime_content_type( $value ),
				'tmp_name' => $value,
				'error'    => 0,
				'size'     => filesize( $value )
			);
		}
		?>
		<input type="file" name="<?php echo $name; ?>_file"
		       data-value="<?php echo esc_attr( json_encode( $value ) ); ?>"
		       data-for="<?php echo $name; ?>" style="display: none">
		<div class="progress-bar">
			<span class="label">0%</span>
			<span class="background completed"></span>
			<span class="background uploading"></span>
		</div>
		<?php
	}

	public function enqueue_scripts() {
		wp_enqueue_style( 'ftp-upload-acf-style', $this->base_url . '/style.css', array( 'acf-field-group' ) );
		wp_enqueue_script( 'ftp-upload-acf', $this->base_url . '/script.js', array(
			'jquery',
			'acf-field-group'
		), false, true );

		$l10n = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'maxSize' => $this->get_upload_max_file_size(),
			'l10n'    => array(
				'connectionError'   => __( 'FTP connection information not valid!', $this->textdomain ),
				'connectionSuccess' => __( 'FTP connection information valid!', $this->textdomain ),
				'noFileSelected'    => __( 'No files selected.', $this->textdomain ),
				'maxSizeError'      => sprintf( __( 'You can only upload file less than %s.', $this->textdomain ), size_format( $this->get_upload_max_file_size() ) )
			)
		);

		wp_localize_script( 'ftp-upload-acf', 'ftpUploadAcf', $l10n );
	}

	public function ftp_connection_section_callback() {
		echo wpautop( __( 'The default information for user connecting into FTP Server.', $this->textdomain ) );
	}

	public function custom_admin_init_action() {
		$this->add_settings_section( 'ftp_connection_info', __( 'FTP Connection Information', $this->textdomain ), array(
			$this,
			'ftp_connection_section_callback'
		) );

		$args = array();

		$this->add_settings_field( 'ftp_host', __( 'Host', $this->textdomain ), array(
			$this,
			'admin_setting_field_input'
		), 'ftp_connection_info', $args );

		$this->add_settings_field( 'ftp_port', __( 'Port', $this->textdomain ), array(
			$this,
			'admin_setting_field_input'
		), 'ftp_connection_info', $args );

		$this->add_settings_field( 'ftp_user', __( 'User', $this->textdomain ), array(
			$this,
			'admin_setting_field_input'
		), 'ftp_connection_info', $args );

		$this->add_settings_field( 'ftp_password', __( 'Password', $this->textdomain ), array(
			$this,
			'admin_setting_field_input'
		), 'ftp_connection_info', $args );

		$args = array(
			'post_type'      => 'acf-field-group',
			'posts_per_page' => - 1,
			'post_status'    => 'publish'
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			$options = array();

			foreach ( $query->posts as $obj ) {
				$options[ $obj->ID ] = $obj->post_title;
			}

			$args = array(
				'description' => __( 'Each image name is on a different line.', $this->textdomain ),
				'options'     => $options
			);

			$this->add_settings_field( 'acf_field_group', __( 'ACF Field Group', $this->textdomain ), array(
				$this,
				'admin_setting_field_select'
			), 'default', $args );
		}
	}

	public function render_acf_field_value( $field ) {
		if ( $this->is_valid_acf_field( $field ) ) {
			$parent       = get_post( $field['uploaded_to'] );
			$field_object = $field['field_object'];

			$icon = $field['icon'];
			?>
			<div class="acf-file-uploader has-value completed" data-library="all" data-mime_types="" data-uploader="wp">
				<input name="<?php echo $field_object->post_name; ?>" value="<?php echo $field['ID']; ?>" data-name="id"
				       type="hidden">

				<div class="show-if-value file-wrap">
					<div class="file-icon">
						<img data-name="icon" src="<?php echo $icon; ?>" alt="">
					</div>
					<div class="file-info">
						<p>
							<strong data-name="title"><?php echo $field['title']; ?></strong>
						</p>

						<p>
							<strong><?php _e( 'File name:', $this->textdomain ); ?></strong>
							<a data-name="filename" href="" target="_blank"><?php echo $field['filename']; ?></a>
						</p>

						<p>
							<strong><?php _e( 'File size:', $this->textdomain ); ?></strong>
							<span data-name="filesize"><?php echo size_format( $field['filesize'] ); ?></span>
						</p>
					</div>
					<div class="acf-actions -hover">
						<a class="acf-icon -pencil dark" data-name="edit" href="#" title="Edit"></a><a
							class="acf-icon -cancel dark" data-name="remove" href="#" title="Remove"></a>
					</div>
				</div>
				<div class="hide-if-value">
					<p><?php _e( 'No file selected', $this->textdomain ); ?> <a data-name="add"
					                                                            class="acf-button button"
					                                                            href="#"><?php _e( 'Add File', $this->textdomain ); ?></a>
					</p>
				</div>
			</div>
			<?php
			$tmp = maybe_unserialize( $field_object->post_content );

			$tmp['file_id'] = $field['ID'];

			$tmp = wp_parse_args( $field, $tmp );

			$tmp['name'] = $field_object->post_name;

			$this->add_input_file( $tmp );
		}
	}

	public function is_valid_acf_field( $field ) {
		$field = acf_get_valid_field( $field );

		if ( ! $this->is_positive_number( $field['ID'] ) ) {
			return false;
		}

		return ( $this->array_has_value( $field ) && isset( $field['_valid'] ) && 1 == $field['_valid'] );
	}

	public function acf_field_by_id( $id, $post_id = null ) {
		if ( function_exists( 'acf_render_field' ) ) {
			$obj = get_post( $id );

			if ( $obj instanceof WP_Post && 'acf-field' == $obj->post_type ) {
				$data = _acf_get_field_by_id( $obj->ID );

				$has_value = false;

				if ( $this->is_positive_number( $post_id ) ) {
					$tmp = get_field( $obj->post_excerpt, $post_id );
					$tmp = acf_get_valid_field( $tmp );

					if ( $this->is_valid_acf_field( $tmp ) ) {
						$has_value = true;

						$tmp['field_object'] = $obj;
						$this->render_acf_field_value( $tmp );
					}
				}

				if ( ! $has_value ) {
					acf_render_field( $data );
				}
			}
		}
	}

	public function shortcode( $atts = array(), $content = null ) {
		$html = '';

		if ( function_exists( 'get_field' ) ) {
			$atts = shortcode_atts( array(
				'field_group'  => '',
				'field_id'     => '',
				'ftp_host'     => '',
				'ftp_port'     => 21,
				'ftp_user'     => '',
				'ftp_password' => '',
				'button_text'  => __( 'Upload', $this->textdomain ),
				'post_id'      => ''
			), $atts );

			$field_group = $atts['field_group'];

			if ( empty( $field_group ) ) {
				$field_group = $this->get_option( 'acf_field_group' );
			}

			$field_id = $atts['field_id'];

			$ftp_host     = $atts['ftp_host'];
			$ftp_port     = $atts['ftp_port'];
			$ftp_user     = $atts['ftp_user'];
			$ftp_password = $atts['ftp_user'];
			$button_text  = $atts['button_text'];

			$post_id = $atts['post_id'];

			if ( ! $this->is_positive_number( $post_id ) ) {
				if ( is_single() || is_singular() || is_page() ) {
					$post_id = get_the_ID();
				}
			}

			if ( empty( $button_text ) ) {
				$button_text = __( 'Upload', $this->textdomain );
			}

			ob_start();
			?>
			<div class="ftp-upload-acf">
				<button class="upload-popup"><?php echo $button_text; ?></button>
				<div id="uploadPopup">
					<div class="inner">
						<div class="module-header">
							<h3><?php _e( 'FTP Upload', $this->textdomain ); ?></h3>
						</div>
						<div class="module-body">
							<form method="post" enctype="multipart/form-data">
								<fieldset>
									<legend><?php _e( 'Connection Information', $this->textdomain ); ?></legend>
									<label for="ftphost"><?php _e( 'Host:', $this->textdomain ); ?></label>
									<input id="ftphost" type="text" name="ftphost" value="<?php echo $ftp_host; ?>">
									<label for="ftpport"><?php _e( 'Port:', $this->textdomain ); ?></label>
									<input id="ftpport" type="text" name="ftpport" value="<?php echo $ftp_port; ?>">
									<label for="ftpuser"><?php _e( 'User:', $this->textdomain ); ?></label>
									<input id="ftpuser" type="text" name="ftpuser" value="<?php echo $ftp_user; ?>">
									<label for="ftppassword"><?php _e( 'Password:', $this->textdomain ); ?></label>
									<input id="ftppassword" type="password" name="ftppassword"
									       value="<?php echo $ftp_password; ?>">
								</fieldset>
								<?php
								if ( $this->is_positive_number( $field_id ) ) {
									$this->acf_field_by_id( $field_id, $post_id );
								} elseif ( $this->is_positive_number( $field_group ) ) {
									$args = array(
										'posts_per_page' => - 1,
										'post_type'      => 'acf-field',
										'post_status'    => 'publish',
										'post_parent'    => $field_group,
										'fields'         => 'ids'
									);

									$query = new WP_Query( $args );

									if ( $query->have_posts() ) {
										foreach ( $query->posts as $id ) {
											$this->acf_field_by_id( $id, $post_id );
										}
									}
								}
								?>
							</form>
						</div>
						<div class="module-footer">
							<button class="upload"><?php _e( 'Upload', $this->textdomain ); ?></button>
							<button class="test"><?php _e( 'Test', $this->textdomain ); ?></button>
							<button class="cancel"><?php _e( 'Cancel', $this->textdomain ); ?></button>
						</div>
					</div>
				</div>
			</div>
			<?php

			$html = ob_get_clean();
		}

		return $html;
	}
}

function FTP_Upload_ACF() {
	return FTP_Upload_ACF::get_instance();
}

add_action( 'plugins_loaded', function () {
	FTP_Upload_ACF();
} );