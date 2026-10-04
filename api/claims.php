<?php
require_once __DIR__ . '/../includes/functions.php';

apiRun(function (PDO $pdo): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? 'list';
    if ($method === 'GET' && $action === 'available') {
        $user = apiRequireUser($pdo);
        $sql = 'SELECT i.id, i.name, i.type, i.status, i.item_date, c.name AS category_name, l.name AS location_name
            FROM items i
            LEFT JOIN categories c ON c.id = i.category_id
            LEFT JOIN locations l ON l.id = i.location_id
            WHERE i.user_id <> ? AND i.status NOT IN ("Returned", "Rejected", "Matched", "Closed")
            ORDER BY i.created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user['id']]);
        apiRespond(true, '', ['items' => $stmt->fetchAll()]);
    }
    if ($method === 'GET' && $action === 'list') {
        $user = apiRequireUser($pdo);
        $sql = 'SELECT c.id, c.item_id, c.verification_details, c.status, c.admin_note, c.created_at, i.name AS item_name, i.type AS item_type
            FROM claims c
            JOIN items i ON i.id = c.item_id
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user['id']]);
        apiRespond(true, '', ['claims' => $stmt->fetchAll()]);
    }
    if ($method === 'POST' && $action === 'create') {
        apiRequireCsrf();
        $user = apiRequireUser($pdo);
        $data = apiBody();
        $itemId = filter_var($data['item_id'] ?? null, FILTER_VALIDATE_INT);
        $unique = trim((string) ($data['unique_feature'] ?? ''));
        $time = trim((string) ($data['approximate_time'] ?? ''));
        $location = trim((string) ($data['approximate_location'] ?? ''));
        $additional = trim((string) ($data['additional_details'] ?? ''));
        $valid = $itemId !== false && $itemId > 0
            && $unique !== '' && $time !== '' && $location !== ''
            && strlen($unique) <= 5000 && strlen($time) <= 500 && strlen($location) <= 500 && strlen($additional) <= 5000;
        if (!$valid) {
            apiRespond(false, 'Provide the unique feature, approximate time, and approximate location.', [], 422);
        }
        $itemStmt = $pdo->prepare('SELECT id, user_id, name, type, status FROM items WHERE id = ? LIMIT 1');
        $itemStmt->execute([$itemId]);
        $item = $itemStmt->fetch();
        $claimable = $item && !in_array($item['status'], ['Returned', 'Rejected', 'Matched', 'Closed'], true)
            && (int) $item['user_id'] !== (int) $user['id'];
        if (!$claimable) {
            apiRespond(false, 'This item cannot be claimed.', [], 422);
        }
        $duplicate = $pdo->prepare('SELECT id FROM claims WHERE item_id = ? AND user_id = ? LIMIT 1');
        $duplicate->execute([$itemId, $user['id']]);
        if ($duplicate->fetch()) {
            apiRespond(false, 'You have already submitted a claim for this item.', [], 409);
        }
        $details = "Unique feature: {$unique}\nApproximate time: {$time}\nApproximate location: {$location}\nAdditional details: {$additional}";
        $pdo->prepare('INSERT INTO claims (item_id, user_id, verification_details, status, admin_note, created_at, updated_at) VALUES (?, ?, ?, "Pending", "", NOW(), NOW())')->execute([$itemId, $user['id'], $details]);
        $pdo->prepare('UPDATE items SET status = "Claim Pending", updated_at = NOW() WHERE id = ? AND status NOT IN ("Returned", "Rejected")')->execute([$itemId]);
        apiNotify($pdo, (int) $user['id'], 'Claim submitted', 'Your claim for "' . $item['name'] . '" is awaiting review.', 'info');
        apiNotify($pdo, (int) $item['user_id'], 'Claim received', 'A claim has been submitted for your "' . $item['name'] . '" report.', 'info');
        apiRespond(true, 'Claim submitted successfully.');
    }
    apiRespond(false, 'Unsupported action.', [], 405);
});
