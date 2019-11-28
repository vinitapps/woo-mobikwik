<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://vinit.site
 * @since      1.0.0
 *
 * @package    Woo_Mobikwik
 * @subpackage Woo_Mobikwik/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Woo_Mobikwik
 * @subpackage Woo_Mobikwik/includes
 * @author     Vinit Patil <reach@vinit.site>
 */


if ( ! defined( 'ABSPATH' ) )
{
    exit; // Exit if accessed directly
}

add_action( 'plugins_loaded', 'wc_mobikwik_gateway_init', 11 );
add_action('admin_post_nopriv_mbk_wc_webhook', 'mobikwik_webhook_init', 10);


function wc_mobikwik_gateway_init() {
    if (!class_exists('WC_Payment_Gateway'))
    {
        return;
    }

    class WC_Gateway_Mobikwik extends WC_Payment_Gateway {
        // This one stores the WooCommerce Order Id
        const SESSION_KEY                    = 'mobikwik_wc_order_id';

        const INR                            = 'INR';
        const CAPTURE                        = 'capture';
        const AUTHORIZE                      = 'authorize';
        const WC_ORDER_ID                    = 'woocommerce_order_id';

        const DEFAULT_LABEL                  = 'Mobikwik';
        const DEFAULT_DESCRIPTION            = 'Pay securely by Mobikwik.';
        const DEFAULT_SUCCESS_MESSAGE        = 'Thank you for shopping with us. Your account has been charged and your transaction is successful. We will be processing your order soon.';

        protected $visibleSettings = array(
            'enabled',
            'enabled_test',
            'title',
            'description',
            'mid',
            'mobi_key_secret',
            'merchant_name',
        );


        public $form_fields = array();

        public $supports = array(
            'products',
            'refunds'
        );

        /**
         * Can be set to true if you want payment fields
         * to show on the checkout (if doing a direct integration).
         * @var boolean
         */
        public $has_fields = false;

        /**
         * Unique ID for the gateway
         * @var string
         */
        public $id = 'mobikwik';

        /**
         * Title of the payment method shown on the admin page.
         * @var string
         */
        public $method_title = 'Mobikwik';


        /**
         * Description of the payment method shown on the admin page.
         * @var  string
         */
        public $method_description = 'Allow customers to securely pay via Mobikwik (Credit/Debit Cards, NetBanking, UPI, Wallets)';

        /**
         * Icon URL, set in constructor
         * @var string
         */
        public $icon;


        public function __construct($hooks = true)
        {
            $this->id = 'mobikwik' ;
            // $this->icon =  plugins_url('images/logo.png' , __FILE__);

            $this->init_form_fields();
            $this->init_settings();

            // TODO: This is hacky, find a better way to do this
            // See mergeSettingsWithParentPlugin() in subscriptions for more details.
            if ($hooks)
            {
                $this->initHooks();
            }

            $this->title = $this->getSetting('title');
        }
        // The meat and potatoes of our gateway will go here


        public function init_form_fields()
        {


            $webhookUrl = esc_url(admin_url('admin-post.php')) . '?action=mbk_wc_webhook';

            $defaultFormFields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', $this->id),
                    'type' => 'checkbox',
                    'label' => __('Enable this module?', $this->id),
                    'default' => 'yes'
                ),
                'enabled_test' => array(
                    'title' => __('Enable/Disable', $this->id),
                    'type' => 'checkbox',
                    'label' => __('Enable this test Mode', $this->id),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', $this->id),
                    'type'=> 'text',
                    'description' => __('This controls the title which the user sees during checkout.', $this->id),
                    'default' => __(static::DEFAULT_LABEL, $this->id)
                ),
                
                'description' => array(
                    'title' => __('Description', $this->id),
                    'type' => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', $this->id),
                    'default' => __(static::DEFAULT_DESCRIPTION, $this->id)
                ),
                'merchant_name' => array(
                    'title' => __('Merchant Name', $this->id),
                    'type'=> 'text',
                    'description' => __('Merchant Name that the user will see ( Your Brand Name ).', $this->id),
                ),
                'mid' => array(
                    'title' => __('MID', $this->id),
                    'type' => 'text',
                    'description' => __('This MID will be provided to you by mobikwik.', $this->id)
                ),
                'mobi_key_secret' => array(
                    'title' => __('MobiKwik Key Secret', $this->id),
                    'type' => 'text',
                    'description' => __('The key secret which was provided by MobiKwik.', $this->id)
                ),
                
                
            );

            foreach ($defaultFormFields as $key => $value)
            {
                if (in_array($key, $this->visibleSettings, true))
                {
                    $this->form_fields[$key] = $value;
                }
            }
        }

        

        protected function getOrderKey($order)
        {
            $orderKey = null;

            if (version_compare(WOOCOMMERCE_VERSION, '3.0.0', '>='))
            {
                return $order->get_order_key();
            }

            return $order->order_key;
        }

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);
            $woocommerce->session->set(self::SESSION_KEY, $order_id);

            $orderKey = $this->getOrderKey($order);

            if (version_compare(WOOCOMMERCE_VERSION, '2.1', '>='))
            {
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg('key', $orderKey, $order->get_checkout_payment_url(true))
                );
            }
            else if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
            {
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg('order', $order->get_id(),
                        add_query_arg('key', $orderKey, $order->get_checkout_payment_url(true)))
                );
            }
            else
            {
                return array(
                    'result' => 'success',
                    'redirect' => add_query_arg('order', $order->get_id(),
                        add_query_arg('key', $orderKey, get_permalink(get_option('woocommerce_pay_page_id'))))
                );
            }
        }

        /**
         * Returns redirect URL post payment processing
         * @return string redirect URL
         */
        private function getRedirectUrl()
        {
            return get_site_url() . '/wc-api/' . $this->id;
        }

        
        /**
         * Return Wordpress plugin settings
         * @param  string $key setting key
         * @return mixed setting value
         */
        public function getSetting($key)
        {
            return $this->settings[$key];
        }

        /**
         * Receipt Page
         * @param string $orderId WC Order Id
         **/
        function receipt_page($orderId)
        {   
            $order = wc_get_order( $orderId );

            $callbackUrl = $this->getRedirectUrl();
            $order_amount = (int) $order->get_total() ;
            $mid = $this->getSetting('mid'); ;
            $key_secret = $this->getSetting('mobi_key_secret') ;
            $merchant_name = $this->getSetting('merchant_name') ;
            
            $customer_email = $order->get_billing_email(); 
            $customer_phone = get_post_meta($order->get_order_number(), '_billing_phone', true); 
            

            $checksum_string = "'".$customer_phone."''".$customer_email."''".$order_amount."''".$orderId."''".$callbackUrl."''".$mid."'" ;
            
            $checksum = hash_hmac('sha256', $checksum_string, $key_secret);


            $actionURL = 'https://walletapi.mobikwik.com/wallet' ;

            if( $this->getSetting('enabled_test') ){
                $actionURL = 'https://test.mobikwik.com/wallet' ;
            }


            // $order_amount = 1 ;
            ?>
				<form action="<?=$actionURL?>" method="post" name =
				"info101">
				<input type="hidden" name="email" value="<?=$customer_email?>" />
				<input type="hidden" name="cell" value="<?=$customer_phone?>" />
				<input type="hidden" name="merchantname" value="<?=$merchant_name?>" />

				<input type="hidden" name="amount" value="<?=$order_amount?>" />
				<input type="hidden" name="orderid" value="<?=$orderId?>" />
				<input type="hidden" name="mid" value="<?=$mid?>" />
				<input type="hidden" name="redirecturl" value="<?=$callbackUrl?>" />
				<input type="hidden" name="checksum" value="<?=$checksum?>"/>
				<button type='submit' > PAY NOW </button>
				</form>
				<script>
				document.forms["info101"].submit();
				</script>
            <?php
        }


        protected function initHooks()
        {
            add_action('init', array(&$this, 'check_mobikwik_response'));

            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            add_action('woocommerce_api_' . $this->id, array($this, 'check_mobikwik_response'));

            $cb = array($this, 'process_admin_options');

            if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>='))
            {
                add_action("woocommerce_update_options_payment_gateways_{$this->id}", $cb);
            }
            else
            {
                add_action('woocommerce_update_options_payment_gateways', $cb);
            }
        }


        /**
         * Check for valid server callback
         **/
        function check_mobikwik_response()
        {
            global $woocommerce;

            $orderId = $woocommerce->session->get(self::SESSION_KEY);
            $order = new WC_Order($orderId);

            $paymentID = null;

            $checksum = sanitize_key($_POST['checksum']) ; 
            $status_code = sanitize_key($_POST['statuscode']);
            $status_message = sanitize_key($_POST['statusmessage']);
            $paymentID = sanitize_key($_POST['orderid']);
            $success = true ;
            $error = $status_message ;

            if($status_code != 0){
                $success = false ;
            }
            if(!$this->verifyChecksum($order,$checksum) ){
                $success = false ;
            }

            $this->updateOrder($order, $success, $error, $paymentID);

            
        }


        /**
         * Modifies existing order and handles success case
         *
         * @param $success, & $order
         */
        public function updateOrder(& $order, $success, $errorMessage, $paymentID )
        {
            global $woocommerce;

            $orderId = $order->get_order_number();

            if (($success === true) && ($order->needs_payment() === true))
            {
                $this->msg['message'] = "Payment Success &nbsp; Order Id: $orderId";
                $this->msg['class'] = 'success';

                $order->payment_complete($paymentID);
                $order->add_order_note("Mobiwik payment successful <br/>Ordeer Id: $paymentID");

                if (isset($woocommerce->cart) === true)
                {
                    $woocommerce->cart->empty_cart();
                }
                $this->redirectUser($order);
            }
            else
            {
                $this->msg['class'] = 'error';
                $this->msg['message'] = $errorMessage;

                if ($paymentID)
                {
                    $order->add_order_note("Payment Failed. Please check Mobikwik Dashboard. <br/> Mobikwik Id: $paymentID");
                }

                $order->add_order_note("Transaction Failed: $errorMessage<br/>");
                $order->update_status('failed');
                
                wp_redirect(get_site_url());
            }

            
        }

        
		/* Verify the checksum sent back from in response */
        protected function verifyChecksum($order, $order_checksum){

            $status_code = sanitize_key($_POST['statuscode']);
            $status_message = sanitize_key($_POST['statusmessage']);


            $orderId = $order->get_order_number();
            $callbackUrl = $this->getRedirectUrl();
            $order_amount = $order->get_total() ;
            $mid = $this->getSetting('mid'); ;
            $key_secret = $this->getSetting('mobi_key_secret') ;

            $checksum_string = "'".$status_code."''".$orderId."''".$order_amount."''".$status_message."''".$mid."'" ;
            
            $checksum = hash_hmac('sha256', $checksum_string, $key_secret);
            
            if($order_checksum == $checksum)
                return true ;
            else 
                return false ;

        }


        protected function redirectUser($order)
        {
            $redirectUrl = $this->get_return_url($order);

            wp_redirect($redirectUrl);
            exit;
        }


    } // end \WC_Gateway_Offline class

    /**
     * Add the Gateway to WooCommerce
     **/
    function woocommerce_add_mobikwik_gateway($methods)
    {
        $methods[] = 'WC_Gateway_Mobikwik';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_mobikwik_gateway' );
}


// This is set to a priority of 10
function mobikwik_webhook_init()
{
    $rzpWebhook = new MBK_Webhook();

    $rzpWebhook->process();
}
