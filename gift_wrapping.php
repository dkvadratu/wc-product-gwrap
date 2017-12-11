<?php
/*
Plugin Name: Gift wrapping
Plugin URI: https://github.com/dkvadratu/wc-product-gwrap
Description: Add an option to your products to enable gift wrapping. Optionally charge a fee.
Version: 1.0
Author: Deividas Dkvadratu
Author URI: https://fb.com/dkvadratu
Text Domain: gwrap
Domain Path: /languages/
*/

/**
 * Localisation
 */
//load_plugin_textdomain( 'woocommerce-product-gift-wrap', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

/**
 * WC_Product_GWrap class.
 */
class WC_Product_GWrap {

	/**
	 * Hook us in :)
	 *
	 * @access public
	 * @return void
	 */

	public function __construct() {

		add_action( 'wp_enqueue_scripts', array($this, 'enqueue_js') );
		add_action( 'wp_ajax_gift_wrap', array($this,'add_gift_wrap') );
		add_action( 'wp_ajax_nopriv_gift_wrap', array($this,'add_gift_wrap') );

		// settings link on the plugins listing page
    	add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'gwrap_settings_link' ), 10, 1 );
        // Add settings SECTION under Woocommerce->Settings->Products
    	add_filter( 'woocommerce_get_sections_products',                array( $this, 'gwrap_section' ), 10, 1 );
    	// Add settings to the section we created with add_section()
		add_filter( 'woocommerce_get_settings_products',                array( $this, 'gwrap_settings' ), 10, 2);

		//add gwrap panel for product settings
		add_action('woocommerce_product_write_panel_tabs', array($this, 'create_gwrap_tab'));
		add_action('woocommerce_product_data_panels', array($this, 'show_gwrap_tab'));
		add_action('woocommerce_process_product_meta_simple', array($this, 'save_gwrap_tab'));

		//update product price
		add_action( 'woocommerce_before_calculate_totals', array($this, 'set_new_product_price'), 20, 1 );
		//still show orginal product price
		add_filter( 'woocommerce_cart_item_price', array($this, 'show_orginal_product_price'), 20, 3 );

		//show meta data after title in cart
		add_filter( 'woocommerce_get_item_data', array($this, 'show_gwrap_status'), 10, 2);
		// add the filter to cart item title
		add_filter( 'woocommerce_cart_item_name', array($this, 'modify_cart_item_name'), 1, 3 );
		//save meta to product oreder
		add_filter( 'woocommerce_add_order_item_meta', array($this, 'add_order_item_meta'), 10, 2 );
	}

	public function enqueue_js(){
		if(is_cart())
			wp_enqueue_script( 'popup', plugins_url( '/js/popup.js', __FILE__ ), array('jquery'), '17.11.14', true);
			wp_enqueue_style( 'popup', plugins_url( '/gwrap.css', __FILE__ ), false, '1.0', 'all');
	}

	public function add_gift_wrap(){//todo doesnt work after Cart_update
		if( $_REQUEST['action'] == "gift_wrap" ){
			global $woocommerce;

			$func 			= $_REQUEST['func'];
			$productId 		= $_REQUEST['productID'];
			$cartKey 		= $_REQUEST['cartKEY'];

			switch($func){
				case 'addWRAP':
					echo "ADD";
					$price 		= get_option('gwrap_style_'.$_REQUEST['gwrapSTYLE'].'_price');
					$title 		= get_option('gwrap_style_'.$_REQUEST['gwrapSTYLE'].'_title');
					$woocommerce->cart->cart_contents[$cartKey]['gift_wrap'] 		= 1;
					$woocommerce->cart->cart_contents[$cartKey]['gift_wrap_type'] 	= $_REQUEST['gwrapSTYLE'];
					$woocommerce->cart->cart_contents[$cartKey]['gift_wrap_qty'] 	= $_REQUEST['gwrapQTY'];
					$woocommerce->cart->cart_contents[$cartKey]['gift_wrap_price'] 	= $price;
					$woocommerce->cart->cart_contents[$cartKey]['gift_wrap_title'] 	= $title;
					$woocommerce->cart->set_session();
					break;
				case 'updateWRAP':
					echo "UPDATE";
					$price 		= get_option('gwrap_style_'.$_REQUEST['gwrapSTYLE'].'_price');
					$title 		= get_option('gwrap_style_'.$_REQUEST['gwrapSTYLE'].'_title');
					$woocommerce->cart->cart_contents[$cartKey]['gift_wrap_type'] 	= $_REQUEST['gwrapSTYLE'];
					$woocommerce->cart->cart_contents[$cartKey]['gift_wrap_qty'] 	= $_REQUEST['gwrapQTY'];
					$woocommerce->cart->cart_contents[$cartKey]['gift_wrap_price'] 	= $price;
					$woocommerce->cart->cart_contents[$cartKey]['gift_wrap_title'] 	= $title;
					$woocommerce->cart->set_session();
					break;
				case 'removeWRAP':
					echo "REMOVE";
					$woocommerce->cart->cart_contents[$cartKey]['gift_wrap'] = 0;
					$woocommerce->cart->set_session();
					break;
				default:
			}
		}
		wp_die();
	}

	public function set_new_product_price($cart_obj){

		foreach ($cart_obj->get_cart() as $key => $value) {
			if(isset($value['gift_wrap']) && $value['gift_wrap'] != 0){
				$prod_qty		= $value['quantity'];
				$wrap_qty		= $value['gift_wrap_qty'];
				$wrap_price		= $value['gift_wrap_price'];

				$total_wrap_cost = $wrap_price * $wrap_qty;

				$old_price	= $value['data']->get_price();
				$new_price	= $old_price + ( $total_wrap_cost/$prod_qty );
				$value['data']->set_price($new_price);
			}
		}
	}
	function show_orginal_product_price($price, $cart_item, $cart_item_key){
		if(isset($cart_item['gift_wrap']) && $cart_item['gift_wrap'] == 1){
			$packing_price 	= $cart_item['gift_wrap_price'];
			$packing_qty 	= $cart_item['gift_wrap_qty'];
			$packing_cost	= $packing_price * $packing_qty;

			$regular_price 	= $cart_item['data']->get_price() - ($packing_cost/$cart_item['quantity']);

			return wc_price($regular_price);
		}
		return $price;
	}
	function modify_cart_item_name( $product_get_name, $cart_item, $cart_item_key ) {

		$gwrap_status = get_post_meta($cart_item['product_id'], '_gwrap_status', true);

		if(isset($gwrap_status) && $gwrap_status != "yes"){ //if status=yes then its disabled

			$checked1 = ""; $checked2 = "";
			if( isset($cart_item['gift_wrap_type']) && $cart_item['gift_wrap_type'] == 1 ){		$checked1 = "checked"; }
			elseif( isset($cart_item['gift_wrap_type']) && $cart_item['gift_wrap_type'] == 2 ){	$checked2 = "checked"; }

			$popup = '<div id="'.$cart_item['product_id'].'" class="lightbox-by-id lightbox-content mfp-hide" style="max-width:600px;padding:20px;text-align:center">';
			//$popup .= '<select id="gwrap_style"><option value="1">'.get_option('gwrap_style_1_title').' (+'. get_option('gwrap_style_1_price').')</option><option value="2">'.get_option('gwrap_style_2_title').' (+'.get_option('gwrap_style_2_price').')</option></select><br>';
			$popup .= '<div class="gwrap_block"><img src="'.get_option('gwrap_style_1_image').'"><input type="radio" name="gwrap_style" '.$checked1.' value="1"><label for="1">'.get_option('gwrap_style_1_title') . ' ' . get_option('gwrap_style_1_price') .'€</label></div>';
			$popup .= '<div class="gwrap_block"><img src="'.get_option('gwrap_style_2_image').'"><input type="radio" name="gwrap_style" '.$checked2.' value="2"><label for="2">'.get_option('gwrap_style_2_title') . ' ' . get_option('gwrap_style_2_price') .'€</label></div>';

			if($cart_item['quantity'] > 1){
				$popup .= 'Kiek dovanų supakuoti? <input type="number" id="gwrap_qty_'.$cart_item['product_id'].'" class="gwrap_qty" placeholder="1" value="1" max="'.$cart_item['quantity'].'">';
			}else{
				$popup .= 'Kiek dovanų supakuoti? <input type="number" id="gwrap_qty_'.$cart_item['product_id'].'" class="gwrap_qty" placeholder="1" value="1" max="'.$cart_item['quantity'].'" disabled>';
				//$popup .= '<input type="hidden" id="gwrap_qty" placeholder="1" value="1" max="1">';
			}

			if(isset($cart_item['gift_wrap']) && $cart_item['gift_wrap'] == 1){
				$popup .= '<a onclick="update_wrap(\''.$cart_item['product_id'] . ','. $cart_item_key . '\')" class="update_gwrap_btn button primary box-shadow-2 box-shadow-5-hover">Atnaujinti</a>';
				$popup .= '<a onclick="remove_wrap(\''.$cart_item['product_id'] . ','. $cart_item_key . '\')" class="remove_gwrap_btn button secondary box-shadow-2 box-shadow-5-hover">Nepakuoti</a>';
			}else{
				$popup .= '<a onclick="add_wrap(\''.$cart_item['product_id'] . ','. $cart_item_key . '\')" class="add_gwrap_btn button primary box-shadow-2 box-shadow-5-hover">Supakuoti</a>';
			}

			$popup .= '</div>';

			return $popup . $product_get_name . '<a style="background: url('.get_option('gwrap_icon').') center center no-repeat;" class="gwrap_icon" href="#'.$cart_item['product_id'].'"></a>';
		}else{
			return $product_get_name;
		}

	}

	function show_gwrap_status($data, $cartItem){
		if ( isset( $cartItem['gift_wrap'] ) && $cartItem['gift_wrap'] != 0) {
				$gift_wrap 	= $cartItem['gift_wrap_qty'] . 'vnt. ' . $cartItem['gift_wrap_title'] . ' (+'.$cartItem['gift_wrap_price'] * $cartItem['gift_wrap_qty'].'€)';
				$data[] = array(
					'name' => 'Įpakavimas',
					'value' => $gift_wrap
				);
			}

		return $data;
	}

	/*
	* Settings in product edit page
	*/
	function create_gwrap_tab(){ ?>
        <li class="gwrap_product_options_tab"><a href="#gwrap_options"><span><?php _e('Dovanos įpakavimas', 'gwrap'); ?></span></a></li>
    <?php
	}
	function show_gwrap_tab(){
        ?>
        <div id='gwrap_options' class='panel woocommerce_options_panel'>
            <div class='options_group'>
                <?php
                // Checkbox
                woocommerce_wp_checkbox(
                  array(
                    'id' => '_gwrap_status',
                    'label' => __('Išjungti dovanos įpakavimą šiam produktui?', 'gwrap' ),
					'desc_tip' => 'true',
					'description' => __( 'Standartiškai įpakavimas įjungas visoms prekėms', 'gwrap' )
                  )
                );
                ?>
            </div>
        </div>
    <?php
    }
	function save_gwrap_tab($post_id){
        // Save Text Field
        $status = $_POST['_gwrap_status'];
        if (!empty($status)) {
            update_post_meta($post_id, '_gwrap_status', esc_attr($status));
        }
    }

	/*
	* Settings in Woocommerce settings
	*/
	static function gwrap_settings_link( $links ) {
		$settings = sprintf( '<a href="%s">%s</a>', admin_url( 'admin.php?page=wc-settings&tab=products&section=gwrapsection' ), __( 'Settings', 'gwrap' ) );
		array_unshift( $links, $settings );
		return $links;
	}
	static function gwrap_section( $sections ) {
		$sections['gwrapsection'] = __( 'Dovanų įpakavimai', 'gwrap' );
		return $sections;
	}
	public static function gwrap_settings( $settings, $current_section ) {
		//todo end
 		if ( $current_section == 'gwrapsection' ) {

			$settings_slider = array();
			$grwap_settings	 = array();

			$settings_slider[] = array(
				'id' 				=> 'gwrap_desc',
				'name' 				=> __( 'Dovanų įpakavimo nustatymai', 'gwrap' ),
				'type' 				=> 'title',
				'desc' 				=> '<strong>1.</strong> ' . sprintf(__( 'Start by <a href="%s" target="_blank">adding at least one product</a> called "Gift Wrapping" or something similar.', 'gwrap' ), admin_url( 'post-new.php?post_type=product' ) ) . '<br /><strong>2.</strong> ' . __( 'Create a unique product category for this/these gift wrapping product(s), and add them to this category.', 'gwrap' ) . '<br /><strong>3.</strong> ' . __( 'Then consider the options below.', 'gwrap' ),
			);

			$settings_slider[] = array(
				'id'       			=> 'gwrap_icon',
				'name'     			=> __( 'Ikonos url dovanų pakavimui', 'gwrap' ),
				'type'     			=> 'text',
				'desc_tip'				=> 'Ikona atsiras prie prekės pavadinimo. Puslapyje Krepšelis. Dydis 20x20px'
			);
			$settings_slider[] = array(
				'id'       			=> 'gwrap_style_1_title',
				'name'     			=> __( 'Style 1 title', 'gwrap' ),
				'type'     			=> 'text',
			);
			$settings_slider[] = array(
				'id'       			=> 'gwrap_style_1_image',
				'name'     			=> __( 'Style 1 image url', 'gwrap' ),
				'type'     			=> 'text',
			);
			$settings_slider[] = array(
				'id'       			=> 'gwrap_style_1_price',
				'name'     			=> __( 'Style 1 price', 'gwrap' ),
				'type'     			=> 'number',
			);

			$settings_slider[] = array(
				'id'       			=> 'gwrap_style_2_title',
				'name'     			=> __( 'Style 2 title', 'gwrap' ),
				'type'     			=> 'text',
			);
			$settings_slider[] = array(
				'id'       			=> 'gwrap_style_2_image',
				'name'     			=> __( 'Style 2 image url', 'gwrap' ),
				'type'     			=> 'text',
			);
			$settings_slider[] = array(
				'id'       			=> 'gwrap_style_2_price',
				'name'     			=> __( 'Style 2 price', 'gwrap' ),
				'type'     			=> 'number',
			);

			//$settings_slider[] = array(
			//	'id' 				=> 'gwrap_settings',
			//	'name' 				=> __( 'Gift Wrapping Nustatymai', 'gwrap' ),
			//	'type' 				=> 'text',
			//	//'desc' 				=> maybe_serialize($grwap_settings),
			//);

			$settings_slider[] = array(
				'id' => 'gwrapsection',
				'type' => 'sectionend',
			);
			//var_dump($settings_slider);

		    return $settings_slider;


		} else {

			return $settings;

		}

	}

	//adding data to order page
	public function add_order_item_meta( $item_id, $values ) {
		$mb = maybe_serialize($values);
		woocommerce_add_order_item_meta($item_id, 'test', 'tetst_ ' . $mb);
		global $woocommerce;
		foreach($woocommerce->cart->cart_contents as $item){
			if(isset($item['gift_wrap']) && $item['gift_wrap'] != 0){
				woocommerce_add_order_item_meta($item_id, 'Įpakavimas', $item['gift_wrap_title'] . ' ' . $item['gift_wrap_price'] . 'vnt. Suma: ' . $item['gift_wrap_price'] * $item['gift_wrap_qty'] . '€');
			}
		}
	}
}

new WC_Product_GWrap();

//TODO on quantioty reduce check if there is gwrap. If yes. check how many packings. If more than cart_quanitnty then reduce packing quantity