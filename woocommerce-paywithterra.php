<?php
/*
 * Plugin Name: PaywithTerra Payment Gateway
 * Plugin URI: https://github.com/paywithterra/woocommerce-paywithterra
 * Description: Take Terra payments on your WooCommerce store.
 * Author: PaywithTerra
 * Author URI: https://paywithterra.com
 * Version: 1.0.5
 * License: MIT
 */

/*
 * This hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'paywithterra_add_gateway_class' );
if ( ! function_exists( 'paywithterra_add_gateway_class' ) ) {
	function paywithterra_add_gateway_class( $gateways ) {
		$gateways[] = 'WC_PaywithTerra_Gateway';

		return $gateways;
	}
}

add_action( 'plugins_loaded', 'paywithterra_init_gateway_class' );
if ( ! function_exists( 'paywithterra_init_gateway_class' ) ) {
	function paywithterra_init_gateway_class() {
		
		if(! class_exists('WC_Payment_Gateway')){
			error_log( 'WooCommerce is not installed or activated' );

			return;
		}

		// Load PaywithTerra PHP library
		$library_path = __DIR__ . DIRECTORY_SEPARATOR . 'php-api-library/src/PaywithTerraClient.php';
		if ( ! file_exists( $library_path ) ) {
			error_log( 'PaywithTerra plugin is not installed properly (PaywithTerraClient not found)!' );

			return;
		}
		require_once $library_path;

		class WC_PaywithTerra_Token extends WC_Payment_Token {
			protected $type = 'PWT';
		}

		class WC_PaywithTerra_Gateway extends WC_Payment_Gateway {

			public $address;
			private $private_key;

			/**
			 * Whether or not logging is enabled
			 * @var bool
			 */
			public static $log_enabled = false;

			/**
			 * Logger instance
			 *
			 * @var WC_Logger
			 */
			public static $log = false;

			/**
			 * Gateway class constructor
			 */
			public function __construct() {

				$this->id                 = 'paywithterra'; // payment gateway plugin ID
				$this->has_fields         = false; // in case you need a custom credit card form
				$this->method_title       = 'PaywithTerra';
				$this->method_description = 'Direct payments in Terra blockchain';

				$this->supports = array(
					'products'
				);

				// Method with all the options fields
				$this->init_form_fields();

				// Load the settings.
				$this->init_settings();
				$this->icon        = $this->selected_icon_url(); // URL of the icon that will be displayed on checkout page
				$this->title       = $this->get_option( 'title' );
				$this->description = $this->get_option( 'description' );
				$this->enabled     = $this->get_option( 'enabled' );
				$this->address     = $this->get_option( 'address' );
				$this->private_key = $this->get_option( 'private_key' );
				self::$log_enabled = ( "yes" === $this->get_option( 'log_enabled' ) );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
					$this,
					'process_admin_options'
				) );

				add_action( 'woocommerce_api_paywithterra', array( $this, 'webhook' ) );
			}

			/**
			 * Plugin options
			 */
			public function init_form_fields() {

				$this->form_fields = array(
					'private_key'     => array(
						'title'       => 'API Key (token)',
						'type'        => 'password',
						'description' => 'API key from your PaywithTerra <a target="_blank" href="https://paywithterra.com/account">account page</a>.',
					),
					'address'         => array(
						'title'       => 'Terra address',
						'type'        => 'text',
						'description' => 'Your shop wallet address on the Terra blockchain to receiving payments.',
					),
					'enabled'         => array(
						'title'       => 'Enable/Disable',
						'label'       => 'Enable PaywithTerra Gateway',
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no'
					),
					'title'           => array(
						'title'       => 'Title',
						'type'        => 'text',
						'description' => 'This controls the title which the user sees during checkout.',
						'default'     => 'PaywithTerra',
					),
					'description'     => array(
						'title'       => 'Description',
						'type'        => 'textarea',
						'description' => 'This controls the description which the user sees during checkout.',
						'default'     => '',
					),
					'log_enabled'     => array(
						'title'       => 'Error logging',
						'label'       => 'Enable logging for errors while interaction with PaywithTerra API',
						'type'        => 'checkbox',
						'description' => '',
						'default'     => 'no'
					),
					'icon'            => array(
						'title'       => 'Public icon',
						'type'        => 'select',
						'default'     => 'logo',
						'options'     => array(
							'logo'       => "PaywithTerra logo",
							'icon-rect'  => "PaywithTerra icon rectangle",
							'custom-url' => "Custom icon",
						),
						'description' => 'Shown on payment method selection page',
					),
					'custom_icon_url' => array(
						'title'       => 'Custom icon',
						'type'        => 'text',
						'placeholder' => 'https://',
						'description' => '<b>Full url</b> (from http) to custom payment icon image.',
						'default'     => '',
					),
				);

			}

			public function selected_icon_url() {
				$icon_option     = $this->get_option( 'icon' );
				$custom_icon_url = $this->get_option( 'custom_icon_url' );

				if ( $icon_option === 'custom-url' ) {
					if ( $custom_icon_url !== "" ) {
						return esc_attr( $custom_icon_url );
					} else {
						// Selected custom option but nothing specified - doing like simple "logo" selected
						$icon_option = "logo";
					}
				}

				$icon_option = esc_attr( $icon_option );

				return plugins_url( "/assets/images/$icon_option.svg", __FILE__ );
			}


			public function validate_fields() {

				// In case of plugin was activated but properly set up not completed
				if ( strlen( $this->address ) === 0 or strlen( $this->private_key ) === 0 ) {
					wc_add_notice( 'PaywithTerra plugin is not configured properly!
				Please, contact website administrator', 'error' );

					return false;
				}

				return true;
			}

			/*
			 * We're processing the payments request here
			 */
			public function process_payment( $order_id ) {

				global $woocommerce;

				$uuid = $this->get_or_create_remote_order_uuid( $order_id );

				if ( $uuid === false ) {
					return false;
				}

				// Empty cart
				$woocommerce->cart->empty_cart();

				// Redirect to payment page
				return array(
					'result'   => 'success',
					'redirect' => 'https://paywithterra.com/pay/' . $uuid
				);
			}

			/*
			 * In case you need a webhook, like PayPal IPN etc
			 */
			public function webhook() {

				$client = new PaywithTerra\PaywithTerraClient( $this->private_key );

				/*
				 * For WP Review team
				 * About: "you should only be attempting to process the items 
				 * within that are required for your plugin to function"
				 *
				 * We must create hash from all incoming data, because regarding server settings
				 * or requested mode (LIVE or TEST) - server may send different sets of fields. 
				 * So there is no exist static list of predefined fields.
				 * If we put to $client->checkIncomingData only whitelisted fields - resulting hash 
				 * may be different from the hash which calculated on server.
				 * 
				 * That's why we put to sanitizer and hash calculator all incoming data
				 */
				$data_sanitized = array_map( 'sanitize_text_field', $_POST );

				// Protection Layer 1 - checking correct hash
				$data_checked = $client->checkIncomingData( $data_sanitized );

				$memo          = $data_checked['memo'];
				$order_id      = (int) $memo;
				$input_address = $data_checked['address'];
				$input_amount  = (int) $data_checked['amount'];
				$input_denom   = $data_checked['denom'];
				$input_uuid    = $data_checked['uuid'];
				$txhash        = $data_checked['txhash'];


				$order = wc_get_order( $order_id );
				if ( ! $order ) {
					$this->log( "Webhook: order was not found by specified ID: $order_id", 'critical' );

					return;
				}

				$issued_tokens = $this->get_order_payment_tokens( $order );

				// Protection Layer 2 - remote order need to be issued from this system
				if ( ! in_array( $input_uuid, $issued_tokens ) ) {
					$order->add_order_note( "Order processing error: unissued uuid." );

					$this->log( "Webhook: remote order uuid was not issued: $input_uuid", 'critical' );

					return;
				}


				// Protection Layer 3 - order data must be same
				$order_amount = $this->calculate_uamount( $order->get_total() );
				if ( $order_amount !== $input_amount ) {
					$order->add_order_note( "Order processing error: wrong amount. $input_amount != $order_amount" );

					$this->log( "Webhook: amounts not equals: $input_amount != $order_amount", 'critical' );

					return;
				}

				if ( $order_denom = $this->currency_to_denom( $order->get_currency() ) !== $input_denom ) {
					$order->add_order_note( "Order processing error: wrong denom (currency)." );

					$this->log( "Webhook: denoms not equals: $input_denom != $order_denom", 'critical' );

					return;
				}
				if ( $input_address != $this->address ) {
					$order->add_order_note( "Tx payment address is not the same as in payment gateway settings." );

					$this->log( "Webhook: addresses not equals: $input_address != $this->address", 'critical' );

					return;
				}


				// Protection Layer 4 - doing "Second check" to request actual data from API
				$payment_result = $client->getOrderStatusByUUID( $input_uuid );
				$is_payed = (bool) $payment_result["is_payed"];

				if ( ! $is_payed ) {
					$order->add_order_note( "PaywithTerra API returned that order does not payed." );

					$this->log( "Webhook: second check failed", 'critical' );

					return;
				}

				// Mark order as payment complete
				$order->payment_complete();
				
				$terra_finder_link = esc_url($payment_result['tx_link']);

				$order_note = "Payment was captured. TxHash: " . $txhash;
				$order_note .= "\n";
				$order_note .= '<a href="'.$terra_finder_link.'" target="_blank">View on Terra Finder</a>';
				$order->add_order_note( $order_note );

			}

			/**
			 * Returns first actual payment token from database if exists
			 *
			 * @param WC_Order $order
			 *
			 * @return string|false
			 */
			protected function get_order_token( $order ) {
				$issued_uuids = $this->get_order_payment_tokens( $order );

				if ( count( $issued_uuids ) === 0 ) {
					return false;
				}

				$client = new PaywithTerra\PaywithTerraClient( $this->private_key );

				foreach ( $issued_uuids as $uuid ) {
					$order_data = $client->getOrderStatusByUUID( $uuid );
					if ( ! isset( $order_data['error'] ) ) {
						return $uuid;
					}
				}

				return false;
			}

			/**
			 * Returns payment tokens list as UUIDs - array
			 *
			 * @param $order
			 *
			 * @return array
			 */
			protected function get_order_payment_tokens( $order ) {
				$ids = $order->get_payment_tokens();
				if ( count( $ids ) === 0 ) {
					return array();
				}

				$uuids = array();

				foreach ( $ids as $token_id ) {
					$paymentToken = new WC_PaywithTerra_Token( $token_id );
					$uuids[]      = $paymentToken->get_token();
				}

				return $uuids;
			}

			/**
			 * Get payment token for order (create if not exists)
			 *
			 * @param $order_id
			 *
			 * @return false|mixed|string
			 */
			protected function get_or_create_remote_order_uuid( $order_id ) {
				$order = wc_get_order( $order_id );

				$exists_uuid = $this->get_order_token( $order );

				if ( $exists_uuid !== false ) {
					return $exists_uuid;
				}

				$client = new PaywithTerra\PaywithTerraClient( $this->private_key );

				$order_info = $client->createOrder( array(
					"address"    => $this->address,
					"memo"       => (string) $order->get_id(),
					"webhook"    => home_url( '/wc-api/' . $this->id . '/' ),
					"amount"     => $this->calculate_uamount( $order->get_total() ),
					"denom"      => $this->currency_to_denom( $order->get_currency() ), // Like "uusd"
					"return_url" => $this->get_return_url( $order )
				) );

				if ( ! in_array($response_code = (int) $client->getLastResponseCode(), array( 200, 201 ) ) ) {

					$err = "Unable to create PaywithTerra order. Code: $response_code";
					if ( isset( $order_info['message'] ) ) {
						$err .= $order_info['message'] . ' <br>';
						if ( isset( $order_info['errors'] ) ) {

							$plainErrors = array_map( function ( $er ) {
								return sanitize_text_field( reset( $er ) );
							}, $order_info['errors'] );

							$err .= implode( "<br>", $plainErrors );
						}
					}
					$err .= '<br>Please, contact website administrator';
					wc_add_notice( $err, 'error' );

					$this->log( json_encode( $order_info ), 'critical' );

					return false;

				}

				if ( ! isset( $order_info['uuid'] ) or strlen( $order_info['uuid'] ) === 0 ) {
					wc_add_notice( "Wrong response from PaywithTerra", 'error' );

					return false;
				}

				$uuid         = $order_info['uuid'];
				$paymentToken = new WC_PaywithTerra_Token();
				$paymentToken->set_token( $uuid );
				$paymentToken->set_gateway_id( $this->id );
				$paymentToken->save();

				$order->add_payment_token( $paymentToken );

				return $uuid;
			}

			/**
			 * Convert international currency symbol to Terra denom.
			 * USD -> uusd
			 *
			 * @param $currency
			 *
			 * @return string
			 */
			protected function currency_to_denom( $currency ) {
				return 'u' . strtolower( $currency );
			}

			/**
			 * Convert decimal amount to uAmount (multiplied by 1000000)
			 *
			 * @param $amount
			 *
			 * @return int
			 */
			protected function calculate_uamount( $amount ) {
				return (int) round( $amount * 1000000, 0, PHP_ROUND_HALF_UP );
			}


			/**
			 * Logging method.
			 *
			 * @param string $message Log message.
			 * @param string $level Optional. Default 'info'. Possible values:
			 *                      emergency|alert|critical|error|warning|notice|info|debug.
			 */
			public function log( $message, $level = 'info' ) {
				if ( self::$log_enabled ) {
					if ( empty( self::$log ) ) {
						self::$log = wc_get_logger();
					}
					self::$log->log( $level, $message, array( 'source' => $this->id ) );
				}
			}
		}
	}
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'paywithterra_plugin_action_links' );
if ( ! function_exists( 'paywithterra_plugin_action_links' ) ) {
	/**
	 * Show action links on the plugin screen.
	 *
	 * @param mixed $links Plugin Action links.
	 *
	 * @return array
	 */
	function paywithterra_plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=paywithterra' ) . '">Set up</a>',
		);

		return array_merge( $action_links, $links );
	}
}