<?php
ini_set('display_errors', '0');

function app_config() {
    static $config = null;
    if ($config !== null) return $config;

    $examplePath = __DIR__ . '/config.example.php';
    $localPath = __DIR__ . '/config.local.php';
    $config = file_exists($examplePath) ? require $examplePath : [];

    if (file_exists($localPath)) {
        $localConfig = require $localPath;
        if (is_array($localConfig)) {
            $config = array_replace_recursive($config, $localConfig);
        }
    }

    return is_array($config) ? $config : [];
}

function app_send_cors_headers($methods = 'GET, POST, OPTIONS') {
    $config = app_config();
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $allowedOrigins = $config['allowed_origins'] ?? [];

    if ($origin !== '') {
        header('Vary: Origin');
        if (in_array('*', $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: *');
        } elseif (in_array($origin, $allowedOrigins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        }
    }

    header('Access-Control-Allow-Methods: ' . $methods);
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 600');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function app_json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function app_read_json_body() {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') return [];

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        app_json_response(['status' => 'error', 'message' => 'Invalid JSON'], 400);
    }
    return $data;
}

function app_require_content_length($maxBytes) {
    $maxBytes = max(1, (int)$maxBytes);
    $length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > $maxBytes) {
        app_json_response(['status' => 'error', 'message' => 'Request body too large'], 413);
    }
}

function app_require_method($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        app_json_response(['status' => 'error', 'message' => 'Method not allowed'], 405);
    }
}

function app_db() {
    $db = app_config()['db'] ?? [];
    foreach (['host', 'user', 'database'] as $key) {
        if (empty($db[$key]) || strpos((string)$db[$key], 'your_database_') === 0) {
            app_json_response(['status' => 'error', 'message' => 'Database is not configured'], 500);
        }
    }

    $conn = new mysqli($db['host'], $db['user'], $db['password'] ?? '', $db['database']);
    if ($conn->connect_error) {
        app_json_response(['status' => 'error', 'message' => 'Database connection failed'], 500);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function app_stmt_fetch_assoc($stmt) {
    if (method_exists($stmt, 'get_result')) {
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc() : null;
    }

    $meta = $stmt->result_metadata();
    if (!$meta) return null;

    $row = [];
    $refs = [];
    while ($field = $meta->fetch_field()) {
        $row[$field->name] = null;
        $refs[] = &$row[$field->name];
    }
    call_user_func_array([$stmt, 'bind_result'], $refs);
    if (!$stmt->fetch()) return null;

    $copy = [];
    foreach ($row as $key => $value) {
        $copy[$key] = $value;
    }
    return $copy;
}

function app_stmt_fetch_all_assoc($stmt) {
    if (method_exists($stmt, 'get_result')) {
        $result = $stmt->get_result();
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    $meta = $stmt->result_metadata();
    if (!$meta) return [];

    $row = [];
    $refs = [];
    while ($field = $meta->fetch_field()) {
        $row[$field->name] = null;
        $refs[] = &$row[$field->name];
    }
    call_user_func_array([$stmt, 'bind_result'], $refs);

    $rows = [];
    while ($stmt->fetch()) {
        $copy = [];
        foreach ($row as $key => $value) {
            $copy[$key] = $value;
        }
        $rows[] = $copy;
    }
    return $rows;
}

function app_get_bearer_token() {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$auth && function_exists('getallheaders')) {
        $headers = getallheaders();
        foreach ($headers as $name => $value) {
            if (strtolower($name) === 'authorization') {
                $auth = $value;
                break;
            }
        }
    }

    if (preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
        return trim($matches[1]);
    }
    return '';
}

function app_normalize_user_id($userId) {
    $userId = trim((string)$userId);
    if ($userId === '' || strlen($userId) > 64 || !preg_match('/^[a-zA-Z0-9_-]+$/', $userId)) {
        app_json_response(['status' => 'error', 'message' => 'Invalid user id'], 400);
    }
    return $userId;
}

function app_client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return substr($ip, 0, 45);
}

function app_rate_limit($conn, $scope, $limit, $windowSeconds) {
    $scope = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$scope);
    $limit = max(1, (int)$limit);
    $windowSeconds = max(10, (int)$windowSeconds);
    $bucket = app_client_ip() . ':' . $scope;

    $conn->query("CREATE TABLE IF NOT EXISTS api_rate_limits (
        bucket VARCHAR(128) NOT NULL,
        hits INT UNSIGNED NOT NULL DEFAULT 0,
        window_start TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (bucket),
        KEY idx_window_start (window_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (mt_rand(1, 100) === 1) {
        $conn->query("DELETE FROM api_rate_limits WHERE window_start < (NOW() - INTERVAL 1 DAY)");
    }

    $stmt = $conn->prepare("SELECT hits, UNIX_TIMESTAMP(window_start) AS started_at FROM api_rate_limits WHERE bucket = ? LIMIT 1");
    $stmt->bind_param('s', $bucket);
    $stmt->execute();
    $row = app_stmt_fetch_assoc($stmt);
    $now = time();

    if (!$row || ($now - (int)$row['started_at']) >= $windowSeconds) {
        $hits = 1;
        $stmt = $conn->prepare("REPLACE INTO api_rate_limits (bucket, hits, window_start) VALUES (?, ?, NOW())");
        $stmt->bind_param('si', $bucket, $hits);
        $stmt->execute();
        return;
    }

    if ((int)$row['hits'] >= $limit) {
        header('Retry-After: ' . max(1, $windowSeconds - ($now - (int)$row['started_at'])));
        app_json_response(['status' => 'error', 'message' => 'Too many requests'], 429);
    }

    $stmt = $conn->prepare("UPDATE api_rate_limits SET hits = hits + 1 WHERE bucket = ?");
    $stmt->bind_param('s', $bucket);
    $stmt->execute();
}
