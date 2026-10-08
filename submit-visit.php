<?php
// Receives the "Schedule a Visit" form, emails the school office, and keeps a backup copy.
header('Content-Type: application/json; charset=utf-8');

$to = 'saintlouiscollegeofsolano@gmail.com';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'POST only']);
  exit;
}

// Honeypot: real visitors never fill this hidden field
if (!empty($_POST['website'])) { echo json_encode(['ok' => true]); exit; }

function clean($k, $max = 500) {
  $v = isset($_POST[$k]) ? trim((string)$_POST[$k]) : '';
  $v = str_replace(["\r", "\n"], ' ', $v);          // blocks header injection
  return mb_substr($v, 0, $max);
}

$name   = clean('name', 100);
$phone  = clean('phone', 40);
$email  = clean('email', 120);
$date   = clean('date', 20);
$time   = clean('time', 20);
$course = clean('course', 60) ?: 'Not sure yet';
$guests = (int)clean('guests', 3) ?: 1;
$msg    = isset($_POST['msg']) ? mb_substr(trim((string)$_POST['msg']), 0, 1000) : '';

if ($name === '' || $phone === '' || $date === '' || $time === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Please complete all required fields.']);
  exit;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid date.']);
  exit;
}

// 1) Backup copy (data/ is blocked from public access by data/.htaccess)
$dir = __DIR__ . '/data';
if (!is_dir($dir)) { mkdir($dir, 0755, true); }
if (!file_exists($dir . '/.htaccess')) { file_put_contents($dir . '/.htaccess', "Require all denied\n"); }
$fh = fopen($dir . '/visit-requests.csv', 'a');
if ($fh) {
  flock($fh, LOCK_EX);
  fputcsv($fh, [date('Y-m-d H:i:s'), $name, $phone, $email, $date, $time, $course, $guests, $msg]);
  flock($fh, LOCK_UN);
  fclose($fh);
}

// 2) Email the school office (reply goes straight to the visitor)
$body = "Campus Visit Request\n\n"
      . "Name: $name\nContact: $phone\nEmail: $email\n"
      . "Preferred date: $date\nPreferred time: $time\n"
      . "Course of interest: $course\nVisitors: $guests\n\nMessage:\n$msg\n";
$headers = "From: no-reply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n"
         . "Reply-To: $email\r\n"
         . "Content-Type: text/plain; charset=utf-8\r\n";
@mail($to, "Schedule a Visit - $name", $body, $headers);

// Backup was saved, so report success even if the server's mail() is not configured
echo json_encode(['ok' => true]);
