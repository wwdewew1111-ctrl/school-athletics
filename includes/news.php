<?php
declare(strict_types=1);

function news_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS news (
        id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, body TEXT NOT NULL,
        published_date TEXT NOT NULL, image TEXT NOT NULL DEFAULT '', published INTEGER NOT NULL DEFAULT 0,
        vk_post_id TEXT NOT NULL DEFAULT '', vk_group_id TEXT NOT NULL DEFAULT '', vk_image TEXT NOT NULL DEFAULT '', vk_attachment TEXT NOT NULL DEFAULT '', vk_hash TEXT NOT NULL DEFAULT '', vk_status TEXT NOT NULL DEFAULT '',
        tg_message_id TEXT NOT NULL DEFAULT '', tg_chat_id TEXT NOT NULL DEFAULT '', tg_kind TEXT NOT NULL DEFAULT '', tg_image TEXT NOT NULL DEFAULT '', tg_hash TEXT NOT NULL DEFAULT '', tg_status TEXT NOT NULL DEFAULT ''
    )");
    $columns = array_column($pdo->query('PRAGMA table_info(news)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    foreach (['vk_attachment', 'vk_hash', 'tg_hash'] as $column) {
        if (!in_array($column, $columns, true)) $pdo->exec("ALTER TABLE news ADD COLUMN $column TEXT NOT NULL DEFAULT ''");
    }
}

function news_image_file(string $path): string
{
    if (!preg_match('~^uploads/news/([a-f0-9]{32}\.(?:jpg|png))$~D', $path, $match)) throw new RuntimeException('Некорректный путь к изображению.');
    return dirname(dirname(__DIR__)).'/school-athletics-news-uploads/'.$match[1];
}

function news_image_upload(array $file): string
{
    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) return '';
    if ($error !== UPLOAD_ERR_OK || ($file['size'] ?? 0) < 1 || ($file['size'] ?? 0) > 10 * 1024 * 1024) {
        throw new RuntimeException('Не удалось загрузить изображение (максимум 10 МБ).');
    }
    $info = @getimagesize((string) ($file['tmp_name'] ?? ''));
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png'];
    if (!$info || !isset($types[$info[2]]) || $info[0] + $info[1] > 10000 || max($info[0] / $info[1], $info[1] / $info[0]) > 20) {
        throw new RuntimeException('Нужно изображение JPEG или PNG с допустимыми размерами.');
    }
    $dir = dirname(dirname(__DIR__)).'/school-athletics-news-uploads';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('Не удалось создать папку изображений.');
    $filename = bin2hex(random_bytes(16)).'.'.$types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $dir.'/'.$filename)) throw new RuntimeException('Не удалось сохранить изображение.');
    return 'uploads/news/'.$filename;
}

function news_excerpt(string $text, int $limit = 180): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    preg_match_all('/./us', $text, $chars);
    return count($chars[0]) > $limit ? implode('', array_slice($chars[0], 0, $limit)).'…' : $text;
}
