<?php
/**
 * Plugin Name: Auto Add Poll
 * Plugin URI: http://hocwp.net/project/
 * Description: This plugin is created by HocWP Team.
 * Author: HocWP Team
 * Version: 1.0.2
 * Author URI: http://facebook.com/hocwpnet/
 * Text Domain: auto-add-poll
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

require_once dirname( __FILE__ ) . '/core.php';

class Auto_Add_Poll extends Auto_Add_Poll_Core {
	protected static $instance;

	protected $plugin_file = __FILE__;

	public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {
		parent::__construct();

		if ( self::$instance instanceof self ) {
			return;
		}

		add_action( 'init', array( $this, 'init_action' ) );

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'custom_admin_init_action' ) );
			add_action( 'wp_ajax_hocwp_auto_add_poll', array( $this, 'ajax_callback' ) );
			add_action( 'wp_ajax_nopriv_hocwp_auto_add_poll', array( $this, 'ajax_callback' ) );
			//add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
			//add_action( 'add_meta_boxes', array( $this, 'adding_custom_meta_boxes' ) );
			add_action( 'save_post', array( $this, 'save_post_action' ) );
		} else {
			add_filter( 'the_content', array( $this, 'the_content_filter' ), 1 );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

		add_shortcode( 'auto_add_poll', array( $this, 'shortcode_auto_add_poll' ) );
	}

	public function adding_custom_meta_boxes( $post_type ) {
		if ( 'post' == $post_type ) {
			add_meta_box(
				'post-poll-options',
				__( 'Poll Options', $this->textdomain ),
				array( $this, 'render_my_meta_box' ),
				'post',
				'normal',
				'default'
			);
		}
	}

	public function save_post_action( $post_id ) {
		$poll_options = isset( $_POST['poll_options'] ) ? $_POST['poll_options'] : '';

		update_post_meta( $post_id, 'poll_options', $poll_options );
	}

	public function render_my_meta_box( $post ) {
		$polls = $this->get_poll_options();

		if ( ! empty( $polls ) ) {
			$poll_options = get_post_meta( $post->ID, 'poll_options', true );
			?>
			<p>
				<label>
					<select id="selectPollOptions" name="poll_options[]" class="widefat" multiple>
						<?php
						foreach ( $polls as $poll ) {
							$key  = $poll;
							$text = $poll;

							$selected = false;

							if ( ! empty( $poll_options ) ) {
								if ( is_array( $poll_options ) && in_array( $key, $poll_options ) ) {
									$selected = true;
								} elseif ( $key == $poll_options ) {
									$selected = true;
								}
							}

							if ( $this->is_positive_number( $key ) ) {
								$text = get_the_title( $poll );
							}
							?>
							<option
								value="<?php echo $key; ?>"<?php selected( true, $selected ); ?>><?php echo $text; ?></option>
							<?php
						}
						?>
					</select>
				</label>
			</p>
			<?php
		} else {
			echo wpautop( __( 'There is no poll option.', $this->textdomain ) );
		}
	}

	public function ajax_callback() {
		$key = isset( $_POST['key'] ) ? $_POST['key'] : '';

		$post_id = isset( $_POST['post_id'] ) ? $_POST['post_id'] : '';

		if ( ! empty( $key ) && $this->is_positive_number( $post_id ) ) {
			$value = isset( $_POST['value'] ) ? $_POST['value'] : '';

			$value = absint( $value );

			$meta_key = 'chosen_count_' . $key;

			update_post_meta( $post_id, $meta_key, $value );

			/*
			if ( $this->is_positive_number( $key ) ) {
				$count = get_post_meta( $key, 'chosen_count', true );
				$count = absint( $count );
				$count += $value;
				update_post_meta( $key, 'chosen_count', $count );
			} else {
				$count = get_option( 'chosen_count_' . $key );
				$count = absint( $count );
				$count += $value;
				update_option( 'chosen_count_' . $key, $value );
			}
			*/
		}

		exit;
	}

	public function admin_scripts() {
		global $pagenow;

		if ( 'post.php' == $pagenow || 'post-new.php' == $pagenow ) {
			wp_enqueue_style( 'select2-style', $this->base_url . '/select2/css/select2.css' );
			wp_enqueue_script( 'select2', $this->base_url . '/select2/js/select2.js', array( 'jquery' ), false, true );

			wp_enqueue_script( 'hocwp-auto-add-poll', $this->base_url . '/admin.js', array(
				'jquery',
				'select2'
			), false, true );
		}
	}

	public function enqueue_scripts() {
		if ( is_single() ) {
			wp_enqueue_style( 'hocwp-auto-add-poll-style', $this->base_url . '/auto-add-poll.css' );
			wp_enqueue_script( 'hocwp-auto-add-poll', $this->base_url . '/auto-add-poll.js', array( 'jquery' ), false, true );

			$l10n = array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' )
			);

			wp_localize_script( 'hocwp-auto-add-poll', 'hocwpAutoPoll', $l10n );
		}
	}

	public function init_action() {
		if ( 1 == $this->get_option( 'poll_post_type' ) ) {
			$args = array(
				'labels'  => array(
					'name'          => __( 'Poll Options', $this->textdomain ),
					'singular_name' => __( 'Poll Option', $this->textdomain )
				),
				'public'  => false,
				'show_ui' => true
			);

			register_post_type( 'hocwp_poll_option', $args );
		}
	}

	public function custom_admin_init_action() {
		/*
		$args = array(
			'description' => __( 'Create custom post type using for poll options.', $this->textdomain ),
			'type'        => 'checkbox'
		);

		$this->add_settings_field( 'poll_post_type', __( 'Post Type', $this->textdomain ), array(
			$this,
			'admin_setting_field_input'
		), 'default', $args );

		$args = array(
			'description' => __( 'Each poll option in new line.', $this->textdomain )
		);

		if ( 1 != $this->get_option( 'poll_post_type' ) ) {
			$this->add_settings_field( 'poll_options', __( 'Poll Options', $this->textdomain ), array(
				$this,
				'admin_setting_field_textarea'
			), 'default', $args );
		}
		*/

		$args = array();

		$args['description'] = __( 'Poll box title.', $this->textdomain );

		$this->add_settings_field( 'poll_title', __( 'Poll Title', $this->textdomain ), array(
			$this,
			'admin_setting_field_input'
		), 'default', $args );

		$this->add_settings_field( 'poll_submit_button_text', __( 'Submit Button Text', $this->textdomain ) );

		/*
		$args['type'] = 'number';

		$args['description'] = __( 'Number of poll options to show on each post.', $this->textdomain );

		$this->add_settings_field( 'poll_number', __( 'Number', $this->textdomain ), null, 'default', $args );
		*/

		$args = array(
			'textarea_rows' => 4
		);

		$this->add_settings_field( 'result_bottom', __( 'Result Bottom', $this->textdomain ), array(
			$this,
			'admin_setting_field_editor'
		), 'default', $args );
	}

	public function get_poll_options( $post_id = null, $number = null ) {
		$poll_options = array(
			'a' => _x( 'Option A', 'poll', $this->textdomain ),
			'b' => _x( 'Option B', 'poll', $this->textdomain ),
			'c' => _x( 'Option C', 'poll', $this->textdomain ),
			'd' => _x( 'Option D', 'poll', $this->textdomain )
		);

		return $poll_options;
		/*
		if ( $this->is_positive_number( $post_id ) ) {
			$poll_options = get_post_meta( $post_id, 'poll_options', true );

			if ( ! empty( $poll_options ) ) {
				return $poll_options;
			}
		}

		if ( 1 == $this->get_option( 'poll_post_type' ) ) {
			$ppp = ( is_numeric( $number ) ) ? $number : - 1;

			$args = array(
				'post_type'      => 'hocwp_poll_option',
				'posts_per_page' => $ppp,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'orderby'        => 'name',
				'order'          => 'asc'
			);

			$query = new WP_Query( $args );

			if ( $query->have_posts() ) {
				return $query->posts;
			}
		}

		$polls = $this->get_option( 'poll_options' );

		if ( ! empty( $polls ) ) {
			$polls = explode( "\n", $polls );

			if ( is_numeric( $number ) ) {
				if ( $number < count( $polls ) ) {
					$polls = array_slice( $polls, 0, $number );
				}
			}
		}

		return $polls;
		*/
	}

	public function get_poll_submit_button_text() {
		$text = $this->get_option( 'poll_submit_button_text' );

		if ( empty( $text ) ) {
			$text = __( 'View result', $this->textdomain );
		}

		return $text;
	}

	public function get_poll_title() {
		$title = $this->get_option( 'poll_title' );

		if ( empty( $title ) ) {
			$title = __( 'Choose the best option', $this->textdomain );
		}

		return $title;
	}

	public function get_poll_number() {
		$number = $this->get_option( 'poll_number' );

		if ( ! is_numeric( $number ) ) {
			$number = 4;
		}

		return $number;
	}

	public function get_poll_chosen_count( $post_id, $poll, $key = '' ) {
		$meta_key = 'chosen_count_' . $poll;

		/*
		if ( empty( $key ) ) {
			$key = $poll;
		}

		if ( ! $this->is_positive_number( $key ) ) {
			$key   = md5( $poll );
			$count = get_option( 'chosen_count_' . $key );
			$meta_key .= $key;
		} else {
			$text  = get_the_title( $poll );
			$count = get_post_meta( $poll, 'chosen_count', true );
			$meta_key .= $poll;
		}
		*/

		$count = get_post_meta( $post_id, $meta_key, true );

		return absint( $count );
	}

	public function shortcode_auto_add_poll( $atts = array(), $content = null ) {
		$atts = shortcode_atts( array(
			'post_id' => get_the_ID(),
			'number'  => $this->get_poll_number()
		), $atts );

		$post_id = $atts['post_id'];
		$number  = $atts['number'];

		$polls = $this->get_poll_options( $post_id, $number );

		if ( empty( $polls ) ) {
			return '';
		}

		ob_start();
		?>
		<div class="hocwp-auto-poll">
			<div class="poll-inner">
				<h2 class="box-title"><?php echo $this->get_poll_title(); ?></h2>

				<div class="poll-body">
					<form method="post" action="" class="auto-poll-form">
						<fieldset class="poll-options">
							<?php
							foreach ( $polls as $key => $poll ) {
								$text = $poll;

								/*
								$key  = $poll;

								if ( ! $this->is_positive_number( $key ) ) {
									$key = md5( $poll );
								} else {
									$text = get_the_title( $poll );
								}
								*/
								?>
								<label>
									<input type="radio" name="hocwp_poll"
									       value="<?php echo $key; ?>" autocomplete="off"> <?php echo $text; ?>
								</label>
								<?php
							}
							?>
						</fieldset>
						<fieldset class="poll-result">
							<?php
							foreach ( $polls as $key => $poll ) {
								$text = $poll;

								/*
								$key  = $poll;

								if ( ! $this->is_positive_number( $key ) ) {
									$key   = md5( $poll );
									$count = get_option( 'chosen_count_' . $key );
								} else {
									$text  = get_the_title( $poll );
									$count = get_post_meta( $poll, 'chosen_count', true );
								}
								*/

								$count = $this->get_poll_chosen_count( $post_id, $key );
								?>
								<label>
									<span><?php echo $text; ?></span>
									<span
										class="chosen-count"><?php printf( __( 'has <span data-key="%s" data-count="%s" class="count">%s</span> person choose.', $this->textdomain ), $key, $count, number_format( $count ) ); ?></span>
								</label>
								<?php
							}
							?>
						</fieldset>
						<button type="submit" name="submit" data-post-id="<?php echo $post_id; ?>"
						        data-result-text="<?php _e( 'Result', $this->textdomain ); ?>"
						        data-confirm-text="<?php _e( 'Please choose option for viewing result', $this->textdomain ); ?>"
						        class="btn-submit"><?php echo $this->get_poll_submit_button_text(); ?></button>
						<?php
						$result_bottom = $this->get_option( 'result_bottom' );

						if ( ! empty( $result_bottom ) ) {
							?>
							<p class="more-links">
								<?php echo $result_bottom; ?>
							</p>
							<?php
						}
						?>
					</form>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public function the_content_filter( $post_content ) {
		$post_id = get_the_ID();

		if ( 'post' == get_post_type( $post_id ) && is_single( $post_id ) ) {
			$post_content .= do_shortcode( '[auto_add_poll post_id="' . $post_id . '"]' );
		}

		return $post_content;
	}
}

function Auto_Add_Poll() {
	return Auto_Add_Poll::get_instance();
}

add_action( 'plugins_loaded', function () {
	Auto_Add_Poll();
} );