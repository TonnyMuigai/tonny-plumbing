<?php
declare(strict_types=1);

/**
 * Read a CSV file into an array of associative rows.
 * Handles: BOM, CRLF, quoted fields, escaped quotes, blank trailing lines.
 */
function csv_read(string $path, int $maxRows = 5000): array
{
    if (!is_readable($path)) {
        throw new RuntimeException('CSV file could not be read.');
    }
    $fh = fopen($path, 'r');
    if (!$fh) {
        throw new RuntimeException('CSV file could not be opened.');
    }

    // Strip UTF-8 BOM if present
    $first = fread($fh, 3);
    if ($first !== "\xEF\xBB\xBF") {
        rewind($fh);
    }

    $headers = [];
    $rows = [];
    $lineNo = 0;

    while (($line = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        $lineNo++;

        if (count($line) === 1 && ($line[0] === null || trim((string)$line[0]) === '')) {
            continue;
        }

        if (!$headers) {
            foreach ($line as $h) {
                $headers[] = strtolower(trim((string)$h));
            }
            continue;
        }

        if (count($rows) >= $maxRows) {
            fclose($fh);
            throw new RuntimeException('CSV contains more than ' . $maxRows . ' rows. Split it and import in batches.');
        }

        $row = [];
        foreach ($headers as $i => $key) {
            $row[$key] = isset($line[$i]) ? (string)$line[$i] : '';
        }
        $rows[] = $row;
    }
    fclose($fh);

    if (!$headers) {
        throw new RuntimeException('CSV appears to be empty.');
    }
    return ['headers' => $headers, 'rows' => $rows];
}

function csv_bool(string $value, int $default = 1): int
{
    $v = strtolower(trim($value));
    if ($v === '') return $default;
    if (in_array($v, ['1', 'true', 'yes', 'y', 'on', 'active'], true)) return 1;
    if (in_array($v, ['0', 'false', 'no', 'n', 'off', 'inactive'], true)) return 0;
    return $default;
}

function csv_validate_product_row(array $row, array $existingSkus, array $existingSlugs, array &$seenSkus, int $lineNo): array
{
    $sku         = trim($row['sku'] ?? '');
    $name        = trim($row['name'] ?? '');
    $slug        = strtolower(trim($row['slug'] ?? ''));
    $category    = trim($row['category'] ?? '');
    $price       = trim($row['price'] ?? '');
    $stockQty    = trim($row['stock_qty'] ?? '0');
    $description = trim($row['description'] ?? '');
    $isActiveRaw = trim($row['is_active'] ?? '');

    if ($sku === '')                              return ['ok' => false, 'error' => 'SKU is required.'];
    if (strlen($sku) > 60)                        return ['ok' => false, 'error' => 'SKU is longer than 60 characters.'];
    if ($name === '')                             return ['ok' => false, 'error' => 'Name is required.'];
    if (mb_strlen($name) > 180)                   return ['ok' => false, 'error' => 'Name is longer than 180 characters.'];
    if ($slug === '')                             return ['ok' => false, 'error' => 'Slug is required.'];
    if (strlen($slug) > 200)                      return ['ok' => false, 'error' => 'Slug is longer than 200 characters.'];
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        return ['ok' => false, 'error' => 'Slug may only contain lowercase letters, numbers and hyphens.'];
    }
    if ($price === '' || !is_numeric($price) || (float)$price < 0) {
        return ['ok' => false, 'error' => 'Price must be a non-negative number (e.g. 1250 or 1250.50).'];
    }
    if ($stockQty !== '' && !ctype_digit($stockQty)) {
        return ['ok' => false, 'error' => 'Stock must be a whole number (0 or more).'];
    }
    if (mb_strlen($category) > 100) {
        return ['ok' => false, 'error' => 'Category is longer than 100 characters.'];
    }
    if (mb_strlen($description) > 2000) {
        return ['ok' => false, 'error' => 'Description is longer than 2000 characters.'];
    }

    $skuKey = strtolower($sku);
    if (isset($seenSkus[$skuKey])) {
        return ['ok' => false, 'error' => 'Duplicate SKU in CSV (also on line ' . $seenSkus[$skuKey] . ').'];
    }
    $seenSkus[$skuKey] = $lineNo;

    $existingId = $existingSkus[$skuKey] ?? null;
    $action = $existingId !== null ? 'update' : 'create';

    $slugOwner = $existingSlugs[$slug] ?? null;
    if ($slugOwner !== null && $slugOwner !== $existingId) {
        return ['ok' => false, 'error' => 'Slug "' . $slug . '" is already used by another product.'];
    }

    return [
        'ok'     => true,
        'action' => $action,
        'data'   => [
            'sku'         => $sku,
            'name'        => $name,
            'slug'        => $slug,
            'category'    => $category !== '' ? $category : null,
            'description' => $description !== '' ? $description : null,
            'price'       => number_format((float)$price, 2, '.', ''),
            'stock_qty'   => $stockQty === '' ? 0 : (int)$stockQty,
            'is_active'   => csv_bool($isActiveRaw, 1),
            'existing_id' => $existingId,
        ],
    ];
}

function csv_download(string $filename, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    foreach ($rows as $row) {
        fputcsv($out, $row, ',', '"', '\\');
    }
    fclose($out);
    exit;
}