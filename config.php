<?php
// config.php
// Paste your InfinityFree MySQL credentials here.

define('DB_HOST', 'YOUR_DB_HOST');   // e.g. sql211.infinityfree.com
define('DB_USER', 'YOUR_DB_USER');   // e.g. if0_...
define('DB_PASS', 'YOUR_DB_PASS');   // your MySQL password
define('DB_NAME', 'YOUR_DB_NAME');   // e.g. if0_..._xxx
define('DB_PORT', 3306);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db(): mysqli {
  try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
  } catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'DB connection failed']);
    exit;
  }
}
