<?php
require_once "../includes/session.php";
require_once "../config/db.php";
require_once "../includes/realtime.php";

require_login();
verify_csrf_token();

header('Content-Type: application/json');

$tenant_id = (int) $_SESSION['tenant_id'];
$sender_id = (int) $_SESSION['user_id'];
$body = trim($_POST['body'] ?? '');

if ($body === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Message is required.']);
    exit;
}

if (strlen($body) > 500) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Message is too long.']);
    exit;
}

$stmt = $pdo->prepare("
    INSERT INTO realtime_messages (tenant_id, sender_id, body)
    VALUES (?, ?, ?)
");
$stmt->execute([$tenant_id, $sender_id, $body]);

log_activity($pdo, 'message_sent', 'A live message was sent.');

echo json_encode(['ok' => true]);
