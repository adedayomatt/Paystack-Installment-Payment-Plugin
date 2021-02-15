<?php
/**
 * Plugin Name: Paystack Installment Payment Gateway
 * Plugin URI:
 * Description: Installment payment gateway for paystack
 * Version: 1.0
 * Author: Adedayo Matthews
 * Author URI: http://adedayomatt.com
 *
 */

require __DIR__.'/classes/PIPConfig.php';

add_action( 'plugins_loaded', 'ip_gateway_init', 11 );

function ip_gateway_init() {

    class WC_Gateway_IP extends WC_Payment_Gateway {

        public function __construct()
        {
            $this->id = 'installment';
            $this->icon = null;
            $this->has_fields = true;
            $this->method_title = "Installment Payment";
            $this->method_description = "Installment payment for credit user";


            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->private_key = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
            $this->public_key = $this->testmode ? $this->get_option( 'test_public_key' ) : $this->get_option( 'public_key' );

            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // Hook to enqueue the paystack script
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
            add_action('woocommerce_after_checkout_shipping_form', [$this, 'fetch_shipping_locations']);
        }

        public function fetch_shipping_locations(){
            print_r('Hello World!');
        }
       /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields() {

            $this->form_fields = apply_filters( 'wc_gateway_ip_form_fields', array(

                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'wc-gateway-ip' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Installment Payment', 'wc-gateway-ip' ),
                    'default' => 'yes'
                ),

                'title' => array(
                    'title'       => __( 'Title', 'wc-gateway-ip' ),
                    'type'        => 'text',
                    'description' => __( "You will be able to pay for your order on installmental. Isn't that awesome???", 'wc-gateway-ip' ),
                    'default'     => __( 'Installment Payment', 'wc-gateway-ip' ),
                    'desc_tip'    => true,
                ),

                'description' => array(
                    'title'       => __( 'Description', 'wc-gateway-ip' ),
                    'type'        => 'textarea',
                    'description' => __( 'Installment will be deducted automatically.', 'wc-gateway-ip' ),
                    'default'     => __( 'Installment will be deducted automatically', 'wc-gateway-ip' ),
                    'desc_tip'    => true,
                ),

                'instructions' => array(
                    'title'       => __( 'Instructions', 'wc-gateway-ip' ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gateway-ip' ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),

                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),

                'test_public_key' => array(
                    'title'       => 'Test Public Key',
                    'type'        => 'text'
                ),

                'test_secret_key' => array(
                    'title'       => 'Test Secret Key',
                    'type'        => 'text',
                ),

                'public_key' => array(
                    'title'       => 'Live Public Key',
                    'type'        => 'password'
                ),

                'secret_key' => array(
                    'title'       => 'Live Secret Key',
                    'type'        => 'password'
                )
                
            ) );
        }

        public function payment_fields() {
            $interval  = $this->testmode ? 'Hour' : 'Month';
            $maxAllowed = PIPConfig::MAX_ALLOWED();
            // ok, let's display some description before the payment form
            if ( $this->description ) {
                // you can instructions for test mode, I mean test card numbers etc.
                if ( $this->testmode ) {
                    $this->description .= '<br><br>*****TEST MODE ENABLED******';
                    $this->description  = trim( $this->description );
                }
                // display the description with <p> tags etc.
                echo wpautop( wp_kses_post( $this->description ) );
            }
            ?>
            <fieldset id="wc-<?php echo esc_attr( $this->id ) ?>-cc-form" class="installment-payment-form" style="background:transparent;">
                <div>
                    <?php

                        if(get_user_meta(get_current_user_id(), 'credit_worthy', true )){
                            if($this->testmode){
                                ?>
                                <div>
                                    <small>Using the smallest ineterval - Hour in TEST MODE</small>
                                </div>
                                <?php
                            }

                            if((int) WC()->cart->total > $maxAllowed){
                                ?>
                                <div style="color: red">
                                    Your order cannot worth more than NGN<?php echo number_format($maxAllowed) ?> to use installment payment, please readjust your cart.
                                </div>
                                <?php
                            }else{
                                ?>
                                <label><?php echo $interval ?><span class="required">*</span></label>
                                <select class="form-control" name="installment_month" id="installment-month" style="width:100%" onchange="javascript: installmentMonthChanged()" total="<?php echo WC()->cart->total ?>">
                                    <option value="">select <?php echo $interval ?></option>
                                    <option value="3">3</option>
                                    <option value="2">2</option>
                                    <option value="1">1</option>
                                </select>
                                <div id="installment-details"></div>
                                <input type="hidden" name="subscription" id="subscription-payload">
                                <input type="hidden" name="initial_payment" id="initial-payment-payload">
                            <?php
                            }
                        }
                        else{
                            ?>
                                <div style="color: red">
                                    Your account is not approved for installment payment yet.
                                </div>
                            <?php
                        }
                    ?>
                </div>
            </fieldset>
            <?php
        }

        public function validate_fields(){

            if( empty( $_POST[ 'installment_month' ]) ) {
                wc_add_notice(  'Installment month is required', 'error' );
                return false;
            }

            if( empty( $_POST[ 'subscription' ]) ) {
                wc_add_notice(  'Installment plan was not created successfully', 'error' );
                return false;
            }

            if( empty( $_POST[ 'initial_payment' ]) ) {
                wc_add_notice(  'Initial payment is required', 'error' );
                return false;
            }

            return true;
        }

        public function payment_scripts() {
            // If user is not a credit user
            if(!current_user_can('creditor')) return;

            // we need JavaScript to process payment only on cart/checkout page?
            if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) return;

            // // if our payment gateway is disabled, we do not have to enqueue JS too
            if ( 'no' === $this->enabled )  return;

            // // no reason to enqueue JavaScript if API keys are not set
            if ( empty( $this->private_key ) || empty( $this->public_key ) )  return;

            // // do not work with card detailes without SSL unless your website is in a test mode
            // if ( ! $this->testmode && ! is_ssl() ) {
            //     return;
            // }

            $current_user = wp_get_current_user();

            wp_enqueue_script( 'paystack', 'https://js.paystack.co/v1/inline.js' );

            wp_register_script( 'installment_payment', plugins_url( 'js/paystack.js', __FILE__ ), array( 'jquery', 'paystack' ) );

            // in most payment processors you have to use PUBLIC KEY to obtain a token
            wp_localize_script( 'installment_payment', 'params', array(
                'user_approved' => get_user_meta(get_current_user_id(), 'credit_worthy', true ),
                'public_key' => $this->public_key,
                'secret_key' => $this->private_key,
                'email' => esc_html( $current_user->user_email ),
                'first_name' => esc_html( $current_user->user_firstname ),
                'last_name' => esc_html( $current_user->user_lastname ),
                'reference' => uniqid('INST-'),
                'total_amount' => WC()->cart->total * 100,
                'subscription_name' => esc_html( $current_user->user_firstname )." ".esc_html( $current_user->user_lastname )." Installment-".$current_user->ID.'-'.uniqid(),
                'interval' => $this->testmode ? 'hourly' : 'monthly',
                'description' => 'Installment payment of '.number_format(WC()->cart->total).' by '.esc_html( $current_user->user_firstname ).' '.esc_html( $current_user->user_lastname )
            ) );

            wp_enqueue_script( 'installment_payment' );
        }

        public function process_payment( $order_id ) {

            $order = wc_get_order( $order_id );
            $installment_month = $_POST['installment_month'];

            update_post_meta($order_id, 'subscription', isset($_POST['subscription']) ? $_POST['subscription'] : '');
            update_post_meta($order_id, 'initial_payment', isset($_POST['initial_payment']) ? $_POST['initial_payment'] : '');

            // we received the payment
            $order->payment_complete();
            $order->reduce_order_stock();

            // some notes to customer (replace true with false to make it private)
            $order->add_order_note( 'Your order installment is active. Thank you!', true );

            // Empty cart
            WC()->cart->empty_cart();

            // Redirect to the thank you page
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order )
            );

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
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

            if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
                echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
            }
        }

    } // end \WC_Gateway_Offline class
}



add_filter( 'woocommerce_payment_gateways', 'wc_gateway_ip_add_to_gateways' );
function wc_gateway_ip_add_to_gateways( $gateways ) {
    $gateways[] = 'WC_Gateway_IP';
    return $gateways;
}

add_filter( 'woocommerce_available_payment_gateways', 'ip_available_gateways' );

//   Filter payment gateways according to user credit worthiness
function ip_available_gateways( $available_gateways ) {
    if(current_user_can('creditor')){
        foreach ($available_gateways as $key => $value) {
            if($key !== 'installment'){
                unset($available_gateways[$key]);
            }
        }
    } else{
        unset($available_gateways['installment']);
    }

   return $available_gateways;

}

