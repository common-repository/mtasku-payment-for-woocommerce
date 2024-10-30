<?php


/*
   *
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


/**
 * <p>Wrapper class for communicating with mTasku API</p>
 *
 * @author Matis
 */
class Woocommerce_Eabi_Telia_Mtasku_Api {
    const GET = 'GET';
    const PUT = 'PUT';
    const POST = 'POST';
    const DELETE = 'DELETE';

    protected $_plugin_text_domain = 'woocommerce-payment-telia-mtasku';

    /**
     * @var Woocommerce_Eabi_Telia_Mtasku_Logger
     */
    protected $logger;
    /**
     * @var array
     */
    protected $config;

    public function __construct(Woocommerce_Eabi_Telia_Mtasku_Logger $logger, array $config = []) {

        $this->logger = $logger;
        $this->config = $config;
    }


    /**
     * @param null $userName
     * @param null $password
     * @return mixed
     * @throws Woocommerce_Eabi_Telia_Mtasku_Exception
     */
    public function login($userName = null, $password = null) {
        $url = $this->getConfigData('login_url');
        $arguments = [
            'username' => $userName ? $userName : $this->getConfigData('username'),
            'password' => $password ? $password : $this->getConfigData('password'),
        ];
        return $this->_getRequest('/auth/login', self::POST, $arguments, $url);
    }


    /**
     * @param $bearerToken
     * @param $terminalId
     * @return mixed
     * @throws Woocommerce_Eabi_Telia_Mtasku_Exception
     */
    public function createTransaction($bearerToken, $terminalId) {
        $url = $this->getConfigData('api_url');
        $arguments = [
            'terminalId' => $terminalId,
        ];

        return $this->_getRequest('/transactions', self::POST, $arguments, $url, $bearerToken);
    }

    /**
     * @param $bearerToken
     * @param $transactionUUID
     * @return mixed
     * @throws Woocommerce_Eabi_Telia_Mtasku_Exception
     */
    public function cancelTransaction($bearerToken, $transactionUUID) {
        $url = $this->getConfigData('api_url');
        $arguments = [];

        return $this->_getRequest('/transactions/' . $transactionUUID, self::DELETE, $arguments, $url, $bearerToken);

    }

    /**
     * @param $bearerToken
     * @param $transactionUUID
     * @return mixed
     * @throws Woocommerce_Eabi_Telia_Mtasku_Exception
     */
    public function getTransaction($bearerToken, $transactionUUID) {
        $url = $this->getConfigData('api_url');
        $arguments = [];
        return $this->_getRequest('/transactions/' . $transactionUUID, self::GET, $arguments, $url, $bearerToken);

    }

    /**
     * @param $bearerToken
     * @param $transactionUUID
     * @param $terminalId
     * @param $amountToPay
     * @return mixed
     * @throws Woocommerce_Eabi_Telia_Mtasku_Exception
     */
    public function updateTransactionSimple($bearerToken, $transactionUUID, $terminalId, $amountToPay) {
        $arguments = [
            'terminalId' => $terminalId,
            'amountToPay' => (string)round($amountToPay, 2),
            'totalAmount' => (string)round($amountToPay, 2),
            'discountAmount' => (string)0,
            'totalVatAmount' => (string)0,
            'vats' => [],
            'items' => [],
            'discounts' => [],
        ];
        return $this->updateTransactionComplex($bearerToken, $transactionUUID, $arguments);
    }

    /**
     * @param $bearerToken
     * @param $transactionUUID
     * @param $transactionArguments
     * @return mixed
     * @throws Woocommerce_Eabi_Telia_Mtasku_Exception
     */
    public function updateTransactionComplex($bearerToken, $transactionUUID, $transactionArguments) {
        $url = $this->getConfigData('api_url');
        $arguments = $transactionArguments;
        return $this->_getRequest('/transactions/' . $transactionUUID, self::POST, $arguments, $url, $bearerToken);

    }

    /**
     * @param $bearerToken
     * @param $transactionUUID
     * @return mixed
     * @throws Woocommerce_Eabi_Telia_Mtasku_Exception
     */
    public function postPaymentCancelTransaction($bearerToken, $transactionUUID) {
        $url = $this->getConfigData('api_url');
        $arguments = [];
        return $this->_getRequest('/transactions/' . $transactionUUID . '/startcancellation', self::POST, $arguments, $url, $bearerToken);

    }


    /**
     * @param $bearerToken
     * @param $terminalId
     * @param $receiptNumber
     * @param $totalAmount
     * @param $totalAmountExVat
     * @param $totalVatAmount
     * @param $shopName
     * @param $callbackUrl
     * @return mixed
     * @throws Woocommerce_Eabi_Telia_Mtasku_Exception
     */
    public function createWebTransaction($bearerToken, $terminalId, $receiptNumber, $totalAmount, $totalAmountExVat, $totalVatAmount, $shopName, $callbackUrl, $discountAmount = null, array $items = null, array $vats = null) {
        $url = $this->getConfigData('web_api_url');
        $arguments = [
            'terminalId' => $terminalId,
            'receiptNumber' => $receiptNumber,
            'totalAmountExVat' => $totalAmountExVat,
            'totalVatAmount' => $totalVatAmount,
            'totalAmount' => $totalAmount,
            'discountAmount' => $discountAmount,
            'vats' => $vats,
            'items' => $items,
            'shopName' => $shopName,
            'callbackUrl' => $callbackUrl,
        ];
        return $this->_getRequest('/web/transactions', self::POST, $arguments, $url, $bearerToken);
    }


    /**
     * @param $bearerToken
     * @param $transactionUUID
     * @return mixed
     * @throws Woocommerce_Eabi_Telia_Mtasku_Exception
     */
    public function getWebTransactionStatus($bearerToken, $transactionUUID) {
        $url = $this->getConfigData('web_api_url');
        $arguments = [];
        return $this->_getRequest('/web/transactions/' . $transactionUUID, self::GET, $arguments, $url, $bearerToken);
    }


    /**
     * @param $field
     * @param string $empty_value
     * @return mixed|string
     */
    public function getConfigData($field, $empty_value = '') {
        if (isset($this->config[$field]) && !is_null($this->config[$field])) {
            return $this->config[$field];
        }
        return $empty_value;
    }


    /**
     * @param $request
     * @param string $method
     * @param array $params
     * @param null $url
     * @param null $bearerToken
     * @return mixed
     * @throws Woocommerce_Eabi_Telia_Mtasku_Exception
     */
    protected function _getRequest($request, $method = self::GET, $params = [], $url = null, $bearerToken = null) {
        if (!$url) {
            $url = $this->getConfigData('api_url');
        }
        $headers = [
            "User-Agent: Eabi_mTasku_WooCommerce_Http_Client/" . Woocommerce_Eabi_Telia_Mtasku::getVersion(),
            "Accept: application/json",
            "Content-type: application/json",
        ];
        if ($bearerToken) {
            $headers[] = sprintf("Authorization:Bearer %s", $bearerToken);
        }
        $options = [
            'http' => [
                'method' => $method,
                'ignore_errors' => true,
                'header' => '',
                'timeout' => $this->getConfigData('http_request_timeout') > 10 ? $this->getConfigData('http_request_timeout') : 10,
            ],
        ];
        if ($this->getConfigData('allow_self_signed')) {
            $options['ssl'] = ['verify_peer' => false, 'verify_peer_name' => false];
        }
        if ($method != self::GET && count($params)) {
            $options['http']['content'] = json_encode($params);
            $headers[] = 'Content-length: '. strlen($options['http']['content']);
        } else if (count($params)) {
            $request .= '?' . http_build_query($params);
        }
        $options['http']['header'] = implode("\r\n", $headers);
        $context = stream_context_create($options);

        $resp = file_get_contents($url . $request, false, $context);
        $dataToLog = array(
//            'options' => $options,
            'url' => $url . $request,
            'method' => $method,
            'headers' => $headers[0],
            'params' => $this->removeSensitiveVariables($params),
        );

        //used to remove username and password from url, if they are added there for making log not to contain sensitive data
        $dataToLog['url'] = preg_replace('/\/[[:alnum:]\-_]+\:[[:alnum:]\-_]+@/', '/***:***@', $dataToLog['url']);
        if (!isset($http_response_header)) {
            $http_response_header = [
                'HTTP/1.1 999 HEADERS WERE NOT PRESENT',
            ];
        }

        $decodeResult = @json_decode($resp, true);
        $responseCode = $this->getResponseCode($http_response_header[0]);

        if (!$decodeResult || !$this->isSuccessful($http_response_header[0])) {
            $dataToLog['response_headers'] = $http_response_header;
            if (!$decodeResult) {
                $dataToLog['response'] = $resp;
            } else {
                $dataToLog['response'] = $decodeResult;
            }
            $this->logger->logStackTrace('response failed with following stack trace');

            if ($decodeResult && isset($decodeResult['code'])) {
                $dataToLog['response'] = $decodeResult;
                $this->logger->error('error response: %s', $dataToLog);
                if (isset($decodeResult['message']) && $decodeResult['message']) {

                    throw new Woocommerce_Eabi_Telia_Mtasku_Exception(rtrim($decodeResult['message'], '.'), $responseCode);
                } else {
                    $dataToLog['response'] = $decodeResult;
                    $this->logger->error('error response: %s', $dataToLog);
                    throw new Woocommerce_Eabi_Telia_Mtasku_Exception(sprintf(__('Request failed with response: %s', $this->_plugin_text_domain), print_r($http_response_header, true) . print_r($resp, true)), $responseCode);
                }
            } else {
                $dataToLog['response'] = print_r($http_response_header, true) . print_r($resp, true);
                $this->logger->error('error response: %s', $dataToLog);
                if (!$resp) {
                    $lastError = error_get_last();
                    if (isset($lastError)) {
                        $resp = sprintf('%s in file %s on line %s',
                            $lastError['message'],
                            isset($lastError['file']) ? $lastError['file'] : 'UNKNOWN',
                            isset($lastError['line']) ? $lastError['line'] : 'UNKNOWN');
                    }
//                     $resp = print_r($lastError[0], true);

                }
                throw new Woocommerce_Eabi_Telia_Mtasku_Exception(sprintf(__('Request failed with response: %s', $this->_plugin_text_domain), print_r($http_response_header, true) . print_r($resp, true)), 0);
            }
        }
        $dataToLog['response'] = $decodeResult;
        $this->logger->debug('returned response: %s', $dataToLog);
        return $decodeResult;



    }


    /**
     * @param $input
     * @return mixed
     */
    protected function removeSensitiveVariables($input) {
        $sensitives = [
            'username', 'password',
        ];
        foreach ($sensitives as $sensitive) {
            if (isset($input[$sensitive])) {
                $input[$sensitive] = '***';
            }
        }
        return $input;
    }

    /**
     * @param $header
     * @return bool
     */
    protected function isSuccessful($header) {
        $matches = array();
        preg_match('#HTTP/\d+\.\d+ (\d+)#', $header, $matches);
        return $matches[1] >= 200 && $matches[1] < 300;
    }

    /**
     * @param $header
     * @return mixed
     */
    protected function getResponseCode($header) {
        $matches = array();
        preg_match('#HTTP/\d+\.\d+ (\d+)#', $header, $matches);
        return $matches[1];
    }

}