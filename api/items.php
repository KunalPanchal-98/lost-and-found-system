<?php
require_once __DIR__ . '/../includes/functions.php';

apiRun(function (PDO $pdo): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? 'list';
    $base = 'SELECT i.*, c.name AS category_name, l.name AS location_name FROM items i LEFT JOIN categories c ON c.id = i.category_id LEFT JOIN locations l ON l.id = i.location_id';

    if ($method === 'GET' && $action === 'options') {
        apiRespond(true, '', [
            'categories' => $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(),
            'locations' => $pdo->query('SELECT id, name FROM locations ORDER BY name')->fetchAll(),
        ]);
    }
    if ($method === 'GET' && $action === 'home') {
        $stats = [
            'total_items' => (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn(),
            'recovered_items' => (int) $pdo->query('SELECT COUNT(*) FROM items WHERE status = "Returned"')->fetchColumn(),
            'active_claims' => (int) $pdo->query('SELECT COUNT(*) FROM claims WHERE status IN ("Pending", "Under Review", "Approved")')->fetchColumn(),
            'total_users' => (int) $pdo->query('SELECT COUNT(*) FROM users WHERE status = "active"')->fetchColumn(),
        ];
        $items = $pdo->query($base . ' ORDER BY i.created_at DESC LIMIT 6')->fetchAll();
        foreach ($items as &$item) {
            unset($item['user_id']);
        }
        unset($item);
        apiRespond(true, '', ['stats' => $stats, 'items' => $items]);
    }
    if ($method === 'GET' && $action === 'dashboard') {
        $user = apiRequireUser($pdo);
        $statsStmt = $pdo->prepare('SELECT SUM(type = "lost") AS lost_count, SUM(type = "found") AS found_count, (SELECT COUNT(*) FROM claims WHERE user_id = ? AND status IN ("Pending", "Under Review", "Approved")) AS active_claims, SUM(status = "Returned") AS recoveries FROM items WHERE user_id = ?');
        $statsStmt->execute([$user['id'], $user['id']]);
        $stats = $statsStmt->fetch();
        $reportsStmt = $pdo->prepare($base . ' WHERE i.user_id = ? ORDER BY i.created_at DESC LIMIT 5');
        $reportsStmt->execute([$user['id']]);
        $notificationsStmt = $pdo->prepare('SELECT id, title, message, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
        $notificationsStmt->execute([$user['id']]);
        $reports = $reportsStmt->fetchAll();
        $possibleMatches = [];
        foreach ($reports as $ownedItem) {
            $opposite = $ownedItem['type'] === 'lost' ? 'found' : 'lost';
            $matchStmt = $pdo->prepare($base . ' WHERE i.type = ? AND i.status NOT IN ("Rejected", "Returned") AND i.user_id <> ?');
            $matchStmt->execute([$opposite, $user['id']]);
            foreach ($matchStmt->fetchAll() as $candidate) {
                $score = apiMatchScore($ownedItem, $candidate);
                if ($score >= 35) {
                    unset($candidate['user_id']);
                    $possibleMatches[] = ['item' => $candidate, 'score' => $score];
                }
            }
        }
        usort($possibleMatches, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        foreach ($reports as &$report) {
            unset($report['user_id']);
        }
        unset($report);
        apiRespond(true, '', ['stats' => $stats, 'reports' => $reports, 'matches' => array_slice($possibleMatches, 0, 4), 'notifications' => $notificationsStmt->fetchAll()]);
    }
    if ($method === 'GET' && $action === 'list') {
        $where = [];
        $params = [];
        foreach (['type' => 'i.type', 'status' => 'i.status', 'category_id' => 'i.category_id', 'location_id' => 'i.location_id'] as $key => $column) {
            if (isset($_GET[$key]) && $_GET[$key] !== '') {
                $where[] = $column . ' = ?';
                $params[] = $_GET[$key];
            }
        }
        if (!empty($_GET['date'])) {
            $where[] = 'i.item_date = ?';
            $params[] = $_GET['date'];
        }
        if (!empty($_GET['q'])) {
            $where[] = '(i.name LIKE ? OR i.description LIKE ? OR i.brand LIKE ? OR i.color LIKE ? OR c.name LIKE ? OR l.name LIKE ?)';
            $term = '%' . trim((string) $_GET['q']) . '%';
            array_push($params, $term, $term, $term, $term, $term, $term);
        }
        $sql = $base . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY i.created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();
        foreach ($items as &$item) {
            unset($item['user_id']);
        }
        unset($item);
        apiRespond(true, '', ['items' => $items]);
    }
    if ($method === 'GET' && $action === 'detail') {
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
        if (!$id) {
            apiRespond(false, 'Invalid item ID.', [], 422);
        }
        $stmt = $pdo->prepare($base . ' WHERE i.id = ? LIMIT 1');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) {
            apiRespond(false, 'Item not found.', [], 404);
        }
        $viewer = apiCurrentUser($pdo);
        $item['can_claim'] = !in_array($item['status'], ['Returned', 'Rejected', 'Matched', 'Closed'], true)
            && (!$viewer || (int) $item['user_id'] !== (int) $viewer['id']);
        unset($item['user_id']);
        $matchesStmt = $pdo->prepare($base . ' WHERE i.type <> ? AND i.status NOT IN ("Rejected", "Returned") AND i.id <> ? ORDER BY i.created_at DESC');
        $matchesStmt->execute([$item['type'], $id]);
        $matches = [];
        foreach ($matchesStmt->fetchAll() as $candidate) {
            $score = apiMatchScore($item, $candidate);
            if ($score >= 35) {
                unset($candidate['user_id']);
                $matches[] = ['item' => $candidate, 'score' => $score];
            }
        }
        usort($matches, static fn(array $a, array $b): int => $b['score'] <=> $a['score']);
        apiRespond(true, '', ['item' => $item, 'matches' => array_slice($matches, 0, 5)]);
    }
    if ($method === 'POST' && $action === 'create') {
        apiRequireCsrf();
        $user = apiRequireUser($pdo);
        $data = apiBody();
        $name = trim((string) ($data['name'] ?? ''));
        $type = (string) ($data['type'] ?? '');
        $categoryId = filter_var($data['category_id'] ?? null, FILTER_VALIDATE_INT);
        $locationId = filter_var($data['location_id'] ?? null, FILTER_VALIDATE_INT);
        $description = trim((string) ($data['description'] ?? ''));
        $date = (string) ($data['item_date'] ?? '');
        if ($name === '' || !in_array($type, ['lost', 'found'], true) || !$categoryId || !$locationId || $description === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4))) {
            apiRespond(false, 'Complete all required item fields using a valid date.', [], 422);
        }
        $itemTime = (string) ($data['item_time'] ?? '');
        $brand = trim((string) ($data['brand'] ?? ''));
        $color = trim((string) ($data['color'] ?? ''));
        if (strlen($name) > 255 || strlen($brand) > 120 || strlen($color) > 80 || strlen($description) > 10000 || ($itemTime !== '' && !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $itemTime))) {
            apiRespond(false, 'One or more item fields exceed the allowed length or use an invalid time.', [], 422);
        }
        $category = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
        $category->execute([$categoryId]);
        $location = $pdo->prepare('SELECT id FROM locations WHERE id = ?');
        $location->execute([$locationId]);
        if (!$category->fetch() || !$location->fetch()) {
            apiRespond(false, 'Select a valid category and campus location.', [], 422);
        }
        $image = apiUploadImage();
        $prefix = $type === 'lost' ? 'LF' : 'FD';
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO items (report_id, user_id, type, name, category_id, description, brand, color, location_id, item_date, item_time, image, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "Reported", NOW(), NOW())');
            $stmt->execute([apiReportId($pdo, $prefix), $user['id'], $type, $name, $categoryId, $description, $brand, $color, $locationId, $date, $itemTime !== '' ? $itemTime : null, $image]);
            $itemId = (int) $pdo->lastInsertId();
            $details = trim((string) ($data['identifying_details'] ?? ''));
            $pdo->prepare('INSERT INTO reports (item_id, reported_by, reason, description, status, created_at) VALUES (?, ?, ?, ?, "Pending", NOW())')->execute([$itemId, $user['id'], ucfirst($type) . ' item report', $details]);
            apiNotify($pdo, (int) $user['id'], ucfirst($type) . ' item reported', 'Your report has been submitted and is under review.', 'success');

            $opposite = $type === 'lost' ? 'found' : 'lost';
            $matchesStmt = $pdo->prepare($base . ' WHERE i.type = ? AND i.status NOT IN ("Rejected", "Returned")');
            $matchesStmt->execute([$opposite]);
            foreach ($matchesStmt->fetchAll() as $candidate) {
                $newItem = ['name' => $name, 'category_id' => $categoryId, 'location_id' => $locationId, 'item_date' => $date, 'color' => $data['color'] ?? '', 'brand' => $data['brand'] ?? ''];
                if (apiMatchScore($newItem, $candidate) >= 60) {
                    apiNotify($pdo, (int) $candidate['user_id'], 'Possible item match', 'A new report may match your "' . $candidate['name'] . '" report.', 'info');
                    apiNotify($pdo, (int) $user['id'], 'Possible item match', 'A campus report may match your "' . $name . '" item.', 'info');
                }
            }
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            if ($image !== null) {
                $imageFile = dirname(__DIR__) . '/' . $image;
                if (is_file($imageFile)) {
                    unlink($imageFile);
                }
            }
            throw $error;
        }
        apiRespond(true, ucfirst($type) . ' item report submitted.', ['item_id' => $itemId]);
    }
    apiRespond(false, 'Unsupported action.', [], 405);
});
