<?php
/*
Plugin Name: JMB Gift Pack Woocommerce
Plugin URI: http://wordpress.org/plugins/
Description: This is Gift Card plugin
Author: Gurjit Singh
Version: 1.0
Author URI: http://jmbweb.com/
*/
if ( ! defined( 'ABSPATH' ) ) {
    return;
}


/**
 * JMB_WC_Product_Gift_Pack class.
 */
class JMB_WC_Product_Gift_Pack {
		
	/**
	 * @access public
	 * @return void
	 */
	public function __construct() {
		$default_message                 = sprintf( __( 'Gift Pack this item for %s?', 'woocommerce-product-gift-wrap' ), '{price}' ). ' {checkbox}';
		$this->gift_wrap_enabled         = get_option( 'product_gift_wrap_enabled' ) == 'yes' ? true : false;
		$this->gift_wrap_cost            = get_option( 'product_gift_wrap_cost', 0 );
		$this->product_gift_wrap_message = get_option( 'product_gift_wrap_message' );

		if ( ! $this->product_gift_wrap_message ) {
			$this->product_gift_wrap_message = $default_message;
		}

		add_option( 'product_gift_wrap_enabled', 'no' );
		add_option( 'product_gift_wrap_cost', '0' );
		add_option( 'product_gift_wrap_message', $default_message );

		// Init settings
		$this->settings = array(
			array(
				'name' 		=> __( 'Gift Packing Enabled by Default?', 'woocommerce-product-gift-wrap' ),
				'desc' 		=> __( 'Enable this to allow gift packing for products by default.', 'woocommerce-product-gift-wrap' ),
				'id' 		=> 'product_gift_wrap_enabled',
				'type' 		=> 'checkbox',
			),
			array(
				'name' 		=> __( 'Default Gift Pack Cost', 'woocommerce-product-gift-wrap' ),
				'desc' 		=> __( 'The cost of gift wrap unless overridden per-product.', 'woocommerce-product-gift-wrap' ),
				'id' 		=> 'product_gift_wrap_cost',
				'type' 		=> 'text',
				'desc_tip'  => true
			),
			array(
				'name' 		=> __( 'Gift Pack Message', 'woocommerce-product-gift-wrap' ),
				'id' 		=> 'product_gift_wrap_message',
				'desc' 		=> __( 'Note: <code>{checkbox}</code> will be replaced with a checkbox and <code>{price}</code> will be replaced with the gift wrap cost.', 'woocommerce-product-gift-wrap' ),
				'type' 		=> 'text',
				'desc_tip'  => __( 'The checkbox and label shown to the user on the frontend.', 'woocommerce-product-gift-wrap' )
			),
		);

		// Display on the front end
		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'gift_option_html' ), 10 );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'display_meta_cart_item_name'), 10, 3 );

		// Filters for cart actions
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'get_cart_item_from_session' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'get_item_data' ), 10, 2 );
		add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 10, 1 );
		add_action( 'woocommerce_add_order_item_meta', array( $this, 'add_order_item_meta' ), 999, 2 );

		// Write Panels
		add_action( 'woocommerce_product_options_pricing', array( $this, 'write_panel' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'write_panel_save' ) );

		// Admin
		add_action( 'woocommerce_settings_general_options_end', array( $this, 'admin_settings' ) );
		add_action( 'woocommerce_update_options_general', array( $this, 'save_admin_settings' ) );
	}

	/**
	 * Show the Gift Checkbox on the frontend
	 *
	 * @access public
	 * @return void
	 */
	public function gift_option_html() {
		global $post;

		$is_wrappable = get_post_meta( $post->ID, '_is_gift_wrappable', true );

// 		if ( $is_wrappable == '' && $this->gift_wrap_enabled ) {
// 			$is_wrappable = 'yes';
// 		}

		if ( $is_wrappable == 'yes' ) {
            
			$current_value = $_REQUEST['gift_wrap'];
            if($current_value == ''){
                $current_value = 'no';
            }

			$cost = get_post_meta( $post->ID, '_gift_wrap_cost', true );

			if ( $cost == '' ) {
				$cost = $this->gift_wrap_cost;
			}

			$price_text = $cost > 0 ? wc_price( $cost ) : __( 'free', 'woocommerce-product-gift-wrap' );
			$checkbox   = '<span class="gift-wrap-options"><span><input type="radio" name="gift_wrap" value="no" ' . checked( $current_value, 'no', false ) . ' />No</span>';
			$checkbox  .= '<span><input type="radio" name="gift_wrap" value="yes" ' . checked( $current_value, 'yes', false ) . ' />Yes</span></span>';
			
			ob_start();
            ?>
            <p class="gift-wrapping" style="clear:both; padding-top: .5em;">
            	<label><?php echo str_replace( array( '{price}', '{checkbox}' ), array( $price_text, $checkbox ), wp_kses_post( $this->product_gift_wrap_message ) ); ?></label>
            </p>
            <?php
            echo ob_get_clean();
		}
	}

	/**
	 * When added to cart, save any gift data
	 *
	 * @access public
	 * @param mixed $cart_item_meta
	 * @param mixed $product_id
	 * @return void
	 */
	public function add_cart_item_data( $cart_item_meta, $product_id ) {
		$is_wrappable = get_post_meta( $product_id, '_is_gift_wrappable', true );

		if ( $is_wrappable == '' && $this->gift_wrap_enabled ) {
			$is_wrappable = 'yes';
		}

		if ( $_POST['gift_wrap'] == 'yes' && $is_wrappable == 'yes' ) {
			$cart_item_meta['gift_wrap'] = true;
		}

		return $cart_item_meta;
	}

	/**
	 * Get the gift data from the session on page load
	 *
	 * @access public
	 * @param mixed $cart_item
	 * @param mixed $values
	 * @return void
	 */
	public function get_cart_item_from_session( $cart_item, $values ) {

		if(isset($values['gift_wrap'])){
    		if ( $values['gift_wrap'] == 'yes' ) {
    			$cart_item['gift_wrap'] = true;
    
    			$cost = get_post_meta( $cart_item['data']->id, '_gift_wrap_cost', true );
    
    			if ( $cost == '' ) {
    				$cost = $this->gift_wrap_cost;
    			}
    
    			$cart_item['data']->adjust_price( $cost );
    		}
		}
		return $cart_item;
	}

	/**
	 * Display gift data if present in the cart
	 *
	 * @access public
	 * @param mixed $other_data
	 * @param mixed $cart_item
	 * @return void
	 */
	public function get_item_data( $item_data, $cart_item ) {
	   // echo "<pre>";
	   // print_r($cart_item);
    //     echo "</pre>";
        $gift_wrap = get_post_meta($cart_item['product_id'], '_gift_wrap_cost', true);
		if ( isset($values['gift_wrap']) && $cart_item['gift_wrap'] == 'yes' )
			$item_data[] = array(
				'name'    => __( 'Gift Packed', 'woocommerce-product-gift-wrap' ),
				'value'   => __( 'Yes', 'woocommerce-product-gift-wrap' ),
				'display' => __( 'Yes', 'woocommerce-product-gift-wrap' ),
			);

		return $item_data;
	}

	/**
	 * Adjust price after adding to cart
	 *
	 * @access public
	 * @param mixed $cart_item
	 * @return void
	 */
	public function add_cart_item( $cart_item ) {
		if ( $cart_item['gift_wrap'] == 'yes' ) {

			$cost = get_post_meta( $cart_item['data']->id, '_gift_wrap_cost', true );

			if ( $cost == '' ) {
				$cost = $this->gift_wrap_cost;
			}

			$cart_item['data']->adjust_price( $cost );
		}

		return $cart_item;
	}

	/**
	 * After ordering, add the data to the order line items.
	 *
	 * @access public
	 * @param mixed $item_id
	 * @param mixed $values
	 * @return void
	 */
	public function add_order_item_meta( $item_id, $cart_item ) {
		if ( $cart_item['gift_wrap'] == 'yes' ) {
		    $gift_wrap = get_post_meta($cart_item['product_id'], '_gift_wrap_cost', true);
			woocommerce_add_order_item_meta( $item_id, __( 'Gift Pack Cost', 'woocommerce-product-gift-wrap' ), __( woocommerce_price($gift_wrap), 'woocommerce-product-gift-wrap' ) );
		}
	}

	function display_meta_cart_item_name( $product_name, $cart_item, $cart_item_key ) {
		$product = $cart_item['data']; // Get the WC_Product Object

		if ( $cart_item['gift_wrap'] == 'yes' ) {
		    $_gift_wrap_cost = get_post_meta($cart_item['product_id'], '_gift_wrap_cost', true);
			$product_name .= '<br><small>Gift Pack Cost: '.wc_price($_gift_wrap_cost).' per Product</small>';
		}

		return $product_name;
	}

	/**
	 * write_panel function.
	 *
	 * @access public
	 * @return void
	 */
	public function write_panel() {
		global $post;

		echo '</div><div class="options_group show_if_simple show_if_variable">';

		$is_wrappable = get_post_meta( $post->ID, '_is_gift_wrappable', true );

		if ( $is_wrappable == '' && $this->gift_wrap_enabled ) {
			$is_wrappable = 'yes';
		}

		woocommerce_wp_checkbox( array(
				'id'            => '_is_gift_wrappable',
				'wrapper_class' => '',
				'value'         => $is_wrappable,
				'label'         => __( 'Gift Packable', 'woocommerce-product-gift-wrap' ),
				'description'   => __( 'Enable this option if the customer can choose gift packing.', 'woocommerce-product-gift-wrap' ),
			) );

		woocommerce_wp_text_input( array(
				'id'          => '_gift_wrap_cost',
				'label'       => __( 'Gift Pack Cost', 'woocommerce-product-gift-wrap' ),
				'placeholder' => $this->gift_wrap_cost,
				'desc_tip'    => true,
				'description' => __( 'Override the default cost by inputting a cost here.', 'woocommerce-product-gift-wrap' ),
			) );

		wc_enqueue_js( "
			jQuery('input#_is_gift_wrappable').change(function(){

				jQuery('._gift_wrap_cost_field').hide();

				if ( jQuery('#_is_gift_wrappable').is(':checked') ) {
					jQuery('._gift_wrap_cost_field').show();
				}

			}).change();
		" );
	}

	/**
	 * write_panel_save function.
	 *
	 * @access public
	 * @param mixed $post_id
	 * @return void
	 */
	public function write_panel_save( $post_id ) {
		$_is_gift_wrappable = ! empty( $_POST['_is_gift_wrappable'] ) ? 'yes' : 'no';
		$_gift_wrap_cost   = ! empty( $_POST['_gift_wrap_cost'] ) ? woocommerce_clean( $_POST['_gift_wrap_cost'] ) : '';

		update_post_meta( $post_id, '_is_gift_wrappable', $_is_gift_wrappable );
		update_post_meta( $post_id, '_gift_wrap_cost', $_gift_wrap_cost );
	}

	/**
	 * admin_settings function.
	 *
	 * @access public
	 * @return void
	 */
	public function admin_settings() {
		woocommerce_admin_fields( $this->settings );
	}

	/**
	 * save_admin_settings function.
	 *
	 * @access public
	 * @return void
	 */
	public function save_admin_settings() {
		woocommerce_update_options( $this->settings );
	}
}

new JMB_WC_Product_Gift_Pack();
