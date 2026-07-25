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

// Simple layout values
$pad = 20;
$xLeft = $pad;
$xRight = $pageW - $pad;
$y = $pageH - 40;

// Background box
$content .= "0.95 0.98 1 rg\n{$xLeft} " . ($y - 240) . " " . ($xRight - $xLeft) . " 240 re f\n";

// Logo if available
$logoObject = 0;
$objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
];

if ($logoData && $logoInfo) {
    $logoObject = 4; // will be index in objects later
}

// Add text header
$content .= "BT /F2 18 Tf {$xLeft} {$y} Td (" . esc_pdf('DELIGOS COMPANY') . ") Tj ET\n";
$y -= 22;
$content .= "BT /F1 11 Tf {$xLeft} {$y} Td (Invoice: " . esc_pdf($invoice) . ") Tj ET\n";
$content .= "BT /F1 11 Tf " . ($xRight - 160) . " {$y} Td (Date: " . esc_pdf($date) . ") Tj ET\n";
$y -= 18;

// Items header
$content .= "BT /F2 11 Tf {$xLeft} {$y} Td (Item) Tj ET\n";
$content .= "BT /F2 11 Tf " . ($xRight - 120) . " {$y} Td (Qty) Tj ET\n";
$content .= "BT /F2 11 Tf " . ($xRight - 60) . " {$y} Td (Total) Tj ET\n";
$y -= 14;

// Items rows
foreach ($items as $it) {
    $name = esc_pdf($it['name'] ?? '');
    $qty = (int)($it['qty'] ?? 0);
    $total = number_format((float)($it['total'] ?? 0), 2);
    $content .= "BT /F1 10 Tf {$xLeft} {$y} Td ({$name}) Tj ET\n";
    $content .= "BT /F1 10 Tf " . ($xRight - 120) . " {$y} Td ({$qty}) Tj ET\n";
    $content .= "BT /F1 10 Tf " . ($xRight - 60) . " {$y} Td (KSh {$total}) Tj ET\n";
    $y -= 12;
}

$y -= 8;
$content .= "BT /F1 10 Tf " . ($xRight - 200) . " {$y} Td (Discount: KSh " . number_format($discount,2) . ") Tj ET\n";
$y -= 12;
$content .= "BT /F2 12 Tf " . ($xRight - 200) . " {$y} Td (Grand Total: KSh " . number_format($grand,2) . ") Tj ET\n";

// Build objects array for PDF
$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
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
