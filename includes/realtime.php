<?php

function mark_user_online(PDO $pdo, ?string $page = null): void
{
    if (!isset($_SESSION['tenant_id'], $_SESSION['user_id'])) {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO user_presence (tenant_id, user_id, current_page, last_seen)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE current_page = VALUES(current_page), last_seen = NOW()
        ");
        $stmt->execute([
            (int) $_SESSION['tenant_id'],
            (int) $_SESSION['user_id'],
            $page,
        ]);
    } catch (PDOException $e) {
        return;
    }
}

function log_activity(PDO $pdo, string $action, string $description = ''): void
{
    if (!isset($_SESSION['tenant_id'])) {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO portal_activity (tenant_id, user_id, action, description)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            (int) $_SESSION['tenant_id'],
            isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
            $action,
            $description,
        ]);
    } catch (PDOException $e) {
        return;
    }
}

function notify_user(PDO $pdo, int $user_id, string $title, string $body = '', string $type = 'info'): void
{
    if (!isset($_SESSION['tenant_id'])) {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (tenant_id, user_id, title, body, type)
            SELECT users.tenant_id, users.id, ?, ?, ?
            FROM users
            WHERE users.id = ?
            AND users.tenant_id = ?
        ");
        $stmt->execute([$title, $body, $type, $user_id, (int) $_SESSION['tenant_id']]);
    } catch (PDOException $e) {
        return;
    }
}

function notify_role(PDO $pdo, string $role, string $title, string $body = '', string $type = 'info'): void
{
    if (!isset($_SESSION['tenant_id'])) {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (tenant_id, role, title, body, type)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([(int) $_SESSION['tenant_id'], $role, $title, $body, $type]);
    } catch (PDOException $e) {
        return;
    }
}

function notify_enrolled_students(PDO $pdo, int $course_id, string $title, string $body = '', string $type = 'course'): void
{
    if (!isset($_SESSION['tenant_id'])) {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT course_enrollments.user_id
            FROM course_enrollments
            INNER JOIN courses
                ON courses.id = course_enrollments.course_id
                AND courses.tenant_id = course_enrollments.tenant_id
            INNER JOIN users
                ON users.id = course_enrollments.user_id
                AND users.tenant_id = course_enrollments.tenant_id
            WHERE course_enrollments.tenant_id = ?
            AND course_enrollments.course_id = ?
            AND course_enrollments.status = 'active'
            AND users.role = 'student'
            AND users.status = 'active'
        ");
        $stmt->execute([(int) $_SESSION['tenant_id'], $course_id]);

        foreach ($stmt->fetchAll() as $student) {
            notify_user($pdo, (int) $student['user_id'], $title, $body, $type);
        }
    } catch (PDOException $e) {
        return;
    }
}
