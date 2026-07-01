<?php
$ref = strtoupper(trim($_GET['r'] ?? ''));
$query = $ref !== '' ? '?ref=' . urlencode($ref) : '';
header('Location: verify_recommendation.php' . $query);
exit;
