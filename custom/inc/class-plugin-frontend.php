<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! trait_exists( 'Woo_Checkout_Images_Frontend_Custom' ) ) {
	// Load custom front-end functions
	require_once dirname( __FILE__ ) . '/trait-frontend.php';
}

class Woo_Checkout_Images_Frontend extends Woo_Checkout_Images {
	use Woo_Checkout_Images_Frontend_Custom;

	// Default plugin variable: Plugin single instance.
	protected static $frontend_instance;

	/*
	 * Default plugin function: Check single instance.
	 */
	public static function get_instance() {
		if ( ! ( self::$frontend_instance instanceof self ) ) {
			self::$frontend_instance = new self();
		}

		return self::$frontend_instance;
	}

	/*
	 * Default plugin function: Plugin construct.
	 */
	public function __construct() {
		parent::__construct();
		$this->main_load();
	}

	// Custom functions should be declared below this line.

	public function main_load() {
		add_action( 'wp_enqueue_scripts', array( $this, 'custom_wp_enqueue_scripts_action' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_fields_filter' ), 999 );
		add_filter( 'woocommerce_form_field_file', array( $this, 'form_field_file_filter' ), 999, 4 );
		add_action( 'wp', array( $this, 'wp_action' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array(
			$this,
			'woocommerce_checkout_update_order_meta_action'
		), 10, 2 );
	}

	public function woocommerce_checkout_update_order_meta_action( $order_id, $data ) {
		$billing_images = $data['billing_images'] ?? '';
		$billing_images = $this->convert_billing_images( $billing_images );

		if ( $this->array_has_value( $billing_images ) ) {
			foreach ( $billing_images as $id ) {
				wp_update_post( array( 'ID' => $id, 'post_parent' => $order_id ) );
				update_post_meta( $id, 'use_order_id', $order_id );
				update_post_meta( $id, '_wp_attachment_image_alt', $this->get_woocommerce_order_title( $order_id ) );
			}
		}
	}

	public function wp_action() {
		if ( isset( $_POST['payment_method'] ) ) {

		}
	}

	public function checkout_fields_filter( $fields ) {
		$item = array(
			'label'             => __( 'Your Product Sizes', $this->textdomain ),
			'required'          => true,
			'type'              => 'file',
			'class'             => array( 'form-row-wide' ),
			'placeholder'       => '',
			'options'           => '',
			'priority'          => 80,
			'multiple'          => 1,
			'custom_attributes' => array(
				'accept' => 'image/*'
			)
		);

		$fields['billing']['billing_images'] = $item;

		//$fields['shipping']['shipping_images'] = $item;

		return $fields;
	}

	public function form_field_file_filter( $field, $key, $args, $value ) {
		if ( empty( $field ) ) {
			$class = $args['class'] ?? '';

			if ( is_array( $class ) ) {
				$class = join( ' ', $class );
			}

			$class .= ' ' . sanitize_html_class( $this->textdomain );

			$label = $args['label'];

			if ( $args['required'] ) {
				$label .= '&nbsp;<abbr class="required" title="required">*</abbr>';
			}

			$atts = $args['custom_attributes'] ?? '';

			if ( ! empty( $atts ) ) {
				$atts = $this->convert_array_attributes_to_string( $atts );
				$atts = ' ' . $atts;
			}

			$multiple = $args['multiple'] ?? 0;

			if ( $multiple ) {
				$atts .= ' multiple';
			}

			ob_start();
			?>
			<p class="form-row <?php echo esc_attr( $class ); ?>" id="<?php echo esc_attr( $key ); ?>_field"
			   data-priority="<?php echo esc_attr( $args['priority'] ); ?>">
				<label for="<?php echo esc_attr( $key ); ?>" class=""><?php echo $label; ?></label>
				<span class="woocommerce-input-wrapper">
					<input type="hidden" class="file-ids" name="<?php echo esc_attr( $key ); ?>">
					<input type="file" class="input-text " name="<?php echo esc_attr( $key ); ?>_file"
					       id="<?php echo esc_attr( $key ); ?>" placeholder="" value=""
					       autocomplete="off"<?php echo $atts; ?>>
					<?php
					$desc = $this->get_option( 'upload_description' );

					if ( ! empty( $desc ) ) {
						$desc = strip_tags( $desc, '<a><span><strong>' );
						echo '<span class="desc">' . $desc . '</span>';
					}
					?>
					<span class="preview"></span>
					<span class="messages"></span>
				</span>
			</p>
			<?php
			$field = ob_get_clean();
		}

		return $field;
	}

	/*
	 * Default plugin function: Load styles and scripts on frontend.
	 */
	public function custom_wp_enqueue_scripts_action() {
		wp_enqueue_style( $this->textdomain . '-style', $this->custom_url . '/css/frontend.css' );
		wp_enqueue_script( $this->textdomain, $this->custom_url . '/js/frontend.js', array( 'jquery' ), false, true );

		$count = $this->get_option( 'max_image_count' );
		$size  = $this->get_option( 'max_image_size' );

		$l10n = array(
			'textDomain'      => $this->textdomain,
			'ajaxUrl'         => $this->get_ajax_url(),
			'optionName'      => $this->get_option_name(),
			'max_image_count' => $count,
			'max_image_size'  => $size / 1024,
			'text'            => array(
				'max_image_count' => sprintf( __( 'You cannot upload more than %s images.', $this->textdomain ), $count ),
				'max_image_size'  => sprintf( __( 'You cannot upload image size larger than %s.', $this->textdomain ), size_format( ( $size / 1024 ) * MB_IN_BYTES ) )
			)
		);

		wp_localize_script( $this->textdomain, 'wcCI', $l10n );
	}
}

function Woo_Checkout_Images_Frontend() {
	return Woo_Checkout_Images_Frontend::get_instance();
}

Woo_Checkout_Images_Frontend();