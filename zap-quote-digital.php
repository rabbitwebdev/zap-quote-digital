<?php
/*
Plugin Name: Building Quotes
Description: A plugin to create and send building quotes to clients.
Version: 2.0
Author: Your Name
*/

if (!defined('ABSPATH')) exit;





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
    $client_phone = get_post_meta($post->ID, '_client_phone', true);
    $client_desc = get_post_meta($post->ID, '_client_desc', true);
    $client_address = get_post_meta($post->ID, '_client_address', true);
    $items = get_post_meta($post->ID, '_quote_items', true) ?: [];
    $status = get_post_meta($post->ID, '_quote_status', true) ?: 'draft';

    $deposit_type = get_post_meta($post->ID, '_quote_deposit_type', true) ?: 'percent';
    $deposit_value = get_post_meta($post->ID, '_quote_deposit_value', true) ?: 50;
      // Calculate total
    $total = 0;
    foreach ($items as $item) {
        $total += floatval($item['cost'] ?? 0);
    }

     // Calculate deposit
    $deposit = ($deposit_type === 'percent')
        ? $total * (floatval($deposit_value) / 100)
        : floatval($deposit_value);
    
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
    <p><label>Client Phone: <input type="text" name="client_phone" value="<?= esc_attr($client_phone) ?>" /></label></p>
        <p><label>Client Address: <textarea name="client_address" rows="4" style="width:100%;"><?= esc_textarea($client_address) ?></textarea></label></p>
    <p><label>Project Description: <textarea name="client_desc" rows="4" style="width:100%;"><?= esc_textarea($client_desc) ?></textarea></label></p>
    <p><strong>Quote Items:</strong></p>
    <div id="quote-items">
        <?php foreach ($items as $i => $item): ?>
            <p>
                <input type="text" name="quote_items[<?= $i ?>][desc]" placeholder="Item description" value="<?= esc_attr($item['desc']) ?>" />
                <input type="number" name="quote_items[<?= $i ?>][cost]" step="0.01" value="<?= esc_attr($item['cost']) ?>" /> 
            </p>
        <?php endforeach; ?>
        <p><button type="button" onclick="addQuoteItem()">Add Item</button></p>
    </div>
    <label>Quote Total: 
        <input type="text" name="quote_total" value="£<?= number_format($total, 2) ?>" onchange="updateTotalDisplay()" readonly />
    </label>
     <p><strong>Total Cost: £<?= number_format($total, 2) ?></strong></p>
     <h4>Deposit</h4>
    <p>
        <label>Type: 
            <select name="quote_deposit_type" id="quote_deposit_type" onchange="updateDepositDisplay()">
                <option value="percent" <?= selected($deposit_type, 'percent') ?>>Percentage (%)</option>
                <option value="custom" <?= selected($deposit_type, 'custom') ?>>Custom Amount (£)</option>
            </select>
        </label>
    </p>
    <p>
        <label>Value: 
            <input type="number" name="quote_deposit_value" id="quote_deposit_value" value="<?= esc_attr($deposit_value) ?>" step="0.01" onchange="updateDepositDisplay()" />
        </label>
    </p>
    <p><strong>Calculated Deposit: £<span id="deposit-calculated"><?= number_format($deposit, 2) ?></span></strong></p>
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
         function updateDepositDisplay() {
            const type = document.getElementById('quote_deposit_type').value;
            const val = parseFloat(document.getElementById('quote_deposit_value').value) || 0;
            const total = <?= $total ?>;
            let calculated = 0;
            if (type === 'percent') {
                calculated = total * (val / 100);
            } else {
                calculated = val;
            }
            document.getElementById('deposit-calculated').textContent = calculated.toFixed(2);
        }
        function updateTotalDisplay() {
            const total = <?= $total ?>;
            document.querySelector('input[name="quote_total"]').value = '£' + total.toFixed(2);
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
    if (isset($_POST['client_desc'])) {
        update_post_meta($post_id, '_client_desc', sanitize_textarea_field($_POST['client_desc']));
    }
     if (isset($_POST['client_address'])) {
        update_post_meta($post_id, '_client_address', sanitize_textarea_field($_POST['client_address']));
    }
    if (isset($_POST['client_phone'])) {
        update_post_meta($post_id, '_client_phone', sanitize_text_field($_POST['client_phone']));
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

    if (isset($_POST['quote_deposit_type'])) {
    update_post_meta($post_id, '_quote_deposit_type', sanitize_text_field($_POST['quote_deposit_type']));
    }
    if (isset($_POST['quote_deposit_value'])) {
        update_post_meta($post_id, '_quote_deposit_value', floatval($_POST['quote_deposit_value']));
    }

    if (isset($_POST['quote_terms_page_id'])) {
    update_option('quote_terms_page_id', intval($_POST['quote_terms_page_id']));
}

    // Send Email if requested
  if (isset($_POST['send_quote'])) {
    $client_email = get_post_meta($post_id, '_client_email', true);
    $client_name = get_post_meta($post_id, '_client_name', true);
    $client_phone = get_post_meta($post_id, '_client_phone', true);
    $client_desc = get_post_meta($post_id, '_client_desc', true);
    $client_address = get_post_meta($post_id, '_client_address', true);
    $items = get_post_meta($post_id, '_quote_items', true);
    $deposit_type = get_post_meta($post_id, '_quote_deposit_type', true) ?: 'percent';
$deposit_value = get_post_meta($post_id, '_quote_deposit_value', true) ?: 50;
$project_title = get_the_title($post_id);


   $template = get_option('quote_email_template', '');
   $terms_page_id = get_option('quote_terms_page_id');
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
$deposit = ($deposit_type === 'percent')
    ? $total * (floatval($deposit_value) / 100)
    : floatval($deposit_value);
$quote_table .= "<tr><td><strong>Total</strong></td><td style='text-align:right;'><strong>£" . number_format($total, 2) . "</strong></td></tr>
<tr><td><strong>Deposit Required</strong></td><td style='text-align:right;'><strong>£" . number_format($deposit, 2) . "</strong></td></tr>
</tbody></table>";


// Replace placeholders
$body = $template;
$body = str_replace('{{client_name}}', $client_name, $body);
$body = str_replace('{{quote_table}}', $quote_table, $body);
$body = str_replace('{{client_desc}}', $client_desc, $body);
$body = str_replace('{{client_phone}}', $client_phone, $body);
$body = str_replace('{{client_address}}', nl2br($client_address), $body);
$body = str_replace('{{quote_id}}', $post_id, $body);
$body = str_replace('{{quote_title}}', $project_title, $body);
$terms_link = get_permalink($terms_page_id);
$body .= "<p><small>By accepting this quote, you agree to our <a href='{$terms_link}'>Terms & Conditions</a>.</small></p>";
$body .= "<p>
    <a href='{$accept_url}' style='background:#4CAF50;color:white;padding:10px 15px;text-decoration:none;border-radius:4px;'>Accept Quote</a>
    &nbsp;
    <a href='{$reject_url}' style='background:#f44336;color:white;padding:10px 15px;text-decoration:none;border-radius:4px;'>Reject Quote</a>
</p>";

if ($logo) {
    $body = "<p><img src='{$logo}' style='max-width:200px;' /></p>" . $body;
}

    $headers = ['Content-Type: text/html; charset=UTF-8'];
require_once plugin_dir_path(__FILE__) . 'fpdf/fpdf.php';
    // --- FPDF PDF Generation ---
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
     if ($logo) {
            $pdf->Image($logo, 10, 10, 50);
            $pdf->Ln(30);
        }
    $pdf->Cell(0, 10, 'Building Quote', 0, 1, 'C');

    $pdf->SetFont('Arial', '', 12);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, "Quote ID: {$post_id}", 0, 1);
    $pdf->Cell(0, 10, "Quote Title: {$project_title}", 0, 1);
    $pdf->Cell(0, 10, "Date: " . date('Y-m-d'), 0, 1);
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, "Client Details", 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, "Name: {$client_name}", 0, 1);
    $pdf->Cell(0, 10, "Email: {$client_email}", 0, 1);
    $pdf->Cell(0, 10, "Phone: {$client_phone}", 0, 1);
    $pdf->Cell(0, 10, "Address: {$client_address}", 0, 1);
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, "Quote Details", 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, "Description: {$client_desc}", 0, 1);
    $pdf->Ln(5);

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(120, 8, 'Item', 1);
    $pdf->Cell(40, 8, 'Cost', 1);
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
    $pdf->Cell(40, 8, '£' . number_format($total, 2), 1, 0, 'R');
    $pdf->Ln(5);

      $deposit = ($deposit_type === 'percent')
    ? $total * (floatval($deposit_value) / 100)
    : floatval($deposit_value);

     $pdf->SetFont('Arial', '', 12);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, "£" . number_format($deposit, 2), 0, 1, 'C');

    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(0, 0, 255);
    $pdf->Write(5, 'View Terms & Conditions', $terms_link);
    $pdf->SetTextColor(0, 0, 0);

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
                'name' => "Deposit for Quote #{$post_id} - {$site_name}",
                'description' => $client_desc,
            ],
            'unit_amount' => intval($deposit * 100),
        ],
        'quantity' => 1,
    ]],
    'mode' => 'payment',
    'success_url' => site_url('/return?quote_payment=success&quote_id={' . $quote_id . '}'),
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
                <p>We'll be in touch soon to move forward.</p>
                <p><strong>Your Company Name</strong></p>
                </body></html>
            ";
            $headers = ['Content-Type: text/html; charset=UTF-8'];
            wp_mail($client_email, $subject, $message, $headers);

            wp_redirect(site_url('/thank-you/'));
            exit;
        }

        if ($status === 'cancel') {
            wp_redirect(site_url('/payment-cancelled/'));
            exit;
        }
    }
});
