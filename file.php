<?php
declare(strict_types=1);

require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/upload_names.php';

$path = (string) ($_GET['path'] ?? '');
if (!preg_match('~^uploads/(?:docs|admission)/[A-Za-z0-9_-]+\.(pdf|doc|docx|xlsx)$~iD', $path)) {
    http_response_code(404);
    exit('Not found');
}
$root = realpath(__DIR__.'/'.dirname($path));
$file = realpath(__DIR__.'/'.$path);
if ($root === false || $file === false || dirname($file) !== $root || !is_file($file)) {
    http_response_code(404);
    exit('Not found');
}

$pdo = db();
ensure_upload_names($pdo);
$stmt = $pdo->prepare('SELECT original_name FROM upload_names WHERE path = ?');
$stmt->execute([$path]);
$name = $stmt->fetchColumn();
try {
    $name = original_upload_name(is_string($name) ? $name : basename($path));
} catch (RuntimeException $e) {
    $name = basename($path);
}
$types = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$disposition = isset($_GET['download']) ? 'attachment' : 'inline';
header('Content-Type: '.$types[$ext]);
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: '.$disposition.'; filename="document.'.$ext.'"; filename*=UTF-8\'\''.rawurlencode($name));
header('Content-Length: '.filesize($file));
readfile($file);
