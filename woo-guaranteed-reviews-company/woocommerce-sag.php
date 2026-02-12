<?php
/**
 * Plugin Name: Woocommerce - Guaranteed Reviews Company
 * Plugin URI: https://www.guaranteed-reviews.com/
 * Description: Shop and/or product reviews, Google stars, Trusted certificate, automatic validation (option), review files importation…
 * Version: 1.2.9
 * Author: Guaranteed Reviews Company
 * Author URI: http://www.guaranteed-reviews.com/
 * License: GPLv3
 * Domain Path: /languages/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'WC_SAG_VERSION', '1.2.9' );
define( 'WC_SAG_MIN_PHP_VER', '5.3.0' );
define( 'WC_SAG_MIN_WC_VER', '3.0.0' );
define( 'WC_SAG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WC_SAG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WC_SAG_BASENAME', plugin_basename( __FILE__ ) );

include_once( WC_SAG_PLUGIN_DIR . 'inc/functions.php' );
include_once( WC_SAG_PLUGIN_DIR . 'inc/class-wc-sag-settings.php' );
include_once( WC_SAG_PLUGIN_DIR . 'inc/class-wc-sag-api-abstact-route.php' );
include_once( WC_SAG_PLUGIN_DIR . 'inc/class-wc-sag-api-check.php' );
include_once( WC_SAG_PLUGIN_DIR . 'inc/class-wc-sag-api-config.php' );
include_once( WC_SAG_PLUGIN_DIR . 'inc/class-wc-sag-api-order-export.php' );
include_once( WC_SAG_PLUGIN_DIR . 'inc/class-wc-sag-api-review-import.php' );
include_once( WC_SAG_PLUGIN_DIR . 'inc/class-wc-sag-api-products.php' );
include_once( WC_SAG_PLUGIN_DIR . 'inc/class-wc-sag-admin-page.php' );
include_once( WC_SAG_PLUGIN_DIR . 'inc/class-wc-sag-frontend.php' );

register_activation_hook( __FILE__, 'wcsag_activate' );
register_deactivation_hook( __FILE__, 'wcsag_deactivate' );

/**
 * The code that runs during plugin activation.
 */
function wcsag_activate() {
    // Add rewrite rules and flush
    $check_api = new WC_SAG_API_Check( new WC_SAG_Settings() );
    $check_api->add_rewrite_rule();
    $config_api = new WC_SAG_API_Config( new WC_SAG_Settings() );
    $config_api->add_rewrite_rule();
    $order_export_api = new WC_SAG_API_Order_Export( new WC_SAG_Settings() );
    $order_export_api->add_rewrite_rule();
    $review_import_api = new WC_SAG_API_Review_Import( new WC_SAG_Settings() );
    $review_import_api->add_rewrite_rule();
    flush_rewrite_rules();

    // disable native WooCommerce reviews & ratings
    update_option( 'woocommerce_enable_reviews', 'no' );
    update_option( 'woocommerce_enable_review_rating', 'no' );
}

/**
 * The code that runs during plugin deactivation.
 */
function wcsag_deactivate() {
    flush_rewrite_rules();
}

/**
 * Main plugin class
 */
class WC_SAG {
    
    protected $settings;

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ), 1 );
        add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
        add_action( 'wp_ajax_wcsag_dismiss_notice', array( $this, 'dismiss_notice' ), 15 );
        add_action( 'woocommerce_order_status_changed', array( $this, 'order_status_updated' ), 10, 4 );
    }

    /**
     * Init plugin
     */
    public function init() {
        if ( $this->get_environment_warning() ) {
            return;
        }

        $this->load_textdomain();
        $this->register_post_types();

        $this->settings = new WC_SAG_Settings();
        
        // API
        new WC_SAG_API_Check( $this->settings );
        new WC_SAG_API_Config( $this->settings );
        new WC_SAG_API_Order_Export( $this->settings );
        new WC_SAG_API_Review_Import( $this->settings );
        new WC_SAG_API_Products( $this->settings );
        
        // Frontend
        new WC_SAG_Frontend( $this->settings );

        // Admin
        if ( is_admin() ) {
            new WC_SAG_Admin_Page( $this->settings );
        }
    }

    /**
     * Display admin notice
     */
    public function admin_notices() {
        if ( $message = $this->get_environment_warning() ) {
            printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) ); 
        }

        if (get_user_meta(get_current_user_id(), 'wcsag_notice_dismissed', true) || $this->settings->get('enable_new_widgets')) {
            return;
        }

        $settings_url = admin_url('admin.php?page=wc-sag-settings');
        ?>
        <div class="notice notice-success is-dismissible wcsag-notice">
            <p>
                <strong><?= __('Guaranteed Reviews Company', 'woo-guaranteed-reviews-company' ) ?> : </strong> 
                <?= str_replace('{settings_url}', esc_url($settings_url), __( '<a href="{settings_url}">Activate the new widget integration</a> to benefit from advanced features and customization options.', 'woo-guaranteed-reviews-company' )) ?>
            </p>
        </div>
        <script>
            jQuery(document).on('click', '.wcsag-notice .notice-dismiss', function() {
                jQuery.post(ajaxurl, {
                    action: 'wcsag_dismiss_notice'
                });
            });
        </script>
        <?php
    }

    /**
     * Dismiss admin notice
     */
    public function dismiss_notice() {
        update_user_meta(get_current_user_id(), 'wcsag_notice_dismissed', 1);
        wp_die();
    }

    /**
     * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
     * found or false if the environment has no problems.
     */
    protected function get_environment_warning() {
        if ( version_compare( phpversion(), WC_SAG_MIN_PHP_VER, '<' ) ) {
            $message = __( 'Guaranteed Reviews Company - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woo-guaranteed-reviews-company' );
            return sprintf( $message, WC_SAG_MIN_PHP_VER, phpversion() );
        }

        if ( ! defined( 'WC_VERSION' ) ) {
            return __( 'Guaranteed Reviews Company requires WooCommerce to be activated to work.', 'woo-guaranteed-reviews-company' );
        }

        if ( version_compare( WC_VERSION, WC_SAG_MIN_WC_VER, '<' ) ) {
            $message = __( 'Guaranteed Reviews Company - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woo-guaranteed-reviews-company' );
            return sprintf( $message, WC_SAG_MIN_WC_VER, WC_VERSION );
        }

        return false;
    }

    /**
     * Load translations
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'woo-guaranteed-reviews-company', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

    /**
     * Register post types
     */
    public function register_post_types() {
        register_post_type( 'wcsag_review', array(
            'public'  => false,
            'rewrite' => false
        ));
    }

    /**
     * Handle order status update
     */
    public function order_status_updated( $order_id, $old_status, $new_status, $order ) {
        
        // If we use old method to retrieve order, ignore hook trigger
        if($this->settings->get('use_old_orders_method')) {
            return;
        }

        // Get trigger statuses
        $triggerStatuses = $this->settings->get( 'wc_statuses' );

        if(in_array( "wc-{$new_status}", $triggerStatuses )) {
            $this->send_order( $order );
        }
    }
    
    /**
     * Send order to API
     */
    private function send_order( $order ) {

        $orderLang = $this->get_order_language( $order );
        $apiKey = $this->settings->guess_api_key_for_language( $orderLang );
        $order_id = $order->get_id();

        if( !$apiKey || get_post_meta( $order_id, '_wcsag_order_sent', true ) ) {
            return;
        }

        // Prepare order data
        $data = array(
            'api_key' => $apiKey,
            'source' => 'wp',
            'orders' => array(
                array(
                    'id_order'   => $order_id,
                    'order_date' => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '',
                    'firstname'  => $order->get_billing_first_name(),
                    'lastname'   => $order->get_billing_last_name(),
                    'email'      => $order->get_billing_email(),
                    'reference'  => $order->get_order_number(),
                    'products'   => array()
                )
            )
        );

        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();

            if ( ! $product ) {
                continue;
            }

            // Get variation
            $variation_product_id = $product->is_type('variation') ? $product->get_id() : null;

            $parent_id = $product->is_type('variation') ? $product->get_parent_id() : $product->get_id();
            $product_id = $variation_product_id ? $variation_product_id : $parent_id;

            // Categories
            $categories = wp_get_post_terms( $parent_id, 'product_cat' );
            $category_id = $categories && !is_wp_error($categories) ? $categories[0]->term_id : '';
            $category_name = $categories && !is_wp_error($categories) ? $categories[0]->name : '';
            
            // GTIN / EAN13
            $eanFields = array(
                '_wpm_gtin_code',                    // Product GTIN official plugin
                '_cpf_ean',                          // Cart Product Feed
                '_wt_feed_ean',                      // WebToffee
                '_gtin_product',                     // custom
                '_gtin',                             // custom
                'gtin_product_variable',             // variation-specific
                'sp_wc_barcode_type_field',          // SeoPress
                'wpseo_variation_global_identifiers_values', // Yoast
                '_alg_ean',                          // plugin "EAN for WooCommerce"
                '_barcode',                          // frequent custom field
                '_product_ean',                      // import/CTX feed
                '_ean_code',                         // custom CSV/XML
                '_yith_ean',                         // YITH old plugin
            );

            $parentMeta = get_post_meta( $parent_id );

            $variationMeta = array();
            if($variation_product_id) {
                $variationMeta = get_post_meta( $variation_product_id );
            }
            
            $ean13 = '';
            foreach($eanFields as $eanField) {
                if(isset($variationMeta[$eanField]) && !empty($variationMeta[$eanField][0])) {
                    // Specific for Yoast
                    if($eanField == "wpseo_variation_global_identifiers_values") {
                        $yoastVal = maybe_unserialize( $variationMeta['wpseo_variation_global_identifiers_values'][0] );
                        if ( is_array($yoastVal) && !empty($yoastVal['gtin13']) ) {
                            $ean13 = $yoastVal['gtin13'];
                            break;
                        } else {
                            continue;
                        }
                    }
                    $ean13 = $variationMeta[$eanField][0];
                    break;
                } elseif(isset($parentMeta[$eanField]) && !empty($parentMeta[$eanField][0])) {
                    // Specific for Yoast
                    if($eanField == "wpseo_variation_global_identifiers_values") {
                        $yoastVal = maybe_unserialize( $parentMeta['wpseo_variation_global_identifiers_values'][0] );
                        if ( is_array($yoastVal) && !empty($yoastVal['gtin13']) ) {
                            $ean13 = $yoastVal['gtin13'];
                            break;
                        } else {
                            continue;
                        }
                    }
                    $ean13 = $parentMeta[$eanField][0];
                    break;
                }
            }

            // Specific for sp_wc_barcode
            if((isset($variationMeta['sp_wc_barcode_type_field']) || isset($parentMeta['sp_wc_barcode_type_field'])) && ($ean13 == "gtin13" || $ean13 == "none")) {
                if(isset($variationMeta['sp_wc_barcode_field']) && !empty($variationMeta['sp_wc_barcode_field'][0])) {
                    $ean13 = $variationMeta['sp_wc_barcode_field'][0];
                } elseif(isset($parentMeta['sp_wc_barcode_field']) && !empty($parentMeta['sp_wc_barcode_field'][0])) {
                    $ean13 = $parentMeta['sp_wc_barcode_field'][0];
                } else {
                    $ean13 = '';
                }
            }

            // Get product image URL
            $childImageId = $product->get_image_id();
            $parentImageId = $parent_id ? get_post_thumbnail_id($parent_id) : 0;
            $productImageId = $childImageId ? $childImageId : $parentImageId;
            $productImageUrl = $productImageId ? wp_get_attachment_image_url( $productImageId, 'woocommerce_thumbnail' ) : null;

            $data['orders'][0]['products'][] = array(
                'id'            => $parent_id,
                'variant_id'    => $variation_product_id,
                'name'          => $product->get_name(),
                'category_id'   => $category_id,
                'category_name' => $category_name,
                'qty'           => $item->get_quantity(),
                'unit_price'    => wc_format_decimal( $item->get_total() / max(1, $item->get_quantity()), 2 ),
                'ean13'         => $ean13,
                'sku'           => $product->get_sku(),
                'upc'           => $product->get_meta('upc', true),
                'url'           => get_permalink( $product_id ),
                'image_url'     => $productImageUrl
            );
        }

        // Send orders data to API
        wp_remote_post( 'https://api.guaranteed-reviews.com/private/v3/orders', [
            'method'   => 'POST',
            'blocking' => false,
            'headers'  => [ 'Content-Type' => 'application/json; charset=utf-8' ],
            'body'     => wp_json_encode( $data )
        ] );

        // Update metadata to avoid duplicates
        update_post_meta( $order_id, '_wcsag_order_sent', current_time( 'mysql' ) );
    }
    
    public function get_order_language( $order ) {
        $order_id = $order->get_id();

        // Polylang
        if ( function_exists( 'pll_get_post_language' ) ) {
            return pll_get_post_language( $order_id, 'slug' );
        }

        // WPML
        if ( function_exists( 'wpml_get_language_information' ) ) {
            $lang_info = wpml_get_language_information( null, $order_id );
            if ( ! empty( $lang_info['language_code'] ) ) {
                return $lang_info['language_code'];
            }
        }

        // Weglot
        if ( class_exists( 'Context_Weglot' ) ) {
            $lang = get_post_meta( $order_id, '_weglot_language', true );
            if ( ! empty( $lang ) ) {
                return $lang;
            }
        }

        // Default locale
        return get_locale();
    }

}

new WC_SAG();
