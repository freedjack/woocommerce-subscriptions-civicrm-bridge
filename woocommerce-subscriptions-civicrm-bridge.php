<?php
/**
 * Plugin Name: WooCommerce Subscriptions CiviCRM Bridge
 * Plugin URI: https://github.com/your-repo/woocommerce-subscriptions-civicrm-bridge
 * Description: Bridges WooCommerce Subscriptions with CiviCRM membership management for renewal orders.
 * Version: 1.0.0
 * Author: Your Name
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
     * Single instance of the class.
     *
     * @var WCS_CiviCRM_Bridge
     */
    private static $instance = null;

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
        error_log( 'WCS CiviCRM Bridge: Constructor' );
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
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

        // Log plugin initialization.
        error_log( 'WCS CiviCRM Bridge: Plugin initialized successfully' );
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
        // Hook into subscription order creation.
        add_action( 'woocommerce_checkout_create_order', array( $this, 'handle_subscription_order_creation' ), 10, 2 );
        add_action( 'woocommerce_new_order', array( $this, 'handle_new_subscription_order' ), 10, 2 );
        
        // Hook into renewal order completion.
        // add_action( 'woocommerce_renewal_order_payment_complete', array( $this, 'handle_renewal_order_completion' ), 10, 1 );
        // add_action( 'woocommerce_subscription_renewal_payment_complete', array( $this, 'handle_subscription_renewal_completion' ), 10, 2 );
        
        // Hook into subscription payment completion.
        // add_action( 'woocommerce_subscription_payment_complete', array( $this, 'handle_subscription_payment_complete' ), 10, 1 );
        //add_action( 'woocommerce_scheduled_subscription_payment', array( $this, 'handle_subscription_payment_complete' ), 5, 1 );

        
              
    //     // Hook into scheduled subscription payment (what gets triggered by our buttons)
    //     add_action( 'woocommerce_scheduled_subscription_payment', array( $this, 'log_scheduled_subscription_payment' ), 5, 1 );
        
    //     // Hook into subscription manager actions
    //     add_action( 'woocommerce_subscription_status_changed', array( $this, 'log_subscription_status_changed' ), 10, 3 );
        
    //     // Hook into payment gateway processing
    //     add_action( 'woocommerce_payment_complete', array( $this, 'log_payment_complete' ), 10, 1 );
    // }
    


        // Hook into order status changes for renewal orders.
        // add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_changed' ), 10, 3 );
        
        // Add admin menu for debugging.
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        
        // Add admin scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );


    // Hook into the filter
    add_filter( 'wpcv_woo_civi/contribution/create_from_order/params', array( $this, 'my_plugin_modify_contribution_params' ), 50, 2 );


    }

    /**
     * Handle subscription order creation during checkout.
     *
     * @param WC_Order $order The order object.
     * @param array    $data The checkout data.
     */
    public function handle_subscription_order_creation( $order, $data ) {
        error_log( sprintf( 'WCS CiviCRM Bridge: Called handle_subscription_order_creation for order #%d', $order->get_id() ) );
        // Check if this order contains subscription products.
        if ( ! $this->order_contains_subscription( $order ) ) {
            return;
        }

        error_log( sprintf( 'WCS CiviCRM Bridge: Subscription order #%d created during checkout', $order->get_id() ) );
        
        // Use WPCV function to create/update contribution
        $contribution = WPCV_WCI()->contribution->create_from_order( $order );
        if ( $contribution ) {
            error_log( sprintf( 'WCS CiviCRM Bridge: Created/updated CiviCRM contribution #%d for subscription order #%d', 
                $contribution['id'], $order->get_id() ) );
            
            // Process membership logic for subscription order
            $this->process_subscription_order( $order );
        } else {
            error_log( sprintf( 'WCS CiviCRM Bridge: Failed to create/update CiviCRM contribution for subscription order #%d', $order->get_id() ) );
        }
    }

    /**
     * Handle new subscription order creation.
     *
     * @param int      $order_id The order ID.
     * @param WC_Order $order The order object.
     */
    public function handle_new_subscription_order( $order_id, $order = null ) {
        error_log( sprintf( 'WCS CiviCRM Bridge: Called handle_new_subscription_order for order #%d', $order_id ) );
        // Sometimes the Order param is missing.
        if ( empty( $order ) ) {
            $order = wc_get_order( $order_id );
        }

        if ( ! $order ) {
            return;
        }

        // Check if this order contains subscription products.
        if ( ! $this->order_contains_subscription( $order ) ) {
            return;
        }

        error_log( sprintf( 'WCS CiviCRM Bridge: New subscription order #%d created', $order_id ) );
        
        // Use WPCV function to create/update contribution
        $contribution = WPCV_WCI()->contribution->create_from_order( $order );
        if ( $contribution ) {
            error_log( sprintf( 'WCS CiviCRM Bridge: Created/updated CiviCRM contribution #%d for new subscription order #%d', 
                $contribution['id'], $order_id ) );
            
            // Process membership logic for subscription order
            $this->process_subscription_order( $order );
        } else {
            error_log( sprintf( 'WCS CiviCRM Bridge: Failed to create/update CiviCRM contribution for new subscription order #%d', $order_id ) );
        }
    }


    /**
     * Modify CiviCRM contribution parameters before creating from WooCommerce order
     *
     * @param array  $params The params to be passed to the CiviCRM API
     * @param object $order  The WooCommerce Order object
     * @return array Modified parameters
     */
    public function my_plugin_modify_contribution_params( $params, $order ) {
        error_log( sprintf( 'WCS CiviCRM Bridge: Modifying contribution params for order #%d', $order->get_id() ) );
        
        // Get parent order if this is a renewal order
        $parent_order = $this->get_parent_order( $order );
        
        if ( $parent_order ) {
            error_log( sprintf( 'WCS CiviCRM Bridge: Found parent order #%d for renewal order #%d', 
                $parent_order->get_id(), $order->get_id() ) );
            
            // Get parent order line items
            $parent_line_items = $this->get_parent_order_line_items( $parent_order );
            
            if ( !empty( $parent_line_items ) ) {
                error_log( sprintf( 'WCS CiviCRM Bridge: Found %d line items in parent order #%d', 
                    count( $parent_line_items ), $parent_order->get_id() ) );
                
                // Initialize line_items array if not set
                if ( !isset( $params['line_items'] ) ) {
                    $params['line_items'] = [];
                }
                
                // Add parent order line items to params
                $params['line_items'] = array_merge( $params['line_items'], $parent_line_items );
                
                error_log( sprintf( 'WCS CiviCRM Bridge: Added parent order line items to contribution params. Total line items: %d', 
                    count( $params['line_items'] ) ) );
            } else {
                error_log( sprintf( 'WCS CiviCRM Bridge: No line items found in parent order #%d', $parent_order->get_id() ) );
            }
        } else {
            error_log( sprintf( 'WCS CiviCRM Bridge: No parent order found for order #%d', $order->get_id() ) );
        }
        
        return $params;
    }

    /**
     * Get the parent order for a renewal order.
     *
     * @param WC_Order $order The order object.
     * @return WC_Order|null The parent order object or null if not found.
     */
    private function get_parent_order( $order ) {
        // Check if this is a renewal order
        if ( ! $this->is_renewal_order( $order ) ) {
            return null;
        }
        
        // Try to get parent order ID from order meta
        $parent_order_id = $order->get_meta( '_subscription_renewal' );
        if ( $parent_order_id ) {
            $parent_order = wc_get_order( $parent_order_id );
            if ( $parent_order ) {
                return $parent_order;
            }
        }
        
        // Try to get parent order using WooCommerce Subscriptions function
        if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
            $subscriptions = wcs_get_subscriptions_for_order( $order );
            if ( !empty( $subscriptions ) ) {
                $subscription = reset( $subscriptions );
                $parent_orders = $subscription->get_related_orders( 'ids', 'parent' );
                if ( !empty( $parent_orders ) ) {
                    $parent_order_id = reset( $parent_orders );
                    $parent_order = wc_get_order( $parent_order_id );
                    if ( $parent_order ) {
                        return $parent_order;
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Get line items from parent order formatted for CiviCRM.
     *
     * @param WC_Order $parent_order The parent order object.
     * @return array Array of line items formatted for CiviCRM.
     */
    private function get_parent_order_line_items( $parent_order ) {
        $line_items = [];
        
        foreach ( $parent_order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            if ( ! $product ) {
                continue;
            }
            
            // Get product CiviCRM settings
            $entity_type = $product->get_meta( '_wpcv_woo_civicrm_entity_type' );
            $financial_type_id = $product->get_meta( '_woocommerce_civicrm_financial_type_id' );
            $pfv_id = $product->get_meta( '_woocommerce_civicrm_pfv_id' );
            
            // Skip if no CiviCRM settings
            if ( empty( $entity_type ) || empty( $financial_type_id ) ) {
                continue;
            }
            
            // Build line item for CiviCRM
            $line_item = [
                'entity_table' => 'civicrm_contribution',
                'entity_id' => '', // Will be set when contribution is created
                'price_field_id' => $pfv_id,
                'label' => $item->get_name(),
                'qty' => $item->get_quantity(),
                'unit_price' => $item->get_total() / $item->get_quantity(),
                'line_total' => $item->get_total(),
                'financial_type_id' => $financial_type_id,
                'non_deductible_amount' => 0,
                'tax_amount' => $item->get_total_tax(),
            ];
            
            // Add entity-specific data
            if ( $entity_type === 'contribution' ) {
                $line_item['contribution_id'] = ''; // Will be set when contribution is created
            } elseif ( $entity_type === 'membership' ) {
                $membership_type_id = $product->get_meta( '_woocommerce_civicrm_membership_type_id' );
                if ( $membership_type_id ) {
                    $line_item['membership_type_id'] = $membership_type_id;
                }
            } elseif ( $entity_type === 'participant' ) {
                $event_id = $product->get_meta( '_woocommerce_civicrm_event_id' );
                $participant_role_id = $product->get_meta( '_woocommerce_civicrm_participant_role_id' );
                if ( $event_id ) {
                    $line_item['event_id'] = $event_id;
                }
                if ( $participant_role_id ) {
                    $line_item['participant_role_id'] = $participant_role_id;
                }
            }
            
            $line_items[] = $line_item;
            
            error_log( sprintf( 'WCS CiviCRM Bridge: Added line item for product #%d (%s) from parent order #%d', 
                $product->get_id(), $item->get_name(), $parent_order->get_id() ) );
        }
        
        return $line_items;
    }

    /**
     * Check if an order contains subscription products.
     *
     * @param WC_Order $order The order object.
     * @return bool True if the order contains subscription products.
     */
    private function order_contains_subscription( $order ) {
        // Check using WooCommerce Subscriptions function.
        // if ( function_exists( 'wcs_order_contains_subscription' ) ) {
        //     return wcs_order_contains_subscription( $order );
        // }

        // Fallback: check order items for subscription products.
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            error_log( sprintf( 'WCS CiviCRM Bridge: Checking if product #%d is a subscription', $product->get_type() ) );
            if ( $product && $product->is_type( 'subscription' ) ) {
                return true;
            }
        }

        return true;
      //  return false;
    }

    /**
     * Process a subscription order for CiviCRM membership.
     *
     * @param WC_Order $order The subscription order.
     */
    private function process_subscription_order( $order ) {
        error_log( sprintf( 'WCS CiviCRM Bridge: Processing subscription order #%d', $order->get_id() ) );

        // Get the associated CiviCRM contribution.
        $contribution = $this->get_contribution_for_order( $order );
        if ( ! $contribution ) {
            error_log( sprintf( 'WCS CiviCRM Bridge: No CiviCRM contribution found for subscription order #%d', $order->get_id() ) );
            return;
        }

        error_log( sprintf( 'WCS CiviCRM Bridge: Found CiviCRM contribution #%d for subscription order #%d', 
            $contribution['id'], $order->get_id() ) );

        // Check each product in the order.
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product ) {
                continue;
            }

            $this->check_product_membership_association( $product, $contribution, $order );
        }
    }

    /**
     * Handle renewal order payment completion.
     *
     * @param int $renewal_order_id The renewal order ID.
     */
    public function handle_renewal_order_completion( $renewal_order_id ) {
        error_log( sprintf( 'WCS CiviCRM Bridge: Renewal order #%d payment completed', $renewal_order_id ) );
        
        $order = wc_get_order( $renewal_order_id );

        error_log( sprintf( 'WCS CiviCRM Bridge: Found order #%d', $order->get_id() ) );
        if ( ! $order ) {
            error_log( sprintf( 'WCS CiviCRM Bridge: Could not load renewal order #%d', $renewal_order_id ) );
            return;
        }

        // Use WPCV function to create/update contribution
        $contribution = WPCV_WCI()->contribution->create_from_order( $order );
        if ( $contribution ) {
            error_log( sprintf( 'WCS CiviCRM Bridge: Created/updated CiviCRM contribution #%d for renewal order #%d', 
                $contribution['id'], $renewal_order_id ) );
            
            // Then process membership logic
            $this->process_renewal_order( $order );
        } else {
            error_log( sprintf( 'WCS CiviCRM Bridge: Failed to create/update CiviCRM contribution for renewal order #%d', $renewal_order_id ) );
        }
    }

    /**
     * Handle subscription renewal payment completion.
     *
     * @param WC_Subscription $subscription The subscription object.
     * @param WC_Order        $renewal_order The renewal order object.
     */
    public function handle_subscription_renewal_completion( $subscription, $renewal_order ) {
        error_log( sprintf( 'WCS CiviCRM Bridge: Subscription #%d renewal payment completed for order #%d', 
            $subscription->get_id(), $renewal_order->get_id() ) );
        
        // Use WPCV function to create/update contribution
        $contribution = WPCV_WCI()->contribution->create_from_order( $renewal_order );
        if ( $contribution ) {
            error_log( sprintf( 'WCS CiviCRM Bridge: Created/updated CiviCRM contribution #%d for subscription renewal order #%d', 
                $contribution['id'], $renewal_order->get_id() ) );
            
            // Then process membership logic
            $this->process_renewal_order( $renewal_order, $subscription );
        } else {
            error_log( sprintf( 'WCS CiviCRM Bridge: Failed to create/update CiviCRM contribution for subscription renewal order #%d', $renewal_order->get_id() ) );
        }
    }

    /**
     * Handle subscription payment completion.
     *
     * @param WC_Subscription $subscription The subscription object.
     */
    public function handle_subscription_payment_complete( $subscription ) {
        error_log( sprintf( 'WCS CiviCRM Bridge: Subscription #%d payment completed', $subscription->get_id() ) );
        
        // Get the latest renewal order for this subscription.
        $renewal_orders = $subscription->get_related_orders( 'ids', 'renewal' );
        if ( ! empty( $renewal_orders ) ) {
            $latest_renewal_id = max( $renewal_orders );
            $renewal_order = wc_get_order( $latest_renewal_id );
            if ( $renewal_order ) {
                // Use WPCV function to create/update contribution
                $contribution = WPCV_WCI()->contribution->create_from_order( $renewal_order );
                if ( $contribution ) {
                    error_log( sprintf( 'WCS CiviCRM Bridge: Created/updated CiviCRM contribution #%d for subscription payment order #%d', 
                        $contribution['id'], $renewal_order->get_id() ) );
                    
                    // Then process membership logic
                    $this->process_renewal_order( $renewal_order, $subscription );
                } else {
                    error_log( sprintf( 'WCS CiviCRM Bridge: Failed to create/update CiviCRM contribution for subscription payment order #%d', $renewal_order->get_id() ) );
                }
            }
        }
    }

    /**
     * Handle order status changes.
     *
     * @param int    $order_id The order ID.
     * @param string $old_status The old status.
     * @param string $new_status The new status.
     */
    public function handle_order_status_changed( $order_id, $old_status, $new_status ) {
        error_log( sprintf( 'WCS CiviCRM Bridge: Order #%d status changed from %s to %s', $order_id, $old_status, $new_status ) );
        
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        // Check if this is a renewal order.
        if ( ! $this->is_renewal_order( $order ) ) {
            return;
        }

        // Use WPCV function to update contribution status
        if ( 'completed' === $new_status && $order->is_paid() ) {
            // Mark as completed using payment_create
            $payment = WPCV_WCI()->contribution->payment_create( $order_id, $order );
            if ( $payment ) {
                error_log( sprintf( 'WCS CiviCRM Bridge: Marked contribution as completed for renewal order #%d', $order_id ) );
            }
        } else {
            // Update status using status_update
            $new_status_id = WPCV_WCI()->contribution->status_map( $new_status );
            if ( $new_status_id ) {
                $contribution = WPCV_WCI()->contribution->status_update( $order_id, $order, $new_status_id );
                if ( $contribution ) {
                    error_log( sprintf( 'WCS CiviCRM Bridge: Updated contribution status for renewal order #%d', $order_id ) );
                }
            }
        }

        // Then process membership logic
        $this->process_renewal_order( $order );
    }

    /**
     * Check if an order is a renewal order.
     *
     * @param WC_Order $order The order object.
     * @return bool True if it's a renewal order.
     */
    private function is_renewal_order( $order ) {
        error_log( sprintf( 'WCS CiviCRM Bridge: Checking if order #%d is a renewal order', $order->get_id() ) );
        // Check for renewal order meta.
        if ( $order->get_meta( '_subscription_renewal' ) ) {
            return true;
        }

        // Check using WooCommerce Subscriptions function.
        if ( function_exists( 'wcs_order_contains_renewal' ) ) {
            return wcs_order_contains_renewal( $order );
        }

        return false;
    }

    /**
     * Process a renewal order for CiviCRM membership.
     *
     * @param WC_Order         $order The renewal order.
     * @param WC_Subscription  $subscription Optional subscription object.
     */
    private function process_renewal_order( $order, $subscription = null ) {
        error_log( sprintf( 'WCS CiviCRM Bridge: Processing renewal order #%d', $order->get_id() ) );

        // Get the associated CiviCRM contribution.
        $contribution = $this->get_contribution_for_order( $order );
        if ( ! $contribution ) {
            error_log( sprintf( 'WCS CiviCRM Bridge: No CiviCRM contribution found for renewal order #%d', $order->get_id() ) );
            return;
        }

        error_log( sprintf( 'WCS CiviCRM Bridge: Found CiviCRM contribution #%d for renewal order #%d', 
            $contribution['id'], $order->get_id() ) );

        // Check each product in the order.
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( ! $product ) {
                continue;
            }

            $this->check_product_membership_association( $product, $contribution, $order );
        }
    }

    /**
     * Get the CiviCRM contribution associated with an order.
     *
     * @param WC_Order $order The order object.
     * @return array|false The contribution data or false if not found.
     */
    private function get_contribution_for_order( $order ) {
        // Use WPCV function to get contribution by order ID
        return WPCV_WCI()->contribution->get_by_order_id( $order->get_id() );
    }

    /**
     * Get CiviCRM contribution by ID.
     *
     * @param int $contribution_id The contribution ID.
     * @return array|false The contribution data or false if not found.
     */
    private function get_contribution_by_id( $contribution_id ) {
        try {
            $result = civicrm_api3( 'Contribution', 'get', array(
                'id' => $contribution_id,
                'return' => array( 'id', 'contact_id', 'total_amount', 'contribution_status_id', 'trxn_id' ),
            ) );

            if ( $result['is_error'] || empty( $result['values'] ) ) {
                return false;
            }

            return reset( $result['values'] );
        } catch ( Exception $e ) {
            error_log( sprintf( 'WCS CiviCRM Bridge: Error getting contribution #%d: %s', $contribution_id, $e->getMessage() ) );
            return false;
        }
    }

    /**
     * Get CiviCRM contribution by transaction ID.
     *
     * @param string $transaction_id The transaction ID.
     * @return array|false The contribution data or false if not found.
     */
    private function get_contribution_by_transaction_id( $transaction_id ) {
        try {
            $result = civicrm_api3( 'Contribution', 'get', array(
                'trxn_id' => $transaction_id,
                'return' => array( 'id', 'contact_id', 'total_amount', 'contribution_status_id', 'trxn_id' ),
            ) );

            if ( $result['is_error'] || empty( $result['values'] ) ) {
                return false;
            }

            return reset( $result['values'] );
        } catch ( Exception $e ) {
            error_log( sprintf( 'WCS CiviCRM Bridge: Error getting contribution by transaction ID %s: %s', $transaction_id, $e->getMessage() ) );
            return false;
        }
    }

    /**
     * Get CiviCRM contribution by order key.
     *
     * @param string $order_key The order key.
     * @return array|false The contribution data or false if not found.
     */
    private function get_contribution_by_order_key( $order_key ) {
        try {
            $result = civicrm_api3( 'Contribution', 'get', array(
                'trxn_id' => array( 'LIKE' => $order_key . '_%' ),
                'return' => array( 'id', 'contact_id', 'total_amount', 'contribution_status_id', 'trxn_id' ),
            ) );

            if ( $result['is_error'] || empty( $result['values'] ) ) {
                return false;
            }

            return reset( $result['values'] );
        } catch ( Exception $e ) {
            error_log( sprintf( 'WCS CiviCRM Bridge: Error getting contribution by order key %s: %s', $order_key, $e->getMessage() ) );
            return false;
        }
    }

    /**
     * Check if a product has an associated CiviCRM membership type.
     *
     * @param WC_Product $product The product object.
     * @param array      $contribution The CiviCRM contribution data.
     * @param WC_Order   $order The order object.
     */
    private function check_product_membership_association( $product, $contribution, $order ) {
        // Check if product has membership type meta (WPCV plugin method).
        $membership_type_id = $product->get_meta( '_woocommerce_civicrm_membership_type_id' );
        
        if ( ! $membership_type_id ) {
            error_log( sprintf( 'WCS CiviCRM Bridge: Product #%d has no CiviCRM membership type association', $product->get_id() ) );
            return;
        }

        error_log( sprintf( 'WCS CiviCRM Bridge: Product #%d has CiviCRM membership type #%d', 
            $product->get_id(), $membership_type_id ) );

        // Check if the contribution has an attached membership.
        $this->check_contribution_membership( $contribution, $membership_type_id, $order );
    }

    /**
     * Check if a contribution has an attached membership.
     *
     * @param array    $contribution The CiviCRM contribution data.
     * @param int      $membership_type_id The membership type ID.
     * @param WC_Order $order The order object.
     */
    private function check_contribution_membership( $contribution, $membership_type_id, $order ) {
        try {
            // Get memberships for this contact.
            $membership_result = civicrm_api3( 'Membership', 'get', array(
                'contact_id' => $contribution['contact_id'],
                'membership_type_id' => $membership_type_id,
                'return' => array( 'id', 'contact_id', 'membership_type_id', 'status_id', 'start_date', 'end_date' ),
            ) );

            if ( $membership_result['is_error'] ) {
                error_log( sprintf( 'WCS CiviCRM Bridge: Error getting memberships for contact #%d: %s', 
                    $contribution['contact_id'], $membership_result['error_message'] ) );
                return;
            }

            if ( empty( $membership_result['values'] ) ) {
                error_log( sprintf( 'WCS CiviCRM Bridge: No membership found for contact #%d with type #%d', 
                    $contribution['contact_id'], $membership_type_id ) );
                // $this->create_membership_for_contribution( $contribution, $membership_type_id, $order );
            } else {
                $membership = reset( $membership_result['values'] );
                error_log( sprintf( 'WCS CiviCRM Bridge: Found existing membership #%d for contact #%d with type #%d', 
                    $membership['id'], $contribution['contact_id'], $membership_type_id ) );
                //$this->extend_membership( $membership, $contribution, $order );
            }

        } catch ( Exception $e ) {
            error_log( sprintf( 'WCS CiviCRM Bridge: Exception checking membership for contribution #%d: %s', 
                $contribution['id'], $e->getMessage() ) );
        }
    }

    /**
     * Create a new membership for a contribution.
     *
     * @param array    $contribution The CiviCRM contribution data.
     * @param int      $membership_type_id The membership type ID.
     * @param WC_Order $order The order object.
     */
    private function create_membership_for_contribution( $contribution, $membership_type_id, $order ) {
        try {
            // Get membership type details.
            $membership_type_result = civicrm_api3( 'MembershipType', 'get', array(
                'id' => $membership_type_id,
                'return' => array( 'id', 'name', 'duration_unit', 'duration_interval' ),
            ) );

            if ( $membership_type_result['is_error'] || empty( $membership_type_result['values'] ) ) {
                error_log( sprintf( 'WCS CiviCRM Bridge: Could not get membership type #%d details', $membership_type_id ) );
                return;
            }

            $membership_type = reset( $membership_type_result['values'] );
            
            // Calculate membership dates.
            $start_date = date( 'Y-m-d' );
            $end_date = $this->calculate_membership_end_date( $start_date, $membership_type );

            // Create membership.
            $membership_result = civicrm_api3( 'Membership', 'create', array(
                'contact_id' => $contribution['contact_id'],
                'membership_type_id' => $membership_type_id,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'source' => 'WooCommerce Subscription Renewal - Order #' . $order->get_id(),
            ) );

            if ( $membership_result['is_error'] ) {
                error_log( sprintf( 'WCS CiviCRM Bridge: Error creating membership: %s', $membership_result['error_message'] ) );
            } else {
                $membership = $membership_result['values'];
                error_log( sprintf( 'WCS CiviCRM Bridge: Created new membership #%d for contact #%d', 
                    $membership['id'], $contribution['contact_id'] ) );
                
                // Add order note.
                $order->add_order_note( sprintf( 
                    __( 'Created new CiviCRM membership #%d for membership type "%s"', 'wcs-civicrm-bridge' ),
                    $membership['id'],
                    $membership_type['name']
                ) );
            }

        } catch ( Exception $e ) {
            error_log( sprintf( 'WCS CiviCRM Bridge: Exception creating membership: %s', $e->getMessage() ) );
        }
    }

    /**
     * Extend an existing membership.
     *
     * @param array    $membership The existing membership data.
     * @param array    $contribution The CiviCRM contribution data.
     * @param WC_Order $order The order object.
     */
    private function extend_membership( $membership, $contribution, $order ) {
        try {
            // Get membership type details.
            $membership_type_result = civicrm_api3( 'MembershipType', 'get', array(
                'id' => $membership['membership_type_id'],
                'return' => array( 'id', 'name', 'duration_unit', 'duration_interval' ),
            ) );

            if ( $membership_type_result['is_error'] || empty( $membership_type_result['values'] ) ) {
                error_log( sprintf( 'WCS CiviCRM Bridge: Could not get membership type #%d details', $membership['membership_type_id'] ) );
                return;
            }

            $membership_type = reset( $membership_type_result['values'] );
            
            // Calculate new end date.
            $current_end_date = $membership['end_date'];
            $new_end_date = $this->calculate_membership_end_date( $current_end_date, $membership_type );

            // Update membership.
            $update_result = civicrm_api3( 'Membership', 'create', array(
                'id' => $membership['id'],
                'end_date' => $new_end_date,
            ) );

            if ( $update_result['is_error'] ) {
                error_log( sprintf( 'WCS CiviCRM Bridge: Error extending membership #%d: %s', 
                    $membership['id'], $update_result['error_message'] ) );
            } else {
                error_log( sprintf( 'WCS CiviCRM Bridge: Extended membership #%d until %s', 
                    $membership['id'], $new_end_date ) );
                
                // Add order note.
                $order->add_order_note( sprintf( 
                    __( 'Extended CiviCRM membership #%d for membership type "%s" until %s', 'wcs-civicrm-bridge' ),
                    $membership['id'],
                    $membership_type['name'],
                    $new_end_date
                ) );
            }

        } catch ( Exception $e ) {
            error_log( sprintf( 'WCS CiviCRM Bridge: Exception extending membership #%d: %s', 
                $membership['id'], $e->getMessage() ) );
        }
    }

    /**
     * Calculate membership end date.
     *
     * @param string $start_date The start date (Y-m-d format).
     * @param array  $membership_type The membership type data.
     * @return string The end date (Y-m-d format).
     */
    private function calculate_membership_end_date( $start_date, $membership_type ) {
        $duration_unit = $membership_type['duration_unit'];
        $duration_interval = $membership_type['duration_interval'];

        $start_timestamp = strtotime( $start_date );
        
        switch ( $duration_unit ) {
            case 'year':
                $end_timestamp = strtotime( '+' . $duration_interval . ' years', $start_timestamp );
                break;
            case 'month':
                $end_timestamp = strtotime( '+' . $duration_interval . ' months', $start_timestamp );
                break;
            case 'day':
                $end_timestamp = strtotime( '+' . $duration_interval . ' days', $start_timestamp );
                break;
            default:
                $end_timestamp = strtotime( '+1 year', $start_timestamp );
        }

        return date( 'Y-m-d', $end_timestamp );
    }

    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'WCS CiviCRM Bridge', 'wcs-civicrm-bridge' ),
            __( 'WCS CiviCRM Bridge', 'wcs-civicrm-bridge' ),
            'manage_woocommerce',
            'wcs-civicrm-bridge',
            array( $this, 'admin_page' )
        );
    }

    /**
     * Enqueue admin scripts.
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( 'woocommerce_page_wcs-civicrm-bridge' !== $hook ) {
            return;
        }

        wp_enqueue_script( 'jquery' );
    }

    /**
     * Display admin page.
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'WooCommerce Subscriptions CiviCRM Bridge', 'wcs-civicrm-bridge' ); ?></h1>
            
            <div class="card">
                <h2><?php esc_html_e( 'Plugin Status', 'wcs-civicrm-bridge' ); ?></h2>
                <p><?php esc_html_e( 'This plugin bridges WooCommerce Subscriptions with CiviCRM membership management.', 'wcs-civicrm-bridge' ); ?></p>
                
                <h3><?php esc_html_e( 'Dependencies', 'wcs-civicrm-bridge' ); ?></h3>
                <ul>
                    <li><?php echo class_exists( 'WooCommerce' ) ? '✅' : '❌'; ?> WooCommerce</li>
                    <li><?php echo class_exists( 'WC_Subscriptions' ) ? '✅' : '❌'; ?> WooCommerce Subscriptions</li>
                    <li><?php echo class_exists( 'WPCV_Woo_Civi' ) ? '✅' : '❌'; ?> WPCV WooCommerce CiviCRM Integration</li>
                    <li><?php echo function_exists( 'civicrm_api3' ) ? '✅' : '❌'; ?> CiviCRM</li>
                </ul>
            </div>

            <div class="card">
                <h2><?php esc_html_e( 'Recent Activity', 'wcs-civicrm-bridge' ); ?></h2>
                <p><?php esc_html_e( 'Check the debug log for recent renewal order processing activity.', 'wcs-civicrm-bridge' ); ?></p>
                <p><code>wp-content/debug.log</code></p>
            </div>

            <div class="card">
                <h2><?php esc_html_e( 'How It Works', 'wcs-civicrm-bridge' ); ?></h2>
                <ol>
                    <li><?php esc_html_e( 'Hooks into subscription order creation events (checkout and new orders)', 'wcs-civicrm-bridge' ); ?></li>
                    <li><?php esc_html_e( 'Hooks into renewal order completion events', 'wcs-civicrm-bridge' ); ?></li>
                    <li><?php esc_html_e( 'Uses WPCV functions to create/update CiviCRM contributions for subscription and renewal orders', 'wcs-civicrm-bridge' ); ?></li>
                    <li><?php esc_html_e( 'Checks if products in the order have associated CiviCRM membership types', 'wcs-civicrm-bridge' ); ?></li>
                    <li><?php esc_html_e( 'Finds the associated CiviCRM contribution for the order using WPCV methods', 'wcs-civicrm-bridge' ); ?></li>
                    <li><?php esc_html_e( 'Checks if the contribution has an attached membership', 'wcs-civicrm-bridge' ); ?></li>
                    <li><?php esc_html_e( 'Creates new membership or extends existing membership as needed', 'wcs-civicrm-bridge' ); ?></li>
                </ol>
            </div>
        </div>
        <?php
    }

    /**
     * Plugin activation.
     */
    public function activate() {
        error_log( 'WCS CiviCRM Bridge: Plugin activated' );
    }

    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        error_log( 'WCS CiviCRM Bridge: Plugin deactivated' );
    }
}

// Initialize the plugin.
WCS_CiviCRM_Bridge::get_instance();
