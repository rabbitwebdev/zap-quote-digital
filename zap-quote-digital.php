<?php
/*
Plugin Name: Building Quotes
Description: A plugin to create and send building quotes to clients.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'fpdf/fpdf.php';



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

// Add Meta Boxes
add_action('add_meta_boxes', function () {
    add_meta_box('quote_details', 'Quote Details', 'quote_details_callback', 'quote', 'normal', 'high');
});

function quote_details_callback($post) {
    $client_name = get_post_meta($post->ID, '_client_name', true);
    $client_email = get_post_meta($post->ID, '_client_email', true);
    $items = get_post_meta($post->ID, '_quote_items', true) ?: [];
    $status = get_post_meta($post->ID, '_quote_status', true) ?: 'draft';
      // Calculate total
    $total = 0;
    foreach ($items as $item) {
        $total += floatval($item['cost'] ?? 0);
    }
    
?>
<p><label>Status: 
    <select name="quote_status">
        <option value="draft" <?= selected($status, 'draft') ?>>Draft</option>
        <option value="sent" <?= selected($status, 'sent') ?>>Sent</option>
        <option value="accepted" <?= selected($status, 'accepted') ?>>Accepted</option>
        <option value="rejected" <?= selected($status, 'rejected') ?>>Rejected</option>
    </select>
</label></p>
    <p><label>Client Name: <input type="text" name="client_name" value="<?= esc_attr($client_name) ?>" /></label></p>
    <p><label>Client Email: <input type="email" name="client_email" value="<?= esc_attr($client_email) ?>" /></label></p>
    <div id="quote-items">
        <?php foreach ($items as $i => $item): ?>
            <p>
                <input type="text" name="quote_items[<?= $i ?>][desc]" placeholder="Item description" value="<?= esc_attr($item['desc']) ?>" />
                <input type="number" name="quote_items[<?= $i ?>][cost]" step="0.01" value="<?= esc_attr($item['cost']) ?>" /> 
            </p>
        <?php endforeach; ?>
        <p><button type="button" onclick="addQuoteItem()">Add Item</button></p>
    </div>
     <p><strong>Total Cost: £<?= number_format($total, 2) ?></strong></p>
    <p><button type="submit" name="send_quote" class="button button-primary">Send Quote</button></p>

    <script>
        function addQuoteItem() {
            const index = document.querySelectorAll('#quote-items p').length - 1;
            const container = document.createElement('p');
            container.innerHTML = `
                <input type="text" name="quote_items[${index}][desc]" placeholder="Item description" />
                <input type="number" name="quote_items[${index}][cost]" step="0.01" />
            `;
            document.querySelector('#quote-items').insertBefore(container, document.querySelector('#quote-items').lastElementChild);
        }
    </script>
    <?php
}

// Save Meta Box
add_action('save_post_quote', function ($post_id) {
    if (!get_post_meta($post_id, '_quote_token', true)) {
    $token = wp_generate_password(20, false);
    update_post_meta($post_id, '_quote_token', $token);
}

    if (isset($_POST['quote_status'])) {
    update_post_meta($post_id, '_quote_status', sanitize_text_field($_POST['quote_status']));
}
    if (isset($_POST['client_name'])) {
        update_post_meta($post_id, '_client_name', sanitize_text_field($_POST['client_name']));
    }
    if (isset($_POST['client_email'])) {
        update_post_meta($post_id, '_client_email', sanitize_email($_POST['client_email']));
    }
    if (isset($_POST['quote_items'])) {
        $clean_items = array_map(function ($item) {
            return [
                'desc' => sanitize_text_field($item['desc']),
                'cost' => floatval($item['cost']),
            ];
        }, $_POST['quote_items']);
        update_post_meta($post_id, '_quote_items', $clean_items);
    }

    // Send Email if requested
  if (isset($_POST['send_quote'])) {
    $client_email = get_post_meta($post_id, '_client_email', true);
    $client_name = get_post_meta($post_id, '_client_name', true);
    $items = get_post_meta($post_id, '_quote_items', true);

   $template = get_option('quote_email_template', '');
$logo = get_option('quote_logo_url', '');
$quote_token = get_post_meta($post_id, '_quote_token', true);
$accept_url = add_query_arg([
    'quote_id' => $post_id,
    'action' => 'accept',
    'token' => $quote_token
], home_url('/quote-response/'));

$reject_url = add_query_arg([
    'quote_id' => $post_id,
    'action' => 'reject',
    'token' => $quote_token
], home_url('/quote-response/'));

$quote_table = '<table width="100%" style="border-collapse: collapse;"><thead><tr><th align="left">Item</th><th align="right">Cost</th></tr></thead><tbody>';
$total = 0;

foreach ($items as $item) {
    $quote_table .= "<tr><td>{$item['desc']}</td><td style='text-align:right;'>£" . number_format($item['cost'], 2) . "</td></tr>";
    $total += $item['cost'];
}
$quote_table .= "<tr><td><strong>Total</strong></td><td style='text-align:right;'><strong>£" . number_format($total, 2) . "</strong></td></tr></tbody></table>";

// Replace placeholders
$body = $template;
$body = str_replace('{{client_name}}', $client_name, $body);
$body = str_replace('{{quote_table}}', $quote_table, $body);
$body .= "<p>
    <a href='{$accept_url}' style='background:#4CAF50;color:white;padding:10px 15px;text-decoration:none;border-radius:4px;'>Accept Quote</a>
    &nbsp;
    <a href='{$reject_url}' style='background:#f44336;color:white;padding:10px 15px;text-decoration:none;border-radius:4px;'>Reject Quote</a>
</p>";

if ($logo) {
    $body = "<p><img src='{$logo}' style='max-width:200px;' /></p>" . $body;
}

    $headers = ['Content-Type: text/html; charset=UTF-8'];

    // --- FPDF PDF Generation ---
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Building Quote', 0, 1, 'C');

    $pdf->SetFont('Arial', '', 12);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, "Client: {$client_name}", 0, 1);
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(120, 8, 'Item', 1);
    $pdf->Cell(40, 8, 'Cost (£)', 1);
    $pdf->Ln();

    $pdf->SetFont('Arial', '', 12);
    foreach ($items as $item) {
        $pdf->Cell(120, 8, $item['desc'], 1);
        $pdf->Cell(40, 8, number_format($item['cost'], 2), 1, 0, 'R');
        $pdf->Ln();
        $total += $item['cost'];
    }

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(120, 8, 'Total', 1);
    $pdf->Cell(40, 8, number_format($total, 2), 1, 0, 'R');

    // Save PDF temporarily
    $pdf_path = plugin_dir_path(__FILE__) . "temp-quote-{$post_id}.pdf";
    $pdf->Output('F', $pdf_path);

    $attachments = [$pdf_path];

    wp_mail($client_email, "Your Building Quote", $body, $headers, $attachments);

    update_post_meta($post_id, '_quote_status', 'sent');

    // Delete temp PDF after script ends
    register_shutdown_function(function () use ($pdf_path) {
        if (file_exists($pdf_path)) {
            unlink($pdf_path);
        }
    });
}


});

add_action('admin_menu', function () {
    add_options_page('Quote Settings', 'Quote Settings', 'manage_options', 'quote-settings', 'render_quote_settings_page');
});

add_action('admin_init', function () {
    register_setting('quote_settings_group', 'quote_email_template');
    register_setting('quote_settings_group', 'quote_logo_url');

    add_settings_section('quote_main_section', 'Email Template Settings', null, 'quote-settings');

    add_settings_field('quote_email_template', 'Email Template', function () {
        $val = get_option('quote_email_template', '<p>Hello {{client_name}},</p><p>Here is your quote:</p>{{quote_table}}<p>Thanks!</p>');
        echo '<textarea name="quote_email_template" rows="10" cols="80" style="width:100%">' . esc_textarea($val) . '</textarea>';
    }, 'quote-settings', 'quote_main_section');

    add_settings_field('quote_logo_url', 'Company Logo URL', function () {
        $val = get_option('quote_logo_url', '');
        echo '<input type="text" name="quote_logo_url" value="' . esc_attr($val) . '" style="width: 60%;" />';
        echo '<p><em>Paste in a media library image URL or upload via Media.</em></p>';
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
        wp_mail($admin_email, "Quote {$new_status}", "Client {$client_name} has {$new_status} the quote #{$quote_id}.");

        // Output message
        wp_head(); // to load styles
        echo "<div style='max-width:600px;margin:50px auto;font-family:sans-serif;text-align:center;'>
            <h2>Thank you {$client_name} !</h2>
            <p>You have successfully <strong>{$new_status}</strong> the quote.</p>
        </div>";
        echo do_shortcode( ' [stripe_subscription_button] ' );
        wp_footer();
        exit;
    }
});
