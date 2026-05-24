<?php
require_once "../includes/session.php";
require_once "../config/db.php";
require_once "../includes/realtime.php";

require_login();

header('Content-Type: application/json');

$page = trim($_POST['page'] ?? $_SERVER['HTTP_REFERER'] ?? '');
mark_user_online($pdo, substr($page, 0, 255));

echo json_encode(['ok' => true]);
