<?php
global $hocwp_plugin;
$obj = $hocwp_plugin->auto_approve_comment;

if ( ! ( $obj instanceof HOCWP_Plugin_Core ) && ! ( $obj instanceof HOCWP_Plugin_Auto_Approve_Comment ) ) {
	return;
}

$options = $obj->get_options();

$base_url = $obj->get_options_page_url();
$tab      = isset( $_GET['tab'] ) ? $_GET['tab'] : '';

$tabs = array(
	'general_settings' => __( 'General Settings', 'auto-approve-comment' ),
	'comment_tags'     => __( 'Comment Tags', 'auto-approve-comment' )
);

if ( ! array_key_exists( $tab, $tabs ) ) {
	reset( $tabs );
	$tab = key( $tabs );
}

$headline = __( 'Auto Approve Comment by HocWP Team', 'auto-approve-comment' );
?>
<div class="wrap">
	<h1><?php echo esc_html( $headline ); ?></h1>
	<hr class="wp-header-end">
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
	if ( empty( $tab ) || 'general_settings' == $tab ) {
		?>
		<form method="post" action="options.php" novalidate="novalidate" autocomplete="off">
			<?php settings_fields( $obj->get_option_name() ); ?>
			<table class="form-table">
				<?php do_settings_fields( $obj->get_option_name(), 'default' ); ?>
			</table>
			<?php
			do_settings_sections( $obj->get_option_name() );
			submit_button();
			?>
		</form>
		<?php
	} elseif ( 'comment_tags' == $tab ) {
		$basename = $obj->get_option_name() . '[tags]';
		?>
		<p><?php _e( 'Auto reply comment by tags.', 'auto-approve-comment' ); ?></p>
		<table class="form-table tags-panel">
			<tr>
				<th style="width: 25%"><?php _e( 'Tag', 'auto-approve-comment' ); ?></th>
				<th><?php _e( 'Reply', 'auto-approve-comment' ); ?></th>
				<th></th>
			</tr>
			<?php
			$tags = $obj->get_option( 'tags' );

			if ( is_array( $tags ) && 0 < count( $tags ) ) {
				foreach ( $tags as $key => $data ) {
					$tag   = isset( $data['tag'] ) ? $data['tag'] : '';
					$reply = isset( $data['reply'] ) ? $data['reply'] : '';
					?>
					<tr data-key="<?php echo $key; ?>">
						<td><input type="text" name="<?php echo $basename; ?>[<?php echo $key; ?>][tag]"
						           class="regular-text"
						           value="<?php echo $tag; ?>">
						</td>
						<td><textarea name="<?php echo $basename; ?>[<?php echo $key; ?>][reply]" class="widefat"
						              rows="3"><?php echo $reply; ?></textarea></td>
						<td><a href="javascript:"
						       class="delete-row"><?php _e( 'Delete', 'auto-approve-comment' ); ?></a></td>
					</tr>
					<?php
				}
			}
			?>
		</table>
		<hr>
		<button class="button default add-new-tag"><?php _e( 'Add', 'auto-approve-comment' ); ?></button>
		<button class="button button-primary update-tags"><?php _e( 'Update', 'auto-approve-comment' ); ?></button>
		<?php
	}
	?>
</div>