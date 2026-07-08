<?php

$pdfDir = __DIR__ . '/data/pdfs';
$mounted = is_dir($pdfDir) && is_readable($pdfDir);

http_response_code($mounted ? 200 : 503);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => $mounted ? 'ok' : 'error',
    'pdf_volume_mounted' => $mounted,
], JSON_UNESCAPED_UNICODE);
