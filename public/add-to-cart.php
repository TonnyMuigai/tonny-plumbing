<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('shop.php');
}
verify_csrf();

$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$qty       = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
$back      = (string)($_POST['back'] ?? 'shop.php');

// Only allow relative local redirects
if ($back === '' || preg_match('#^https?://#i', $back) || str_starts_with($back, '//')) {
    $back = 'shop.php';
}

if (!$productId || !$qty || $qty < 1) {
    flash('error', 'Invalid product or quantity.');
    redirect($back);
}

$stmt = db()->prepare('SELECT id, name, price, stock_qty FROM products WHERE id=? AND is_active=1 LIMIT 1');
$stmt->execute([$productId]);
$product = $stmt->fetch();
if (!$product) {
    flash('error', 'That product is no longer available.');
    redirect($back);
}

$stock = (int)$product['stock_qty'];
if ($stock <= 0) {
    flash('error', 'That product is out of stock.');
    redirect($back);
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$current = (int)($_SESSION['cart'][$productId] ?? 0);
$newQty  = min($current + $qty, $stock, 99); // cap at stock and 99
$_SESSION['cart'][$productId] = $newQty;

flash('success', $product['name'] . ' added to cart.');
redirect($back);