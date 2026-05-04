<?php
require_once __DIR__ . '/../api_bootstrap.php';

app_send_cors_headers('POST, OPTIONS');
app_require_method('POST');
app_require_content_length(2 * 1024 * 1024 + 32768);

$data = $_POST['data'] ?? '';
if (!is_string($data) || $data === '') {
    http_response_code(400);
    echo 'Missing data';
    exit;
}

if (strlen($data) > 2 * 1024 * 1024) {
    http_response_code(413);
    echo 'File too large';
    exit;
}

header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="character_card.json"');
echo $data;
?>
