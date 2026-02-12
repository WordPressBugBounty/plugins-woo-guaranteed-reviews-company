<?php

class WC_SAG_Frontend_Loop_Rating {
    
    /** @var WC_SAG_Settings Plugin settings */
    protected $settings;

    /**
     * Constructor
     */
    public function __construct( $settings ) {
        $this->settings = $settings;

		add_shortcode( 'wcsag_category', array( $this, 'render_shortcode' ) );

        if ( $this->settings->get( 'enable_loop_rating' ) == 1 ) {
            add_filter( 'woocommerce_after_shop_loop_item_title', array( $this, 'render_filter' ), 2 );
        }
    }

    /**
     * Render shortcode content
     */
    public function render_shortcode( $atts = array(), $content = null ) {
        global $product;
        $atts = shortcode_atts( array( 'id' => ($product ? $product->get_id() : get_the_ID()) ), $atts );

        if( $this->settings->get( 'enable_new_widgets' ) ) {

            if ( !$product && $atts['id'] ) {
                $product = wc_get_product( $atts['id'] );
            }

            $product_sku = $product ? $product->get_sku() : false;

            return '<div class="grc-category-stars" 
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

            if ( $ratings['average'] && $reviews_query->found_posts !== 0 ) {
                include( WC_SAG_PLUGIN_DIR . 'views/loop-star-rating.php' );
            }
        }
    }

    /**
     * Render action content
     */
    public function render_filter() {
        echo do_shortcode( '[wcsag_category]' );
    }
}