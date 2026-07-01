<?php
$required_role = 'admin';
require_once 'includes/auth.php';
require_once 'config/db.php';

function pdf_text($value) {
    $value = (string)$value;
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value);
        if ($converted !== false) {
            $value = $converted;
        }
    }

    return str_replace(["\\", "(", ")", "\r", "\n"], ["\\\\", "\\(", "\\)", " ", " "], $value);
}

function pdf_wrap($text, $maxChars = 88) {
    $words = preg_split('/\s+/', trim($text));
    $lines = [];
    $line = '';

    foreach ($words as $word) {
        $candidate = $line === '' ? $word : $line . ' ' . $word;
        if (strlen($candidate) > $maxChars && $line !== '') {
            $lines[] = $line;
            $line = $word;
        } else {
            $line = $candidate;
        }
    }

    if ($line !== '') {
        $lines[] = $line;
    }

    return $lines;
}

function pdf_text_line(&$content, $text, $x, $y, $size = 11, $font = 'F1') {
    $content .= "BT /{$font} {$size} Tf 1 0 0 1 {$x} {$y} Tm (" . pdf_text($text) . ") Tj ET\n";
}

function pdf_paragraph(&$content, $text, $x, &$y, $size = 11, $lineHeight = 17, $maxChars = 78) {
    foreach (pdf_wrap($text, $maxChars) as $line) {
        pdf_text_line($content, $line, $x, $y, $size);
        $y -= $lineHeight;
    }
    $y -= 10;
}

function pdf_recommendation_watermark(&$content, $companyName, $pageWidth, $pageHeight, $hasLogo, $logoInfo) {
    if ($hasLogo) {
        $content .= "q /WMLOGO gs\n";
        $logoWidth = 225;
        $logoHeight = max(1, round($logoWidth * ($logoInfo[1] / $logoInfo[0])));
        $logoX = round(($pageWidth - $logoWidth) / 2);
        $logoY = round(($pageHeight - $logoHeight) / 2) + 10;
        $content .= "q {$logoWidth} 0 0 {$logoHeight} {$logoX} {$logoY} cm /Im1 Do Q\n";
        $content .= "Q\n";
    }

    $content .= "q /WMTEXT gs\n";
    $text = pdf_text($companyName);
    for ($y = 92; $y < $pageHeight; $y += 112) {
        for ($x = -70; $x < $pageWidth; $x += 165) {
            $content .= "BT /F2 8 Tf 0.42 0.48 0.54 rg 0.866 0.5 -0.5 0.866 {$x} {$y} Tm ({$text}) Tj ET\n";
        }
    }
    $content .= "Q\nq /NORMAL gs Q\n";
}

function recommendation_reference_number($cashier) {
    try {
        $random = strtoupper(bin2hex(random_bytes(2)));
    } catch (Exception $e) {
        $random = strtoupper(substr(md5(uniqid('', true)), 0, 4));
    }

    return 'REC-' . date('ymd-His') . '-' . str_pad((string)(int)$cashier['id'], 3, '0', STR_PAD_LEFT) . '-' . $random;
}

function ensure_recommendation_letters_table($pdo) {
    return true;
}

function create_recommendation_letter_record($pdo, $cashier, $generatedBy) {
    ensure_recommendation_letters_table($pdo);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $referenceNumber = recommendation_reference_number($cashier);

        try {
            $stmt = $pdo->prepare("INSERT INTO recommendation_letters (ref_no, user_id, generated_by) VALUES (?, ?, ?)");
            $stmt->execute([$referenceNumber, (int)$cashier['id'], $generatedBy ?: null]);
            return $referenceNumber;
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }
        }
    }

    throw new RuntimeException('Could not generate a unique recommendation reference number.');
}

function qr_gf_mul($x, $y) {
    $result = 0;
    while ($y > 0) {
        if ($y & 1) {
            $result ^= $x;
        }
        $x <<= 1;
        if ($x & 0x100) {
            $x ^= 0x11D;
        }
        $y >>= 1;
    }

    return $result & 0xFF;
}

function qr_gf_pow($power) {
    $result = 1;
    for ($i = 0; $i < $power; $i++) {
        $result = qr_gf_mul($result, 2);
    }

    return $result;
}

function qr_rs_generator($degree) {
    $generator = [1];
    for ($i = 0; $i < $degree; $i++) {
        $next = array_fill(0, count($generator) + 1, 0);
        $root = qr_gf_pow($i);
        foreach ($generator as $index => $coefficient) {
            $next[$index] ^= $coefficient;
            $next[$index + 1] ^= qr_gf_mul($coefficient, $root);
        }
        $generator = $next;
    }

    return $generator;
}

function qr_rs_ecc($data, $degree) {
    $generator = qr_rs_generator($degree);
    $message = array_merge($data, array_fill(0, $degree, 0));

    for ($i = 0; $i < count($data); $i++) {
        $factor = $message[$i];
        if ($factor === 0) {
            continue;
        }
        foreach ($generator as $j => $coefficient) {
            $message[$i + $j] ^= qr_gf_mul($coefficient, $factor);
        }
    }

    return array_slice($message, -$degree);
}

function qr_append_bits(&$bits, $value, $length) {
    for ($i = $length - 1; $i >= 0; $i--) {
        $bits[] = ($value >> $i) & 1;
    }
}

function qr_make_matrix($text) {
    $version = 3;
    $size = 29;
    $dataCodewords = 55;
    $eccCodewords = 15;
    $text = substr((string)$text, 0, 53);

    $bits = [];
    qr_append_bits($bits, 0x4, 4);
    qr_append_bits($bits, strlen($text), 8);
    for ($i = 0; $i < strlen($text); $i++) {
        qr_append_bits($bits, ord($text[$i]), 8);
    }

    $capacityBits = $dataCodewords * 8;
    qr_append_bits($bits, 0, min(4, $capacityBits - count($bits)));
    while (count($bits) % 8 !== 0) {
        $bits[] = 0;
    }

    $data = [];
    for ($i = 0; $i < count($bits); $i += 8) {
        $byte = 0;
        for ($j = 0; $j < 8; $j++) {
            $byte = ($byte << 1) | $bits[$i + $j];
        }
        $data[] = $byte;
    }

    $pad = 0xEC;
    while (count($data) < $dataCodewords) {
        $data[] = $pad;
        $pad = $pad === 0xEC ? 0x11 : 0xEC;
    }

    $codewords = array_merge($data, qr_rs_ecc($data, $eccCodewords));
    $matrix = array_fill(0, $size, array_fill(0, $size, false));
    $reserved = array_fill(0, $size, array_fill(0, $size, false));

    $set = function ($row, $col, $dark, $isFunction = true) use (&$matrix, &$reserved, $size) {
        if ($row < 0 || $row >= $size || $col < 0 || $col >= $size) {
            return;
        }
        $matrix[$row][$col] = (bool)$dark;
        if ($isFunction) {
            $reserved[$row][$col] = true;
        }
    };

    $drawFinder = function ($row, $col) use (&$set) {
        for ($r = -1; $r <= 7; $r++) {
            for ($c = -1; $c <= 7; $c++) {
                $rr = $row + $r;
                $cc = $col + $c;
                $dark = $r >= 0 && $r <= 6 && $c >= 0 && $c <= 6
                    && ($r === 0 || $r === 6 || $c === 0 || $c === 6 || ($r >= 2 && $r <= 4 && $c >= 2 && $c <= 4));
                $set($rr, $cc, $dark);
            }
        }
    };

    $drawFinder(0, 0);
    $drawFinder(0, $size - 7);
    $drawFinder($size - 7, 0);

    for ($i = 8; $i < $size - 8; $i++) {
        $set(6, $i, $i % 2 === 0);
        $set($i, 6, $i % 2 === 0);
    }

    $center = 22;
    for ($r = -2; $r <= 2; $r++) {
        for ($c = -2; $c <= 2; $c++) {
            $dark = max(abs($r), abs($c)) !== 1;
            $set($center + $r, $center + $c, $dark);
        }
    }

    $set(4 * $version + 9, 8, true);
    for ($i = 0; $i < 9; $i++) {
        if ($i !== 6) {
            $set(8, $i, false);
            $set($i, 8, false);
        }
    }
    for ($i = 0; $i < 8; $i++) {
        $set(8, $size - 1 - $i, false);
        $set($size - 1 - $i, 8, false);
    }

    $dataBits = [];
    foreach ($codewords as $codeword) {
        qr_append_bits($dataBits, $codeword, 8);
    }

    $bitIndex = 0;
    $upward = true;
    for ($col = $size - 1; $col >= 1; $col -= 2) {
        if ($col === 6) {
            $col--;
        }
        for ($i = 0; $i < $size; $i++) {
            $row = $upward ? $size - 1 - $i : $i;
            for ($offset = 0; $offset < 2; $offset++) {
                $currentCol = $col - $offset;
                if ($reserved[$row][$currentCol]) {
                    continue;
                }
                $bit = $dataBits[$bitIndex] ?? 0;
                $masked = $bit ^ (($row + $currentCol) % 2 === 0 ? 1 : 0);
                $matrix[$row][$currentCol] = (bool)$masked;
                $bitIndex++;
            }
        }
        $upward = !$upward;
    }

    $format = 0x77C4;
    $getFormatBit = function ($index) use ($format) {
        return (($format >> $index) & 1) === 1;
    };
    for ($i = 0; $i <= 5; $i++) {
        $set($i, 8, $getFormatBit($i));
    }
    $set(7, 8, $getFormatBit(6));
    $set(8, 8, $getFormatBit(7));
    $set(8, 7, $getFormatBit(8));
    for ($i = 9; $i < 15; $i++) {
        $set(8, 14 - $i, $getFormatBit($i));
    }
    for ($i = 0; $i < 8; $i++) {
        $set(8, $size - 1 - $i, $getFormatBit($i));
    }
    for ($i = 8; $i < 15; $i++) {
        $set($size - 15 + $i, 8, $getFormatBit($i));
    }
    $set($size - 8, 8, true);

    return $matrix;
}

function pdf_qr_code(&$content, $text, $x, $y, $moduleSize = 3) {
    $matrix = qr_make_matrix($text);
    $size = count($matrix);
    $quiet = 4;
    $totalSize = ($size + ($quiet * 2)) * $moduleSize;

    $content .= "q 1 1 1 rg {$x} {$y} {$totalSize} {$totalSize} re f Q\n";
    $content .= "q 0 0 0 rg\n";
    for ($row = 0; $row < $size; $row++) {
        for ($col = 0; $col < $size; $col++) {
            if (!$matrix[$row][$col]) {
                continue;
            }
            $drawX = $x + (($col + $quiet) * $moduleSize);
            $drawY = $y + (($size - 1 - $row + $quiet) * $moduleSize);
            $content .= "{$drawX} {$drawY} {$moduleSize} {$moduleSize} re f\n";
        }
    }
    $content .= "Q\n";
}

function build_recommendation_pdf($cashier, $referenceNumber) {
    $pageWidth = 595;
    $pageHeight = 842;
    $marginX = 64;
    $companyName = 'DELIGOS COMPANY';
    $companyAddress = 'P.O. Box 30200, Kitale, Kenya';
    $companyContacts = 'Phone: 0743067646 | Email: admin123@gmail.com';
    $companyMotto = 'Motto: Quality service, trusted every day.';
    $companyVision = 'Vision: To deliver reliable retail service through integrity, care, and innovation.';
    $logoPath = __DIR__ . '/assets/DELIGOS LOGO.jpg';
    $logoData = file_exists($logoPath) ? file_get_contents($logoPath) : false;
    $logoInfo = $logoData !== false ? @getimagesize($logoPath) : false;
    $hasLogo = $logoData !== false && $logoInfo && ($logoInfo['mime'] ?? '') === 'image/jpeg';

    $content = "";
    pdf_recommendation_watermark($content, $companyName, $pageWidth, $pageHeight, $hasLogo, $logoInfo);

    $content .= "q 0.12 0.20 0.29 RG 2 w 54 54 487 734 re S Q\n";
    $content .= "q 0.95 0.97 0.98 rg 55 698 485 90 re f Q\n";
    $content .= "q 0.12 0.20 0.29 RG 1.3 w 55 698 485 90 re S Q\n";

    if ($hasLogo) {
        $logoWidth = 70;
        $logoHeight = max(1, round($logoWidth * ($logoInfo[1] / $logoInfo[0])));
        $logoX = 72;
        $logoY = 708;
        $content .= "q {$logoWidth} 0 0 {$logoHeight} {$logoX} {$logoY} cm /Im1 Do Q\n";
    }

    pdf_text_line($content, $companyName, 158, 764, 18, 'F2');
    pdf_text_line($content, $companyAddress, 158, 746, 10);
    pdf_text_line($content, $companyContacts, 158, 731, 10);
    pdf_text_line($content, $companyMotto, 158, 716, 9);
    pdf_text_line($content, $companyVision, 158, 703, 8);

    $content .= "q 0.12 0.20 0.29 RG 1.5 w 64 676 m 531 676 l S Q\n";
    pdf_text_line($content, 'SYSTEM GENERATED RECOMMENDATION LETTER', 137, 652, 13, 'F2');

    $y = 610;
    pdf_text_line($content, 'Date: ' . date('F j, Y'), $marginX, $y, 11);
    $y -= 18;
    pdf_text_line($content, 'Ref No: ' . $referenceNumber, $marginX, $y, 10, 'F2');
    $y -= 34;
    pdf_text_line($content, 'To Whom It May Concern,', $marginX, $y, 11, 'F2');
    $y -= 34;

    $fullName = $cashier['full_name'] ?: $cashier['username'];
    $joinedDate = !empty($cashier['created_at']) ? date('F j, Y', strtotime($cashier['created_at'])) : 'their start date';

    pdf_paragraph(
        $content,
        "This letter is to recommend {$fullName}, who has served as a cashier at {$companyName} since {$joinedDate}.",
        $marginX,
        $y,
        11,
        17,
        72
    );
    pdf_paragraph(
        $content,
        "{$fullName} has handled cashier responsibilities including customer service, sales processing, receipt handling, and daily point of sale operations.",
        $marginX,
        $y,
        11,
        17,
        72
    );
    pdf_paragraph(
        $content,
        "Based on the company records available in this system, {$fullName} is recognized as a cashier of {$companyName}. We appreciate their contribution and recommend them for opportunities where reliability, attention to detail, and customer care are valued.",
        $marginX,
        $y,
        11,
        17,
        72
    );
    pdf_paragraph(
        $content,
        'This recommendation letter was generated automatically from the Deligos POS admin system.',
        $marginX,
        $y,
        11,
        17,
        72
    );

    $y -= 22;
    pdf_text_line($content, 'Sincerely,', $marginX, $y, 11);
    $y -= 28;
    pdf_text_line($content, $companyName, $marginX, $y, 11, 'F2');
    $y -= 18;
    pdf_text_line($content, 'Authorized Administration', $marginX, $y, 11);

    $qrText = 'v.php?r=' . $referenceNumber;
    pdf_qr_code($content, $qrText, 398, 194, 3);
    pdf_text_line($content, 'Scan to verify', 419, 178, 9, 'F2');
    pdf_text_line($content, $qrText, 390, 164, 7);

    $content .= "BT /F1 9 Tf 0.35 0.35 0.35 rg 1 0 0 1 64 76 Tm (Generated by Deligos POS System) Tj ET\n";

    $objects = [];
    $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
    $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";

    $resources = '/Font << /F1 4 0 R /F2 5 0 R >> /ExtGState << /WMLOGO << /Type /ExtGState /CA 0.12 /ca 0.12 >> /WMTEXT << /Type /ExtGState /CA 0.18 /ca 0.18 >> /NORMAL << /Type /ExtGState /CA 1 /ca 1 >> >>';
    if ($hasLogo) {
        $resources .= ' /XObject << /Im1 7 0 R >>';
    }

    $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageWidth} {$pageHeight}] /Resources << {$resources} >> /Contents 6 0 R >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";
    $objects[] = "<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream";

    if ($hasLogo) {
        $objects[] = "<< /Type /XObject /Subtype /Image /Width {$logoInfo[0]} /Height {$logoInfo[1]} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($logoData) . " >>\nstream\n{$logoData}\nendstream";
    }

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $number = $index + 1;
        $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= count($objects); $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

    return $pdf;
}

require_post_csrf();

$cashierId = validate_int($_POST['id'] ?? null, 1) ?? 0;
$stmt = $pdo->prepare("SELECT id, username, full_name, role, created_at FROM users WHERE id = ? AND role = 'cashier'");
$stmt->execute([$cashierId]);
$cashier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cashier) {
    http_response_code(404);
    echo 'Cashier not found.';
    exit;
}

$fileSafeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', $cashier['full_name'] ?: $cashier['username']);
$referenceNumber = create_recommendation_letter_record($pdo, $cashier, (int)($_SESSION['user_id'] ?? 0));
$pdf = build_recommendation_pdf($cashier, $referenceNumber);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="recommendation_' . $fileSafeName . '_' . $referenceNumber . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');
echo $pdf;
exit;
