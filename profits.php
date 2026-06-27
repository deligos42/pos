<?php
ob_start();
$required_role = 'admin';
require_once 'includes/auth.php';
require_once 'config/db.php';

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS expenses (
        id int(11) NOT NULL AUTO_INCREMENT,
        user_id int(11) NOT NULL,
        category varchar(100) NOT NULL,
        description varchar(255) DEFAULT NULL,
        amount decimal(10,2) NOT NULL,
        expense_date date NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY expense_date (expense_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-d');

function profit_pdf_text($x, $y, $text, $size = 10, $color = '0 0 0')
{
    $text = str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], (string)$text);
    $text = preg_replace('/[^\x20-\x7E]/', '', $text);
    return sprintf("%s rg\nBT /F1 %d Tf %.2f %.2f Td (%s) Tj ET\n", $color, $size, $x, $y, $text);
}

function profit_pdf_box($x, $y, $w, $h, $fill = null)
{
    if ($fill) {
        return sprintf("%s rg %.2f %.2f %.2f %.2f re f\n", $fill, $x, $y, $w, $h);
    }

    return sprintf("0.80 0.80 0.80 RG %.2f %.2f %.2f %.2f re S\n", $x, $y, $w, $h);
}

function profit_pdf_line($x1, $y1, $x2, $y2)
{
    return sprintf("0.82 0.82 0.82 RG %.2f %.2f m %.2f %.2f l S\n", $x1, $y1, $x2, $y2);
}

function profit_pdf_image($name, $x, $y, $w, $h)
{
    return sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q\n", $w, $h, $x, $y, $name);
}

function profit_pdf_cell($text, $limit)
{
    $text = trim((string)$text);
    return strlen($text) > $limit ? substr($text, 0, $limit - 3) . '...' : $text;
}

function profit_pdf_logo_data()
{
    $path = __DIR__ . '/assets/DELIGOS LOGO.jpg';
    if (!is_file($path)) {
        return null;
    }

    $size = @getimagesize($path);
    $data = @file_get_contents($path);
    if (!$size || !$data) {
        return null;
    }

    return [
        'width' => $size[0],
        'height' => $size[1],
        'data' => $data,
    ];
}

function build_profit_report_pdf($sales, $start, $end, $summary, $gross_sales, $cost_of_goods, $discounts, $gross_profit, $expenses_total, $net_profit)
{
    $logo = profit_pdf_logo_data();
    $pageW = 842;
    $pageH = 595;
    $margin = 36;
    $tableW = $pageW - ($margin * 2);
    $cols = [100, 120, 104, 104, 104, 104, 120];
    $headers = ['Invoice', 'Cashier', 'Sales', 'Cost', 'Discount', 'Profit', 'Date'];
    $pages = [];
    $content = '';
    $y = 0;

    $startPage = function () use (&$content, &$y, $logo, $pageH, $margin, $tableW, $cols, $headers, $start, $end, $summary, $gross_sales, $cost_of_goods, $discounts, $gross_profit, $expenses_total, $net_profit) {
        $content = '';
        if ($logo) {
            $content .= profit_pdf_image('Logo', $margin, $pageH - 82, 48, 48);
        }

        $content .= profit_pdf_text($margin + 60, $pageH - 38, 'DELIGOS COMPANY', 18);
        $content .= profit_pdf_text($margin + 60, $pageH - 58, 'Profit Report', 13);
        $content .= profit_pdf_text($margin + 60, $pageH - 76, 'Period: ' . $start . ' to ' . $end, 10);
        $content .= profit_pdf_text(650, $pageH - 64, 'Generated: ' . date('Y-m-d H:i'), 9);

        $metricY = $pageH - 138;
        $content .= profit_pdf_box($margin, $metricY, 180, 34, '0.90 0.97 1.00');
        $content .= profit_pdf_text($margin + 10, $metricY + 20, 'Sales: KSh ' . number_format($gross_sales, 2), 9);
        $content .= profit_pdf_box($margin + 194, $metricY, 180, 34, '1.00 0.97 0.86');
        $content .= profit_pdf_text($margin + 204, $metricY + 20, 'Cost: KSh ' . number_format($cost_of_goods, 2), 9);
        $content .= profit_pdf_box($margin + 388, $metricY, 180, 34, '1.00 0.92 0.92');
        $content .= profit_pdf_text($margin + 398, $metricY + 20, 'Expenses: KSh ' . number_format($expenses_total, 2), 9);
        $content .= profit_pdf_box($margin + 582, $metricY, 188, 34, $net_profit >= 0 ? '0.90 1.00 0.94' : '1.00 0.90 0.90');
        $content .= profit_pdf_text($margin + 592, $metricY + 20, 'Net: KSh ' . number_format($net_profit, 2), 9);

        $summaryY = $pageH - 182;
        $content .= profit_pdf_text($margin, $summaryY, 'Sales Count: ' . (int)($summary['total_sales'] ?? 0), 9);
        $content .= profit_pdf_text($margin + 170, $summaryY, 'Discounts: KSh ' . number_format($discounts, 2), 9);
        $content .= profit_pdf_text($margin + 360, $summaryY, 'Gross Profit: KSh ' . number_format($gross_profit, 2), 9);

        $headerY = $pageH - 216;
        $content .= profit_pdf_box($margin, $headerY, $tableW, 22, '0.17 0.24 0.31');
        $x = $margin + 6;
        foreach ($headers as $i => $header) {
            $content .= profit_pdf_text($x, $headerY + 8, $header, 8, '1 1 1');
            $x += $cols[$i];
        }
        $y = $headerY - 18;
    };

    $finishPage = function () use (&$pages, &$content, $margin, $tableW) {
        $content .= profit_pdf_line($margin, 38, $margin + $tableW, 38);
        $content .= profit_pdf_text($margin, 24, 'POS System', 8);
        $pages[] = $content;
    };

    $startPage();
    foreach ($sales as $index => $sale) {
        if ($y < 58) {
            $finishPage();
            $startPage();
        }

        $rowY = $y - 4;
        if ($index % 2 === 0) {
            $content .= profit_pdf_box($margin, $rowY, $tableW, 18, '0.97 0.97 0.97');
        }

        $row = [
            profit_pdf_cell($sale['invoice_no'], 16),
            profit_pdf_cell($sale['cashier'], 20),
            'KSh ' . number_format((float)$sale['gross_sales'], 2),
            'KSh ' . number_format((float)$sale['cost_of_goods'], 2),
            'KSh ' . number_format((float)$sale['discount'], 2),
            'KSh ' . number_format((float)$sale['profit'], 2),
            date('d/m/Y H:i', strtotime($sale['sale_date'])),
        ];

        $x = $margin + 6;
        foreach ($row as $i => $value) {
            $content .= profit_pdf_text($x, $y + 2, $value, 7);
            $x += $cols[$i];
        }

        $content .= profit_pdf_line($margin, $rowY, $margin + $tableW, $rowY);
        $y -= 18;
    }

    if (!$sales) {
        $content .= profit_pdf_text($margin + 6, $y + 2, 'No sales found for this period.', 10);
    }
    $finishPage();

    $objects = [
        "<< /Type /Catalog /Pages 2 0 R >>",
        '',
        "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
    ];
    $logoObjectNumber = 0;
    if ($logo) {
        $logoObjectNumber = count($objects) + 1;
        $objects[] = "<< /Type /XObject /Subtype /Image /Width {$logo['width']} /Height {$logo['height']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($logo['data']) . " >>\nstream\n" . $logo['data'] . "\nendstream";
    }

    $kids = [];
    foreach ($pages as $pageContent) {
        $pageObjectNumber = count($objects) + 1;
        $contentObjectNumber = $pageObjectNumber + 1;
        $kids[] = $pageObjectNumber . ' 0 R';
        $xObjectResource = $logoObjectNumber ? " /XObject << /Logo $logoObjectNumber 0 R >>" : '';
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pageW $pageH] /Resources << /Font << /F1 3 0 R >>$xObjectResource >> /Contents $contentObjectNumber 0 R >>";
        $objects[] = "<< /Length " . strlen($pageContent) . " >>\nstream\n" . $pageContent . "endstream";
    }
    $objects[1] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($pages) . " >>";

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $i => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
    $pdf .= "startxref\n$xrefOffset\n%%EOF";

    return $pdf;
}

$summaryStmt = $pdo->prepare(
    "SELECT
        COUNT(DISTINCT s.id) AS total_sales,
        COALESCE(SUM(si.total), 0) AS gross_sales,
        COALESCE(SUM(si.qty * COALESCE(p.cost, 0)), 0) AS cost_of_goods,
        COALESCE(SUM(si.total - (si.qty * COALESCE(p.cost, 0))), 0) AS gross_profit,
        COALESCE(SUM((si.total / NULLIF(s.total_amount, 0)) * s.discount), 0) AS discount_share
     FROM sales s
     JOIN sale_items si ON si.sale_id = s.id
     JOIN products p ON p.id = si.product_id
     WHERE DATE(s.sale_date) BETWEEN ? AND ?"
);
$summaryStmt->execute([$start, $end]);
$summary = $summaryStmt->fetch();

$expenseStmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ?");
$expenseStmt->execute([$start, $end]);
$expenses_total = (float)$expenseStmt->fetchColumn();

$gross_sales = (float)($summary['gross_sales'] ?? 0);
$cost_of_goods = (float)($summary['cost_of_goods'] ?? 0);
$discounts = (float)($summary['discount_share'] ?? 0);
$gross_profit = (float)($summary['gross_profit'] ?? 0) - $discounts;
$net_profit = $gross_profit - $expenses_total;

$detailStmt = $pdo->prepare(
    "SELECT
        s.invoice_no,
        s.sale_date,
        u.full_name AS cashier,
        s.discount,
        COALESCE(SUM(si.total), 0) AS gross_sales,
        COALESCE(SUM(si.qty * COALESCE(p.cost, 0)), 0) AS cost_of_goods,
        COALESCE(SUM(si.total - (si.qty * COALESCE(p.cost, 0))), 0) - s.discount AS profit
     FROM sales s
     JOIN users u ON u.id = s.user_id
     JOIN sale_items si ON si.sale_id = s.id
     JOIN products p ON p.id = si.product_id
     WHERE DATE(s.sale_date) BETWEEN ? AND ?
     GROUP BY s.id, s.invoice_no, s.sale_date, u.full_name, s.discount
     ORDER BY s.sale_date DESC"
);
$detailStmt->execute([$start, $end]);
$sales = $detailStmt->fetchAll();

if (($_GET['download'] ?? '') === 'pdf') {
    $filename = 'profit-report-' . preg_replace('/[^0-9-]/', '', $start) . '-to-' . preg_replace('/[^0-9-]/', '', $end) . '.pdf';
    if (ob_get_length()) {
        ob_end_clean();
    }

    $pdf = build_profit_report_pdf($sales, $start, $end, $summary, $gross_sales, $cost_of_goods, $discounts, $gross_profit, $expenses_total, $net_profit);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

if (ob_get_length()) {
    ob_end_clean();
}

$pageTitle = 'Profits';
include 'includes/header.php';
$download_query = http_build_query([
    'start' => $start,
    'end' => $end,
    'download' => 'pdf',
]);
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <h2 class="mb-0">Profit Manager</h2>
    <div class="d-flex gap-2 flex-wrap d-print-none">
        <button class="btn btn-secondary" onclick="window.print()" type="button"><i class="bi bi-printer"></i> Print</button>
        <a class="btn btn-success" href="?<?= htmlspecialchars($download_query) ?>"><i class="bi bi-file-earmark-pdf"></i> Download PDF</a>
        <a class="btn btn-outline-secondary" href="profits.php"><i class="bi bi-x-circle"></i> Clear</a>
        <a class="btn btn-outline-danger" href="expenses.php"><i class="bi bi-cash-coin"></i> Expenses Manager</a>
    </div>
</div>

<form method="GET" class="row g-3 mb-3 d-print-none">
    <div class="col-auto"><label>From</label><input type="date" name="start" class="form-control" value="<?= htmlspecialchars($start) ?>"></div>
    <div class="col-auto"><label>To</label><input type="date" name="end" class="form-control" value="<?= htmlspecialchars($end) ?>"></div>
    <div class="col-auto align-self-end"><button class="btn btn-primary" type="submit">Filter</button></div>
</form>

<div class="row mb-3">
    <div class="col-md-3"><div class="card bg-primary text-white p-3">Sales<br><strong class="fs-4">KSh <?= number_format($gross_sales, 2) ?></strong></div></div>
    <div class="col-md-3"><div class="card bg-warning text-dark p-3">Cost of Goods<br><strong class="fs-4">KSh <?= number_format($cost_of_goods, 2) ?></strong></div></div>
    <div class="col-md-3"><div class="card bg-danger text-white p-3">Expenses<br><strong class="fs-4">KSh <?= number_format($expenses_total, 2) ?></strong></div></div>
    <div class="col-md-3"><div class="card <?= $net_profit >= 0 ? 'bg-success' : 'bg-danger' ?> text-white p-3">Net Profit<br><strong class="fs-4">KSh <?= number_format($net_profit, 2) ?></strong></div></div>
</div>

<div class="card mb-3">
    <div class="card-header">Profit Summary</div>
    <div class="card-body">
        <table class="table table-sm mb-0">
            <tbody>
                <tr><th>Total Sales Count</th><td><?= (int)($summary['total_sales'] ?? 0) ?></td></tr>
                <tr><th>Gross Sales</th><td>KSh <?= number_format($gross_sales, 2) ?></td></tr>
                <tr><th>Discounts</th><td>KSh <?= number_format($discounts, 2) ?></td></tr>
                <tr><th>Cost of Goods</th><td>KSh <?= number_format($cost_of_goods, 2) ?></td></tr>
                <tr><th>Gross Profit After Discounts</th><td>KSh <?= number_format($gross_profit, 2) ?></td></tr>
                <tr><th>Expenses</th><td>KSh <?= number_format($expenses_total, 2) ?></td></tr>
                <tr><th>Net Profit</th><td><strong>KSh <?= number_format($net_profit, 2) ?></strong></td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">Profit by Sale</div>
    <div class="card-body">
        <table class="table table-striped">
            <thead><tr><th>Invoice</th><th>Cashier</th><th>Sales</th><th>Cost</th><th>Discount</th><th>Profit</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($sales as $sale): ?>
                <tr>
                    <td><?= htmlspecialchars($sale['invoice_no']) ?></td>
                    <td><?= htmlspecialchars($sale['cashier']) ?></td>
                    <td>KSh <?= number_format((float)$sale['gross_sales'], 2) ?></td>
                    <td>KSh <?= number_format((float)$sale['cost_of_goods'], 2) ?></td>
                    <td>KSh <?= number_format((float)$sale['discount'], 2) ?></td>
                    <td><strong>KSh <?= number_format((float)$sale['profit'], 2) ?></strong></td>
                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($sale['sale_date']))) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$sales): ?>
                <tr><td colspan="7" class="text-muted">No sales found for this period.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include 'includes/footer.php'; ?>
