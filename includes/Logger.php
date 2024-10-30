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
 * <p>Fluent logging interface</p>
 *
 * @author Matis
 */
class Woocommerce_Eabi_Telia_Mtasku_Logger {

    /**
     * Detailed debug information
     */
    const DEBUG = 100;

    /**
     * Interesting events
     *
     * Examples: User logs in, SQL logs.
     */
    const INFO = 200;

    /**
     * Uncommon events
     */
    const NOTICE = 250;

    /**
     * Exceptional occurrences that are not errors
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     */
    const WARNING = 300;

    /**
     * Runtime errors
     */
    const ERROR = 400;

    /**
     * Critical conditions
     *
     * Example: Application component unavailable, unexpected exception.
     */
    const CRITICAL = 500;

    /**
     * Action must be taken immediately
     *
     * Example: Entire website down, database unavailable, etc.
     * This should trigger the SMS alerts and wake you up.
     */
    const ALERT = 550;

    /**
     * Urgent alert.
     */
    const EMERGENCY = 600;

    private static $_isRequestLogged = false;

    /**
     *
     * @var WC_Logger
     */
    private static $log;


    protected $_logPrefix = '';

    protected $_isLogEnabled = false;

    protected $logFileName = 'WC_Eabi_mTasku_payment';
    /**
     *
     * @var int
     */
    protected $logLevel = self::DEBUG;


    /**
     * @var bool
     */
    protected $logPostRequests = false;


    protected static $levels = [
        self::DEBUG     => 'DEBUG',
        self::INFO      => 'INFO',
        self::NOTICE    => 'NOTICE',
        self::WARNING   => 'WARNING',
        self::ERROR     => 'ERROR',
        self::CRITICAL  => 'CRITICAL',
        self::ALERT     => 'ALERT',
        self::EMERGENCY => 'EMERGENCY',
    ];

    protected $sensitiveVariableNames = [];

    /**
     * <p>Returns true, if loggins is enabled for related payment method processor</p>
     * @return bool
     */
    protected function _isLogEnabled() {
        return $this->_isLogEnabled;
    }

    public function setIsLogEnabled($isLogEnabled) {
        $this->_isLogEnabled = (bool)$isLogEnabled;
        return $this;
    }

    public function getIsLogEnabled() {
        return $this->_isLogEnabled;
    }


    public function setLogPrefix($logPrefix) {
        $this->_logPrefix = $logPrefix;
        return $this;
    }

    public function getLogPrefix() {
        return $this->_logPrefix;
    }

    public function getLogFileName() {
        return $this->logFileName;
    }

    public function setLogFileName($logFileName) {
        $this->logFileName = $logFileName;
        return $this;
    }

    /**
     * @return int
     */
    public function getLogLevel()
    {
        return $this->logLevel;
    }

    /**
     * @return bool
     */
    public function getLogPostRequests()
    {
        return $this->logPostRequests;
    }

    /**
     * @param bool $logPostRequests
     * @return Woocommerce_Eabi_Telia_Mtasku_Logger
     */
    public function setLogPostRequests($logPostRequests)
    {
        $this->logPostRequests = (bool)$logPostRequests;
        return $this;
    }


    /**
     * @return array
     */
    public static function getLevels() {
        $levels = [];
        foreach (self::$levels as $i => $level) {
            $levels[(string)$i] = $level;
        }
        return $levels;
    }

    /**
     * @param int $logLevel
     * @return Woocommerce_Eabi_Telia_Mtasku_Logger
     */
    public function setLogLevel($logLevel)
    {
        if (is_string($logLevel)) {
             // Contains chars of all log levels and avoids using strtoupper() which may have
            // strange results depending on locale (for example, "i" will become "İ" in Turkish locale)
            $upper = strtr($logLevel, 'abcdefgilmnortuwy', 'ABCDEFGILMNORTUWY');
            if (defined(__CLASS__.'::'.$upper)) {
                $logLevel = constant(__CLASS__ . '::' . $upper);
            }
        }
        $this->logLevel = $logLevel;
        return $this;
    }

    /**
     * @return array
     */
    public function getSensitiveVariableNames()
    {
        return $this->sensitiveVariableNames;
    }

    /**
     * @param array $sensitiveVariableNames
     * @return Woocommerce_Eabi_Telia_Mtasku_Logger
     */
    public function setSensitiveVariableNames($sensitiveVariableNames)
    {
        $this->sensitiveVariableNames = $sensitiveVariableNames;
        return $this;
    }


    /**
     * <p>Returns current log file path, if WooCommerce is at least 2.2</p>
     * @return string|bool
     */
    public function getLogFilePath() {
        $path = false;
        if (function_exists('wc_get_log_file_path')) {
            $path = wc_get_log_file_path($this->getLogFileName() );
        }
        return $path;
    }

    /**
     * Clears the related log file
     * @return $this
     */
    public function clear() {
        if (is_null(self::$log)) {
            if (version_compare(WOOCOMMERCE_VERSION, '3.0', '>=')) {
                self::$log = new WC_Log_Handler_File();
            } else {
                self::$log = new WC_Logger();
            }
        }
        self::$log->clear($this->getLogFileName());
        return $this;
    }

    protected static function _log($data, $logPrefix, $level, $class, $actualLogLevel, $sensitiveVariables = [], $logPostRequests = false) {
        $postLevel = self::DEBUG;
        if (is_null(self::$log)) {
            if (version_compare(WOOCOMMERCE_VERSION, '3.0', '>=')) {
                self::$log = new WC_Log_Handler_File();
            } else {
                self::$log = new WC_Logger();
            }
        }

        if (!self::$_isRequestLogged && $logPostRequests) {
            //data is truncated to ~4kb and any non printable characters are cslashed
            //https://stackoverflow.com/questions/32190183/do-i-need-to-sanitize-user-data-for-error-log-in-php
            //this code runs only if Log level is DEBUG and this setting is enabled.
            //https://wiki.e-abi.ee/dokid/010430/en/AllowPOSTGETUserAgentcontentstob.html
            if ($actualLogLevel <= $postLevel) {
                if (isset($_POST) && count($_POST)) {
                    self::__addToLog(self::$log, $class,
                        'POST=' . self::_escapeFullPost(self::_removeSensitiveVariables($_POST, $logPrefix, $sensitiveVariables)), $postLevel);
                }
                if (isset($_GET) && count($_GET)) {
                    self::__addToLog(self::$log, $class,
                        'GET=' . self::_escapeFullPost(self::_removeSensitiveVariables($_GET, $logPrefix, $sensitiveVariables)),
                        $postLevel);
                }
                if (isset($_SERVER) && isset($_SERVER['HTTP_USER_AGENT'])) {
                    self::__addToLog(self::$log, $class, 'USER_AGENT=' . addcslashes(substr(print_r($_SERVER['HTTP_USER_AGENT'], true), 0, self::getMaxPostDataLength()), "\000..\037\177..\377\\"), $postLevel);
                }
            }
            self::$_isRequestLogged = true;
        }

        if ($data instanceof Exception) {
            $data = $data->__toString();
        }

//        self::$log->add($class, sprintf('%s: %s %s', $level, $logPrefix, print_r($data, true)));
        self::__addToLog(self::$log, $class, sprintf('%s %s', $logPrefix, print_r($data, true)), $level);
    }

    private static function getMaxPostDataLength() {
        $maxLength = (int)(256 * 15); //15 rows, 256 chars per row
        return $maxLength;
    }

    /**
     * Does print_r on the input array.
     * Limits the legnth to 256x15 bytes
     * encodes all non-printable and non-ASCII characters and double any original backshash.
     *
     * @param $fullPost
     */
    private static function _escapeFullPost($fullPost) {
        $maxLength = (int)(256 * 15); //15 rows, 256 chars per row
        $result = addcslashes(substr(print_r($fullPost, true), 0, self::getMaxPostDataLength()), "\000..\037\177..\377\\");
        return $result;
    }

    private static function __addToLog($log, $class, $data, $level = self::DEBUG) {
        if (version_compare(WOOCOMMERCE_VERSION, '3.0', '>=')) {
            $log->handle(time(), strtolower(self::$levels[$level]), $data, array('source' => $class));
        } else {
            $log->add($class, sprintf('%s: %s', strtoupper(self::$levels[$level]), $data));
        }
    }

    protected static function _removeSensitiveVariables($input, $logPrefix, array $sensitives = []) {
        $result = $input;
        if (!is_array($result)) {
            return $result;
        }
        foreach ($sensitives as $sensitive) {
            if (isset($result[$sensitive])) {
                $result[$sensitive] = '***';
            }
        }

        return $result;
    }

    protected function log($level = self::DEBUG, $arguments = []) {

        if (!count($arguments)) {
            //nothing to log
            return $this;
        }
        $firstArgument = array_shift($arguments);

        if ($firstArgument instanceof Exception) {
            $level = self::ERROR;
        }


        if ($this->_isLogEnabled() && $this->getLogLevel() <= $level)  {
            $logString = '';
            if (is_string($firstArgument)) {
                //handle like sprintf
                $logString = vsprintf($firstArgument, $this->_parseLogArguments($arguments));

            } else {
                //handle only first argument and ignore the rest
                $logString = $this->_handleLogArgument($firstArgument);

            }
            self::_log($logString, $this->_logPrefix, $level, $this->getLogFileName(), $this->getLogLevel(), $this->getSensitiveVariableNames(), $this->getLogPostRequests());
        }
        return $this;
    }

    /**
     * @param $dataToLog
     * @return $this
     */
    public function debug($dataToLog) {
        return $this->log(self::DEBUG, func_get_args());
    }

    /**
     * @param $dataToLog
     * @return $this
     */
    public function info($dataToLog) {
        return $this->log(self::INFO, func_get_args());
    }


    /**
     * @param $dataToLog
     * @return $this
     */
    public function notice($dataToLog) {
        return $this->log(self::NOTICE, func_get_args());
    }


    /**
     * @param $dataToLog
     * @return $this
     */
    public function warning($dataToLog) {
        return $this->log(self::WARNING, func_get_args());
    }


    /**
     * @param $dataToLog
     * @return $this
     */
    public function error($dataToLog) {
        return $this->log(self::ERROR, func_get_args());
    }


    /**
     * @param $dataToLog
     * @return $this
     */
    public function critical($dataToLog) {
        return $this->log(self::CRITICAL, func_get_args());
    }


    /**
     * @param $dataToLog
     * @return $this
     */
    public function alert($dataToLog) {
        return $this->log(self::ALERT, func_get_args());
    }


    /**
     * @param $dataToLog
     * @return $this
     */
    public function emergency($dataToLog) {
        return $this->log(self::EMERGENCY, func_get_args());
    }

    /**
     * @param $dataToLog
     * @return $this
     */
    public function logStackTrace($extraInfo = null) {
        $dataToLog = array();
        $dataToLog['info'] = $extraInfo;
        $stack = debug_backtrace();
        $dataToLog['stack'] = '';
        foreach ($stack as $key => $info) {
            $dataToLog['stack'] .= "#" . $key . " Called " . $info['function'] . " in " . (isset($info['file']) ? $info['file'] : '#UNKNOWN FILE#') . " on line " . (isset($info['line']) ? $info['line'] : '#UNKNOWN LINE#') . "\r\n";
        }

        return $this->debug($dataToLog);
    }

    /**
     * @param array $arguments
     * @return array
     */
    private function _parseLogArguments(array $arguments) {
        $results = [];

        foreach ($arguments as $argument) {
            $results[] = $this->_handleLogArgument($argument);
        }
        return $results;
    }

    /**
     * @param mixed $data
     * @return string
     */
    private function _handleLogArgument($data) {
        $result = null;
        if ($data instanceof \Exception) {
            $result = $data->__toString();

        } else if (!is_scalar($data)) {
            $result = print_r($data, true);
        } else {
            $result = $data;
        }

        return $result;
    }



}