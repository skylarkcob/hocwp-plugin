<?php
global $hocwp_plugin;
$obj = $hocwp_plugin->auto_add_post;

if ( ! ( $obj instanceof HOCWP_Plugin_Core ) ) {
	return;
}

$option_name = $obj->get_option_name();

if ( empty( $option_name ) ) {
	return;
}

$base_url = $obj->get_options_page_url();
$tab      = isset( $_GET['tab'] ) ? $_GET['tab'] : 'general';

$tabs = apply_filters( 'plugin_' . $obj->get_option_name() . '_setting_tabs', array() );

if ( 0 < count( $tabs ) && ! array_key_exists( $tab, $tabs ) ) {
	reset( $tabs );
	$tab = key( $tabs );
}

$plugin = get_plugin_data( $obj->get_root_file_path() );

$headline = apply_filters( 'plugin_' . $option_name . '_setting_page_title', $plugin['Name'] );
?>
<div class="wrap">
	<h1><?php echo esc_html( $headline ); ?></h1>
	<hr class="wp-header-end">
	<?php
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
		?>
		<form method="post" action="options.php" novalidate="novalidate" autocomplete="off">
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