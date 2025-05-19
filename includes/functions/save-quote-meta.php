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
require_once plugin_dir_path(__FILE__) . '../fpdf/fpdf.php';
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