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
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';

function zapquote_admin_enqueue_styles() {
    wp_enqueue_style('zapquote-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css');
}
add_action('admin_enqueue_scripts', 'zapquote_admin_enqueue_styles');


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
    $pdf->Cell(0, 10, "Date: " . date('d-m-Y'), 0, 1);
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
    }
  

    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(120, 8, 'Total', 1);
    $pdf->Cell(40, 8, '' . number_format($total, 2), 1, 0, 'R');
    $pdf->Ln(5);

     $pdf->SetFont('Arial', '', 12);
    $pdf->Ln(5);
    $pdf->Cell(0, 10, 'Deposit to Pay:' . number_format($deposit, 2), 0, 1, 'L');



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
