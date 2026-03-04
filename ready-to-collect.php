<?php
/**
 * Plugin Name: WooCommerce Ready to Collect Status
 * Description: Adds a "Ready to Collect" order status with email notification, compatible with YayMail.
 * Version: 1.0.0
 * Author: Liam O'Shannessy with Claude AI
 * License: MIT
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// -------------------------------------------------------------------------
// 1. REGISTER THE CUSTOM ORDER STATUS
// -------------------------------------------------------------------------

add_action( 'init', 'rtc_register_order_status' );
function rtc_register_order_status() {
    register_post_status( 'wc-ready-to-collect', array(
        'label'                     => _x( 'Ready to Collect', 'Order status', 'woocommerce' ),
        'public'                     => true,
        'exclude_from_search'        => false,
        'show_in_admin_all_list'     => true,
        'show_in_admin_status_list'  => true,
        'label_count'                => _n_noop(
            'Ready to Collect <span class="count">(%s)</span>',
            'Ready to Collect <span class="count">(%s)</span>'
        ),
    ) );
}

add_filter( 'wc_order_statuses', 'rtc_add_order_status' );
function rtc_add_order_status( $statuses ) {
    $new = array();
    foreach ( $statuses as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'wc-processing' === $key ) {
            $new['wc-ready-to-collect'] = _x( 'Ready to Collect', 'Order status', 'woocommerce' );
        }
    }
    return $new;
}

add_filter( 'woocommerce_order_is_paid_statuses', 'rtc_paid_status' );
function rtc_paid_status( $statuses ) {
    $statuses[] = 'ready-to-collect';
    return $statuses;
}

// -------------------------------------------------------------------------
// 2. WOOCOMMERCE EMAIL CLASS (handles actual sending)
// -------------------------------------------------------------------------

add_filter( 'woocommerce_email_classes', 'rtc_register_wc_email_class', 20 );
function rtc_register_wc_email_class( $email_classes ) {
    if ( ! class_exists( 'RTC_WC_Email' ) ) {
        class RTC_WC_Email extends WC_Email {
            public function __construct() {
                $this->id             = 'ready_to_collect';
                $this->title          = 'Ready to Collect';
                $this->description    = 'Sent to the customer when their order is marked as Ready to Collect.';
                $this->customer_email = true;
                $this->heading        = 'Your order is ready to collect!';
                $this->subject        = 'Your order #{order_number} is ready to collect';
                $this->placeholders   = array(
                    '{order_number}' => '',
                    '{order_date}'   => '',
                );
                add_action( 'woocommerce_order_status_ready-to-collect_notification', array( $this, 'trigger' ), 10, 2 );
                parent::__construct();
            }

            public function trigger( $order_id, $order = false ) {
                $this->setup_locale();
                if ( $order_id && ! is_a( $order, 'WC_Order' ) ) {
                    $order = wc_get_order( $order_id );
                }
                if ( is_a( $order, 'WC_Order' ) ) {
                    $this->object                         = $order;
                    $this->recipient                      = $order->get_billing_email();
                    $this->placeholders['{order_number}'] = $order->get_order_number();
                    $this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
                }
                if ( $this->is_enabled() && $this->get_recipient() ) {
                    $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
                }
                $this->restore_locale();
            }

            public function get_content_html() {
                ob_start();
                $order = $this->object;
                $email = $this;
                do_action( 'woocommerce_email_header', $this->get_heading(), $email );
                echo '<p>Hi ' . esc_html( $order->get_billing_first_name() ) . ',</p>';
                echo '<p>Your order is now <strong>ready to collect</strong>. Please visit us at your earliest convenience.</p>';
                do_action( 'woocommerce_email_order_details', $order, false, false, $email );
                do_action( 'woocommerce_email_order_meta', $order, false, false, $email );
                do_action( 'woocommerce_email_customer_details', $order, false, false, $email );
                do_action( 'woocommerce_email_footer', $email );
                return ob_get_clean();
            }

            public function get_content_plain() {
                $order = $this->object;
                return "Hi " . $order->get_billing_first_name() . ",\n\nYour order #" . $order->get_order_number() . " is now ready to collect.\n";
            }

            public function get_default_additional_content() {
                return 'Please bring your order confirmation and a valid ID when collecting.';
            }
        }
    }
    $email_classes['RTC_WC_Email'] = new RTC_WC_Email();
    return $email_classes;
}

// -------------------------------------------------------------------------
// 3. TRIGGER THE EMAIL ON STATUS CHANGE
// -------------------------------------------------------------------------

add_action( 'woocommerce_order_status_ready-to-collect', 'rtc_trigger_email', 10, 2 );
function rtc_trigger_email( $order_id, $order ) {
    $mailer = WC()->mailer();
    $mails  = $mailer->get_emails();
    if ( isset( $mails['RTC_WC_Email'] ) ) {
        $mails['RTC_WC_Email']->trigger( $order_id, $order );
    }
}

// -------------------------------------------------------------------------
// 4. YAYMAIL INTEGRATION — using the official YayMail BaseEmail API
// -------------------------------------------------------------------------

add_action( 'yaymail_register_emails', 'rtc_yaymail_register_email' );
function rtc_yaymail_register_email( $yaymail_emails ) {
    if ( ! class_exists( '\\YayMail\\Abstracts\\BaseEmail' ) ) return;

    if ( ! class_exists( 'RTC_YayMail_Email' ) ) {
        class RTC_YayMail_Email extends \YayMail\Abstracts\BaseEmail {

            private static $instance;

            public $email_types = [ YAYMAIL_WITH_ORDER_EMAILS ];

            public static function get_instance() {
                if ( null === static::$instance ) {
                    static::$instance = new static();
                }
                return static::$instance;
            }

            protected function __construct() {
                // Must match the id in RTC_WC_Email above
                $this->id        = 'ready_to_collect';
                $this->title     = 'Ready to Collect';
                $this->recipient = 'Customer';
                $this->source    = array(
                    'plugin_id'   => 'ready-to-collect',
                    'plugin_name' => 'WooCommerce Ready to Collect Status',
                );
            }

            public function get_default_elements() {
                return \YayMail\Elements\ElementsLoader::load_elements( array(
                    array( 'type' => 'Logo' ),
                    array(
                        'type'       => 'Heading',
                        'attributes' => array(
                            'rich_text' => 'Your order is ready to collect!',
                        ),
                    ),
                    array(
                        'type'       => 'Text',
                        'attributes' => array(
                            'rich_text' => '<p>Hi {{customer_first_name}},</p><p>Great news — your order <strong>#{{order_number}}</strong> is now <strong>ready to collect</strong>!</p><p>Please come in at your convenience. Don\'t forget to bring your order confirmation.</p>',
                        ),
                    ),
                    array( 'type' => 'Order' ),
                    array( 'type' => 'Footer' ),
                ) );
            }

            public function get_template_path() {
                return plugin_dir_path( __FILE__ ) . 'templates/ready-to-collect-email.php';
            }
        }
    }

    $yaymail_emails->register( RTC_YayMail_Email::get_instance() );
}
