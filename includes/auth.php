<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'cashier';

// Optional: restrict admin pages
if (isset($required_role) && $required_role === 'admin' && $role !== 'admin') {
    header('Location: ../dashboard.php');
    exit;

}

