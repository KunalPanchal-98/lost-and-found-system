<?php
require_once __DIR__ . '/../includes/functions.php';

apiRun(function (PDO $pdo): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? '';
    $statuses = ['Reported', 'Under Review', 'Matched', 'Verified', 'Claim Pending', 'Returned', 'Closed', 'Rejected'];

    if ($method === 'GET') {
        apiRequireAdmin($pdo);
        if ($action === 'dashboard') {
            apiRespond(true, '', [
                'total_users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
                'lost_items' => (int) $pdo->query('SELECT COUNT(*) FROM items WHERE type = "lost"')->fetchColumn(),
                'found_items' => (int) $pdo->query('SELECT COUNT(*) FROM items WHERE type = "found"')->fetchColumn(),
                'recovered_items' => (int) $pdo->query('SELECT COUNT(*) FROM items WHERE status = "Returned"')->fetchColumn(),
                'pending_claims' => (int) $pdo->query('SELECT COUNT(*) FROM claims WHERE status = "Pending"')->fetchColumn(),
                'pending_verification' => (int) $pdo->query('SELECT COUNT(*) FROM items WHERE status IN ("Reported", "Under Review")')->fetchColumn(),
            ]);
        }
        $queries = [
            'users' => 'SELECT id, name, email, student_id, phone, role, status, created_at FROM users ORDER BY created_at DESC',
            'items' => 'SELECT i.id, i.report_id, i.user_id, i.type, i.name, i.status, i.item_date, i.created_at, u.name AS user_name FROM items i LEFT JOIN users u ON u.id = i.user_id ORDER BY i.created_at DESC',
            'claims' => 'SELECT c.id, c.item_id, c.user_id, c.verification_details, c.status, c.admin_note, c.created_at, i.name AS item_name, i.type AS item_type, i.user_id AS item_owner_id, u.name AS claimant_name FROM claims c JOIN items i ON i.id = c.item_id JOIN users u ON u.id = c.user_id ORDER BY c.created_at DESC',
            'categories' => 'SELECT id, name, description, created_at FROM categories ORDER BY name',
            'locations' => 'SELECT id, name, description, created_at FROM locations ORDER BY name',
            'reports' => 'SELECT r.id, r.item_id, r.reported_by, r.reason, r.description, r.status, r.created_at, i.name AS item_name, u.name AS reporter_name FROM reports r LEFT JOIN items i ON i.id = r.item_id LEFT JOIN users u ON u.id = r.reported_by ORDER BY r.created_at DESC',
        ];
        if (!isset($queries[$action])) {
            apiRespond(false, 'Unsupported action.', [], 400);
        }
        $key = $action;
        apiRespond(true, '', [$key => $pdo->query($queries[$action])->fetchAll()]);
    }

    if ($method !== 'POST') {
        apiRespond(false, 'Method not allowed.', [], 405);
    }
    apiRequireCsrf();
    apiRequireAdmin($pdo);
    $data = apiBody();
    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'user-status') {
        $status = (string) ($data['status'] ?? '');
        if (!$id || !in_array($status, ['active', 'inactive'], true)) {
            apiRespond(false, 'Invalid user status update.', [], 422);
        }
        if ((int) $_SESSION['user_id'] === $id && $status !== 'active') {
            apiRespond(false, 'You cannot deactivate your own account.', [], 422);
        }
        $stmt = $pdo->prepare('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$status, $id]);
        apiRespond(true, 'User status updated.');
    }

    if ($action === 'item-status') {
        $status = (string) ($data['status'] ?? '');
        if (!$id || !in_array($status, $statuses, true)) {
            apiRespond(false, 'Invalid item status update.', [], 422);
        }
        $stmt = $pdo->prepare('SELECT user_id, name FROM items WHERE id = ?');
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        if (!$item) {
            apiRespond(false, 'Item not found.', [], 404);
        }
        $pdo->prepare('UPDATE items SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $id]);
        $title = $status === 'Returned' ? 'Item returned' : 'Item status updated';
        apiNotify($pdo, (int) $item['user_id'], $title, 'Your "' . $item['name'] . '" report is now ' . $status . '.', $status === 'Returned' ? 'success' : 'info');
        apiRespond(true, 'Item status updated.');
    }

    if ($action === 'item-delete') {
        if (!$id) {
            apiRespond(false, 'Invalid item.', [], 422);
        }
        $stmt = $pdo->prepare('DELETE FROM items WHERE id = ?');
        $stmt->execute([$id]);
        apiRespond(true, 'Item deleted.');
    }

    if ($action === 'claim-status') {
        $status = (string) ($data['status'] ?? '');
        if (!$id || !in_array($status, ['Pending', 'Under Review', 'Approved', 'Rejected', 'Completed'], true)) {
            apiRespond(false, 'Invalid claim status.', [], 422);
        }
        $stmt = $pdo->prepare('SELECT c.item_id, c.user_id AS claimant_id, i.user_id AS owner_id, i.name AS item_name FROM claims c JOIN items i ON i.id = c.item_id WHERE c.id = ?');
        $stmt->execute([$id]);
        $claim = $stmt->fetch();
        if (!$claim) {
            apiRespond(false, 'Claim not found.', [], 404);
        }
        $itemStatus = 'Claim Pending';
        if ($status === 'Under Review') {
            $itemStatus = 'Under Review';
        } elseif ($status === 'Approved') {
            $itemStatus = 'Matched';
        } elseif ($status === 'Rejected') {
            $activeClaims = $pdo->prepare('SELECT COUNT(*) FROM claims WHERE item_id = ? AND id <> ? AND status IN ("Pending", "Under Review", "Approved")');
            $activeClaims->execute([$claim['item_id'], $id]);
            $itemStatus = (int) $activeClaims->fetchColumn() > 0 ? 'Claim Pending' : 'Reported';
        } elseif ($status === 'Completed') {
            $itemStatus = 'Returned';
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE claims SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $id]);
            $pdo->prepare('UPDATE items SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$itemStatus, $claim['item_id']]);
            $noticeType = $status === 'Rejected' ? 'danger' : (in_array($status, ['Approved', 'Completed'], true) ? 'success' : 'info');
            $noticeTitle = $status === 'Approved' ? 'Claim approved' : ($status === 'Rejected' ? 'Claim rejected' : ($status === 'Completed' ? 'Item returned' : 'Claim status update'));
            $claimantMessage = $status === 'Completed'
                ? 'Your claim is complete. "' . $claim['item_name'] . '" has been marked returned.'
                : 'Your claim for "' . $claim['item_name'] . '" is now ' . $status . '.';
            apiNotify($pdo, (int) $claim['claimant_id'], $noticeTitle, $claimantMessage, $noticeType);
            if ((int) $claim['owner_id'] !== (int) $claim['claimant_id']) {
                $ownerMessage = $status === 'Completed'
                    ? '"' . $claim['item_name'] . '" has been marked returned.'
                    : 'A claim for "' . $claim['item_name'] . '" is now ' . $status . '.';
                apiNotify($pdo, (int) $claim['owner_id'], $noticeTitle, $ownerMessage, $noticeType);
            }
            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
        apiRespond(true, 'Claim status updated.');
    }

    if ($action === 'category-create' || $action === 'location-create') {
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        if ($name === '' || strlen($name) > 120 || strlen($description) > 255) {
            apiRespond(false, 'A name is required.', [], 422);
        }
        $table = $action === 'category-create' ? 'categories' : 'locations';
        $stmt = $pdo->prepare("INSERT INTO {$table} (name, description, created_at) VALUES (?, ?, NOW())");
        try {
            $stmt->execute([$name, $description]);
        } catch (PDOException $error) {
            if ((string) $error->getCode() === '23000') {
                apiRespond(false, 'That name already exists.', [], 409);
            }
            throw $error;
        }
        apiRespond(true, ucfirst($action === 'category-create' ? 'category' : 'location') . ' created.');
    }

    if ($action === 'category-update' || $action === 'location-update') {
        $name = trim((string) ($data['name'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        if (!$id || $name === '' || strlen($name) > 120 || strlen($description) > 255) {
            apiRespond(false, 'A valid record and name are required.', [], 422);
        }
        $table = $action === 'category-update' ? 'categories' : 'locations';
        try {
            $stmt = $pdo->prepare("UPDATE {$table} SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $description, $id]);
        } catch (PDOException $error) {
            if ((string) $error->getCode() === '23000') {
                apiRespond(false, 'That name already exists or is in use.', [], 409);
            }
            throw $error;
        }
        apiRespond(true, 'Record updated.');
    }

    if ($action === 'category-delete' || $action === 'location-delete') {
        if (!$id) {
            apiRespond(false, 'Invalid record.', [], 422);
        }
        $table = $action === 'category-delete' ? 'categories' : 'locations';
        try {
            $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
            $stmt->execute([$id]);
        } catch (PDOException $error) {
            if ((string) $error->getCode() === '23000') {
                apiRespond(false, 'This record is in use and cannot be deleted.', [], 409);
            }
            throw $error;
        }
        apiRespond(true, 'Record deleted.');
    }

    if ($action === 'report-status') {
        $status = (string) ($data['status'] ?? '');
        if (!$id || !in_array($status, ['Pending', 'Under Review', 'Verified', 'Approved', 'Rejected', 'Closed'], true)) {
            apiRespond(false, 'Invalid report status.', [], 422);
        }
        $stmt = $pdo->prepare('SELECT r.reported_by, r.item_id, i.name AS item_name FROM reports r JOIN items i ON i.id = r.item_id WHERE r.id = ?');
        $stmt->execute([$id]);
        $report = $stmt->fetch();
        if (!$report) {
            apiRespond(false, 'Report not found.', [], 404);
        }
        $pdo->prepare('UPDATE reports SET status = ? WHERE id = ?')->execute([$status, $id]);
        if ($status === 'Approved' || $status === 'Verified') {
            $pdo->prepare('UPDATE items SET status = "Verified", updated_at = NOW() WHERE id = ?')->execute([$report['item_id']]);
        } elseif ($status === 'Rejected') {
            $pdo->prepare('UPDATE items SET status = "Rejected", updated_at = NOW() WHERE id = ?')->execute([$report['item_id']]);
        } elseif ($status === 'Under Review') {
            $pdo->prepare('UPDATE items SET status = "Under Review", updated_at = NOW() WHERE id = ?')->execute([$report['item_id']]);
        } elseif ($status === 'Closed') {
            $pdo->prepare('UPDATE items SET status = "Closed", updated_at = NOW() WHERE id = ?')->execute([$report['item_id']]);
        }
        apiNotify($pdo, (int) $report['reported_by'], 'Report status updated', 'Your report for "' . $report['item_name'] . '" is now ' . $status . '.', $status === 'Rejected' ? 'danger' : 'info');
        apiRespond(true, 'Report status updated.');
    }

    if ($action === 'report-delete') {
        if (!$id) {
            apiRespond(false, 'Invalid report.', [], 422);
        }
        $stmt = $pdo->prepare('DELETE FROM reports WHERE id = ?');
        $stmt->execute([$id]);
        apiRespond(true, 'Report deleted.');
    }

    apiRespond(false, 'Unsupported action.', [], 400);
});
