<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$lib = __DIR__ . '/static/phpqrcode/qrlib.php';
if (!file_exists($lib)) {
    die("ERROR: QR library not found at $lib");
}

require $lib;

$url = isset($_GET['url']) ? $_GET['url'] : 'empty';
function generateQrPng(string $url): string {
    ob_start();
    include __DIR__ . '/static/phpqrcode/qrlib.php';
    QRcode::png($url, false, QR_ECLEVEL_M, 10, 2);
    return ob_get_clean();
}

header("Content-Type: image/png");
QRcode::png($url, false, QR_ECLEVEL_M, 10, 2);
