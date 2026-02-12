<?php

class WC_SAG_API_Products extends WC_SAG_API_Abstract_Route {
    /** @var string Route slug */
    protected $route = '/products';
    
    /** @var string Query var */
    protected $query_var = 'wcsag_products';

    /**
     * Run the endpoint
     */
    protected function run() {
        // Get parameters
        $params = $this->validate_request();

        if(empty($params['lang'])) {
            die( 'Missing lang' );
        }

        // Get API key for current lang
        $apiKey = $this->settings->guess_api_key_for_language($params['lang']);

        if($apiKey !== $params['apiKey']) {
            die( 'Invalid apiKey' );
        }

        return $this->get_products();
    }

    /**
     * Validate and sanitize request
     */
    protected function validate_request() {
        // Parameters default values
        return array(
            'apiKey' => isset( $_GET['apiKey'] ) ? $_GET['apiKey'] : '',
            'lang' => isset( $_GET['lang'] ) ? $_GET['lang'] : ''
        );
    }

    /**
     * Get products data
     */
    protected function get_products() {

        $args = array(
            'limit'  => -1,
            'status' => 'publish',
        );

        $lang = apply_filters( 'wpml_current_language', null );

        if ( $lang ) {
            $args['lang'] = $lang;
        }

        $products = wc_get_products($args);

        $result = array();

        foreach ($products as $product) {

            $id = $product->get_id();
            $name = $product->get_name();
            $categories = wp_get_post_terms($id, 'product_cat');

            $category_id   = null;
            $category_name = null;

            if (!empty($categories)) {
                $category_id   = $categories[0]->term_id;
                $category_name = $categories[0]->name;
            }
            
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

            $productMeta = get_post_meta( $id );
            
            $ean13 = '';
            foreach($eanFields as $eanField) {
                if(isset($productMeta[$eanField]) && !empty($productMeta[$eanField][0])) {
                    // Specific for Yoast
                    if($eanField == "wpseo_variation_global_identifiers_values") {
                        $yoastVal = maybe_unserialize( $productMeta['wpseo_variation_global_identifiers_values'][0] );
                        if ( is_array($yoastVal) && !empty($yoastVal['gtin13']) ) {
                            $ean13 = $yoastVal['gtin13'];
                            break;
                        } else {
                            continue;
                        }
                    }
                    $ean13 = $productMeta[$eanField][0];
                    break;
                }
            }

            // Specific for sp_wc_barcode
            if(isset($productMeta['sp_wc_barcode_type_field']) && ($ean13 == "gtin13" || $ean13 == "none")) {
                if(isset($productMeta['sp_wc_barcode_field']) && !empty($productMeta['sp_wc_barcode_field'][0])) {
                    $ean13 = $productMeta['sp_wc_barcode_field'][0];
                } else {
                    $ean13 = '';
                }
            }

            $upc = get_post_meta($id, '_upc', true);
            
            $image_id = $product->get_image_id();
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail') : null;
            
            $url = get_permalink($id);

            $result[] = array(
                'id'            => $id,
                'name'          => $name,
                'category_id'   => $category_id,
                'category_name' => $category_name,
                'ean13'         => $ean13,
                'sku'           => $product->get_sku(),
                'upc'           => $upc,
                'url'           => $url,
                'image_url'     => $image_url,
            );
        }

        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
}
