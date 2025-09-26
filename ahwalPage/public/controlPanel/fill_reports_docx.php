<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
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
        if ($key === '' || $key === null) continue;
        $map[(string)$key] = norm_scalar($val);
    }
    return $map;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST ?: [];

try {
    if (!class_exists('ZipArchive')) throw new RuntimeException('Missing ZipArchive');
    if (!class_exists('DOMDocument')) throw new RuntimeException('Missing DOMDocument');

    $docxfile  = basename((string)($data['docxfile'] ?? ''));
    $tablename = (string)($data['tablename'] ?? '');
    if (!$docxfile) throw new RuntimeException('docxfile missing');

    $tplPath = __DIR__ . DIRECTORY_SEPARATOR . $docxfile;
    $realTpl = realpath($tplPath);
    if (!$realTpl || !is_file($realTpl)) throw new RuntimeException("Template not found: $docxfile");

    $map = buildReplacementMap($data);

    // --- Load DOCX ---
    $zip = new ZipArchive();
    if ($zip->open($realTpl) !== TRUE) throw new RuntimeException('Cannot open DOCX');
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    if ($xml === false) throw new RuntimeException('Missing document.xml');

    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;
    $dom->loadXML($xml);
    $xp = new DOMXPath($dom);
    $nsw = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $xp->registerNamespace('w', $nsw);

    // --- Replace placeholders ---
    foreach ($xp->query('//w:t') as $t) {
        $txt = $t->textContent;
        foreach ($map as $k => $v) {
            if (strpos($txt, $k) !== false) {
                $t->nodeValue = str_replace($k, $v, $txt);
            }
        }
    }

    // --- Helper: apply Arabic font & size ---
    $applyArabicStyle = function(DOMElement $rPr, DOMDocument $dom, string $nsw) {
        $rFonts = $dom->createElementNS($nsw, 'w:rFonts');
        $rFonts->setAttribute('w:ascii', 'Arabic Typesetting');
        $rFonts->setAttribute('w:hAnsi', 'Arabic Typesetting');
        $rFonts->setAttribute('w:cs', 'Arabic Typesetting');
        $rFonts->setAttribute('w:eastAsia', 'Arabic Typesetting');
        $rPr->appendChild($rFonts);

        $sz = $dom->createElementNS($nsw, 'w:sz');
        $sz->setAttribute('w:val', '40'); // 20pt
        $rPr->appendChild($sz);

        $szCs = $dom->createElementNS($nsw, 'w:szCs');
        $szCs->setAttribute('w:val', '40');
        $rPr->appendChild($szCs);

        $rtl = $dom->createElementNS($nsw, 'w:rtl');
        $rPr->appendChild($rtl);

        $lang = $dom->createElementNS($nsw, 'w:lang');
        $lang->setAttribute('w:bidi', 'ar-SA');
        $lang->setAttribute('w:val', 'ar-SA');
        $rPr->appendChild($lang);
    };

    // --- Helper to set cell text ---
    $setCell = function($cell, $val) use ($dom, $nsw, $xp, $applyArabicStyle) {
        foreach ($xp->query('.//w:t', $cell) as $t) {
            $t->parentNode->removeChild($t);
        }
        $p = $xp->query('.//w:p', $cell)->item(0);
        if (!$p) {
            $p = $dom->createElementNS($nsw, 'w:p');
            $cell->appendChild($p);
        }
        $r = $dom->createElementNS($nsw, 'w:r');
        $rPr = $dom->createElementNS($nsw, 'w:rPr');
        $applyArabicStyle($rPr, $dom, $nsw);
        $r->appendChild($rPr);
        $t = $dom->createElementNS($nsw, 'w:t', $val);
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $r->appendChild($t);
        $p->appendChild($r);
    };

    // --- Fill TableAuth ---
    if ($tablename === "TableAuth" && !empty($data['rows'])) {
        $rowsInput = $data['rows'];
        foreach ($xp->query('//w:tbl') as $tbl) {
            $trs = $xp->query('./w:tr', $tbl);
            if ($trs->length < 2) continue;
            $sample = $trs->item(1);

            // First row
            $first = $rowsInput[0];
            $cells = $xp->query('./w:tc', $sample);
            if ($cells->length >= 4) {
                $setCell($cells->item(3), "1");
                $setCell($cells->item(2), (string)($first['مقدم_الطلب'] ?? ''));
                $setCell($cells->item(1), (string)($first['الموكَّل'] ?? ''));
                $setCell($cells->item(0), (string)($first['رقم_التوكيل'] ?? ''));
            }

            // Other rows
            for ($i = 1; $i < count($rowsInput); $i++) {
                $rowData = $rowsInput[$i];
                $clone = $sample->cloneNode(true);
                $cells = $xp->query('./w:tc', $clone);
                if ($cells->length >= 4) {
                    $setCell($cells->item(3), (string)($i+1));
                    $setCell($cells->item(2), (string)($rowData['مقدم_الطلب'] ?? ''));
                    $setCell($cells->item(1), (string)($rowData['الموكَّل'] ?? ''));
                    $setCell($cells->item(0), (string)($rowData['رقم_التوكيل'] ?? ''));
                }
                $tbl->appendChild($clone);
            }
            break;
        }
    }

    // --- Fill TableCollection ---
    if ($tablename === "TableCollection" && !empty($data['rows'])) {
        $rowsInput = $data['rows'];
        foreach ($xp->query('//w:tbl') as $tbl) {
            $trs = $xp->query('./w:tr', $tbl);
            if ($trs->length < 2) continue;
            $sample = $trs->item(1);

            // First row
            $first = $rowsInput[0];
            $cells = $xp->query('./w:tc', $sample);
            if ($cells->length >= 4) {
                $setCell($cells->item(3), "1"); // الرقم
                $setCell($cells->item(2), (string)($first['مقدم_الطلب'] ?? '')); // اسم مقدم الطلب
                $setCell($cells->item(1), (string)($first['نوع_المعاملة'] ?? '')); // المعاملة
                $setCell($cells->item(0), (string)($first['رقم_المعاملة'] ?? '')); // الرقم المرجعي
            }

            // Other rows
            for ($i = 1; $i < count($rowsInput); $i++) {
                $rowData = $rowsInput[$i];
                $clone = $sample->cloneNode(true);
                $cells = $xp->query('./w:tc', $clone);
                if ($cells->length >= 4) {
                    $setCell($cells->item(3), (string)($i+1));
                    $setCell($cells->item(2), (string)($rowData['مقدم_الطلب'] ?? ''));
                    $setCell($cells->item(1), (string)($rowData['نوع_المعاملة'] ?? ''));
                    $setCell($cells->item(0), (string)($rowData['رقم_المعاملة'] ?? ''));
                }
                $tbl->appendChild($clone);
            }
            break;
        }
    }

        // --- Fill TableSummary (Monthly / Quarterly / Biannually / Yearly) ---
    // --- Fill TableSummary (Monthly / Quarterly / Biannually / Yearly) ---
if ($tablename === "TableSummary" && !empty($data['rows'])) {
    // Columns in the DOCX, fixed order
    $allColumns = [
        "Period",                   // الشهر
        "إفادة لمن يهمه الأمر",
        "إقرار",
        "إقرار مشفوع باليمين",
        "شهادة لمن يهمه الأمر",
        "مذكرة لسفارة عربية",
        "مذكرة لسفارة أجنبية",
        "توكيل",
        "التوثيق",
        "وثيقة زواج",
        "وثيقة طلاق",
        "Total"                     // مجموع المعاملات
    ];

    $rowsInput = $data['rows'];

    foreach ($xp->query('//w:tbl') as $tbl) {
        $trs = $xp->query('./w:tr', $tbl);
        if ($trs->length < 2) continue;
        $sample = $trs->item(1);

        // Helper to fill a row
        $fillRow = function($rowData, $clone) use ($xp, $setCell, $allColumns) {
            $cells = $xp->query('./w:tc', $clone);
            foreach ($allColumns as $i => $colName) {
                if ($i < $cells->length) {
                    $val = isset($rowData[$colName]) ? (string)$rowData[$colName] : "0";
                    $setCell($cells->item($i), $val);
                }
            }
            return $clone;
        };

        // First row (replace sample)
        $fillRow($rowsInput[0], $sample);

        // Remaining rows
        for ($i = 1; $i < count($rowsInput); $i++) {
            $rowData = $rowsInput[$i];
            $clone = $sample->cloneNode(true);
            $tbl->appendChild($fillRow($rowData, $clone));
        }

        break; // only one summary table
    }
}


    // --- Save DOCX ---
    $newXml = $dom->saveXML();
    $tmpOut = tempnam(sys_get_temp_dir(), 'docx_');
    copy($realTpl, $tmpOut);
    $outZip = new ZipArchive();
    $outZip->open($tmpOut);
    $outZip->addFromString('word/document.xml', $newXml);
    $outZip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="'.pathinfo($docxfile, PATHINFO_FILENAME).'_filled.docx"');
    header('Content-Length: '.filesize($tmpOut));
    readfile($tmpOut);
    unlink($tmpOut);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}
