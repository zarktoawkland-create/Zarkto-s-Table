<?php
require_once __DIR__ . '/api_bootstrap.php';

app_send_cors_headers('GET, POST, OPTIONS');

$conn = app_db();
$libraryConfig = app_config()['library'] ?? [];
$maxContentBytes = max(1024, (int)($libraryConfig['max_content_bytes'] ?? (4 * 1024 * 1024)));
$listLimit = min(100, max(1, (int)($libraryConfig['list_limit'] ?? 60)));

function library_ensure_schema($conn) {
    return (bool)$conn->query("CREATE TABLE IF NOT EXISTS coc_library_modules (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        title VARCHAR(255) NOT NULL,
        description TEXT NULL,
        content LONGTEXT NOT NULL,
        author_name VARCHAR(120) NULL,
        author_id VARCHAR(64) NOT NULL,
        owner_token_hash CHAR(64) NOT NULL,
        type VARCHAR(32) NOT NULL DEFAULT 'original',
        downloads BIGINT UNSIGNED NOT NULL DEFAULT 0,
        min_players INT NULL,
        max_players INT NULL,
        PRIMARY KEY (id),
        KEY idx_type_created (type, created_at),
        KEY idx_author_id (author_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function library_text($value, $maxBytes, $field, $required = false) {
    $value = trim((string)$value);
    if ($required && $value === '') {
        app_json_response(['status' => 'error', 'message' => $field . ' is required'], 400);
    }
    if (strlen($value) > $maxBytes) {
        app_json_response(['status' => 'error', 'message' => $field . ' is too long'], 413);
    }
    return $value;
}

function library_type($type) {
    $type = (string)$type;
    $allowed = ['original', 'reprint', 'ai_adapt', 'ai_adapt_private'];
    if (!in_array($type, $allowed, true)) {
        app_json_response(['status' => 'error', 'message' => 'Invalid module type'], 400);
    }
    return $type;
}

function library_owner_hash($token) {
    $token = trim((string)$token);
    if (!preg_match('/^[a-f0-9]{32,128}$/i', $token)) {
        app_json_response(['status' => 'error', 'message' => 'Invalid owner token'], 400);
    }
    return hash('sha256', strtolower($token));
}

function library_nullable_int($value) {
    if ($value === null || $value === '') return null;
    $value = (int)$value;
    if ($value < 1 || $value > 20) {
        app_json_response(['status' => 'error', 'message' => 'Invalid player count'], 400);
    }
    return $value;
}

function library_module_row($row, $includeContent = false) {
    $module = [
        'id' => (int)$row['id'],
        'created_at' => $row['created_at'],
        'title' => $row['title'],
        'description' => $row['description'],
        'author_name' => $row['author_name'],
        'author_id' => $row['author_id'],
        'type' => $row['type'],
        'downloads' => (int)$row['downloads'],
        'min_players' => $row['min_players'] === null ? null : (int)$row['min_players'],
        'max_players' => $row['max_players'] === null ? null : (int)$row['max_players'],
        'content_length' => isset($row['content_length'])
            ? (int)$row['content_length']
            : strlen((string)($row['content'] ?? '')),
    ];
    if ($includeContent) {
        $module['content'] = (string)($row['content'] ?? '');
    }
    return $module;
}

function library_can_read_private_module($row) {
    $userId = app_normalize_user_id($_GET['user_id'] ?? '');
    $ownerToken = app_get_bearer_token();
    if (!preg_match('/^[a-f0-9]{32,128}$/i', $ownerToken)) return false;
    $ownerHash = hash('sha256', strtolower($ownerToken));
    return (string)$row['author_id'] === $userId
        && hash_equals((string)$row['owner_token_hash'], $ownerHash);
}

app_run_periodically('library_schema_v1', 3600, function () use ($conn) {
    return library_ensure_schema($conn);
});

$action = $_GET['action'] ?? '';

if ($action === 'list') {
    app_require_method('GET');
    app_rate_limit($conn, 'library_list', 240, 300);

    $section = ($_GET['section'] ?? 'reading') === 'restricted' ? 'restricted' : 'reading';
    $query = trim((string)($_GET['q'] ?? ''));
    if (strlen($query) > 80) $query = substr($query, 0, 80);
    $like = '%' . $query . '%';
    $modules = [];
    $columns = 'id, created_at, title, description, author_name, author_id, type, downloads, min_players, max_players, CHAR_LENGTH(content) AS content_length';

    if ($section === 'restricted') {
        $userId = trim((string)($_GET['user_id'] ?? ''));
        if ($userId !== '') $userId = app_normalize_user_id($userId);
        $ownerToken = app_get_bearer_token();
        $ownerHash = preg_match('/^[a-f0-9]{32,128}$/i', $ownerToken)
            ? hash('sha256', strtolower($ownerToken))
            : str_repeat('0', 64);

        if ($query !== '') {
            $stmt = $conn->prepare("SELECT {$columns}
                FROM coc_library_modules
                WHERE (type = 'ai_adapt' OR (type = 'ai_adapt_private' AND author_id = ? AND owner_token_hash = ?)) AND title LIKE ?
                ORDER BY created_at DESC LIMIT {$listLimit}");
            $stmt->bind_param('sss', $userId, $ownerHash, $like);
        } else {
            $stmt = $conn->prepare("SELECT {$columns}
                FROM coc_library_modules
                WHERE type = 'ai_adapt' OR (type = 'ai_adapt_private' AND author_id = ? AND owner_token_hash = ?)
                ORDER BY created_at DESC LIMIT {$listLimit}");
            $stmt->bind_param('ss', $userId, $ownerHash);
        }
    } else {
        if ($query !== '') {
            $stmt = $conn->prepare("SELECT {$columns}
                FROM coc_library_modules
                WHERE type IN ('original', 'reprint') AND title LIKE ?
                ORDER BY created_at DESC LIMIT {$listLimit}");
            $stmt->bind_param('s', $like);
        } else {
            $stmt = $conn->prepare("SELECT {$columns}
                FROM coc_library_modules
                WHERE type IN ('original', 'reprint')
                ORDER BY created_at DESC LIMIT {$listLimit}");
        }
    }

    if (!$stmt->execute()) {
        app_json_response(['status' => 'error', 'message' => 'Library query failed'], 500);
    }

    foreach (app_stmt_fetch_all_assoc($stmt) as $row) {
        $modules[] = library_module_row($row);
    }

    app_json_response(['status' => 'success', 'modules' => $modules]);
}

if ($action === 'detail') {
    app_require_method('GET');
    app_rate_limit($conn, 'library_detail', 300, 300);
    $id = (int)($_GET['id'] ?? 0);
    if ($id < 1) app_json_response(['status' => 'error', 'message' => 'Invalid module id'], 400);

    $stmt = $conn->prepare("SELECT id, created_at, title, description, content, author_name, author_id, owner_token_hash, type, downloads, min_players, max_players, CHAR_LENGTH(content) AS content_length
        FROM coc_library_modules WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $module = app_stmt_fetch_assoc($stmt);
    if (!$module) app_json_response(['status' => 'error', 'message' => 'Module not found'], 404);

    $isPublic = in_array($module['type'], ['original', 'reprint', 'ai_adapt'], true);
    if (!$isPublic && !library_can_read_private_module($module)) {
        app_json_response(['status' => 'error', 'message' => 'Module access denied'], 403);
    }

    app_json_response(['status' => 'success', 'module' => library_module_row($module, true)]);
}

if ($action === 'create') {
    app_require_method('POST');
    app_rate_limit($conn, 'library_create', 60, 300);
    app_require_content_length($maxContentBytes + 32768);
    $data = app_read_json_body();

    $title = library_text($data['title'] ?? '', 255, 'Title', true);
    $description = library_text($data['description'] ?? '', 4000, 'Description');
    $content = library_text($data['content'] ?? '', $GLOBALS['maxContentBytes'], 'Content', true);
    $authorName = library_text($data['author_name'] ?? '游客', 120, 'Author name');
    $authorId = app_normalize_user_id($data['author_id'] ?? '');
    $ownerHash = library_owner_hash($data['owner_token'] ?? '');
    $type = library_type($data['type'] ?? 'original');
    $minPlayers = library_nullable_int($data['min_players'] ?? null);
    $maxPlayers = library_nullable_int($data['max_players'] ?? null);
    if ($minPlayers !== null && $maxPlayers !== null && $minPlayers > $maxPlayers) {
        app_json_response(['status' => 'error', 'message' => 'Minimum players cannot exceed maximum players'], 400);
    }
    $minPlayersParam = $minPlayers === null ? '' : (string)$minPlayers;
    $maxPlayersParam = $maxPlayers === null ? '' : (string)$maxPlayers;

    $stmt = $conn->prepare("INSERT INTO coc_library_modules
        (title, description, content, author_name, author_id, owner_token_hash, type, min_players, max_players)
        VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), NULLIF(?, ''))");
    $stmt->bind_param('sssssssss', $title, $description, $content, $authorName, $authorId, $ownerHash, $type, $minPlayersParam, $maxPlayersParam);
    if (!$stmt->execute()) {
        app_json_response(['status' => 'error', 'message' => 'Library insert failed'], 500);
    }

    app_json_response(['status' => 'success', 'id' => (int)$stmt->insert_id]);
}

if ($action === 'delete') {
    app_require_method('POST');
    app_rate_limit($conn, 'library_delete', 60, 300);
    app_require_content_length(64 * 1024);
    $data = app_read_json_body();

    $id = (int)($data['id'] ?? 0);
    if ($id < 1) app_json_response(['status' => 'error', 'message' => 'Invalid module id'], 400);
    $authorId = app_normalize_user_id($data['author_id'] ?? '');
    $ownerHash = library_owner_hash($data['owner_token'] ?? '');

    $stmt = $conn->prepare("SELECT author_id, owner_token_hash FROM coc_library_modules WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $module = app_stmt_fetch_assoc($stmt);
    if (!$module) app_json_response(['status' => 'error', 'message' => 'Module not found'], 404);

    if ($module['author_id'] !== $authorId || !hash_equals($module['owner_token_hash'], $ownerHash)) {
        app_json_response(['status' => 'error', 'message' => 'You can only delete modules from this browser'], 403);
    }

    $stmt = $conn->prepare("DELETE FROM coc_library_modules WHERE id = ?");
    $stmt->bind_param('i', $id);
    if (!$stmt->execute()) {
        app_json_response(['status' => 'error', 'message' => 'Library delete failed'], 500);
    }

    app_json_response(['status' => 'success']);
}

if ($action === 'increment_downloads') {
    app_require_method('POST');
    app_rate_limit($conn, 'library_download', 300, 300);
    app_require_content_length(64 * 1024);
    $data = app_read_json_body();
    $id = (int)($data['id'] ?? 0);
    if ($id < 1) app_json_response(['status' => 'error', 'message' => 'Invalid module id'], 400);

    $stmt = $conn->prepare("UPDATE coc_library_modules SET downloads = downloads + 1 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();

    app_json_response(['status' => 'success']);
}

app_json_response(['status' => 'error', 'message' => 'Unknown action'], 404);
