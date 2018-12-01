<?php
/**
 * Plugin Name: WP Custom Coupons
 * Plugin URI: http://hocwp.net/project/
 * Description: This plugin is created by HocWP Team.
 * Author: HocWP Team
 * Version: 1.0.2
 * Author URI: http://facebook.com/hocwpnet/
 * Text Domain: wp-custom-coupons
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! function_exists( 'deactivate_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

deactivate_plugins( 'auto-coupon/main.php' );

require_once dirname( __FILE__ ) . '/phpoffice/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

require_once dirname( __FILE__ ) . '/core.php';

class WP_Custom_Coupons extends WP_Custom_Coupons_Core {
	protected static $instance;

	protected $plugin_file = __FILE__;

	public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public $theme_stylesheet = 'wp-coupon';
	public $data_post_type = 'wp_coupon_data';
	public $data_description_post_type = 'wp_data_desc';
	public $combination_taxonomy = 'wp_column_combination';
	public $combination_description_taxonomy = 'wp_combination_desc';
	public $coupon_store_taxonomy = 'coupon_store';
	public $coupon_post_type = 'coupon';
	public $coupon_verified_meta_key = 'coupon_verified';

	public function __construct() {
		$this->sub_menu  = false;
		$this->menu_icon = 'dashicons-update';

		$this->labels['options_page']['menu_title'] = __( 'Custom Coupons', $this->textdomain );

		add_filter( 'hocwp_plugin_console_log_created_by', '__return_false' );

		parent::__construct();

		if ( self::$instance instanceof self ) {
			return;
		}

		$theme = wp_get_theme();

		$stylesheet  = $theme->get_stylesheet();
		$text_domain = $theme->get( 'TextDomain' );

		if ( $text_domain != $this->theme_stylesheet && $stylesheet != $this->theme_stylesheet ) {
			add_action( 'admin_notices', array( $this, 'admin_notices_action' ) );

			return;
		}

		add_filter( 'pre_get_document_title', array( $this, 'pre_get_document_title_filter' ), 99 );

		add_filter( 'wpseo_opengraph_desc', array( $this, 'wpseo_opengraph_desc_filter' ) );
		add_filter( 'wpseo_opengraph_title', array( $this, 'wpseo_opengraph_title_filter' ) );
		add_filter( 'wpseo_twitter_description', array( $this, 'wpseo_twitter_description_filter' ) );
		add_filter( 'wpseo_twitter_title', array( $this, 'wpseo_twitter_title_filter' ) );

		add_action( 'init', array( $this, 'init_action' ) );

		add_filter( 'mime_types', array( $this, 'mime_types_filter' ) );

		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'custom_admin_menu_action' ), 99 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts_action' ) );

			add_action( 'wp_ajax_wp_auto_import_coupon_and_store', array(
				$this,
				'import_coupon_and_store_ajax_callback'
			) );

			add_action( 'add_meta_boxes', array( $this, 'adding_custom_meta_boxes' ) );
			add_action( 'save_post', array( $this, 'save_post_action' ) );
			add_action( 'admin_init', array( $this, 'custom_admin_init_action' ) );

			// Add fields to create or edit store form.
			add_action( $this->coupon_store_taxonomy . '_add_form_fields', array(
				$this,
				'category_add_form_fields_action'
			) );

			add_action( $this->coupon_store_taxonomy . '_edit_form_fields', array(
				$this,
				'category_add_form_fields_action'
			) );

			// Action hook when create or edit Coupon Store
			add_action( 'edited_' . $this->coupon_store_taxonomy, array( $this, 'create_category_action' ) );
			add_action( 'create_' . $this->coupon_store_taxonomy, array( $this, 'create_category_action' ) );

			add_action( 'post_submitbox_misc_actions', array( $this, 'post_submitbox_misc_actions_action' ) );
			add_filter( 'bulk_actions-edit-' . $this->coupon_post_type, array( $this, 'bulk_actions_filter' ) );

			add_filter( 'handle_bulk_actions-edit-' . $this->coupon_post_type, array(
				$this,
				'handle_bulk_actions_action'
			), 10, 3 );

			add_filter( 'bulk_actions-edit-' . $this->coupon_store_taxonomy, array(
				$this,
				'bulk_actions_edit_store_filter'
			) );

			add_filter( 'handle_bulk_actions-edit-' . $this->coupon_store_taxonomy, array(
				$this,
				'handle_bulk_actions_store_taxonomy'
			), 10, 3 );

			add_action( 'admin_notices', array( $this, 'custom_admin_notices_messages' ) );

			add_action( 'restrict_manage_posts', array( $this, 'restrict_manage_posts_action' ), 10, 2 );
		} else {
			add_action( 'template_redirect', array( $this, 'template_redirect_action' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts_action' ) );
			add_action( 'wp', array( $this, 'wp_action' ) );
			add_filter( 'wpseo_metadesc', array( $this, 'wpseo_metadesc_filter' ) );
			//add_action( 'dynamic_sidebar_before', array( $this, 'dynamic_sidebar_before_action' ) );
		}

		if ( ! is_admin() || wp_doing_ajax() ) {
			add_filter( 'post_class', array( $this, 'post_class_filter' ) );
			add_action( 'pre_get_posts', array( $this, 'pre_get_posts_action' ), 99 );
			add_action( 'get_template_part_loop/loop-coupon', array( $this, 'get_template_part_action' ) );
			add_action( 'get_template_part_loop/loop', array( $this, 'get_template_part_action' ) );
		}

		add_action( 'wp_custom_coupons_cron_run', array( $this, 'run_cron' ) );

		require_once $this->base_dir . '/class-widget-store-image.php';

		add_action( 'widgets_init', array( $this, 'widgets_init_action' ) );
	}

	public function  widgets_init_action() {
		register_widget( 'Widget_Store_Image' );
	}

	public function single_store_thumbnail_widget() {
		?>
		<div class="widget-content shadow-box">
			<div class="header-thumb">
				<div class="header-store-thumb">
					<a rel="nofollow" target="_blank"
					   title="<?php esc_html_e( 'Shop ', WP_Custom_Coupons()->textdomain );
					   echo wpcoupon_store()->get_display_name(); ?>"
					   href="<?php echo wpcoupon_store()->get_go_store_url(); ?>">
						<?php
						echo wpcoupon_store()->get_thumbnail();
						?>
					</a>
				</div>
				<a class="add-favorite" data-id="<?php echo wpcoupon_store()->term_id; ?>" href="#"><i
						class="empty heart icon"></i><span><?php esc_html_e( 'Favorite This Store', WP_Custom_Coupons()->textdomain ); ?></span></a>
			</div>
		</div>
		<?php
	}

	public function dynamic_sidebar_before_action( $sidebar ) {
		if ( is_tax( $this->coupon_store_taxonomy ) && 'sidebar-store' == $sidebar ) {
			?>
			<div class="widget single-store-header">
				<?php $this->single_store_thumbnail_widget(); ?>
			</div>
			<?php
		}
	}

	public function bulk_actions_edit_store_filter( $actions ) {
		$actions['generate_coupon'] = __( 'Generate Coupons', $this->textdomain );
		$actions['export']          = __( 'Export', $this->textdomain );

		return $actions;
	}

	public function handle_bulk_actions_store_taxonomy( $redirect, $action, $ids ) {
		if ( 'generate_coupon' == $action ) {
			if ( $this->array_has_value( $ids ) ) {
				foreach ( $ids as $store_id ) {
					$this->generate_coupons_for_store( $store_id, false );
				}

				if ( isset( $_REQUEST['_wp_http_referer'] ) ) {
					$redirect = add_query_arg( 'coupon_generated', count( $ids ), $_REQUEST['_wp_http_referer'] );
				}
			}
		} elseif ( 'export' == $action ) {
			if ( $this->array_has_value( $ids ) ) {
				$dir = wp_upload_dir();

				$content_dir = dirname( $dir['basedir'] );
				$content_url = dirname( $dir['baseurl'] );

				$content_dir = trailingslashit( $content_dir );
				$content_dir .= 'exports/stores';

				if ( ! is_dir( $content_dir ) ) {
					mkdir( $content_dir, 0777, true );
				}

				if ( ! is_dir( $content_dir ) ) {
					$redirect = add_query_arg( array(
						'action'     => 'export',
						'error_code' => 'cannot_create_dir'
					), $redirect );

					return $redirect;
				}

				$content_url = trailingslashit( $content_url );
				$content_url .= 'exports/stores';

				$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
				$spreadsheet->setActiveSheetIndex( 0 );

				$row = 1;

				foreach ( $ids as $store_id ) {
					$store = get_term( $store_id, $this->coupon_store_taxonomy );

					if ( $store instanceof WP_Term ) {
						$spreadsheet->getActiveSheet()->setCellValue( 'A' . $row, $store->name );
						$spreadsheet->getActiveSheet()->setCellValue( 'B' . $row, get_term_meta( $store_id, '_wpc_store_url', true ) );
						$spreadsheet->getActiveSheet()->setCellValue( 'C' . $row, get_term_meta( $store_id, '_wpc_store_aff_url', true ) );
						$row ++;
					}
				}

				$timestamp = current_time( 'timestamp' );

				$name = '/' . $this->coupon_store_taxonomy . '_' . date( 'Ymd_His', $timestamp ) . '.xlsx';

				$file_dir = $content_dir;
				$file_dir .= $name;

				$file_url = $content_url;
				$file_url .= $name;

				$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $spreadsheet );
				$writer->save( $file_dir );
				$spreadsheet->disconnectWorksheets();
				unset( $spreadsheet );

				$redirect = add_query_arg( array(
					'action'   => 'export',
					'file_url' => $file_url
				), $redirect );
			}
		}

		return $redirect;
	}

	public function run_cron() {
		if ( 1 == $this->get_option( 'auto_generate_coupon' ) ) {
			$args = array(
				'taxonomy'   => $this->coupon_store_taxonomy,
				'hide_empty' => false
			);

			$terms = $this->get_terms( $args );

			if ( $this->array_has_value( $terms ) ) {
				$store_number = $this->get_option( 'store_number_cron' );

				if ( ! $this->is_positive_number( $store_number ) ) {
					$store_number = 1;
				}

				$old = '';

				while ( $store_number > 0 ) {
					$term = $this->get_random_value( $terms, $terms );

					if ( $old instanceof WP_Term && $term instanceof WP_Term && $old->term_id == $term->term_id ) {
						continue;
					}

					if ( $term instanceof WP_Term ) {
						$number = $this->get_option( 'coupon_number_cron' );

						$old = $term;

						$this->generate_coupons_for_store( $term->term_id, false, $number );
					}

					$store_number --;
				}
			}
		}
	}

	public function wpseo_metadesc_filter( $desc ) {
		if ( empty( $desc ) ) {

		}

		return $desc;
	}

	public function custom_admin_notices_messages() {
		global $pagenow, $post_type;

		if ( 'edit.php' == $pagenow && $this->coupon_post_type == $post_type ) {
			global $post_type;

			if ( isset( $_REQUEST[ $this->coupon_verified_meta_key ] ) ) {
				$count = absint( $_REQUEST[ $this->coupon_verified_meta_key ] );

				if ( $this->is_positive_number( $count ) ) {
					?>
					<div class="updated settings-error notice is-dismissible">
						<p><?php printf( __( '%s coupons have been verified successfully!', $this->textdomain ), number_format( $count ) ); ?></p>
					</div>
					<script>window.history.pushState(null, null, window.location.pathname + "?post_type=<?php echo $post_type; ?>");</script>
					<?php
				}
			} elseif ( isset( $_REQUEST['coupon_unverified'] ) ) {
				$count = absint( $_REQUEST['coupon_unverified'] );

				if ( $this->is_positive_number( $count ) ) {
					?>
					<div class="updated settings-error notice is-dismissible">
						<p><?php printf( __( '%s coupons have been unverified successfully!', $this->textdomain ), number_format( $count ) ); ?></p>
					</div>
					<script>window.history.pushState(null, null, window.location.pathname + "?post_type=<?php echo $post_type; ?>");</script>
					<?php
				}
			} elseif ( isset( $_REQUEST['set_aff_url'] ) ) {
				$count = absint( $_REQUEST['set_aff_url'] );

				if ( $this->is_positive_number( $count ) ) {
					?>
					<div class="updated settings-error notice is-dismissible">
						<p><?php printf( __( '%s coupons have been set affiliate url successfully!', $this->textdomain ), number_format( $count ) ); ?></p>
					</div>
					<script>window.history.pushState(null, null, window.location.pathname + "?post_type=<?php echo $post_type; ?>");</script>
					<?php
				}
			}
		} elseif ( 'edit-tags.php' == $pagenow ) {
			global $taxonomy, $post_type;

			if ( isset( $_REQUEST['coupon_generated'] ) ) {
				$count = absint( $_REQUEST['coupon_generated'] );

				if ( $this->is_positive_number( $count ) ) {
					?>
					<div class="updated settings-error notice is-dismissible">
						<p><?php printf( __( 'Coupons have been generated for %s Stores successfully!', $this->textdomain ), number_format( $count ) ); ?></p>
					</div>
					<script>window.history.pushState(null, null, window.location.pathname + "?taxonomy=<?php echo $taxonomy; ?>&post_type=<?php echo $post_type; ?>");</script>
					<?php
				}
			} elseif ( isset( $_REQUEST['action'] ) && 'export' == $_REQUEST['action'] ) {
				if ( isset( $_REQUEST['error_code'] ) && 'cannot_create_dir' == $_REQUEST['error_code'] ) {
					?>
					<div class="updated settings-error error notice-error notice is-dismissible">
						<p><?php _e( '<strong>Error:</strong> Cannot create directory for exporting data, please check your wp-content chmod permissions.', $this->textdomain ); ?></p>
					</div>
					<script>window.history.pushState(null, null, window.location.pathname + "?taxonomy=<?php echo $taxonomy; ?>&post_type=<?php echo $post_type; ?>");</script>
					<?php
				} elseif ( isset( $_REQUEST['file_url'] ) && ! empty( $_REQUEST['file_url'] ) ) {
					$url       = $_REQUEST['file_url'];
					$file_name = basename( $url );
					?>
					<div class="updated settings-error notice is-dismissible">
						<p><?php printf( __( '<strong>Data Exported:</strong> Store data has been exported successfully. You can see <a href="%s">%s</a> in <code>wp-content/exports/stores</code> folder.', $this->textdomain ), $url, $file_name ); ?></p>
					</div>
					<script>window.history.pushState(null, null, window.location.pathname + "?taxonomy=<?php echo $taxonomy; ?>&post_type=<?php echo $post_type; ?>");</script>
					<?php
				}
			}
		}
	}

	public function handle_bulk_actions_action( $redirect, $action, $ids ) {
		if ( 'mark_verified' == $action ) {
			if ( ! $this->array_has_value( $ids ) ) {
				$redirect = add_query_arg( 'empty_verified', 1, $redirect );
			} else {
				foreach ( $ids as $post_id ) {
					delete_post_meta( $post_id, $this->coupon_verified_meta_key );
				}

				$redirect = add_query_arg( $this->coupon_verified_meta_key, count( $ids ), $redirect );
			}
		} elseif ( 'mark_unverified' == $action && $this->array_has_value( $ids ) ) {
			foreach ( $ids as $post_id ) {
				update_post_meta( $post_id, $this->coupon_verified_meta_key, 0 );
			}

			$redirect = add_query_arg( 'coupon_unverified', count( $ids ), $redirect );
		} elseif ( 'set_url' == $action && $this->array_has_value( $ids ) ) {
			$aff_url = isset( $_REQUEST['coupon_aff_url'] ) ? $_REQUEST['coupon_aff_url'] : '';

			if ( ! empty( $aff_url ) ) {
				foreach ( $ids as $post_id ) {
					update_post_meta( $post_id, '_wpc_destination_url', $aff_url );
				}

				$redirect = add_query_arg( 'set_aff_url', count( $ids ), $redirect );
			}
		}

		return $redirect;
	}

	public function restrict_manage_posts_action( $post_type, $which ) {
		if ( $post_type == $this->coupon_post_type && 'top' == $which ) {
			?>
			<div class="set-coupon-aff-url"
			     style="display: none; position: fixed; z-index: 9999; left: 0; right: 0; top: 0; bottom: 0; background-color: rgba(0,0,0,0.75);">
				<div class="box-inner"
				     style="position: relative; top: 10%; padding: 20px; background: #fff; width: 280px; margin-left: auto; margin-right: auto; border-radius: 5px;">
					<h2 style="margin-bottom: 15px;"><?php _e( 'Set Coupon Affiliate URL', $this->textdomain ); ?></h2>
					<label><input type="text" name="coupon_aff_url"
					              placeholder="<?php _e( 'Enter coupon affiliate url', $this->textdomain ); ?>"
					              style="width: 220px;"></label>
					<button class="button" type="submit"><?php _e( 'Ok', $this->textdomain ); ?></button>
					<span class="dashicons dashicons-no-alt"
					      style="cursor: pointer; position: absolute; top: 10px; right: 10px;"></span>
				</div>
			</div>
			<?php
		}
	}

	public function bulk_actions_filter( $actions ) {
		$actions['mark_verified']   = __( 'Mark as verified', $this->textdomain );
		$actions['mark_unverified'] = __( 'Mark as unverified', $this->textdomain );
		$actions['set_url']         = __( 'Set affiliate URL', $this->textdomain );

		return $actions;
	}

	public function wp_action() {
		if ( is_tax( $this->coupon_store_taxonomy ) ) {
			$object_id = get_queried_object_id();

			$thumb_id = get_term_meta( $object_id, '_wpc_store_image_id', true );

			if ( ! $this->is_positive_number( $thumb_id ) ) {
				$this->generate_store_thumb( $object_id );
			} else {
				$this->delete_duplicate_store_thumb( $thumb_id, $object_id );
			}
		}
	}

	public function post_submitbox_misc_actions_action( $post ) {
		if ( $this->coupon_post_type == $post->post_type ) {
			$post_id = $post->ID;

			$auto_added = get_post_meta( $post_id, 'auto_added_coupon', true );

			if ( 1 == $auto_added ) {
				if ( ! $this->coupon_verified( $post_id ) ) {
					$post_type = get_post_type_object( $post->post_type );
					?>
					<div class="misc-pub-section">
						<label>
							<input type="checkbox" name="<?php echo $this->coupon_verified_meta_key; ?>"
							       value="1"> <?php printf( __( 'Mark this %s as verified?', $this->textdomain ), $post_type->labels->singular_name ); ?>
						</label>
					</div>
					<?php
				}
			}
		}
	}

	public function query_unverified_coupon_args( $posts_per_page, $term_id ) {
		$query_args = array(
			'post_type'      => 'coupon',
			'post_status'    => 'publish',
			'posts_per_page' => $posts_per_page,
			'paged'          => 1,
			'tax_query'      => array(
				'relation' => 'AND',
				array(
					'taxonomy' => 'coupon_store',
					'field'    => 'term_id',
					'terms'    => array( $term_id ),
					'operator' => 'IN',
				),
			),
			'orderby'        => 'menu_order date',
			'order'          => 'desc',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'   => $this->coupon_verified_meta_key,
					'value' => 0,
					'type'  => 'NUMERIC'
				),
				array(
					'key'     => $this->coupon_verified_meta_key,
					'compare' => 'EXISTS'
				)
			)
		);

		return $query_args;
	}

	public function pre_get_posts_action( $query ) {
		if ( $query instanceof WP_Query ) {
			if ( is_tax( $this->coupon_store_taxonomy ) || wp_doing_ajax() ) {
				$meta_query = $query->get( 'meta_query' );

				if ( ! is_array( $meta_query ) ) {
					$meta_query = array();
				}

				$serialize = maybe_serialize( $meta_query );

				if ( false !== strpos( $serialize, '_wpc_percent_success' ) ) {
					$meta_query[] = array(
						'relation' => 'OR',
						array(
							'key'     => $this->coupon_verified_meta_key,
							'compare' => 'NOT EXISTS'
						),
						array(
							'key'   => $this->coupon_verified_meta_key,
							'type'  => 'NUMERIC',
							'value' => 1
						)
					);

					$meta_query['relation'] = 'AND';

					$query->set( 'meta_query', $meta_query );
				} else {
					if ( wp_doing_ajax() ) {
						if ( isset( $_POST['args'] ) && isset( $_POST['args']['type'] ) && 'unverified' == $_POST['args']['type'] ) {
							$ppp = $query->get( 'posts_per_page' );

							if ( ! is_numeric( $ppp ) ) {
								$ppp = $this->get_store_number_unverified();
							}

							$query_args = $this->query_unverified_coupon_args( $ppp, 1 );
							$query->set( 'meta_query', $query_args['meta_query'] );
						}
					}
				}
			}
		}
	}

	public function get_store_number_unverified( $default = '' ) {
		$number_unverified = WP_Custom_Coupons()->get_option( 'store_number_unverified' );

		if ( ! is_numeric( $number_unverified ) ) {
			if ( ! is_numeric( $default ) ) {
				$default = absint( wpcoupon_get_option( 'store_number_active', 15 ) );
			}

			$number_unverified = $default;
		}

		return $number_unverified;
	}

	public function coupon_verified( $post_id ) {
		$coupon_verified = get_post_meta( $post_id, $this->coupon_verified_meta_key, true );

		if ( 0 != $coupon_verified || '' === $coupon_verified ) {
			return true;
		}

		return false;
	}

	public function post_class_filter( $classes ) {
		$post_id = get_the_ID();

		if ( $this->coupon_post_type == get_post_type( $post_id ) ) {
			$classes[] = 'coupon';

			if ( $this->coupon_verified( $post_id ) ) {
				$classes[] = 'verified';
			}
		}

		return $classes;
	}

	public function get_template_part_action() {
		$post_id = get_the_ID();

		if ( $this->coupon_post_type == get_post_type( $post_id ) ) {
			if ( wp_doing_ajax() ) {
				$action   = isset( $_POST['wpcoupon_coupon_ajax'] ) ? $_POST['wpcoupon_coupon_ajax'] : '';
				$st_doing = isset( $_POST['st_doing'] ) ? $_POST['st_doing'] : '';

				if ( 'wpcoupon_coupon_ajax' != $action && 'load_store_coupons' != $st_doing ) {
					return;
				}
			}

			$used_today = wpcoupon_coupon()->get_used_today();

			$used_today = absint( $used_today );

			$used_today = number_format( $used_today );

			$used_today = sprintf( __( '%s People used today', $this->textdomain ), $used_today );

			$class = join( ' ', get_post_class( 'custom-coupons-data', $post_id ) );
			?>
			<div class="<?php echo $class; ?>" data-used-today="<?php echo esc_attr( $used_today ); ?>"></div>
			<?php
		}
	}

	public function list_coupons( $coupons, $loop, $args = array() ) {
		?>
		<div class="store-listings st-list-coupons">
			<?php
			foreach ( $coupons as $coupon ) {
				wpcoupon_setup_coupon( $coupon );
				$post = $coupon->post;
				setup_postdata( $post );

				get_template_part( 'loop/loop-coupon', $loop );
			}
			?>
		</div>
		<?php
		$posts_per_page = isset( $args['posts_per_page'] ) ? $args['posts_per_page'] : '';
		$term_id        = isset( $args['term_id'] ) ? $args['term_id'] : '';
		$max_num_pages  = isset( $args['max_num_pages'] ) ? $args['max_num_pages'] : '';

		if ( is_numeric( $posts_per_page ) && $this->is_positive_number( $term_id ) && is_numeric( $max_num_pages ) && $max_num_pages > 1 ) {
			$type = isset( $args['type'] ) ? $args['type'] : 'active';

			$args = array(
				'type'     => $type,
				'number'   => $posts_per_page,
				'store_id' => $term_id
			);
			?>
			<div class="load-more wpb_content_element">
				<a href="#" class="ui button btn btn_primary btn_large"
				   data-doing="load_store_coupons" data-next-page="2"
				   data-args="<?php echo esc_attr( json_encode( $args ) ); ?>"
				   data-loading-text="<?php esc_attr_e( 'Loading...', $this->textdomain ); ?>"><?php esc_html_e( 'Load More Coupons', $this->textdomain ); ?>
					<i class="arrow circle alternate outline down icon"></i></a>
			</div>
			<?php
		}
	}

	public function wp_enqueue_scripts_action() {
		wp_enqueue_style( 'custom-coupons-style', $this->base_url . '/css/custom-coupons.css' );

		wp_enqueue_script( 'wp-custom-coupons', $this->base_url . '/js/custom-coupons.js', array(
			'jquery',
			'wpcoupon_global'
		), false, true );

		$l10n = array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'l10n'    => array(
				'couponVerifiedText' => __( '<i class="check-o-icon"></i> Verified Coupon', $this->textdomain )
			)
		);

		wp_localize_script( 'wp-custom-coupons', 'wpCustomCoupons', $l10n );
	}

	public function template_redirect_action() {
		if ( is_tax( $this->coupon_store_taxonomy ) && function_exists( 'wpcoupon_setup_store' ) ) {
			include_once $this->base_dir . '/taxonomy-' . $this->coupon_store_taxonomy . '.php';
			exit;
		}
	}

	public function create_category_action( $term_id ) {
		remove_action( 'edited_' . $this->coupon_store_taxonomy, array( $this, 'create_category_action' ) );

		$coupon_number = isset( $_POST['coupon_number'] ) ? $_POST['coupon_number'] : '';

		update_term_meta( $term_id, 'coupon_number', $coupon_number );

		$data_file = isset( $_POST['data_file'] ) ? $_POST['data_file'] : '';

		update_term_meta( $term_id, 'data_file', $data_file );

		$data_file = isset( $_POST['data_file_description'] ) ? $_POST['data_file_description'] : '';

		update_term_meta( $term_id, 'data_file_description', $data_file );

		$percent_off_position = isset( $_POST['percent_off_position'] ) ? $_POST['percent_off_position'] : '';

		update_term_meta( $term_id, 'percent_off_position', $percent_off_position );

		$description = isset( $_POST['description'] ) ? $_POST['description'] : '';

		$store_url = isset( $_POST['_wpc_store_url'] ) ? $_POST['_wpc_store_url'] : '';

		$this->update_store_description( $term_id, $description, $store_url );

		$title_meta = isset( $_POST['title_meta'] ) ? $_POST['title_meta'] : '';

		update_term_meta( $term_id, 'title_meta', $title_meta );

		$description_meta = isset( $_POST['description_meta'] ) ? $_POST['description_meta'] : '';

		update_term_meta( $term_id, 'description_meta', $description_meta );

		$action = isset( $_POST['action'] ) ? $_POST['action'] : '';

		if ( 'add-tag' == $action ) {
			update_term_meta( $term_id, '_wpc_auto_thumbnail', 'on' );

			$this->generate_coupons_for_store( $term_id, false, $coupon_number );

			$this->generate_store_thumb( $term_id, $store_url );
		}
	}

	public function update_store_description( $term_id, $store_description, $store_home_url ) {
		$default_desc = $this->get_option( 'store_description' );

		// Check if store description field contains Name
		if ( false !== strpos( $store_description, 'NAME:' ) ) {
			$store_description = str_replace( 'NAME:', '', $store_description );

			if ( ! empty( $default_desc ) ) {
				$store_description = str_replace( '%store_name%', $store_description, $default_desc );
			}
		} elseif ( empty( $store_description ) && ! empty( $default_desc ) && ! empty( $store_home_url ) ) {
			$domain = $this->get_domain_name( $store_home_url, true );

			$domain = ucfirst( $domain );

			$store_description = str_replace( '%store_name%', $domain, $default_desc );
		}

		if ( ! empty( $store_description ) ) {
			wp_update_term( $term_id, $this->coupon_store_taxonomy, array( 'description' => $store_description ) );
		}
	}

	public function query_data_file( $args = array() ) {
		$defaults = array(
			'post_type'      => $this->data_post_type,
			'posts_per_page' => 100,
			'post_status'    => 'publish'
		);

		$args = wp_parse_args( $args, $defaults );

		$query = new WP_Query( $args );

		return $query;
	}

	public function select_data_file_html( $query, $name, $selected = '' ) {
		if ( $query instanceof WP_Query ) {
			?>
			<select name="<?php echo $name; ?>" id="<?php echo $name; ?>">
				<option value=""><?php _e( '-- Choose data file --', $this->textdomain ); ?></option>
				<?php
				while ( $query->have_posts() ) {
					$query->the_post();
					?>
					<option
						value="<?php the_ID(); ?>"<?php selected( get_the_ID(), $selected ); ?>><?php the_title(); ?></option>
					<?php
				}

				wp_reset_postdata();
				?>
			</select>
			<?php
		}
	}

	public function category_add_form_fields_action( $term ) {
		global $pagenow;

		if ( 'edit-tags.php' == $pagenow ) {
			?>
			<div class="form-field">
				<label for="coupon_number"><?php _e( 'Coupon Number', $this->textdomain ); ?></label>
				<input name="coupon_number" id="coupon_number" type="number" value="" class="medium-text" min="1">

				<p class="description"><?php _e( 'The number of auto generation coupon for this store.', $this->textdomain ); ?></p>
			</div>
			<div class="form-field">
				<label for="data_file"><?php _e( 'Data File for Title', $this->textdomain ); ?></label>
				<?php
				$query = $this->query_data_file();

				WP_Custom_Coupons()->select_data_file_html( $query, 'data_file' );
				?>

				<p class="description"><?php _e( 'Choose the data file for auto generate coupon title.', $this->textdomain ); ?></p>
			</div>
			<div class="form-field">
				<label
					for="data_file_description"><?php _e( 'Data File for Description', $this->textdomain ); ?></label>
				<?php
				if ( 1 == WP_Custom_Coupons()->get_option( 'separate_description_table' ) ) {
					$query = WP_Custom_Coupons()->query_data_file( array( 'post_type' => WP_Custom_Coupons()->data_description_post_type ) );
				}

				WP_Custom_Coupons()->select_data_file_html( $query, 'data_file_description' );
				?>

				<p class="description"><?php _e( 'Choose the data file for auto generate coupon description.', $this->textdomain ); ?></p>
			</div>
			<div class="form-field">
				<label
					for="percent_off_position"><?php _e( 'Percent Off Position', $this->textdomain ); ?></label>
				<select name="percent_off_position" id="percent_off_position">
					<option value="tail"><?php _ex( 'Tail', 'percent off position', $this->textdomain ); ?></option>
					<option value="head"><?php _ex( 'Head', 'percent off position', $this->textdomain ); ?></option>
				</select>

				<p class="description"><?php _e( 'Choose the data file for auto generate coupon description.', $this->textdomain ); ?></p>
			</div>
			<input type="hidden" name="_wpc_auto_thumbnail" value="on">
			<?php
		} else {
			if ( $term instanceof WP_Term ) {
				$coupon_number = get_term_meta( $term->term_id, 'coupon_number', true );
				$data_file     = get_term_meta( $term->term_id, 'data_file', true );

				$data_file_description = get_term_meta( $term->term_id, 'data_file_description', true );
				$percent_off_position  = get_term_meta( $term->term_id, 'percent_off_position', true );

				$title_meta       = get_term_meta( $term->term_id, 'title_meta', true );
				$description_meta = get_term_meta( $term->term_id, 'description_meta', true );
				?>
				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="title_meta"><?php _e( 'Title Meta', $this->textdomain ); ?></label>
					</th>
					<td>
						<input name="title_meta" id="title_meta" type="text"
						       value="<?php echo $title_meta; ?>" class="medium-text">

						<p class="description"><?php _e( 'Store title for using in meta tag. You can use <code>%store_name%</code> for real store name, <code>%store_count%s</code> for total coupons of store, <code>%date:date_format%</code> for current datetime.', $this->textdomain ); ?></p>
					</td>
				</tr>
				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="description_meta"><?php _e( 'Description Meta', $this->textdomain ); ?></label>
					</th>
					<td>
						<input name="description_meta" id="description_meta" type="text"
						       value="<?php echo $description_meta; ?>" class="medium-text">

						<p class="description"><?php _e( 'Store description for using in meta tag. You can use <code>%store_name%</code> for real store name, <code>%store_count%s</code> for total coupons of store, <code>%date:date_format%</code> for current datetime.', $this->textdomain ); ?></p>
					</td>
				</tr>
				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="coupon_number"><?php _e( 'Coupon Number', $this->textdomain ); ?></label>
					</th>
					<td>
						<input name="coupon_number" id="coupon_number" min="1" type="number"
						       value="<?php echo $coupon_number; ?>" class="medium-text">

						<p class="description"><?php _e( 'The number of auto generation coupon for this store.', $this->textdomain ); ?></p>
					</td>
				</tr>
				<tr class="form-field">
					<th scope="row" valign="top">
						<label for="data_file"><?php _e( 'Data File for Title', $this->textdomain ); ?></label>
					</th>
					<td>
						<?php
						$query = $this->query_data_file();

						WP_Custom_Coupons()->select_data_file_html( $query, 'data_file', $data_file );
						?>

						<p class="description"><?php _e( 'Choose the data file for auto generate coupon title.', $this->textdomain ); ?></p>
					</td>
				</tr>
				<tr class="form-field">
					<th scope="row" valign="top">
						<label
							for="data_file_description"><?php _e( 'Data File for Description', $this->textdomain ); ?></label>
					</th>
					<td>
						<?php
						if ( 1 == WP_Custom_Coupons()->get_option( 'separate_description_table' ) ) {
							$query = WP_Custom_Coupons()->query_data_file( array( 'post_type' => WP_Custom_Coupons()->data_description_post_type ) );
						}

						WP_Custom_Coupons()->select_data_file_html( $query, 'data_file_description', $data_file_description );
						?>

						<p class="description"><?php _e( 'Choose the data file for auto generate coupon description.', $this->textdomain ); ?></p>
					</td>
				</tr>
				<tr class="form-field">
					<th scope="row" valign="top">
						<label
							for="percent_off_position"><?php _e( 'Percent Off Position', $this->textdomain ); ?></label>
					</th>
					<td>
						<select name="percent_off_position" id="percent_off_position">
							<option
								value="tail"<?php selected( 'tail', $percent_off_position ); ?>><?php _ex( 'Tail', 'percent off position', $this->textdomain ); ?></option>
							<option
								value="head"<?php selected( 'head', $percent_off_position ); ?>><?php _ex( 'Head', 'percent off position', $this->textdomain ); ?></option>
						</select>

						<p class="description"><?php _e( 'Choose the data file for auto generate coupon description.', $this->textdomain ); ?></p>
					</td>
				</tr>
				<tr class="form-field">
					<th scope="row" valign="top">&nbsp;</th>
					<td>
						<?php
						$atts = array(
							'data-confirm-text' => __( 'Are you sure you want to do it?', WP_Custom_Coupons()->textdomain ),
							'data-wait-text'    => __( 'Please wait...', WP_Custom_Coupons()->textdomain ),
							'data-text'         => __( 'Generate Coupon', WP_Custom_Coupons()->textdomain )
						);

						$button = get_submit_button( $atts['data-text'], 'primary', 'generate_coupon', false, $atts );
						$button = str_replace( 'type="submit"', 'type="button"', $button );
						echo $button;
						?>
						<img class="icon-ajax" src="<?php echo home_url( 'wp-includes/images/wpspin-2x.gif' ); ?>"
						     alt=""
						     style="vertical-align: middle; margin-left: 5px; max-height: 28px; display: none;">
					</td>
				</tr>
				<?php
			}
		}
	}

	public function admin_notices_action() {
		?>
		<div class="updated settings-error notice error is-dismissible">
			<p>
				<strong><?php _e( 'Error:', $this->textdomain ); ?></strong>
				<span><?php _e( 'Plugin <code>Auto Coupon</code> is only compatible with the WP Coupon theme, please contact the author if you want to change the other theme.', $this->textdomain ); ?></span>
			</p>
		</div>
		<?php
	}

	public function advanced_settings_section_callback() {
		echo wpautop( __( 'If you want to customize more, you can find advanced settings in this section.', $this->textdomain ) );
	}

	public function custom_admin_init_action() {
		$options = array();
		$args    = $options;

		$files = $this->query_data_file();

		foreach ( $files as $object ) {
			if ( $object instanceof WP_Post ) {
				$options[ $object->ID ] = $object->post_title;
			}
		}

		$args['option_none'] = '<option value="">' . __( '-- Choose data file --', $this->textdomain ) . '</option>';
		$args['options']     = $options;
		$args['class']       = 'regular-text';
		$args['description'] = __( 'The default data file for generating coupon title.', $this->textdomain );

		$this->add_settings_field( 'data_file', __( 'Data File for Title', $this->textdomain ), array(
			$this,
			'admin_setting_field_select'
		), 'default', $args );

		if ( 1 == $this->get_option( 'separate_description_table' ) ) {
			$options = array();

			$files = $this->query_data_file( array( 'post_type' => $this->data_description_post_type ) );

			foreach ( $files as $object ) {
				if ( $object instanceof WP_Post ) {
					$options[ $object->ID ] = $object->post_title;
				}
			}
		}

		$args['option_none'] = '<option value="">' . __( '-- Choose data file --', $this->textdomain ) . '</option>';
		$args['options']     = $options;
		$args['class']       = 'regular-text';
		$args['description'] = __( 'The default data file for generating coupon description.', $this->textdomain );

		$this->add_settings_field( 'data_file_description', __( 'Data File for Description', $this->textdomain ), array(
			$this,
			'admin_setting_field_select'
		), 'default', $args );

		$args = array();

		$args['type'] = 'number';

		$args['attributes'] = array( 'min' => 1 );

		$args['description'] = __( 'The default number of coupons for auto generating for each store.', $this->textdomain );

		$this->add_settings_field( 'coupon_number', __( 'Coupon Number', $this->textdomain ), null, 'default', $args );

		unset( $args['attributes'] );

		$args['description'] = __( 'Number unverified coupons to show.', $this->textdomain );
		$this->add_settings_field( 'store_number_unverified', __( 'Unverified Coupon Number', $this->textdomain ), null, 'default', $args );

		$args['type'] = 'text';

		$args['description'] = __( 'The heading title for unverified coupons section. You can use <code>%store_name%</code> tag to replace with real Store\'s name.', $this->textdomain );
		$this->add_settings_field( 'store_unverified_coupon', __( 'Unverified Coupon Heading', $this->textdomain ), null, 'default', $args );

		$args['description'] = __( 'The default description for Stores. You can use <code>%store_name%</code> tag to replace with real Store\'s name.', $this->textdomain );

		$this->add_settings_field( 'store_description', __( 'Store Description', $this->textdomain ), array(
			$this,
			'admin_setting_field_editor'
		), 'default', $args );

		$taxonomies = get_taxonomies( array(
			'_builtin' => false,
			'public'   => false
		), OBJECT );

		$options = array();

		foreach ( $taxonomies as $taxonomy ) {
			if ( $taxonomy instanceof WP_Taxonomy && false !== strpos( $taxonomy->name, 'wp_data_column' ) ) {
				$options[ $taxonomy->name ] = $taxonomy->labels->singular_name;
			}
		}

		$args['option_none'] = '<option value="">' . __( '-- Choose column --', $this->textdomain ) . '</option>';
		$args['options']     = $options;
		$args['class']       = 'regular-text';
		$args['description'] = __( 'The data column that contains percent off for coupon.', $this->textdomain );

		$this->add_settings_field( 'percent_off_column', __( 'Percent Off Column', $this->textdomain ), array(
			$this,
			'admin_setting_field_select'
		), 'default', $args );

		$args['description'] = __( 'Define percent off text. If coupon title contains any text in this field, it will be added to description too. Separate each text per line.', $this->textdomain );

		$this->add_settings_field( 'percent_off_text', __( 'Percent Text', $this->textdomain ), array(
			$this,
			'admin_setting_field_textarea'
		), 'default', $args );

		$this->add_settings_section( 'advanced_settings', __( 'Advanced Settings', $this->textdomain ), array(
			$this,
			'advanced_settings_section_callback'
		) );

		$args = array();

		$args['type'] = 'checkbox';

		$args['description'] = __( 'Auto generate coupon base on default options or store data.', $this->textdomain );

		$this->add_settings_field( 'auto_generate_coupon', __( 'Auto Generate Coupon', $this->textdomain ), null, 'advanced_settings', $args );

		$args['description'] = __( 'If you don\'t want to use Description Data Table post type same as Title Data Table post type, you can separate it here.', $this->textdomain );

		$this->add_settings_field( 'separate_description_table', __( 'Separate Description Table', $this->textdomain ), null, 'advanced_settings', $args );

		$args['description'] = __( 'If you don\'t want to use Description Data Table Column same as Title Data Table Column, you can separate it here.', $this->textdomain );

		$this->add_settings_field( 'separate_description_column', __( 'Separate Description Column', $this->textdomain ), null, 'advanced_settings', $args );

		$args['type'] = 'number';

		$args['description'] = __( 'The number of coupons for auto generating in cron job.', $this->textdomain );

		$this->add_settings_field( 'coupon_number_cron', __( 'Coupon Number Cron', $this->textdomain ), null, 'advanced_settings', $args );

		$args['description'] = __( 'The number of stores for auto generating coupon in cron job. Plugin will get random (this number) stores and generate random coupons. The default value is 1.', $this->textdomain );

		$this->add_settings_field( 'store_number_cron', __( 'Store Number Cron', $this->textdomain ), null, 'advanced_settings', $args );
	}

	public function get_custom_coupons_number( $store_id = null ) {
		$number = $this->get_option( 'coupon_number' );

		if ( ! is_numeric( $number ) && $this->is_positive_number( $store_id ) ) {
			$number = get_term_meta( $store_id, 'coupon_number', true );
		}

		if ( ! is_numeric( $number ) ) {
			$number = 30;
		}

		return $number;
	}

	public function get_column_taxonomy_name( $char ) {
		$char = strtoupper( $char );

		return 'wp_data_column_' . $char;
	}

	public function get_column_description_taxonomy_name( $char ) {
		$char = strtoupper( $char );

		return 'wp_desc_column_' . $char;
	}

	public function mime_types_filter( $mime_types ) {
		$mime_types['xls|xlsx'] = 'application/vnd.ms-excel';

		return $mime_types;
	}

	public function save_post_action( $post_id ) {
		$post_type = get_post_type( $post_id );

		if ( $this->is_data_post_type( $post_type ) ) {
			$data_file = isset( $_POST['data_file'] ) ? $_POST['data_file'] : '';
			$current   = get_post_meta( $post_id, 'data_file', true );

			update_post_meta( $post_id, 'data_file', $data_file );

			if ( $data_file != $current ) {
				$file_path = get_attached_file( $data_file );

				if ( ! file_exists( $file_path ) ) {
					return;
				}

				$spreadsheet = IOFactory::load( $file_path );

				$data = $spreadsheet->getActiveSheet()->toArray( null, true, true, true );

				if ( $this->array_has_value( $data ) ) {
					$comb_x = '';
					$comb_y = '';

					foreach ( $data as $row => $column ) {
						if ( $this->array_has_value( $column ) ) {
							foreach ( $column as $cn => $value ) {
								if ( ! empty( $value ) ) {
									$search = 'Column Combinations';

									if ( $search == $value || strtolower( $search ) == strtolower( $value ) ) {
										$comb_x = $row;
										$comb_y = $cn;

										continue;
									}

									$taxonomy = '';

									if ( $post_type == $this->data_post_type ) {
										if ( $comb_x < $row && $comb_y == $cn ) {
											$taxonomy = $this->combination_taxonomy;
										} else {
											$taxonomy = $this->get_column_taxonomy_name( $cn );
										}
									} elseif ( $post_type == $this->data_description_post_type ) {
										if ( $comb_x < $row && $comb_y == $cn ) {
											$taxonomy = $this->combination_description_taxonomy;
										} else {
											$taxonomy = $this->get_column_description_taxonomy_name( $cn );
										}
									}

									if ( ! taxonomy_exists( $taxonomy ) ) {
										continue;
									}

									$term = term_exists( $value, $taxonomy );

									$term_id = '';

									if ( 0 !== $term && null !== $term ) {
										if ( isset( $term['term_id'] ) ) {
											$term_id = $term['term_id'];
										}
									} else {
										$result = wp_insert_term( $value, $taxonomy );

										if ( ! is_wp_error( $result ) && isset( $result['term_id'] ) ) {
											$term_id = $result['term_id'];
										}
									}

									if ( $this->is_positive_number( $term_id ) ) {
										$term = get_term( $term_id, $taxonomy );

										if ( $term instanceof WP_Term ) {
											wp_set_object_terms( $post_id, $term->name, $taxonomy, true );
										}
									}
								}
							}
						}
					}
				}
			}
		} elseif ( $this->coupon_post_type == $post_type ) {
			if ( isset( $_POST[ $this->coupon_verified_meta_key ] ) ) {
				delete_post_meta( $post_id, $this->coupon_verified_meta_key );
			}
		}
	}

	public function is_data_post_type( $post_type ) {
		return ( $this->data_post_type == $post_type || $this->data_description_post_type == $post_type );
	}

	public function adding_custom_meta_boxes( $post_type ) {
		if ( $this->is_data_post_type( $post_type ) ) {
			add_meta_box(
				'coupon-data-extra',
				__( 'Extra Information', $this->textdomain ),
				array( $this, 'render_my_meta_box' ),
				array( $this->data_post_type, $this->data_description_post_type ),
				'normal',
				'default'
			);
		}
	}

	public function render_my_meta_box( $post ) {
		if ( $this->is_data_post_type( $post->post_type ) ) {
			$data_file = get_post_meta( $post->ID, 'data_file', true );
			$att_file  = get_attached_file( $data_file );

			if ( ! file_exists( $att_file ) ) {
				$data_file = '';
			}
			?>
			<table class="form-table">
				<tbody>
				<tr>
					<th scope="row">
						<label for="data_file"><?php _e( 'Data File:', $this->textdomain ); ?></label>
					</th>
					<td>
						<input type="text" id="data_file" name="data_file" class="medium-text"
						       value="<?php echo $data_file; ?>" readonly>
						<button type="button"
						        class="button wp-media-upload"
						        data-title="<?php _e( 'Insert Media File', $this->textdomain ); ?>"
						        data-button-text="<?php _e( 'Use this file', $this->textdomain ); ?>"><?php _e( 'Choose File', $this->textdomain ); ?></button>
						<p class="file-url description">
							<?php
							if ( $this->is_positive_number( $data_file ) && file_exists( $att_file ) ) {
								printf( '(%s)', $att_file );
							}
							?>
						</p>
					</td>
				</tr>
				</tbody>
			</table>
			<?php
		}
	}

	public function build_text_by_combination( $data_file, $combinations = '', $store = '' ) {
		$result = '';

		if ( is_string( $combinations ) && ! empty( $combinations ) ) {
			$combinations = explode( '+', $combinations );
		}

		$post_type = get_post_type( $data_file );

		if ( empty( $combinations ) ) {
			$combinations = array();

			if ( $post_type == $this->data_post_type || 1 != $this->get_option( 'separate_description_column' ) ) {
				$combinations = wp_get_object_terms( $data_file, $this->combination_taxonomy );
			} elseif ( $post_type == $this->data_description_post_type ) {
				$combinations = wp_get_object_terms( $data_file, $this->combination_description_taxonomy );
			}

			$combination = $this->get_random_value( $combinations );

			if ( $combination instanceof WP_Term ) {
				$combinations = explode( '+', $combination->name );
			}
		}

		if ( $this->array_has_value( $combinations ) ) {
			foreach ( $combinations as $cn ) {
				$taxonomy = '';

				if ( $post_type == $this->data_post_type || 1 != $this->get_option( 'separate_description_column' ) ) {
					$taxonomy = $this->get_column_taxonomy_name( $cn );
				} elseif ( $post_type == $this->data_description_post_type ) {
					$taxonomy = $this->get_column_description_taxonomy_name( $cn );
				}

				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}

				$terms = wp_get_object_terms( $data_file, $taxonomy, array( 'fields' => 'names' ) );

				if ( $this->array_has_value( $terms ) ) {
					$text = $this->get_random_value( $terms );

					$result .= $text;

					$result .= " ";
				}
			}

			$result = trim( $result );
		}

		if ( $store instanceof WP_Term ) {
			$search = array(
				'%store_name%'
			);

			$replace = array(
				$store->name
			);

			$result = str_replace( $search, $replace, $result );
		}

		$result = ucfirst( $result );

		return $result;
	}

	public function delete_duplicate_store_thumb( $thumb_id, $term_id = '' ) {
		$details = wp_get_attachment_metadata( $thumb_id );
		$width   = isset( $details['width'] ) ? $details['width'] : '';
		$height  = isset( $details['height'] ) ? $details['height'] : '';
		$file    = isset( $details['file'] ) ? $details['file'] : '';

		if ( ! empty( $file ) && is_numeric( $width ) && is_numeric( $height ) ) {
			global $wpdb;

			$dirname  = dirname( $file );
			$name     = basename( $file );
			$info     = pathinfo( $name );
			$filename = $info['filename'];

			$parts = explode( '-', $filename );
			array_pop( $parts );

			$name = implode( '-', $parts );

			$sql = "SELECT ID FROM " . $wpdb->posts;
			$sql .= " WHERE post_name LIKE '%$name%' AND post_type = 'attachment'";

			$ids = $wpdb->get_col( $sql );

			if ( $this->array_has_value( $ids ) ) {
				$path = get_attached_file( $thumb_id );
				$size = filesize( $path );
				$sha1 = sha1_file( $path );

				foreach ( $ids as $key => $file_id ) {
					if ( $file_id != $thumb_id ) {
						$details = wp_get_attachment_metadata( $file_id );

						$w = isset( $details['width'] ) ? $details['width'] : '';
						$h = isset( $details['height'] ) ? $details['height'] : '';
						$f = isset( $details['file'] ) ? $details['file'] : '';
						$d = dirname( $f );

						if ( $d == $dirname && $w == $width && $h == $height ) {
							$p  = get_attached_file( $file_id );
							$s  = filesize( $p );
							$sf = sha1_file( $p );

							if ( $s == $size && $sha1 == $sf ) {
								$name     = basename( $f );
								$info     = pathinfo( $name );
								$filename = $info['filename'];

								$parts = explode( '-', $filename );
								$last  = array_pop( $parts );

								$del = true;

								if ( ! is_numeric( $last ) ) {
									if ( $this->is_positive_number( $term_id ) ) {
										$this->set_store_thumb( $term_id, $file_id );
										unset( $ids[ $key ] );
										$del = false;
									}
								}

								if ( $del ) {
									wp_delete_attachment( $file_id, true );
								}
							}
						}
					}
				}
			}
		}
	}

	public function set_store_thumb( $term_id, $thumb_id ) {
		if ( $this->is_positive_number( $thumb_id ) ) {
			update_term_meta( $term_id, '_wpc_store_image', wp_get_attachment_url( $thumb_id ) );
			update_term_meta( $term_id, '_wpc_store_image_id', $thumb_id );
			$this->delete_duplicate_store_thumb( $thumb_id, $term_id );
		}
	}

	public function generate_store_thumb( $term_id, $store_home_url = '' ) {
		if ( function_exists( 'wpcoupon_download_webshoot' ) ) {
			if ( empty( $store_home_url ) ) {
				$store_home_url = get_term_meta( $term_id, '_wpc_store_url', true );
			}

			if ( empty( $store_home_url ) ) {
				return;
			}

			$id = get_term_meta( $term_id, '_wpc_store_image_id', true );

			if ( $this->is_positive_number( $id ) ) {
				$path = get_attached_file( $id );

				if ( file_exists( $path ) ) {
					return;
				}
			}

			$id = wpcoupon_download_webshoot( $store_home_url );

			$this->set_store_thumb( $term_id, $id );
		}
	}

	public function insert_store( $name, $home_url, $aff_url, $description = '' ) {
		$inserted = wp_insert_term( $name, $this->coupon_store_taxonomy );

		if ( is_array( $inserted ) && isset( $inserted['term_id'] ) && $this->is_positive_number( $inserted['term_id'] ) ) {
			$term_id = $inserted['term_id'];

			if ( ! empty( $home_url ) ) {
				update_term_meta( $term_id, '_wpc_store_url', $home_url );

				if ( ! function_exists( 'wpcoupon_download_webshoot' ) ) {
					$helper = get_template_directory() . '/inc/core/helper.php';

					if ( file_exists( $helper ) ) {
						require_once $helper;
					}
				}

				$this->generate_store_thumb( $term_id, $home_url );
			}

			if ( ! empty( $aff_url ) ) {
				update_term_meta( $term_id, '_wpc_store_aff_url', $aff_url );
			}

			$this->update_store_description( $term_id, $description, $home_url );

			update_term_meta( $term_id, '_wpc_auto_thumbnail', 'on' );
		}

		return $inserted;
	}

	/**
	 * AJAX callback for auto insert Store or auto generate Coupons for Store.
	 */
	public function import_coupon_and_store_ajax_callback() {
		$data = array();
		$new  = false;

		$store_id = isset( $_POST['store_id'] ) ? $_POST['store_id'] : '';

		$store_name        = isset( $_POST['store_name'] ) ? $_POST['store_name'] : '';
		$store_home_url    = isset( $_POST['store_home_url'] ) ? $_POST['store_home_url'] : '';
		$store_description = isset( $_POST['store_description'] ) ? $_POST['store_description'] : '';

		$store_data = isset( $_FILES['store_data_file'] ) ? $_FILES['store_data_file'] : '';

		if ( ! $this->is_positive_number( $store_id ) && ! $this->array_has_value( $store_id ) ) {
			if ( ! empty( $store_name ) ) {
				$result = wp_insert_term( $store_name, $this->coupon_store_taxonomy );

				if ( ! is_wp_error( $result ) ) {
					$term_id = isset( $result['term_id'] ) ? $result['term_id'] : '';

					if ( $this->is_positive_number( $term_id ) ) {
						$store_aff_url = isset( $_POST['store_aff_url'] ) ? $_POST['store_aff_url'] : '';

						if ( ! empty( $store_aff_url ) ) {
							update_term_meta( $term_id, '_wpc_store_aff_url', $store_aff_url );
						}

						if ( ! empty( $store_home_url ) ) {
							update_term_meta( $term_id, '_wpc_store_url', $store_home_url );

							if ( ! function_exists( 'wpcoupon_download_webshoot' ) ) {
								$helper = get_template_directory() . '/inc/core/helper.php';

								if ( file_exists( $helper ) ) {
									require_once $helper;
								}
							}

							$this->generate_store_thumb( $term_id, $store_home_url );
						}

						$this->update_store_description( $term_id, $store_description, $store_home_url );

						//update_term_meta( $term_id, '_wpc_extra_info', $store_description );

						update_term_meta( $term_id, '_wpc_auto_thumbnail', 'on' );
						$store_id = $term_id;

						$new = true;
					}
				} else {
					$data['message'] = $result->get_error_message();
					wp_send_json_error( $data );
				}
			} elseif ( $this->array_has_value( $store_data ) ) {
				if ( isset( $store_data['tmp_name'] ) && ! empty( $store_data['tmp_name'] ) ) {
					$spreadsheet = IOFactory::load( $store_data['tmp_name'] );

					$data = $spreadsheet->getActiveSheet()->toArray( null, true, true, true );

					if ( $this->array_has_value( $data ) ) {
						$store_id = array();

						foreach ( $data as $data_row ) {
							if ( $this->array_has_value( $data_row ) ) {
								$name = isset( $data_row['A'] ) ? $data_row['A'] : '';

								if ( ! empty( $name ) ) {
									// Insert non-exists term.
									$inserted = wp_insert_term( $name, $this->coupon_store_taxonomy );

									if ( is_array( $inserted ) && isset( $inserted['term_id'] ) && $this->is_positive_number( $inserted['term_id'] ) ) {
										$term_id = $inserted['term_id'];

										$store_id[] = $term_id;
										$home_url   = isset( $data_row['B'] ) ? $data_row['B'] : '';

										if ( ! empty( $home_url ) ) {
											update_term_meta( $term_id, '_wpc_store_url', $home_url );

											if ( ! function_exists( 'wpcoupon_download_webshoot' ) ) {
												$helper = get_template_directory() . '/inc/core/helper.php';

												if ( file_exists( $helper ) ) {
													require_once $helper;
												}
											}

											$this->generate_store_thumb( $term_id, $home_url );
										}

										$aff_url = isset( $data_row['C'] ) ? $data_row['C'] : '';

										if ( ! empty( $aff_url ) ) {
											update_term_meta( $term_id, '_wpc_store_aff_url', $aff_url );
										}

										$this->update_store_description( $term_id, '', $home_url );

										update_term_meta( $term_id, '_wpc_auto_thumbnail', 'on' );
									}
								}
							}
						}

						$new = true;
					}
				}
			}
		}

		$data_file = isset( $_POST['data_file'] ) ? $_POST['data_file'] : '';

		if ( $new && empty( $data_file ) ) {
			if ( empty( $store_name ) ) {
				$data['message'] = __( 'Store has been added successfully!', $this->textdomain );
			} else {
				$data['message'] = sprintf( __( 'Store %s has been added successfully!', $this->textdomain ), $store_name );
			}

			wp_send_json_success( $data );
		}

		$coupon_number = isset( $_POST['coupon_number'] ) ? $_POST['coupon_number'] : '';

		if ( ! is_numeric( $coupon_number ) ) {
			$coupon_number = $this->get_custom_coupons_number( $store_id );
		}

		$result = '';

		if ( is_array( $store_id ) ) {
			$count = 0;

			while ( $count < $coupon_number ) {
				foreach ( $store_id as $id ) {
					$result = $this->generate_coupons_for_store( $id, $new, 1 );

					if ( is_wp_error( $result ) ) {
						$data['message'] = $result->get_error_message();
						wp_send_json_error( $data );
					}
				}

				$count ++;
			}
		}

		if ( empty( $result ) ) {
			$result = $this->generate_coupons_for_store( $store_id, $new );
		}

		if ( is_wp_error( $result ) ) {
			$data['message'] = $result->get_error_message();
		} else {
			$coupon_number = isset( $_POST['coupon_number'] ) ? $_POST['coupon_number'] : '';

			if ( ! is_numeric( $coupon_number ) ) {
				$coupon_number = $this->get_custom_coupons_number( $store_id );
			}

			if ( empty( $store_name ) ) {
				$data['message'] = sprintf( __( '%d Coupons have been added to selected Stores successfully.', $this->textdomain ), $coupon_number );
			} else {
				$data['message'] = sprintf( __( '%d Coupons have been added to Store %s successfully.', $this->textdomain ), $coupon_number, $store_name );
			}

			wp_send_json_success( $data );
		}

		wp_send_json_error( $data );
	}

	public function sanitize_percent_off_text( $text ) {
		if ( false === strpos( $text, '%' ) ) {
			return '';
		}

		return $text;
	}

	public function get_percent_off_text() {
		$percent_off_text = $this->get_option( 'percent_off_text' );

		if ( ! empty( $percent_off_text ) ) {
			$percent_off_text = explode( "\n", $percent_off_text );
		}

		$percent_off_column = $this->get_option( 'percent_off_column' );

		if ( ! empty( $percent_off_column ) && taxonomy_exists( $percent_off_column ) ) {
			$args = array(
				'taxonomy'   => $percent_off_column,
				'hide_empty' => false,
				'fields'     => 'names'
			);

			$query = new WP_Term_Query( $args );

			$terms = $query->get_terms();

			if ( $this->array_has_value( $terms ) ) {
				if ( ! is_array( $percent_off_text ) ) {
					$percent_off_text = $terms;
				} else {
					$percent_off_text = array_merge( $percent_off_text, $terms );
				}
			}
		}

		if ( is_array( $percent_off_text ) ) {
			$percent_off_text = array_map( array( $this, 'sanitize_percent_off_text' ), $percent_off_text );
			$percent_off_text = array_unique( $percent_off_text );
			$percent_off_text = array_filter( $percent_off_text );
		}

		return $percent_off_text;
	}

	public function get_random_value( $arr, &$result_arr = null ) {
		$key = array_rand( $arr );

		$result = $arr[ $key ];

		if ( null != $result_arr ) {
			unset( $arr[ $key ] );
			$result_arr = $arr;
		}

		return $result;
	}

	/**
	 * Auto generate Coupons for specific Store.
	 *
	 * @param int $store_id Store Term ID.
	 * @param bool $new Is new Store or update exists Store.
	 *
	 * @param string $coupon_number The number of coupons will be generated.
	 *
	 * @return bool|WP_Error True if all coupons were generated. WP_Error when input data invalid.
	 */
	public function generate_coupons_for_store( $store_id, $new, $coupon_number = '' ) {
		if ( $this->is_positive_number( $store_id ) ) {
			$data_file = isset( $_POST['data_file'] ) ? $_POST['data_file'] : '';

			if ( empty( $data_file ) && ! $new ) {
				$data_file = get_term_meta( $store_id, 'data_file', true );
			}

			if ( empty( $data_file ) ) {
				$data_file = $this->get_option( 'data_file' );
			}

			if ( $this->is_positive_number( $data_file ) && $this->post_exists( $data_file ) ) {
				$store = get_term( $store_id, $this->coupon_store_taxonomy );

				if ( ! is_numeric( $coupon_number ) ) {
					$coupon_number = isset( $_POST['coupon_number'] ) ? $_POST['coupon_number'] : '';

					if ( ! is_numeric( $coupon_number ) ) {
						$coupon_number = $this->get_custom_coupons_number( $store_id );
					}
				}

				$file_desc = isset( $_POST['data_file_description'] ) ? $_POST['data_file_description'] : '';

				if ( empty( $file_desc ) && ! $new ) {
					$file_desc = get_term_meta( $store_id, 'data_file_description', true );
				}

				if ( empty( $file_desc ) ) {
					$file_desc = $this->get_option( 'data_file_description' );
				}

				// Get columns of data table
				$combinations = wp_get_object_terms( $data_file, $this->combination_taxonomy );

				if ( 1 != $this->get_option( 'separate_description_column' ) ) {
					$combinations_desc = wp_get_object_terms( $file_desc, $this->combination_taxonomy );
				} else {
					$combinations_desc = wp_get_object_terms( $file_desc, $this->combination_description_taxonomy );
				}

				$percent_off_text = $this->get_percent_off_text();

				for ( $i = 0; $i < $coupon_number; $i ++ ) {
					$combination = $this->get_random_value( $combinations );

					if ( $combination instanceof WP_Term ) {
						$title = $this->build_text_by_combination( $data_file, $combination->name, $store );

						if ( ! empty( $title ) ) {
							$description = '';

							if ( $this->is_positive_number( $file_desc ) && $this->post_exists( $file_desc ) ) {
								$combination = $this->get_random_value( $combinations_desc );

								if ( $combination instanceof WP_Term ) {
									$description = $this->build_text_by_combination( $file_desc, $combination->name, $store );
								}
							}

							if ( ! empty( $description ) && $this->array_has_value( $percent_off_text ) ) {
								foreach ( $percent_off_text as $off ) {
									if ( false !== strpos( $title, $off ) ) {
										/*
										$percent_off_position = get_term_meta( $store_id, 'percent_off_position', true );

										if ( empty( $percent_off_position ) ) {
											$percent_off_position = 'tail';
										}
										*/

										$rand = rand( 0, 1 );

										if ( 0 == $rand ) {
											$percent_off_position = 'head';
										} else {
											$percent_off_position = 'tail';
										}

										$off = sprintf( _x( 'Up to %s.', 'percent off', $this->textdomain ), $off );

										if ( 'head' == $percent_off_position ) {
											$description = $off . ' ' . $description;
										} else {
											$description .= ' ' . $off;
										}
									}
								}
							}

							$postdata = array(
								'post_type'    => $this->coupon_post_type,
								'post_title'   => $title,
								'post_content' => $description,
								'post_status'  => 'publish'
							);

							$post_id = wp_insert_post( $postdata );

							if ( $this->is_positive_number( $post_id ) ) {
								$store = get_term( $store_id, $this->coupon_store_taxonomy );

								if ( $store instanceof WP_Term ) {
									wp_set_post_terms( $post_id, array( $store->term_id ), $store->taxonomy );
								}

								update_post_meta( $post_id, $this->coupon_verified_meta_key, 0 );
								update_post_meta( $post_id, 'auto_added_coupon', 1 );
								update_post_meta( $post_id, '_wpc_coupon_type', 'sale' );
							}
						}
					} else {
						return new WP_Error( 'missing_combination', sprintf( __( 'Please set Column Combination for data file %s.', $this->textdomain ), get_the_title( $data_file ) ) );
					}
				}

				return true;
			} elseif ( ! $new ) {
				return new WP_Error( 'invalid_data_file', __( 'Please select a data file for auto generating coupons.', $this->textdomain ) );
			}
		} elseif ( $this->array_has_value( $store_id ) ) {
			foreach ( (array) $store_id as $id ) {
				$result = $this->generate_coupons_for_store( $id, $new );

				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}

			return true;
		}

		return new WP_Error( 'store_id_invalid', __( 'Please select store or add new one by filling the fields.', $this->textdomain ) );
	}

	public function admin_enqueue_scripts_action() {
		global $pagenow, $plugin_page, $post_type;

		$load = false;

		if ( ( 'post.php' == $pagenow || 'post-new.php' == $pagenow ) && $this->is_data_post_type( $post_type ) ) {
			$load = true;
			wp_enqueue_media();
		} elseif ( ( 'edit.php' == $pagenow || 'admin.php' == $pagenow ) && 'wp_import_coupon' == $plugin_page ) {
			$load = true;
			wp_enqueue_style( 'select2-style', $this->base_url . '/select2/css/select2.css' );
			wp_enqueue_script( 'select2', $this->base_url . '/select2/js/select2.js', array( 'jquery' ), false, true );
		} elseif ( 'term.php' == $pagenow ) {
			global $taxonomy;

			if ( $this->coupon_store_taxonomy == $taxonomy ) {
				$load = true;
			}
		} elseif ( 'edit.php' == $pagenow ) {
			$load = true;
		}

		if ( $load ) {
			wp_enqueue_script( 'wp-custom-coupons', $this->base_url . '/js/admin.js', array( 'jquery' ), false, true );

			$max = ini_get( 'max_execution_time' );

			if ( ! is_numeric( $max ) ) {
				$max = 60;
			}

			$max *= 1000;

			$l10n = array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'maxExecutionTime' => $max
			);

			wp_localize_script( 'wp-custom-coupons', 'wpCustomCoupons', $l10n );
		}
	}

	public function custom_admin_menu_action() {
		add_submenu_page( $this->option_name, __( 'Import Coupons', $this->textdomain ), __( 'Import', $this->textdomain ), 'manage_categories', 'wp_import_coupon', array(
			$this,
			'import_coupon_page_callback'
		) );

		$post_types = array(
			$this->data_post_type,
			$this->data_description_post_type
		);

		foreach ( $post_types as $type ) {
			remove_menu_page( 'edit.php?post_type=' . $type );

			$post_type = get_post_type_object( $type );

			if ( $post_type instanceof WP_Post_Type ) {
				add_submenu_page( $this->option_name, $post_type->label, $post_type->label, 'manage_options', 'edit.php?post_type=' . $type );
			}
		}
	}

	public function import_coupon_page_callback() {
		include $this->base_dir . '/admin/admin-setting-page-import-coupon.php';
	}

	public function init_action() {
		if ( 1 == $this->get_option( 'separate_description_table' ) ) {
			$args = array(
				'public'            => false,
				'labels'            => array(
					'name' => __( 'Title Data Table', $this->textdomain )
				),
				'show_ui'           => true,
				'supports'          => array( 'title' ),
				'show_in_nav_menus' => false,
				'show_in_admin_bar' => false
			);

			register_post_type( $this->data_post_type, $args );

			$args = array(
				'public'            => false,
				'labels'            => array(
					'name' => __( 'Description Data Table', $this->textdomain )
				),
				'show_ui'           => true,
				'supports'          => array( 'title' ),
				'show_in_nav_menus' => false,
				'show_in_admin_bar' => false
			);

			register_post_type( $this->data_description_post_type, $args );
		} else {
			$args = array(
				'public'            => false,
				'labels'            => array(
					'name' => __( 'Coupon Data Table', $this->textdomain )
				),
				'show_ui'           => true,
				'supports'          => array( 'title' ),
				'show_in_nav_menus' => false,
				'show_in_admin_bar' => false
			);

			register_post_type( $this->data_post_type, $args );
		}

		if ( 1 == $this->get_option( 'separate_description_column' ) && 1 == $this->get_option( 'separate_description_table' ) ) {
			$args = array(
				'public'  => false,
				'show_ui' => true,
				'labels'  => array(
					'name'          => __( 'Title Column Combinations', $this->textdomain ),
					'singular_name' => __( 'Title Column Combination', $this->textdomain ),
					'menu_name'     => __( 'Title Combinations', $this->textdomain )
				)
			);

			$args['show_admin_column'] = true;

			$args['sort'] = true;

			register_taxonomy( $this->combination_taxonomy, $this->data_post_type, $args );

			$args['labels']['name']          = __( 'Description Column Combinations', $this->textdomain );
			$args['labels']['singular_name'] = __( 'Description Column Combination', $this->textdomain );
			$args['labels']['menu_name']     = __( 'Description Combinations', $this->textdomain );

			register_taxonomy( $this->combination_description_taxonomy, $this->data_description_post_type, $args );

			$count = 0;

			foreach ( range( 'A', 'Z' ) as $char ) {
				$char = strtoupper( $char );

				$args = array(
					'public'  => false,
					'show_ui' => true,
					'labels'  => array(
						'name' => sprintf( __( 'Title Column %s', $this->textdomain ), $char )
					)
				);

				if ( 9 > $count ) {
					$args['show_admin_column'] = true;

					$args['sort'] = true;
				}

				register_taxonomy( $this->get_column_taxonomy_name( $char ), $this->data_post_type, $args );

				unset( $args['show_admin_column'], $args['sort'] );

				if ( 7 > $count ) {
					$args['show_admin_column'] = true;

					$args['sort'] = true;
				}

				$args['labels']['name'] = sprintf( __( 'Description Column %s', $this->textdomain ), $char );

				register_taxonomy( $this->get_column_description_taxonomy_name( $char ), $this->data_description_post_type, $args );

				$count ++;
			}
		} else {
			$args = array(
				'public'  => false,
				'show_ui' => true,
				'labels'  => array(
					'name'          => __( 'Column Combinations', $this->textdomain ),
					'singular_name' => __( 'Column Combination', $this->textdomain ),
					'menu_name'     => __( 'Combinations', $this->textdomain )
				)
			);

			$args['show_admin_column'] = true;

			$args['sort'] = true;

			register_taxonomy( $this->combination_taxonomy, $this->data_post_type, $args );

			$count = 0;

			foreach ( range( 'A', 'Z' ) as $char ) {
				$char = strtoupper( $char );

				$args = array(
					'public'  => false,
					'show_ui' => true,
					'labels'  => array(
						'name' => sprintf( __( 'Column %s', $this->textdomain ), $char )
					)
				);

				if ( 10 > $count ) {
					$args['show_admin_column'] = true;

					$args['sort'] = true;
				}

				register_taxonomy( $this->get_column_taxonomy_name( $char ), $this->data_post_type, $args );

				$count ++;
			}
		}

		$this->fix_yoast_seo_duplicate();
	}

	private function fix_yoast_seo_duplicate() {
		remove_action( 'wp_head', 'wp_coupon_open_graph', 3 );

		if ( is_plugin_active( 'wordpress-seo/wp-seo.php' ) ) {
			remove_action( 'wp_enqueue_scripts', 'wp_coupon_remove_YOAT_SEO_twitter_og' );
		} else {
			add_action( 'wp_head', array( $this, 'open_graph' ) );
		}
	}

	public function open_graph() {
		$data = array();

		if ( is_tax( $this->coupon_store_taxonomy ) ) {
			$term = get_queried_object();
			wpcoupon_setup_store( $term );

			$title = wpcoupon_store()->get_single_store_name();

			$title = $this->get_store_meta_title( $term, $title );

			$data['og:title'] = wp_strip_all_tags( $title, true );

			$desc = wpcoupon_store()->get_content( false );

			$desc = wp_trim_words( $desc, 30, '...' );

			$desc = $this->get_store_meta_description( $term, $desc );

			$data['og:description'] = wp_strip_all_tags( $desc, true );

			$image = wpcoupon_store()->get_thumbnail( 'full', true );

			if ( $image ) {
				$data['og:image'] = $image;
			}

			$data['og:type'] = 'article';
			$url             = wpcoupon_store()->get_url();

			if ( get_query_var( 'coupon_id' ) > 0 ) {
				$post = get_post( get_query_var( 'coupon_id' ) );

				if ( $post->post_type == 'coupon' ) {
					wpcoupon_coupon( $post );

					$url                    = wpcoupon_coupon()->get_href();
					$data['og:type']        = 'article';
					$data['og:url']         = $url;
					$data['og:title']       = $post->post_title;
					$data['og:description'] = get_the_excerpt( $post );
					$image                  = wpcoupon_coupon()->get_thumb( 'full', true, true );

					if ( $image ) {
						$data['og:image'] = $image;
					}

					wp_reset_postdata();
				}
			}

			$data['og:url'] = $url;
		} else if ( is_singular( $this->coupon_post_type ) ) {
			global $post;
			wp_reset_query();
			wpcoupon_coupon( $post );
			$url                    = wpcoupon_coupon()->get_href();
			$data['og:url']         = $url;
			$data['og:type']        = 'article';
			$data['og:title']       = $post->post_title;
			$data['og:description'] = get_the_excerpt( $post );
			$image                  = wpcoupon_coupon()->get_thumb( 'full', true, true );

			if ( $image ) {
				$data['og:image'] = $image;
			}

			wp_reset_postdata();
		}

		foreach ( $data as $k => $v ) {
			echo '<meta property="' . esc_attr( $k ) . '" content="' . esc_attr( $v ) . '" />' . "\n";
		}

		$twitter_keys = array(
			'twitter:title'       => 'og:title',
			'twitter:url'         => 'og:url',
			'twitter:description' => 'og:description',
			'twitter:image'       => 'og:image',
		);

		if ( ! empty ( $data ) ) {
			if ( isset( $data['og:image'] ) && $data['og:image'] ) {
				echo "\n<meta name=\"twitter:card\" content=\"summary_large_image\" />\n";
			}

			foreach ( $twitter_keys as $k => $id ) {
				if ( isset ( $data[ $id ] ) ) {
					echo "<meta name=\"{$k}\" content=\"" . esc_attr( $data[ $id ] ) . "\" />\n";
				}
			}
		}

		$desc = isset( $data['og:description'] ) ? $data['og:description'] : '';

		if ( empty( $desc ) ) {
			if ( is_home() || is_front_page() ) {
				$desc = get_bloginfo( 'description' );
			} elseif ( is_single() || is_page() || is_singular() ) {
				$desc = get_the_excerpt( get_the_ID() );

				if ( empty( $desc ) ) {
					$obj = get_post( get_the_ID() );

					if ( $obj instanceof WP_Post ) {
						$desc = $obj->post_content;
					}
				}
			} elseif ( is_tag() || is_category() || is_tax() ) {
				$obj = get_queried_object();

				if ( $obj instanceof WP_Term ) {
					$desc = $obj->description;
				}
			}
		}

		if ( ! empty( $desc ) ) {
			$desc = wp_strip_all_tags( $desc, true );
			$desc = wp_trim_words( $desc, 50, '...' );
			echo '<meta name="description" content="' . $desc . '" />' . PHP_EOL;
		}
	}

	public function get_store_meta_description( $term, $description ) {
		if ( $term instanceof WP_Term ) {
			$description = get_term_meta( $term->term_id, 'description_meta', true );

			if ( empty( $description ) ) {
				$description = sprintf( __( 'Get the Latest %d %s Coupons, Promo Codes & Vouchers %s.', $this->textdomain ), $term->count, $term->name, date( 'F Y' ) );
			} else {
				$description = $this->sanitize_store_title_and_description_meta( $term, $description );
			}
		}

		return $description;
	}

	public function sanitize_store_title_and_description_meta( $term, $text ) {
		if ( $term instanceof WP_Term ) {
			$search = array(
				'%store_name%',
				'%store_count%'
			);

			$replace = array(
				$term->name,
				$term->count
			);

			$text = str_replace( $search, $replace, $text );

			if ( false !== ( $pos = strpos( $text, '%date:' ) ) ) {
				$sub = substr( $text, 0, $pos );

				$format = substr( $text, $pos );
				$format = ltrim( $format, '%' );

				if ( false !== ( $ps = strpos( $format, '%' ) ) ) {
					$format = substr( $format, 0, $ps );

					$date_format = $format;
					$date_format = str_replace( 'date:', '', $date_format );
					$date_format = str_replace( 'DATE:', '', $date_format );
					$date_format = str_replace( '%', '', $date_format );

					$format = '%' . $format . '%';

					$now = current_time( 'timestamp' );

					$date = date( $date_format, $now );

					$text = str_replace( $format, $date, $text );
				}
			}
		}

		return $text;
	}

	public function get_store_meta_title( $term, $title = '' ) {
		if ( $term instanceof WP_Term ) {
			$title = get_term_meta( $term->term_id, 'title_meta', true );

			if ( empty( $title ) ) {
				$title = sprintf( __( '%s Coupons & Promo Codes %s', $this->textdomain ), $term->name, date( 'F Y' ) );
			} else {
				$title = $this->sanitize_store_title_and_description_meta( $term, $title );
			}
		}

		return $title;
	}

	private function update_wpseo_description_meta( $desc ) {
		if ( is_tax( $this->coupon_store_taxonomy ) ) {
			$obj = get_queried_object();

			$desc = $this->get_store_meta_description( $obj, $desc );
		}

		return $desc;
	}

	/**
	 * Update twitter:title
	 *
	 * @param $title
	 *
	 * @return string
	 */
	public function wpseo_twitter_title_filter( $title ) {
		return $this->wpseo_opengraph_title_filter( $title );
	}

	/**
	 * Update twitter:description
	 *
	 * @param $desc
	 *
	 * @return string
	 */
	public function wpseo_twitter_description_filter( $desc ) {
		$desc = $this->update_wpseo_description_meta( $desc );

		return $desc;
	}

	/**
	 * Update og:description
	 *
	 * @param $desc
	 *
	 * @return string
	 */
	public function wpseo_opengraph_desc_filter( $desc ) {
		$desc = $this->update_wpseo_description_meta( $desc );

		return $desc;
	}

	/**
	 * Update og:title
	 *
	 * @param $title
	 *
	 * @return string
	 */
	public function wpseo_opengraph_title_filter( $title ) {
		if ( is_tax( $this->coupon_store_taxonomy ) ) {
			$obj = get_queried_object();

			$title = $this->get_store_meta_title( $obj, $title );
		}

		return $title;
	}

	/**
	 * Update document title.
	 *
	 * @param $title
	 *
	 * @return string
	 */
	public function pre_get_document_title_filter( $title ) {
		return $this->wpseo_opengraph_title_filter( $title );
	}
}

function WP_Custom_Coupons() {
	return WP_Custom_Coupons::get_instance();
}

add_action( 'plugins_loaded', function () {
	WP_Custom_Coupons();
} );

function wp_custom_coupons_activation() {
	if ( ! wp_next_scheduled( 'wp_custom_coupons_cron' ) ) {
		wp_schedule_event( time(), 'hourly', 'wp_custom_coupons_cron' );
	}
}

register_activation_hook( __FILE__, 'wp_custom_coupons_activation' );

function wp_custom_coupons_cron_run() {
	do_action( 'wp_custom_coupons_cron_run' );
}

add_action( 'wp_custom_coupons_cron', 'wp_custom_coupons_cron_run' );

function wp_custom_coupons_deactivation() {
	wp_clear_scheduled_hook( 'wp_custom_coupons_cron' );
}

register_deactivation_hook( __FILE__, 'wp_custom_coupons_deactivation' );