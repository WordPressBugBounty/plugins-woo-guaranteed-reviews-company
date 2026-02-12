<?php

class WC_SAG_Shortcode_Site_Rating {
    /** @var WC_SAG_Settings Plugin settings */
    protected $settings;

    /**
     * Constructor
     */
    public function __construct( $settings ) {
        $this->settings = $settings;

        if( $this->settings->get( 'enable_new_widgets' ) ) {
            add_shortcode( 'wcsag_site_rating', array( $this, 'render_shortcode' ) );
        }
    }

    /**
     * Render shortcode content
     */
    public function render_shortcode( $atts = array(), $content = null ) {
        return '<div class="grc-site-rating"></div>';
    }
}