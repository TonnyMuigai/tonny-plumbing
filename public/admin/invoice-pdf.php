<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('Order ID is required.');
}

$stmt = db()->prepare('SELECT * FROM shop_orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

$itemStmt = db()->prepare('SELECT product_name, sku, quantity, unit_price, line_total FROM shop_order_items WHERE order_id = ? ORDER BY id ASC');
$itemStmt->execute([$id]);
$items = $itemStmt->fetchAll();

$siteName   = setting('site_name', 'TONNY');
$tagline    = setting('footer_tagline');
$whatsapp   = setting('whatsapp_number', '254723777601');
$logo       = setting('logo_image_path');
$brandPrimary = branding_clean_hex(setting('color_primary', '#102a43'));
$brandAccent  = branding_clean_hex(setting('color_accent', '#0d9488'));

// Dompdf needs a filesystem path or absolute URL for images
$logoTag = '';
if ($logo !== '') {
    $absLogo = realpath(dirname(__DIR__) . '/' . ltrim($logo, '/'));
    if ($absLogo && is_file($absLogo)) {
        $logoTag = '<img src="' . htmlspecialchars('file://' . str_replace('\\', '/', $absLogo), ENT_QUOTES) . '" alt="" style="height:56px;">';
    }
}

$statusLabels = [
    'pending'    => 'Pending',
    'confirmed'  => 'Confirmed',
    'processing' => 'Processing',
    'ready'      => 'Ready for pickup',
    'dispatched' => 'Dispatched',
    'completed'  => 'Completed',
    'cancelled'  => 'Cancelled',
];
$statusLabel = $statusLabels[$order['status']] ?? ucfirst($order['status']);

ob_start();
?><!doctype html>
<html><head><meta charset="utf-8"><title>Invoice <?= e($order['order_ref']) ?></title>
<style>
  * { box-sizing: border-box; }
  body { font-family: 'DejaVu Sans', sans-serif; color: #0f172a; font-size: 12px; line-height: 1.5; margin: 0; }
  .wrap { padding: 24px 28px; }
  .head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid <?= e($brandPrimary) ?>; padding-bottom: 14px; }
  .brand-name { font-size: 20px; font-weight: bold; color: <?= e($brandPrimary) ?>; margin: 0; }
  .brand-name span { color: <?= e($brandAccent) ?>; }
  .muted { color: #64748b; font-size: 11px; }
  .doc-title { text-align: right; }
  .doc-title h1 { margin: 0; font-size: 22px; color: <?= e($brandPrimary) ?>; }
  .doc-title p { margin: 4px 0 0; font-size: 11px; color: #64748b; }

  .grid { display: flex; gap: 24px; margin-top: 20px; }
  .grid .col { flex: 1; }
  .panel { border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; }
  .panel h3 { margin: 0 0 6px; font-size: 11px; text-transform: uppercase; letter-spacing: .08em; color: #64748b; }
  .panel p { margin: 2px 0; font-size: 12px; }

  table.items { width: 100%; border-collapse: collapse; margin-top: 22px; }
  table.items th { background: #f1f5f9; text-align: left; padding: 9px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #475569; }
  table.items td { padding: 9px 10px; border-bottom: 1px solid #e2e8f0; font-size: 12px; vertical-align: top; }
  table.items td.num { text-align: right; white-space: nowrap; }
  table.items tr:nth-child(even) td { background: #f8fafc; }

  .totals { margin-top: 16px; width: 260px; margin-left: auto; }
  .totals .row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 12px; }
  .totals .row.grand { border-top: 2px solid <?= e($brandPrimary) ?>; margin-top: 6px; padding-top: 10px; font-size: 16px; font-weight: bold; color: <?= e($brandPrimary) ?>; }

  .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: bold; background: #e0f2fe; color: #075985; }
  .footer { margin-top: 30px; border-top: 1px solid #e2e8f0; padding-top: 12px; text-align: center; color: #64748b; font-size: 11px; }
</style>
</head><body>
<div class="wrap">

  <div class="head">
    <div style="display:flex; gap:14px; align-items:center;">
      <?= $logoTag ?>
      <div>
        <p class="brand-name"><?= e($siteName) ?><span>.</span></p>
        <?php if ($tagline !== ''): ?><p class="muted" style="margin:2px 0 0;"><?= e($tagline) ?></p><?php endif; ?>
        <p class="muted" style="margin:2px 0 0;">WhatsApp: <?= e($whatsapp) ?></p>
      </div>
    </div>
    <div class="doc-title">
      <h1>INVOICE</h1>
      <p>Ref: <strong><?= e($order['order_ref']) ?></strong></p>
      <p>Issued: <?= e(date('d M Y', strtotime($order['created_at']))) ?></p>
      <p><span class="badge"><?= e($statusLabel) ?></span></p>
    </div>
  </div>

  <div class="grid">
    <div class="col">
      <div class="panel">
        <h3>Billed to</h3>
        <p><strong><?= e($order['customer_name']) ?></strong></p>
        <p><?= e($order['customer_phone']) ?></p>
        <?php if (!empty($order['delivery_address'])): ?>
          <p><?= e($order['delivery_address']) ?></p>
        <?php endif; ?>
      </div>
    </div>
    <div class="col">
      <div class="panel">
        <h3>Delivery</h3>
        <p><?= e($order['delivery_address'] ?: 'Pickup at our yard') ?></p>
        <p class="muted" style="margin-top:8px;">Payment: M-Pesa / Cash on delivery</p>
      </div>
    </div>
  </div>

  <table class="items">
    <thead>
      <tr>
        <th style="width:46%;">Item</th>
        <th style="width:14%;">SKU</th>
        <th style="width:8%;">Qty</th>
        <th style="width:16%;" class="num">Unit price</th>
        <th style="width:16%;" class="num">Line total</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td><?= e($it['product_name']) ?></td>
          <td class="muted"><?= e($it['sku'] ?: '—') ?></td>
          <td><?= (int)$it['quantity'] ?></td>
          <td class="num">KES <?= number_format((float)$it['unit_price'], 2) ?></td>
          <td class="num"><strong>KES <?= number_format((float)$it['line_total'], 2) ?></strong></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$items): ?>
        <tr><td colspan="5" class="muted" style="text-align:center; padding:20px;">No items.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="totals">
    <div class="row"><span class="muted">Subtotal</span><span>KES <?= number_format((float)$order['subtotal'], 2) ?></span></div>
    <div class="row grand"><span>Total</span><span>KES <?= number_format((float)$order['total'], 2) ?></span></div>
  </div>

  <div class="footer">
    <p><strong><?= e($siteName) ?> Plumbing &amp; Sanitation</strong></p>
    <?php if ($tagline !== ''): ?><p><?= e($tagline) ?></p><?php endif; ?>
    <p>WhatsApp <?= e($whatsapp) ?> · Thank you for your business.</p>
    <p>Generated <?= e(date('d M Y, H:i')) ?></p>
  </div>

</div>
</body></html>
<?php
$html = ob_get_clean();

$filename = 'Invoice-' . $order['order_ref'] . '.pdf';
$mode = (($_GET['mode'] ?? 'download') === 'inline') ? 'inline' : 'download';
pdf_stream($html, $filename, $mode);