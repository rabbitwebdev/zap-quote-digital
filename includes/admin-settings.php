<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
/**
 * Admin Settings
 */
// This file is included in the main plugin file and is responsible for displaying the settings page
add_action('admin_menu', function () {
    add_options_page('Quote Settings', 'Quote Settings', 'manage_options', 'quote-settings', 'render_quote_settings_page');
});

add_action('admin_init', function () {
    register_setting('quote_settings_group', 'quote_email_template');
    register_setting('quote_settings_group', 'quote_logo_url');
    register_setting('quote_settings_group', 'quote_email_subject');
    register_setting( 'quote_settings_group', 'quote_terms_page_id');
    

    add_settings_section('quote_main_section', 'Email Template Settings', null, 'quote-settings');

    add_settings_field('quote_email_template', 'Email Template', function () {
        echo '<p>You can use the following placeholders: <code>{{client_name}}</code>, <code>{{quote_table}}</code>, <code>{{client_desc}}</code>, <code>{{client_phone}}</code>, <code>{{client_address}}</code> </p>';
        $val = get_option('quote_email_template', '<p>Hello {{client_name}},</p><p>Project details: {{client_desc}}</p><p>Client details:<br>Phone: {{client_phone}} <br>Address: {{client_address}}<p>Here is your quote:</p>{{quote_table}}<p>Thanks!</p>');
        echo '<textarea name="quote_email_template" rows="10" cols="80" style="width:100%">' . esc_textarea($val) . '</textarea>';
    }, 'quote-settings', 'quote_main_section');

    add_settings_field('quote_logo_url', 'Company Logo URL', function () {
        $val = get_option('quote_logo_url', '');
        echo '<input type="text" name="quote_logo_url" value="' . esc_attr($val) . '" style="width: 60%;" />';
        echo '<p><em>Paste in a media library image URL or upload via Media.</em></p>';
    }, 'quote-settings', 'quote_main_section');

    add_settings_field('quote_email_subject', 'Email Subject', function () {
        $val = get_option('quote_email_subject', 'Your Quote');
        echo '<input type="text" name="quote_email_subject" value="' . esc_attr($val) . '" style="width: 60%;" />';
    }, 'quote-settings', 'quote_main_section');

    add_settings_field('quote_terms_page_id', 'Terms and Conditions Page', function () {
        $val = get_option('quote_terms_page_id', '');
        $pages = get_pages();
        echo '<select name="quote_terms_page_id" style="width: 60%;">';
        echo '<option value="">Select a page</option>';
        foreach ($pages as $page) {
            echo '<option value="' . esc_attr($page->ID) . '"' . selected($val, $page->ID, false) . '>' . esc_html($page->post_title) . '</option>';
        }
        echo '</select>';
    }, 'quote-settings', 'quote_main_section');
});
function render_quote_settings_page() {
    ?>
    <div class="wrap">
        <h1>Quote Email Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('quote_settings_group');
            do_settings_sections('quote-settings');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}