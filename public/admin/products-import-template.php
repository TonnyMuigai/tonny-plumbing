<?php
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_admin();

csv_download('products-template.csv', [
    ['sku', 'name', 'slug', 'category', 'price', 'stock_qty', 'description', 'is_active'],
    ['PIPE-001', '1/2 inch PVC Pipe', 'pvc-pipe-half-inch', 'Pipes', '450.00', '100', 'High-pressure PVC pipe for water lines', '1'],
    ['TAP-010', 'Chrome Kitchen Tap', 'chrome-kitchen-tap', 'Taps', '1850.00', '15', 'Stainless steel kitchen mixer tap', '1'],
    ['ELB-050', 'PVC Elbow 90° Half-inch', 'pvc-elbow-90-half-inch', 'Fittings', '45.00', '250', 'Right-angle PVC elbow for half-inch pipe', '1'],
    ['VAL-020', 'Gate Valve Brass 3/4 inch', 'gate-valve-brass-3-4', 'Valves', '980.00', '40', 'Brass gate valve, quarter-turn handle', '1'],
    ['HOS-100', 'Flexible Shower Hose 1.5m', 'flex-shower-hose-1-5m', 'Accessories', '620.00', '80', 'Stainless-steel braided hose', '1'],
]);