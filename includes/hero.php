<?php
declare(strict_types=1);

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
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) return '';
    if ($error !== UPLOAD_ERR_OK || !is_int($file['size'] ?? null) || $file['size'] < 1 || $file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Не удалось загрузить фотографию (максимум 10 МБ).');
    }
    $info = @getimagesize((string) ($file['tmp_name'] ?? ''));
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]]) || $info[0] + $info[1] > 10000 || max($info[0] / $info[1], $info[1] / $info[0]) > 20) {
        throw new RuntimeException('Нужна фотография JPEG, PNG или WebP с допустимыми размерами.');
    }
    $dir = dirname(__DIR__).'/uploads/hero';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Не удалось создать папку для фона.');
    $path = 'uploads/hero/'.bin2hex(random_bytes(16)).'.'.$types[$info[2]];
    if (!move_uploaded_file((string) $file['tmp_name'], dirname(__DIR__).'/'.$path)) throw new RuntimeException('Не удалось сохранить фотографию.');
    return $path;
}
