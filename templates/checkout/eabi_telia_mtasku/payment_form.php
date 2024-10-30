<?php
/**
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

?>

<div class="form-block">
    <div style="z-index: 10000; position: fixed; display: block; top: 0px; left: 0px; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.8); opacity: 0.8;" >

    </div>
    <section class="eabi-mtasku-modal active">
        <div class="eabi-mtasku-mobile" id="eabi-telia-mtasku-payment-form" style="">
            <div data-role="qr-code"></div>
            <button class="btn green not-visible" data-role="start-button"></button>
            <button class="btn grey close" data-role="cancel-button"></button>
        </div>

        <script type="text/javascript">
            /* <![CDATA[ */
            (function defer() {
                var options = <?php echo json_encode($formOptions)?>;
                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.eabi_telia_mtasku_payment_form) {
                    jQuery('#eabi-telia-mtasku-payment-form').eabi_telia_mtasku_payment_form(options);
                } else {
                    setTimeout(function() { defer() }, 50);
                }
            }());
            /* ]]> */
        </script>
    </section>

</div>
