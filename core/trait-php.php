<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

trait Share_Fonts_PHP {
	public $safe_string = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

	public function debug( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			error_log( print_r( $value, true ) );
		} else {
			error_log( $value );
		}
	}

	public function string_to_datetime( $string, $format = '' ) {
		if ( empty( $format ) ) {
			$format = 'Y-m-d H:i:s';
		}

		$string = str_replace( '/', '-', $string );
		$string = trim( $string );

		$totime = strtotime( $string );

		return date( $format, $totime );
	}

	public function js_datetime( $php_format ) {
		return $this->javascript_datetime_format( $php_format );
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

		$result = '';

		for ( $i = 0; $i < strlen( $php_format ); $i ++ ) {
			$char = $php_format[ $i ];

			if ( isset( $matched_symbols[ $char ] ) ) {
				$result .= $matched_symbols[ $char ];
			} else {
				$result .= $char;
			}
		}

		return $result;
	}

	public function size_in_bytes( $size ) {
		$result = floatval( trim( $size ) );

		$last = strtolower( substr( $size, - 1 ) );

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

	public function resize_resource_image( $image, $new_width, $new_height ) {
		$new_image = imagecreatetruecolor( $new_width, $new_height );

		imagealphablending( $new_image, false );
		imagesavealpha( $new_image, true );

		$transparent = imagecolorallocatealpha( $new_image, 255, 255, 255, 127 );

		imagefilledrectangle( $new_image, 0, 0, $new_width, $new_height, $transparent );

		$src_w = imagesx( $image );
		$src_h = imagesy( $image );

		imagecopyresampled( $new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $src_w, $src_h );

		return $new_image;
	}

	public function convert_to_boolean( $value ) {
		if ( is_numeric( $value ) ) {
			if ( 0 == $value ) {
				return false;
			}

			return true;
		}

		if ( is_string( $value ) ) {
			if ( 'false' == strtolower( $value ) ) {
				return false;
			}

			return true;
		}

		return (bool) $value;
	}

	public function insert_block( $content, $text, $element = '<h2>', $alt_element = '<h3>', $position = 1, $min = 5, $before = false ) {
		// split content
		$parts = explode( $element, $content );

		// count element occurrences
		$count = count( $parts );

		// check if the minimum required elements are found
		if ( ! empty( $alt_element ) && ( $count - 1 ) < $min && $element != $alt_element ) {
			$parts = explode( $alt_element, $content ); // check for the alternative tag
			$count = count( $parts );

			$element = $alt_element; // continue by using alternative tag instead of the primary one
		}

		if ( ( $count - 1 ) < $min ) {
			return $content;
		} // you can give up here and just return the original content

		$output = '';

		foreach ( $parts as $index => $part ) {
			if ( $index + 1 == $position ) {
				if ( $before ) {
					$output .= $part . $text . $element;
				} else {
					$output .= $part . $element . $text;
				}
			} else {
				$output .= $part . $element;
			}
		}

		return $output;
	}

	public function array_to_csv( $data ) {
		# Generate CSV data from array
		$fh = fopen( 'php://temp', 'rw' ); # don't create a file, attempt
		# to use memory instead

		# write out the headers
		fputcsv( $fh, array_keys( current( $data ) ) );

		# write out the data
		foreach ( $data as $row ) {
			fputcsv( $fh, $row );
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );

		return $csv;
	}

	/**
	 * Notation to numbers.
	 *
	 * This function transforms the php.ini notation for numbers (like '2M') to an integer.
	 *
	 * @param string $size Size value.
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

	public function get_max_upload_size() {
		$max_upload   = (int) ( ini_get( 'upload_max_filesize' ) );
		$max_post     = (int) ( ini_get( 'post_max_size' ) );
		$memory_limit = (int) ( ini_get( 'memory_limit' ) );

		return min( $max_upload, $max_post, $memory_limit );
	}

	public function get_upload_max_file_size( $to_byte = false ) {
		$size = $this->get_max_upload_size();

		if ( $to_byte ) {
			$size = $this->size_in_bytes( $size );
		}

		return $size;
	}

	public function strip_tags_content( $text, $allowed_tags = array(), $allowed_attrs = array() ) {
		if ( ! is_string( $text ) || ! strlen( $text ) ) {
			return $text;
		}

		$xml = new DOMDocument();

		libxml_use_internal_errors( true );

		if ( $xml->loadHTML( $text, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD ) ) {
			foreach ( $xml->getElementsByTagName( "*" ) as $tag ) {
				if ( ! in_array( $tag->tagName, $allowed_tags ) ) {
					$tag->parentNode->removeChild( $tag );
				} else {
					foreach ( $tag->attributes as $attr ) {
						if ( ! in_array( $attr->nodeName, $allowed_attrs ) ) {
							$tag->removeAttribute( $attr->nodeName );
						}
					}
				}
			}
		}

		return $xml->saveHTML();
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

	public function ftp_connect( $host, $user, $password, $port = 21 ) {
		$conn_id = @ftp_connect( $host, $port );

		$result = false;

		if ( $conn_id ) {
			$result = @ftp_login( $conn_id, $user, $password );

			if ( $result ) {
				return $conn_id;
			}
		}

		if ( ! $result ) {
			$conn_id = @ftp_ssl_connect( $host, $port );

			if ( $conn_id ) {
				$result = @ftp_login( $conn_id, $user, $password );

				if ( $result ) {
					return $conn_id;
				}
			}
		}

		return false;
	}

	/**
	 * Appends a trailing slash.
	 *
	 * Will remove trailing forward and backslashes if it exists already before adding
	 * a trailing forward slash. This prevents double slashing a string or path.
	 *
	 * The primary use of this is for paths and thus should be used for paths. It is
	 * not restricted to paths and offers no specific path support.
	 *
	 * @param string $string What to add the trailing slash to.
	 *
	 * @return string String with trailing slash added.
	 * @since 1.0.3
	 *
	 */
	public function trailingslashit( $string ) {
		return untrailingslashit( $string ) . '/';
	}

	/**
	 * Removes trailing forward slashes and backslashes if they exist.
	 *
	 * The primary use of this is for paths and thus should be used for paths. It is
	 * not restricted to paths and offers no specific path support.
	 *
	 * @param string $string What to remove the trailing slashes from.
	 *
	 * @return string String without the trailing slashes.
	 * @since 1.0.3
	 *
	 */
	public function untrailingslashit( $string ) {
		return rtrim( $string, '/\\' );
	}

	public function replace_char_with( $search, $replace, $string, $splitter = null, $map_callback = null ) {
		if ( $splitter ) {
			$string = explode( $splitter, $string );
			$first  = current( $string );

			if ( 'http:' == $first || 'https:' == $first ) {
				$first     .= '/';
				$string[0] = $first;
			}
		}

		if ( is_array( $string ) ) {
			foreach ( $string as $key => $char ) {
				$string[ $key ] = $this->replace_char_with( $search, $replace, $char, null, $map_callback );
			}

			$string = array_filter( $string );

			foreach ( $string as $key => $char ) {
				$string[ $key ] = ltrim( $char, $replace );
				$string[ $key ] = rtrim( $char, $replace );
			}

			$string = join( $splitter, $string );
		}

		$string = explode( $search, $string );
		$string = array_map( 'trim', $string );

		if ( is_callable( $map_callback ) ) {
			$string = array_map( $map_callback, $string );
		}

		$string = array_filter( $string );

		foreach ( $string as $key => $char ) {
			$string[ $key ] = ltrim( $char, $replace );
			$string[ $key ] = rtrim( $char, $replace );
		}

		return join( $replace, $string );
	}

	public function get_url_from_string( $string, $domain = '' ) {
		preg_match_all( '#\bhttps?://[^,\s()<>]+(?:\([\w\d]+\)|([^,[:punct:]\s]|/))#', $string, $matches );

		$urls = $matches[0];

		if ( ! empty( $domain ) ) {
			$dom = new DOMDocument( '1.0', 'UTF-8' );

			@$dom->loadHTML( '<?xml version="1.0" encoding="UTF-8"?>' . $string );

			$links = $dom->getElementsByTagName( 'a' );

			$abs = array();

			foreach ( $links as $link ) {
				$href = $link->getAttribute( 'href' );

				if ( false === strpos( $href, 'http' ) ) {
					$href  = ltrim( $href, '/' );
					$href  = $this->trailingslashit( $domain ) . $href;
					$abs[] = $href;
				}
			}

			$urls = array_merge( $urls, $abs );
		}

		return $urls;
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

	public function is_session_started() {
		if ( php_sapi_name() !== 'cli' ) {
			if ( version_compare( phpversion(), '5.4.0', '>=' ) ) {
				return session_status() === PHP_SESSION_ACTIVE;
			} else {
				return session_id() === '' ? false : true;
			}
		}

		return false;
	}

	public function session_start() {
		if ( $this->is_session_started() === false ) {
			session_start();
		}
	}

	public function is_empty_string( $string ) {
		return ( is_string( $string ) && empty( $string ) );
	}

	public function is_string_empty( $string ) {
		return $this->is_empty_string( $string );
	}

	public static function array_has_value( $arr, $count = 1 ) {
		return ( is_array( $arr ) && count( $arr ) > ( $count - 1 ) );
	}

	public function array_has_val( $arr, $count = 1 ) {
		return $this->array_has_value( $arr, $count );
	}

	public function get_value_in_array( $arr, $key, $default = '' ) {
		if ( is_array( $arr ) && isset( $arr[ $key ] ) ) {
			return $arr[ $key ];
		}

		return $default;
	}

	public function in_array( $needle, $haystack ) {
		return ( is_array( $haystack ) && in_array( $needle, $haystack ) );
	}

	public function is_positive_number( $number, $min = 0 ) {
		return ( is_numeric( $number ) && $number > $min );
	}

	public function random_string( $length = 10, $keyspace = '' ) {
		if ( empty( $keyspace ) ) {
			$keyspace = $this->safe_string;
		}

		$pieces = array();

		$max = mb_strlen( $keyspace, '8bit' ) - 1;

		for ( $i = 0; $i < $length; ++ $i ) {
			try {
				$index = random_int( 0, $max );
			} catch ( Exception $e ) {
				$index = 1;
			}

			$pieces[] = $keyspace[ $index ];
		}

		return implode( '', $pieces );
	}

	public function is_image_url( $url ) {
		$img_formats = array( 'png', 'jpg', 'jpeg', 'gif', 'tiff', 'bmp', 'ico', 'webp', 'svg' );

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
		$doc = new DOMDocument( '1.0', 'UTF-8' );
		@$doc->loadHTML( '<?xml version="1.0" encoding="UTF-8"?>' . $content );
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
			$tmp = array_filter( $tmp );

			$count = count( $tmp );

			while ( $count > 2 ) {
				if ( 3 == $count ) {
					$ltd = $tmp[1];

					$skip = array(
						'com',
						'net',
						'org',
						'info',
						'co',
						'blog',
						'dev'
					);

					if ( in_array( $ltd, $skip ) ) {
						break;
					}
				}

				array_shift( $tmp );
				$count = count( $tmp );
			}

			$result = implode( '.', $tmp );
		}

		if ( $root ) {
			$result = str_replace( 'www.', '', $result );
			$result = str_replace( 'https://', '', $result );
			$result = str_replace( 'http://', '', $result );
		}

		return $result;
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
                    if ("undefined" != typeof toastr) {
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

	public function field_description( $args = array() ) {
		if ( isset( $args['description'] ) && ! empty( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', $args['description'] );
		}
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
}