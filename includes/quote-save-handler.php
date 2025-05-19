<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * Quote Save Handler
 *
 * Handles the saving of quote meta data when a quote post is saved.
 */
// This file is included in the main plugin file and is responsible for saving the meta data
add_action('save_post_quote', function ($post_id) {
    require_once plugin_dir_path(__FILE__) . 'functions/save-quote-meta.php';
});