<?php
/**
 * M-Pesa STK Push callback.
 * Safaricom POSTs the result here. Must respond with HTTP 200 JSON.
 * No authentication — Safaricom doesn't send cookies/sessions.
 */

require_once dirname(__DIR__) . '/app/bootstrap.php';

// Read raw body
$raw = file_get_contents('php://input');
error_log('M-Pesa callback raw: ' . $raw);

$data = json_decode($raw, true);

// Always respond 200 so Safaricom doesn't retry
http_response_code(200);
header('Content-Type: application/json');

if (!is_array($data) || empty($data['Body']['stkCallback'])) {
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

$cb = $data['Body']['stkCallback'];
$resultCode = (int)($cb['ResultCode'] ?? -1);
$checkoutId = (string)($cb['CheckoutRequestID'] ?? '');
$merchantId = (string)($cb['MerchantRequestID'] ?? '');

// Find the payment row by CheckoutRequestID
$stmt = db()->prepare('SELECT * FROM payments WHERE mpesa_checkout_request_id = ? LIMIT 1');
$stmt->execute([$checkoutId]);
$payment = $stmt->fetch();

if (!$payment) {
    error_log('M-Pesa callback: no payment row for CheckoutRequestID ' . $checkoutId);
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    exit;
}

// Extract callback metadata
$receipt = null;
$amountPaid = null;
$txDate = null;
if (!empty($cb['CallbackMetadata']['Item'])) {
    foreach ($cb['CallbackMetadata']['Item'] as $item) {
        $name = $item['Name'] ?? '';
        $val  = $item['Value'] ?? null;
        if ($name === 'MpesaReceiptNumber') $receipt = (string)$val;
        if ($name === 'Amount')            $amountPaid = (float)$val;
        if ($name === 'TransactionDate')   $txDate = (string)$val;
    }
}

$pdo = db();

if ($resultCode === 0) {
    // SUCCESS
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE payments SET status=?, mpesa_receipt_number=?, transaction_date=?, raw_callback=? WHERE id=?')
            ->execute([
                'completed',
                $receipt,
                $txDate ? date('Y-m-d H:i:s', strtotime($txDate)) : null,
                $raw,
                $payment['id'],
            ]);

        $pdo->prepare('UPDATE shop_orders SET status=? WHERE id=?')
            ->execute(['confirmed', $payment['order_id']]);

        log_event('mpesa.payment_completed', 'shop_order', (int)$payment['order_id'], [
            'receipt' => $receipt,
            'amount'  => $amountPaid,
        ]);

        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('M-Pesa callback DB error: ' . $ex->getMessage());
    }
} else {
    // FAILED / CANCELLED
    $reason = (string)($cb['ResultDesc'] ?? 'Payment failed or was cancelled.');
    $pdo->prepare('UPDATE payments SET status=?, raw_callback=? WHERE id=?')
        ->execute(['failed', $raw, $payment['id']]);

    log_event('mpesa.payment_failed', 'shop_order', (int)$payment['order_id'], [
        'result_code' => $resultCode,
        'reason'      => $reason,
    ]);
}

echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);