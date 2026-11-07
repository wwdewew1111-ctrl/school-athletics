<?php
declare(strict_types=1);
require_once __DIR__.'/includes/db.php';
require_once __DIR__.'/includes/news.php';
$path = (string) ($_GET['path'] ?? '');
if (!preg_match('~^uploads/news/[a-f0-9]{32}\.(?:jpg|png)$~D', $path)) { http_response_code(404); exit('Not found'); }
$pdo = db();
news_schema($pdo);
$stmt = $pdo->prepare('SELECT 1 FROM news WHERE image=? AND published=1 LIMIT 1');
$stmt->execute([$path]);
$file = news_image_file($path);
if (!$stmt->fetchColumn() || !is_file($file)) { http_response_code(404); exit('Not found'); }
header('Content-Type: '.(str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg'));
header('X-Content-Type-Options: nosniff');
header('Content-Length: '.filesize($file));
readfile($file);
