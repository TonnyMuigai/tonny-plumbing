<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/mpesa.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('shop.php');
}
verify_csrf();

$orderRef = trim((string)($_POST['order_ref'] ?? ''));
$phone    = trim((string)($_POST['phone'] ?? ''));

if ($orderRef === '' || $phone === '') {
    flash('error', 'Order reference and phone number are required.');
    redirect('shop.php');
}

$stmt = db()->prepare('SELECT * FROM shop_orders WHERE order_ref = ? LIMIT 1');
$stmt->execute([$orderRef]);
$order = $stmt->fetch();
if (!$order || $order['status'] !== 'pending') {
    flash('error', 'Order not found or already paid.');
    redirect('shop.php');
}

try {
    $result = mpesa_stk_push(
        (int)$order['id'],
        $order['order_ref'],
        (float)$order['total'],
        $phone
    );

    // Daraja response codes
    if (!empty($result['ResponseCode']) && $result['ResponseCode'] === '0') {
        // Success — STK prompt sent to customer
        $checkoutId = $result['CheckoutRequestID'] ?? '';
        $merchantId = $result['MerchantRequestID'] ?? '';

        // Store pending payment record
        $payRef = 'PAY-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $ins = db()->prepare('INSERT INTO payments (payment_ref, order_id, method, amount, status, mpesa_checkout_request_id) VALUES (?,?,?,?,?,?)');
        $ins->execute([
            $payRef,
            $order['id'],
            'mpesa',
            $order['total'],
            'pending',
            $checkoutId,
        ]);
        log_event('mpesa.stk_sent', 'shop_order', (int)$order['id'], [
            'checkout_request_id' => $checkoutId,
            'phone' => substr($phone, -4), // log only last 4 digits
        ]);

        $_SESSION['mpesa_pending'] = [
            'order_ref'        => $order['order_ref'],
            'checkout_id'      => $checkoutId,
            'payment_ref'      => $payRef,
            'amount'           => $order['total'],
            'sent_at'          => time(),
        ];
        redirect('mpesa-wait.php');
    } else {
        // Daraja rejected the request
        $msg = $result['errorMessage'] ?? $result['ResponseDescription'] ?? 'Unknown error';
        error_log('M-Pesa STK rejected: ' . json_encode($result));
        flash('error', 'M-Pesa could not process the request: ' . $msg);
        redirect('pay-order.php?ref=' . urlencode($orderRef));
    }
} catch (Throwable $ex) {
    error_log('M-Pesa initiate failed: ' . $ex->getMessage());
    flash('error', $ex->getMessage());
    redirect('pay-order.php?ref=' . urlencode($orderRef));
}