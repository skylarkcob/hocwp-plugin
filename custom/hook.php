<?php
add_action( 'wp_insert_comment', 'hocwp_auto_approve_comment_insert_comment', 10, 2 );
function hocwp_auto_approve_comment_insert_comment( $comment_id, $comment ) {
	if ( 0 == $comment->comment_approved ) {
		$post_id = $comment->comment_post_ID;

		delete_post_meta( $post_id, 'comments_approved' );

		hocwp_auto_approve_comment_update_has_approved( $post_id );
	}
}

add_action( 'transition_comment_status', 'hocwp_auto_approve_comment_transition_comment_status', 10, 3 );
function hocwp_auto_approve_comment_transition_comment_status( $new_status, $old_status, $comment ) {
	$post_id = $comment->comment_post_ID;

	if ( 'approved' == $new_status ) {
		update_post_meta( $post_id, 'has_approved', 1 );
		update_post_meta( $post_id, 'last_comment_approved', strtotime( $comment->comment_date ) );

		$args = array(
			'status'  => 'hold',
			'number'  => 1,
			'post_id' => $post_id,
			'fields'  => 'ids'
		);

		$query    = new WP_Comment_Query( $args );
		$comments = $query->get_comments();

		if ( is_array( $comments ) && 0 < count( $comments ) ) {
			delete_post_meta( $post_id, 'comments_approved' );
		} else {
			update_post_meta( $post_id, 'comments_approved', 1 );
		}
	} elseif ( 'unapproved' == $new_status && 'approved' == $old_status ) {
		delete_post_meta( $post_id, 'comments_approved' );
	}

	if ( 'approved' == $old_status ) {
		hocwp_auto_approve_comment_update_has_approved( $post_id );
	}
}

//add_action( 'init', 'hocwp_auto_approve_comment_init_action' );
function hocwp_auto_approve_comment_init_action() {
	$args = array(
		'status'     => 'hold',
		'meta_query' => array(
			array(
				'key'     => 'auto_approve_timestamp',
				'type'    => 'numeric',
				'value'   => '0',
				'compare' => '>'
			),
			array(
				array(
					'key'     => 'auto_approved',
					'compare' => '!=',
					'value'   => 1
				),
				array(
					'key'     => 'auto_approved',
					'compare' => 'not exists'
				),
				'relation' => 'or'
			),
			'relation' => 'and'
		)
	);

	$query    = new WP_Comment_Query( $args );
	$comments = $query->get_comments();

	if ( is_array( $comments ) && 0 < count( $comments ) ) {
		foreach ( $comments as $comment ) {
			$comment_id = $comment->comment_ID;

			$current = current_time( 'timestamp' );
			$post_id = $comment->comment_post_ID;

			$time = get_comment_meta( $comment_id, 'auto_approve_timestamp', true );

			if ( $time <= $current ) {
				hocwp_auto_approve_comment_then_reply( $comment_id, $post_id );
			}
		}
	}
}

add_filter( 'cron_schedules', 'hocwp_auto_approve_comment_cron_schedules_filter' );
function hocwp_auto_approve_comment_cron_schedules_filter( $schedules ) {
	if ( ! isset( $schedules['minutely'] ) ) {
		$schedules['minutely'] = array(
			'interval' => MINUTE_IN_SECONDS,
			'display'  => __( 'Once Minutely', 'auto-approve-comment' )
		);
	}

	return $schedules;
}