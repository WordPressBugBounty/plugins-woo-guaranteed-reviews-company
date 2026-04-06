<?php

class WC_SAG_API_Order_Export extends WC_SAG_API_Abstract_Route {
    /** @var string Route slug */
    protected $route = '/orders/export';
    
    /** @var string Query var */
    protected $query_var = 'wcsag_orders_export';

    /**
     * Run the endpoint
     */
    protected function run() {
        $params = $this->validate_request();

        // Expose util headers
        header( 'X-GRC-Module: wordpress' );
        header( 'X-GRC-Version: ' . WC_SAG_VERSION );
        header( 'X-GRC-Widgets: ' . ($this->settings->get( 'enable_new_widgets' ) ? '1' : '0') );

        // Block if attempt to sending emails from GRC (old version)
        if( isset( $params['source'] ) && $params['source'] == 'mail' && !$this->settings->get('use_old_orders_method') ) {
            echo 'IGNORE';
            return;
        }

        // Get orders between requested dates
        $orders = $this->get_orders( $params );
		
        // Format orders
        $formatted_orders = array_map( array( $this, 'format_order' ), $orders );

        // Build full URL
        $url = add_query_arg( array(
            'token'  => $params['token'],
            'apiKey' => $this->settings->guess_api_key_for_language( $params['lang'] )
        ), $this->settings->get_sag_api_url( $params['lang'] ) . 'bulkOrderInfos.php' );

        // Post orders to SAG endpoint
        wp_remote_post( esc_url_raw( $url ), array(
            'body'    => array( 'data' => base64_encode( json_encode( $formatted_orders ) ) ),
            'timeout' => 30,
        ) );
    }

    /**
     * Get local WPML languages based on API keys
     */
    protected function get_local_languages( $lang ) {
        $raw_api_key = $this->settings->get( 'api_key_raw' );
        $local_lang_codes = array();
        if ( is_array( $raw_api_key ) ) {
            // Looks like multilingual setup, returns key for current language
            foreach ( $raw_api_key as $lang_code => $api_key ) {
                if ( wcsag_get_lang_from_api_key( $api_key ) == $lang ) {
                    $local_lang_codes[] = $lang_code;
                }
            }
        }
        else {
            // Use api key language as default case
            $local_lang_codes[] = wcsag_get_lang_from_api_key( $api_key );
        }
        return $local_lang_codes;
    }

    /**
     * Get orders with retrocompat
     */
    protected function get_orders( $params ) {
        
        if ( version_compare( WC_VERSION, '3.0.0', '<' ) ) {

            $args = array(
                'post_type'      => 'shop_order',
                'post_status'    => $this->settings->get( 'wc_statuses' ),
                'posts_per_page' => -1,
                'date_query' => array(
                    array(
                        'after'     => array(
                            'year'  => date( 'Y', $params['date_from'] ),
                            'month' => date( 'n', $params['date_from'] ),
                            'day'   => date( 'j', $params['date_from'] ),
                        ),
                        'before'    => array(
                            'year'  => date( 'Y', $params['date_to'] ),
                            'month' => date( 'n', $params['date_to'] ),
                            'day'   => date( 'j', $params['date_to'] ),
                        ),
                        'inclusive' => true,
                        'column'    => 'post_modified',
                    ),
                ),
            );

            // Filter by lang if WPML is enabled
            if ( $params['lang'] && function_exists( 'icl_object_id' ) && class_exists( 'SitePress' ) ) {
                $args['meta_query'][] = array(
                    'key'     => 'wpml_language',
                    'value'   => $this->get_local_languages( $params['lang'] ),
                    'compare' => 'IN',
                );
            }
            
            if ( $params['lang'] && class_exists( 'Polylang_Woocommerce' )) {
                $args['meta_query'][] = array(
                    'key'     => 'lang',
                    'value'   => $this->get_local_languages( $params['lang'] ),
                    'compare' => 'IN',
                );
            }

            // Filter by lang if Weglot is enabled
			if ( $params['lang'] && class_exists( 'Context_Weglot' )) {
                $args['meta_query'][] = array(
                    'key'     => 'weglot_language',
                    'value'   => $this->get_local_languages( $params['lang'] ),
                    'compare' => 'IN',
                );
            }
            
            // TranslatePress
			if ( $params['lang'] && class_exists( 'trp_get_languages' )) {
				$locales = [];
				foreach ( trp_get_languages() as $language_code => $language ) {
					if ( strpos( $language_code, $params['lang'] ) === 0 ) {
						$locales[] = $language_code;
					}
				}
				
				if ( ! empty( $locales ) ) {
					$args['meta_query'][] = array(
						'key'     => 'trp_language',
						'value'   => $locales,
						'compare' => 'IN',
					);
				}
			}
			
			if ( $params['lang'] && class_exists( 'Context_Weglot' )) {
                echo "Weglot_up";
            }
            
            if ( $params['lang'] && class_exists( 'Polylang_Woocommerce' )) {
                echo "PL WC loaded";
            }

            return array_map( 'wc_get_order', get_posts( $args ) );
        }
        else 
        {
        	// Try to get orders count with SQL query (faster)
			if($params['source'] == 'count') {
				global $wpdb;

				$statuses = $this->settings->get('wc_statuses');
				$statuses_sql = implode("','", array_map('esc_sql', $statuses));
				
				$has_lang_filter = $params['lang'] && (
					( function_exists( 'icl_object_id' ) && class_exists( 'SitePress' ) && function_exists( 'wcml_loader' ) )
					|| class_exists( 'Polylang_Woocommerce' )
					|| class_exists( 'Context_Weglot' ) 
					|| class_exists( 'trp_get_languages' )
				);
				
				// Check if WPML table exists
				$wpml_table_exists = $wpdb->get_var(
					$wpdb->prepare(
						"SHOW TABLES LIKE %s",
						$wpdb->prefix . 'icl_translations'
					)
				);

				$sql = "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p ";

				if($wpml_table_exists) {
					// WPML
					$sql .= "LEFT JOIN {$wpdb->prefix}icl_translations wpml ON wpml.element_id = p.ID AND wpml.element_type = 'post_shop_order' ";
				}
				
				// Polylang
				$sql .= "LEFT JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID 
				LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'language'
				LEFT JOIN {$wpdb->terms} polylang ON polylang.term_id = tt.term_id ";
				
				// Meta
				$sql .= "LEFT JOIN {$wpdb->postmeta} pm_lang ON pm_lang.post_id = p.ID AND pm_lang.meta_key IN ('weglot_language', '_order_language', 'wpml_language', 'trp_language') ";
				
				$sql .= "WHERE p.post_type = 'shop_order'
				AND p.post_status IN ('$statuses_sql')
				AND p.post_modified BETWEEN %s AND %s";
				
				if($has_lang_filter) {
					$langs = (array) $this->get_local_languages($params['lang']);
					
					if( class_exists( 'trp_get_languages' ) ) {
						foreach ( trp_get_languages() as $language_code => $language ) {
							if ( strpos( $language_code, $params['lang'] ) === 0 ) {
								$langs[] = $language_code;
								break;
							}
						}
					}
					
					$langs_sql = implode("','", array_map('esc_sql', $langs));
					
					$sql .= " AND (
						". ($wpml_table_exists ? "wpml.language_code IN ('$langs_sql') OR " : "") ."
						polylang.slug IN ('$langs_sql')
						OR pm_lang.meta_value IN ('$langs_sql')
					)";
				}
				
				$sql = $wpdb->prepare(
					$sql,
					date('Y-m-d H:i:s', $params['date_from']),
					date('Y-m-d H:i:s', $params['date_to'])
				);
				
				$orders = $wpdb->get_var($sql);
				
				if($orders > 0) {
					header('Content-Type: application/json');
					echo json_encode(['count' => $orders]);
					exit;
				}
			}
            
            $args = array(
                'type'           => 'shop_order',
                'status'         => $this->settings->get( 'wc_statuses' ),
                'posts_per_page' => -1,
                'date_modified'  => "{$params['date_from']}...{$params['date_to']}",
            );

            // Filter by lang if WPML is enabled & WooCommerce multilingual is enabled
            if ( $params['lang'] && function_exists( 'icl_object_id' ) && class_exists( 'SitePress' ) && function_exists( 'wcml_loader' )) {
                add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( $this, 'handle_custom_query_var' ), 10, 2 );
                $args['wpml_languages'] = $this->get_local_languages( $params['lang'] );
            }
            
            
            if ( $params['lang'] && class_exists( 'Polylang_Woocommerce' )) {
                $args['lang'] = $this->get_local_languages( $params['lang'] );
            }

            // Filter by lang if Weglot is enabled & WooCommerce multilingual is enabled
			if ( $params['lang'] && class_exists( 'Context_Weglot' )) {
				$args['meta_key'] = 'weglot_language';
				$args['meta_value'] = $this->get_local_languages( $params['lang'] );
            }

            // TranslatePress
			if ( $params['lang'] && class_exists( 'trp_get_languages' )) {
				$locale = null;

				foreach ( trp_get_languages() as $language_code => $language ) {
					if ( strpos( $language_code, $params['lang'] ) === 0 ) {
						$locale = $language_code;
						break;
					}
				}

				if ( $locale ) {
					$args['meta_query'][] = array(
						'key'     => 'trp_language',
						'value'   => $locale,
						'compare' => '=',
					);
				}
			}
			
            $orders = wc_get_orders( $args );
            return $orders;
        }
    }

    /**
     * Handle WC wpml_languages query var to get orders with specific language
     */
    public function handle_custom_query_var( $query, $query_vars ) {
        if ( ! empty( $query_vars['wpml_languages'] ) ) {
            $query['meta_query'][] = array(
                'key'   => 'wpml_language',
                'value' => is_array($query_vars['wpml_languages']) ? $query_vars['wpml_languages'] : array(),
                'compare' => 'IN',
            );
        }

        return $query;
    }

    /**
     * Validate and sanitize request 
     */
    protected function validate_request() {
        $params = array();

        // Lang validation
        if ( isset( $_POST['lang'] ) ) {
            $params['lang'] = $_POST['lang'];
        }
        else {
            die( 'Missing Lang' );
        }

        // Token validation
        if ( isset( $_POST['token'] ) && $this->check_token( $_POST['token'], $params['lang'] ) ) {
            $params['token'] = $_POST['token'];
        }
        else {
            die( 'Invalid token' );
        }

        // From Date validation
        if ( isset( $_POST['fromDate'] ) && false !== $date_from = strtotime( $_POST['fromDate'] ) ) {
            $params['date_from'] = $date_from;
        }
        else {
            die( 'Invalid fromDate' );
        }

        // To Date validation
        if ( isset( $_POST['toDate'] ) && false !== $date_to = strtotime( $_POST['toDate'] ) ) {
            $params['date_to'] = $date_to;
        }
        else {
            die( 'Invalid toDate' );
        }

        // Source validation
        if ( isset( $_POST['source'] ) ) {
            $params['source'] = $_POST['source'];
        }

        return $params;
    }

    /**
     * Check a token
     */
    protected function check_token( $token, $lang = null ) {
        // Build SAG token checking URL
        $url = add_query_arg( array(
            'token'  => $token,
            'apiKey' => $this->settings->guess_api_key_for_language( $lang )
            ), $this->settings->get_sag_api_url( $lang ) . 'checkToken.php' );

        $response_body = wp_remote_retrieve_body( wp_remote_get( esc_url_raw( $url ), array( 'timeout' => 30 ) ) );

        // Check if token was validated
        return ( strpos( $response_body, 'ValidSagData' ) !== false ); 
    }

    /**
     * Format order values
     */
    protected function format_order( $order ) {

        // Check if phone number transmission is enabled
        $phoneEnabled = $this->settings->get( 'send_phone' );

        $formatted_order = array(
            'id_order'            => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id(),
            'reference'           => $order->get_order_number(),
            'order_date'          => version_compare( WC_VERSION, '3.0.0', '<' ) ? date( 'Y-m-d H:i:s', strtotime( $order->order_date ) ) : $order->get_date_created()->date( 'Y-m-d H:i:s' ),
            'total_paid_tax_incl' => wc_format_decimal( $order->get_total(), 2 ),
            'firstname'           => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_first_name : $order->get_billing_first_name(),
            'lastname'            => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_last_name : $order->get_billing_last_name(),
            'email'               => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_email : $order->get_billing_email(),
            'phone'               => $phoneEnabled ? (version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_phone : $order->get_billing_phone()) : null, 
            'shipping_country'    => version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->shipping_country : $order->get_shipping_country()
        );

        foreach ( $order->get_items() as $item ) {
            $formatted_product = $this->format_product($item, $order);
            if ( $formatted_product ) {
                $formatted_order['products'][] = $formatted_product;
            }
        }

        return $formatted_order;
    }

    /**
     * Format product values 
     */
    protected function format_product( $item, $order ) {
        $product_id = $item instanceof WC_Order_Item_Product ? $item->get_product_id() : $item['product_id'];
		$variation_product_id = $item instanceof WC_Order_Item_Product ? $item->get_variation_id() : $item['variation_id'];
        // Apply filter for WPML
        if ( function_exists( 'icl_object_id' )  && class_exists( 'SitePress' ) ) {
            $order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();
            $order_lang = get_post_meta( $order_id, 'wpml_language', true );
            $product_id = function_exists( 'wpml_object_id_filter' ) ? apply_filters( 'wpml_object_id', $product_id, 'product', true, $order_lang ) : icl_object_id( $product_id, 'product', true, $order_lang );
        }

        $product = wc_get_product( $product_id );

        if ( $product ) {
			$idProduct = version_compare( WC_VERSION, '3.0.0', '<' ) ?  $product->id : $product->get_id();
			$ean13 = '';
            
			//Do we have a ean13 ? (SeoPress compatibility)
			$fieldType = get_post_meta( $idProduct, 'sp_wc_barcode_type_field', true );
			if ($fieldType && ($fieldType =="gtin13" or $fieldType =="none")) {
				$ean13 = get_post_meta( $idProduct, 'sp_wc_barcode_field', true );
			}
            
            //Do we have a ean13 ? (Cart Product Feed Additional Product Fields compatibility)
            $cpfEan = get_post_meta( $idProduct, '_cpf_ean', true );
			if ($cpfEan) {
				$ean13 = $cpfEan;
			}

            //Do we have a ean13 ? (Product GTIN (EAN, UPC, ISBN) for WooCommerce)
            $wpmEan = get_post_meta( $idProduct, '_wpm_gtin_code', true );
			if ($wpmEan) {
				$ean13 = $wpmEan;
			}
			
			$GtinProduct = get_post_meta( $idProduct, '_gtin_product', true );
			if ($GtinProduct) {
				$ean13 = $GtinProduct;
			}
            
            //custom Method from https://njengah.com/add-gtin-numbers-products-woocommerce/
            $customGtin = get_post_meta( $idProduct, '_gtin', true );
			if ($customGtin) {
				$ean13 = $customGtin;
			}
			
			$customGtin13 = get_post_meta( $idProduct, '_gtin_product', true );
			if ($customGtin13) {
				$ean13 = $customGtin13;
			}
			
			if ($variation_product_id) {
				$customGtin13var = get_post_meta( $variation_product_id, 'gtin_product_variable', true );
				if ($customGtin13var) {
					$ean13 = $customGtin13var;
				}
                $customGtin13var = get_post_meta( $variation_product_id, "wpseo_variation_global_identifiers_values", true );
				if ($customGtin13var) {
					$ean13 = $customGtin13var["gtin13"];
				}
			}
			
            return array(
                'id'              => $idProduct,
                'ean13'           => $ean13,
                'upc'             => '',
                'sku'             => $product->get_sku(),
                'name'            => version_compare( WC_VERSION, '3.0.0', '<' ) ? $product->post_title : $product->get_title(),
                'quantitySold'    => $item instanceof WC_Order_Item_Product ? $item->get_quantity() : $item['qty'],
                'unitPriceSoldHt' => wc_format_decimal( $order->get_item_total( $item ), 2 ),
                'url' 			  => get_permalink( $product_id )
            );
        }
        else {
            return false;
        }
    }

}