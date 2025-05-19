<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Quote Meta Boxes
 */

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

<p class="quote-p"><label>Status: 
    <select name="quote_status">
        <option value="draft" <?= selected($status, 'draft') ?>>Draft</option>
        <option value="sent" <?= selected($status, 'sent') ?>>Sent</option>
        <option value="accepted" <?= selected($status, 'accepted') ?>>Accepted</option>
        <option value="rejected" <?= selected($status, 'rejected') ?>>Rejected</option>
        <option value="deposit_paid" <?= selected($status, 'deposit_paid') ?>>Deposit Paid</option>
    </select>
</label></p>
    <p class="quote-p"><label>Client Name: <input type="text" name="client_name" value="<?= esc_attr($client_name) ?>" /></label></p>
    <p class="quote-p"><label>Client Email: <input type="email" name="client_email" value="<?= esc_attr($client_email) ?>" /></label></p>
    <p class="quote-p"><label>Client Phone: <input type="text" name="client_phone" value="<?= esc_attr($client_phone) ?>" /></label></p>
        <p class="quote-p"><label>Client Address: <textarea name="client_address" rows="4" style="width:100%;"><?= esc_textarea($client_address) ?></textarea></label></p>
    <p class="quote-p"><label>Project Description: <textarea name="client_desc" rows="4" style="width:100%;"><?= esc_textarea($client_desc) ?></textarea></label></p>
    <p class="quote-item-title"><strong>Quote Items:</strong></p>
    <div id="quote-items">
        <?php foreach ($items as $i => $item): ?>
            <p>
                <input type="text" name="quote_items[<?= $i ?>][desc]" placeholder="Item description" value="<?= esc_attr($item['desc']) ?>" />
                <input type="number" name="quote_items[<?= $i ?>][cost]" step="0.01" value="<?= esc_attr($item['cost']) ?>" /> 
            </p>
        <?php endforeach; ?>
        <p><button class="btn add-btn-item" type="button" onclick="addQuoteItem()">Add Item</button></p>
    </div>
    <label class="quote-result">Quote Total: 
        <input type="text" name="quote_total" value="£<?= number_format($total, 2) ?>" onchange="updateTotalDisplay()" readonly />
    </label>
     <p class="quote-total-cost"><strong>Total Cost: £<?= number_format($total, 2) ?></strong></p>
     <h4>Deposit</h4>
    <p class="quote-result">
        <label>Type: 
            <select name="quote_deposit_type" id="quote_deposit_type" onchange="updateDepositDisplay()">
                <option value="percent" <?= selected($deposit_type, 'percent') ?>>Percentage (%)</option>
                <option value="custom" <?= selected($deposit_type, 'custom') ?>>Custom Amount (£)</option>
            </select>
        </label>
    </p>
    <p class="quote-result">
        <label>Value: 
            <input type="number" name="quote_deposit_value" id="quote_deposit_value" value="<?= esc_attr($deposit_value) ?>" step="0.01" onchange="updateDepositDisplay()" />
        </label>
    </p>
    <p class="quote-result"><strong>Calculated Deposit: £<span id="deposit-calculated"><?= number_format($deposit, 2) ?></span></strong></p>
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