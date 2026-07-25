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

function payslip_pdf_text(string $text): string
{
    $text = preg_replace('/[^\x20-\x7E]/', '', $text);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

$logoPath = __DIR__ . '/assets/DELIGOS LOGO.jpg';
$logoData = file_exists($logoPath) ? file_get_contents($logoPath) : false;
$logoInfo = $logoData !== false ? @getimagesize($logoPath) : false;
$hasLogo = $logoData !== false && $logoInfo && ($logoInfo['mime'] ?? '') === 'image/jpeg';

$lines = [
    ['DELIGOS COMPANY', 20, 72, 752],
    ['COMMISSION PAYSLIP', 14, 72, 724],
    ['Generated: ' . date('Y-m-d H:i'), 9, 410, 724],
    ['Reference: ' . $referenceNumber, 9, 72, 700],
    ['Staff member: ' . $staff['full_name'], 11, 72, 660],
    ['Username: ' . $staff['username'], 10, 72, 640],
    ['Role: ' . ucfirst($staff['role']), 10, 72, 620],
    ['Commission period: ' . $start . ' to ' . $end, 10, 72, 598],
    ['Completed sales: ' . (int)$staff['sale_count'], 11, 72, 562],
    ['Eligible sales total: KSh ' . number_format($salesTotal, 2), 11, 72, 540],
    ['Commission rate: ' . number_format($rate, 2) . '%', 11, 72, 518],
    ['COMMISSION PAYABLE: KSh ' . number_format($commission, 2), 15, 72, 448],
];

$footerLines = [
    ['Prepared by', 10, 72, 78],
    ['________________________', 10, 72, 58],
    ['Approved by', 10, 250, 78],
    ['________________________', 10, 250, 58],
    ['Company Address: P.O. Box 1234, Nairobi, Kenya', 8, 72, 28],
    ['Contact: +254 700 000 000 | info@deligos.co.ke', 8, 72, 14],
];

$content = "0.95 0.98 1 rg\n72 700 468 2 re f\n0.95 0.98 1 rg\n72 425 468 55 re f\n0.86 0.90 0.95 rg\n72 492 468 1 re f\n0.80 0.84 0.90 rg\n72 96 468 1 re f\n0.17 0.24 0.31 rg\n";

if ($hasLogo) {
    $content .= "q /WMLOGO gs\n";
    $watermarkWidth = 230;
    $watermarkHeight = max(1, round($watermarkWidth * ($logoInfo[1] / $logoInfo[0])));
    $watermarkX = round((612 - $watermarkWidth) / 2);
    $watermarkY = round((792 - $watermarkHeight) / 2) + 10;
    $content .= "q {$watermarkWidth} 0 0 {$watermarkHeight} {$watermarkX} {$watermarkY} cm /Im1 Do Q\n";
    $content .= "Q\n";

    $headerLogoWidth = 92;
    $headerLogoHeight = max(1, round($headerLogoWidth * ($logoInfo[1] / $logoInfo[0])));
    $headerLogoX = 430;
    $headerLogoY = 742;
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
