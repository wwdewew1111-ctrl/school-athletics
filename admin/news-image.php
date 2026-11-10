<?php
declare(strict_types=1);
require_once __DIR__.'/../includes/db.php';
require_once __DIR__.'/../includes/news.php';
require_once __DIR__.'/../includes/admin_auth.php';
admin_session_start();
if (empty($_SESSION['admin'])) { http_response_code(403); exit('Forbidden'); }
$path = (string) ($_GET['path'] ?? '');
try { $file = news_image_file($path); } catch (RuntimeException $e) { http_response_code(404); exit('Not found'); }
$pdo = db();
news_schema($pdo);
$stmt = $pdo->prepare('SELECT 1 FROM news WHERE image=? LIMIT 1');
$stmt->execute([$path]);
if (!$stmt->fetchColumn() || !is_file($file)) { http_response_code(404); exit('Not found'); }
header('Content-Type: '.(str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg'));
header('X-Content-Type-Options: nosniff');
header('Content-Length: '.filesize($file));
readfile($file);
