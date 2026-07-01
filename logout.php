<?php
require_once 'includes/security.php';

start_secure_session();
session_destroy();
header('Location: index.php');
exit;
