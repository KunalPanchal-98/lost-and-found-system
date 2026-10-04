<?php
require_once __DIR__ . '/../includes/functions.php';

apiRun(function (PDO $pdo): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? 'list';
    $user = apiRequireUser($pdo);
    if ($method === 'GET' && $action === 'list') {
        $stmt = $pdo->prepare('SELECT id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC');
        $stmt->execute([$user['id']]);
        $notifications = $stmt->fetchAll();
        $unread = count(array_filter($notifications, static fn(array $note): bool => !(int) $note['is_read']));
        apiRespond(true, '', ['notifications' => $notifications, 'unread_count' => $unread]);
    }
    if ($method === 'POST' && $action === 'read') {
        apiRequireCsrf();
        $data = apiBody();
        if (!empty($data['id'])) {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
            $stmt->execute([(int) $data['id'], $user['id']]);
        } else {
            $stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?');
            $stmt->execute([$user['id']]);
        }
        apiRespond(true, 'Notifications marked as read.');
    }
    apiRespond(false, 'Unsupported action.', [], 405);
});
