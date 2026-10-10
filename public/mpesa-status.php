<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

header('Content-Type: application/json');

$orderRef = trim((string)($_GET['ref'] ?? ''));
if ($orderRef === '') {
    echo json_encode(['status' => 'error', 'message' => 'Missing reference']);
    exit;
}

$stmt = db()->prepare('
    SELECT o.status AS order_status, p.status AS payment_status
    FROM shop_orders o
    LEFT JOIN payments p ON p.order_id = o.id AND p.method = "mpesa"
    WHERE o.order_ref = ?
    ORDER BY p.id DESC
    LIMIT 1
');
$stmt->execute([$orderRef]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    exit;
}

// If order moved past pending, payment succeeded
if ($row['order_status'] !== 'pending') {
    unset($_SESSION['mpesa_pending']);
    echo json_encode(['status' => 'completed']);
    exit;
}

// Check payment record status
if ($row['payment_status'] === 'completed') {
    unset($_SESSION['mpesa_pending']);
    echo json_encode(['status' => 'completed']);
} elseif (in_array($row['payment_status'], ['failed', 'reversed'], true)) {
    echo json_encode(['status' => 'failed', 'message' => 'Payment failed or was cancelled.']);
} else {
    echo json_encode(['status' => 'pending']);
}