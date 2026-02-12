<?php

class WC_SAG_Shortcode_Reviews {

    /** @var WC_SAG_Settings Plugin settings */
    protected $settings;

    /**
     * Constructor
     */
    public function __construct( $settings ) {
        $this->settings = $settings;

        add_shortcode( 'wcsag_reviews', array( $this, 'render_shortcode' ) );

        if ( $this->settings->get( 'enable_widget_product' ) == 1 ) {
            add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_action' ), 15 );
        }

        if ( !$this->settings->get( 'enable_new_widgets' ) ) {
            add_action( 'wp_ajax_wcsag_more_reviews', array( $this, 'ajax_more_review' ) );
            add_action( 'wp_ajax_nopriv_wcsag_more_reviews', array( $this, 'ajax_more_review' ) );
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

            return '<div class="grc-product-reviews" 
                        data-product-id="' . $atts['id'] . '"
                        ' . ($product_sku ? ' data-product-sku="' . $product_sku .'"' : '') . '>
                    </div>';
        }
        else {
            $ratings = wcsag_get_ratings( $atts['id'] );

            $reviews_query = new WP_Query( array(
                'post_type'      => 'wcsag_review',
                'post_status'    => 'publish',
                'post_parent'    => $atts['id'],
                'posts_per_page' => $this->settings->get( 'posts_per_page' )
            ) );

            if ($reviews_query->found_posts == 0 || $reviews_query->found_posts < $this->settings->get( 'minReviews' )) return;
            
            //ob_start();
            include( WC_SAG_PLUGIN_DIR . 'views/shortcode-reviews.php' );
            //return ob_get_clean();
        }
    }

    /**
     * Render action content
     */
    public function render_action() {
        echo do_shortcode( '[wcsag_reviews]' );
    }

    /**
     * Render shortcode content
     */
    public function ajax_more_review() {
        // AJAX check
        if ( ( empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest' ) ) die;
        // Params check
        if ( !isset( $_POST['currentPage'] ) || !isset( $_POST['id_product'] ) ) die;

        $paged = (int) $_POST['currentPage'];
        $product_id = (int) $_POST['id_product'];

        $reviews_query = new WP_Query( array(
            'post_type'      => 'wcsag_review',
            'post_status'    => 'publish',
            'post_parent'    => $product_id,
            'posts_per_page' => $this->settings->get( 'posts_per_page' ),
            'paged'          => $paged
        ) );

        include( WC_SAG_PLUGIN_DIR . 'views/shortcode-reviews-list.php' );
        exit;
    }
}