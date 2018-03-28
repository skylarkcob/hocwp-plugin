<?php
/**
 * Plugin Name: Auto Approve Comment
 * Plugin URI: http://hocwp.net/project/
 * Description: This plugin is created by HocWP Team.
 * Author: HocWP Team
 * Version: 1.0.7
 * Author URI: http://hocwp.net/
 * Text Domain: auto-approve-comment
 * Domain Path: /languages/
 */

define( 'HOCWP_AAC_INTERVAL', 5 );

require_once dirname( __FILE__ ) . '/hocwp/class-hocwp-plugin.php';

class HOCWP_Plugin_Auto_Approve_Comment extends HOCWP_Plugin_Core {

	public function __construct( $file_path ) {
		global $pagenow;
		parent::__construct( $file_path );

		$labels = array(
			'action_link_text' => __( 'Settings', 'auto-approve-comment' ),
			'options_page'     => array(
				'page_title' => __( 'Auto Approve Comment by HocWP Team', 'auto-approve-comment' ),
				'menu_title' => __( 'Auto Approve Comment', 'auto-approve-comment' )
			)
		);

		$this->set_labels( $labels );
		$this->set_option_name( 'hocwp_auto_approve_comment' );
		$this->init();

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		}
	}

	public function admin_notices() {

	}

	public function admin_init() {
		$this->add_settings_field( 'interval', __( 'Time Interval', 'auto-approve-comment' ), array(
			$this,
			'time_interval'
		) );

		$this->add_settings_field( 'reply', __( 'Auto Reply', 'auto-approve-comment' ), array(
			$this,
			'auto_reply'
		) );

		$this->add_settings_section( 'auto_author', __( 'Auto Reply Author', 'auto-approve-comment' ), array(
			$this,
			'auto_author_section'
		) );

		$this->add_settings_field( 'auto_author_user_id', __( 'User', 'auto-approve-comment' ), array(
			$this,
			'auto_author_user_id'
		), 'auto_author' );

		$this->add_settings_field( 'auto_author_name', __( 'Name', 'auto-approve-comment' ), array(
			$this,
			'auto_author_name'
		), 'auto_author' );

		$args = array(
			'type' => 'email'
		);
		$this->add_settings_field( 'auto_author_email', __( 'Email', 'auto-approve-comment' ), array(
			$this,
			'admin_setting_field_input'
		), 'auto_author', $args );

		$args['type'] = 'url';
		$this->add_settings_field( 'auto_author_website', __( 'Website', 'auto-approve-comment' ), array(
			$this,
			'admin_setting_field_input'
		), 'auto_author', $args );
	}

	public function auto_author_section( $args ) {

	}

	public function auto_author_user_id( $args ) {
		$users = get_users();
		$value = $args['value'];
		?>
		<label for="<?php echo esc_attr( $args['label_for'] ); ?>"></label>
		<select id="<?php echo esc_attr( $args['label_for'] ); ?>"
		        name="<?php echo esc_attr( $args['name'] ); ?>">
			<option value=""><?php _e( '-- Choose user --', 'auto-approve-comment' ); ?></option>
			<?php
			foreach ( $users as $user ) {
				?>
				<option
					value="<?php echo $user->ID; ?>"<?php selected( $value, $user->ID ); ?>><?php echo $user->display_name; ?></option>
				<?php
			}
			?>
		</select>
		<?php
	}

	public function auto_author_name( $args ) {
		$this->admin_setting_field_input( $args );
	}

	public function time_interval( $args ) {
		$value = $args['value'];

		$min = ( is_array( $value ) && isset( $value['min'] ) ) ? $value['min'] : '';
		$max = ( is_array( $value ) && isset( $value['max'] ) ) ? $value['max'] : '';
		?>
		<label for="<?php echo $args['label_for']; ?>"></label>
		<input name="<?php echo $args['name']; ?>[min]" type="number" step="1" min="1"
		       id="<?php echo $args['label_for']; ?>"
		       value="<?php echo $min; ?>"
		       class="small-text"> -
		<input name="<?php echo $args['name']; ?>[max]" type="number" step="1" min="1"
		       id="<?php echo $args['label_for']; ?>"
		       value="<?php echo $max; ?>"
		       class="small-text"> <?php _e( 'seconds', 'auto-approve-comment' ); ?>
		<?php
	}

	public function auto_reply( $args ) {
		?>
		<label for="<?php echo $args['label_for']; ?>"></label>
		<textarea name="<?php echo $args['name']; ?>" id="<?php echo $args['label_for']; ?>" class="widefat"
		          rows="4"><?php echo $args['value']; ?></textarea>
		<?php
	}

	public function sanitize_callbacks( $input ) {
		return $input;
	}

	protected function notify_license_email_subject() {
		return __( 'Notify plugin license', 'auto-approve-comment' );
	}

	public function check_license_action() {
		$check = $this->check_license();

		if ( ! $check ) {
			$msg = __( 'Your plugin is blocked.', 'auto-approve-comment' );
			wp_die( $msg, __( 'Plugin Invalid License', 'auto-approve-comment' ) );
			exit;
		}
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'auto-approve-comment', false, basename( dirname( $this->file ) ) . '/languages/' );
	}
}

global $hocwp_plugin;

if ( ! is_object( $hocwp_plugin ) ) {
	$hocwp_plugin = new stdClass();
}

$hocwp_plugin->auto_approve_comment = new HOCWP_Plugin_Auto_Approve_Comment( __FILE__ );

$plugin = $hocwp_plugin->auto_approve_comment;

if ( $plugin instanceof HOCWP_Plugin_Auto_Approve_Comment ) {
	register_activation_hook( $plugin->get_root_file_path(), 'hocwp_auto_approve_comment_schedule_event' );
	function hocwp_auto_approve_comment_schedule_event() {
		if ( ! wp_next_scheduled( 'hocwp_auto_approve_comment_cron' ) ) {
			wp_schedule_event( time(), 'auto_approve_comment', 'hocwp_auto_approve_comment_cron' );
		}
	}

	add_action( 'hocwp_auto_approve_comment_cron', 'hocwp_auto_approve_comment_cron' );
	function hocwp_auto_approve_comment_cron() {
		global $wpdb;

		if ( ! isset( $wpdb->hocwpmeta ) ) {
			$table = $wpdb->prefix . 'hocwpmeta';

			$wpdb->hocwpmeta = $table;
		}

		$args = array(
			'post_type'      => 'any',
			'post_status'    => 'publish',
			'posts_per_page' => - 1,
			'meta_query'     => array(
				'relation' => 'and',
				array(
					'key'   => 'has_approved',
					'value' => 1,
					'type'  => 'numeric'
				),
				array(
					'relation' => 'or',
					array(
						'key'     => 'comments_approved',
						'value'   => 1,
						'type'    => 'numeric',
						'compare' => '!='
					),
					array(
						'key'     => 'comments_approved',
						'compare' => 'not exists'
					)
				)
			),
			'fields'         => 'ids'
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $obj_id ) {
				$args = array(
					'post_id' => $obj_id,
					'status'  => 'hold',
					'number'  => 1,
					'fields'  => 'ids'
				);

				$query    = new WP_Comment_Query( $args );
				$comments = $query->get_comments();

				if ( is_array( $comments ) && 0 < count( $comments ) ) {
					$comment_id = $comments[0];

					$interval = hocwp_comment_approve_get_time_interval( $obj_id, $comment_id );

					$count = HP()->get_meta( $obj_id, 'count_interval', true );
					$count = absint( $count );
					$count += absint( HOCWP_AAC_INTERVAL );

					if ( $count >= $interval ) {
						hocwp_auto_approve_comment_then_reply( $comment_id, $obj_id );
						HP()->delete_meta( $obj_id, 'count_interval' );
					} else {
						HP()->update_meta( $obj_id, 'count_interval', $count );
					}
				}
			}
		}
	}

	register_deactivation_hook( $plugin->get_root_file_path(), 'hocwp_auto_approve_comment_schedule_event_clean' );
	function hocwp_auto_approve_comment_schedule_event_clean() {
		wp_clear_scheduled_hook( 'hocwp_auto_approve_comment_cron' );
	}
}