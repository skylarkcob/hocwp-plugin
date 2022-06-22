<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

trait Woo_Checkout_Images_Custom {
	public function convert_billing_images( $images, $output = ARRAY_A ) {
		if ( ( ARRAY_A == $output || ARRAY_N == $output ) && ! is_array( $images ) ) {
			$images = explode( ',', $images );
			$images = array_map( 'trim', $images );
			$images = array_filter( $images );
			$images = array_unique( $images );
		} elseif ( is_array( $images ) ) {
			$images = join( ',', $images );
		}

		return $images;
	}

	public function button_remove_images( $post_id ) {
		?>
		<button type="button" class="button button-danger remove-all-images"
		        data-id="<?php echo esc_attr( $post_id ); ?>"><?php _e( 'Remove all', $this->textdomain ); ?></button>
		<?php
	}
}