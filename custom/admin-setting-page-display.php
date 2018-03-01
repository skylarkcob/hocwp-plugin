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
	'general_settings' => __( 'General Settings', 'auto-approve-comment' )
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
	<form method="post" action="options.php" novalidate="novalidate" autocomplete="off">
		<?php
		settings_fields( $obj->get_option_name() );
		?>
		<table class="form-table">
			<?php
			do_settings_fields( $obj->get_option_name(), 'default' );
			do_settings_sections( $obj->get_option_name() );
			?>
		</table>
		<?php submit_button(); ?>
	</form>
</div>