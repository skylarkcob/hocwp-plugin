<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! trait_exists( 'Woo_Checkout_Images_PHP' ) ) {
	// Load core PHP functions
	require_once dirname( __FILE__ ) . '/trait-php.php';
}

trait Woo_Checkout_Images_Utils {
	use Woo_Checkout_Images_PHP;

	/**
	 * Get size information for all currently-registered image sizes.
	 *
	 * @return array $sizes Data for all currently-registered image sizes.
	 * @uses   get_intermediate_image_sizes()
	 * @global $_wp_additional_image_sizes
	 */
	function get_image_sizes() {
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

	/**
	 * Get size information for a specific image size.
	 *
	 * @param string $size The image size for which to retrieve data.
	 *
	 * @return bool|array $size Size data about an image size or false if the size doesn't exist.
	 * @uses   get_image_sizes()
	 *
	 */
	function get_image_size( $size ) {
		$sizes = $this->get_image_sizes();

		if ( isset( $sizes[ $size ] ) ) {
			return $sizes[ $size ];
		}

		return false;
	}

	/**
	 * Get the width of a specific image size.
	 *
	 * @param string $size The image size for which to retrieve data.
	 *
	 * @return bool|string $size Width of an image size or false if the size doesn't exist.
	 * @uses   get_image_size()
	 *
	 */
	function get_image_width( $size ) {
		if ( ! $size = $this->get_image_size( $size ) ) {
			return false;
		}

		if ( isset( $size['width'] ) ) {
			return $size['width'];
		}

		return false;
	}

	/**
	 * Get the height of a specific image size.
	 *
	 * @param string $size The image size for which to retrieve data.
	 *
	 * @return bool|string $size Height of an image size or false if the size doesn't exist.
	 * @uses   get_image_size()
	 *
	 */
	function get_image_height( $size ) {
		if ( ! $size = $this->get_image_size( $size ) ) {
			return false;
		}

		if ( isset( $size['height'] ) ) {
			return $size['height'];
		}

		return false;
	}

	public function upload_file( $file_name, $bits, $check_bytes = 100 ) {
		$upload = wp_upload_bits( $file_name, null, $bits );

		if ( isset( $upload['file'] ) && file_exists( $upload['file'] ) ) {
			if ( $this->is_positive_number( $check_bytes ) ) {
				$bytes = filesize( $upload['file'] );

				if ( ! $bytes || ! is_numeric( $bytes ) || $bytes < $check_bytes ) {
					unlink( $upload['file'] );

					return $this->upload_file( $file_name, @file_get_contents( $bits ), null );
				}
			}

			$filename = basename( $file_name );

			$filetype = wp_check_filetype( $filename, null );

			$attachment = array(
				'guid'           => $upload['url'],
				'post_mime_type' => $filetype['type'],
				'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
				'post_content'   => '',
				'post_status'    => 'inherit'
			);

			$attach_id = wp_insert_attachment( $attachment, $upload['file'] );

			$upload['id'] = $attach_id;

			if ( $this->is_positive_number( $attach_id ) ) {
				if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
					load_template( ABSPATH . 'wp-admin/includes/image.php' );
				}

				$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
				wp_update_attachment_metadata( $attach_id, $attach_data );
				$upload['data'] = $attach_data;

				unset( $attach_data );
			}

			unset( $filename, $attachment, $attach_id );
		}

		return $upload;
	}

	public function get_attachment_id( $url ) {
		global $wpdb;
		$attachment = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid='%s';", $url ) );

		return isset( $attachment[0] ) ? $attachment[0] : '';
	}

	public function fetch_feed( $args = array(), $rss_object = null ) {
		$defaults = array(
			'number'     => 5,
			'offset'     => 0,
			'url'        => '',
			'expiration' => HOUR_IN_SECONDS
		);

		$args = wp_parse_args( $args, $defaults );

		$number = absint( $args['number'] );
		$offset = absint( $args['offset'] );
		$url    = $args['url'];

		if ( empty( $url ) && $rss_object instanceof SimplePie ) {
			$url = $rss_object->feed_url;
		}

		if ( empty( $url ) && ! ( $rss_object instanceof SimplePie ) ) {
			return '';
		}

		$expiration = $args['expiration'];

		$transient_name = 'hocwp_fetch_feed_' . md5( $url );

		if ( $rss_object instanceof SimplePie ) {
			$max    = $rss_object->get_item_quantity( $number );
			$result = $rss_object->get_items( $offset, $max );
			set_transient( $transient_name, $result, $expiration );
		} elseif ( false === ( $result = get_transient( $transient_name ) ) ) {
			if ( ! function_exists( 'fetch_feed' ) ) {
				include_once( ABSPATH . WPINC . '/feed.php' );
			}

			$result = '';

			if ( $rss_object instanceof SimplePie ) {
				$rss = $rss_object;
			} else {
				$rss = fetch_feed( $url );
			}

			if ( ! is_wp_error( $rss ) ) {
				$max    = $rss->get_item_quantity( $number );
				$result = $rss->get_items( $offset, $max );
				set_transient( $transient_name, $result, $expiration );
			}
		}

		return $result;
	}

	public function object_valid( $obj ) {
		return ( is_object( $obj ) && ! is_wp_error( $obj ) );
	}

	public function get_feed_items( $args = array(), $rss_object = null ) {
		$result = array();
		$items  = $this->fetch_feed( $args, $rss_object );

		if ( $this->array_has_value( $items ) ) {
			foreach ( $items as $item ) {
				if ( ! $this->object_valid( $item ) ) {
					continue;
				}

				if ( $item instanceof SimplePie_Item ) {
					$description = $item->get_description();
					$thumbnail   = $this->get_first_image_source( $description );
					$description = wp_strip_all_tags( $description );
					$content     = $item->get_content();

					if ( empty( $thumbnail ) ) {
						$thumbnail = $this->get_first_image_source( $content );
					}

					$link = $item->get_permalink();

					if ( empty( $link ) ) {
						$link = $item->get_link();
					}

					$value = array(
						'permalink'   => $link,
						'title'       => $item->get_title(),
						'date'        => $item->get_date(),
						'image_url'   => $thumbnail,
						'description' => $description,
						'content'     => $content
					);

					array_push( $result, $value );
				}
			}
		}

		return $result;
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
					@ftp_chdir( $conn, $directory );
				}

				if ( $this->array_has_value( $parts ) ) {
					foreach ( $parts as $name ) {
						$directory = trailingslashit( $directory ) . $name;

						if ( ! @ftp_chdir( $conn, $directory ) ) {
							@ftp_mkdir( $conn, $directory );
							@ftp_chdir( $conn, $directory );
						}
					}
				}
			}
		}

		return $directory;
	}

	public function ftp_delete( $file, $host, $user, $password, $port = 21, $passive = false ) {
		$conn = $this->ftp_connect( $host, $user, $password, $port );

		if ( false !== $conn ) {
			$deleted = false;

			if ( ! is_array( $file ) ) {
				$file = array( $file );
			}

			$first = current( $file );

			$dir = dirname( $first );

			if ( ! empty( $dir ) ) {
				@ftp_chdir( $conn, $dir );
			}

			$basename = basename( $first );

			if ( 0 > @ftp_size( $conn, $basename ) ) {
				return - 1;
			}

			if ( $passive ) {
				@ftp_pasv( $conn, true );
			}

			foreach ( $file as $a_file ) {
				$basename = basename( $a_file );

				$deleted = @ftp_delete( $conn, $basename );
			}

			@ftp_close( $conn );

			return $deleted;
		}

		return false;
	}

	public function ftp_upload( $file, $host, $user, $password, $port = 21, $directory = '', $passive = false ) {
		if ( ( is_array( $file ) && isset( $file['tmp_name'] ) ) || ( is_string( $file ) && file_exists( $file ) ) ) {
			$conn = $this->ftp_connect( $host, $user, $password, $port );

			if ( false !== $conn ) {
				if ( ! empty( $directory ) ) {
					$this->ftp_create_directory( $conn, $directory );
					@ftp_chdir( $conn, $directory );
				} else {
					@ftp_chdir( $conn, '~' );
				}

				$cur_dir = @ftp_pwd( $conn );

				if ( $cur_dir != $directory ) {
					@ftp_chdir( $conn, $cur_dir );
				}

				$new_name = ( is_array( $file ) && isset( $file['new_file'] ) ) ? basename( $file['new_file'] ) : '';

				if ( empty( $new_name ) ) {
					$new_name = isset( $file['name'] ) ? $file['name'] : '';
				}

				if ( empty( $new_name ) && is_string( $file ) ) {
					$new_name = basename( $file );
				}

				if ( empty( $new_name ) ) {
					$new_name = current_time( 'timestamp' ) . '.jpg';
				}

				if ( 1 == $this->get_option( 'current_time_name' ) ) {
					$info = pathinfo( $new_name );

					if ( isset( $info['extension'] ) && ! empty( $info['extension'] ) ) {
						$new_name = $info['filename'] . '-' . current_time( 'timestamp' ) . '.' . $info['extension'];
					}
				}

				$file = isset( $file['tmp_name'] ) ? $file['tmp_name'] : $file;

				if ( ! empty( $cur_dir ) ) {
					$new_name = trailingslashit( $cur_dir ) . $new_name;
				}

				if ( $passive ) {
					@ftp_pasv( $conn, true );
				}

				$uploaded = @ftp_put( $conn, $new_name, $file, FTP_BINARY );

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

	public function query_related_posts( $args = array() ) {
		$post_id = isset( $args['post_id'] ) ? $args['post_id'] : get_the_ID();

		$obj = get_post( $post_id );

		$defaults = array(
			'post__not_in'  => array( $post_id ),
			'post_type'     => $obj->post_type,
			'orderby'       => 'rand',
			'related_posts' => true
		);

		$post_parent = $obj->post_parent;

		if ( $this->is_positive_number( $post_parent ) ) {
			$defaults['post_parent'] = $post_parent;
		}

		$args = wp_parse_args( $args, $defaults );

		$tr_name = 'query_related_posts_' . md5( maybe_serialize( $args ) );

		unset( $args['post_id'] );

		if ( false === ( $query = get_transient( $tr_name ) ) ) {
			$query = new WP_Query();

			$taxonomy = isset( $args['taxonomy'] ) ? $args['taxonomy'] : '';
			unset( $args['taxonomy'] );

			if ( ! empty( $taxonomy ) ) {
				if ( ! is_array( $taxonomy ) ) {
					$taxonomy = array( $taxonomy );
				}

				$tax_query = isset( $args['tax_query'] ) ? $args['tax_query'] : '';

				if ( ! is_array( $tax_query ) ) {
					$tax_query = array();
				}

				foreach ( $taxonomy as $tax ) {
					$term_ids = wp_get_object_terms( get_the_ID(), $tax, array( 'fields' => 'ids' ) );

					if ( $this->array_has_value( $term_ids ) ) {
						$tax_query[] = array(
							'taxonomy' => $tax,
							'field'    => 'term_id',
							'terms'    => $term_ids
						);
					}
				}

				if ( ! isset( $tax_query['relation'] ) ) {
					$tax_query['relation'] = 'OR';
				}

				$args['tax_query'] = $tax_query;

				$query = new WP_Query( $args );
			}

			if ( $query->have_posts() ) {
				set_transient( $tr_name, $query, HOUR_IN_SECONDS );

				return $query;
			}

			$by_term = false;

			if ( isset( $args['cat'] ) && is_numeric( $args['cat'] ) ) {
				$by_term = true;
			} elseif ( isset( $args['category_name'] ) && ! empty( $args['category_name'] ) ) {
				$by_term = true;
			} elseif ( isset( $args['category__and'] ) && $this->array_has_value( $args['category__and'] ) ) {
				$by_term = true;
			} elseif ( isset( $args['category__in'] ) && $this->array_has_value( $args['category__in'] ) ) {
				$by_term = true;
			} elseif ( isset( $args['tag_id'] ) && is_numeric( $args['tag_id'] ) ) {
				$by_term = true;
			} elseif ( isset( $args['tag'] ) && ! empty( $args['tag'] ) ) {
				$by_term = true;
			} elseif ( isset( $args['tag__and'] ) && $this->array_has_value( $args['tag__and'] ) ) {
				$by_term = true;
			} elseif ( isset( $args['tag__in'] ) && $this->array_has_value( $args['tag__in'] ) ) {
				$by_term = true;
			} elseif ( isset( $args['tag_slug__and'] ) && $this->array_has_value( $args['tag_slug__and'] ) ) {
				$by_term = true;
			} elseif ( isset( $args['tag_slug__in'] ) && $this->array_has_value( $args['tag_slug__in'] ) ) {
				$by_term = true;
			} elseif ( isset( $args['tax_query'] ) && $this->array_has_value( $args['tax_query'] ) ) {
				$by_term = true;
			}

			if ( $by_term ) {
				$query = new WP_Query( $args );
			}

			if ( $query->have_posts() ) {
				set_transient( $tr_name, $query, HOUR_IN_SECONDS );

				return $query;
			}

			$term_relation = array();

			$taxs = get_object_taxonomies( $obj );

			if ( $this->array_has_value( $taxs ) ) {
				$tax_query = isset( $args['tax_query'] ) ? $args['tax_query'] : array();

				if ( ! is_array( $tax_query ) ) {
					$tax_query = array();
				}

				$new = array();

				$has_tag = false;

				$tax_item = array(
					'field'    => 'term_id',
					'operator' => 'IN'
				);

				foreach ( $taxs as $key => $tax ) {
					$taxonomy = get_taxonomy( $tax );

					if ( $taxonomy instanceof WP_Taxonomy ) {
						if ( ! $taxonomy->hierarchical ) {
							$ids = wp_get_post_terms( $post_id, $tax, array( 'fields' => 'ids' ) );

							if ( $this->array_has_value( $ids ) && is_string( $tax ) ) {
								$tax_item['taxonomy'] = $tax;

								$tax_item['terms'] = $ids;

								$new[] = $tax_item;

								$has_tag = true;

								$term_relation[ $tax ] = $ids;
							}

							unset( $taxs[ $key ] );
						}
					} else {
						unset( $taxs[ $key ] );
					}
				}

				if ( $has_tag ) {
					$new['relation'] = 'or';

					$tax_query[] = $new;
				}

				if ( $has_tag ) {
					$args['tax_query'] = $tax_query;

					$query = new WP_Query( $args );
				}

				$missing = false;

				if ( $query->have_posts() ) {
					$ppp = $query->get( 'posts_per_page' );

					if ( ! is_numeric( $ppp ) ) {
						$ppp = $this->get_posts_per_page();
					}

					if ( $query->found_posts < ( $ppp / 2 ) ) {
						$missing = true;
					}
				}

				if ( ! $query->have_posts() || $missing ) {
					foreach ( $taxs as $tax ) {
						$ids = wp_get_post_terms( $post_id, $tax, array( 'fields' => 'ids' ) );

						if ( $this->array_has_value( $ids ) && is_string( $tax ) ) {
							$tax_item['taxonomy'] = $tax;

							$tax_item['terms'] = $ids;

							$new[] = $tax_item;

							$term_relation[ $tax ] = $ids;
						}
					}

					if ( $this->array_has_value( $new ) ) {
						$new['relation'] = 'or';

						$tax_query = $new;
					}

					if ( ! isset( $tax_query['relation'] ) ) {
						$tax_query['relation'] = 'or';
					}

					$args['tax_query'] = $tax_query;

					$query = new WP_Query( $args );
				}
			} else {
				$args['s'] = $obj->post_title;
				$query     = new WP_Query( $args );

				if ( ! $query->have_posts() ) {
					$parts = explode( ' ', $obj->post_title );

					while ( ! $query->have_posts() && count( $parts ) > 0 ) {
						$key       = array_shift( $parts );
						$args['s'] = $key;
						$query     = new WP_Query( $args );
					}
				}
			}

			if ( ! isset( $query->query_vars['term_relation'] ) ) {
				$query->query_vars['term_relation'] = $term_relation;
			}

			if ( $query->have_posts() ) {
				set_transient( $tr_name, $query, HOUR_IN_SECONDS );
			}
		}

		return $query;
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

		if ( is_array( $columns ) ) {
			$columns = implode( ',', $columns );
		}

		if ( is_array( $values ) ) {
			foreach ( $values as $key => $value ) {
				if ( is_string( $value ) ) {
					$values[ $key ] = "'" . $value . "'";
				}
			}

			$values = implode( ',', $values );
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

	public function parse_options( $options, $args ) {
		if ( $this->array_has_value( $args ) && $this->array_has_value( $options ) ) {
			foreach ( $args as $option => $value ) {
				if ( empty( $value ) ) {
					unset( $args[ $option ] );
				}
			}

			$options = wp_parse_args( $args, $options );
		}

		return $options;
	}

	public function build_inline_css_form_option( $args = array(), $options = null ) {
		$css = '';

		if ( null == $options ) {
			$options = $this->get_options();
		}

		foreach ( $args as $key => $value ) {
			$option = $this->get_value_in_array( $options, $value );

			if ( ! empty( $option ) ) {
				$css .= $key . ':' . $option . ';';
			}
		}

		return rtrim( $css, ';' );
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
		if ( is_numeric( $id ) && file_exists( get_attached_file( $id ) ) ) {
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

	public function get_media_id_by_url( $url, $path = false ) {
		global $wpdb;

		if ( $path ) {
			$sql = 'SELECT post_id';
			$sql .= ' FROM ' . $wpdb->postmeta;
			$sql .= " WHERE meta_value LIKE '%%s'";
		} else {
			$sql = 'SELECT ID';
			$sql .= ' FROM ' . $wpdb->posts;
			$sql .= " WHERE guid='%s'";
		}

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

	public function download_image( $url, $name = '', $check_exists = false ) {
		if ( ! $url || empty ( $url ) ) {
			return false;
		}

		global $wpdb;

		$id = '';

		$args = array(
			'select' => 'post_id',
			'where'  => array(
				'meta_key'   => 'source_url',
				'meta_value' => $url
			)
		);

		$results = $this->mysql_select( $wpdb->postmeta, $args );

		$info = pathinfo( $url );

		if ( $this->array_has_value( $results ) ) {
			$first = $results[0];

			if ( isset( $first->post_id ) ) {
				$attachment = get_post( $first->post_id );

				if ( $attachment instanceof WP_Post && 'attachment' == $attachment->post_type ) {
					if ( $attachment->post_title == $info['filename'] ) {
						return $attachment->ID;
					}
				}
			}
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
			$info = pathinfo( $name );

			if ( ! isset( $info['extension'] ) || empty( $info['extension'] ) ) {
				$name .= '.jpeg';
			}

			$file_array['name'] = $name;
		} else {
			if ( $check_exists ) {
				$name = 'downloaded-';
				$name .= md5( $url );
				$name .= '.jpeg';

				if ( file_exists( $name ) ) {
					$id = $this->get_media_id_by_url( $name, true );
				}
			}

			if ( ! $this->is_media_file_exists( $id ) ) {
				preg_match( '/[^\?]+\.(jpe?g|jpe|gif|png)\b/i', $file_array['tmp_name'], $matches );

				if ( ! empty( $matches ) ) {
					$file_array['name'] = basename( $matches[0] );
				} else {
					$file_array['name'] = uniqid( 'downloaded-' ) . '.jpeg';
				}
			}
		}

		if ( ! $this->is_media_file_exists( $id ) ) {
			$id = media_handle_sideload( $file_array, 0 );
		}

		if ( is_wp_error( $id ) ) {
			@unlink( $file_array['tmp_name'] );

			return false;
		}

		$data = array(
			'ID'         => $id,
			'post_title' => $info['filename']
		);

		wp_update_post( $data );

		update_post_meta( $id, 'source_url', $url );

		return $id;
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

	public function fetch_thumbnail_image_meta( $url ) {
		$thumb = '';

		$remote = wp_remote_get( $url );
		$body   = wp_remote_retrieve_body( $remote );

		if ( ! empty( $body ) ) {
			$search = 'property="og:image"';

			$pos = strpos( $body, $search );

			if ( false === $pos ) {
				$search = 'name="twitter:image"';

				$pos = strpos( $body, $search );
			}

			if ( false === $pos ) {
				$search = 'name="og:image"';

				$pos = strpos( $body, $search );
			}

			if ( false === $pos ) {
				$search = 'article_image=';

				$pos = strpos( $body, $search );
			}

			if ( false !== $pos ) {
				$body = substr( $body, $pos + strlen( $search ) );

				$regex = '/https?\:\/\/[^\" ]+/i';

				preg_match( $regex, $body, $matches );

				if ( isset( $matches[0] ) ) {
					$tmp = $matches[0];

					if ( $this->is_image_url( $tmp ) ) {
						$thumb = $tmp;
					}
				}
			}
		}

		return $thumb;
	}

	public function get_first_paragraph( $string ) {
		$string = wpautop( $string );

		return substr( $string, strpos( $string, "<p" ), strpos( $string, "</p>" ) + 4 );
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

	public function set_post_term( $post_id, $term, $taxonomy ) {
		$post_type = get_post_type( $post_id );

		if ( 'post' == $post_type ) {
			$result = wp_set_post_terms( $post_id, $term, $taxonomy );
		} else {
			$result = wp_set_object_terms( $post_id, $term, $taxonomy );
		}

		return $result;
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

		if ( ! $this->string_contain( $table_name, $wpdb->prefix ) ) {
			$table_name = $wpdb->prefix . $table_name;
		}

		$result = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );

		if ( empty( $result ) ) {
			return false;
		}

		return true;
	}

	public function get_woocommerce_order_title( $order ) {
		$order = wc_get_order( $order );

		$buyer = '';

		if ( $order->get_billing_first_name() || $order->get_billing_last_name() ) {
			/* translators: 1: first name 2: last name */
			$buyer = trim( sprintf( '%1$s %2$s', $order->get_billing_first_name(), $order->get_billing_last_name() ) );
		} elseif ( $order->get_billing_company() ) {
			$buyer = trim( $order->get_billing_company() );
		} elseif ( $order->get_customer_id() ) {
			$user  = get_user_by( 'id', $order->get_customer_id() );
			$buyer = ucwords( $user->display_name );
		}

		/**
		 * Filter buyer name in list table orders.
		 *
		 * @since 3.7.0
		 *
		 * @param string $buyer Buyer name.
		 * @param WC_Order $order Order data.
		 */
		$buyer = apply_filters( 'woocommerce_admin_order_buyer_name', $buyer, $order );

		return '#' . esc_attr( $order->get_order_number() ) . ' ' . esc_html( $buyer );
	}
}