<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! trait_exists( 'Woo_Checkout_Images_Admin_Core' ) ) {
	// Load core admin functions
	require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/core/trait-admin.php';
}

if ( ! trait_exists( 'Woo_Checkout_Images_Backend_Custom' ) ) {
	// Load custom admin functions
	require_once dirname( __FILE__ ) . '/trait-backend.php';
}

class Woo_Checkout_Images_Admin extends Woo_Checkout_Images {
	use Woo_Checkout_Images_Admin_Core, Woo_Checkout_Images_Backend_Custom;

	// Default plugin variable: Plugin single instance.
	protected static $admin_instance;

	/*
	 * Default plugin function: Check single instance.
	 */
	public static function get_instance() {
		if ( ! ( self::$admin_instance instanceof self ) ) {
			self::$admin_instance = new self();
		}

		return self::$admin_instance;
	}

	/*
	 * Default plugin function: Plugin construct.
	 */
	public function __construct() {
		parent::__construct();

		$version = phpversion();

		if ( version_compare( $this->require_php_version, $version, '>' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notices_require_php_version' ) );

			return;
		}

		add_action( 'admin_init', array( $this, 'admin_init_action' ), 20 );
		add_filter( 'plugin_action_links_' . $this->base_name, array( $this, 'action_links_filter' ), 20 );
		add_action( 'admin_menu', array( $this, 'admin_menu_action' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );

		if ( $this->array_has_value( $this->required_plugins ) ) {
			add_action( 'admin_notices', array( $this, 'required_plugins_notices' ) );
		}

		add_action( 'admin_footer', array( $this, 'admin_footer' ) );
		add_action( 'admin_footer', array( $this, 'created_by_log' ) );

		if ( $this->array_has_value( $this->one_term_taxonomies ) ) {
			add_action( 'save_post', array( $this, 'one_term_taxonomy_action' ), 99 );
			add_action( 'admin_notices', array( $this, 'one_term_taxonimes_notices' ) );
		}

		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu_action' ), 99 );

		$this->main_load();

		if ( method_exists( $this, 'run_custom_ajax_callback' ) ) {
			add_action( 'wp_ajax_' . $this->get_option_name(), array( $this, 'run_custom_ajax_callback' ) );
			add_action( 'wp_ajax_nopriv_' . $this->get_option_name(), array( $this, 'run_custom_ajax_callback' ) );
		}

		if ( method_exists( $this, 'sanitize_setting_filter' ) ) {
			add_filter( $this->textdomain . '_settings_save_data', array( $this, 'sanitize_setting_filter' ) );
		}
	}

	// Custom functions should be declared below this line.

	public function main_load() {
		add_action( 'admin_init', array( $this, 'custom_admin_init_action' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes_action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'custom_admin_enqueue_scripts' ) );
		add_action( 'pre_get_posts', array( $this, 'custom_pre_get_posts_action' ) );
		add_action( 'restrict_manage_posts', array( $this, 'custom_restrict_manage_posts_action' ) );
		add_filter( 'posts_where', array( $this, 'custom_posts_where_filter' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'custom_manage_posts_columns_filter' ) );
		add_filter( 'manage_media_columns', array( $this, 'custom_manage_media_columns_filter' ) );
		add_action( 'manage_media_custom_column', array( $this, 'custom_manage_media_custom_column_action' ), 10, 2 );
		add_action( 'manage_shop_order_posts_custom_column', array(
			$this,
			'custom_manage_shop_order_posts_custom_column_action'
		), 10, 2 );
	}

	public function custom_manage_media_custom_column_action( $column, $post_id ) {
		if ( 'shop_order' == $column ) {
			$order = get_post_meta( $post_id, 'use_order_id', true );

			if ( is_numeric( $order ) ) {
				?>
				<a href="<?php echo esc_url( get_edit_post_link( $order ) ); ?>"><?php echo $this->get_woocommerce_order_title( $order ); ?></a>
				<?php
			}
		}
	}

	public function custom_manage_media_columns_filter( $columns ) {
		$columns['shop_order'] = __( 'Shop order', $this->textdomain );

		return $columns;
	}

	public function custom_manage_shop_order_posts_custom_column_action( $column, $post_id ) {
		if ( 'view_images' == $column ) {
			$images = get_post_meta( $post_id, '_billing_images', true );

			if ( ! empty( $images ) ) {
				?>
				<a href="<?php echo esc_url( admin_url( 'upload.php?mode=list&attachment-filter&m=0&order=' . $post_id ) ); ?>"
				   title="<?php esc_attr_e( 'View customer product images', $this->textdomain ); ?>"
				   class="button"><?php _e( 'View', $this->textdomain ); ?></a>
				<?php
				$this->button_remove_images( $post_id );
			}
		}
	}

	public function custom_manage_posts_columns_filter( $columns ) {
		$columns['view_images'] = __( 'View images', $this->textdomain );

		return $columns;
	}

	public function custom_posts_where_filter( $where, $query ) {
		global $pagenow;

		if ( ( 'upload.php' == $pagenow || 'admin-ajax.php' == $pagenow ) && $query instanceof WP_Query ) {
			$post_type = $query->get( 'post_type' );

			if ( 'attachment' == $post_type ) {
				$order = $_GET['order'] ?? '';

				if ( 'any' == $order || is_numeric( $order ) ) {
					global $wpdb;

					$where .= ' AND ' . $wpdb->posts . '.post_name LIKE \'' . esc_sql( $wpdb->esc_like( $this->get_textdomain() ) ) . '%\'';
				}
			}
		}

		return $where;
	}

	public function custom_restrict_manage_posts_action( $post_type ) {
		if ( 'attachment' == $post_type ) {
			$args = array(
				'post_type'      => 'shop_order',
				'post_status'    => 'any',
				'posts_per_page' => 500,
				'fields'         => 'ids'
			);

			$query = new WP_Query( $args );

			if ( $query->have_posts() ) {
				$order = $_GET['order'] ?? '';
				?>
				<label for="filter-by-order"
				       class="screen-reader-text"><?php _e( 'Filter by order', $this->textdomain ); ?></label>
				<select name="order" id="filter-by-order">
					<option
						value=""<?php selected( $order, '' ); ?>><?php _e( '-- Choose shop order --', $this->textdomain ); ?></option>
					<option
						value="any"<?php selected( $order, 'any' ); ?>><?php _e( 'All orders', $this->textdomain ); ?></option>
					<?php
					foreach ( $query->get_posts() as $id ) {
						?>
						<option
							value="<?php echo esc_attr( $id ); ?>"<?php selected( $order, $id ); ?>><?php echo $this->get_woocommerce_order_title( $id ); ?></option>
						<?php
					}
					?>
				</select>
				<?php
			}
		}
	}

	public function custom_pre_get_posts_action( $query ) {
		global $pagenow;

		if ( ( 'upload.php' == $pagenow || 'admin-ajax.php' == $pagenow ) && $query instanceof WP_Query ) {
			$post_type = $query->get( 'post_type' );

			if ( 'attachment' == $post_type ) {
				$s = $query->get( 's' );

				if ( '#' == substr( $s, 0, 1 ) ) {
					$s = ltrim( $s, '#' );

					$query->set( 'meta_query', array(
						'relation' => 'and',
						array(
							'key'     => 'use_order_id',
							'value'   => $s,
							'compare' => '='
						)
					) );

					$query->set( 's', '' );
				}

				$order = $_GET['order'] ?? '';

				if ( is_numeric( $order ) ) {
					$query->set( 'meta_query', array(
						'relation' => 'and',
						array(
							'key'     => 'use_order_id',
							'value'   => $order,
							'compare' => '='
						)
					) );
				}
			}
		}
	}

	public function add_meta_boxes_action( $post_type ) {
		if ( 'shop_order' == $post_type ) {
			add_meta_box( 'billing_images', __( 'Customer Product Sizes', $this->textdomain ), array(
				$this,
				'meta_box_callback'
			), $post_type );
		}
	}

	public function meta_box_callback( $post ) {
		$images = get_post_meta( $post->ID, '_billing_images', true );

		if ( ! empty( $images ) ) {
			$images = $this->convert_billing_images( $images );
			?>
			<div class="billing-images <?php echo sanitize_html_class( $this->textdomain ); ?>">
				<input name="_billing_images" type="hidden"
				       value="<?php echo esc_attr( $this->convert_billing_images( $images, 'string' ) ); ?>">

				<div class="preview">
					<?php
					foreach ( $images as $id ) {
						if ( $this->is_media_file_exists( $id ) ) {
							?>
							<a target="_blank"
							   href="<?php echo esc_url( wp_get_attachment_image_url( $id, 'full' ) ); ?>"
							   title="<?php echo get_the_title( $id ); ?>">
								<?php echo wp_get_attachment_image( $id, 'full', false, array( 'data-id' => $id ) ); ?>
							</a>
							<?php
						}
					}
					?>
				</div>
			</div>
			<?php
			$this->button_remove_images( $post->ID );
		} else {
			echo wpautop( __( 'No images found!', $this->textdomain ) );
		}
	}

	public function run_custom_ajax_callback() {
		$data = array(
			'message' => __( 'There was an error caused, please try again.', $this->textdomain )
		);

		$do_action = isset( $_REQUEST['do_action'] ) ? $_REQUEST['do_action'] : '';

		switch ( $do_action ) {
			case 'upload_images':
				$name_prefix = $this->textdomain;
				$name_prefix .= '-';
				$name_prefix .= date( 'Y-m-d' );
				$name_prefix .= '-';

				foreach ( $_FILES as $file ) {
					$up = $this->upload_file( $name_prefix . $file['name'], $file['tmp_name'] );

					if ( is_array( $up ) && isset( $up['id'] ) ) {
						$data['image'] = $up;
					}
				}

				wp_send_json_success( $data );

				break;
			case 'delete_image':
				$id = $_GET['id'] ?? '';

				$post_id = $_GET['post_id'] ?? '';

				if ( is_numeric( $id ) ) {
					wp_delete_attachment( $id, true );
				}

				if ( $this->is_positive_number( $post_id ) ) {
					$images = $_GET['images'] ?? '';

					// Remove all order images and in media too.
					if ( empty( $id ) && empty( $images ) ) {
						$ids = get_post_meta( $post_id, '_billing_images', true );

						$ids = $this->convert_billing_images( $ids );

						foreach ( $ids as $image_id ) {
							wp_delete_attachment( $image_id, true );
						}
					}

					if ( ! empty( $images ) ) {
						$images = str_replace( ',' . $id, '', $images );
						$images = str_replace( $id . ',', '', $images );

						update_post_meta( $post_id, '_billing_images', $images );
					} else {
						delete_post_meta( $post_id, '_billing_images' );
					}

					$data['images'] = $images;

					wp_send_json_success( $data );
				}

				break;
			default:
				break;
		}

		wp_send_json_error( $data );
	}

	/**
	 * Default plugin function: Add setting fields on admin_init action.
	 */
	public function custom_admin_init_action() {
		$args = array(
			'type'        => 'number',
			'description' => __( 'The max number of images user can upload.', $this->textdomain ),
			'class'       => 'small-text'
		);

		$this->add_settings_field( 'max_image_count', __( 'Max Image Count', $this->textdomain ), 'admin_setting_field_input', 'default', $args );

		$args['description'] = sprintf( __( 'The max image size (KB). The default size (%s) will be used if leave empty.', $this->textdomain ), size_format( $this->get_max_upload_size() * MB_IN_BYTES ) );
		$args['class']       = 'medium-text';

		$this->add_settings_field( 'max_image_size', __( 'Max Image Size', $this->textdomain ), 'admin_setting_field_input', 'default', $args );

		$args = array(
			'class' => 'widefat'
		);

		$this->add_settings_field( 'upload_description', __( 'Upload Description', $this->textdomain ), 'admin_setting_field_input', 'default', $args );
	}

	/**
	 * Sanitize option input data.
	 *
	 * @param array $input The data in $_POST variable.
	 *
	 * @return array The sanitized data.
	 */
	public function sanitize_setting_filter( $input ) {
		return $input;
	}

	public function custom_admin_enqueue_scripts() {
		global $pagenow;

		if ( 'post.php' == $pagenow || 'post-new.php' == $pagenow || 'upload.php' == $pagenow || 'edit.php' == $pagenow ) {
			wp_enqueue_style( $this->textdomain . '-frontend-style', $this->custom_url . '/css/frontend.css' );
			wp_enqueue_style( $this->textdomain . '-style', $this->custom_url . '/css/backend.css' );

			wp_enqueue_script( $this->textdomain, $this->custom_url . '/js/backend.js', array( 'jquery' ), false, true );

			$l10n = array(
				'textDomain' => $this->textdomain,
				'ajaxUrl'    => $this->get_ajax_url(),
				'optionName' => $this->get_option_name(),
				'text'       => array(
					'confirm_delete'     => __( 'Are you sure you want to delete?', $this->textdomain ),
					'all_images_deleted' => __( 'All images have been deleted successfully.', $this->textdomain )
				)
			);

			wp_localize_script( $this->textdomain, 'wcCI', $l10n );
		}
	}
}

function Woo_Checkout_Images_Admin() {
	return Woo_Checkout_Images_Admin::get_instance();
}

Woo_Checkout_Images_Admin();