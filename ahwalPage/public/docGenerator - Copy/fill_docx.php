<?php
declare(strict_types=1);

// --- DEBUGGING HELP (safe to leave on in dev) ---
ini_set('display_errors', '0');           // keep off in prod
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Make sure everything is a printable scalar (no arrays/objects)
function norm_scalar(mixed $v): string {
    if ($v === null) return '';
    if (is_bool($v)) return $v ? '1' : '0';
    if (is_scalar($v)) return (string)$v;
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function buildReplacementMap(array $data, bool $apostropheVariant = true): array {
    $map = [];
    foreach ($data as $key => $val) {
        if ($key === '' || $key === null) continue;
        $k = (string)$key;
        $v = norm_scalar($val);
        $map[$k] = $v;
        if ($apostropheVariant) {
            $map[$k . "'"] = $v; // handle accidental trailing apostrophe in template
        }
    }
    return $map;
}

// Tiny logger
function logx(string $msg): void { error_log('[docGenerator] ' . $msg); }

// Health check
if (isset($_GET['ping'])) {
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => true, 'ts' => date('c')]);
    exit;
}

// Read JSON payload (fallback to form)
$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) { $data = $_POST ?: []; }

try {
    // --- Guard: required extensions ---
    if (!class_exists('ZipArchive'))  throw new RuntimeException('ZipArchive extension is missing. Install/enable php-zip.');
    if (!class_exists('DOMDocument')) throw new RuntimeException('DOM extension is missing. Install/enable php-xml.');

    $hasMb = function_exists('mb_strlen') && function_exists('mb_strpos') && function_exists('mb_substr');

    // --- Template path ---
    // --- Pick template from client (payload["docxfile"]), safely ---
$requested = (string)($data['docxfile'] ?? 'SingleAuth.docx');
$langDoc = (string)($data['lang'] ?? 'ar');

// Only allow a simple filename (no paths); force .docx
$fname = basename($requested);
$ext   = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
if ($ext !== 'docx') {
    throw new RuntimeException('Invalid template extension. Only .docx is allowed.');
}

$tplPath = __DIR__ . DIRECTORY_SEPARATOR . $fname;

// Resolve and ensure it stays inside this folder (no path traversal)
$realTpl = realpath($tplPath);
$realDir = realpath(__DIR__);
if ($realTpl === false || $realDir === false || strncmp($realTpl, $realDir, strlen($realDir)) !== 0) {
    throw new RuntimeException('Template path is invalid or outside allowed directory.');
}

if (!is_file($realTpl)) {
    throw new RuntimeException('Template not found: ' . $fname);
}

$template = $realTpl;

// (Optional) set a nicer output name: <TemplateName>_filled.docx
$outBaseName = preg_replace('/\.docx$/i', '', $fname) . '_filled.docx';

    if (!is_file($template)) throw new RuntimeException('Template not found at: ' . $template);

    // --- Build replacement map from incoming keys ---
    $map = buildReplacementMap($data, true);

    // --- Load needed parts from template ---
    $zip = new ZipArchive();
    if ($zip->open($template) !== TRUE) throw new RuntimeException('Cannot open DOCX template (ZipArchive open failed).');
    $xml    = $zip->getFromName('word/document.xml');
    $rels   = $zip->getFromName('word/_rels/document.xml.rels');
    $ctypes = $zip->getFromName('[Content_Types].xml');
    $footerXml = $zip->getFromName('word/footer1.xml'); // may be false if no footer
    $footerDom = null;
    $footerXp  = null;

    $headerXml = $zip->getFromName('word/header1.xml'); // may be false if no header
    $headerDom = null;
    if ($headerXml !== false) {
        $headerDom = new DOMDocument();
        $headerDom->preserveWhiteSpace = false;
        $headerDom->formatOutput = false;
        $headerDom->loadXML($headerXml);

        $headerXp = new DOMXPath($headerDom);
        $headerXp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        foreach ($headerXp->query('//w:t') as $t) {
            $text = $t->textContent;
            foreach ($map as $k => $v) {
                $text = str_replace('${' . $k . '}', $v, $text);
            }
            $t->nodeValue = $text;
        }
    }


    $zip->close();
    if ($xml === false)    throw new RuntimeException('word/document.xml not found in template.');
    if ($ctypes === false) throw new RuntimeException('[Content_Types].xml not found in template.');
    if ($rels === false) {
        $rels = '<?xml version="1.0" encoding="UTF-8"?>'
              . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>';
    }

    // --- Parse document.xml ---
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = false;
    if (!$dom->loadXML($xml, LIBXML_NOENT | LIBXML_NOCDATA | LIBXML_NOWARNING | LIBXML_NOERROR)) {
        throw new RuntimeException('Failed to parse document.xml');
    }
    $xp = new DOMXPath($dom);
    $nsw = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $xp->registerNamespace('w', $nsw);
    if ($footerXml !== false) {
        $footerDom = new DOMDocument();
        $footerDom->preserveWhiteSpace = false;
        $footerDom->formatOutput = false;
        if ($footerDom->loadXML($footerXml)) {
            $footerXp = new DOMXPath($footerDom);
            $footerXp->registerNamespace('w', $nsw);
        }
    }
    // --- Helper: apply Traditional Arabic 15pt bold + RTL to a run ---
$applyArabicRunStyle = function (DOMElement $r) use ($dom, $nsw) {
    $rPr = $r->getElementsByTagNameNS($nsw, 'rPr')->item(0);
    if (!$rPr) {
        $rPr = $dom->createElementNS($nsw, 'w:rPr');
        $r->insertBefore($rPr, $r->firstChild);
    }
    $rFonts = $rPr->getElementsByTagNameNS($nsw, 'rFonts')->item(0);
    if (!$rFonts) {
        $rFonts = $dom->createElementNS($nsw, 'w:rFonts');
        $rPr->appendChild($rFonts);
    }
    $rFonts->setAttribute('w:ascii', 'Traditional Arabic');
    $rFonts->setAttribute('w:hAnsi', 'Traditional Arabic');
    $rFonts->setAttribute('w:cs', 'Traditional Arabic');
    $rFonts->setAttribute('w:eastAsia', 'Traditional Arabic');

    if (!$rPr->getElementsByTagNameNS($nsw, 'b')->item(0)) {
        $rPr->appendChild($dom->createElementNS($nsw, 'w:b'));
    }
    if (!$rPr->getElementsByTagNameNS($nsw, 'bCs')->item(0)) {
        $rPr->appendChild($dom->createElementNS($nsw, 'w:bCs'));
    }

    $setSz = function (string $name) use ($dom, $nsw, $rPr) {
        $el = $rPr->getElementsByTagNameNS($nsw, $name)->item(0);
        if (!$el) {
            $el = $dom->createElementNS($nsw, 'w:' . $name);
            $rPr->appendChild($el);
        }
        $el->setAttribute('w:val', '30'); // 15pt
    };
    $setSz('sz');
    $setSz('szCs');

    if (!$rPr->getElementsByTagNameNS($nsw, 'rtl')->item(0)) {
        $rPr->appendChild($dom->createElementNS($nsw, 'w:rtl'));
    }
    $lang = $rPr->getElementsByTagNameNS($nsw, 'lang')->item(0);
    if (!$lang) {
        $lang = $dom->createElementNS($nsw, 'w:lang');
        $rPr->appendChild($lang);
    }
    if (!$lang->hasAttribute('w:bidi')) $lang->setAttribute('w:bidi', 'ar-SA');
    if (!$lang->hasAttribute('w:val'))  $lang->setAttribute('w:val', 'ar-SA');
};

// --- In-place replace text in a table cell ---
$setCellText = function (DOMElement $tc, string $value) use ($xp, $dom, $nsw, $applyArabicRunStyle) {
    $p = $xp->query('.//w:p', $tc)->item(0);
    if (!$p) { $p = $dom->createElementNS($nsw, 'w:p'); $tc->appendChild($p); }
    $r = $xp->query('.//w:r', $p)->item(0);
    if (!$r) { $r = $dom->createElementNS($nsw, 'w:r'); $p->appendChild($r); }

    foreach ($xp->query('.//w:t|.//w:br', $p) as $node) {
        $node->parentNode?->removeChild($node);
    }

    $lines = explode("\n", $value);
    foreach ($lines as $i => $line) {
        $t = $dom->createElementNS($nsw, 'w:t');
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $t->appendChild($dom->createTextNode($line));
        $r->appendChild($t);
        if ($i < count($lines)-1) {
            $r->appendChild($dom->createElementNS($nsw, 'w:br'));
        }
    }
    $applyArabicRunStyle($r);

    $runs = $xp->query('./w:r', $p);
    for ($i = 1; $i < $runs->length; $i++) {
        $rr = $runs->item($i);
        $rr->parentNode?->removeChild($rr);
    }
};

    // ---------- Helpers ----------
    $copyRunProps = function (?DOMElement $srcRPr) use ($dom): ?DOMElement {
        if (!$srcRPr instanceof DOMElement) return null;
        $clone = $srcRPr->cloneNode(true);
        return $dom->importNode($clone, true);
    };
    $createRunWithText = function (?DOMElement $rPr, string $text) use ($dom, $nsw): DOMElement {
        $r = $dom->createElementNS($nsw, 'w:r');
        if ($rPr) $r->appendChild($rPr);
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            $t = $dom->createElementNS($nsw, 'w:t');
            $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $t->appendChild($dom->createTextNode($line));
            $r->appendChild($t);
            if ($i < count($lines) - 1) $r->appendChild($dom->createElementNS($nsw, 'w:br'));
        }
        return $r;
    };

    // Replace placeholders paragraph-by-paragraph (preserve formatting where possible)
    $replacePlaceholderInParagraph = function (DOMElement $p, string $ph, string $val) use (
        $xp, $copyRunProps, $createRunWithText, $nsw, $hasMb
    ): bool {
        if ($ph === '') return false;
        $runs = [];
        foreach ($xp->query('.//w:r', $p) as $r) {
            $t = $xp->query('.//w:t', $r)->item(0);
            $txt = $t ? $t->textContent : '';
            $runs[] = ['r' => $r, 't' => $t, 'text' => $txt];
        }
        if (!$runs) return false;

        if (!$hasMb) {
            $original = '';
            foreach ($runs as $it) $original .= $it['text'];
            if (strpos($original, $ph) === false) return false;
            $new = str_replace($ph, $val, $original);
            while ($p->firstChild) $p->removeChild($p->firstChild);
            $p->appendChild($createRunWithText(null, $new));
            return true;
        }

        $full = ''; $idxMap = [];
        foreach ($runs as $ri => $it) {
            $chars = preg_split('//u', $it['text'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($chars as $oi => $ch) { $idxMap[mb_strlen($full,'UTF-8')] = [$ri,$oi]; $full .= $ch; }
        }

        $changed=false; $start=0;
        while (true) {
            $pos = mb_strpos($full, $ph, $start, 'UTF-8');
            if ($pos === false) break;
            $end = $pos + mb_strlen($ph, 'UTF-8');
            $startMap = $idxMap[$pos] ?? null;
            $endMap   = $idxMap[$end-1] ?? null;
            if (!$startMap || !$endMap) { $start = $end; continue; }
            [$startRun, $startOff] = $startMap; [$endRun, $endOff] = $endMap;

            $startText = $runs[$startRun]['text']; $endText = $runs[$endRun]['text'];
            $prefix = ($startOff>0)? mb_substr($startText,0,$startOff,'UTF-8') : '';
            $suffix = mb_substr($endText, $endOff+1, null, 'UTF-8');

            $refNode = $runs[$endRun]['r']->nextSibling; $parent = $p;
            for ($ri=$startRun; $ri<=$endRun; $ri++) { $rNode = $runs[$ri]['r']; if ($rNode->parentNode) $rNode->parentNode->removeChild($rNode); }
            $firstRPr = $xp->query('.//w:rPr', $runs[$startRun]['r'])->item(0);
            if ($prefix !== '') $parent->insertBefore($createRunWithText($copyRunProps($firstRPr), $prefix), $refNode);
            $parent->insertBefore($createRunWithText($copyRunProps($firstRPr), $val), $refNode);
            if ($suffix !== '') {
                $endRPr = $xp->query('.//w:rPr', $runs[$endRun]['r'])->item(0);
                $parent->insertBefore($createRunWithText($copyRunProps($endRPr), $suffix), $refNode);
            }

            $full   = mb_substr($full,0,$pos,'UTF-8') . $val . mb_substr($full,$end,null,'UTF-8');
            $start  = $pos + mb_strlen($val,'UTF-8');
            $changed= true;
        }
        return $changed;
    };

    $changedCount = 0;
    foreach ($xp->query('//w:p') as $p) {
        $paraChanged = false;
        foreach ($map as $k => $v) {
            if ($k === '') continue;
            if ($replacePlaceholderInParagraph($p, $k, $v)) $paraChanged = true;
        }
        if ($paraChanged) $changedCount++;
    }
    if ($changedCount === 0) logx('No placeholders matched. Check your template text exactly.');

    // ---------- QR/Barcode (client-provided PNG via "barcode_png") ----------
    $pngBytes = null;
    if (!empty($data['barcode_png'])) {
        $d = (string)$data['barcode_png'];
        // Treat placeholders like "no data" or "none" as empty
        if (!preg_match('/^(no\s*data|none)$/i', $d)) {
            if (preg_match('#^data:image/\w+;base64,#i', $d)) {
                $d = preg_replace('#^data:image/\w+;base64,#i', '', $d);
            }
            $decoded = base64_decode($d, true);
            if ($decoded !== false && strlen($decoded) > 0) {
                $pngBytes = $decoded;
            }
        }
    }


    /* ---------- Insert QR/Barcode: exactly ONE blank line before, align RIGHT ---------- */
$relId = null;

// ===== تعبئة جدول المتقدمين (قديم + جديد عربي + جديد إنجليزي) =====
$rowsInput = [];
if (!empty($data['جدول_المتقدمين']) && is_array($data['جدول_المتقدمين'])) {
    foreach ($data['جدول_المتقدمين'] as $row) {
        if (!is_array($row)) continue;
        $rowsInput[] = [
            'الرقم'                => (string)($row['الرقم'] ?? $row['No.'] ?? ''),
            'الاسم'                => (string)($row['الاسم'] ?? $row['Name'] ?? ''),
            'رقم الجواز'           => (string)($row['رقم الجواز'] ?? $row['Passport No.'] ?? ''),
            'مكان الإصدار'         => (string)($row['مكان الإصدار'] ?? $row['Place of Issue'] ?? ''),
            'التوقيع والبصمة'      => (string)($row['التوقيع والبصمة'] ?? ''),   // old column
            'انتهاء صلاحية الجواز' => (string)($row['انتهاء صلاحية الجواز'] ?? $row['Date of Expiry'] ?? '') // new AR/EN column
        ];
    }
}

if (count($rowsInput) > 0) {
    $getCellTexts = function(DOMElement $tc) use ($xp): string {
        $txt = '';
        foreach ($xp->query('.//w:t', $tc) as $t) { $txt .= $t->textContent; }
        return trim($txt);
    };

    foreach ($xp->query('//w:tbl') as $tbl) {
        $trs = $xp->query('./w:tr', $tbl);
        if ($trs->length < 2) continue;

        $hdr = $trs->item(0);
        $hdrCells = $xp->query('./w:tc', $hdr);
        if ($hdrCells->length < 5) continue;

        // Build header map: label → index
        $headerMap = [];
        foreach ($hdrCells as $idx => $tc) {
            $label = $getCellTexts($tc);
            if ($label !== '') $headerMap[$label] = $idx;
        }

        // detect type
        $isOld     = isset($headerMap['التوقيع والبصمة']);
        $isNewAr   = isset($headerMap['انتهاء صلاحية الجواز']);
        $isNewEn   = isset($headerMap['Date of Expiry']);
        if (!$isOld && !$isNewAr && !$isNewEn) continue;

        // Common mapping for both AR & EN headers
        $mapLabels = [
            'No.'                  => 'الرقم',
            'الرقم'                => 'الرقم',

            'Name'                 => 'الاسم',
            'الاسم'                => 'الاسم',

            'Passport No.'         => 'رقم الجواز',
            'رقم الجواز'           => 'رقم الجواز',

            'Place of Issue'       => 'مكان الإصدار',
            'مكان الإصدار'         => 'مكان الإصدار',

            'Date of Expiry'       => 'انتهاء صلاحية الجواز',
            'انتهاء صلاحية الجواز' => 'انتهاء صلاحية الجواز',

            'التوقيع والبصمة'      => 'التوقيع والبصمة',
        ];

        // Sample row (row 2)
        $sample = $trs->item(1);

        // Fill first row
        $first = $rowsInput[0];
        $sampleTcs = $xp->query('.//w:tc', $sample);
        foreach ($headerMap as $hdrLabel => $colIdx) {
            if (!isset($mapLabels[$hdrLabel])) continue;
            $fieldKey = $mapLabels[$hdrLabel];
            $val = $first[$fieldKey] ?? '';
            $setCellText($sampleTcs->item($colIdx), $val);
        }

        // Clone for others
        for ($i = 1; $i < count($rowsInput); $i++) {
            $rowData = $rowsInput[$i];
            $clone = $sample->cloneNode(true);
            $tcs = $xp->query('.//w:tc', $clone);
            foreach ($headerMap as $hdrLabel => $colIdx) {
                if (!isset($mapLabels[$hdrLabel])) continue;
                $fieldKey = $mapLabels[$hdrLabel];
                $val = $rowData[$fieldKey] ?? '';
                $setCellText($tcs->item($colIdx), $val);
            }
            $tbl->appendChild($clone);
        }
    }
}






/* ===== Remove completely empty tables (e.g., empty "additional info") ===== */

/**
 * Normalize text: collapse Unicode spaces, trim, ignore zero-width chars.
 */
$normText = function(string $s): string {
    // replace NBSP and other common non-breaking spaces with regular space
    $s = str_replace(["\xC2\xA0", "\xE2\x80\xAF", "\xE2\x80\x8B", "\xE2\x80\x8C", "\xE2\x80\x8D"], ' ', $s);
    // collapse whitespace
    $s = preg_replace('/\s+/u', ' ', $s);
    // trim
    $s = trim($s);
    return $s;
};

/**
 * Check if a table has ANY non-empty text in its cells.
 * A table is considered "empty" when ALL <w:tc> contain only whitespace or nothing.
 */
$tableIsCompletelyEmpty = function(DOMElement $tbl) use ($xp, $normText): bool {
    // Look at every cell's visible text
    foreach ($xp->query('.//w:tc', $tbl) as $tc) {
        $buf = '';
        foreach ($xp->query('.//w:t', $tc) as $t) {
            $buf .= $t->textContent ?? '';
        }
        if ($normText($buf) !== '') {
            return false; // found real content somewhere → not empty
        }
    }
    return true; // no cell had content
};

/**
 * Remove an element from DOM.
 */
$removeNode = function(DOMElement $el): void {
    if ($el->parentNode) {
        $el->parentNode->removeChild($el);
    }
};

// Iterate all tables and drop the ones that are fully empty
$tables = $xp->query('//w:tbl');
if ($tables && $tables->length) {
    // collect first to avoid skipping due to live NodeList
    $toRemove = [];
    foreach ($tables as $tbl) {
        /** @var DOMElement $tbl */
        if ($tableIsCompletelyEmpty($tbl)) {
            $toRemove[] = $tbl;
        }
    }
    foreach ($toRemove as $tbl) {
        $removeNode($tbl);
    }
}


    // --- Serialize updated document.xml ---
$newXml = $dom->saveXML();
if ($newXml === false) throw new RuntimeException('Failed to serialize updated XML.');

// --- Build output DOCX in temp file ---
$tmpOut = tempnam(sys_get_temp_dir(), 'docx_');
if ($tmpOut === false) throw new RuntimeException('Cannot create temp file.');
if (!copy($template, $tmpOut)) throw new RuntimeException('Failed to copy template to temp.');
$outZip = new ZipArchive();
if ($outZip->open($tmpOut) !== TRUE) throw new RuntimeException('Cannot open temp DOCX for writing.');

// --- Always write document.xml ---
$outZip->addFromString('word/document.xml', $newXml);


// --- Inject footer with QR if available ---
if ($pngBytes || !empty($data['footer_text'])) {
    $footerDom = new DOMDocument('1.0', 'UTF-8');
    $footerDom->formatOutput = false;
    $nsw = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    $ftr = $footerDom->createElementNS($nsw, 'w:ftr');
    $footerDom->appendChild($ftr);

    // === Table ===
    $tbl = $footerDom->createElementNS($nsw, 'w:tbl');

    // Table properties (no borders, full width)
    $tblPr = $footerDom->createElementNS($nsw, 'w:tblPr');
    $tblW = $footerDom->createElementNS($nsw, 'w:tblW');
    $tblW->setAttribute('w:w', '5000'); // 100%
    $tblW->setAttribute('w:type', 'pct');
    $tblPr->appendChild($tblW);

    $tblBorders = $footerDom->createElementNS($nsw, 'w:tblBorders');
    foreach (['top','left','bottom','right','insideH','insideV'] as $side) {
        $el = $footerDom->createElementNS($nsw, 'w:'.$side);
        $el->setAttribute('w:val','none');
        $tblBorders->appendChild($el);
    }
    $tblPr->appendChild($tblBorders);
    $tbl->appendChild($tblPr);

    // Table grid: if both QR + text → 2 columns; else → 1 column
    $tblGrid = $footerDom->createElementNS($nsw, 'w:tblGrid');
    if ($pngBytes && !empty($data['footer_text'])) {
        // Left cell (QR) = 25%, right cell (Text) = 75%
        $gridCol1 = $footerDom->createElementNS($nsw, 'w:gridCol');
        $gridCol1->setAttribute('w:w', '1250');
        $tblGrid->appendChild($gridCol1);

        $gridCol2 = $footerDom->createElementNS($nsw, 'w:gridCol');
        $gridCol2->setAttribute('w:w', '3750');
        $tblGrid->appendChild($gridCol2);
    } else {
        // Single full-width cell
        $gridCol = $footerDom->createElementNS($nsw, 'w:gridCol');
        $gridCol->setAttribute('w:w', '5000');
        $tblGrid->appendChild($gridCol);
    }
    $tbl->appendChild($tblGrid);

    // One row
    $tr = $footerDom->createElementNS($nsw, 'w:tr');

    // === Left cell (QR) ===
    if ($pngBytes) {
        $tc1 = $footerDom->createElementNS($nsw, 'w:tc');
        $tcPr1 = $footerDom->createElementNS($nsw, 'w:tcPr');
        $vAlign1 = $footerDom->createElementNS($nsw, 'w:vAlign');
        $vAlign1->setAttribute('w:val','center');
        $tcPr1->appendChild($vAlign1);
        $tc1->appendChild($tcPr1);

        $p1 = $footerDom->createElementNS($nsw, 'w:p');
        $r1 = $footerDom->createElementNS($nsw, 'w:r');

        $relId = 'rIdQR' . mt_rand(10000, 99999);
        $emu = fn($mm) => (int) round($mm * 36000);
        $cx = $emu(20); $cy = $emu(20);

        $drawXml = <<<XML
            <w:drawing xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
            xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing"
            xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"
            xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
            <wp:inline distT="0" distB="0" distL="0" distR="0">
                <wp:extent cx="$cx" cy="$cy"/>
                <wp:docPr id="1" name="QR"/>
                <a:graphic>
                <a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">
                    <pic:pic>
                    <pic:nvPicPr>
                        <pic:cNvPr id="0" name="qr.png"/>
                        <pic:cNvPicPr/>
                    </pic:nvPicPr>
                    <pic:blipFill>
                        <a:blip r:embed="$relId"/>
                        <a:stretch><a:fillRect/></a:stretch>
                    </pic:blipFill>
                    <pic:spPr>
                        <a:xfrm><a:off x="0" y="0"/><a:ext cx="$cx" cy="$cy"/></a:xfrm>
                        <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
                    </pic:spPr>
                    </pic:pic>
                </a:graphicData>
                </a:graphic>
            </wp:inline>
            </w:drawing>
        XML;

        $frag = $footerDom->createDocumentFragment();
        $frag->appendXML($drawXml);
        $r1->appendChild($frag);
        $p1->appendChild($r1);
        $tc1->appendChild($p1);
        $tr->appendChild($tc1);
    }

    // === Right cell (footer text) OR single cell if no QR ===
    if (!empty($data['footer_text'])) {
        $tc2 = $footerDom->createElementNS($nsw, 'w:tc');
        $tcPr2 = $footerDom->createElementNS($nsw, 'w:tcPr');
        $vAlign2 = $footerDom->createElementNS($nsw, 'w:vAlign');
        $vAlign2->setAttribute('w:val','center');
        $tcPr2->appendChild($vAlign2);
        $tc2->appendChild($tcPr2);

        $p2 = $footerDom->createElementNS($nsw, 'w:p');
        $pPr2 = $footerDom->createElementNS($nsw, 'w:pPr');
        $jc2 = $footerDom->createElementNS($nsw, 'w:jc');
        $jc2->setAttribute('w:val','center');
        $pPr2->appendChild($jc2);
        $p2->appendChild($pPr2);

        $rText = $footerDom->createElementNS($nsw, 'w:r');
        $t = $footerDom->createElementNS($nsw, 'w:t', (string)($data['footer_text']));
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space','preserve');
        $rText->appendChild($t);
        $p2->appendChild($rText);
        $tc2->appendChild($p2);

        $tr->appendChild($tc2);
    }

    $tbl->appendChild($tr);
    $ftr->appendChild($tbl);

    // Save footer
    $outZip->addFromString('word/footer1.xml', $footerDom->saveXML());

    // If QR present, also save image + footer rels
    if ($pngBytes) {
        $outZip->addFromString('word/media/qr.png', $pngBytes);

        $relsDom = new DOMDocument();
        $relsDom->loadXML('<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
        $rel = $relsDom->createElementNS('http://schemas.openxmlformats.org/package/2006/relationships', 'Relationship');
        $rel->setAttribute('Id',$relId);
        $rel->setAttribute('Type','http://schemas.openxmlformats.org/officeDocument/2006/relationships/image');
        $rel->setAttribute('Target','media/qr.png');
        $relsDom->documentElement->appendChild($rel);
        $outZip->addFromString('word/_rels/footer1.xml.rels',$relsDom->saveXML());
    }

    // Attach footer reference to sectPr
    $sectPr = $xp->query('/w:document/w:body/w:sectPr')->item(0);
    if ($sectPr instanceof DOMElement) {
        if (!$xp->query('./w:titlePg',$sectPr)->length) {
            $sectPr->appendChild($dom->createElementNS($nsw,'w:titlePg'));
        }
        $footerRef = $dom->createElementNS($nsw,'w:footerReference');
        $footerRef->setAttribute('w:type','first');
        $footerRef->setAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','r:id','rIdFooter1');
        $sectPr->appendChild($footerRef);

        $relsDom = new DOMDocument();
        $relsDom->loadXML($rels);
        $rel = $relsDom->createElementNS('http://schemas.openxmlformats.org/package/2006/relationships','Relationship');
        $rel->setAttribute('Id','rIdFooter1');
        $rel->setAttribute('Type','http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer');
        $rel->setAttribute('Target','footer1.xml');
        $relsDom->documentElement->appendChild($rel);
        $rels = $relsDom->saveXML();
        $outZip->addFromString('word/_rels/document.xml.rels',$rels);
    }

    // Adjust footer distance
    $footerDist = $dom->createElementNS($nsw, 'w:footerDistance');
    $footerDist->setAttribute('w:val', '1134'); // 2 cm
    $sectPr->appendChild($footerDist);

    $outZip->addFromString('word/document.xml',$dom->saveXML());
}




// Ensure [Content_Types].xml declares PNG
$ctypesXml = $outZip->getFromName('[Content_Types].xml');
if ($ctypesXml !== false) {
    $ctDom = new DOMDocument();
    $ctDom->loadXML($ctypesXml);
    $xpath = new DOMXPath($ctDom);
    $xpath->registerNamespace('c', 'http://schemas.openxmlformats.org/package/2006/content-types');
    if ($xpath->query('//c:Default[@Extension="png"]')->length === 0) {
        $def = $ctDom->createElementNS('http://schemas.openxmlformats.org/package/2006/content-types', 'Default');
        $def->setAttribute('Extension', 'png');
        $def->setAttribute('ContentType', 'image/png');
        $ctDom->documentElement->appendChild($def);
        $outZip->addFromString('[Content_Types].xml', $ctDom->saveXML());
    }
}

if ($headerDom) {
    $outZip->addFromString('word/header1.xml', $headerDom->saveXML());
}


$outZip->close();

        // --- Send file to client, DOCX or PDF depending on request ---
    $outputFormat = strtolower((string)($data['output_format'] ?? 'docx'));

if ($outputFormat === 'pdf') {
    $loProfile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lo_profile';
    if (!is_dir($loProfile)) {
        mkdir($loProfile, 0777, true);
    }

    $soffice = '"C:\\Program Files\\LibreOffice\\program\\soffice.exe"';
    $cmd = $soffice
        . ' --headless --nologo --nofirststartwizard'
        . ' -env:UserInstallation=file:///' . str_replace('\\', '/', $loProfile)
        . ' --convert-to pdf --outdir "' . dirname($tmpOut) . '" "' . $tmpOut . '"';

    exec($cmd . " 2>&1", $o, $ret);
    logx("LibreOffice CMD: " . $cmd);
    logx("LibreOffice RET: " . $ret);
    logx("LibreOffice OUT: " . implode(" | ", $o));

    // LibreOffice creates <basename>.pdf in outdir
    $pdfCandidate = dirname($tmpOut) . DIRECTORY_SEPARATOR
                  . preg_replace('/\.[^.]+$/', '', basename($tmpOut)) . '.pdf';

    if (!file_exists($pdfCandidate) || filesize($pdfCandidate) === 0) {
        throw new RuntimeException("PDF conversion failed. Expected: $pdfCandidate. OUT: " . implode(" | ", $o));
    }

    if (ob_get_length()) ob_end_clean();
    flush();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($pdfCandidate) . '"');
    header('Content-Length: ' . filesize($pdfCandidate));
    readfile($pdfCandidate);

    @unlink($tmpOut);
    @unlink($pdfCandidate);
    exit;
}

 else {
        // Default → DOCX
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="'. $outBaseName .'"');
        header('Content-Length: ' . filesize($tmpOut));
        readfile($tmpOut);
        @unlink($tmpOut);
        exit;
    }


} catch (Throwable $e) {
    logx('ERROR: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
}

// 01412761118