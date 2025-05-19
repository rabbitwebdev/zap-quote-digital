<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Quote Meta Boxes
 */

 // Add Meta Boxes
add_action('add_meta_boxes', function () {
    add_meta_box('quote_details', 'Quote Details', 'building_quotes_meta_box', 'quote', 'normal', 'high');
});

function building_quotes_meta_box($post) {
    require plugin_dir_path(__FILE__) . 'views/meta-box-fields.php';
}
