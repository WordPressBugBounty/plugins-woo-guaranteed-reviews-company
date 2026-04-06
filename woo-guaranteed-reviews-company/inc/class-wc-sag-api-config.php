<?php

class WC_SAG_API_Config extends WC_SAG_API_Abstract_Route
{
    /** @var string Route slug */
    protected $route = '/config';

    /** @var string Query var */
    protected $query_var = 'wcsag_config';

    /**
     * Run the endpoint
     */
    protected function run() {

        if(!isset($_GET['key']) || !isset($_GET['lang'])) {
            return;
        }

        $langApiKey = $this->settings->guess_api_key_for_language($_GET['lang']);

        if($_GET['key'] !== $langApiKey) {
            return;
        }

        // Get config
        $config = $this->get_config();
        
        header('Content-Type: application/json');
        echo json_encode($config);
        exit;
    }

    /**
     * Format expected configuration
     */
    protected function get_config() {
        return array(
            'api_key_raw'            => $this->settings->get('api_key_raw'),
            'public_api_key'         => $this->settings->get('public_api_key'),
            'wc_statuses'            => $this->settings->get('wc_statuses'),
            'enable_widget_js'       => $this->settings->get('enable_widget_js'),
            'enable_widget_product'  => $this->settings->get('enable_widget_product'),
            'widget_style'           => $this->settings->get('widget_style'),
            'minReviews'             => $this->settings->get('minReviews'),
            'enable_widget_footer'   => $this->settings->get('enable_widget_footer'),
            'enable_loop_rating'     => $this->settings->get('enable_loop_rating'),
            'posts_per_page'         => $this->settings->get('posts_per_page'),
            'source_lang_flags'      => $this->settings->get('source_lang_flags'),
            'star_color'			 => $this->settings->get('star_color'),
            'enable_new_widgets'     => $this->settings->get('enable_new_widgets'),
            'use_old_orders_method'  => $this->settings->get('use_old_orders_method'),
            'send_phone'             => $this->settings->get('send_phone'),
        );

    }
}
