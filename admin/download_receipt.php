<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

require_permission('receipts.reprint', '/admin/index.php');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid receipt id.');
}

try {
    $stmt = $pdo->prepare("SELECT r.*, s.invoice_no FROM receipts r LEFT JOIN sales s ON r.sale_id = s.id WHERE r.id = ?");
    $stmt->execute([$id]);
    $rec = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$rec) {
        http_response_code(404);
        exit('Receipt not found.');
    }
} catch (Throwable $e) {
    app_log('download_receipt error: ' . $e->getMessage());
    http_response_code(500);
    exit('Internal error.');
}

$raw = $rec['snapshot'] ?? '';
$decoded = null;
try {
    $decoded = $raw ? json_decode($raw, true, 512, JSON_THROW_ON_ERROR) : null;
} catch (Throwable $e) {
    app_log('download_receipt json decode failed: ' . $e->getMessage());
}

if (!is_array($decoded)) {
    http_response_code(400);
    exit('Invalid receipt snapshot.');
}

// Prepare receipt data
$items = $decoded['items'] ?? [];
$invoice = $decoded['invoice_no'] ?? $decoded['invoice'] ?? $rec['invoice_no'] ?? '';
$date = $decoded['created_at'] ?? $decoded['date'] ?? $rec['created_at'] ?? date('Y-m-d H:i');
$cashier = $decoded['cashier'] ?? $_SESSION['full_name'] ?? '';
$discount = (float)($decoded['discount'] ?? 0);
$grand = (float)($decoded['grand_total'] ?? $decoded['total_amount'] ?? 0);
$mpesaCode = trim((string)($decoded['mpesa_code'] ?? ''));

// Load logo (prefer jpg then png)
$logoPathJ = __DIR__ . '/../assets/DELIGOS LOGO.jpg';
$logoPathP = __DIR__ . '/../assets/DELIGOS LOGO.png';
$logoPath = file_exists($logoPathJ) ? $logoPathJ : (file_exists($logoPathP) ? $logoPathP : false);
$logoData = false;
$logoInfo = false;
if ($logoPath) {
    $logoData = file_get_contents($logoPath);
    $logoInfo = @getimagesize($logoPath);
}

// Build a simple PDF similar to client-side receipt
$pageW = 210 * (72 / 25.4); // points for A4 width
$pageH = 297 * (72 / 25.4);
$content = '';

function esc_pdf($s) {
    $s = (string)$s;
    $s = preg_replace('/[^\x20-\x7E]/', '', $s);
    $s = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    return $s;
}

function pdfText($x, $y, $text, $size = 10, $color = '0 0 0', $font = 'F1') {
    return sprintf("%s rg\nBT /%s %s Tf %s %s Td (%s) Tj ET\n", $color, $font, $size, $x, $y, esc_pdf($text));
}

function pdfRightText($x, $y, $text, $size = 10, $color = '0 0 0', $font = 'F1') {
    $approx = mb_strlen((string)$text, '8bit') * $size * 0.55;
    return pdfText($x - $approx, $y, $text, $size, $color, $font);
}

function pdfCenteredText($centerX, $y, $text, $size = 10, $color = '0 0 0', $font = 'F1') {
    $approx = mb_strlen($text, '8bit') * $size * 0.55;
    return pdfText($centerX - ($approx / 2), $y, $text, $size, $color, $font);
}

function pdfLine($x1, $y1, $x2, $y2) {
    return sprintf("0.75 0.75 0.75 RG %s %s m %s %s l S\n", $x1, $y1, $x2, $y2);
}

function pdfBox($x, $y, $w, $h, $fill) {
    return sprintf("%s rg %s %s %s %s re f\n", $fill, $x, $y, $w, $h);
}

function pdfImage($name, $x, $y, $w, $h) {
    return sprintf("q %s 0 0 %s %s %s cm /%s Do Q\n", $w, $h, $x, $y, $name);
}

$mm = 72 / 25.4;
$receiptSize = 80 * $mm;
$receiptX = ($pageW - $receiptSize) / 2;
$receiptBottom = $pageH - $receiptSize - 20;
$centerX = $receiptX + ($receiptSize / 2);
$textLeft = $receiptX + 10;
$textRight = $receiptX + $receiptSize - 10;

$content .= pdfBox($receiptX, $receiptBottom, $receiptSize, $receiptSize, '1 1 1');

$y = $pageH - 20;
if ($logoData && $logoInfo) {
    $logoWpt = 54;
    $logoHpt = max(12, ($logoInfo[1] / $logoInfo[0]) * $logoWpt);
    $content .= pdfImage('Im1', $centerX - ($logoWpt / 2), $y - $logoHpt, $logoWpt, $logoHpt);
    $y -= $logoHpt + 8;
}

$content .= pdfCenteredText($centerX, $y, 'DELIGOS COMPANY', 10, '0 0 0', 'F2');
$y -= 12;
$content .= pdfCenteredText($centerX, $y, 'Invoice: ' . $invoice, 7);
$y -= 9;
$content .= pdfCenteredText($centerX, $y, $date, 7);
$y -= 9;
$content .= pdfCenteredText($centerX, $y, 'Cashier: ' . $cashier, 7);
$y -= 10;
$content .= pdfLine($textLeft, $y, $textRight, $y);
$y -= 10;

$content .= pdfText($textLeft, $y, 'Item', 7, '0 0 0', 'F2');
$content .= pdfText($receiptX + 142, $y, 'Qty', 7, '0 0 0', 'F2');
    $content .= pdfRightText($receiptX + 174, $y, 'Price', 7, '0 0 0', 'F2');
    $y -= 8;
    $content .= pdfLine($textLeft, $y, $textRight, $y);
    $y -= 9;

    $footerHeight = 42;
    $rowHeight = 9;
    $maxRows = max(0, (int)floor(($y - ($receiptBottom + $footerHeight)) / $rowHeight));
    $visibleItems = array_slice($items, 0, $maxRows);

    foreach ($visibleItems as $it) {
        $name = esc_pdf($it['name'] ?? '');
        if (mb_strlen($name, '8bit') > 22) {
            $name = mb_substr($name, 0, 19, '8bit') . '...';
        }
        $qty = (int)($it['qty'] ?? 0);
        $total = number_format((float)($it['total'] ?? 0), 2);
        $content .= pdfText($textLeft, $y, $name, 6);
        $content .= pdfRightText($receiptX + 160, $y, $qty, 6);
        $content .= pdfRightText($receiptX + 188, $y, 'KSh ' . $total, 6);
        $y -= $rowHeight;
    }

    if (count($visibleItems) < count($items)) {
        $content .= pdfText($textLeft, $y, '+ ' . (count($items) - count($visibleItems)) . ' more item(s)', 6);
    }

    $y = $receiptBottom + 34;
    $content .= pdfLine($textLeft, $y, $textRight, $y);
$y -= 10;
$content .= pdfText($textLeft, $y, 'Discount: KSh ' . number_format($discount, 2), 7);
$y -= 10;
    if ($mpesaCode !== '') {
        $content .= pdfText($textLeft, $y, 'MPESA Code: ' . $mpesaCode, 7);
        $y -= 10;
    }
$content .= pdfCenteredText($centerX, $y, 'Thank you!', 7);

// Build objects array for PDF
$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';
$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>';
if ($logoData && $logoInfo) {
    $objects[] = '<< /Type /XObject /Subtype /Image /Width ' . $logoInfo[0] . ' /Height ' . $logoInfo[1] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($logoData) . " >>\nstream\n" . $logoData . "\nendstream";
}
$objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $pageW . ' ' . $pageH . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>' . ($logoData ? ' /XObject << /Im1 5 0 R >>' : '') . ' >> /Contents 6 0 R >>';
$objects[] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";

$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $index => $object) {
    $offsets[] = strlen($pdf);
    $pdf .= ($index + 1) . " 0 obj\n{$object}\nendobj\n";
}

$xref = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
for ($i = 1; $i <= count($objects); $i++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
}
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

$filename = 'receipt-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $invoice) . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
