<?php
/**
 * Plugin Name: WooCommerce Advanced Offline Payment Gateway
 * Plugin URI: NA
 * Description: let the buyer upload the bank payment receipt during checkout.
 * Author: Mahesh B
 * Author URI: NA
 * Version: 1
 * Text Domain: wc-advanced-offline-payment-gateway
 * License: GNU General Public License v3.0
 */
 
defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
add_action( 'activate_plugin', 'wc_aopg_add_to_gateways_activate' , 10, 2);

 function wc_aopg_add_to_gateways_activate($plugin, $network_wide){
      global $wp_version;

      if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        error_log( 'WooCommerce is required for activating this plugin.' );      
        $args = var_export( func_get_args(), true );
        error_log( $args );
        wp_die( 'WooCommerce is required for activating this plugin.' );
		return;
      }
 }


/**
 * Add this gateway to WC Available Gateways
 */
function wc_aopg_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Adv_Offline_Payment_Gateway';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_aopg_add_to_gateways' );


/**
 * Plugin page links
 * 
 */
function wc_aopg_gateway_plugin_links( $links ) {

	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=offline_gateway' ) . '">' . __( 'Configure', 'wc-adv-off-pay-gateway' ) . '</a>'
	);

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_aopg_gateway_plugin_links' );

/**
 * @class 		WC_Adv_Offline_Payment_Gateway
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 */
add_action( 'plugins_loaded', 'wc_aopg_gateway_init', 11 );

function wc_aopg_gateway_init() {

	class WC_Adv_Offline_Payment_Gateway extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {
	  
			$this->id                 = 'offline_gateway';
			$this->icon               = apply_filters('woocommerce_offline_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Offline', 'wc-adv-off-pay-gateway' );
			$this->method_description = __( 'Allows offline payments. Very handy if you use your cheque gateway for another payment method, and can help with testing. Orders are marked as "on-hold" when received.', 'wc-adv-off-pay-gateway' );
		  
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
		  
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
		  
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
	
	
		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {
	  
			$this->form_fields = apply_filters( 'wc_aopg_form_fields', array(
		  
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'wc-adv-off-pay-gateway' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Advanced Offline Payment Gateway', 'wc-adv-off-pay-gateway' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Title', 'wc-adv-off-pay-gateway' ),
					'type'        => 'text',
					'description' => __( 'Title for the payment method during checkout.', 'wc-adv-off-pay-gateway' ),
					'default'     => __( 'Advanced Offline Payment Gateway.', 'wc-adv-off-pay-gateway' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Description', 'wc-adv-off-pay-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'Please upload payment reciept for further checkout.', 'wc-adv-off-pay-gateway' ),
					'default'     => __( 'Please upload payment reciept for further checkout.', 'wc-adv-off-pay-gateway' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instructions', 'wc-adv-off-pay-gateway' ),
					'type'        => 'textarea',
					'description' => __( 'Thank you for the payment and we will get back to you asap.', 'wc-adv-off-pay-gateway' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			) );
		}
	
	
		/**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}


		/**
		 * Display file upload button
		 */
		public function payment_fields() {
			if ( $this->description ) {
				echo $this->description;
			}
			echo '<div style="border: 2px solid #e2e2e2; padding:10px;"><input type="file" name="reciept_image" id="reciept_image" accept=".jpeg, .jpg, .png, .pdf">
			<input name="security" id="security" value="'.wp_create_nonce("uploadingFile").'" type="hidden"></div>';
			echo '<span id="uploadStatus" style="display:none;"></span>';
			echo '<div style="margin:20px;" id="imageCont" ><a href="javascript:" id="removeUploadedReciept" style="display:none; float: left;" title="Click here to delete." class="remove">X</a><a href="" id="imgRecieptContUrl" target="_blank" ><img src="" id="imgRecieptCont" style="width:300px; float:left; display:none;" title="Click here to enlarge." /></a></div>';
		}

	
		/**
		 * Process the payment and return the result
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
	
			$order = wc_get_order( $order_id );
			
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'on-hold', __( 'Awaiting reciept confirmation', 'wc-adv-off-pay-gateway' ) );
			

			// Store reciept in the order meta data, session created while saving the image
			session_start();
			if( isset( $_SESSION['_reciept_image'] ) && $_SESSION['_reciept_image'] != "" ){
				update_post_meta($order_id, '_reciept_image', esc_attr(htmlspecialchars($_SESSION['_reciept_image'])));
			}
			
			// Remove cart
			WC()->cart->empty_cart();
			
			
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}
	
  } // end \WC_Adv_Offline_Payment_Gateway class
}

add_action( 'wp_enqueue_scripts', 'wc_aopg_enqueue_scripts' );
function wc_aopg_enqueue_scripts(){
  	if( is_checkout() ) {
		wp_enqueue_script( 'wc_aopg_script', plugin_dir_url( __FILE__ ) . 'js/wc_aopg_custom.js', array( 'jquery' ), '1.0' );

		wp_localize_script( 'wc_aopg_script', 'wc_aopg_js_obj', array(
			'ajax_url'    => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce('ajax-nonce')
		) );
	}  
}

/** Save reciept image and store the image path in the session for sending along with the order */
add_action( 'wp_ajax_store_image', 'store_reciept_image' );
add_action( 'wp_ajax_nopriv_store_image', 'store_reciept_image' );
function store_reciept_image(){
	
	$arr_img_ext = array('image/png', 'image/jpeg', 'image/jpg', 'application/pdf');
    if ( in_array( $_FILES['reciept_file']['type'], $arr_img_ext ) ) {
        $upload_reciept = wp_upload_bits($_FILES["reciept_file"]["name"], null, file_get_contents($_FILES["reciept_file"]["tmp_name"]));

		if( count($upload_reciept) > 0 ){
			@session_start();
    		$_SESSION['_reciept_image'] = $upload_reciept["url"];
			$responseArray = array(
				'result' 	=> 'success',
				'uploaded_image_url' => $upload_reciept["url"]
			);
			echo json_encode($responseArray);
		}else{
			$responseArray = array(
				'result' 	=> 'failed',
				'upload_error' => $upload_reciept["error"]
			);
			echo json_encode($responseArray);
		}

    }else{
		
		$responseArray = array(
			'result' 	=> 'failed',
			'upload_error' => 'Unable to get the file.'
		);
		echo json_encode($responseArray);

	}
	wp_die();
}

/** Let's display recipet image in the order details - admin dashboard */
add_action('woocommerce_admin_order_data_after_shipping_address', 'wc_aopg_display_uploaded_reciept', 10, 1 );
function wc_aopg_display_uploaded_reciept( $order ) {

	// Get uploaded reciept from the meta data
    $reciept_image = $order->get_meta('_reciept_image');
    
    if ( ! empty( $reciept_image ) ) {
        echo '<div style="margin-top:10px;"><h3>Reciept</h3><br/><a href="'.$reciept_image.'" id="imgRecieptContUrl" target="_blank" ><img src="'.$reciept_image.'" id="imgRecieptCont" style="width:300px; float:none;" title="Click here to enlarge." /></a></div>';
    }
}

/** Delete uploaded image */
add_action( 'wp_ajax_wc_aopg_delete_image', 'wc_aopg_delete_image' );
add_action( 'wp_ajax_nopriv_wc_aopg_delete_image', 'wc_aopg_delete_image' );
function wc_aopg_delete_image(){

	$imagePath = get_home_path() .''.parse_url($_REQUEST["reciept_image"], PHP_URL_PATH);

	wp_delete_file( $imagePath );

	$responseArray = array(
		'result' 	=> 'success',
		'response_status' => 'Reciept deleted successfully.'
	);
	echo json_encode($responseArray);
	wp_die();
}