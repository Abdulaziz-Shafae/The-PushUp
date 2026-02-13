<?php
// config.php
// InfinityFree MySQL credentials.

define('DB_HOST', 'sql211.infinityfree.com');
define('DB_USER', 'if0_41117992');
define('DB_PASS', '50UJEU42MoH');
define('DB_NAME', 'if0_41117992_pushup');
define('DB_DEBUG', true);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db(): mysqli {
  // Common misconfiguration: DB password accidentally set to DB host.
  if (DB_PASS === DB_HOST) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'DB connection failed', 'detail' => 'DB_PASS is misconfigured']);
    exit;
  }

  try {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
  } catch (Throwable $e) {
    error_log('DB connection failed: ' . $e->getMessage());

    http_response_code(500);
    header('Content-Type: application/json');

    if (DB_DEBUG) {
      echo json_encode(['error' => 'DB connection failed', 'detail' => $e->getMessage()]);
    } else {
      echo json_encode(['error' => 'DB connection failed']);
    }

    exit;
  }
}
