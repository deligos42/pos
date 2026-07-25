<?php
$required_role = 'admin';
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

require_post_csrf();

$userId = validate_int($_POST['user_id'] ?? null, 1);
$start = $_POST['start'] ?? '';
$end = $_POST['end'] ?? '';
$rate = validate_decimal($_POST['rate'] ?? null, 0, 100);

if ($userId === null || $rate === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) || $start > $end) {
    http_response_code(422);
    exit('Invalid payslip request.');
}

try {
    $stmt = $pdo->prepare(
        "SELECT
            u.full_name,
            u.username,
            u.role,
            COUNT(s.id) AS sale_count,
            COALESCE(SUM(s.grand_total), 0) AS sales_total
         FROM users u
         LEFT JOIN sales s ON s.user_id = u.id AND DATE(s.sale_date) BETWEEN ? AND ?
         WHERE u.id = ?
         GROUP BY u.id, u.full_name, u.username, u.role"
    );
    $stmt->execute([$start, $end, $userId]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$staff) {
        http_response_code(404);
        exit('Staff member not found.');
    }
} catch (Throwable $e) {
    http_response_code(500);
    exit('Could not create the payslip.');
}

$salesTotal = (float)$staff['sales_total'];
$commission = round($salesTotal * ($rate / 100), 2);
$referenceNumber = 'PAY-' . strtoupper(substr(md5($staff['username'] . $start . $end . $rate . $staff['full_name']), 0, 10));

$pageW = 612;
$pageH = 792;
$marginLeft = 72;
$marginRight = 72;
$marginTop = 72;
$marginBottom = 54;
$contentWidth = $pageW - $marginLeft - $marginRight;

function payslip_pdf_text(string $text): string
{
    $text = preg_replace('/[^\x20-\x7E]/', '', $text);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

$logoPath = __DIR__ . '/assets/DELIGOS LOGO.jpg';
if (!file_exists($logoPath)) {
    $fallbackPath = __DIR__ . '/assets/DELIGOS LOGO.png';
    if (file_exists($fallbackPath)) {
        $logoPath = $fallbackPath;
    }
}
$logoData = file_exists($logoPath) ? file_get_contents($logoPath) : false;
$logoInfo = $logoData !== false ? @getimagesize($logoPath) : false;
$hasLogo = $logoData !== false && $logoInfo && in_array($logoInfo['mime'] ?? '', ['image/jpeg', 'image/png'], true);

$headerY = $pageH - $marginTop - 8;
$lineGap = 18;
$lines = [
    ['DELIGOS COMPANY', 20, $marginLeft, $headerY],
    ['COMMISSION PAYSLIP', 14, $marginLeft, $headerY - 28],
    ['Generated: ' . date('Y-m-d H:i'), 9, $pageW - $marginRight - 140, $headerY - 6],
    ['Reference: ' . $referenceNumber, 9, $marginLeft, $headerY - 44],
    ['Staff member: ' . $staff['full_name'], 11, $marginLeft, $headerY - 80],
    ['Username: ' . $staff['username'], 10, $marginLeft, $headerY - 100],
    ['Role: ' . ucfirst($staff['role']), 10, $marginLeft, $headerY - 120],
    ['Commission period: ' . $start . ' to ' . $end, 10, $marginLeft, $headerY - 140],
    ['Completed sales: ' . (int)$staff['sale_count'], 11, $marginLeft, $headerY - 182],
    ['Eligible sales total: KSh ' . number_format($salesTotal, 2), 11, $marginLeft, $headerY - 202],
    ['Commission rate: ' . number_format($rate, 2) . '%', 11, $marginLeft, $headerY - 222],
    ['COMMISSION PAYABLE: KSh ' . number_format($commission, 2), 15, $marginLeft, $headerY - 290],
];

$footerY = $marginBottom + 40;
$footerLines = [
    ['Prepared by', 10, $marginLeft, $footerY + 26],
    ['________________________', 10, $marginLeft, $footerY + 8],
    ['Approved by', 10, $marginLeft + 220, $footerY + 26],
    ['________________________', 10, $marginLeft + 220, $footerY + 8],
    ['Company Address: P.O. Box 1234, Nairobi, Kenya', 8, $marginLeft, $footerY - 16],
    ['Contact: +254 700 000 000 | info@deligos.co.ke', 8, $marginLeft, $footerY - 32],
];

$content = sprintf(
    "0.95 0.98 1 rg\n%d 700 %d 2 re f\n0.95 0.98 1 rg\n%d 425 %d 55 re f\n0.86 0.90 0.95 rg\n%d 492 %d 1 re f\n0.80 0.84 0.90 rg\n%d 96 %d 1 re f\n0.17 0.24 0.31 rg\n",
    $marginLeft,
    $contentWidth,
    $marginLeft,
    $contentWidth,
    $marginLeft,
    $contentWidth,
    $marginLeft,
    $contentWidth
);

if ($hasLogo) {
    $content .= "q /WMLOGO gs\n";
    $watermarkWidth = min(180, $contentWidth * 0.7);
    $watermarkHeight = max(1, round($watermarkWidth * ($logoInfo[1] / $logoInfo[0])));
    $watermarkX = ($pageW - $watermarkWidth) / 2;
    $watermarkY = ($pageH - $watermarkHeight) / 2 - 20;
    $content .= sprintf("q 0.906 0.423 -0.423 0.906 %.2f %.2f cm /Im1 Do Q\n", $watermarkX, $watermarkY);
    $content .= "Q\n";

    $headerLogoWidth = 70;
    $headerLogoHeight = max(1, round($headerLogoWidth * ($logoInfo[1] / $logoInfo[0])));
    $headerLogoX = $pageW - $marginRight - $headerLogoWidth;
    $headerLogoY = $pageH - $marginTop - $headerLogoHeight;
    $content .= "q {$headerLogoWidth} 0 0 {$headerLogoHeight} {$headerLogoX} {$headerLogoY} cm /Im1 Do Q\n";
}

$content .= "q /WMTEXT gs\n";
$content .= "0.76 0.80 0.84 rg\n";
$content .= "BT /F2 24 Tf 0.866 0.5 -0.5 0.866 140 360 Tm (" . payslip_pdf_text('DELIGOS COMPANY') . ") Tj ET\n";
$content .= "Q\n";

foreach ($lines as [$text, $size, $x, $y]) {
    $content .= "BT /F1 {$size} Tf {$x} {$y} Td (" . payslip_pdf_text($text) . ") Tj ET\n";
}

foreach ($footerLines as [$text, $size, $x, $y]) {
    $content .= "BT /F1 {$size} Tf {$x} {$y} Td (" . payslip_pdf_text($text) . ") Tj ET\n";
}

$objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> /ExtGState << /WMLOGO 6 0 R /WMTEXT 7 0 R /NORMAL 8 0 R >> /XObject << /Im1 9 0 R >> >> /Contents 10 0 R >>',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
    '<< /Type /ExtGState /CA 0.12 /ca 0.12 >>',
    '<< /Type /ExtGState /CA 0.08 /ca 0.08 >>',
    '<< /Type /ExtGState /CA 1 /ca 1 >>',
];

if ($hasLogo) {
    $objects[] = '<< /Type /XObject /Subtype /Image /Width ' . $logoInfo[0] . ' /Height ' . $logoInfo[1] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($logoData) . " >>\nstream\n" . $logoData . "\nendstream";
} else {
    $objects[] = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length 0 >>\nstream\n\nendstream';
}

$objects[] = '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream";

$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $index => $object) {
    $offsets[] = strlen($pdf);
    $pdf .= ($index + 1) . " 0 obj\n{$object}\nendobj\n";
}
$xref = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
for ($index = 1; $index <= count($objects); $index++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$index]);
}
$pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

$filename = 'commission-payslip-' . preg_replace('/[^A-Za-z0-9_-]/', '-', $staff['username']) . '-' . $start . '-to-' . $end . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
