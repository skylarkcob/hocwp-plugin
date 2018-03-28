<?php
function hocwp_plugin_aac_admin_notices_action() {

}

add_action( 'admin_notices', 'hocwp_plugin_aac_admin_notices_action' );

function hocwp_plugin_aac_admin_init_action() {
	$tr_name = 'hocwp_aac_invalid_comment_date_updated';
	$updated = get_transient( $tr_name );

	if ( false === $updated ) {
		$date = '0000-00-00';

		global $wpdb;

		$sql = "SELECT * FROM $wpdb->comments ";
		$sql .= "WHERE comment_date LIKE '%$date%' OR comment_date LIKE '%0001%' ";
		$sql .= "OR comment_date_gmt LIKE '%$date%' OR comment_date_gmt LIKE '%0001%' ";
		$sql .= "AND comment_approved = 1";

		$results = $wpdb->get_results( $sql );

		if ( is_array( $results ) && 0 < count( $results ) ) {
			foreach ( $results as $comment ) {
				$comment_id = $comment->comment_ID;

				$year  = 2018;
				$month = rand( 1, 2 );
				$day   = rand( 1, 28 );

				$hour   = rand( 0, 23 );
				$minute = rand( 1, 59 );
				$second = rand( 1, 59 );

				$date = sprintf( "%s-%s-%s %s:%s:%s", $year, $month, $day, $hour, $minute, $second );
				$date = strtotime( $date );

				$current = current_time( 'timestamp' );
				$current = strtotime( '-1 day', $current );

				if ( $date > $current ) {
					$date = $current;
				}

				$gmt  = gmdate( 'Y-m-d H:i:s', $date );
				$date = gmdate( 'Y-m-d H:i:s', ( $date + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) );

				$data = array(
					'comment_ID'       => $comment_id,
					'comment_date'     => $date,
					'comment_date_gmt' => $gmt
				);

				wp_update_comment( $data );
				update_comment_meta( $comment_id, 'auto_update_date', 1 );
			}
		} else {
			set_transient( $tr_name, 1, DAY_IN_SECONDS );
		}
	}
}

add_action( 'admin_init', 'hocwp_plugin_aac_admin_init_action' );


function hocwp_plugin_aac_admin_enqueue_scripts() {
	global $hocwp_plugin;

	$plugin = $hocwp_plugin->auto_approve_comment;

	if ( $plugin instanceof HOCWP_Plugin_Auto_Approve_Comment ) {
		$url = $plugin->get_baseurl() . '/css/admin' . HP()->css_suffix();
		wp_enqueue_style( 'hocwp-plugin-aac-style', $url );

		$url = $plugin->get_baseurl() . '/js/admin' . HP()->js_suffix();
		wp_enqueue_script( 'hocwp-plugin-aac', $url, array( 'jquery' ), false, true );
		$l10n = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' )
		);
		wp_localize_script( 'hocwp-plugin-aac', 'hocwpAAC', $l10n );
	}
}

add_action( 'admin_enqueue_scripts', 'hocwp_plugin_aac_admin_enqueue_scripts' );

function hocwp_plugin_aac_generate_tag_row_ajax_callback() {
	global $hocwp_plugin;

	$plugin = $hocwp_plugin->auto_approve_comment;

	if ( $plugin instanceof HOCWP_Plugin_Auto_Approve_Comment ) {
		$basename = $plugin->get_option_name() . '[tags]';

		$index = isset( $_POST['index'] ) ? $_POST['index'] : '';
		$index = absint( $index );

		if ( 0 == $index ) {
			$tags = $plugin->get_option( 'tags' );

			if ( is_array( $tags ) && 0 < count( $tags ) ) {
				$keys  = array_keys( $tags );
				$index = max( $keys );
				$index ++;
			}
		}

		ob_start();
		?>
		<tr data-key="<?php echo $index; ?>">
			<td><input type="text" name="<?php echo $basename; ?>[<?php echo $index; ?>][tag]" class="regular-text"
			           value="">
			</td>
			<td><textarea name="<?php echo $basename; ?>[<?php echo $index; ?>][reply]" class="widefat"
			              rows="3"></textarea></td>
			<td><a href="javascript:" class="delete-row"><?php _e( 'Delete', 'auto-approve-comment' ); ?></a></td>
		</tr>
		<?php
		$data = array(
			'tag_row' => ob_get_clean(),
			'index'   => $index
		);
		wp_send_json_success( $data );
	}

	wp_send_json_error();
}

add_action( 'wp_ajax_hocwp_plugin_aac_generate_tag_row', 'hocwp_plugin_aac_generate_tag_row_ajax_callback' );

function hocwp_plugin_aac_update_tag_row_ajax_callback() {
	$data = isset( $_POST['data'] ) ? $_POST['data'] : '';
	$all  = isset( $_POST['all'] ) ? $_POST['all'] : '';

	global $hocwp_plugin;

	$plugin = $hocwp_plugin->auto_approve_comment;

	if ( $plugin instanceof HOCWP_Plugin_Auto_Approve_Comment ) {
		$options = $plugin->get_options();
		$tags    = isset( $options['tags'] ) ? $options['tags'] : '';

		if ( ! is_array( $tags ) ) {
			$tags = array();
		}

		$update = false;

		if ( 1 == $all && is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				$tags[ $key ]['tag']   = $value['tag'];
				$tags[ $key ]['reply'] = $value['reply'];
			}

			$options['tags'] = $tags;

			$update = true;
		} elseif ( is_array( $data ) && isset( $data['name'] ) ) {
			$index = filter_var( $data['name'], FILTER_SANITIZE_NUMBER_INT );

			if ( is_numeric( $index ) && 0 <= $index ) {
				$name = hocwp_plugin_aac_get_string_between( $data['name'], '[', ']', true );

				if ( ! empty( $name ) && isset( $data['value'] ) ) {
					$tags[ $index ][ $name ] = $data['value'];

					$options['tags'] = $tags;

					$update = true;
				}
			}
		}

		if ( $update ) {
			$plugin->update_option( $options );

			wp_send_json_success();
		}
	}

	wp_send_json_error();
}

add_action( 'wp_ajax_hocwp_plugin_aac_update_tag_row', 'hocwp_plugin_aac_update_tag_row_ajax_callback' );

function hocwp_plugin_aac_remove_tag_row_ajax_callback() {
	$index = isset( $_POST['index'] ) ? $_POST['index'] : '';

	if ( is_numeric( $index ) && 0 <= $index ) {
		global $hocwp_plugin;

		$plugin = $hocwp_plugin->auto_approve_comment;

		if ( $plugin instanceof HOCWP_Plugin_Auto_Approve_Comment ) {
			$options = $plugin->get_options();
			$tags    = isset( $options['tags'] ) ? $options['tags'] : '';

			if ( isset( $tags[ $index ] ) ) {
				unset( $tags[ $index ] );

				$options['tags'] = $tags;

				$plugin->update_option( $options );

				wp_send_json_success();
			}
		}
	}

	wp_send_json_error();
}

add_action( 'wp_ajax_hocwp_plugin_aac_remove_tag_row', 'hocwp_plugin_aac_remove_tag_row_ajax_callback' );