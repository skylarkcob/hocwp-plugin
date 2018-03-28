<?php
function hocwp_auto_approve_comment_update_has_approved( $post_id ) {
	$args = array(
		'status'  => 'approve',
		'number'  => 1,
		'post_id' => $post_id,
		'fields'  => 'ids'
	);

	$query    = new WP_Comment_Query( $args );
	$comments = $query->get_comments();

	if ( is_array( $comments ) && 0 < count( $comments ) ) {
		update_post_meta( $post_id, 'has_approved', 1 );
	} else {
		delete_post_meta( $post_id, 'has_approved' );
	}
}

function hocwp_auto_approve_comment_get_last_comment_approved( $post_id ) {
	$result = get_post_meta( $post_id, 'last_comment_approved', true );

	if ( ! is_numeric( $result ) || 1 > $result ) {
		$args = array(
			'post_id' => $post_id,
			'status'  => 'approve',
			'number'  => 1
		);

		$query    = new WP_Comment_Query( $args );
		$comments = $query->get_comments();

		if ( is_array( $comments ) && 0 < count( $comments ) ) {
			$comment = $comments[0];
			$result  = strtotime( $comment->comment_date );

			update_post_meta( $post_id, 'last_comment_approved', $result );
		}
	}

	return $result;
}

function hocwp_comment_approve_get_time_interval( $post_id, $comment_id = 0 ) {
	$interval = null;

	if ( 0 < $comment_id ) {
		$interval = get_comment_meta( $comment_id, 'aac_interval', true );
	}

	if ( ! is_numeric( $interval ) ) {
		$value = get_post_meta( $post_id, 'aac_interval', true );

		if ( ! is_array( $value ) ) {
			$options = get_option( 'hocwp_auto_approve_comment' );
			$value   = isset( $options['interval'] ) ? $options['interval'] : '';
		}

		$min = ( is_array( $value ) && isset( $value['min'] ) ) ? $value['min'] : '';
		$max = ( is_array( $value ) && isset( $value['max'] ) ) ? $value['max'] : '';

		if ( is_numeric( $min ) ) {
			$interval = $min;
		}

		if ( ! is_numeric( $interval ) ) {
			$interval = $max;
		}

		if ( is_numeric( $min ) && is_numeric( $max ) ) {
			$interval = rand( $min, $max );
		}

		if ( 0 < $comment_id && is_numeric( $interval ) ) {
			update_comment_meta( $comment_id, 'aac_interval', $interval );
		}
	}

	return absint( $interval );
}

function hocwp_auto_approve_comment_reply( $comment_id, $post_id ) {
	$tags = '';

	global $hocwp_plugin;

	$plugin = $hocwp_plugin->auto_approve_comment;

	if ( $plugin instanceof HOCWP_Plugin_Auto_Approve_Comment ) {
		$tags = $plugin->get_option( 'tags' );
	}

	$reply = '';

	$comment = get_comment( $comment_id );

	if ( is_array( $tags ) ) {
		foreach ( $tags as $data ) {
			if ( isset( $data['tag'] ) && isset( $data['reply'] ) && ! empty( $data['reply'] ) ) {
				$parts = explode( ',', $data['tag'] );

				array_map( 'trim', $parts );

				foreach ( $parts as $keyword ) {
					if ( strpos( $comment->comment_content, $keyword ) ) {
						$reply = $data['reply'];
						break;
					}
				}
			}
		}
	}

	if ( empty( $reply ) ) {
		if ( 0 < $post_id ) {
			$reply = get_post_meta( $post_id, 'aac_reply', true );
		}

		if ( empty( $reply ) ) {
			$options = get_option( 'hocwp_auto_approve_comment' );
			$reply   = isset( $options['reply'] ) ? $options['reply'] : '';
		}
	}

	if ( ! empty( $reply ) ) {
		$time = current_time( 'mysql' );

		$info = hocwp_aac_get_auto_author();

		$reply = str_replace( '@name@', $comment->comment_author, $reply );

		$data = array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => $info['name'],
			'comment_author_email' => $info['email'],
			'comment_author_url'   => $info['website'],
			'comment_content'      => $reply,
			'comment_type'         => '',
			'comment_parent'       => $comment_id,
			'user_id'              => $info['user_id'],
			'comment_date'         => $time,
			'comment_approved'     => 1
		);

		wp_insert_comment( $data );
	}
}

function hocwp_auto_approve_comment_then_reply( $comment_id, $post_id ) {
	$ok = wp_set_comment_status( $comment_id, 'approve' );

	if ( $ok ) {
		$date = current_time( 'mysql' );
		$gmt  = current_time( 'mysql', true );

		$data = array(
			'comment_ID'       => $comment_id,
			'comment_date'     => $date,
			'comment_date_gmt' => $gmt
		);

		wp_update_comment( $data );

		hocwp_auto_approve_comment_reply( $comment_id, $post_id );

		update_comment_meta( $comment_id, 'auto_approved', 1 );

		delete_comment_meta( $comment_id, 'auto_approve_timestamp' );
	}
}

function hocwp_aac_get_auto_author() {
	$options = get_option( 'hocwp_auto_approve_comment' );
	$user_id = isset( $options['auto_author_user_id'] ) ? $options['auto_author_user_id'] : '';
	$user_id = absint( $user_id );

	$user = get_user_by( 'id', $user_id );

	$name    = isset( $options['auto_author_name'] ) ? $options['auto_author_name'] : '';
	$email   = isset( $options['auto_author_email'] ) ? $options['auto_author_email'] : '';
	$website = isset( $options['auto_author_website'] ) ? $options['auto_author_website'] : '';

	if ( $user instanceof WP_User ) {
		if ( empty( $name ) ) {
			$name = $user->display_name;
		}

		if ( ! is_email( $email ) ) {
			$email = $user->user_email;
		}

		if ( empty( $website ) ) {
			$website = $user->user_url;
		}
	} else {
		if ( empty( $name ) ) {
			$name = get_bloginfo( 'name' );
		}

		if ( ! is_email( $email ) ) {
			//$email = get_bloginfo( 'admin_email' );
		}

		if ( empty( $website ) ) {
			//$website = home_url( '/' );
		}
	}

	$result = array(
		'name'    => $name,
		'email'   => $email,
		'website' => $website,
		'user_id' => $user_id
	);

	return $result;
}

function hocwp_plugin_aac_get_string_between( $string, $start, $end, $from_last = false ) {
	$string = ' ' . $string;

	if ( $from_last ) {
		$ini = strrpos( $string, $start );
	} else {
		$ini = strpos( $string, $start );
	}

	if ( $ini == 0 ) {
		return '';
	}

	$ini += strlen( $start );

	if ( $from_last ) {
		$len = strrpos( $string, $end, $ini ) - $ini;
	} else {
		$len = strpos( $string, $end, $ini ) - $ini;
	}

	return substr( $string, $ini, $len );
}