<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Auto_Fetch_Post_Core {
	protected static $instance;
	public $core_version = '1.0.0';

	public $require_php_version = '5.6';
	public $required_plugins;

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

	public $safe_string = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	public $date_format;
	public $time_format;

	public $user;

	public function is_empty_string( $string ) {
		return ( is_string( $string ) && empty( $string ) );
	}

	public function random_string( $length = 10, $keyspace = '' ) {
		if ( empty( $keyspace ) ) {
			$keyspace = $this->safe_string;
		}

		$pieces = array();

		$max = mb_strlen( $keyspace, '8bit' ) - 1;

		for ( $i = 0; $i < $length; ++ $i ) {
			$index = random_int( 0, $max );

			$pieces[] = $keyspace[ $index ];
		}

		return implode( '', $pieces );
	}

	public function string_to_datetime( $string, $format = '' ) {
		if ( empty( $format ) ) {
			$format = 'Y-m-d H:i:s';
		}

		$string = str_replace( '/', '-', $string );
		$string = trim( $string );

		$totime = strtotime( $string );

		$result = date( $format, $totime );

		return $result;
	}

	public static function javascript_datetime_format( $php_format ) {
		$matched_symbols = array(
			'd' => 'dd',
			'D' => 'D',
			'j' => 'd',
			'l' => 'DD',
			'N' => '',
			'S' => '',
			'w' => '',
			'z' => 'o',
			'W' => '',
			'F' => 'MM',
			'm' => 'mm',
			'M' => 'M',
			'n' => 'm',
			't' => '',
			'L' => '',
			'o' => '',
			'Y' => 'yy',
			'y' => 'y',
			'a' => '',
			'A' => '',
			'B' => '',
			'g' => '',
			'G' => '',
			'h' => '',
			'H' => '',
			'i' => '',
			's' => '',
			'u' => ''
		);

		$result   = '';
		$escaping = false;

		for ( $i = 0; $i < strlen( $php_format ); $i ++ ) {
			$char = $php_format[ $i ];

			if ( isset( $matched_symbols[ $char ] ) ) {
				$result .= $matched_symbols[ $char ];
			} else {
				$result .= $char;
			}
		}

		if ( $escaping ) {
			$result = esc_attr( $result );
		}

		return $result;
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

	public function get_max_upload_size() {
		$max_upload   = (int) ( ini_get( 'upload_max_filesize' ) );
		$max_post     = (int) ( ini_get( 'post_max_size' ) );
		$memory_limit = (int) ( ini_get( 'memory_limit' ) );
		$upload_mb    = min( $max_upload, $max_post, $memory_limit );

		return $upload_mb;
	}

	/**
	 * Notation to numbers.
	 *
	 * This function transforms the php.ini notation for numbers (like '2M') to an integer.
	 *
	 * @param  string $size Size value.
	 *
	 * @return int
	 */
	public function let_to_num( $size ) {
		$size = str_replace( ' ', '', $size );
		$size = trim( $size );

		$l   = substr( $size, - 1 );
		$ret = substr( $size, 0, - 1 );

		$byte = 1024;

		switch ( strtoupper( $l ) ) {
			case 'P':
				$ret *= $byte;
			case 'T':
				$ret *= $byte;
			case 'G':
				$ret *= $byte;
			case 'M':
				$ret *= $byte;
			case 'K':
				$ret *= $byte;
		}

		unset( $l, $byte );

		return $ret;
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

	public function get_youtube_video_id( $url ) {
		$id = '';

		if ( false === strpos( $url, 'http' ) ) {
			$id = $url;
		} else {
			if ( false !== strpos( $url, '?v=' ) ) {
				$parts = parse_url( $url );

				if ( isset( $parts['query'] ) ) {
					parse_str( $parts['query'], $query );

					$id = isset( $query['v'] ) ? $query['v'] : '';
				}
			} elseif ( false !== strpos( $url, '/embed/' ) ) {
				$parts = explode( '/embed/', $url );
				$id    = array_pop( $parts );
			} elseif ( false !== strpos( $url, 'youtu.be/' ) ) {
				$parts = explode( 'youtu.be/', $url );
				$id    = array_pop( $parts );
			} elseif ( false !== strpos( $url, 'youtube.com/video/' ) ) {
				$parts = explode( 'youtube.com/video/', $url );
				$parts = array_pop( $parts );
				$parts = explode( '/', $parts );

				if ( isset( $parts[0] ) && ! empty( $parts[0] ) ) {
					$id = $parts[0];
				}
			}
		}

		return $id;
	}

	public function get_youtube_video_thumbnail_url( $video_id ) {
		if ( false !== strpos( $video_id, 'http' ) || false !== strpos( $video_id, 'www' ) || false !== strpos( $video_id, 'youtu' ) ) {
			$video_id = $this->get_youtube_video_id( $video_id );
		}

		return 'http://img.youtube.com/vi/' . $video_id . '/0.jpg';
	}

	public function get_youtube_video_info( $video_id, $api_key ) {
		if ( empty( $api_key ) ) {
			return new WP_Error( 'invalid_google_api_key', __( 'Invalid Google API Key.', $this->textdomain ) );
		}

		$url = 'https://www.googleapis.com/youtube/v3/videos?part=snippet&id=' . $video_id . '&key=' . $api_key;
		$res = wp_remote_get( $url );

		$res = wp_remote_retrieve_body( $res );

		if ( ! empty( $res ) ) {
			$res = json_decode( $res );

			if ( is_object( $res ) && ! is_wp_error( $res ) && isset( $res->items ) && $this->array_has_value( $res->items ) ) {
				return current( $res->items );
			}
		}

		return $res;
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

				return $uploaded;
			}
		}

		return false;
	}

	public function get_meta_or_option( $object_id, $meta_key, $object_type = 'post' ) {
		$value = get_metadata( $object_type, $object_id, $meta_key, true );

		if ( '' == $value ) {
			$value = $this->get_option( $meta_key );
		}

		return $value;
	}

	public function get_terms( $args = array() ) {
		$query = new WP_Term_Query( $args );

		return $query->get_terms();
	}

	public function term_exists( $value, $taxonomy, $output = ARRAY_A, $by = 'default' ) {
		if ( 'default' === $by ) {
			return term_exists( $value );
		}

		$term = get_term_by( $by, $value, $taxonomy, $output );

		if ( $this->is_positive_number( $term ) ) {
			$tmp = get_term( $term, $taxonomy );

			if ( ! ( $tmp instanceof WP_Term ) ) {
				return false;
			}
		}

		if ( $term instanceof WP_Term && OBJECT != $output ) {
			return array(
				'term_id'          => $term->term_id,
				'term_taxonomy_id' => $term->term_taxonomy_id
			);
		}

		return $term;
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

	public function mysql_update( $table, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'set'   => '',
			'where' => ''
		);

		$args = wp_parse_args( $args, $defaults );

		$set = $args['set'];

		if ( empty( $set ) ) {
			return false;
		}

		$where = $args['where'];

		$sql = 'UPDATE ' . $table;
		$sql .= ' SET ' . $set;

		$sql .= ' WHERE 1 = 1';

		if ( $this->array_has_value( $where ) ) {
			$tmp = '';

			foreach ( $where as $key => $value ) {
				$tmp .= sprintf( " AND %s = '%s'", $key, $value );
			}

			$where = $tmp;
		}

		$where = trim( $where );
		$sql .= ' ' . $where;
		$sql = trim( $sql );

		return $wpdb->query( $sql );
	}

	public function mysql_insert( $table, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'columns' => '',
			'values'  => ''
		);

		$args = wp_parse_args( $args, $defaults );

		$columns = $args['columns'];
		$values  = $args['values'];

		if ( empty( $columns ) || empty( $values ) ) {
			return false;
		}

		$sql = 'INSERT ' . 'INTO ' . $table;
		$sql .= ' (' . $columns . ')';
		$sql .= ' VALUES (' . $values . ')';

		return $wpdb->query( $sql );
	}

	public function mysql_delete( $table, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'where' => ''
		);

		$args = wp_parse_args( $args, $defaults );

		$sql = 'DELETE ' . 'FROM ' . $table;
		$sql .= ' WHERE 1 = 1';

		$where = $args['where'];

		if ( $this->array_has_value( $where ) ) {
			$tmp = '';

			foreach ( $where as $key => $value ) {
				$tmp .= sprintf( " AND %s = '%s'", $key, $value );
			}

			$where = $tmp;
		}

		$where = trim( $where );
		$sql .= ' ' . $where;
		$sql = trim( $sql );

		return $wpdb->query( $sql );
	}

	public function mysql_select( $table, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'select'   => '*',
			'join'     => '',
			'where'    => '',
			'group_by' => '',
			'having'   => '',
			'order_by' => '',
			'offset'   => '',
			'limit'    => '',
			'order'    => 'ASC',
			'output'   => OBJECT
		);

		$args = wp_parse_args( $args, $defaults );

		$select   = $args['select'];
		$join     = $args['join'];
		$where    = $args['where'];
		$group_by = $args['group_by'];
		$having   = $args['having'];
		$order_by = $args['order_by'];
		$order    = $args['order'];
		$offset   = $args['offset'];
		$limit    = $args['limit'];

		$sql = "SELECT $select";
		$sql .= " FROM $table";

		$join = trim( $join );
		$sql .= ' ' . $join;
		$sql = trim( $sql );

		$sql .= ' WHERE 1 = 1';

		if ( $this->array_has_value( $where ) ) {
			$tmp = '';

			foreach ( $where as $key => $value ) {
				$tmp .= sprintf( " AND %s = '%s'", $key, $value );
			}

			$where = $tmp;
		}

		$where = trim( $where );
		$sql .= ' ' . $where;
		$sql = trim( $sql );

		$group_by = trim( $group_by );
		$sql .= ' ' . $group_by;
		$sql = trim( $sql );

		$having = trim( $having );
		$sql .= ' ' . $having;
		$sql = trim( $sql );

		$order_by = trim( $order_by );

		if ( ! empty( $order_by ) ) {
			$sql .= ' ORDER BY ' . $order_by;
			$sql = trim( $sql );

			$order = trim( $order );
			$order = strtoupper( $order );
			$sql .= ' ' . $order;
			$sql = trim( $sql );
		}

		if ( ! empty( $limit ) ) {
			$limit  = absint( $limit );
			$offset = absint( $offset );

			$sql .= " LIMIT $offset, $limit";
		}

		return $wpdb->get_results( $sql, $args['output'] );
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

	public function get_media_id_by_url( $url ) {
		global $wpdb;

		$sql = 'SELECT ID';
		$sql .= ' FROM ' . $wpdb->posts;
		$sql .= " WHERE guid='%s'";

		$sql = $wpdb->prepare( $sql, $url );

		$attachment = $wpdb->get_col( $sql );

		if ( $this->array_has_value( $attachment ) && isset( $attachment[0] ) ) {
			return $attachment[0];
		}

		return false;
	}

	public function set_post_thumbnail( $post_id, $id_or_url ) {
		if ( ! $this->is_positive_number( $id_or_url ) && ! empty( $id_or_url ) ) {
			$id = $this->get_media_id_by_url( $id_or_url );

			if ( ! $this->is_positive_number( $id ) ) {
				$id = $this->download_image( $id_or_url );
			}

			if ( $this->is_positive_number( $id ) ) {
				$id_or_url = $id;
			}

			unset( $id );
		}

		if ( $this->is_positive_number( $id_or_url ) ) {
			return set_post_thumbnail( $post_id, $id_or_url );
		}

		return false;
	}

	public function download_image( $url, $name = '' ) {
		if ( ! $url || empty ( $url ) ) {
			return false;
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
		}

		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
		}

		$file_array = array(
			'tmp_name' => download_url( $url )
		);

		if ( empty( $file_array['tmp_name'] ) || is_wp_error( $file_array['tmp_name'] ) ) {
			return false;
		}

		if ( $name ) {
			$file_array['name'] = $name;
		} else {
			preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file_array['tmp_name'], $matches );

			if ( ! empty( $matches ) ) {
				$file_array['name'] = basename( $matches[0] );
			} else {
				$file_array['name'] = uniqid( 'downloaded-' ) . '.jpeg';
			}
		}

		$id = media_handle_sideload( $file_array, 0 );

		if ( is_wp_error( $id ) ) {
			@unlink( $file_array['tmp_name'] );

			return false;
		}

		return $id;
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

	public function convert_array_attributes_to_string( $attributes ) {
		$atts = '';

		if ( $this->array_has_value( $attributes ) ) {
			foreach ( (array) $attributes as $key => $att_value ) {
				$atts .= $key . '="' . esc_attr( $att_value ) . '" ';
			}

			$atts = trim( $atts );
		} else {
			$atts = $attributes;
		}

		if ( empty( $atts ) ) {
			$atts = '';
		}

		return $atts;
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

	public function replace_last( $search, $replace, $subject ) {
		$pos = strrpos( $subject, $search );

		if ( $pos !== false ) {
			$subject = substr_replace( $subject, $replace, $pos, strlen( $search ) );
		}

		return $subject;
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

	public function get_current_url( $with_params = true ) {
		$url = ( isset( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] == 'on' ? 'https' : 'http' ) . '://' . $_SERVER['HTTP_HOST'];

		if ( $with_params ) {
			$url .= $_SERVER['REQUEST_URI'];
		}

		return $url;
	}

	public function get_user_ip( $data = array() ) {
		if ( empty( $data ) ) {
			$data = $_SERVER;
		}

		if ( empty( $data ) ) {
			return '';
		}

		if ( array_key_exists( 'HTTP_X_FORWARDED_FOR', $data ) && ! empty( $data['HTTP_X_FORWARDED_FOR'] ) ) {
			if ( strpos( $data['HTTP_X_FORWARDED_FOR'], ',' ) > 0 ) {
				$addr = explode( ',', $data['HTTP_X_FORWARDED_FOR'] );

				return trim( $addr[0] );
			} else {
				return $data['HTTP_X_FORWARDED_FOR'];
			}
		} else {
			return $data['REMOTE_ADDR'];
		}
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
			'total'   => $max_page
		);

		$args = wp_parse_args( $args, $defaults );

		return paginate_links( $args );
	}

	public function pagination_html( $query, $paged = null, $max_page = null, $args = array() ) {
		echo '<div class="pagination-container">' . PHP_EOL;
		echo $this->pagination( $query, $paged, $max_page, $args );
		echo '</div>' . PHP_EOL;
	}

	public function toastr( $message, $type = 'success', $redirect = '' ) {
		if ( ! empty( $redirect ) ) {
			$redirect = 'setTimeout(function(){window.location.href="' . $redirect . '"},3e3);';
		}

		switch ( $type ) {
			case 'error':
				$type = 'toastr.error("' . $message . '");';
				break;
			case 'warning':
				$type = 'toastr.warning("' . $message . '");';
				break;
			case 'success':
				$type = 'toastr.success("' . $message . '");';
				break;
			default:
				$type = 'toastr.info("' . $message . '");';
		}
		?>
		<script>
			jQuery(document).ready(function () {
				(function () {
					if ("undefine" != typeof toastr) {
						toastr.options = {
							preventDuplicates: true
						};

						<?php echo $type; ?>
					} else {
						alert("<?php echo $message; ?>");
					}

					<?php echo $redirect; ?>
				})();
			});
		</script>
		<?php
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

		if ( ! is_array( $options ) ) {
			$options = array();
		}

		if ( $this->array_has_value( $this->option_defaults ) ) {
			$options = wp_parse_args( $options, $this->option_defaults );
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

			if ( ! empty( $page_title ) && ! empty( $this->option_name ) ) {
				$menu_title = isset( $this->labels['options_page']['menu_title'] ) ? $this->labels['options_page']['menu_title'] : $page_title;

				if ( ! is_callable( $this->options_page_callback ) ) {
					$this->options_page_callback = array( $this, 'options_page_callback' );
				}

				if ( $this->sub_menu ) {
					add_options_page( $page_title, $menu_title, 'manage_options', $this->option_name, $this->options_page_callback );
				} else {
					add_menu_page( $page_title, $menu_title, 'manage_options', $this->option_name, $this->options_page_callback, $this->menu_icon );
					add_submenu_page( $this->option_name, $page_title, $menu_title, 'manage_options', $this->option_name, $this->options_page_callback );

					if ( ! empty( $this->sub_menu_label ) ) {
						global $submenu;

						if ( is_array( $submenu ) && isset( $submenu[ $this->option_name ][0][0] ) ) {
							$submenu[ $this->option_name ][0][0] = $this->sub_menu_label;
						}
					}
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
			if ( ! isset( $_REQUEST['settings-updated'] ) ) {
				settings_errors();
			}

			if ( $this->array_has_value( $this->setting_tabs ) ) {
				?>
				<h2 class="nav-tab-wrapper">
					<?php
					if ( empty( $this->setting_tab ) ) {
						reset( $this->setting_tabs );
						$this->setting_tab = key( $this->setting_tabs );
					}

					foreach ( $this->setting_tabs as $tab => $data ) {
						$url = admin_url();
						$url = add_query_arg( 'page', $this->option_name, $url );
						$url = add_query_arg( 'tab', $tab, $url );

						$nav_class = 'nav-tab';

						if ( $tab == $this->setting_tab ) {
							$nav_class .= ' nav-tab-active';
						}

						$text = $tab;

						if ( isset( $data['text'] ) && ! empty( $data['text'] ) ) {
							$text = $data['text'];
						}
						?>
						<a href="<?php echo $url; ?>"
						   class="<?php echo $nav_class; ?>"><?php echo $text; ?></a>
						<?php
					}
					?>
				</h2>
				<?php
			}

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
			$callback = array( $this, $callback );

			if ( ! is_callable( $callback ) ) {
				$callback = array( $this, 'admin_setting_field_input' );
			}
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

		$atts = isset( $args['attributes'] ) ? $args['attributes'] : '';
		$atts = $this->convert_array_attributes_to_string( $atts );

		$class = isset( $args['class'] ) ? $args['class'] : '';

		if ( empty( $class ) ) {
			$class = 'regular-text';
		}

		if ( 'checkbox' == $type || 'radio' == $type ) {
			$label     = isset( $args['label'] ) ? $args['label'] : '';
			$show_desc = true;

			if ( empty( $label ) ) {
				$label     = isset( $args['description'] ) ? $args['description'] : '';
				$show_desc = false;
			}

			if ( empty( $label ) ) {
				$label = isset( $args['text'] ) ? $args['text'] : '';
			}

			$field_value = isset( $args['field_value'] ) ? $args['field_value'] : 1;
			?>
			<label for="<?php echo esc_attr( $id ); ?>">
				<input name="<?php echo esc_attr( $name ); ?>" type="<?php echo esc_attr( $type ); ?>"
				       id="<?php echo esc_attr( $id ); ?>"
				       value="<?php echo esc_attr( $field_value ); ?>"
				       class="<?php echo esc_attr( $class ); ?>"<?php checked( $value, $field_value ); ?><?php echo $atts; ?>> <?php echo $label; ?>
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
			       class="<?php echo esc_attr( $class ); ?>"<?php echo $atts; ?>>
			<?php
			$this->field_description( $args );
		}
	}

	public function admin_setting_field_input_size( $args ) {
		$value = $args['value'];
		$type  = 'number';
		$id    = isset( $args['label_for'] ) ? $args['label_for'] : '';
		$name  = isset( $args['name'] ) ? $args['name'] : '';

		$atts = isset( $args['attributes'] ) ? $args['attributes'] : '';
		$atts = $this->convert_array_attributes_to_string( $atts );

		$width  = isset( $value['width'] ) ? $value['width'] : '';
		$height = isset( $value['height'] ) ? $value['height'] : '';
		?>
		<label for="<?php echo esc_attr( $id ); ?>_width"></label>
		<input name="<?php echo esc_attr( $name ); ?>[width]" type="<?php echo esc_attr( $type ); ?>"
		       id="<?php echo esc_attr( $id ); ?>_width"
		       value="<?php echo esc_attr( $width ); ?>"
		       class="small-text"<?php echo $atts; ?>>
		<span>x</span>
		<label for="<?php echo esc_attr( $id ); ?>_height"></label>
		<input name="<?php echo esc_attr( $name ); ?>[height]" type="<?php echo esc_attr( $type ); ?>"
		       id="<?php echo esc_attr( $id ); ?>_height"
		       value="<?php echo esc_attr( $height ); ?>"
		       class="small-text"<?php echo $atts; ?>>
		<span><?php _e( 'Pixels', $this->textdomain ); ?></span>
		<?php
		$this->field_description( $args );
	}

	public function admin_setting_field_textarea( $args ) {
		$value = $args['value'];
		$id    = isset( $args['label_for'] ) ? $args['label_for'] : '';
		$name  = isset( $args['name'] ) ? $args['name'] : '';

		$class = isset( $args['class'] ) ? $args['class'] : '';

		if ( empty( $class ) ) {
			$class = 'widefat';
		}
		?>
		<label for="<?php echo esc_attr( $id ); ?>"></label>
		<textarea name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>"
		          class="<?php echo esc_attr( $class ); ?>"
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

		$atts = isset( $args['attributes'] ) ? $args['attributes'] : '';
		$atts = $this->convert_array_attributes_to_string( $atts );
		?>
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo $label; ?></label>
		<select name="<?php echo esc_attr( $name ); ?>" id="<?php echo esc_attr( $id ); ?>"
		        class="<?php echo esc_attr( $class ); ?>"<?php echo $atts; ?>>
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

					$selected = false;

					if ( $value === $current || ( is_array( $value ) && in_array( $current, $value ) ) ) {
						$selected = true;
					}
					?>
					<option
						value="<?php echo esc_attr( $current ); ?>"<?php selected( $selected, true ); ?>><?php echo $text; ?></option>
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

	public function admin_setting_field_chosen( $args ) {
		$atts = isset( $args['attributes'] ) ? $args['attributes'] : '';

		if ( ! is_array( $atts ) ) {
			$atts = array();
		}

		$atts['data-chosen'] = 1;

		if ( ! isset( $atts['multiple'] ) ) {
			$atts['multiple'] = 'multiple';

			$name = isset( $args['name'] ) ? $args['name'] : '';

			if ( ! empty( $name ) && false === strpos( $name, '[]' ) ) {
				$name .= '[]';
				$args['name'] = $name;
			}
		}

		$args['attributes'] = $atts;

		$this->admin_setting_field_select( $args );
	}

	public function admin_setting_field_posts( $args ) {
		$post_type = isset( $args['post_type'] ) ? $args['post_type'] : '';

		if ( empty( $post_type ) ) {
			$post_type = 'post';
		}

		$query_args = array(
			'post_type'      => $post_type,
			'posts_per_page' => - 1,
			'post_status'    => 'publish',
			'paged'          => 1
		);

		$query = new WP_Query( $query_args );

		$label = __( '-- Choose post --', $this->textdomain );

		if ( is_string( $post_type ) ) {
			$object = get_post_type_object( $post_type );
			$label  = sprintf( __( '-- Choose %s --', $this->textdomain ), $object->labels->singular_name );
		}

		$args['option_none'] = '<option value="">' . $label . '</option>';

		if ( $query->have_posts() ) {
			$options = array();

			foreach ( $query->get_posts() as $post ) {
				$options[ $post->ID ] = $post->post_title;
			}

			$args['options'] = $options;
		}

		$args['class'] = 'regular-text';

		$this->admin_setting_field_select( $args );
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

	public function Ascending( $a, $b ) {
		if ( $a == $b ) {
			return 0;
		}

		return ( $a < $b ) ? - 1 : 1;
	}

	public function Descending( $a, $b ) {
		if ( $a == $b ) {
			return 0;
		}

		return ( $a > $b ) ? - 1 : 1;
	}

	public function created_by_log() {
		$show = apply_filters( 'hocwp_plugin_console_log_created_by', true );

		if ( $show ) {
			$name = $this->get_plugin_info( 'Name' );
			?>
			<script>
				console.log("%c<?php printf( __( 'Plugin %s is created by %s', $this->textdomain ), $name, 'HocWP Team - http://hocwp.net' ); ?>", "font-size:16px;color:red;font-family:tahoma;padding:10px 0");
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

	public function add_setting_tab( $tab ) {
		if ( is_array( $tab ) && isset( $tab['name'] ) && ! empty( $tab['name'] ) && ! in_array( $tab['name'], $this->setting_tabs ) ) {
			$this->setting_tabs[ $tab['name'] ] = $tab;
		}
	}

	public function one_term_taxonomy_action( $post_id ) {
		global $post_type;

		$taxonomies = get_object_taxonomies( $post_type );

		$one_term_taxonomies = array();

		foreach ( $this->one_term_taxonomies as $taxonomy ) {
			if ( in_array( $taxonomy, $taxonomies ) ) {
				$terms = wp_get_object_terms( $post_id, $taxonomy );

				if ( $this->array_has_value( $terms ) && 1 < count( $terms ) ) {
					$term = current( $terms );

					wp_set_object_terms( $post_id, array( $term->term_id ), $taxonomy );
					$one_term_taxonomies[] = $taxonomy;
				}
			}
		}

		$tr_name = 'one_term_taxonomies_notices';

		if ( $this->array_has_value( $one_term_taxonomies ) ) {
			set_transient( $tr_name, $one_term_taxonomies );
		} else {
			delete_transient( $tr_name );
		}
	}

	public function one_term_taxonimes_notices() {
		$tr_name = 'one_term_taxonomies_notices';

		if ( false !== ( $taxonomies = get_transient( $tr_name ) ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				$taxonomy_object = get_taxonomy( $taxonomy );
				?>
				<div class="notice notice-warning is-dismissible">
					<p><?php printf( __( '<strong>Warning:</strong> You can choose one term only for taxonomy %s.', $this->textdomain ), $taxonomy_object->labels->singular_name . ' (' . $taxonomy . ')' ); ?></p>
				</div>
				<?php
			}

			delete_transient( $tr_name );
		}
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

	private function init() {
		$this->base_dir = dirname( $this->plugin_file );
		$this->base_url = plugins_url( '', $this->plugin_file );

		$this->plugins_dir = dirname( $this->base_dir );

		$this->base_name = plugin_basename( $this->plugin_file );

		$this->textdomain = $this->get_plugin_info( 'TextDomain' );
		$this->version    = $this->get_plugin_info( 'Version' );

		if ( null !== $this->option_name ) {
			$this->set_option_name( basename( $this->base_dir ) );
		}

		$this->setting_tabs = array();

		$this->setting_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : '';

		if ( ! is_array( $this->labels ) ) {
			$this->labels = array();
		}

		$this->labels['options_page']['page_title'] = $this->get_plugin_info( 'Name' );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		add_action( 'init', array( $this, 'init_action' ) );

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

			if ( $this->array_has_value( $this->required_plugins ) ) {
				add_action( 'admin_notices', array( $this, 'required_plugins_notices' ) );
			}

			add_action( 'admin_footer', array( $this, 'admin_footer' ) );

			if ( $this->array_has_value( $this->one_term_taxonomies ) ) {
				add_action( 'save_post', array( $this, 'one_term_taxonomy_action' ), 99 );
				add_action( 'admin_notices', array( $this, 'one_term_taxonimes_notices' ) );
			}
		}

		add_action( 'admin_footer', array( $this, 'created_by_log' ) );
		add_action( 'wp_footer', array( $this, 'created_by_log' ) );
		add_action( 'login_footer', array( $this, 'created_by_log' ) );

		if ( $this->doing_ajax ) {
			$path = dirname( dirname( __FILE__ ) ) . '/ajax.php';

			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	public function init_action() {
		if ( false !== get_transient( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_transient( 'flush_rewrite_rules' );
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

	public function create_database_table( $table_name, $sql_column ) {
		if ( false !== strpos( $sql_column, 'CREATE TABLE' ) || false !== strpos( $sql_column, 'create table' ) ) {
			_doing_it_wrong( __FUNCTION__, __( 'The <strong>$sql_column</strong> argument just only contains MySQL query inside (), it isn\'t full MySQL query.', $this->textdomain ), '1.0.0' );

			return;
		}

		global $wpdb;

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
			$charset_collate = '';

			if ( ! empty( $wpdb->charset ) ) {
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			}

			if ( ! empty( $wpdb->collate ) ) {
				$charset_collate .= " COLLATE $wpdb->collate";
			}

			$sql = 'CREATE ' . 'TABLE ';
			$sql .= "$table_name ( $sql_column ) $charset_collate;\n";

			if ( ! function_exists( 'dbDelta' ) ) {
				load_template( ABSPATH . 'wp-admin/includes/upgrade.php' );
			}

			dbDelta( $sql );
		}
	}

	public function is_database_table_exists( $table_name ) {
		global $wpdb;

		if ( ! Cloak_URI()->string_contain( $table_name, $wpdb->prefix ) ) {
			$table_name = $wpdb->prefix . $table_name;
		}

		$result = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );

		if ( empty( $result ) ) {
			return false;
		}

		return true;
	}

	public function get_ajax_url() {
		return apply_filters( 'plugin_' . $this->option_name . '_ajax_url', admin_url( 'admin-ajax.php' ) );
	}

	public function date_i18n( $date, $format = '' ) {
		if ( ! is_numeric( $date ) ) {
			$date = strtotime( $date );
		}

		if ( empty( $format ) ) {
			$format = sprintf( '%s %s', $this->date_format, $this->time_format );
		}

		$now = new DateTime();
		$now->setTimestamp( $date );
		$now->setTimezone( new DateTimeZone( get_option( 'timezone_string' ) ) );

		return $now->format( $format );
	}

	public function __construct() {
		$this->user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';

		$this->is_developing = ( ( defined( 'WP_DEBUG' ) && true === WP_DEBUG ) ? true : false );
		$this->css_suffix    = ( $this->is_developing ) ? '.css' : '.min.css';
		$this->js_suffix     = ( $this->is_developing ) ? '.js' : '.min.js';
		$this->doing_ajax    = ( ( defined( 'DOING_AJAX' ) && true === DOING_AJAX ) ? true : false );
		$this->doing_cron    = ( ( defined( 'DOING_CRON' ) && true === DOING_CRON ) ? true : false );

		$this->date_format = get_option( 'date_format' );
		$this->time_format = get_option( 'time_format' );

		$this->user = wp_get_current_user();

		add_action( 'plugins_loaded', array( $this, 'run_init_action' ), 11 );
	}

	public function run_init_action() {
		$this->init();
	}
}