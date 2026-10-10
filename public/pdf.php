<?php
declare(strict_types=1);

/**
 * Thin wrapper around Dompdf.
 *
 * Usage:
 *   pdf_stream($htmlString, 'invoice-ORD-001.pdf', 'download');   // forces download
 *   pdf_stream($htmlString, 'invoice-ORD-001.pdf', 'inline');     // opens in browser
 */
function pdf_stream(string $html, string $filename, string $mode = 'download'): never
{
    $autoload = dirname(__DIR__) . '/vendor/dompdf/autoload.inc.php';
    if (!is_file($autoload)) {
        http_response_code(500);
        exit('PDF engine not installed. Expected at: ' . $autoload);
    }
    require_once $autoload;

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // Clean any stray output that may have been echoed before us
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $dompdf->stream($filename, ['Attachment' => $mode === 'download']);
    exit;
}

/**
 * Return PDF as a string (useful for saving to disk or attaching to email).
 */
function pdf_output(string $html): string
{
    $autoload = dirname(__DIR__) . '/vendor/dompdf/autoload.inc.php';
    if (!is_file($autoload)) {
        throw new RuntimeException('PDF engine not installed.');
    }
    require_once $autoload;

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('isHtml5ParserEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    return $dompdf->output();
}