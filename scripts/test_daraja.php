<?php
require_once __DIR__ . '/../includes/daraja.php';

$cases = [
    ['input' => '0712345678', 'expected' => '254712345678'],
    ['input' => '+254712345678', 'expected' => '254712345678'],
    ['input' => '254712345678', 'expected' => '254712345678'],
];

foreach ($cases as $case) {
    $actual = normalize_phone_number($case['input']);
    if ($actual !== $case['expected']) {
        fwrite(STDERR, "Expected {$case['expected']} but got $actual\n");
        exit(1);
    }
}

if (!function_exists('daraja_request_token')) {
    fwrite(STDERR, "Daraja token helper is not available\n");
    exit(1);
}

echo "Daraja helper tests passed\n";
