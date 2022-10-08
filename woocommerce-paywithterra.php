<?php
/*
 * Plugin Name: PaywithTerra Payment Gateway
 * Plugin URI: https://github.com/paywithterra/woocommerce-paywithterra
 * Description: Take Terra payments on your WooCommerce store.
 * Author: PaywithTerra
 * Author URI: https://paywithterra.org
 * Version: 2.0.0
 * License: MIT
 */

use PaywithTerra\Exception\TxValidationException;
use PaywithTerra\TerraTxValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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

		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			error_log( 'WooCommerce is not installed or activated' );

			return;
		}

		// Load PaywithTerra PHP library
		$library_path = __DIR__ . DIRECTORY_SEPARATOR . 'php-backend-library/src/autoload-legacy.php';
		if ( ! file_exists( $library_path ) ) {
			error_log( 'PaywithTerra plugin is not installed properly (PaywithTerraClient not found)!' );

			return;
		}
		require_once $library_path;

		class WC_PaywithTerra_Token extends WC_Payment_Token {
			protected $type = 'PWT';
		}

		/**
		 * Data from frontend encapsulated in this class
		 */
		class WC_PaywithTerra_Callback_Data {

			public $memo;
			public $merchant_address;
			public $denom;
			public $tx_hash;

			public function __construct( $inputData = [] ) {

				$params = [ 'memo', 'merchantAddress', 'denom', 'txHash' ];
				foreach ( $params as $param ) {
					if ( ! isset( $inputData[ $param ] ) ) {
						wp_die( 'Missing parameter: ' . $param );
					}
				}

				$this->memo             = sanitize_text_field( $inputData['memo'] );
				$this->merchant_address = sanitize_text_field( $inputData['merchantAddress'] );
				$this->denom            = sanitize_text_field( $inputData['denom'] );
				$this->tx_hash          = sanitize_text_field( $inputData['txHash'] );
			}
		}

		class WC_PaywithTerra_Gateway extends WC_Payment_Gateway {

			public $address;
			public $network;
			public $prefix;
			public $denom;
			public $denom_rate;
			public $tx_link_template;

			private $disable_ssl_check = false;

			static $DEFAULT_DENOM = 'uluna';
			static $DEFAULT_DENOM_RATE = 1;
			static $DEFAULT_NETWORK = 'mainnet';
			static $TEST_NETWORK = 'testnet';
			static $DEFAULT_PREFIX = 'ORD-';
			static $DEFAULT_TX_LINK_TEMPLATE = 'https://finder.terra.money/{network}/tx/';

			/**
			 * Whether logging is enabled
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
				$this->icon              = $this->selected_icon_url(); // URL of the icon that will be displayed on checkout page
				$this->title             = $this->get_option( 'title' );
				$this->description       = $this->get_option( 'description' );
				$this->enabled           = $this->get_option( 'enabled' );
				$this->address           = $this->get_option( 'address' );
				$this->prefix            = $this->get_option( 'prefix', self::$DEFAULT_PREFIX );
				$this->network           = $this->get_option( 'network', self::$DEFAULT_NETWORK );
				$this->denom             = $this->get_option( 'denom', self::$DEFAULT_DENOM );
				$this->denom_rate        = $this->get_option( 'denom_rate', self::$DEFAULT_DENOM_RATE );
				$this->tx_link_template  = $this->get_option( 'tx_link_template', self::$DEFAULT_TX_LINK_TEMPLATE );
				$this->disable_ssl_check = $this->get_option( 'disable_ssl_check' ) === 'yes';
				self::$log_enabled       = ( "yes" === $this->get_option( 'log_enabled' ) );

				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
					$this,
					'process_admin_options'
				) );

				add_action( 'woocommerce_api_paywithterra', array( $this, 'webhook' ) );

				if ( 'yes' === $this->enabled ) {
					add_filter( 'woocommerce_thankyou_order_received_text', array(
						$this,
						'order_received_text'
					), 10, 2 );
				}
			}

			/**
			 * Plugin options
			 */
			public function init_form_fields() {
				// ! do not replace to "require_once" because it will be executed more than once
				$this->form_fields = require __DIR__ . '/includes/settings-paywithterra.php';
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
				if ( strlen( $this->address ) === 0 ) {
					wc_add_notice( 'PaywithTerra plugin is not configured properly!
				Please, contact website administrator', 'error' );

					return false;
				}

				return true;
			}

			/*
			 * No real payment processing here, just directing to a special page
			 */
			public function process_payment( $order_id ) {

				// Redirect to payment page
				return array(
					'result'   => 'success',
					'redirect' => $this->get_webhook_full_url( $order_id )
				);
			}

			/*
			 * Compile our webhook url
			 */
			protected function get_webhook_full_url( $order_id ) {
				return home_url( '/wc-api/' . $this->id . '?order=' . $order_id );
			}

			/*
			 * In case you need a webhook, like PayPal IPN etc
			 */
			public function webhook() {
				if ( ! isset( $_GET['order'] ) ) {
					$this->log( "Payment Webhook called without parameters", 'critical' );

					wp_die( 'Order parameter was not passed' );
				}

				$order_id = absint( sanitize_text_field( $_GET['order'] ) );

				$order = wc_get_order( $order_id );

				if ( ! $order ) {
					$this->log( "Payment Webhook called for non-existing order $order_id", 'critical' );

					wp_die( "Order $order_id was not found" );
				}

				$post_data_json = file_get_contents( "php://input" );
				$post_data      = json_decode( $post_data_json, true );

				if ( ! isset( $post_data['txHash'] ) ) {

					if ( $order->is_paid() ) {
						$this->log( "Payment Webhook called for already paid order $order_id", 'critical' );
						$this->redirect_to( $this->get_return_url( $order ) );
					}

					/**
					 * Payment step 1 (rendering payment page)
					 */
					$this->page_render( $order );

				} else {

					/**
					 * Process payment step 2 (payment confirmation)
					 */
					$this->process_payment_callback( $order, new WC_PaywithTerra_Callback_Data( $post_data ) );
				}
			}

			/**
			 * @param WC_Order $order
			 *
			 * @return void
			 */
			protected function page_render( $order ) {

				$template = plugin_dir_path( __FILE__ ) . 'assets/payment-page.html';
				$content  = file_get_contents( $template );

				$rendered_content = str_replace( [
					'{{order_id}}',
					'{{order_total}}',
					'{{order_prefix}}',
					'{{order_denom}}',
					'{{order_amount}}',
					'{{order_currency}}',
					'{{merchant_endpoint}}',
					'{{merchant_address}}',
					'{{network}}',
					'{{testnet_warning}}',
					'{{finder_tx_url}}',
				], [
					$order->get_id(),
					$order->get_total(),
					$this->prefix,
					$this->denom(),
					$this->calculate_uamount( $order->get_total() ),
					$order->get_currency(),
					$this->get_webhook_full_url( $order->get_id() ),
					$this->merchantAddress(),
					$this->network(),
					$this->showTestnetWarning() ? 'true' : 'false',
					$this->tx_link_finder(),
				], $content );

				$this->send_response_html( $rendered_content );
			}

			/**
			 * @param WC_Order $order
			 * @param WC_PaywithTerra_Callback_Data $incoming_data
			 *
			 * @return string
			 */
			protected function process_payment_callback( $order, $incoming_data ) {

				$client = $this->prepare_client();

				try {
					$client->lookupTx( $incoming_data->tx_hash );

					$client->assertTx( [
						"memo"   => $this->prefix . $order->get_id(),
						"denom"  => $this->denom(),
						"amount" => $this->calculate_uamount( $order->get_total() ),
					] );
				} catch ( TxValidationException $e ) {
					$this->send_response_json( [ 'error' => $e->getMessage() ], 400 );
				} catch ( \Exception $e ) {
					$this->send_response_json( [ 'error' => $e->getMessage() ], 500 );
				}

				/**
				 * If we are here, then transaction is valid
				 */

				// Mark order as payment complete
				$order->payment_complete();

				$terra_finder_link = esc_url( $this->tx_link( $incoming_data->tx_hash ) );

				$order_note = "Payment was captured. TxHash: " . $incoming_data->tx_hash;
				$order_note .= "\n";
				$order_note .= '<a href="' . $terra_finder_link . '" target="_blank">View on Terra Finder</a>';
				$order->add_order_note( $order_note );

				$this->send_response_json( [
					'success'  => true,
					'closeUrl' => $this->get_return_url( $order ),
				] );
			}

			protected function prepare_client() {
				$curlOptions = [
					CURLOPT_TIMEOUT => 30,
				];

				if ( $this->disable_ssl_check ) {
					$curlOptions[ CURLOPT_SSL_VERIFYPEER ] = false;
					$curlOptions[ CURLOPT_SSL_VERIFYHOST ] = false;
				}

				return new TerraTxValidator( [
					"networkName"     => $this->network(),
					"cache"           => new \PaywithTerra\Cache\FileCache(),
					"merchantAddress" => $this->merchantAddress(),
					"curlOptions"     => $curlOptions,
				] );
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
				return (int) round( $amount * $this->denom_rate * 1000000, 0, PHP_ROUND_HALF_UP );
			}

			/**
			 * @param mixed $response
			 *
			 * @return void
			 */
			protected function send_response_json( $response, $status_code = 200 ) {
				$this->send_response_headers( 'Content-Type: application/json', $status_code );
				echo json_encode( $response );
				exit;
			}

			protected function send_response_html( $response ) {
				$this->send_response_headers(
					"Content-Type: text/html; charset=" . get_option( 'blog_charset' ), 200 );
				echo $response;
				exit;
			}

			protected function send_response_headers( $primary_header, $status_code = 200 ) {
				if ( ! headers_sent() ) {
					send_origin_headers();
					send_nosniff_header();
					wc_nocache_headers();
					header( $primary_header );
					header( 'X-Robots-Tag: noindex' );
					status_header( $status_code );
				}
			}

			protected function redirect_to( $url ) {
				$this->send_response_headers( "Location: $url", 302 );
				exit;
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

			protected function denom() {
				return $this->denom;
			}

			protected function tx_link( $tx_hash ) {
				return $this->tx_link_finder() . $tx_hash;
			}

			protected function tx_link_finder() {
				return str_replace( '{network}', $this->network(), $this->tx_link_template );
			}

			protected function network() {
				return $this->network;
			}

			protected function showTestnetWarning() {
				return $this->network === self::$TEST_NETWORK;
			}

			protected function merchantAddress() {
				return $this->address;
			}

			protected function generate_paywithterra_form_asset_html( $key, $data ) {
				$field_key = $this->get_field_key( $key );
				$defaults  = array(
					'title'             => '',
					'disabled'          => false,
					'class'             => '',
					'css'               => '',
					'placeholder'       => '',
					'type'              => 'text',
					'desc_tip'          => false,
					'description'       => '',
					'custom_attributes' => array(),
				);

				$data = wp_parse_args( $data, $defaults );

				ob_start();
				?>
                <tr valign="top">
                    <th scope="row" class="titledesc">
                        <label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?><?php echo $this->get_tooltip_html( $data ); // WPCS: XSS ok. ?></label>
                    </th>
                    <td class="forminp">
                        <fieldset>
                            <legend class="screen-reader-text">
                                <span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
                            <input class="input-text regular-input <?php echo esc_attr( $data['class'] ); ?>"
                                   type="text" name="<?php echo esc_attr( $field_key ); ?>"
                                   id="<?php echo esc_attr( $field_key ); ?>"
                                   style="<?php echo esc_attr( $data['css'] ); ?>"
                                   value="<?php echo esc_attr( $this->get_option( $key ) ); ?>"
                                   placeholder="<?php echo esc_attr( $data['placeholder'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); // WPCS: XSS ok. ?> />
                            <p class="description">
                                Quick paste:
                                <a href="javascript:void(0)"
                                   onclick="document.querySelector('#<?php echo esc_attr( $field_key ); ?>').value='uluna'">Luna</a>,
                                <a href="javascript:void(0)"
                                   onclick="document.querySelector('#<?php echo esc_attr( $field_key ); ?>').value='ibc/CBF67A2BCF6CAE343FDF251E510C8E18C361FC02B23430C121116E0811835DEF'">axlUSDT</a>,
                                <a href="javascript:void(0)"
                                   onclick="document.querySelector('#<?php echo esc_attr( $field_key ); ?>').value='ibc/B3504E092456BA618CC28AC671A71FB08C6CA0FD0BE7C8A5B5A3E2DD933CC9E4'">axlUSDC</a>
                                <br>
                                See also: <a href="https://assets.terra.money/ibc/tokens.json" target="_blank">Full
                                    list</a>
                            </p>
                        </fieldset>
                    </td>
                </tr>
				<?php

				return ob_get_clean();
			}

			/**
			 * Custom order received text.
			 *
			 * @param string $text Default text.
			 * @param WC_Order $order Order data.
			 *
			 * @return string
			 */
			public function order_received_text( $text, $order ) {
				if ( $order && $order->is_paid() && $this->id === $order->get_payment_method() ) {
					return esc_html( 'Thank you for your payment.
					Your transaction has been registered, and a receipt for your purchase has been emailed to you.' );
				}

				return $text;
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
