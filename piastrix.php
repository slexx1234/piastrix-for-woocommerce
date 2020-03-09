<?php
/*
  Plugin Name: Piastrix
  Description: Piastrix Plugin for WooCommerce
  Plugin URI: https://github.com/slexx1234/piastrix-for-woocommerce
  Version: 1.0.0
  Author: slexx1234
  Author URI: https://slexx1234.netlify.com
  License: GPLv2
 */
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/* Add a custom payment class to WC
------------------------------------------------------------ */
add_action('plugins_loaded', 'woocommerce_piastrix', 0);
function woocommerce_piastrix() {
    load_plugin_textdomain( 'piastrix', false, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

    if (!class_exists('WC_Payment_Gateway'))
        return; // if the WC payment gateway class is not available, do nothing
    if (class_exists('WC_PIASTRIX'))
        return;
    class WC_PIASTRIX extends WC_Payment_Gateway{
        public function __construct() {

            $plugin_dir = plugin_dir_url(__FILE__);

            global $woocommerce;

            $this->id = 'piastrix';
            $this->icon = apply_filters('woocommerce_piastrix_icon', ''.$plugin_dir.'piastrix.png');
            $this->has_fields = false;

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->shop_id = $this->get_option('shop_id');
            $this->secret_key = $this->get_option('secret_key');
            $this->title = 'Piastrix';
            $this->description = __('Payment system Piastrix', 'piastrix');
            $this->method_description = __('Payment system Piastrix', 'piastrix');

            // Actions
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));

            // Save options
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // Payment listener/API hook
            add_action('woocommerce_api_wc_' . $this->id, array($this, 'callback'));
        }

        public function admin_options() {
            ?>
            <h3><?php _e('PIASTRIX', 'piastrix'); ?></h3>
            <p><?php _e('Setup payments parameters.', 'piastrix'); ?></p>

            <table class="form-table">

                <?php
                // Generate the HTML For the settings form.
                $this->generate_settings_html();
                ?>
            </table><!--/.form-table-->

            <?php
        } // End admin_options()

        function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'piastrix'),
                    'type' => 'checkbox',
                    'label' => __('Enabled', 'piastrix'),
                    'default' => 'yes'
                ),
                'shop_id' => array(
                    'title' => __('SHOP ID', 'piastrix'),
                    'type' => 'text',
                    'description' => __('Copy SHOP ID from your account page in piastrix system', 'piastrix'),
                    'default' => ''
                ),
                'secret_key' => array(
                    'title' => __('SECRET KEY', 'piastrix'),
                    'type' => 'text',
                    'description' => __('Copy SECRET KEY from your account page in piastrix system', 'piastrix'),
                    'default' => ''
                )

            );
        }

        /**
         * Generate form
         **/
        public function generate_form($order_id) {
            $order = new WC_Order( $order_id );

            $locale = get_locale() == 'ru_RU' ? 'ru' : 'en';
            $currency = 978;
            switch($order->get_order_currency()) {
                case 'RUB': $currency = 643; break;
                case 'USD': $currency = 840; break;
            }
            $params = array(
                'amount' => number_format($order->order_total, 2, '.', ''),
                'shop_id' => $this->shop_id,
                'currency' => $currency,
                'description' => __('Payment for Order №', 'piastrix') . $order_id,
                'shop_order_id' => $order_id,
                'callback_url' => $this->get_return_url($order),
            );

            $params['sign'] = hash('sha256', implode(':', array($params['amount'], $params['currency'], $params['shop_id'], $params['shop_order_id'])) . $this->secret_key);

            $form = '<form action="https://pay.piastrix.com/' . $locale . '/pay" method="POST" id="piastrix_form">';
            foreach($params as $key => $value) {
                $form .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
            }

            return $form .
                '<input type="submit" class="button alt" id="submit_piastrix_form" value="'.__('Pay', 'piastrix').'" /> ' .
                '<a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel payment and return back to card', 'piastrix').'</a>' .
                '</form>';
        }

        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id) {
            $order = new WC_Order($order_id);

            return array(
                'result' => 'success',
                'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
            );
        }

        function receipt_page($order) {
            echo '<p>'.__('Thank you for your order, press button to pay.', 'piastrix').'</p>';
            echo $this->generate_form($order);
        }

        function callback() {
            if (!isset($_POST['shop_order_id']) || !$_POST['shop_order_id']) {
                header('HTTP/1.1 400 Bad Request');
                return;
            }
            $order = new WC_Order( $_POST['shop_order_id'] );
            if (!$order->id) {
                header('HTTP/1.1 400 Bad Request');
                return;
            }

            if ($_POST['status'] == 'success') {
                $order->payment_complete();
            } else if ($_POST['status'] == 'rejected') {
                $order->update_status('failed', __('Payment error', 'piastrix'));
            }

            header('HTTP/1.1 200 OK');
            die();
        }
    }

    /**
     * Add the gateway to WooCommerce
     **/
    function add_piastrix_gateway($methods) {
        $methods[] = 'WC_PIASTRIX';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_piastrix_gateway');
}
?>
