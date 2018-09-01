<?php

/**
 * Plugin Name: Auto Thumbnail
 * Plugin URI: http://hocwp.net/project/
 * Description: This plugin is created by HocWP Team.
 * Author: HocWP Team
 * Version: 1.0.0
 * Author URI: http://facebook.com/hocwpnet/
 * Text Domain: auto-thumbnail
 * Domain Path: /languages/
 */
class Auto_Thumbnail {
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
		}

		add_filter( 'post_thumbnail_html', array( $this, 'post_thumbnail_html_filter' ), 10, 5 );
	}

	public function sanitize_exclude_name( $name ) {
		return basename( $name );
	}

	public function get_exclude_images() {
		$exclude = $this->get_option( 'exclude' );

		if ( ! empty( $exclude ) ) {
			$exclude = explode( "\r\n", $exclude );

			return array_map( array( $this, 'sanitize_exclude_name' ), $exclude );
		}

		return array();
	}

	public function get_thumbnail_from_string( $string ) {
		$result = '';

		if ( ! empty( $string ) ) {
			$images = $this->get_all_image_from_string( $string, 'url' );

			if ( $this->array_has_value( $images ) ) {
				$exclude = $this->get_exclude_images();

				$first = '';

				foreach ( $images as $image ) {
					$name = $this->sanitize_exclude_name( $image );

					if ( ! $this->array_has_value( $exclude ) || ( $this->array_has_value( $exclude ) && false === array_search( $name, $exclude ) ) ) {
						$result = $image;
						break;
					} else {
						if ( empty( $first ) ) {
							$first = $image;
						}
					}
				}

				if ( empty( $result ) ) {
					$result = $first;
				}
			}
		}

		if ( empty( $result ) ) {
			$default = $this->get_option( 'default' );

			if ( ! empty( $default ) ) {
				$default = $this->sanitize_media_value( $default );

				if ( isset( $default['url'] ) && ! empty( $default['url'] ) ) {
					$result = $default['url'];
				}
			}
		}

		return $result;
	}

	public function post_thumbnail_html_filter( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		if ( empty( $html ) ) {
			$obj = get_post( $post_id );

			if ( $obj instanceof WP_Post ) {
				$thumbnail = get_post_meta( $post_id, 'thumbnail_url', true );

				if ( empty( $thumbnail ) ) {
					$thumbnail = $this->get_thumbnail_from_string( $obj->post_content );
				}

				if ( ! empty( $thumbnail ) ) {
					$html = sprintf( '<img src="%s" alt="%s">', $thumbnail, $obj->post_title );
				}
			}
		}

		return $html;
	}

	public function custom_admin_init_action() {
		$args = array(
			'description' => __( 'Each image name is on a different line.', $this->textdomain )
		);

		$this->add_settings_field( 'exclude', __( 'Exclude Image Name', $this->textdomain ), array(
			$this,
			'admin_setting_field_textarea'
		), 'default', $args );

		$this->add_settings_field( 'default', __( 'Default Thumbnail', $this->textdomain ), array(
			$this,
			'admin_setting_field_media_upload'
		) );
	}
}

function Auto_Thumbnail() {
	return Auto_Thumbnail::get_instance();
}

add_action( 'plugins_loaded', function () {
	Auto_Thumbnail();
} );