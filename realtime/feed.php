<?php
require_once "../includes/session.php";
require_once "../config/db.php";
require_once "../includes/realtime.php";

require_login();
mark_user_online($pdo, substr($_SERVER['HTTP_REFERER'] ?? '', 0, 255));

header('Content-Type: application/json');

$tenant_id = (int) $_SESSION['tenant_id'];
$user_id = (int) $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    $stmt = $pdo->prepare("
        SELECT users.name, users.role, user_presence.current_page, user_presence.last_seen
        FROM user_presence
        INNER JOIN users
            ON users.id = user_presence.user_id
            AND users.tenant_id = user_presence.tenant_id
        WHERE user_presence.tenant_id = ?
        AND user_presence.last_seen >= (NOW() - INTERVAL 2 MINUTE)
        ORDER BY user_presence.last_seen DESC
        LIMIT 12
    ");
    $stmt->execute([$tenant_id]);
    $online_users = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT id, title, body, type, created_at
        FROM notifications
        WHERE tenant_id = ?
        AND (user_id = ? OR role = ?)
        ORDER BY created_at DESC
        LIMIT 8
    ");
    $stmt->execute([$tenant_id, $user_id, $role]);
    $notifications = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT portal_activity.action, portal_activity.description, portal_activity.created_at, users.name
        FROM portal_activity
        LEFT JOIN users
            ON users.id = portal_activity.user_id
            AND users.tenant_id = portal_activity.tenant_id
        WHERE portal_activity.tenant_id = ?
        ORDER BY portal_activity.created_at DESC
        LIMIT 8
    ");
    $stmt->execute([$tenant_id]);
    $activity = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT realtime_messages.body, realtime_messages.created_at, users.name AS sender_name
        FROM realtime_messages
        INNER JOIN users
            ON users.id = realtime_messages.sender_id
            AND users.tenant_id = realtime_messages.tenant_id
        WHERE realtime_messages.tenant_id = ?
        AND (realtime_messages.receiver_id IS NULL OR realtime_messages.receiver_id = ? OR realtime_messages.sender_id = ?)
        ORDER BY realtime_messages.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$tenant_id, $user_id, $user_id]);
    $messages = array_reverse($stmt->fetchAll());
} catch (PDOException $e) {
    $online_users = [];
    $notifications = [];
    $activity = [];
    $messages = [];
}

try {
    $stmt = $pdo->prepare("
        SELECT
            (SELECT COUNT(*) FROM users WHERE tenant_id = ?) AS users_count,
            (SELECT COUNT(*) FROM courses WHERE tenant_id = ?) AS courses_count,
            (SELECT COUNT(*) FROM resources WHERE tenant_id = ?) AS resources_count,
            (SELECT COUNT(*) FROM course_enrollments WHERE tenant_id = ? AND status = 'active') AS active_enrollments
    ");
    $stmt->execute([$tenant_id, $tenant_id, $tenant_id, $tenant_id]);
    $stats = $stmt->fetch();
} catch (PDOException $e) {
    $stats = [];
}

echo json_encode([
    'ok' => true,
    'onlineUsers' => $online_users,
    'notifications' => $notifications,
    'activity' => $activity,
    'messages' => $messages,
    'stats' => $stats,
]);
