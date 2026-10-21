<?php
/**
 * Plugin Name: WooCommerce Subscriptions CiviCRM Bridge
 * Plugin URI: https://github.com/your-repo/woocommerce-subscriptions-civicrm-bridge
 * Description: Bridges WooCommerce Subscriptions with CiviCRM membership management for renewal orders.
 * Version: 1.0.0
 * Author: Pixel Lab
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wcs-civicrm-bridge
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants.
define( 'WCS_CIVICRM_BRIDGE_VERSION', '1.0.0' );
define( 'WCS_CIVICRM_BRIDGE_PLUGIN_FILE', __FILE__ );
define( 'WCS_CIVICRM_BRIDGE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCS_CIVICRM_BRIDGE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class.
 */
class WCS_CiviCRM_Bridge {

    /**
     * CiviCRM contribution id stored on WooCommerce orders.
     *
     * @var string
     */
    const CONTRIBUTION_META_KEY = '_woocommerce_civicrm_contribution_id';

    /**
     * Single instance of the class.
     *
     * @var WCS_CiviCRM_Bridge
     */
    private static $instance = null;

    /**
     * WPCV checkout flags saved while a subscription order is first saved.
     *
     * Null when sync is not suppressed.
     *
     * @var array|null
     */
    private $suppressed_flags = null;

    /**
     * Get single instance of the class.
     *
     * @return WCS_CiviCRM_Bridge
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        
        add_action( 'plugins_loaded', array( $this, 'init' ) );
    }

    /**
     * Initialize the plugin.
     */
    public function init() {
        // Check dependencies.
        if ( ! $this->check_dependencies() ) {
            return;
        }

        // Load text domain.
        load_plugin_textdomain( 'wcs-civicrm-bridge', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        // Initialize hooks.
        $this->init_hooks();
    }

    /**
     * Check plugin dependencies.
     *
     * @return bool True if all dependencies are met.
     */
    private function check_dependencies() {
        $missing_deps = array();

        // Check for WooCommerce.
        if ( ! class_exists( 'WooCommerce' ) ) {
            $missing_deps[] = 'WooCommerce';
        }

        // Check for WooCommerce Subscriptions.
        if ( ! class_exists( 'WC_Subscriptions' ) ) {
            $missing_deps[] = 'WooCommerce Subscriptions';
        }

        // Check for WPCV WooCommerce CiviCRM Integration.
        if ( ! class_exists( 'WPCV_Woo_Civi' ) ) {
            $missing_deps[] = 'WPCV WooCommerce CiviCRM Integration';
        }


        if ( ! empty( $missing_deps ) ) {
            add_action( 'admin_notices', function() use ( $missing_deps ) {
                $message = sprintf(
                    __( 'WooCommerce Subscriptions CiviCRM Bridge requires the following plugins to be active: %s', 'wcs-civicrm-bridge' ),
                    implode( ', ', $missing_deps )
                );
                echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
            } );
            return false;
        }

        return true;
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // WPCV only saves CiviCRM product settings for types in this option.
        // When the option has never been saved, WordPress returns the default
        // and does not run the option_* filter, so both hooks are required.
        add_filter( 'option_woocommerce_civicrm_product_types_with_panel', array( $this, 'include_subscription_product_type' ) );
        add_filter( 'default_option_woocommerce_civicrm_product_types_with_panel', array( $this, 'include_subscription_product_type' ), 20 );

        // Do not copy the parent contribution onto the subscription or its renewals.
        add_filter( 'wc_subscriptions_subscription_data', array( $this, 'exclude_civicrm_contribution_meta' ) );
        add_filter( 'wc_subscriptions_renewal_order_data', array( $this, 'exclude_civicrm_contribution_meta' ) );

        // Renewal orders are fully built, including line items, before this fires.
        add_filter( 'wcs_renewal_order_created', array( $this, 'handle_renewal_order_created' ), 10, 2 );

        // The first save of a subscription order has no billing data or renewal
        // link yet. Pause WPCV around that hook so it does not create a contact.
        add_action( 'woocommerce_new_order', array( $this, 'suppress_wpcv_new_order_sync' ), 5, 2 );
        add_action( 'woocommerce_new_order', array( $this, 'restore_wpcv_new_order_sync' ), 99, 2 );
    }

    /**
     * Stop WPCV treating the first save of a subscription order as a new contact.
     *
     * WooCommerce Subscriptions saves the order before billing details, line
     * items, or the renewal relationship exist. Both WPCV new-order handlers
     * return early while their checkout flags are set.
     *
     * @param int           $order_id Order ID.
     * @param WC_Order|null $order    Order, when WooCommerce passes it.
     */
    public function suppress_wpcv_new_order_sync( $order_id, $order = null ) {
        if ( null !== $this->suppressed_flags ) {
            return;
        }

        if ( ! $order instanceof WC_Order ) {
            $order = function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : null;
        }

        if ( ! $order instanceof WC_Order || 'subscription' !== $order->get_created_via() ) {
            return;
        }

        if ( ! function_exists( 'WPCV_WCI' ) ) {
            return;
        }

        $wpcv = WPCV_WCI();
        if ( empty( $wpcv->contact ) || empty( $wpcv->order ) ) {
            return;
        }

        $this->suppressed_flags = array(
            'contact' => (bool) $wpcv->contact->is_checkout,
            'order'   => (bool) $wpcv->order->is_checkout,
        );

        $wpcv->contact->is_checkout = true;
        $wpcv->order->is_checkout   = true;

        error_log(
            sprintf(
                'WCS CiviCRM Bridge: Skipped WPCV contact sync for new subscription order #%d',
                $order->get_id()
            )
        );
    }

    /**
     * Restore the WPCV checkout flags after the new-order hook.
     */
    public function restore_wpcv_new_order_sync() {
        if ( null === $this->suppressed_flags || ! function_exists( 'WPCV_WCI' ) ) {
            return;
        }

        $wpcv = WPCV_WCI();
        if ( ! empty( $wpcv->contact ) ) {
            $wpcv->contact->is_checkout = $this->suppressed_flags['contact'];
        }
        if ( ! empty( $wpcv->order ) ) {
            $wpcv->order->is_checkout = $this->suppressed_flags['order'];
        }

        $this->suppressed_flags = null;
    }

    /**
     * Keep simple subscription products on the CiviCRM settings panel.
     *
     * WooCommerce Subscriptions shows the simple-product panel for the
     * subscription type, but WPCV drops those fields unless the type is listed.
     * Hooked to both option_* and default_option_* because an unsaved option
     * never runs the option_* filter.
     *
     * @param mixed $types Product types that show the CiviCRM settings panel.
     * @return array
     */
    public function include_subscription_product_type( $types ) {
        if ( ! is_array( $types ) ) {
            $types = array( 'simple' );
        }

        if ( ! in_array( 'subscription', $types, true ) ) {
            $types[] = 'subscription';
        }

        return $types;
    }

    /**
     * Remove the parent contribution id from data copied onto a subscription or renewal.
     *
     * @param array $data Order meta being copied.
     * @return array
     */
    public function exclude_civicrm_contribution_meta( $data ) {
        if ( ! is_array( $data ) ) {
            return $data;
        }

        unset( $data[ self::CONTRIBUTION_META_KEY ] );

        return $data;
    }

    /**
     * Create a new CiviCRM contribution for a subscription renewal order.
     *
     * The product's membership settings are included as a membership line item,
     * so CiviCRM creates a new membership. Payment completion then runs the
     * existing membership date adjustment.
     *
     * @param WC_Order        $renewal_order The renewal order object.
     * @param WC_Subscription $subscription  The subscription object.
     * @return WC_Order The renewal order object (required for filters).
     */
    public function handle_renewal_order_created( $renewal_order, $subscription = null ) {
        if ( ! $renewal_order instanceof WC_Order ) {
            return $renewal_order;
        }

        if ( ! function_exists( 'wcs_order_contains_renewal' ) || ! wcs_order_contains_renewal( $renewal_order ) ) {
            return $renewal_order;
        }

        // A copied parent contribution id would record this payment on the original membership.
        if ( $renewal_order->get_meta( self::CONTRIBUTION_META_KEY ) ) {
            $renewal_order->delete_meta_data( self::CONTRIBUTION_META_KEY );
            $renewal_order->save();
        }

        error_log(
            sprintf(
                'WCS CiviCRM Bridge: Called handle_renewal_order_created for renewal order #%d from subscription #%d',
                $renewal_order->get_id(),
                $subscription ? $subscription->get_id() : 'N/A'
            )
        );

        // Renewals are not checkout orders. WPCV only consults UFMatch when
        // is_checkout is set, and only after an email lookup misses. A matching
        // but wrong email is corrected later by the contribution params filter.
        $contact_handler = WPCV_WCI()->contact;
        $was_checkout    = (bool) $contact_handler->is_checkout;
        $contact_handler->is_checkout = true;

        try {
            $contribution = WPCV_WCI()->contribution->create_from_order( $renewal_order );
        } finally {
            $contact_handler->is_checkout = $was_checkout;
        }

        if ( $contribution ) {
            error_log(
                sprintf(
                    'WCS CiviCRM Bridge: Created/updated CiviCRM contribution #%d for renewal order #%d',
                    $contribution['id'],
                    $renewal_order->get_id()
                )
            );
        } else {
            error_log(
                sprintf(
                    'WCS CiviCRM Bridge: Failed to create/update CiviCRM contribution for renewal order #%d',
                    $renewal_order->get_id()
                )
            );
        }

        return $renewal_order;
    }

}

// Initialize the plugin.
WCS_CiviCRM_Bridge::get_instance();
