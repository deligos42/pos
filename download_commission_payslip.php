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

function payslip_pdf_text(string $text): string
{
    $text = preg_replace('/[^\x20-\x7E]/', '', $text);
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

$lines = [
    ['DELIGOS COMPANY', 20, 72, 752],
    ['COMMISSION PAYSLIP', 14, 72, 724],
    ['Generated: ' . date('Y-m-d H:i'), 9, 410, 724],
    ['Staff member: ' . $staff['full_name'], 11, 72, 678],
    ['Username: ' . $staff['username'], 10, 72, 658],
    ['Role: ' . ucfirst($staff['role']), 10, 72, 638],
    ['Commission period: ' . $start . ' to ' . $end, 10, 72, 610],
    ['Completed sales: ' . (int)$staff['sale_count'], 11, 72, 558],
    ['Eligible sales total: KSh ' . number_format($salesTotal, 2), 11, 72, 530],
    ['Commission rate: ' . number_format($rate, 2) . '%', 11, 72, 502],
    ['COMMISSION PAYABLE: KSh ' . number_format($commission, 2), 15, 72, 450],
    ['This payslip is system generated.', 9, 72, 90],
];

$content = "0.17 0.24 0.31 rg\n72 700 468 2 re f\n0.95 0.98 1 rg\n72 425 468 55 re f\n0.17 0.24 0.31 rg\n";
foreach ($lines as [$text, $size, $x, $y]) {
    $content .= "BT /F1 {$size} Tf {$x} {$y} Td (" . payslip_pdf_text($text) . ") Tj ET\n";
}

$objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
    '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . "endstream",
];

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
