<?php
require_once __DIR__ . '/api_bootstrap.php';

app_send_cors_headers('GET, POST, OPTIONS');

$conn = app_db();
$action = $_GET['action'] ?? '';
$cloudConfig = app_config()['cloud'] ?? [];
$maxSyncBytes = max(1024 * 1024, (int)($cloudConfig['max_sync_bytes'] ?? (16 * 1024 * 1024)));

function ensure_auth_schema($conn) {
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        user_uuid VARCHAR(64) NOT NULL UNIQUE,
        auth_token VARCHAR(128) NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS user_data (
        user_id VARCHAR(64) NOT NULL,
        settings LONGTEXT NULL,
        investigators LONGTEXT NULL,
        saves LONGTEXT NULL,
        tavern_novels LONGTEXT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $table = 'users';
    $column = 'auth_token';
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $row = app_stmt_fetch_assoc($stmt);
    if ((int)($row['n'] ?? 0) === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN auth_token VARCHAR(128) NULL");
    }
}

function sanitize_cloud_settings($settings) {
    if (!is_array($settings)) return $settings;

    if (isset($settings['api']) && is_array($settings['api'])) {
        unset($settings['api']['customApiKey'], $settings['api']['imageGenApiKey']);
        if (isset($settings['api']['apiPresets']) && is_array($settings['api']['apiPresets'])) {
            foreach ($settings['api']['apiPresets'] as &$preset) {
                if (is_array($preset)) unset($preset['key']);
            }
            unset($preset);
        }
    }

    return $settings;
}

function json_column($value) {
    if ($value === null || $value === '') return null;
    $decoded = json_decode($value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
}

function hash_auth_token($token) {
    return hash('sha256', (string)$token);
}

function require_user_token($conn, $userId) {
    $token = app_get_bearer_token();
    if ($token === '' || strlen($token) > 128) {
        app_json_response(['status' => 'error', 'message' => 'Login required'], 401);
    }

    $tokenHash = hash_auth_token($token);
    $stmt = $conn->prepare("SELECT id, auth_token FROM users WHERE user_uuid = ? AND (auth_token = ? OR auth_token = ?) LIMIT 1");
    $stmt->bind_param('sss', $userId, $tokenHash, $token);
    $stmt->execute();
    $user = app_stmt_fetch_assoc($stmt);
    if (!$user) {
        app_json_response(['status' => 'error', 'message' => 'Login expired'], 401);
    }

    if (hash_equals((string)$user['auth_token'], $token)) {
        $stmt = $conn->prepare("UPDATE users SET auth_token = ? WHERE user_uuid = ?");
        $stmt->bind_param('ss', $tokenHash, $userId);
        $stmt->execute();
    }
}

ensure_auth_schema($conn);

if ($action === 'register') {
    app_require_method('POST');
    app_rate_limit($conn, 'auth', 20, 300);
    app_require_content_length(64 * 1024);
    $data = app_read_json_body();
    $email = trim((string)($data['email'] ?? ''));
    $password = (string)($data['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        app_json_response(['status' => 'error', 'message' => '邮箱格式不正确'], 400);
    }
    if (strlen($password) < 6) {
        app_json_response(['status' => 'error', 'message' => '密码至少需要 6 位'], 400);
    }

    $hashedPass = password_hash($password, PASSWORD_DEFAULT);
    $userId = bin2hex(random_bytes(16));
    $authToken = bin2hex(random_bytes(32));
    $authTokenHash = hash_auth_token($authToken);

    $stmt = $conn->prepare("INSERT INTO users (email, password, user_uuid, auth_token) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $email, $hashedPass, $userId, $authTokenHash);
    if (!$stmt->execute()) {
        app_json_response(['status' => 'error', 'message' => '邮箱已被注册'], 409);
    }

    app_json_response(['status' => 'success', 'user_id' => $userId, 'auth_token' => $authToken]);
}

if ($action === 'login') {
    app_require_method('POST');
    app_rate_limit($conn, 'auth', 30, 300);
    app_require_content_length(64 * 1024);
    $data = app_read_json_body();
    $email = trim((string)($data['email'] ?? ''));
    $password = (string)($data['password'] ?? '');

    $stmt = $conn->prepare("SELECT password, user_uuid FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $user = app_stmt_fetch_assoc($stmt);

    if (!$user || !password_verify($password, $user['password'])) {
        app_json_response(['status' => 'error', 'message' => '账号或密码错误'], 401);
    }

    $authToken = bin2hex(random_bytes(32));
    $authTokenHash = hash_auth_token($authToken);
    $stmt = $conn->prepare("UPDATE users SET auth_token = ? WHERE user_uuid = ?");
    $stmt->bind_param('ss', $authTokenHash, $user['user_uuid']);
    $stmt->execute();

    app_json_response(['status' => 'success', 'user_id' => $user['user_uuid'], 'auth_token' => $authToken]);
}

if ($action === 'pull') {
    app_require_method('GET');
    app_rate_limit($conn, 'cloud_pull', 240, 300);
    $userId = app_normalize_user_id($_GET['user_id'] ?? '');
    require_user_token($conn, $userId);

    $stmt = $conn->prepare("SELECT settings, investigators, saves, tavern_novels FROM user_data WHERE user_id = ? LIMIT 1");
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $row = app_stmt_fetch_assoc($stmt);
    if (!$row) app_json_response(null);

    $settings = sanitize_cloud_settings(json_column($row['settings']));
    app_json_response([
        'settings' => $settings,
        'investigators' => json_column($row['investigators']),
        'saves' => json_column($row['saves']),
        'tavern_novels' => json_column($row['tavern_novels']),
    ]);
}

if ($action === 'sync') {
    app_require_method('POST');
    app_rate_limit($conn, 'cloud_sync', 120, 300);
    app_require_content_length($maxSyncBytes);
    $data = app_read_json_body();
    $userId = app_normalize_user_id($data['user_id'] ?? '');
    require_user_token($conn, $userId);

    $settings = sanitize_cloud_settings($data['settings'] ?? []);
    $investigators = $data['investigators'] ?? [];
    $saves = $data['saves'] ?? [];
    $tavernNovels = $data['tavern_novels'] ?? [];

    $stmt = $conn->prepare("REPLACE INTO user_data (user_id, settings, investigators, saves, tavern_novels) VALUES (?, ?, ?, ?, ?)");
    $settingsJson = json_encode($settings, JSON_UNESCAPED_UNICODE);
    $investigatorsJson = json_encode($investigators, JSON_UNESCAPED_UNICODE);
    $savesJson = json_encode($saves, JSON_UNESCAPED_UNICODE);
    $tavernNovelsJson = json_encode($tavernNovels, JSON_UNESCAPED_UNICODE);
    $stmt->bind_param('sssss', $userId, $settingsJson, $investigatorsJson, $savesJson, $tavernNovelsJson);

    app_json_response(['status' => $stmt->execute() ? 'success' : 'error']);
}

if ($action === 'logout') {
    app_require_method('POST');
    app_rate_limit($conn, 'auth_logout', 60, 300);
    app_require_content_length(64 * 1024);
    $data = app_read_json_body();
    $userId = app_normalize_user_id($data['user_id'] ?? '');
    require_user_token($conn, $userId);

    $stmt = $conn->prepare("UPDATE users SET auth_token = NULL WHERE user_uuid = ?");
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    app_json_response(['status' => 'success']);
}

app_json_response(['status' => 'error', 'message' => 'Unknown action'], 404);
?>
