<?php

class WC_SAG_Shortcode_Summary {

    /** @var WC_SAG_Settings Plugin settings */
    protected $settings;

    /**
     * Constructor
     */
    public function __construct( $settings ) {

        $this->settings = $settings;

        add_shortcode( 'wcsag_summary', array( $this, 'render_shortcode' ) );
        
        if ( $this->settings->get( 'enable_widget_product' ) == 1 ) {
            $newWidgetsEnabled = $this->settings->get( 'enable_new_widgets' );
            add_action( 'woocommerce_single_product_summary', array( $this, 'render_action' ), ($newWidgetsEnabled ? 6 : 35) );
        }
    }

    /**
     * Render shortcode content
     */
    public function render_shortcode( $atts = array(), $content = null ) {
        global $product;
        $atts = shortcode_atts( array( 'id' => ($product ? $product->get_id() : get_queried_object_id()) ), $atts );

        if( $this->settings->get( 'enable_new_widgets' ) ) {

            if ( !$product && $atts['id'] ) {
                $product = wc_get_product( $atts['id'] );
            }

            $product_sku = $product ? $product->get_sku() : false;

            return '<div class="grc-product-summary" 
                        data-product-id="' . $atts['id'] . '"
                        ' . ($product_sku ? ' data-product-sku="' . $product_sku .'"' : '') . '>
                    </div>';
        }
        else {
            $ratings = wcsag_get_ratings( $atts['id'] );

            $reviews_query = new WP_Query( array(
                'post_type'   => 'wcsag_review',
                'post_status' => 'publish',
                'post_parent' => $atts['id']
            ) );

            if ($reviews_query->found_posts == 0 || $reviews_query->found_posts < $this->settings->get( 'minReviews' )) return;
            
            //ob_start();
            if ($this->settings->get( 'widget_style' )) {
                include( WC_SAG_PLUGIN_DIR . 'views/shortcode-summary-' . $this->settings->get( 'widget_style' ) . '.php' );
            }
            //return ob_get_clean();
        }
    }
    
    /**
     * Render action content
     */
    public function render_action() {
        echo do_shortcode( '[wcsag_summary]' );
    }
}