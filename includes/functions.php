<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function apiStart(): void
{
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_set_cookie_params([
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]);
        session_start();
    }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
}

function apiRespond(bool $success, string $message = '', array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function apiBody(): array
{
    if (!empty($_POST)) {
        return $_POST;
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $body = json_decode($raw, true);
    return is_array($body) ? $body : [];
}

function apiCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function apiRequireCsrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($provided) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $provided)) {
        apiRespond(false, 'Your session token expired. Refresh the page and try again.', [], 403);
    }
}

function apiCurrentUser(PDO $pdo): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT id, name, email, student_id, phone, role, status FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id']);
        return null;
    }
    return $user;
}

function apiRequireUser(PDO $pdo): array
{
    $user = apiCurrentUser($pdo);
    if (!$user) {
        apiRespond(false, 'Please log in to continue.', [], 401);
    }
    return $user;
}

function apiRequireAdmin(PDO $pdo): array
{
    $user = apiRequireUser($pdo);
    if ($user['role'] !== 'admin') {
        apiRespond(false, 'Administrator access required.', [], 403);
    }
    return $user;
}

function apiNotify(PDO $pdo, int $userId, string $title, string $message, string $type = 'info'): void
{
    $stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())');
    $stmt->execute([$userId, $title, $message, $type]);
}

function apiReportId(PDO $pdo, string $prefix): string
{
    $date = date('Ymd');
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(report_id, CHAR(45), -1) AS UNSIGNED)), 0) FROM items WHERE report_id LIKE ?');
    $stmt->execute([$prefix . '-' . $date . '-%']);
    return $prefix . '-' . $date . '-' . str_pad((string) ((int) $stmt->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
}

function apiUploadImage(string $field = 'image'): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Image must be JPG, PNG, or WEBP and no larger than 5 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Image must be a valid JPG, PNG, or WEBP file.');
    }
    $directory = dirname(__DIR__) . '/uploads/items';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Image storage is unavailable.');
    }
    $filename = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) {
        throw new RuntimeException('Unable to save the uploaded image.');
    }
    return 'uploads/items/' . $filename;
}

function apiMatchScore(array $left, array $right): int
{
    $leftName = (string) ($left['name'] ?? '');
    $rightName = (string) ($right['name'] ?? '');
    $leftName = function_exists('mb_strtolower') ? mb_strtolower($leftName) : strtolower($leftName);
    $rightName = function_exists('mb_strtolower') ? mb_strtolower($rightName) : strtolower($rightName);
    similar_text($leftName, $rightName, $namePercent);
    $checks = [
        [(int) ($left['category_id'] ?? 0) > 0 && (int) $left['category_id'] === (int) ($right['category_id'] ?? 0), 20],
        [$namePercent >= 45, 20],
        [(int) ($left['location_id'] ?? 0) > 0 && (int) $left['location_id'] === (int) ($right['location_id'] ?? 0), 20],
        [!empty($left['item_date']) && !empty($right['item_date']) && abs((strtotime((string) $left['item_date']) ?: 0) - (strtotime((string) $right['item_date']) ?: 0)) <= 7 * 86400, 15],
        [strcasecmp(trim((string) ($left['color'] ?? '')), trim((string) ($right['color'] ?? ''))) === 0 && trim((string) ($left['color'] ?? '')) !== '', 15],
        [strcasecmp(trim((string) ($left['brand'] ?? '')), trim((string) ($right['brand'] ?? ''))) === 0 && trim((string) ($left['brand'] ?? '')) !== '', 10],
    ];
    $score = 0;
    foreach ($checks as [$matches, $weight]) {
        if ($matches) {
            $score += $weight;
        }
    }
    return $score;
}

function apiRun(callable $handler): void
{
    apiStart();
    try {
        $handler(getDb());
    } catch (InvalidArgumentException $error) {
        apiRespond(false, $error->getMessage(), [], 422);
    } catch (Throwable $error) {
        error_log('CampusFind API error: ' . $error->getMessage());
        apiRespond(false, 'The request could not be completed. Please try again.', [], 500);
    }
}
