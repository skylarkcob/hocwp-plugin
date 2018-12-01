<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WP_Custom_Coupons_Core {
	protected static $instance;

	public $require_php_version = '5.6';

	protected $plugin_file;
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

	public $sub_menu = true;
	public $menu_icon = 'dashicons-admin-generic';

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

	public function ftp_create_directory( $conn, $directory ) {
		if ( $conn ) {
			if ( ! empty( $directory ) ) {
				$home = @ftp_pwd( $conn );

				$directory = ltrim( $directory, '/' );
				$directory = untrailingslashit( $directory );

				$parts = explode( '/', $directory );

				$directory = array_shift( $parts );

				$directory = trailingslashit( $home ) . $directory;

				if ( ! @ftp_chdir( $conn, $directory ) ) {
					@ftp_mkdir( $conn, $directory );
				}

				if ( $this->array_has_value( $parts ) ) {
					foreach ( $parts as $name ) {
						$directory = trailingslashit( $directory ) . $name;

						if ( ! @ftp_chdir( $conn, $directory ) ) {
							@ftp_mkdir( $conn, $directory );
						}
					}
				}
			}
		}

		return $directory;
	}

	public function ftp_upload( $file, $host, $user, $password, $port = 21, $directory = '' ) {
		if ( is_array( $file ) && isset( $file['tmp_name'] ) ) {
			$conn = $this->ftp_connect( $host, $user, $password, $port );

			if ( false !== $conn ) {
				$current_path = @ftp_pwd( $conn );

				if ( ! empty( $directory ) ) {
					$this->ftp_create_directory( $conn, $directory );
				} else {
					@ftp_chdir( $conn, '~' );
				}

				$new_name = $file['name'];

				if ( 1 == $this->get_option( 'current_time_name' ) ) {
					$info = pathinfo( $new_name );

					if ( isset( $info['extension'] ) && ! empty( $info['extension'] ) ) {
						$new_name = $info['filename'] . '-' . current_time( 'timestamp' ) . '.' . $info['extension'];
					}
				}

				$uploaded = @ftp_put( $conn, $new_name, $file['tmp_name'], FTP_BINARY );

				@ftp_close( $conn );
			}
		}

		return false;
	}

	public function get_terms( $args = array() ) {
		$query = new WP_Term_Query( $args );

		return $query->get_terms();
	}

	public function get_terms_by_term( $taxonomy, $args = array() ) {
		$term = isset( $args['term'] ) ? $args['term'] : '';

		if ( ! ( $term instanceof WP_Term ) && is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();
		}

		if ( $term instanceof WP_Term ) {
			$post_type = isset( $args['post_type'] ) ? $args['post_type'] : 'post';

			$query_args = array(
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				'posts_per_page' => - 1,
				'fields'         => 'ids',
				'tax_query'      => array(
					array(
						'taxonomy' => $term->taxonomy,
						'field'    => 'ids',
						'terms'    => array( $term->term_id )
					)
				)
			);

			$query = new WP_Query( $query_args );

			if ( $query->have_posts() ) {
				if ( ! taxonomy_exists( $taxonomy ) ) {
					$taxonomy = isset( $args['taxonomy'] ) ? $args['taxonomy'] : '';
				}

				return wp_get_object_terms( $query->posts, $taxonomy, $args );
			}
		}

		return false;
	}

	public function get_tags_by_category( $args ) {
		global $wpdb;

		if ( ! $this->array_has_value( $args ) ) {
			if ( $this->is_positive_number( $args ) ) {
				$args = array(
					'taxonomy' => 'category',
					'term_ids' => array( $args )
				);
			} elseif ( $args instanceof WP_Term ) {
				$args = array(
					'taxonomy' => $args->taxonomy,
					'term_ids' => array( $args->term_id )
				);
			}
		}

		$taxonomy     = isset( $args['taxonomy'] ) ? $args['taxonomy'] : 'category';
		$term_ids     = isset( $args['term_ids'] ) ? $args['term_ids'] : '';
		$tag_taxonomy = isset( $args['tag_taxonomy'] ) ? $args['tag_taxonomy'] : 'post_tag';

		if ( ! $this->array_has_value( $term_ids ) ) {
			return null;
		}

		$term_ids = implode( ',', $term_ids );

		$where = "t1.taxonomy = '$taxonomy' AND p1.post_status = 'publish' AND terms1.term_id IN (" . $term_ids . ") AND
			t2.taxonomy = '$tag_taxonomy' AND p2.post_status = 'publish'
			AND p1.ID = p2.ID";

		$post_type = isset( $args['post_type'] ) ? $args['post_type'] : '';

		if ( ! empty( $post_type ) ) {
			if ( ! is_array( $post_type ) ) {
				$post_type = array( $post_type );
			}

			$type = '';

			foreach ( $post_type as $pt ) {
				$type .= " AND p1.post_type = '$pt' AND p2.post_type = '$pt'";
			}

			$where .= $type;
		}

		$tags = $wpdb->get_results( "
			SELECT DISTINCT terms2.term_id as tag_id, terms2.name as tag_name, null as tag_link
			FROM $wpdb->posts as p1
				LEFT JOIN $wpdb->term_relationships as r1 ON p1.ID = r1.object_ID
				LEFT JOIN $wpdb->term_taxonomy as t1 ON r1.term_taxonomy_id = t1.term_taxonomy_id
				LEFT JOIN $wpdb->terms as terms1 ON t1.term_id = terms1.term_id,
				$wpdb->posts as p2
				LEFT JOIN $wpdb->term_relationships as r2 ON p2.ID = r2.object_ID
				LEFT JOIN $wpdb->term_taxonomy as t2 ON r2.term_taxonomy_id = t2.term_taxonomy_id
				LEFT JOIN $wpdb->terms as terms2 ON t2.term_id = terms2.term_id
			WHERE $where
			ORDER by tag_name
		" );

		$result = array();

		foreach ( $tags as $tag ) {
			$term = get_term_by( 'id', $tag->tag_id, $tag_taxonomy );

			if ( $term instanceof WP_Term ) {
				$result[] = $term;
			}
		}

		if ( $this->array_has_value( $result ) ) {
			$number = isset( $args['number'] ) ? $args['number'] : '';

			if ( $this->is_positive_number( $number ) && count( $result ) > $number ) {
				$result = array_slice( $result, 0, $number );
			}
		}

		return $result;
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

	public function get_image_sizes() {
		global $_wp_additional_image_sizes;

		$sizes = array();

		foreach ( get_intermediate_image_sizes() as $_size ) {
			if ( in_array( $_size, array( 'thumbnail', 'medium', 'medium_large', 'large' ) ) ) {
				$sizes[ $_size ]['width']  = get_option( "{$_size}_size_w" );
				$sizes[ $_size ]['height'] = get_option( "{$_size}_size_h" );
				$sizes[ $_size ]['crop']   = (bool) get_option( "{$_size}_crop" );
			} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
				$sizes[ $_size ] = array(
					'width'  => $_wp_additional_image_sizes[ $_size ]['width'],
					'height' => $_wp_additional_image_sizes[ $_size ]['height'],
					'crop'   => $_wp_additional_image_sizes[ $_size ]['crop'],
				);
			}
		}

		return $sizes;
	}

	public function webshot( $website_url, $size = '' ) {
		if ( ! $website_url || empty ( $website_url ) ) {
			return false;
		}

		$sizes = $this->get_image_sizes();

		if ( is_string( $size ) ) {
			if ( ! empty( $size ) && isset( $sizes[ $size ] ) ) {
				$size = $sizes[ $size ];
			}
		}

		if ( ! is_array( $size ) ) {
			if ( $this->array_has_value( $sizes ) ) {
				$size = current( $sizes );
			} else {
				$size = array();
			}
		}

		$size = wp_parse_args( $size, array(
			'width'  => 800,
			'height' => 500
		) );

		$url = 'http://s.wordpress.com/mshots/v1/' . urlencode( $website_url );

		$url = add_query_arg( array( 'h' => $size['height'], 'w' => $size['width'] ), $url );

		return $url;
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

	public function is_IP( $IP ) {
		return filter_var( $IP, FILTER_VALIDATE_IP );
	}

	public function get_domain_name( $url, $root = false ) {
		if ( ! is_string( $url ) || empty( $url ) ) {
			return '';
		}

		if ( false === $this->string_contain( $url, 'http://' ) && false === $this->string_contain( $url, 'https://' ) ) {
			$url = 'http://' . $url;
		}

		$url    = strval( $url );
		$parse  = parse_url( $url );
		$result = isset( $parse['host'] ) ? $parse['host'] : '';

		if ( $root && ! $this->is_IP( $result ) ) {
			$tmp = explode( '.', $result );

			while ( count( $tmp ) > 2 ) {
				array_shift( $tmp );
			}

			$result = implode( '.', $tmp );
		}

		return $result;
	}

	public function post_exists( $mixed ) {
		if ( $this->is_positive_number( $mixed ) ) {
			$obj = get_post( $mixed );

			return ( $obj instanceof WP_Post );
		}

		if ( is_string( $mixed ) ) {
			return post_exists( $mixed );
		}

		return false;
	}

	public function create_install_plugin_url( $plugin_slug ) {
		$action = 'install-plugin';

		$url = add_query_arg(
			array(
				'action' => $action,
				'plugin' => $plugin_slug
			),
			admin_url( 'update.php' )
		);

		return wp_nonce_url( $url, $action . '_' . $plugin_slug );
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

				if ( $this->sub_menu ) {
					add_options_page( $page_title, $menu_title, 'manage_options', $this->option_name, $this->options_page_callback );
				} else {
					add_menu_page( $page_title, $menu_title, 'manage_options', $this->option_name, $this->options_page_callback, $this->menu_icon );
					add_submenu_page( $this->option_name, $page_title, $menu_title, 'manage_options', $this->option_name, $this->options_page_callback );
				}
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
			settings_errors();

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

	public function add_settings_field( $id, $title, $callback = null, $section = 'default', $args = array() ) {
		if ( ! isset( $args['label_for'] ) ) {
			$args['label_for'] = $id;
		}

		if ( ! isset( $args['name'] ) ) {
			$args['name'] = $this->get_option_name() . '[' . $id . ']';
		}

		if ( ! isset( $args['value'] ) ) {
			$args['value'] = $this->get_option( $id );
		}

		if ( ! is_callable( $callback ) ) {
			$callback = array( $this, 'admin_setting_field_input' );
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

		$attributes = isset( $args['attributes'] ) ? $args['attributes'] : '';

		$atts = '';

		if ( $this->array_has_value( $attributes ) ) {
			foreach ( (array) $attributes as $key => $att_value ) {
				$atts .= $key . '="' . esc_attr( $att_value ) . '" ';
			}

			$atts = trim( $atts );
		}

		if ( 'checkbox' == $type || 'radio' == $type ) {
			$label     = isset( $args['label'] ) ? $args['label'] : '';
			$show_desc = true;

			if ( empty( $label ) ) {
				$label     = isset( $args['description'] ) ? $args['description'] : '';
				$show_desc = false;
			}

			$field_value = isset( $args['field_value'] ) ? $args['field_value'] : 1;
			?>
			<label for="<?php echo esc_attr( $id ); ?>">
				<input name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>"
				       id="<?php echo esc_attr( $id ); ?>"
				       value="<?php echo esc_attr( $field_value ); ?>"
				       class="regular-text"<?php checked( $value, $field_value ); ?><?php echo $atts; ?>> <?php echo $label; ?>
			</label>
			<?php
			if ( $show_desc ) {
				$this->field_description( $args );
			}
		} else {
			?>
			<label for="<?php echo esc_attr( $id ); ?>"></label>
			<input name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>"
			       id="<?php echo esc_attr( $id ); ?>"
			       value="<?php echo esc_attr( $value ); ?>"
			       class="regular-text"<?php echo $atts; ?>>
			<?php
			$this->field_description( $args );
		}
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

	public function admin_setting_field_editor( $args ) {
		$value = $args['value'];
		$id    = isset( $args['label_for'] ) ? $args['label_for'] : '';
		$name  = isset( $args['name'] ) ? $args['name'] : '';

		if ( ! isset( $args['textarea_name'] ) ) {
			$args['textarea_name'] = $name;
		}

		wp_editor( $value, $id, $args );
		$this->field_description( $args );
	}

	public function admin_setting_field_select( $args ) {
		$value   = $args['value'];
		$id      = isset( $args['label_for'] ) ? $args['label_for'] : '';
		$name    = isset( $args['name'] ) ? $args['name'] : '';
		$options = isset( $args['options'] ) ? $args['options'] : '';

		$option_none = isset( $args['option_none'] ) ? $args['option_none'] : '';
		$label       = isset( $args['label'] ) ? $args['label'] : '';
		$class       = isset( $args['class'] ) ? $args['class'] : 'widefat';
		?>
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo $label; ?></label>
		<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>"
		        class="<?php echo $class; ?>">
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
		<button type="button" class="change-media custom-media button"
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
		$button_text = __( 'Insert Media', $this->textdomain );
		?>
		<script>
			jQuery(document).ready(function ($) {
				var body = $("body");

				(function () {
					$("body").on("click", ".custom-media.change-media", function (e) {
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
								title: "<?php echo $button_text; ?>",
								library: {},
								button: {
									text: "<?php echo $button_text; ?>"
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
				})();

				// Fix current submenu but parent menu not open.
				(function () {
					function wpFixMenuNotOpen(menuItem) {
						if (menuItem.length) {
							var topMenu = menuItem.closest("li.menu-top"),
								notCurrentClass = "wp-not-current-submenu";

							if (topMenu.hasClass(notCurrentClass)) {
								var openClass = "wp-has-current-submenu wp-menu-open";
								topMenu.removeClass(notCurrentClass).addClass(openClass);
								topMenu.children("a").removeClass(notCurrentClass).addClass(openClass);
							}
						}
					}

					$(".wp-has-submenu .wp-submenu li.current").each(function () {
						var that = this,
							element = $(that);

						wpFixMenuNotOpen(element);
					});

					if (body.hasClass("post-new-php") || body.hasClass("post-php")) {
						var postType = body.find("#post_type");

						if (postType.length && $.trim(postType.val())) {
							var menuLink = body.find("a[href='edit.php?post_type=" + postType.val() + "']");

							if (menuLink.length) {
								var menuItem = menuLink.parent();

								menuItem.addClass("current");
								wpFixMenuNotOpen(menuItem);
							}
						}
					}
				})();
			});
		</script>
		<?php
	}

	public function created_by_log() {
		$show = apply_filters( 'hocwp_plugin_console_log_created_by', true );

		if ( $show ) {
			$name = $this->get_plugin_info( 'Name' );
			?>
			<script>
				console.log("%c<?php printf(__('Plugin %s is created by %s', $this->textdomain), $name, 'HocWP Team - http://hocwp.net'); ?>", "font-size:16px;color:red;font-family:tahoma;padding:10px 0");
			</script>
			<?php
		}
	}

	public function admin_notices_require_php_version() {
		?>
		<div class="updated settings-error error notice is-dismissible">
			<p><?php printf( __( '<strong>Error:</strong> Plugin %s requires PHP version at least %s, please upgrade it or contact your hosting provider.', $this->textdomain ), $this->get_plugin_info( 'Name' ), $this->require_php_version ); ?></p>
		</div>
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

		$version = phpversion();

		if ( version_compare( $this->require_php_version, $version, '>' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notices_require_php_version' ) );

			return;
		}

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'admin_init_action' ), 20 );
			add_filter( 'plugin_action_links_' . $this->base_name, array( $this, 'action_links_filter' ), 20 );
			add_action( 'admin_menu', array( $this, 'admin_menu_action' ), 20 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'admin_footer', array( $this, 'admin_footer' ) );
		}

		add_action( 'admin_footer', array( $this, 'created_by_log' ) );
		add_action( 'wp_footer', array( $this, 'created_by_log' ) );
		add_action( 'login_footer', array( $this, 'created_by_log' ) );
	}

	public function __construct() {
		$this->init();
	}
}