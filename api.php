<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

set_exception_handler(function (Throwable $e): void {
  http_response_code(500);
  echo json_encode(['error' => 'Server error']);
  exit;
});

function bad_request(string $msg): void {
  http_response_code(400);
  echo json_encode(['error' => $msg]);
  exit;
}

function get_json_body(): array {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function str_len(string $s): int {
  if (function_exists('mb_strlen')) return mb_strlen($s);
  return strlen($s);
}

function is_valid_profile_id(string $id): bool {
  return (bool)preg_match('/^[a-zA-Z0-9\-_]{6,32}$/', $id);
}

function is_valid_date(string $d): bool {
  return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
}

function date_to_ts(string $d): int {
  return strtotime($d . ' 00:00:00');
}

function ts_to_date(int $ts): string {
  return date('Y-m-d', $ts);
}

function get_month_range(string $month): array {
  if (!preg_match('/^\d{4}-\d{2}$/', $month)) bad_request('Invalid month');
  $start = $month . '-01';
  $startTs = date_to_ts($start);
  $endTs = strtotime(date('Y-m-01', $startTs) . ' +1 month');
  $end = date('Y-m-d', $endTs); // exclusive
  return [$start, $end];
}

function list_profiles(mysqli $db): array {
  $res = $db->query("SELECT profile_id, name FROM profiles ORDER BY created_at ASC");
  $out = [];
  while ($r = $res->fetch_assoc()) $out[] = $r;
  return $out;
}

function profile_exists(mysqli $db, string $profile_id): bool {
  $stmt = $db->prepare("SELECT 1 FROM profiles WHERE profile_id=? LIMIT 1");
  $stmt->bind_param("s", $profile_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $ok = (bool)$res->fetch_row();
  $stmt->close();
  return $ok;
}

function fetch_month_status(mysqli $db, string $profile_id, string $monthStart, string $monthEnd): array {
  $stmt = $db->prepare("SELECT day_date, completed FROM pushup_days WHERE profile_id=? AND day_date>=? AND day_date<?");
  $stmt->bind_param("sss", $profile_id, $monthStart, $monthEnd);
  $stmt->execute();
  $res = $stmt->get_result();
  $map = [];
  while ($row = $res->fetch_assoc()) $map[$row['day_date']] = ((int)$row['completed'] === 1);
  $stmt->close();
  return $map;
}

function count_completed_before(mysqli $db, string $profile_id, string $beforeDate): int {
  $stmt = $db->prepare("SELECT COUNT(*) AS c FROM pushup_days WHERE profile_id=? AND completed=1 AND day_date<?");
  $stmt->bind_param("ss", $profile_id, $beforeDate);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return (int)($res['c'] ?? 0);
}

function count_completed_total(mysqli $db, string $profile_id): int {
  $stmt = $db->prepare("SELECT COUNT(*) AS c FROM pushup_days WHERE profile_id=? AND completed=1");
  $stmt->bind_param("s", $profile_id);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  return (int)($res['c'] ?? 0);
}

function fetch_completed_set(mysqli $db, string $profile_id): array {
  $stmt = $db->prepare("SELECT day_date FROM pushup_days WHERE profile_id=? AND completed=1");
  $stmt->bind_param("s", $profile_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $set = [];
  while ($row = $res->fetch_assoc()) $set[$row['day_date']] = true;
  $stmt->close();
  return $set;
}

function compute_streak(array $completedSet): int {
  $todayTs = date_to_ts(date('Y-m-d'));
  $today = ts_to_date($todayTs);
  $ts = !empty($completedSet[$today]) ? $todayTs : strtotime('-1 day', $todayTs);
  $streak = 0;
  for ($i = 0; $i < 3650; $i++) {
    $d = ts_to_date($ts);
    if (!empty($completedSet[$d])) {
      $streak++;
      $ts = strtotime('-1 day', $ts);
    } else {
      break;
    }
  }
  return $streak;
}

$action = $_GET['action'] ?? '';
if (!$action) bad_request('Missing action');

$db = db();

if ($action === 'profiles_list') {
  echo json_encode(['profiles' => list_profiles($db)]);
  exit;
}

if ($action === 'profiles_create') {
  $body = get_json_body();
  $name = trim((string)($body['name'] ?? ''));

  if ($name === '' || str_len($name) > 40) bad_request('Invalid name');

  $profile_id = substr(bin2hex(random_bytes(16)), 0, 12);

  $stmt = $db->prepare("INSERT INTO profiles (profile_id, name) VALUES (?, ?)");
  $stmt->bind_param("ss", $profile_id, $name);
  $stmt->execute();
  $stmt->close();

  echo json_encode(['ok' => true, 'profile_id' => $profile_id]);
  exit;
}

if ($action === 'profiles_rename') {
  $body = get_json_body();
  $profile_id = (string)($body['profile_id'] ?? '');
  $name = trim((string)($body['name'] ?? ''));

  if (!is_valid_profile_id($profile_id)) bad_request('Invalid profile_id');
  if ($name === '' || str_len($name) > 40) bad_request('Invalid name');
  if (!profile_exists($db, $profile_id)) bad_request('Profile not found');

  $stmt = $db->prepare("UPDATE profiles SET name=? WHERE profile_id=?");
  $stmt->bind_param("ss", $name, $profile_id);
  $stmt->execute();
  $stmt->close();

  echo json_encode(['ok' => true]);
  exit;
}

if ($action === 'profiles_delete') {
  $body = get_json_body();
  $profile_id = (string)($body['profile_id'] ?? '');

  if (!is_valid_profile_id($profile_id)) bad_request('Invalid profile_id');
  if (!profile_exists($db, $profile_id)) bad_request('Profile not found');

  $stmt = $db->prepare("DELETE FROM pushup_days WHERE profile_id=?");
  $stmt->bind_param("s", $profile_id);
  $stmt->execute();
  $stmt->close();

  $stmt = $db->prepare("DELETE FROM profiles WHERE profile_id=?");
  $stmt->bind_param("s", $profile_id);
  $stmt->execute();
  $stmt->close();

  echo json_encode(['ok' => true]);
  exit;
}

if ($action === 'state') {
  $profile_id = (string)($_GET['profile_id'] ?? '');
  $month = $_GET['month'] ?? date('Y-m');

  if (!is_valid_profile_id($profile_id)) bad_request('Invalid profile_id');
  if (!profile_exists($db, $profile_id)) bad_request('Profile not found');

  [$monthStart, $monthEnd] = get_month_range($month);

  $monthMap = fetch_month_status($db, $profile_id, $monthStart, $monthEnd);
  $completedBeforeMonth = count_completed_before($db, $profile_id, $monthStart);

  $days = [];
  $startTs = date_to_ts($monthStart);
  $endTs = date_to_ts($monthEnd);
  $counter = $completedBeforeMonth;

  for ($ts = $startTs; $ts < $endTs; $ts = strtotime('+1 day', $ts)) {
    $date = ts_to_date($ts);
    $target = 1 + $counter;
    $completed = $monthMap[$date] ?? false;

    $days[] = [
      'date' => $date,
      'target' => $target,
      'completed' => $completed,
    ];

    if ($completed) $counter++;
  }

  $completedTotal = count_completed_total($db, $profile_id);
  $completedSet = fetch_completed_set($db, $profile_id);

  $today = date('Y-m-d');
  $stats = [
    'totalCompletedDays' => $completedTotal,
    'totalPushupsCompleted' => (int)(($completedTotal * ($completedTotal + 1)) / 2),
    'currentStreak' => compute_streak($completedSet),
    'nextTarget' => $completedTotal + 1,
    'todayCompleted' => !empty($completedSet[$today]),
  ];

  echo json_encode([
    'month' => $month,
    'days' => $days,
    'stats' => $stats,
  ]);
  exit;
}

if ($action === 'toggle') {
  $body = get_json_body();
  $profile_id = (string)($body['profile_id'] ?? '');
  $date = (string)($body['date'] ?? '');

  if (!is_valid_profile_id($profile_id)) bad_request('Invalid profile_id');
  if (!is_valid_date($date)) bad_request('Invalid date');
  if (!profile_exists($db, $profile_id)) bad_request('Profile not found');

  $stmt = $db->prepare("SELECT completed FROM pushup_days WHERE profile_id=? AND day_date=?");
  $stmt->bind_param("ss", $profile_id, $date);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res->fetch_assoc();
  $stmt->close();

  $current = $row ? ((int)$row['completed'] === 1) : false;
  $next = $current ? 0 : 1;

  if ($row) {
    $stmt = $db->prepare("UPDATE pushup_days SET completed=? WHERE profile_id=? AND day_date=?");
    $stmt->bind_param("iss", $next, $profile_id, $date);
  } else {
    $stmt = $db->prepare("INSERT INTO pushup_days (profile_id, day_date, completed) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $profile_id, $date, $next);
  }
  $stmt->execute();
  $stmt->close();

  echo json_encode(['ok' => true, 'date' => $date, 'completed' => ($next === 1)]);
  exit;
}

bad_request('Unknown action');
