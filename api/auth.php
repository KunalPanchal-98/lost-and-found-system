<?php
require_once __DIR__ . '/../includes/functions.php';

apiRun(function (PDO $pdo): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $action = $_GET['action'] ?? 'session';

    if ($method === 'GET' && $action === 'session') {
        apiRespond(true, '', ['user' => apiCurrentUser($pdo), 'csrf_token' => apiCsrfToken()]);
    }
    if ($method !== 'POST') {
        apiRespond(false, 'Method not allowed.', [], 405);
    }
    apiRequireCsrf();
    $data = apiBody();

    if ($action === 'register') {
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $studentId = trim((string) ($data['student_id'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $password = (string) ($data['password'] ?? '');
        $confirmPassword = (string) ($data['confirm_password'] ?? '');
        $valid = $name !== '' && strlen($name) <= 255
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 255
            && $studentId !== '' && strlen($studentId) <= 100
            && $phone !== '' && strlen($phone) <= 50
            && strlen($password) >= 8;
        if (!$valid) {
            apiRespond(false, 'Provide your name, valid email, student ID, phone, and a password of at least 8 characters.', [], 422);
        }
        if (!hash_equals($password, $confirmPassword)) {
            apiRespond(false, 'Passwords do not match.', [], 422);
        }
        $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $check->execute([$email]);
        if ($check->fetch()) {
            apiRespond(false, 'An account with this email already exists.', [], 409);
        }
        $insert = $pdo->prepare('INSERT INTO users (name, email, student_id, phone, password, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, "user", "active", NOW(), NOW())');
        $insert->execute([$name, $email, $studentId, $phone, password_hash($password, PASSWORD_DEFAULT)]);
        apiRespond(true, 'Registration complete. You can now log in.');
    }

    if ($action === 'login') {
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');
        $stmt = $pdo->prepare('SELECT id, name, email, student_id, phone, role, status, password FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $storedPassword = (string) ($user['password'] ?? '');
        $legacyHash = $storedPassword !== '' && password_get_info($storedPassword)['algo'] === 0;
        $legacyMatch = $legacyHash && hash_equals($storedPassword, $password);
        if (!$user || $user['status'] !== 'active' || (!password_verify($password, $storedPassword) && !$legacyMatch)) {
            apiRespond(false, 'Invalid email or password.', [], 401);
        }
        if ($legacyMatch) {
            $update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        unset($user['password'], $user['status']);
        apiRespond(true, 'Welcome back, ' . $user['name'] . '.', ['user' => $user, 'csrf_token' => apiCsrfToken()]);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
        apiRespond(true, 'You have been logged out.');
    }

    if ($action === 'profile') {
        $user = apiRequireUser($pdo);
        $name = trim((string) ($data['name'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $studentId = trim((string) ($data['student_id'] ?? ''));
        $valid = $name !== '' && strlen($name) <= 255
            && $phone !== '' && strlen($phone) <= 50
            && $studentId !== '' && strlen($studentId) <= 100;
        if (!$valid) {
            apiRespond(false, 'Name, phone, and student ID are required.', [], 422);
        }
        $update = $pdo->prepare('UPDATE users SET name = ?, phone = ?, student_id = ?, updated_at = NOW() WHERE id = ?');
        $update->execute([$name, $phone, $studentId, $user['id']]);
        $user['name'] = $name;
        $user['phone'] = $phone;
        $user['student_id'] = $studentId;
        apiRespond(true, 'Profile updated.', ['user' => $user]);
    }

    apiRespond(false, 'Unsupported action.', [], 400);
});
