<?php
require_once dirname(__DIR__) . '/app/bootstrap.php';
?><!doctype html>
<html><head>
<meta charset="utf-8">
<title>Bare Test</title>
<?= render_brand_head() ?>
</head><body>
<h1 class="text-red-600 text-4xl font-black">Tailwind should make this red</h1>
<div class="bg-green-500 h-20 w-40 mt-4"></div>
</body></html>