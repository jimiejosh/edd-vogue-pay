<?php
/**
 * Plugin Name: Voguepay Payment Gateway for Easy Digital Downloads
 * Plugin URI: https://github.com/jimiejosh/edd-vogue-pay
 * Description: Extends Easy Digital Downloads plugin, allowing you to take payments through popular Nigerian payment gateway service Voguepay.
 * Version: 1.0.1
 * Author: Jimie Josh
 * Author URI: https://github.com/jimiejosh
 * Text Domain: edd-voguepay
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: https://https://github.com/jimiejosh/edd-vogue-pay
 */
/**
 * Main file, includes plugin classes and registers constants
 *
 * @package Voguepay_Payment_Gateway
 *
 * @since Voguepay_Payment_Gateway 1.0
 */
/**
 * Don't load this file directly!
 */
if( !defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Absolute path to plugin
 */
if( !defined( 'SPG_ABSPATH' ) ) {
    define( 'SPG_ABSPATH', __DIR__ );
}

/**
 * Path to plugins root folder
 */
if( !defined( 'SPG_ROOT' ) ) {
    define( 'SPG_ROOT', plugin_dir_path( __FILE__ ) );
}

/**
 * Base URL of plugin
 */
if( !defined( 'SPG_BASEURL' ) ) {
    define( 'SPG_BASEURL', plugin_dir_url( __FILE__ ) );
}

/**
 * Base Name of plugin
 */
if( !defined( 'SPG_BASENAME' ) ) {
    define( 'SPG_BASENAME', plugin_basename( __FILE__ ) );
}

/**
 * Directory Name of plugin
 */
if( !defined( 'SPG_DIRNAME' ) ) {
    define( 'SPG_DIRNAME', dirname( plugin_basename( __FILE__ ) ) );
}

/**
 * Voguepay_Payment_Gateway
 *
 * The starting point of Voguepay_Payment_Gateway.
 *
 * @package Voguepay_Payment_Gateway
 * @since Voguepay_Payment_Gateway 1.0
 */
if( !class_exists( "Voguepay_Payment_Gateway" ) ) {

    class Voguepay_Payment_Gateway {

        // plugin warning
        protected $warning;

        public function __construct() {
            global $edd_options;

            // we use this hook to render our warnings
            add_action( 'admin_notices', array( $this, 'render_warnings' ) );

            // check if EDD is active
            if( !class_exists( 'Easy_Digital_Downloads' ) ) {
                $this->warning = sprintf(
                    __( 'The plugin Easy Digital Downloads - Voguepay Payment Gateway is enabled but not effective. It requires %s in order to work.', 'edd-voguepay' ), sprintf( '<a target="_blank" href="%s">%s</a>', 'https://wordpress.org/plugins/easy-digital-downloads/', 'Easy Digital Downloads' )
                );
                return;
            }

            // Add filter to add payment gateway in edd.
            add_filter( 'edd_payment_gateways', array( &$this, 'add_voguepay_payment' ) );

            // Add filter to register voguepay payment gateway settings in edd.
            add_filter( 'edd_settings_gateways', array( &$this, 'register_voguepay_payment_gateway_settings' ) );
			
			// Add NGN as a currency in EDD
			add_filter( 'edd_currencies', 'seye_add_my_currency' );
			
			// Add NGN as a currency in EDD
			add_filter( 'edd_currency', 'seye_add_ngn' );
			
			// Enable the naira currency symbol in EDD
		    add_filter('edd_currency_symbol', 'seye_add_my_currency_symbol', 10, 2);
			

		  // Add voguepay payment form for user.
            add_action( 'edd_voguepay_cc_form', array( &$this, 'add_voguepay_payment_form' ) );

            // Add `Contact Number` field into voguepay payment gateway form.
            add_action( 'edd_purchase_form_user_info_fields', array( &$this, 'voguepay_payment_form_fields' ) );

            // Add validation in voguepay payment gateway form.
            add_filter( 'edd_purchase_form_required_fields', array( &$this, 'voguepay_payment_form_fields_validation' ) );

            // Set validation for billing address by return `true` or `false`;
            add_filter( 'edd_require_billing_address', array( &$this, 'is_billing_address_require' ) );

            // Process voguepay Purchase.
            add_action( 'edd_gateway_voguepay', array( &$this, 'process_voguepay_purchase' ) );

            // Get Resposne from voguepay.
            add_action( 'init', array( &$this, 'get_response_from_voguepay' ) );

            // Process voguepay response.
            add_action( 'verify_voguepay_response', array( &$this, 'process_voguepay_response' ) );

            // Process if voguepay payment is failed.
            add_action( 'template_redirect', array( &$this, 'voguepay_listen_for_failed_payments' ), 20 );

            //Display settings link on plugin page (beside the activate/deactivate links).
            add_filter( 'plugin_action_links_' . SPG_BASENAME, array( &$this, 'voguepay_action_links' ) );

            //Clear currency conversion rates if settings updated.
            add_action( 'update_option_edd_settings', array( &$this, 'clear_currency_rates' ), 10, 2 );
			
        }
		
		/**
         * Check if Naira is set as voguepay payment.
         */
		public function seye_valid_for_use(){
			//check if currency is not Nigeria naira
			if(  edd_get_currency() != 'NGN'  ){
			$dfdf = edd_get_currency();
				$this->warning = __('Voguepay doesn\'t support your store currency, set it  hto Nigerian Naira &#8358; <a href="' . get_bloginfo('wpurl') . '/wp-admin/edit.php?post_type=download&page=edd-settings&tab=general&section=currency">here</a>');
				// $this->warning = __('<pre>'.printf(edd_get_currencies().'</pre>'));
				return false;
			}
		}
        /**
         * Render plugin warning.
         */
        public function render_warnings() {
            if( !empty( $this->warning ) ) :
                ?>
                <div class="message error">
                    <p><?php echo $this->warning; ?></p>
                </div>
                <?php
            endif;
        }

       
		
/**
         * Add `voguepay` Payment gateway into EDD.
         * @param array   $gateways Payment Gateways List
         * @return $gateways
         */
        public function add_voguepay_payment( $gateways ) {
            global $edd_options;

		 $this->seye_valid_for_use();
            $gateways[ 'voguepay' ] = array(
                'admin_label' => __( 'voguepay (recommended for Nigerian users)', 'edd-voguepay' ),
                'checkout_label' => __( 'voguepay', 'edd-voguepay' ),
            );

            if( isset( $edd_options[ 'voguepay_checkout_label' ] ) && !empty( $edd_options[ 'voguepay_checkout_label' ] ) ) {
                $gateways[ 'voguepay' ][ 'checkout_label' ] = __( $edd_options[ 'voguepay_checkout_label' ], 'edd' );
            }

            return $gateways;
        }

        /**
         * Register `voguepay` Payment gateway settings in EDD Payment Gateway tab.
         * @param array   $gateways_settings Settings of gateways
         * @return $gateways_settings
         */
        public function register_voguepay_payment_gateway_settings( $gateways_settings ) {
            global $edd_options;

            $gateways_settings[ 'voguepay' ] = array(
                'id' => 'voguepay',
                'name' => '<strong>' . __( 'Voguepay Settings', 'edd-voguepay' ) . '</strong>',
                'desc' => __( 'Configure the Voguepay settings', 'edd-voguepay' ),
                'type' => 'header'
            );

            $gateways_settings[ 'v_merchant_id' ] = array(
                'id' => 'v_merchant_id',
                'name' => __( 'Voguepay Merchant ID', 'edd-voguepay' ),
                'desc' => __( 'Enter your Merchant ID', 'edd-voguepay' ),
                'type' => 'text',
                'size' => 'regular'
            );

            $gateways_settings[ 'storeId' ] = array(
					  'id' => 'storeId',
					'name' => __( 'Voguepay Store ID', 'edd-voguepay' ),
					'desc' 	=> __( 'Enter Your Store ID here, if you have created a unique store within your Voguepay account.') ,
                'type' => 'text',
                'size' => 'regular'
				);

 
            $gateways_settings[ 'voguepay_checkout_label' ] = array(
                'id' => 'voguepay_checkout_label',
                'name' => __( 'Checkout Label', 'edd-voguepay' ),
                'desc' => __( 'Display payment gateway text on checkout page e.g. Pay with Voguepay', 'edd-voguepay' ),
                'type' => 'text',
                'size' => 'regular',
            );


            return $gateways_settings;
        }

        /**
         * Add `voguepay` Payment gateway form for user where users fill up personal details.
         */
        public function add_voguepay_payment_form() {
           // do_action( 'edd_after_cc_fields' );
        }

        /**
         * Add `Contact Number` field into `voguepay` payment gateway form.
         */
        public function voguepay_payment_form_fields() {
            /*if( 'voguepay' == edd_get_chosen_gateway() ) {
                $contact_number = is_user_logged_in() ? get_user_meta( get_current_user_id(), '_edd_user_contact_info', true ) : '';
                ?>
                <p id="edd-contact-wrap">
                    <label for="contact_number" class="edd-label">
                        <?php _e( 'Contact Number', 'edd-voguepay' ); ?>
                        <?php if( edd_field_is_required( 'contact_number' ) ) { ?>
                            <span class="edd-required-indicator">*</span>
                        <?php } ?>
                    </label>
                    <span class="edd-description"><?php _e( 'Your contact number.', 'edd-voguepay' ); ?></span>
                    <input id="contact_number" type="text" size="10" name="contact_number" class="contact-number edd-input<?php
                           if( edd_field_is_required( 'contact_number' ) ) {
                               echo ' required';
                           }
                           ?>" placeholder="<?php _e( 'Contact Number', 'edd-voguepay' ); ?>" value="<?php echo $contact_number; ?>"/>
                </p>
                <?php
            }
			*/
        }

        /**
         * Billing address is required for voguepay payment gateway.
         * @param  bool $is_billing Whether billing details is dispalyed or not.
         */
        public function is_billing_address_require( $is_billing ) {
            // if( 'voguepay' == edd_get_chosen_gateway() && edd_get_cart_total() ) {
                // return true;
            // } else {
                // return false;
            // }
        }

        /**
         * Add validation in voguepay payment gateway form.
         * @param array   $required_fields All Require fields
         * @return $required_fields
         */
        public function voguepay_payment_form_fields_validation( $required_fields ) {

            // if( 'voguepay' == edd_get_chosen_gateway() ) {
                // $required_fields[ 'contact_number' ] = array(
                    // 'error_id' => 'invalid_contact_number',
                    // 'error_message' => __( 'Please enter contact number', 'edd-voguepay' )
                // );
                // if( edd_get_cart_total() ) {
                    // $required_fields[ 'card_address' ] = array(
                        // 'error_id' => 'invalid_address',
                        // 'error_message' => __( 'Please enter billing address', 'edd-voguepay' )
                    // );
                // }
            // }

            return $required_fields;
        }

        /**
         * Get voguepay Redirect
         * @global $edd_options Array of all the EDD Options
         * @param boolean   $ssl_check Need url with ssl or without ssl
         * @return $voguepay_uri
         */
        public function get_voguepay_redirect( $ssl_check = false ) {
            global $edd_options;

            if( is_ssl() || !$ssl_check ) {
                $protocal = 'https://';
            } else {
                $protocal = 'http://';
            }

            // Check the current payment mode
            if( edd_is_test_mode() ) {
                // Test mode
                $voguepay_uri = $protocal . 'voguepay.com/pay/';
            } else {
                // Live mode
                $voguepay_uri = $protocal . 'voguepay.com/pay/';
            }

            return apply_filters( 'edd_voguepay_uri', $voguepay_uri );
        }

        /**
         * Generate Merchant reference ID.
         * @param int   $length Length of Merchant reference ID.
         * @return $merchant_ref
         */
        public function generate_merchant_refID( $length = 20 ) {

            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

            $charactersLength = strlen( $characters );

            $merchant_ref = '';

            for( $i = 0; $i < $length; $i++ ) {
                $merchant_ref .= $characters[ rand( 0, $charactersLength - 1 ) ];
            }

            return $merchant_ref;
        }

        /**
         * Process voguepay Purchase
         * @global $edd_options Array of all the EDD Options
         * @param array   $purchase_data Purchase Data
         * @return void
         */
        function process_voguepay_purchase( $purchase_data ) {
            global $edd_options;

            if( !wp_verify_nonce( $purchase_data[ 'gateway_nonce' ], 'edd-gateway' ) ) {
                wp_die( __( 'Nonce verification has failed', 'edd-voguepay' ), __( 'Error', 'edd-voguepay' ), array( 'response' => 403 ) );
            }

            // Collect payment data
            $payment_data = array(
                'price' => $purchase_data[ 'price' ],
                'date' => $purchase_data[ 'date' ],
                'user_email' => $purchase_data[ 'user_email' ],
                'purchase_key' => $purchase_data[ 'purchase_key' ],
                'currency' => edd_get_currency(),
                'downloads' => $purchase_data[ 'downloads' ],
                'user_info' => $purchase_data[ 'user_info' ],
                'cart_details' => $purchase_data[ 'cart_details' ],
                'gateway' => 'voguepay',
                'status' => 'pending'
            );

            // Add contact number if user is logged in.
            // if( is_user_logged_in() ) {
                // $user_ID = get_current_user_id();
                // Add contact number in user meta.
                // update_user_meta( $user_ID, '_edd_user_contact_info', $purchase_data[ 'post_data' ][ 'contact_number' ] );
            // }

            // Record the pending payment
            $payment = edd_insert_payment( $payment_data );

            // Check payment
            if( !$payment ) {
                // Record the error
                edd_record_gateway_error( __( 'Payment Error', 'edd-voguepay' ), sprintf( __( 'Payment creation failed before sending buyer to Voguepay. Payment data: %s', 'edd-voguepay' ), json_encode( $payment_data ) ), $payment );
                // Problems? send back
                edd_send_back_to_checkout( '?payment-mode=' . $purchase_data[ 'post_data' ][ 'edd-gateway' ] );
            } else {
                // Only send to voguepay if the pending payment is created successfully
                //$listener_url = add_query_arg( 'edd-listener', 'VOGUEPAY_RESPONSE', home_url( 'index.php' ) );
                // Get the success url
                $listener_url = add_query_arg(
                    array(
                    'edd-listener' => 'VOGUEPAY_RESPONSE',
                    'payment-id' => $payment
                    ), home_url( 'index.php' )
                );

                // Get the voguepay redirect uri
                $voguepay_redirect = trailingslashit( $this->get_voguepay_redirect() );

                // Merchant ID.
                $merchant_id = $edd_options[ 'v_merchant_id' ];

                // Merchant ID.
                $store_id = $edd_options[ 'store_id' ];

                // Generate merchant ref ID.
                $merchant_ref = $this->generate_merchant_refID();

                // Checksum Method.
                $checksum_method = 'MD5';

                /* Do currency conversion. */
                // $amount = $this->do_currency_conversion( $purchase_data[ 'price' ] );

                // Round up final amount and convert amount into paisa.
                $amount = $purchase_data[ 'price' ];

                //Get server IP address.
                $ip_address = gethostbyname( $_SERVER['SERVER_NAME'] );

                // String to generate checksum.
                $checksum_string = $edd_options[ 'voguepay_secret_key' ] . $merchant_id . '|' . $edd_options[ 'voguepay_apikey' ] . '|' . $ip_address . '|' . $merchant_ref . '|' . 'INR' . '|' . $amount . '|' . $checksum_method . '|' . 1;

                // Generate checksum.
                $checksum = md5( $checksum_string );
		
                // Setup voguepay arguments
                $voguepay_args = array(
                    'cur' => 'NGN',
                    'memo' => 'Secure Payment with VoguePay',
                    'total' => $amount,
                    'merchant_ref' => $merchant_ref,
                    'v_merchant_id' => $merchant_id,
                    'store_id' => $store_id,
					'success_url' => get_permalink( $edd_options[ 'success_page' ] ),
                    'fail_url' => edd_get_failed_transaction_uri( '?payment-id=' . $payment ),
                    'notify_url' => $listener_url
                );

                $voguepay_args = apply_filters( 'edd_voguepay_redirect_args', $voguepay_args, $purchase_data );

				echo '<div align="center"><br /><br /><br />';
				echo "<h3>...Redirecting. Click the image below if not automatically redirected</h3><br /><br /><br />";
                echo '<form action="' . $voguepay_redirect . '" method="POST" name="voguepayForm">';

                foreach( $voguepay_args as $arg => $arg_value ) {
                    echo '<input type="hidden" name="' . $arg . '" value="' . $arg_value . '">';
                }
				$seyeurl = plugins_url( 'assets/pay-via-voguepay.png' , __FILE__ );
				echo '<input type="image" src="'.$seyeurl.'" />';
                echo '</form></div>
                      <script language="JavaScript">
                         //  document.voguepayForm.submit();
                      </script>';
                die();
            }
        }

        /**
         * Get response from voguepay and then sends to the processing function.
         * @global $edd_options Array of all the EDD Options
         * @return void
         */
        public function get_response_from_voguepay() {
            global $edd_options;

            // Regular voguepay Response
            if( isset( $_GET[ 'edd-listener' ] ) && 'VOGUEPAY_RESPONSE' == $_GET[ 'edd-listener' ] ) {
                do_action( 'verify_voguepay_response' );
            }
        }

        /**
         * process voguepay response and then redirect to purchase confirmation page.
         * @global $edd_options Array of all the EDD Options
         * @return void
         */
        public function process_voguepay_response() {
            global $edd_options;

            // Get all response data coming from voguepay.
            $post_data = $_POST;

            // Get payment id.
            $payment_id = $_GET[ 'payment-id' ];

            // Get Payment status code.
            $payment_status_code = intval( $post_data[ 'status' ] );

            if( empty( $payment_id ) ) {
                return;
            }

            if( 'voguepay' != edd_get_payment_gateway( $payment_id ) ) {
                return; // this isn't a voguepay response.
            }

            // create payment note.
            $payment_note = sprintf( __( 'voguepay Reference ID: %s <br> Merchant Reference ID: %s', 'edd-voguepay' ), $post_data[ 'voguepay_refID' ], $post_data[ 'merchant_ref' ] );
            $payment_note .= '<br> Message: ' . $post_data[ 'status_msg' ];

            if( 0 == $payment_status_code ) {

                edd_insert_payment_note( $payment_id, $payment_note );
                edd_set_payment_transaction_id( $payment_id, $post_data[ 'voguepay_refID' ] );
                edd_update_payment_status( $payment_id, 'publish' );

                $confirm_url = add_query_arg(
                    array(
                    'payment-confirmation' => 'voguepay',
                    'payment-id' => $payment_id
                    ), get_permalink( $edd_options[ 'success_page' ] )
                );

                wp_redirect( $confirm_url );
            } else {

                edd_insert_payment_note( $payment_id, $payment_note );
                edd_set_payment_transaction_id( $payment_id, $post_data[ 'voguepay_refID' ] );
                edd_update_payment_status( $payment_id, 'failed' );

                wp_redirect( edd_get_failed_transaction_uri( '?payment-id=' . $payment_id ) );
            }

            die();
        }

        /**
         * Mark payments as Failed when returning to the Failed Transaction page
         * @return      void
         */
        public function voguepay_listen_for_failed_payments() {

            $failed_page = edd_get_option( 'failure_page', 0 );

            if( !empty( $failed_page ) && is_page( $failed_page ) && !empty( $_GET[ 'payment-id' ] ) ) {

                $payment_id = absint( $_GET[ 'payment-id' ] );

                if( !empty( $_POST ) ) {
                    // create payment note for failed transaction.
                    $payment_note = sprintf( __( 'voguepay Reference ID: %s <br> Merchant Reference ID: %s', 'edd-voguepay' ), $_POST[ 'voguepay_refID' ], $_POST[ 'merchant_ref' ] );
                    $payment_note .= '<br> Message: ' . $_POST[ 'status_msg' ];

                    edd_insert_payment_note( $payment_id, $payment_note );
                    edd_set_payment_transaction_id( $payment_id, $post_data[ 'voguepay_refID' ] );
                }
            }
        }

        /**
         * Check Open Exchange Rates APP ID is valid or not.
         * If valid then store and return currency rates.
         * Currency rates store in transient for 1 hour.
         * If not valid then return error message.
         * @global $edd_options
         * @return array
         */
        public function get_currency_rate() {
            global $edd_options;

            $exchangeRates = $appId = '';
            $return = array();

            // Check for app id.
            if( isset( $edd_options[ 'voguepay_openexchangerates_appid' ] ) && !empty( $edd_options[ 'voguepay_openexchangerates_appid' ] ) ) {
                $appId = $edd_options[ 'voguepay_openexchangerates_appid' ];
            } else {
                return $return = array(
                    "error" => true,
                    "message" => __( 'Need app id for currency conversion', 'edd-voguepay' ),
                );
            }

            // Get currency rates if exist.
            if( false === ( $exchangeRates = get_transient( '_rtp_currency_rates' ) ) ) {
                // It wasn't there, so get latest currency rates.

                $file = 'latest.json';
                $base = $edd_options[ 'currency' ];

                // Open CURL session:
                $ch = curl_init( "http://openexchangerates.org/api/{$file}?base={$base}&app_id={$appId}" );
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );

                // Get the data:
                $json = curl_exec( $ch );
                curl_close( $ch );

                // Decode JSON response:
                $exchangeRates = json_decode( $json );

                if( isset( $exchangeRates->error ) ) {
                    return $return = array(
                        "error" => true,
                        "message" => $exchangeRates->description,
                    );
                } else {
                    set_transient( '_rtp_currency_rates', $exchangeRates->rates, 1 * HOUR_IN_SECONDS );
                    $exchangeRates = $exchangeRates->rates;
                }
            }

            return $return = array(
                "success" => true,
                "rates" => $exchangeRates,
            );
        }

        /**
         * Doing currency conversion. For example convert USD amount to INR.
         * @param int   $amount Amount in.
         * @param string $currency Defualt is INR
         * @return $converted_amount Converted amount.
         */
        public function do_currency_conversion( $amount, $currency = 'INR' ) {
            global $edd_options;

            $converted_amount = $amount;
            if( isset( $edd_options[ 'currency' ] ) && 'INR' != $edd_options[ 'currency' ] ) {
                $exchangeRates = $this->get_currency_rate();
                if( isset( $exchangeRates[ 'success' ] ) ) {
                    $converted_amount = ( $amount * $exchangeRates[ 'rates' ]->$currency );
                }
                /**
                 * `edd_voguepay_currency_conversion` filter.
                 * Allow users to use different currency conversion api if store currency is not INR.
                 * $converted_amount amount after currency conversion
                 * $amount actual amount
                 * $base_currency is the store currency.
                 * $currency amount is converted into this currency.
                 */
                $base_currency = $edd_options[ 'currency' ];
                $converted_amount = apply_filters( 'edd_voguepay_currency_conversion', $converted_amount, $amount, $base_currency, $currency );
            }
            return $converted_amount;
        }

        /**
         * Display `Settings` link on plugin page (beside the activate/deactivate links).
         * @param array $action_links
         * @return array $action_links
         *
         */
        public function voguepay_action_links( $action_links ) {
            $settings = array(
                'settings' => '<a href="' . admin_url( 'edit.php?post_type=download&page=edd-settings&tab=gateways' ) . '">' . __( 'Settings', 'edd-voguepay' ) . '</a>'
            );
            $action_links = array_merge( $settings, $action_links );
            return $action_links;
        }

        /**
         * Clear currency conversion rates from transient.
         * @param type $old_value
         * @param type $value
         */
        public function clear_currency_rates( $old_value, $value ) {
            delete_transient( '_rtp_currency_rates' );
        }
		

    }

	 /**
         * Add `NGN` Nigerian Naira into EDD.
         * @param array   $currencies 
         * @return $currencies updated with Nigerian Naira
         */
		 
		 
		function seye_add_my_currency( $currencies ) {
				 $currencies['NGN'] = __( 'Nigerian Naira (&#8358;)', 'easy-digital-downloads' );
				 return $currencies;
			}
			
				
			function seye_add_ngn($currency) {
				// $currency = edd_update_option( 'currency', 'NGN' );
				$currency = 'NGN';
		
				// If it updated, let's update the global variable
					global $edd_options;
					$edd_options[ 'NGN' ] = 'NGN';
	
				/////////////
				
				return $currency;
			}
		    
			/**
         * Enable the naira currency symbol in EDD.
         * @param array   $currency, $symbol 
         * @return $symbol updated with Nigerian Naira
         */
		 
		 /**
		* 
		**/
	
		
			function seye_add_my_currency_symbol( $symbol, $currency ) {
			     switch( $currency ) {
			          case 'NGN': $symbol = '&#8358; '; break;
			     }
			     return $symbol;
			}
    /**
     * Instantiate Main Class
     */
    global $rtspg;
    $rtspg = new Voguepay_Payment_Gateway();
	
	
}
