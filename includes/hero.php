<?php
declare(strict_types=1);
require_once __DIR__.'/photo_upload.php';

function hero_background(PDO $pdo): string
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS site_settings (name TEXT PRIMARY KEY, value TEXT NOT NULL)');
    $stmt = $pdo->prepare('SELECT value FROM site_settings WHERE name=?');
    $stmt->execute(['hero_background']);
    $path = (string) ($stmt->fetchColumn() ?: '');
    return preg_match('~^uploads/hero/[a-f0-9]{32}\.(?:jpg|png|webp)$~D', $path) ? $path : '';
}

function hero_background_upload(array $file): string
{
    return photo_upload($file, 'hero', dirname(__DIR__).'/uploads/hero', [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'], 1920);
}
