<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

function norm_scalar(mixed $v): string {
    if ($v === null) return '';
    if (is_bool($v)) return $v ? '1' : '0';
    if (is_scalar($v)) return (string)$v;
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function buildReplacementMap(array $data): array {
    $map = [];
    foreach ($data as $key => $val) {
        $k = (string)$key;
        if ($k === '') continue;
        $map[$k] = norm_scalar($val);
    }
    return $map;
}

function replaceHeaders(string $template, string $output, array $map): void {
    $zip = new ZipArchive();
    if ($zip->open($template) !== TRUE) {
        throw new RuntimeException("Cannot open template $template");
    }

    // Copy template to destination
    if (!copy($template, $output)) {
        throw new RuntimeException("Failed to copy template to $output");
    }

    $outZip = new ZipArchive();
    if ($outZip->open($output) !== TRUE) {
        throw new RuntimeException("Cannot open $output for writing");
    }

    // Process header1.xml, header2.xml, header3.xml
    for ($i = 1; $i <= 3; $i++) {
        $hName = "word/header{$i}.xml";
        $hXml = $zip->getFromName($hName);
        if ($hXml === false) continue;

        $hDom = new DOMDocument();
        $hDom->preserveWhiteSpace = false;
        $hDom->formatOutput = false;
        if (!$hDom->loadXML($hXml)) continue;

        $hXp = new DOMXPath($hDom);
        $hXp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        foreach ($hXp->query('//w:t') as $t) {
            $text = $t->textContent;
            foreach ($map as $k => $v) {
                $text = str_replace('${' . $k . '}', $v, $text);
                $text = str_replace($k, $v, $text);
            }
            $t->nodeValue = $text;
        }

        $outZip->addFromString($hName, $hDom->saveXML());
    }

    $zip->close();
    $outZip->close();
}

// -------------------
// Main batch process
// -------------------

$sourceDir = __DIR__ . '/sourcedocx';
$targetDir = dirname(__DIR__) . '/docGenerator';

// Make sure target folder exists
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

// Read JSON payload
$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = []; // fallback
}

$map = buildReplacementMap($data);


// Loop over all .docx in sourcedocx
$files = glob($sourceDir . '/*.docx');

// skip lock/temp files
$files = array_filter($files, function($f) {
    return strpos(basename($f), '~$') !== 0;
});

foreach ($files as $file) {
    $fname = basename($file);
    $outPath = $targetDir . '/' . $fname;
    try {
        replaceHeaders($file, $outPath, $map);
        echo "✔ Processed $fname → saved to $outPath\n";
    } catch (Throwable $e) {
        echo "❌ Failed $fname: " . $e->getMessage() . "\n";
    }
}
