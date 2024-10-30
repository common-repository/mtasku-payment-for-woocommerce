<?php
/*
  Plugin Name: mTasku payment for WooCommerce
  Plugin URI: https://en.e-abi.ee/mtasku-payment-for-woocommerce.html
  Description: Adds mobile wallet mTasku payment method to Woocommerce
  Version: 1.0.2
  Author: Matis Halmann, Aktsiamaailm LLC
  Copyright: (c) Aktsiamaailm LLC
  License: GNU General Public License (GPL) version 3
  License URI: http://www.gnu.org/licenses/gpl-3.0.txt
  WC requires at least: 3.0.0
  WC tested up to: 5.0.0
  TextDomain: woocommerce-payment-telia-mtasku
  Domain Path: /locale/

 *
 */

/*
 *  *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the OpenGPL v3 license (GNU Public License V3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://www.gnu.org/licenses/gpl-3.0.txt
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@e-abi.ee so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future.
 *
 * @category   WooCommmerce payment method
 * @package    mTasku payment for WooCommerce
 * @copyright  Copyright (c) 2023 Aktsiamaailm LLC (http://en.e-abi.ee/)
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt  GNU Public License V3.0
 * @author     Matis Halmann
 * 

 */


// Security check
if (!defined('ABSPATH')) {
    exit;
}

include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
if (is_plugin_active('woocommerce/woocommerce.php')) {

    function woocommerce_payment_eabi_telia_mtasku_init() {
        if (!class_exists('Woocommerce_Eabi_Telia_Mtasku_Api')) {
            require_once('includes/Api.php');
        }
        if (!class_exists('Woocommerce_Eabi_Telia_Mtasku_Exception')) {
            require_once('includes/Exception.php');
        }
        if (!class_exists('Woocommerce_Eabi_Telia_Mtasku_Logger')) {
            require_once('includes/Logger.php');
        }

        /**
         * Class Woocommerce_Eabi_Telia_ScriptLoader
         * Adds JS,CSS files
         * Adds template paths
         * Loads translations
         */
        class Woocommerce_Eabi_Telia_ScriptLoader {

            /**
             * Woocommerce_Eabi_Telia_ScriptLoader constructor.
             */
            public function __construct()
            {
                add_action('wp_enqueue_scripts', [$this, 'add_page_scripts']);
                add_filter( 'woocommerce_locate_template', [$this, 'locate_template'], 20, 3 );
                add_filter( 'woocommerce_locate_core_template', [$this, 'locate_template'], 20, 3 );

                add_filter('plugins_loaded', [$this, 'load_translations']);
            }


            /**
             *
             */
            public function load_translations() {
                $domain = 'woocommerce-payment-telia-mtasku';
                $locale = apply_filters('plugin_locale', get_locale(), $domain);

                load_textdomain($domain, WP_LANG_DIR . '/eabi-telia-mtasku/' . $domain . '-' . $locale . '.mo');
                load_plugin_textdomain($domain, false, dirname(plugin_basename(__FILE__)) . '/locale/');
            }


            /**
             *
             */
            public function add_page_scripts() {

                $suffix = defined('SCRIPT_DEBUG') && constant('SCRIPT_DEBUG') ? '' : '.min';


                //https://github.com/lrsjng/jquery-qrcode
                wp_register_script('jquery-qrcode-plugin', plugins_url('/m-assets/js/jquery-qrcode/jquery-qrcode.min.js', __FILE__), ['jquery'], false, true);

                wp_register_script('jquery-eabi-telia-mtasku-plugin', plugins_url('/m-assets/js/payment_form' . $suffix . '.js', __FILE__), ['jquery-qrcode-plugin'], false, true);


                wp_enqueue_script('jquery-eabi-telia-mtasku-plugin');
                wp_enqueue_style('eabi-telia-mtasku', $this->plugin_url() . '/m-assets/css/mtasku.css');
            }


            /**
             * Locates the WooCommerce template files from this plugin directory
             *
             * @param  string $template      Already found template
             * @param  string $template_name Searchable template name
             * @param  string $template_path Template path
             * @return string                Search result for the template
             */
            public function locate_template($template, $template_name, $template_path) {
                // Tmp holder
                $_template = $template;

                if (!$template_path) {
                    $template_path = WC_TEMPLATE_PATH;
                }

                // Set our base path
                $plugin_path = $this->plugin_path() . '/templates/';

                // Look within passed path within the theme - this is priority
                $template = locate_template(
                    array(
                        trailingslashit($template_path) . $template_name,
                        $template_name,
                    )
                );

                // Get the template from this plugin, if it exists
                if (!$template && file_exists($plugin_path . $template_name)) {
                    $template = $plugin_path . $template_name;
                }

                // Use default template
                if (!$template) {
                    $template = $_template;
                }

                // Return what we found
                return $template;
            }
            /**
             * Get the plugin path.
             * @return string
             */
            public function plugin_path($file = '') {
                if (!$file) {
                    $file = $this->_getFilePath();
                }
                return untrailingslashit(plugin_dir_path($file));
            }

            /**
             * @return string
             */
            protected function _getFilePath() {
                return __FILE__;
            }

            /**
             * Get the plugin url.
             * @return string
             */
            public function plugin_url() {
                return untrailingslashit(plugins_url('/', $this->_getFilePath()));
            }



        }

        /**
         * Class Woocommerce_Eabi_Telia_Mtasku_Base
         * Base class to ensure that $api and $logger variables could not be directly accessed
         *
         */
        abstract class Woocommerce_Eabi_Telia_Mtasku_Base extends WC_Payment_Gateway {
            protected $_plugin_text_domain = 'woocommerce-payment-telia-mtasku';

            const TRANSACTION_KEY = '_eabi_payment_transaction_id';

            const STATUS_WAIT_FOR_CLIENT = 'WAIT_FOR_CLIENT';
            const STATUS_CLIENT_READY = 'CLIENT_READY';
            const STATUS_WAIT_FOR_PAYMENT = 'WAIT_FOR_PAYMENT';
            const STATUS_IN_PAYMENT = 'IN_PAYMENT';
            const STATUS_WAIT_FOR_POS_PAYMENT_CONFIRMATION = 'WAIT_FOR_POS_PAYMENT_CONFIRMATION';
            const STATUS_PARTIAL_PAYMENT = 'PARTIAL_PAYMENT';
            const STATUS_WAIT_FOR_POS_SYSTEM = 'WAIT_FOR_POS_SYSTEM';
            const STATUS_LOYALTY_PRESENTED = 'LOYALTY_PRESENTED';
            const STATUS_REJECTED_BY_CLIENT = 'REJECTED_BY_CLIENT';
            const STATUS_PAYMENT_COMPLETE = 'PAYMENT_COMPLETE';
            const STATUS_CANCELLED = 'CANCELLED';
            const STATUS_REJECTED_BY_MERCHANT = 'REJECTED_BY_MERCHANT';


            const API_CHECK_TRANSACTION_URL_KEY = 'eabi_telia_mtasku_check_transaction_background';


            const MODE_LIVE = 'live';
            const MODE_TEST = 'test';

            private $urls = [
                'live' => [
                    'login_url' => 'https://makse.tapi.ee:8888/rest/pos/v1',
                    'api_url' => 'https://makse.tapi.ee:8888/rest/pos/v3',
                    'web_api_url' => 'https://makse.tapi.ee:8888/rest/pos/v1',
                    'allow_self_signed' => false,
                ],
                'test' => [
                    'login_url' => 'https://makse-prelive.tapi.ee:8888/rest/pos/v1',
                    'api_url' => 'https://makse-prelive.tapi.ee:8888/rest/pos/v3',
                    'web_api_url' => 'https://makse-prelive.tapi.ee:8888/rest/pos/v1',
                    'allow_self_signed' => true,
                ],
            ];


            /**
             * @var Woocommerce_Eabi_Telia_Mtasku_Api
             */
            private $api;
            /**
             * @var Woocommerce_Eabi_Telia_Mtasku_Logger
             */
            private $logger;


            /**
             * Woocommerce_Eabi_Telia_Mtasku_Base constructor.
             * Subclasses should use _construct() method instead.
             */
            final public function __construct()
            {
                $this->_construct();

                //we need this because get_title is using $this->title variable
//                $this->title = $this->get_option('title');

                $this->title = __('mTasku', $this->_plugin_text_domain);

                //we need this because get_description is using $this->description variable
//                $this->description = $this->get_option('description');
                $this->description = __('With mTasku, you can pay in e-shop without logging in to the Internet bank and entering bank card data. Move your plastic cards to your phone: www.mtasku.ee', $this->_plugin_text_domain);

                $this->method_description = __('Customers can pay with any payment card registered in the mTasku smartphone app', $this->_plugin_text_domain);

            }

            /**
             * Can be overwritten in subclasses
             */
            protected function _construct() {

            }

            /**
             * Initializes Logger object with proper settings and returns it.
             * Keeps logger in cache as singleton
             * @return Woocommerce_Eabi_Telia_Mtasku_Logger
             */
            public function getLogger() {
                if (!$this->logger) {
                    $logger = new Woocommerce_Eabi_Telia_Mtasku_Logger();

                    $sensitives = [
                        'woocommerce_' . $this->id . '_credential_live_username',
                        'woocommerce_' . $this->id . '_credential_live_password',
                        'woocommerce_' . $this->id . '_credential_live_terminal_id',
                        'woocommerce_' . $this->id . '_credential_live_ident_id',
                        'woocommerce_' . $this->id . '_credential_test_username',
                        'woocommerce_' . $this->id . '_credential_test_password',
                        'woocommerce_' . $this->id . '_credential_test_terminal_id',
                        'woocommerce_' . $this->id . '_credential_test_ident_id',
                        '_wpnonce',
                        '_wp_http_referer',
                    ];
                    $logger->setLogPrefix($this->id)->setLogFileName(get_class($this))
                        ->setIsLogEnabled($this->get_option('enable_log', 'no') == 'yes')
                        ->setLogLevel((int)$this->get_option('log_level'))
                        ->setSensitiveVariableNames($sensitives)
                        ->setLogPostRequests($this->get_option('log_post_requests', 'no') == 'yes')
                    ;

                    $this->logger = $logger;
                }
                return $this->logger;
            }



            /**
             * Initializes Api object and returns it.
             * Keeps Api object in cache as singleton.
             * @return Woocommerce_Eabi_Telia_Mtasku_Api
             */
            public function getApi() {
                if (!$this->api) {
                    $config = [];
                    $mode = $this->get_option('connection_mode');
                    foreach ($this->settings as $key => $value) {
                        $prefix = 'credential_' . $mode . '_';
                        if ($this->stringStartsWith($key, $prefix)) {
                            $config[substr($key, strlen($prefix))] = $value;
                        }


                    }
//                    $config['login_url'] = 'https://makse-prelive.tapi.ee:8888/rest/pos/v1';
//                    $config['api_url'] = 'https://makse-prelive.tapi.ee:8888/rest/pos/v3';
//                    $config['web_api_url'] = 'https://makse-prelive.tapi.ee:8888/rest/pos/v1';
//                    $config['allow_self_signed'] = true;
                    foreach ($this->urls[$mode] as $configKey => $configValue) {
                        $config[$configKey] = $configValue;
                    }

                    $api = new Woocommerce_Eabi_Telia_Mtasku_Api($this->getLogger(), $config);
                    $this->api = $api;

                }

                return $this->api;
            }

            protected function getErrorMessageTemplate($text = '') {
                return sprintf('<ul class="woocommerce-error" role="alert"><li>%s</li></ul>', esc_html($text));
            }


            /**
             * @param WC_Order $order
             * @param string $property
             * @return string|float|int|null
             */
            protected function _getWooOrderProperty($order, $property) {
                if (version_compare(WOOCOMMERCE_VERSION, '3.0', '>=')) {
                    if ($property == 'order_total') {
                        return $order->get_total();
                    }
                    $functionName = 'get_' . $property;
                    return $order->$functionName();
                } else {
                    return $order->$property;
                }
            }

            /**
             * @param string $haystack
             * @param string $needle
             * @return bool
             */
            public function stringStartsWith($haystack, $needle)
            {
                $length = strlen($needle);
                return (substr($haystack, 0, $length) === $needle);
            }


            /**
             * @param string $haystack
             * @param string $needle
             * @return bool
             */
            public function stringEndsWith($haystack, $needle)
            {
                $length = strlen($needle);
                if ($length == 0) {
                    return true;
                }

                return (substr($haystack, -$length) === $needle);
            }


            /**
             * @return WooCommerce
             */
            protected function _getWooCommerce() {
                global $woocommerce;
                return $woocommerce;
            }
            /**
             * @return wpdb
             */
            protected function _getWpdb() {
                global $wpdb;
                return $wpdb;
            }

            /**
             * Returns true, if test mode option is allowd
             * @return bool
             */
            protected function isTestModeAllowed() {
                return get_option('eabi_telia_mtasku_test_allowed', 'no') == 'yes';
            }

            /**
             * Forces connection mode always to be live if test mode is not allowed
             * @param string $key
             * @param null $empty_value
             * @return string
             */
            public function get_option($key, $empty_value = null)
            {
                if (!$this->isTestModeAllowed() && $key == 'connection_mode') {
                    return static::MODE_LIVE;
                }
                return parent::get_option($key, $empty_value);
            }

            public static function getVersion() {
                return '1.0.2';
            }

        }


        /**
         * Payment method that offers mTasku payment option to customers.
         * Class Woocommerce_Eabi_Telia_Mtasku
         */
        class Woocommerce_Eabi_Telia_Mtasku extends Woocommerce_Eabi_Telia_Mtasku_Base {
            public $id = 'eabi_telia_mtasku';


            /**
             *
             */
            protected function _construct()
            {
                $this->icon = apply_filters('woocommerce_' . $this->id . '_icon', plugins_url('/m-assets/images/icons/logo_mtasku.svg', __FILE__));

                // Load the form fields.
                $this->init_form_fields();

                // Load the settings.
                $this->init_settings();
//                $this->has_fields = true;

                $this->supports = [
                    'products',
                ];


                // Actions

                //
//                add_action('init', [&$this, 'check_banklink_response']);
                add_action('woocommerce_api_' . strtolower(get_class($this)), [$this, 'handle_payment_notification']);
                add_action('woocommerce_api_' . static::API_CHECK_TRANSACTION_URL_KEY, [$this, 'handle_transaction_status_check']);
                add_action('woocommerce_receipt_' . $this->id, [$this, 'after_order_placement_page']);
                add_action('woocommerce_update_options_payment_gateways', [$this, 'process_admin_options']);
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);

                //mark the order as paid on the success page
                add_action( 'woocommerce_before_thankyou', [$this, 'maybe_mark_order_paid_public']);

                //for some reason it does not show notices on order success page, so we add this manually
                add_action( 'woocommerce_before_thankyou', [$this, 'success_message_after_payment']);
            }

            /**
             * Listens for responses from Telia mTasku server for approved transactions.
             *
             */
            public function handle_payment_notification() {
                $logger = $this->getLogger();
                $logger->debug('received notification from outer server');
                $result = null;
                if (isset($_SERVER) && isset($_SERVER['HTTP_AUTHORIZATION']) && $this->stringStartsWith($_SERVER['HTTP_AUTHORIZATION'], 'Bearer ')) {
                    $authorizationParts = explode(' ', $_SERVER['HTTP_AUTHORIZATION']);
                    $bearerToken = $authorizationParts[1];

                    $postedData = @json_decode(file_get_contents('php://input'), true);
                    if ($postedData && is_array($postedData)) {
                        $transactionId = $postedData['transactionUUID'];
                        $status = $postedData['state'];
                        if ($postedData['state'] == self::STATUS_PAYMENT_COMPLETE) {
                            //locate the order
                            $order = $this->getOrderByTransactionId($transactionId);
                            if ($order) {
                                $logger->debug('found matching order with id: %s', $order->get_id());
                                if ($this->get_option('show_form') == 'before') {
                                    $logger->debug('Ignoring payment notification because before order confirmation generates new Order ID each time order submit button is pressed if the order is paid');
                                } else {
                                    if ($order->needs_payment()) {
                                        $logger->debug('marking order as paid: %s', $order->get_id());

                                        $result = $this->_maybe_mark_order_paid($order, $transactionId, $bearerToken);
                                    } else {
                                        $logger->debug('order is already marked as paid: %s', $order->get_id());
                                    }

                                }

                            }
                        }
                    } else {
                        $logger->error('invalid posted data from outer server');
                    }
                } else {
                    $logger->error('bearer token missing from outer server');
                }
                $logger->debug('done receiving notification from outer server');
                if ($result) {
                    echo '1';
                } else {
                    echo '0';
                }
                exit;
            }

            /**
             * Order success page does not have any option to display custom notices.
             * This method adds wc_print_notices to the top of the success page.
             * @param $order_id
             */
            public function success_message_after_payment($order_id)
            {

                wc_print_notices();
            }

            public function maybe_mark_order_paid_public($order_id) {
                $order = wc_get_order($order_id);
                if ($order->needs_payment() && isset($this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id)
                    && $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id == $order->get_meta(self::TRANSACTION_KEY)) {
                    $result = $this->_maybe_mark_order_paid($order, $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id);
                    if ($result) {
                        $this->_getWooCommerce()->cart->empty_cart();
                        $this->_clearDataFromSession();
                        wc_add_notice(__('Payment was successful', $this->_plugin_text_domain), 'success');
                    }
                }
            }

            /**
             * @param WC_Order $order
             * @param $transactionId
             */
            protected function _maybe_mark_order_paid($order, $transactionId, $bearerToken = null) {
                $result = true;
                if ($order->needs_payment() && $transactionId == $order->get_meta(self::TRANSACTION_KEY)) {
                    try {
                        if (!$bearerToken) {
                            $bearerToken = $this->obtainBearerToken();
                        }
                        $priceArgs = [
                            'currency' => 'EUR',
                            'decimals' => 2,
                        ];

                        $transaction = $this->getApi()->getWebTransactionStatus($bearerToken, $transactionId);
                        if ($transaction['state'] == static::STATUS_PAYMENT_COMPLETE) {
                            $order->payment_complete($transactionId);
                            $orderNote = [];
                            $orderNote[] = __('Transaction ID',
                                    $this->_plugin_text_domain) . ':' . $transactionId;

                            if (isset($transaction['paymentMethods']) && is_array($transaction['paymentMethods'])) {
                                foreach ($transaction['paymentMethods'] as $paymentMethod) {
                                    $orderNote[] = sprintf('%s - %s', $paymentMethod['paymentMethod'], wc_price($paymentMethod['amount'], $priceArgs));
                                }
                            }



                            $order->add_order_note(implode("\r\n", $orderNote));
                            $order->payment_complete($transactionId);
                        }


                    } catch (Woocommerce_Eabi_Telia_Mtasku_Exception $e) {
                        $result = false;
                    }
                }
                return $result;
            }


            /**
             * When QR code form is shown, then this function is called periodically to check for the related transaction status in the background.
             *
             */
            public function handle_transaction_status_check() {
                if (!isset($this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id) && !$this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id) {
                    $response = [
                        'status' => 'error',
                    ];
                    echo json_encode($response);
                    exit;
                }
                $logger = $this->getLogger();
                $api = $this->getApi();
                $transactionId = $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id;
                $bearerToken = $this->obtainBearerToken();
                $response = [
                    'status' => 'waiting',
                    'redirect' => null,
                ];
                $transaction = null;

                try {
                    $transaction = $api->getWebTransactionStatus($bearerToken, $transactionId);
                    if (in_array($transaction['state'], $this->getActiveTransactionStatuses())) {
                        //waiting for client
                        $response['status'] = 'waiting';

                    } else if ($transaction['state'] == static::STATUS_PAYMENT_COMPLETE) {
                        $response['status'] = 'complete';

                        if ($this->get_option('show_form') == 'after') {
                            $order = $this->getOrderByTransactionId($transactionId);
                            if ($order) {
                                $this->_maybe_mark_order_paid($order, $transactionId);
                                $result['result'] = 'success';
                                wc_add_notice(__('Payment was successful', $this->_plugin_text_domain), 'success');


                                //make redirect
                                $response['redirect'] = $this->get_return_url($order);


                            }

                        }



                    } else {
                        $response['status'] = 'error';

                    }

                } catch (Woocommerce_Eabi_Telia_Mtasku_Exception $e) {
                    $response['status'] = 'error';

                }

                echo json_encode($response);
                exit;



            }




            /**
             * @param $transactionId
             * @return WC_Order|null
             */
            protected function getOrderByTransactionId($transactionId) {
                $wpdb = $this->_getWpdb();
                $queryTemplate = "SELECT * FROM %s";
                $order = null;

                $sql = $wpdb->prepare(sprintf($queryTemplate,
                        $wpdb->postmeta) . ' WHERE meta_key = %s AND meta_value = %s',
                    [static::TRANSACTION_KEY, $transactionId]);

                $queryResult = $wpdb->get_row($sql, ARRAY_A);

                if ($queryResult && isset($queryResult['post_id']) && $queryResult['post_id']) {
                    $order = wc_get_order($queryResult['post_id']);
                }
                return $order;
            }


            /**
             * If show_form = after, then return success and redirect to get_checkout_payment_url
             * If show_form = before, then return failure, where the messages contain the HTML for the self-opening payment form.
             * If show_form = before and related transaction is paid, then return success and redirect to get_return_url(order)
             * @param int $order_id
             * @return array
             */
            public function process_payment($order_id)
            {
                $logger = $this->getLogger()
                    ->debug('called process payment for the order: %s', $order_id);
                $order = new WC_Order($order_id);
                $result = [
                    'result' => 'failure',
                    'messages' => '',
                ];


                if ($this->get_option('show_form') == 'before') {
                    //check the transaction status
                    try {
                        $logger->debug('before show form process');

                        $api = $this->getApi();


                        $bearerToken = $this->obtainBearerToken();
                        $transaction = null;
                        $logger->debug('transaction: %s',
                            $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id);

                        $this->initializeTransactionKey(false, $order);
                        if (!isset($this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id) || !$this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id) {
                            $logger->debug('no trasnasction key in session, making the payment form');
                            $result['messages'] = $this->generatePaymentForm($order);
                        } else {
                            $transaction = $api->getWebTransactionStatus($bearerToken,
                                $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id);
                            $logger->debug('polling current transaction: %s', $transaction['state']);


                            $statuses = [
                                static::STATUS_PAYMENT_COMPLETE,
                            ];

                            if ($transaction && in_array($transaction['state'], $statuses)) {
                                $this->_maybe_mark_order_paid($order, $transaction['transactionUUID']);
                                $result['result'] = 'success';
                                wc_add_notice(__('Payment was successful', $this->_plugin_text_domain), 'success');

                                $result['redirect'] = $this->get_return_url($order);
                            } else {
                                $result['messages'] = $this->generatePaymentForm($order);
                            }

                        }
                        $logger->debug('done called process payment for the order: %s, %s', $order_id, $result);


                        //result redirect is HTML


                        wp_send_json($result);
                    } catch (Woocommerce_Eabi_Telia_Mtasku_Exception $e) {
                        //not same transaction
                        $result['result'] = 'failure';
                        $result['messages'] = $this->getErrorMessageTemplate($e->getMessage());
                        wp_send_json($result);
                        return $result;
                    }


                } else {
                    return [
                        'result' => 'success',
                        'redirect' => $order->get_checkout_payment_url(true),
                    ];
                }

                return $result;
            }

            /**
             * If show_form = after, then this page shows the self-opening payment form.
             * If show_form = before, then this page is never shown. But it could be shown at merchants will?
             * @param int $order_id
             */
            public function after_order_placement_page($order_id) {
                $htmlResult = '<p>' . __('Thank you for the order, please click on the button to start the payment.', $this->_plugin_text_domain) . '</p>';
                $order = new WC_Order($order_id);
                $options = [
                    'confirmButtonLocation' => '#eabi-telia-mtasku-forward-handler',
                    'confirmButtonLocationIsGlobal' => true,
                    'errorMessageLocation' => '.woocommerce-notices-wrapper',
                    'errorMessageLocationIsGlobal' => true,
                    'allowRedirect' => true,
                ];
                $htmlResult .= sprintf('<input type="submit" id="eabi-telia-mtasku-payment-starter" class="button-alt" value="%s" />', __('Start payment', $this->_plugin_text_domain));

                $htmlResult .= sprintf('<a class="button cancel" href="%s">%s</a>', esc_url($order->get_cancel_order_url()), esc_html(__('Cancel order & restore cart', $this->_plugin_text_domain)));
                try {
                    $htmlResult .= $this->generatePaymentForm($order, $options);

                } catch (Woocommerce_Eabi_Telia_Mtasku_Exception $e) {
                    $htmlResult .= sprintf('<div class="woocommerce-notices-wrapper">%s</div>', $this->getErrorMessageTemplate($e->getMessage()));
                }
                $innerJs = <<<JS
(function defer() {
    if (window.jQuery) {
        jQuery('#eabi-telia-mtasku-payment-starter').on('click', function() {
            window.location.reload();
        });
    } else {
        setTimeout(function() { defer() }, 50);
    }
}());
JS;
                wc_enqueue_js($innerJs);




                 echo $htmlResult;

            }



            /**
             * Clears all mTasku related session variables after successful order placement and payment
             */
            protected function _clearDataFromSession() {
                $keys = [
                    'bearer_token',
                    'bearer_token_stamp',
                    'transaction_id',
                    'transaction_currency',
                    'transaction_amount',
                    'transaction_amount_authorized',
                    'transaction_qr_tag_url',
                    'transaction_qr_tag_uuid',
                ];
                foreach ($keys as $key) {
                    if (isset($this->_getWooCommerce()->session->{'eabi_telia_mtasku_' . $key})) {
                        unset($this->_getWooCommerce()->session->{'eabi_telia_mtasku_' . $key});
                    }
                }
            }



            /**
             * Returns self opening form.
             *
             * @param WC_Order $order
             * @param array $options allows overriding of some payment_form.js variables
             * @return string
             */
            protected function generatePaymentForm($order, array $options = []) {

                $logger = $this->getLogger();
                $html = '';

                $logger->debug('starting to generate payment form for order %s', $order->get_id());

                $transactionId = $this->initializeTransactionKey(false, $order);

                if (!$transactionId) {
                    //some error
                    throw new Woocommerce_Eabi_Telia_Mtasku_Exception(__('Transaction could not be initialized', $this->_plugin_text_domain));
                } else {
                    $formOptions = (
                        [
                            'imageSrc' => plugins_url('/m-assets/images/icons/logo_mtasku_no_border.svg', __FILE__),
                            'apiCheckUrl' => $this->_getWooCommerce()->api_request_url(static::API_CHECK_TRANSACTION_URL_KEY),
                            'qrTagUrl' => $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_qr_tag_url,
                            'cancelErrorText' => __('You canceled the payment', $this->_plugin_text_domain),
                            'cancelText' => __('Cancel', $this->_plugin_text_domain),
                            'startPaymentText' => __('Open mTasku app', $this->_plugin_text_domain),
                            'errorMessageLocation' => '.woocommerce-NoticeGroup-checkout',
                            'errorMessageLocationIsGlobal' => true,
                            'confirmButtonLocation' => 'form.woocommerce-checkout #place_order',
                            'confirmButtonLocationIsGlobal' => true,
                            'genericErrorText' => __('Error occurred with the payment, please try again', $this->_plugin_text_domain),
                            'allowRedirect' => false,
                        ]
                    );
                    $templateArguments = [
                        'formOptions' => array_merge($formOptions, $options),
                    ];

                    $templateResult = wc_get_template_html( 'checkout/eabi_telia_mtasku/payment_form.php', $templateArguments );
                    $html .= $templateResult;


                }


                return $html;
            }


            /**
             * @param bool $forceNew
             * @param WC_Order|null $order
             * @return bool
             */
            protected function initializeTransactionKey($forceNew = false, $order = null) {
                if ($this->isSameTransaction($order) && !$forceNew) {
                    return $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id;
                }
                //we do not have transaction key, we need to create it
                if ($order) {
                    $country = $order->get_billing_country();
                    $grandTotal = $order->get_total();
                    $reference = $order->get_order_number();
                    $totalAmountExVat = $order->get_total() - $order->get_total_tax();
                    $totalVat = $order->get_total_tax();
                } else {
                    $country = $this->_getWooCommerce()->customer->get_billing_country();
                    $grandTotal = $this->_getWooCommerce()->cart->total;
                    $reference = 'cart-' . time();
                    $totalAmountExVat = $this->_getWooCommerce()->cart->total - $this->_getWooCommerce()->cart->get_total_tax();

                    $totalVat = $this->_getWooCommerce()->cart->get_total_tax();
                }
                $currency = get_option('woocommerce_currency');
                if (!in_array($currency, $this->_getAllowedCurrencies())) {
                    $grandTotal = $this->_toTargetAmount($grandTotal, $currency);
                    $currency = $this->get_option('currency');
                    $totalAmountExVat = $this->_toTargetAmount($totalAmountExVat, $currency);
                    $totalVat = $this->_toTargetAmount($totalVat, $currency);
                }
                $api = $this->getApi();
                $logger = $this->getLogger();

                $logger->debug('starting to generate transaction for reference %s', $reference);
                $token = $this->obtainBearerToken();
                $terminalId = $api->getConfigData('terminal_id');
                $receiptNumber = $reference;
                $totalAmount = $grandTotal;
                $shopName = $this->get_option('shop_name');
                $callbackUrl = get_site_url(null, '/wc-api/' . strtolower(get_class($this)) . '/', 'https');
//                $callbackUrl = 'http://beverlyhills.ee.mhalmann.ddns.net/post-logger.php';

                $logger->debug('attempting to create transaction with arguments: %s, %s, %s, %s, %s, %s, %s, %s', $token, $terminalId, $receiptNumber, $totalAmount, $totalAmountExVat, $totalVat, $shopName, $callbackUrl);
                $transaction = false;

                try {
                    $items = null;
                    $discountAmount = null;
                    $items = [
                        [
                            'name' => sprintf($this->get_option('payment_message'), $order->get_order_number()),
                            'eanCode' => null,
                            'quantity' => 1,
                            'unit' => '',
                            'unitPrice' => $totalAmount,
                            'vatRate' => 0,
                            'vatAmount' => $totalVat,
                            'sumExVat' => $totalAmountExVat,
                            'sumWithDiscount' => $totalAmount,
                            'discountAmount' => 0,
                            'discounts' => [],
                        ],
                    ];
                    $vats = null;
                    $transaction = $api->createWebTransaction($token, $terminalId, $receiptNumber, $totalAmount,
                        $totalAmountExVat, $totalVat, $shopName, $callbackUrl, $discountAmount, $items, $vats);
                    $logger->debug('resulting transaction: %s', $transaction);

                } catch (Woocommerce_Eabi_Telia_Mtasku_Exception $e) {
                    //exception is still logged, but we do not want to show that to the customer
                    return false;
                }

                //save the transaction to extrainfo
                $this->_applyTransactionData($transaction, $grandTotal);
                $logger->debug('adding transaction to order: %s, %s', $order->get_id(), $transaction['transactionUUID']);
                if ($order) {
                    $order->add_meta_data(static::TRANSACTION_KEY, $transaction['transactionUUID'], true);
                    $order->save_meta_data();
                }
                $logger->debug('order meta saved');



                return $transaction['transactionUUID'];
            }

            /**
             * Applies transaction data to session
             * @param $transactionData
             * @param $quoteOrderGrandTotal
             */
            protected function _applyTransactionData($transactionData, $quoteOrderGrandTotal) {
                $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id = $transactionData['transactionUUID'];
                $transactionData['totalAmount'] = str_replace(',', '', $transactionData['totalAmount']);
                if ($quoteOrderGrandTotal <= 0 || $transactionData['totalAmount'] <= 0) {
                    throw new Exception('Total transaction amount is 0');
                }
                $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_amount = $quoteOrderGrandTotal;
                $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_amount_authorized = $transactionData['totalAmount'];
                $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_currency = $transactionData['currencyCode'] ? $transactionData['currencyCode'] :  'EUR';
                $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_qr_tag_url = $transactionData['qrTagUrl'];
                $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_qr_tag_uuid = $transactionData['qrTagUUID'];
            }


            /**
             * @param WC_Order|null $order
             * @return bool
             */
            protected function isSameTransaction($order = null) {
                $logger = $this->getLogger();
                $logger->debug('checking if transaction is same');
                if (!isset($this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id)) {
                    $logger->debug('transaction is not same because it is not set in session');
                    return false;
                }
                $cart = $this->_getWooCommerce()->cart;
                $customer = $this->_getWooCommerce()->customer;
                $grandTotal = $cart->total;
                $currency = get_option('woocommerce_currency');
                if (!in_array($currency, $this->_getAllowedCurrencies())) {
                    $grandTotal = $this->_toTargetAmount($grandTotal, $currency);
                    $currency = $this->_currency;
                }
                if (!isset($this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_amount) || round($this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_amount, 2) != round($grandTotal, 2)) {
                    $logger->debug('transaction is not same because amounts do not match: isset=%s, session amount %s vs  grand total amount%s', isset($this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_amount), round($this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_amount, 2), round($grandTotal, 2));
                    return false;
                }
                if (!isset($this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_currency) || $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_currency != $currency) {
                    $logger->debug('transaction is not same because currencies do not match: isset=%s, session currency %s vs  grand total amount%s', isset($this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_currency), $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_currency, $currency);

                    return false;
                }

                $bearerToken = $this->obtainBearerToken();
                $transaction = null;
                try {
                    $transaction = $this->getApi()->getWebTransactionStatus($bearerToken, $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id);

                } catch (Woocommerce_Eabi_Telia_Mtasku_Exception $e) {
                    //not same transaction
                    $logger->debug('there was an error getting the transaction from remote server');
                    return false;
                }

                if (!in_array($transaction['state'], array_merge($this->getActiveTransactionStatuses(), [static::STATUS_PAYMENT_COMPLETE]))) {
                    $logger->debug('transaction not same because state %s is not one of %s', $transaction['state'], implode(',', array_merge($this->getActiveTransactionStatuses(), [static::STATUS_PAYMENT_COMPLETE])));
                    return false;
                }
                if ($order) {
                    if ($order->get_meta(static::TRANSACTION_KEY) != $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id) {
                        $logger->debug('transaction not same because transaction key does not match. Order meta: %s, session: %s', $order->get_meta(static::TRANSACTION_KEY), $this->_getWooCommerce()->session->eabi_telia_mtasku_transaction_id);
                        return false;
                    }
                }
                $logger->debug('transaction is same');
                $logger->debug('done checking if transaction is same');


                return true;


            }


            /**
             * Returns list of transaction statuses, that are considered as the ones that customer is going to pay in near future
             * @return array
             */
            public function getActiveTransactionStatuses() {
                return [
                    static::STATUS_WAIT_FOR_CLIENT,
                    static::STATUS_WAIT_FOR_PAYMENT,
                    static::STATUS_WAIT_FOR_POS_PAYMENT_CONFIRMATION,
                    static::STATUS_WAIT_FOR_POS_SYSTEM,
                    static::STATUS_PARTIAL_PAYMENT,
                    static::STATUS_LOYALTY_PRESENTED,
                    static::STATUS_CLIENT_READY,
                    static::STATUS_IN_PAYMENT,
                ];
            }

            /**
             * <p>Returns array of allowed currency codes for this payment method</p>
             * @return array
             */
            protected function _getAllowedCurrencies() {
                $allowedCurrencies = array_filter(array_map('trim', explode(',', 'EUR')));
                return $allowedCurrencies;
            }


            /**
             * Obtains bearer token from session.
             * If session is empty, then bearer token is generated and injected into the session
             * @param false $reset Overwrite the token from the session
             * @return string
             */
            protected function obtainBearerToken($reset = false) {
                $api = $this->getApi();
                $session = $this->_getWooCommerce()->session;
                $time = time();
                $maxLength = (int)(10 * 60); //10 minutes
                if (!isset($session->eabi_telia_mtasku_bearer_token_stamp) || ($time - $session->eabi_telia_mtasku_bearer_token_stamp) >= $maxLength) {
                    $reset = true;
                }

                if (!isset($session->eabi_telia_mtasku_bearer_token) || $reset) {
                    $tokenData = $api->login();
                    $session->eabi_telia_mtasku_bearer_token = $tokenData['authToken'];
                    $session->eabi_telia_mtasku_bearer_token_stamp = (int)$time;

                }
                return $session->eabi_telia_mtasku_bearer_token;
            }


            /**
             * WooCommerce admin form field
             */
            public function init_form_fields()
            {
                $connectionModes = [
                    static::MODE_LIVE => __('Live mode', $this->_plugin_text_domain),
                    static::MODE_TEST => __('Test mode', $this->_plugin_text_domain),
                ];
                $agreementUrl = __('https://wiki.e-abi.ee/dokid/010430/en/AllowPOSTGETUserAgentcontentstob.html', $this->_plugin_text_domain);

                $this->form_fields = [
                    'enabled' => [
                        'title' => __('Enable/Disable', $this->_plugin_text_domain),
                        'type' => 'checkbox',
                        'label' => __('Enable mTasku payments', $this->_plugin_text_domain),
                        'default' => 'no',
                    ],
                    /*
                    'title' => [
                        'title' => __('Title', $this->_plugin_text_domain),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', $this->_plugin_text_domain),
                        'default' => __('mTasku', $this->_plugin_text_domain),
                    ],
                    */
                    /*
                    'description' => [
                        'title' => __('Description', $this->_plugin_text_domain),
                        'type' => 'textarea',
                        'description' => __('This controls the description which the user sees during checkout.', $this->_plugin_text_domain),
                        'default' => 'mTasku koondab endasse makse- ja kinkekaarte, laia valiku kliendikaarte, heategevuslikke annetuskaarte, transpordikaarti (Ühiskaart) ja palju muud. mTasku e-poe makselink annab võimaluse tasuda kauba eest kiirelt ja mugavalt sisestamata internetipanga paroole või pangakaardi andmeid.',
                    ],
                    */
                    'credential_live_username' => [
                        'title' => sprintf('%s %s', __('Merchant username/registration code', $this->_plugin_text_domain), __('[LIVE]', $this->_plugin_text_domain)),
                        'type' => 'text',
                        'description' => sprintf("<a href=\"%s\" target=\"_blank\">%s</a>", __('https://www.mtasku.ee/en/sooviavaldus/', $this->_plugin_text_domain), __('Live account credentials can be obtained here', $this->_plugin_text_domain)),
                        'default' => '',
                    ],
                    'credential_live_password' => [
                        'title' => sprintf('%s %s',__('Merchant password', $this->_plugin_text_domain), __('[LIVE]', $this->_plugin_text_domain)),
                        'type' => 'password',
                        'description' => sprintf("<a href=\"%s\" target=\"_blank\">%s</a>", __('https://www.mtasku.ee/en/sooviavaldus/', $this->_plugin_text_domain), __('Live account credentials can be obtained here', $this->_plugin_text_domain)),
                        'default' => '',
                    ],
                    'credential_live_terminal_id' => [
                        'title' => sprintf('%s %s', __('Merchant terminal ID', $this->_plugin_text_domain), __('[LIVE]', $this->_plugin_text_domain)),
                        'type' => 'text',
                        'description' => sprintf("<a href=\"%s\" target=\"_blank\">%s</a>", __('https://www.mtasku.ee/en/sooviavaldus/', $this->_plugin_text_domain), __('Live account credentials can be obtained here', $this->_plugin_text_domain)),
                        'default' => '',
                    ],
                    /*
                    'credential_live_ident_id' => [
                        'title' => sprintf('%s %s', __('Merchant IDENT terminal ID', $this->_plugin_text_domain), __('[LIVE]', $this->_plugin_text_domain)),
                        'type' => 'text',
                        'description' => sprintf("<a href=\"%s\" target=\"_blank\">%s</a>", __('https://www.mtasku.ee/en/sooviavaldus/', $this->_plugin_text_domain), __('Live account credentials can be obtained here', $this->_plugin_text_domain)),
                        'default' => '',
                    ],
                    */
                    'shop_name' => [
                        'title' => __('Shop name on some reports', $this->_plugin_text_domain),
                        'type' => 'text',
                        'default' => '',
                    ],
                    'payment_message' => [
                        'title' => __('Payment message on receipt', $this->_plugin_text_domain),
                        'type' => 'text',
                        'description' => sprintf(__('Default: %s, where %%s is replaced with order number', $this->_plugin_text_domain), __('Order %s fee', $this->_plugin_text_domain)),
                        'default' => __('Order %s fee', $this->_plugin_text_domain),
                    ],
                    'currency' => [
                        'title' => __('Accepted currency by this gateway', $this->_plugin_text_domain),
                        'type' => 'select',
                        'description' => __('Other currencies will be converted to accepted currency', $this->_plugin_text_domain),
                        'options' => get_woocommerce_currencies(),
                        'default' => 'EUR'
                    ],

                    'availability' => [
                        'title' => __('Method availability', $this->_plugin_text_domain),
                        'type' => 'select',
                        'default' => 'all',
                        'class' => 'availability',
                        'options' => [
                            'all' => __('All allowed countries', $this->_plugin_text_domain),
                            'specific' => __('Specific Countries', $this->_plugin_text_domain)
                        ]
                    ],
                    'countries' => [
                        'title' => __('Specific Countries', $this->_plugin_text_domain),
                        'type' => 'multiselect',
                        'class' => 'chosen_select',
                        'css' => 'width: 450px;',
                        'default' => $this->getSupportedCountries(),
                        'options' => $this->_getWooCommerce()->countries->countries
                    ],
                    'show_form' => [
                        'title' => __('Show payment QR code', $this->_plugin_text_domain),
                        'type' => 'select',
                        'default' => 'after',
                        'class' => '',
                        'options' => [
                            'after' => __('On separate page after customer confirms order', $this->_plugin_text_domain),
                            'before' => __('On checkout page (may not work for everyone)', $this->_plugin_text_domain)
                        ]
                    ],
                    'connection_mode' => array(
                        'title' => __('Payment method mode', $this->_plugin_text_domain),
                        'type' => 'select',
                        'options' => $connectionModes,
                        'default' => static::MODE_LIVE,
                    ),
                    'credential_test_username' => [
                        'title' => sprintf('%s %s', __('Merchant username/registration code', $this->_plugin_text_domain), __('[TEST]', $this->_plugin_text_domain)),
                        'type' => 'text',
                        'default' => '',
                    ],
                    'credential_test_password' => [
                        'title' => sprintf('%s %s', __('Merchant password', $this->_plugin_text_domain), __('[TEST]', $this->_plugin_text_domain)),
                        'type' => 'password',
                        'default' => '',
                    ],
                    'credential_test_terminal_id' => [
                        'title' => sprintf('%s %s', __('Merchant terminal ID', $this->_plugin_text_domain), __('[TEST]', $this->_plugin_text_domain)),
                        'type' => 'text',
                        'default' => '',
                    ],
                    /*
                    'credential_test_ident_id' => [
                        'title' => sprintf('%s %s',__('Merchant IDENT terminal ID', $this->_plugin_text_domain), __('[TEST]', $this->_plugin_text_domain)),
                        'type' => 'text',
                        'default' => '',
                    ],
                    */

                    'enable_log' => [
                        'title' => __('Enable logging', $this->_plugin_text_domain),
                        'type' => 'checkbox',
                        'label' => __('Enable logging', $this->_plugin_text_domain),
                        'default' => 'no'
                    ],
                    'log_level' => [
                        'title' => __('Log level', $this->_plugin_text_domain),
                        'type' => 'select',
                        'label' => __('Log level', $this->_plugin_text_domain),
                        'options' => Woocommerce_Eabi_Telia_Mtasku_Logger::getLevels(),
                        'default' => '400',
                    ],
                    'log_post_requests' => [
                        'title' => __('Allow POST/GET/UserAgent contents to be logged to the log file', $this->_plugin_text_domain),
                        'type' => 'checkbox',
                        'label' => sprintf(__('I confirm that I have read the %s and agree to them', $this->_plugin_text_domain), sprintf('<a href="%s" target="_blank">%s</a>', esc_html($agreementUrl), esc_html(__('terms and conditions', $this->_plugin_text_domain)))),
                        'description' => __('Log level DEBUG is required for this setting to be enabled', $this->_plugin_text_domain),
                        'default' => 'no'
                    ],

                ];

                if (!$this->isTestModeAllowed()) {
                    $removals = [
                        'connection_mode',
                        'credential_test_username',
                        'credential_test_password',
                        'credential_test_terminal_id',
                        'credential_test_ident_id',
                    ];
                    foreach ($removals as $removal) {
                        if (isset($this->form_fields[$removal])) {
                            unset($this->form_fields[$removal]);
                        }
                    }
                }
            }


            /**
             * Adds warning messages, if some settings are not OK
             * Adds information if the connection to mTasku server was ok.
             * @return bool
             */
            public function process_admin_options()
            {
                $result = parent::process_admin_options();
                $this->init_settings();
                $this->pingRemoteServer();

                //do any kind of API validation
                return $result;
            }


            /**
             * Adds warning messages, if some settings are not OK
             * Adds information if the connection to mTasku server was ok.
             */
            public function pingRemoteServer() {
                if ($this->get_option('enabled') == 'yes') {
                    $isUrlFopenEnabled = ini_get('allow_url_fopen');
                    if (!$isUrlFopenEnabled) {
                        WC_Admin_Settings::add_error(sprintf(__('Setting [%s] is disabled. This may cause errors in the plugin', $this->_plugin_text_domain), 'allow_url_fopen'));

                    }

                    //API
                    $api = $this->getApi();

                    $logger = $this->getLogger();
                    try {
                        $apiResponse = $api->login();
                        $logger->debug('api response: %s', $apiResponse);
                        WC_Admin_Settings::add_message(__('Connection to mTasku server was successful', $this->_plugin_text_domain));

                    } catch (Exception $e) {
                        WC_Admin_Settings::add_error(sprintf(__('Connection to mTasku server failed with message: %s', $this->_plugin_text_domain), $e->getMessage()));

                    }



                }

            }

            /**
             * <p>Returns the preferred locale for the offsite payment or credit card payment form</p>
             * @return string
             */
            protected function _getPreferredLocale() {
                $defaultLocale = 'et';
                $locale = $this->get_option('locale');
                if (defined('ICL_LANGUAGE_CODE') && ICL_LANGUAGE_CODE && strlen(ICL_LANGUAGE_CODE) == 2) {
                    $locale = ICL_LANGUAGE_CODE;
                }

                if ($locale) {
                    $localeParts = explode('_', $locale);
                    if (strlen($localeParts[0]) == 2) {
                        return strtolower($localeParts[0]);
                    } else {
                        return $defaultLocale;
                    }
                }
                return $defaultLocale;
            }

            /**
             *
             * @param WC_Order $order
             * @return string
             */
            protected function _getOrderConfirmationUrl($order) {
                $url = $order->get_checkout_payment_url(true);
                return $url;
            }

            /**
             * <p>Converts input to destined currency</p>
             * @param float $input
             * @param string $currency
             * @return float
             */
            protected function _toTargetAmount($input, $currency) {

                if ($currency == $this->get_option('currency')) {
                    return $input;
                }
                return round($input * $this->_getExchangeRate($currency, $this->get_option('currency')), 2);
            }

            /**
             * Copied from: http://stackoverflow.com/questions/13134574/how-to-do-usd-to-inr-currency-conversion-on-the-fly-woocommerce
             * @param string $from
             * @param string $to
             * @return float
             */
            protected function _getExchangeRate($from, $to) {

                //another json api
                $url = "https://free.currencyconverterapi.com/api/v5/convert?q=%s_%s&compact=y";

                $result = wp_remote_retrieve_body($response = wp_remote_get(sprintf($url, $from, $to))); // fetches the result from the url
                if (is_wp_error($response)) {
                    return 1;
                }

                $data = json_decode($result, true);
                $key = sprintf('%s_%s', $from, $to);
                if ($data && is_array($data) && isset($data[$key]) && isset($data[$key]['val']) && $data[$key]['val'] > 0) {
                    $q = (float) $data[$key]['val'];
                    return ( $q == 0 ) ? 1 : $q;
                }
                return 1;
            }

            /**
             * @return string[]
             */
            public function getSupportedCountries() {
                return ['EE'];
            }




        }
        new Woocommerce_Eabi_Telia_ScriptLoader();

    }


    function woocommerce_payment_eabi_telia_mtasku_addmethod($methods) {
        $methods[] = 'Woocommerce_Eabi_Telia_Mtasku';
        return $methods;
    }



    add_action('before_woocommerce_init', function () {
        if (class_exists("\Automattic\WooCommerce\Utilities\FeaturesUtil")) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__,
                true);
        }
    });

    add_action('before_woocommerce_init', function () {
        if (class_exists("\Automattic\WooCommerce\Utilities\FeaturesUtil")) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__,
                true);
        }
    });


    add_action('woocommerce_loaded', 'woocommerce_payment_eabi_telia_mtasku_init');

    add_action('woocommerce_payment_gateways', 'woocommerce_payment_eabi_telia_mtasku_addmethod');

}