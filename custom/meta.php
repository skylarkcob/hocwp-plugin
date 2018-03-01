<?php
function hocwp_auto_approve_comment_meta_boxes( $post_type, $post ) {
	$args = array(
		'public' => true
	);

	$types = get_post_types( $args );

	add_meta_box(
		'auto-approve-comment',
		__( 'Auto Approve Comment', 'auto-approve-comment' ),
		'hocwp_auto_approve_comment_meta_box',
		$types,
		'normal',
		'default'
	);
}

add_action( 'add_meta_boxes', 'hocwp_auto_approve_comment_meta_boxes', 10, 2 );

function hocwp_auto_approve_comment_meta_box( $post ) {
	$interval = get_post_meta( $post->ID, 'aac_interval', true );
	$interval_max = get_post_meta( $post->ID, 'aac_interval_max', true );
	$reply    = get_post_meta( $post->ID, 'aac_reply', true );
	?>
	<p>
		<label for="aac-interval"
		       style="display: block"><?php _e( 'Time Interval:', 'auto-approve-comment' ); ?></label>
		<input name="aac_interval" id="aac-interval" value="<?php echo $interval; ?>"
		       type="number" min="1" step="1"> <?php _e( 'minutes', 'auto-approve-comment' ); ?>
	</p>
	<p>
		<label for="aac-interval-max"
		       style="display: block"><?php _e( 'Time Interval Max:', 'auto-approve-comment' ); ?></label>
		<input name="aac_interval_max" id="aac-interval-max" value="<?php echo $interval_max; ?>"
		       type="number" min="1" step="1"> <?php _e( 'minutes', 'auto-approve-comment' ); ?>
	</p>
	<p>
		<label for="aac-reply"><?php _e( 'Auto Reply:', 'hocwp-theme' ); ?></label>
		<textarea name="aac_reply" id="aac-reply" class="widefat"
		          rows="4"><?php echo $reply; ?></textarea>
	</p>
	<?php
}

function hocwp_auto_approve_comment_save_post( $post_id ) {
	if ( isset( $_POST['aac_interval'] ) ) {
		update_post_meta( $post_id, 'aac_interval', $_POST['aac_interval'] );
	}

	if ( isset( $_POST['aac_reply'] ) ) {
		update_post_meta( $post_id, 'aac_reply', $_POST['aac_reply'] );
	}
}

add_action( 'save_post', 'hocwp_auto_approve_comment_save_post' );