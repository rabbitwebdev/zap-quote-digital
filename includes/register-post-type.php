<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * Register Custom Post Type
 */
// Register Custom Post Type
add_action('init', function () {
    register_post_type('quote', [
        'labels' => [
            'name' => 'Quotes',
            'singular_name' => 'Quote'
        ],
        'public' => false,
        'show_ui' => true,
        'menu_icon' => 'dashicons-clipboard',
        'supports' => ['title'],
    ]);
});