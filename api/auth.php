<?php
/**
 * Admin Authentication Endpoint
 * Mess Menu Feedback Analyzer
 */

declare(strict_types=1);
require_once __DIR__ . '/database.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? $_POST['action'] ?? 'check';

if ($action === 'login') {
    // Read JSON payload or POST fields
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $username = trim($input['username'] ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($username) || empty($password)) {
        jsonError('Please provide both username and password.', 400);
    }

    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT id, username, password_hash, full_name, email, role FROM admins WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        jsonError('Invalid username or password.', 401);
    }

    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_name'] = $admin['full_name'];
    $_SESSION['admin_role'] = $admin['role'];

    // Update last login
    $upd = $pdo->prepare("UPDATE admins SET last_login = NOW() WHERE id = ?");
    $upd->execute([$admin['id']]);

    // Generate serverless token for Vercel persistence
    $authToken = generateAdminToken($admin);

    // Set 7-day auth cookie
    setcookie('admin_auth_token', $authToken, [
        'expires'  => time() + (86400 * 7),
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    jsonResponse([
        'success'    => true,
        'message'    => 'Login successful. Welcome back!',
        'token'      => $authToken,
        'csrf_token' => getCsrfToken(),
        'user'       => [
            'id'       => $admin['id'],
            'username' => $admin['username'],
            'name'     => $admin['full_name'],
            'role'     => $admin['role']
        ]
    ]);
}

if ($action === 'check') {
    // Check session first, then fallback to token verification for serverless
    $userPayload = null;
    if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
        $userPayload = [
            'id'       => $_SESSION['admin_id'],
            'username' => $_SESSION['admin_username'],
            'name'     => $_SESSION['admin_name'],
            'role'     => $_SESSION['admin_role']
        ];
    } else {
        $tokenData = verifyAdminToken(getRequestAdminToken());
        if ($tokenData) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_id']        = $tokenData['id'];
            $_SESSION['admin_username']  = $tokenData['username'];
            $_SESSION['admin_name']      = $tokenData['name'];
            $_SESSION['admin_role']      = $tokenData['role'];
            $userPayload = $tokenData;
        }
    }

    if ($userPayload) {
        jsonResponse([
            'authenticated' => true,
            'csrf_token'    => getCsrfToken(),
            'user'          => $userPayload
        ]);
    } else {
        jsonResponse([
            'authenticated' => false,
            'message'       => 'Session not authenticated.'
        ], 401);
    }
}

if ($action === 'logout') {
    $_SESSION = [];
    setcookie('admin_auth_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();

    jsonResponse([
        'success' => true,
        'message' => 'Logged out successfully.'
    ]);
}

if ($action === 'csrf') {
    jsonResponse([
        'csrf_token' => getCsrfToken()
    ]);
}

jsonError('Invalid action specified.', 400);
