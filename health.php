<?php
require_once __DIR__ . '/api_bootstrap.php';

app_require_method('GET');

$conn = app_db();
try {
    $result = $conn->query('SELECT 1');
} catch (Throwable $error) {
    $result = false;
}
if ($result === false) {
    app_json_response(['status' => 'error'], 503);
}

$conn->close();
app_json_response(['status' => 'ok']);
