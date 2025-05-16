<?php
/*
Plugin Name: Building Quotes
Description: A plugin to create and send building quotes to clients.
Version: 1.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'lib/dompdf/autoload.inc.php';
use Dompdf\Dompdf;

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

       $items_table = '';
$total = 0;
foreach ($items as $item) {
    $items_table .= "<tr><td>{$item['desc']}</td><td style='text-align:right;'>£" . number_format($item['cost'], 2) . "</td></tr>";
    $total += $item['cost'];
}

$html_body = "
<html>
<body style='font-family: Arial, sans-serif;'>
<h2>Hello {$client_name},</h2>
<p>Here is your quote from <strong>Your Building Company</strong>.</p>
<table width='100%' style='border-collapse: collapse;'>
<thead>
<tr><th align='left'>Item</th><th align='right'>Cost</th></tr>
</thead>
<tbody>
$items_table
<tr><td><strong>Total</strong></td><td style='text-align:right;'><strong>£" . number_format($total, 2) . "</strong></td></tr>
</tbody>
</table>
<p>Thank you for your interest!</p>
</body>
</html>
";

$headers = ['Content-Type: text/html; charset=UTF-8'];


        wp_mail($client_email, "Your Building Quote", $body);
    }
});
