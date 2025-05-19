<?php
/*
Plugin Name: Building Quotes
Description: A plugin to create and send building quotes to clients.
Version: 2.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/register-post-type.php';
require_once plugin_dir_path(__FILE__) . 'includes/quote-meta-boxes.php';
require_once plugin_dir_path(__FILE__) . 'includes/quote-save-handler.php';

function zapquote_admin_enqueue_styles() {
    wp_enqueue_style('zapquote-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css');
}
add_action('admin_enqueue_scripts', 'zapquote_admin_enqueue_styles');




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

add_action('init', function () {
    add_rewrite_rule('^quote-response/?$', 'index.php?quote_response=1', 'top');
    add_rewrite_tag('%quote_response%', '1');
});

add_filter('query_vars', function ($vars) {
    $vars[] = 'quote_response';
    return $vars;
});

add_action('template_redirect', function () {
    if (get_query_var('quote_response')) {
        $quote_id = absint($_GET['quote_id'] ?? 0);
        $action = $_GET['action'] ?? '';
        $token = $_GET['token'] ?? '';

        $valid_actions = ['accept', 'reject'];

        if (!$quote_id || !in_array($action, $valid_actions)) {
            wp_die('Invalid request.');
        }

        $stored_token = get_post_meta($quote_id, '_quote_token', true);
        if (!$stored_token || $stored_token !== $token) {
            wp_die('Invalid or expired token.');
        }

        $new_status = $action === 'accept' ? 'accepted' : 'rejected';
        update_post_meta($quote_id, '_quote_status', $new_status);

        // Optional: notify admin
        $admin_email = get_option('admin_email');
        $client_name = get_post_meta($quote_id, '_client_name', true);
        $client_email = get_post_meta($quote_id, '_client_email', true);
        $client_phone = get_post_meta($quote_id, '_client_phone', true);
        $client_desc = get_post_meta($quote_id, '_client_desc', true);
        $title = get_the_title($quote_id);
        $items = get_post_meta($quote_id, '_quote_items', true) ?: [];
        $total = 0;
            foreach ($items as $item) {
            $total += floatval($item['cost'] ?? 0);
        }
        $deposit_value = get_post_meta($quote_id, '_quote_deposit_value', true) ?: 50;
        $deposit_type = get_post_meta($quote_id, '_quote_deposit_type', true) ?: 'percent';
        $deposit = ($deposit_type === 'percent')
            ? $total * (floatval($deposit_value) / 100)
            : floatval($deposit_value);
            $secret_key = get_option('scf_stripe_secret_key');
            \Stripe\Stripe::setApiKey($secret_key);
        $site_name = get_bloginfo('name');
$checkout_session = \Stripe\Checkout\Session::create([
    'payment_method_types' => ['card'],
    'customer_email' => $client_email,
    'line_items' => [[
        'price_data' => [
            'currency' => 'gbp',
            'product_data' => [
                'name' => "Deposit for Quote #{$quote_id} - {$site_name}",
                'description' => $client_desc,
            ],
            'unit_amount' => intval($deposit * 100),
        ],
        'quantity' => 1,
    ]],
    'mode' => 'payment',
    'success_url' => site_url('/thank-you?quote_payment=success&quote_id={' . $quote_id . '}'),
    'cancel_url' => site_url('?quote_payment=cancel&quote_id=' . $post_id),
]);

$payment_url = $checkout_session->url;
update_post_meta($post_id, '_stripe_checkout_url', esc_url($payment_url));
        wp_mail($admin_email, "Quote {$new_status}", "Client {$client_name} has {$new_status} the quote #{$quote_id}.");

        // Output message
        wp_head(); // to load styles
        echo "<div style='max-width:600px;margin:50px auto;font-family:sans-serif;text-align:center;'>
            <h2>Thank you {$client_name} !</h2>
             <p>Quote Title: {$title}</p>
            <p>You have successfully <strong>{$new_status}</strong> the quote.</p>
            <p>Quote ID: {$quote_id}</p>
           <p>{$client_desc}</p>
            <p>{$client_phone}</p>
        </div>";
        echo "<div style='max-width:600px;margin:50px auto;font-family:sans-serif;text-align:center;'>
            <h2>Quote Summary</h2>
            <table style='width:100%;border-collapse:collapse;'>
                <thead>
                    <tr>
                        <th style='border:1px solid #000;padding:8px;'>Item</th>
                        <th style='border:1px solid #000;padding:8px;'>Cost (£)</th>
                    </tr>
                </thead>
                <tbody>";
        foreach ($items as $item) {
            echo "<tr>
                    <td style='border:1px solid #000;padding:8px;'>{$item['desc']}</td>
                    <td style='border:1px solid #000;padding:8px;text-align:right;'>£" . number_format($item['cost'], 2) . "</td>
                </tr>";
        }
        echo "<tr>
                    <td style='border:1px solid #000;padding:8px;'><strong>Total</strong></td>
                    <td style='border:1px solid #000;padding:8px;text-align:right;'><strong>£" . number_format($total, 2) . "</strong></td>
                </tr>
            </tbody>
        </table>
        <p style='margin-top:20px;'><strong>Deposit:</strong> £" . number_format($deposit, 2) . "</p>
        <p style='margin-top:20px;'><strong>Payment Options:</strong></p>
        <p><strong>To secure your quote, pay the deposit here:</strong></p>
        <p><a href='{$payment_url}' class='button'>Pay Deposit</a></p>";
        wp_footer();
        exit;
    }
});

add_action('template_redirect', function () {
    if (isset($_GET['quote_payment']) && isset($_GET['quote_id'])) {
        $quote_id = intval($_GET['quote_id']);
        $status = $_GET['quote_payment'];

        if ($status === 'success') {
            update_post_meta($quote_id, '_quote_status', 'deposit_paid');

            // Send confirmation email
            $client_email = get_post_meta($quote_id, '_client_email', true);
            $client_name  = get_post_meta($quote_id, '_client_name', true);
             

            $subject = 'Deposit Received - Thank You!';
            $message = "
                <html><body>
                <h2>Thank you, {$client_name}!</h2>
                <p>We've received your deposit for Quote #{$quote_id}.</p>
                <p>Quote Summary:</p>
                <p>If you have any questions, feel free to reach out.</p>
                <p>We appreciate your business!</p>
                <p>Best regards,</p>
                <p>We'll be in touch soon to move forward.</p>
                <p><strong>Your Company Name</strong></p>
                </body></html>
            ";
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            wp_mail($client_email, $subject, $message, $headers);

            wp_redirect(site_url("/thank-you?quote_id={$quote_id}"));
            exit;
        }

        if ($status === 'cancel') {
            wp_redirect(site_url('/payment-cancelled/'));
            exit;
        }
    }
});

add_shortcode('quote_thank_you', function () {
    if (!isset($_GET['quote_id'])) return '<p>Quote not found.</p>';

    $quote_id = intval($_GET['quote_id']);

    if (get_post_type($quote_id) !== 'quote') return '<p>Invalid quote.</p>';

    $client_name = get_post_meta($quote_id, '_client_name', true);
    $client_email = get_post_meta($quote_id, '_client_email', true);
    $client_phone = get_post_meta($quote_id, '_client_phone', true);
    $client_desc = get_post_meta($quote_id, '_client_desc', true);
    $client_address = get_post_meta($quote_id, '_client_address', true);
    $status = get_post_meta($quote_id, '_quote_status', true);
    $items = get_post_meta($quote_id, '_quote_items', true) ?: [];
    $deposit = get_post_meta($quote_id, '_quote_deposit_amount', true);

    $total = 0;
    foreach ($items as $item) {
        $total += floatval($item['cost']);
    }

    ob_start();
    ?>
    <div class="quote-thank-you">
        <h2>Thank You, <?= esc_html($client_name) ?>!</h2>
        <p>Your quote has been processed.</p>
        <h3>Project Title: <?= esc_html(get_the_title($quote_id)) ?></h3>
        <p><strong>Description:</strong> <?= esc_html($client_desc) ?></p>
        <p>Quote ID: <?= esc_html($quote_id) ?></p>
        <h4>Client Details:</h4>
        <div class="client-details">
            <p><strong>Email:</strong> <?= esc_html($client_email) ?></p>
            <p><strong>Phone:</strong> <?= esc_html($client_phone) ?></p>
            <p><strong>Address:</strong> <?= nl2br(esc_html($client_address)) ?></p>
        </div>
        <ul>
            <li><strong>Status:</strong> <?= ucfirst(esc_html($status)) ?></li>
            <li><strong>Total Quote:</strong> £<?= number_format($total, 2) ?></li>
            <?php if ($deposit): ?>
                <li><strong>Deposit Paid:</strong> £<?= number_format(floatval($deposit), 2) ?></li>
            <?php endif; ?>
        </ul>
        <p>If you have any questions, feel free to <a href="<?= esc_url(home_url('/contact')) ?>">contact us</a>.</p>
    </div>
    <?php
    return ob_get_clean();
});
